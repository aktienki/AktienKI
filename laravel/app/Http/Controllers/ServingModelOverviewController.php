<?php

namespace App\Http\Controllers;

use App\Services\LocalModelFeasibilityService;
use App\Services\SavedFilterLimitService;
use App\Services\ServingModelOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ServingModelOverviewController extends Controller
{
    public function __invoke(Request $request, string $symbol, ServingModelOverviewService $models, LocalModelFeasibilityService $feasibility): View
    {
        $data = $models->data($symbol);
        // The picker above reads the external serving database - it says
        // nothing about whether AutomatedPortfolioService could ever match
        // this configuration against the local, server-scheduled prediction
        // pipeline. Without this, a strategy can be saved that never fires,
        // with no indication why. See LocalModelFeasibilityService.
        $data['horizons'] = $data['horizons']->map(function (array $horizon) use ($feasibility, $symbol): array {
            foreach ($horizon['variants'] as $variantKey => $variant) {
                $check = $feasibility->check($symbol, (int) $horizon['days'], (string) $variantKey, (string) ($variant['model_name'] ?? ''));
                $horizon['variants'][$variantKey]['local_feasible'] = $check['feasible'];
                $horizon['variants'][$variantKey]['local_feasibility_message'] = $check['reason'] === null
                    ? null
                    : $feasibility->explain($check['reason'], $symbol, (int) $horizon['days'], (string) ($variant['model_name'] ?? ''), $check['local_model_name']);
            }

            return $horizon;
        });
        $requestedHorizon = $request->integer('horizon');
        if (in_array($requestedHorizon, [10, 20, 40], true)
            && $data['horizons']->contains(fn (array $horizon): bool => (int) $horizon['days'] === $requestedHorizon)) {
            $data['initialHorizon'] = $requestedHorizon;
        }
        $requestedVariant = (string) $request->query('variant', '');
        $data['initialVariant'] = in_array($requestedVariant, ['standard', 'pure_tcn'], true)
            ? $requestedVariant
            : null;
        $data['personalModelConfigurationKeys'] = $request->user()->savedPredictionFilters()
            ->get(['filters'])
            ->flatMap(fn ($strategy) => collect(data_get($strategy->filters, 'serving_model_configurations', []))
                ->pluck('key'))
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();

        return view('stocks.model-overview', $data);
    }

    public function storeStrategy(
        Request $request,
        string $symbol,
        ServingModelOverviewService $models,
        SavedFilterLimitService $limits,
        LocalModelFeasibilityService $feasibility,
    ): RedirectResponse {
        $validated = $request->validate([
            'release_id' => ['required', 'uuid'],
            'horizon' => ['required', 'integer', 'in:10,20,40'],
            'variant' => ['required', 'in:standard,pure_tcn'],
        ]);
        $data = $models->data($symbol);
        if (! hash_equals((string) $data['release']['id'], (string) $validated['release_id'])) {
            return redirect()->route('stocks.models', ['symbol' => $symbol])
                ->withErrors(['model_configuration' => __('Das aktive Modell wurde inzwischen aktualisiert. Bitte prüfe die neue Konfiguration und füge sie erneut hinzu.')]);
        }

        $horizon = $data['horizons']->firstWhere('days', (int) $validated['horizon']);
        $variant = $horizon['variants'][(string) $validated['variant']] ?? null;
        abort_unless($horizon && $variant, 404);

        // The picker only validates against the external serving database.
        // Refuse here rather than silently saving a strategy that
        // AutomatedPortfolioService can never match - see
        // LocalModelFeasibilityService for why this differs from what the
        // page above just displayed as available.
        $check = $feasibility->check($symbol, (int) $horizon['days'], (string) $validated['variant'], (string) ($variant['model_name'] ?? ''));
        if (! $check['feasible']) {
            return redirect()->route('stocks.models', ['symbol' => $symbol])
                ->withErrors(['model_configuration' => $feasibility->explain(
                    (string) $check['reason'], $symbol, (int) $horizon['days'], (string) ($variant['model_name'] ?? ''), $check['local_model_name'],
                )]);
        }

        $configurationKey = $this->configurationKey(
            (string) $data['release']['id'],
            (int) $horizon['days'],
            (string) $variant['key'],
        );
        $user = $request->user();

        $strategy = DB::transaction(function () use (
            $configurationKey,
            $data,
            $horizon,
            $variant,
            $limits,
            $user,
        ) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id']);
            $ownedStrategies = $user->savedPredictionFilters()->get();
            $existing = $ownedStrategies->first(function ($strategy) use ($configurationKey): bool {
                return collect(data_get($strategy->filters, 'serving_model_configurations', []))
                    ->contains(fn ($configuration): bool => is_array($configuration)
                        && hash_equals((string) ($configuration['key'] ?? ''), $configurationKey));
            });
            if ($existing) {
                return $existing;
            }
            if ($ownedStrategies->count() >= $limits->limitFor($user)) {
                return null;
            }

            $variantLabel = $variant['key'] === 'pure_tcn' ? 'Pure TCN' : 'Standard';
            $baseName = mb_substr($data['instrument']->symbol.' · '.$horizon['days'].'T · '.$variantLabel, 0, 80);
            $name = $baseName;
            $suffix = 2;
            while ($ownedStrategies->contains(fn ($saved): bool => mb_strtolower((string) $saved->name) === mb_strtolower($name))) {
                $ending = ' ('.$suffix++.')';
                $name = mb_substr($baseName, 0, 80 - mb_strlen($ending)).$ending;
            }

            $metrics = collect((array) $variant['metrics'])
                ->only(['trades', 'hit_rate', 'profit_factor', 'average_net_trade', 'cumulative_return', 'max_drawdown', 'average_holding_days'])
                ->map(fn ($value) => is_numeric($value) ? (float) $value : null)
                ->all();
            $configuration = [
                'key' => $configurationKey,
                'source' => 'serving_model_overview',
                'release_id' => (string) $data['release']['id'],
                'dataset_cutoff' => (string) $data['release']['dataset_cutoff'],
                'symbol' => (string) $data['instrument']->symbol,
                'instrument_name' => (string) ($data['instrument']->name ?: $data['instrument']->symbol),
                'horizon_days' => (int) $horizon['days'],
                'variant' => (string) $variant['key'],
                'variant_label' => $variantLabel,
                'model_name' => (string) $variant['model_name'],
                'champion' => (bool) $variant['selected'],
                'prediction_enabled' => (bool) $variant['prediction_enabled'],
                'quality_gate_passed' => (bool) $variant['quality_gate_passed'],
                'quality_label' => (string) $variant['quality_label'],
                'threshold' => is_numeric($variant['threshold']) ? (float) $variant['threshold'] : null,
                'metrics' => $metrics,
                'added_at' => now()->toIso8601String(),
            ];
            $filters = SavedPredictionFilterController::FILTER_DEFAULTS;
            $filters['q'] = (string) $data['instrument']->symbol;
            $filters['signal'] = 'BUY';
            $filters['display_icon'] = $variant['key'] === 'pure_tcn' ? 'bolt' : 'chart-bar';
            $filters['display_color'] = $variant['key'] === 'pure_tcn' ? '#A78BFA' : '#22D3EE';
            $filters['serving_model_configurations'] = [$configuration];

            return $user->savedPredictionFilters()->create([
                'name' => $name,
                'filters' => $filters,
                'visibility' => 'private',
                'description' => __('Persönliche Modellkonfiguration für :symbol: :horizon Handelstage mit :variant.', [
                    'symbol' => $data['instrument']->symbol,
                    'horizon' => $horizon['days'],
                    'variant' => $variantLabel,
                ]),
                'published_at' => null,
            ]);
        });

        if (! $strategy) {
            return redirect()->route('stocks.models', ['symbol' => $symbol])
                ->withErrors(['model_configuration' => __('Das Limit für persönliche Strategien in deinem Tarif ist erreicht.')]);
        }

        return redirect()->route('setup.saved-filters.index', ['highlight' => $strategy->id])
            ->with('status', __('Die Modellkonfiguration wurde deiner persönlichen Strategie hinzugefügt.'));
    }

    private function configurationKey(string $releaseId, int $horizon, string $variant): string
    {
        return $releaseId.':'.$horizon.':'.$variant;
    }
}
