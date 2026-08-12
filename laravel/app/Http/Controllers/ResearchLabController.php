<?php

namespace App\Http\Controllers;

use App\Models\ResearchExperiment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ResearchLabController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereRaw('LOWER(symbol) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('market_cap')
            ->orderBy('symbol');

        $stocks = $query->limit(5000)->get([
            'id', 'symbol', 'name', 'country', 'sector', 'market_cap', 'exchange_id',
        ]);
        $exchanges = DB::table('exchanges')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $experiments = ResearchExperiment::query()->where('user_id', $request->user()->id)->latest()->limit(12)->get();

        return view('research.lab', [
            'stocks' => $stocks,
            'exchanges' => $exchanges,
            'experiments' => $experiments,
            'universeCount' => $stocks->count(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'symbols' => ['required', 'array', 'min:1', 'max:50'],
            'symbols.*' => ['string', 'max:32'],
            'training_years' => ['required', 'integer', 'in:10,20,30'],
            'horizons' => ['required', 'array', 'min:1'],
            'horizons.*' => ['integer', 'in:5,10,15,20'],
            'indicators' => ['nullable', 'array'],
            'indicator_periods' => ['nullable', 'array'],
            'macros' => ['nullable', 'array'],
            'macro_lags' => ['nullable', 'array'],
            'benchmark_mode' => ['required', 'in:auto,exchange,index,sector'],
            'minimum_hit_rate' => ['required', 'numeric', 'between:0,100'],
            'minimum_profit_factor' => ['required', 'numeric', 'between:0,10'],
            'minimum_trades' => ['required', 'integer', 'between:1,10000'],
            'shadow_days' => ['required', 'integer', 'in:20,60,120'],
        ]);

        $symbols = DB::table('instruments')
            ->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')
            ->whereIn('symbol', $validated['symbols'])->pluck('symbol')->values()->all();
        abort_if($symbols === [], 422, __('Keine gültigen Aktien ausgewählt.'));

        $indicatorPeriods = collect($validated['indicator_periods'] ?? [])
            ->map(fn ($periods): array => collect(explode(',', (string) $periods))->map(fn ($period): int => (int) trim($period))->filter(fn (int $period): bool => $period >= 2 && $period <= 500)->unique()->values()->all())
            ->filter(fn (array $periods): bool => $periods !== [])
            ->all();
        $macroLags = collect($validated['macro_lags'] ?? [])
            ->map(fn ($lag): int => max(0, min(252, (int) $lag)))
            ->all();

        $configuration = [
            'training_years' => (int) $validated['training_years'],
            'horizons' => array_values(array_map('intval', $validated['horizons'])),
            'indicators' => array_values($validated['indicators'] ?? []),
            'indicator_periods' => $indicatorPeriods,
            'macros' => array_values($validated['macros'] ?? []),
            'macro_lags' => $macroLags,
            'benchmark_mode' => $validated['benchmark_mode'],
            'minimum_hit_rate' => (float) $validated['minimum_hit_rate'],
            'minimum_profit_factor' => (float) $validated['minimum_profit_factor'],
            'minimum_trades' => (int) $validated['minimum_trades'],
            'shadow_days' => (int) $validated['shadow_days'],
            'exit_timing' => ['enabled' => true, 'range' => [1, 20], 'selection' => 'risk_adjusted_test_backtest'],
            'pipeline' => ['training', 'backtest', 'walk_forward', 'noise_filter_analysis'],
            'noise_filter' => [
                'name' => 'Noise Filter',
                'method' => 'signed_forecast_area',
                'horizons' => [5, 10, 15, 20],
                'positive_net_area_required' => true,
                'stage' => 'after_walk_forward',
            ],
            'forecast_stability_filter' => [
                'name' => 'Forecast Stability Filter',
                'method' => 'weighted_consensus_mad_direction_slope',
                'horizons' => [5, 10, 15, 20],
                'minimum_stability_score' => 0.55,
                'stage' => 'after_walk_forward',
                'mode' => 'comparison_only',
            ],
        ];

        $experiment = ResearchExperiment::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'status' => 'queued',
            'stage' => 'queued',
            'symbols' => $symbols,
            'configuration' => $configuration,
        ]);

        // The worker integration consumes this durable request. It is kept in
        // the same queue table as the other Python jobs so it can run on the
        // Mac mini without blocking the web request.
        DB::table('python_engine_jobs')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'type' => 'research_experiment',
            'calculation_version' => 'research-lab-v1',
            'status' => 'queued',
            'payload' => json_encode(['experiment_id' => $experiment->id, 'configuration' => $configuration, 'symbols' => $symbols], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', __('Experiment wurde für :count Aktien in die Research-Warteschlange gestellt.', ['count' => count($symbols)]));
    }

    public function status(Request $request, ResearchExperiment $experiment): JsonResponse
    {
        abort_unless((int) $experiment->user_id === (int) $request->user()->id, 403);

        // The Mac mini worker reports progress through the durable Python job
        // queue. Mirror that state here so the browser can poll one endpoint
        // regardless of where the experiment is executed.
        $job = DB::table('python_engine_jobs')
            ->where('type', 'research_experiment')
            ->whereRaw("payload->>'experiment_id' = ?", [(string) $experiment->id])
            ->latest('id')
            ->first();
        if ($job !== null && ((string) $job->status !== (string) $experiment->status || (int) $job->progress !== (int) $experiment->progress)) {
            $stage = data_get(json_decode((string) ($job->result ?? '{}'), true), 'stage', $experiment->stage);
            $experiment->forceFill([
                'status' => (string) $job->status,
                'stage' => (string) $stage,
                'progress' => (int) $job->progress,
                'result' => $job->result ? (json_decode((string) $job->result, true) ?: $experiment->result) : $experiment->result,
                'error_message' => $job->error_message,
            ])->saveQuietly();
        }
        return response()->json([
            'status' => $experiment->status,
            'stage' => $experiment->stage,
            'progress' => $experiment->progress,
            'result' => $experiment->result,
            'error' => $experiment->error_message,
        ]);
    }
}
