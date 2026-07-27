<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DepotController extends Controller
{
    public function index(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id);
        $paperMode = false;
        $stockInstrumentIds = $this->stockInstrumentIds();

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds'));
    }

    public function paperIndex(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id, 'paper');
        $paperMode = true;
        $stockInstrumentIds = collect();

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds'));
    }

    private function portfolios(int $userId, ?string $type = null)
    {
        return Portfolio::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->with(['positions.instrument'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (Portfolio $portfolio): Portfolio {
                $invested = $portfolio->positions->sum(
                    fn ($position) => (float) $position->quantity * (float) $position->average_buy_price
                );
                $currentValue = $portfolio->positions->sum(
                    fn ($position) => (float) $position->quantity
                        * (float) ($position->current_price ?? $position->average_buy_price)
                );

                $portfolio->setAttribute('invested_value', $invested);
                $portfolio->setAttribute('current_value', $currentValue);
                $portfolio->setAttribute(
                    'performance_percent',
                    $invested > 0 ? (($currentValue - $invested) / $invested) * 100 : 0.0
                );

                return $portfolio;
            });
    }

    private function stockInstrumentIds()
    {
        return DB::table('instruments')
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->pluck('id', 'symbol');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:strategy,paper'],
            'currency' => ['required', 'in:EUR,USD,CHF,GBP'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = Portfolio::query()
            ->where('user_id', $request->user()->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => __('Ein Depot mit diesem Namen existiert bereits.')]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $isFirst = ! Portfolio::query()
                ->where('user_id', $request->user()->id)
                ->where('active', true)
                ->exists();

            Portfolio::query()->create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'is_default' => $isFirst,
                'active' => true,
            ]);
        });

        return back()->with('status', 'portfolio-created');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active, 404);

        $portfolio->load(['positions.instrument']);
        $invested = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity * (float) $position->average_buy_price
        );
        $currentValue = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity
                * (float) ($position->current_price ?? $position->average_buy_price)
        );
        $performance = $invested > 0 ? (($currentValue - $invested) / $invested) * 100 : 0.0;
        $returnToPaper = $portfolio->type === 'paper' && $request->query('return_to') === 'paper';
        $backUrl = $returnToPaper ? route('paper-depots.index') : route('depots.index');
        $backLabel = $returnToPaper ? __('Zurück zu Musterdepots') : __('Zurück zu Depots');

        return view('depots.show', compact('portfolio', 'invested', 'currentValue', 'performance', 'backUrl', 'backLabel'));
    }
}
