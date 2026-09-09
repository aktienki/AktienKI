<?php

namespace App\Http\Controllers;

use App\Services\ServingChartCacheService;
use App\Services\ServingReadService;
use App\Services\ServingStockLegacyViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class ServingStockController extends Controller
{
    public function __invoke(
        Request $request,
        string $symbol,
        ServingReadService $serving,
        ServingStockLegacyViewService $legacyView,
    ): View|RedirectResponse {
        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo) && Str::startsWith($returnTo, '/') && ! Str::startsWith($returnTo, '//')
            ? $returnTo
            : null;

        try {
            $stock = $serving->stock($symbol);
            if (! $stock) {
                if ($returnTo) {
                    return redirect()->to($returnTo)
                        ->with('error', __('Für diese Aktie gibt es derzeit kein aktives Modell für die Detailansicht.'));
                }

                abort(404, __('Für diese Aktie gibt es in der Service Datenbank kein aktives Modell.'));
            }

            return view('stocks.show', $legacyView->data($request, $stock));
        } catch (Throwable $exception) {
            if (! $returnTo) {
                throw $exception;
            }

            report($exception);

            return redirect()->to($returnTo)
                ->with('error', __('Die Detaildaten dieser Aktie sind momentan unvollständig. Die Chartsignaltabelle bleibt verfügbar.'));
        }
    }

    public function chartData(
        string $symbol,
        ServingReadService $serving,
        ServingChartCacheService $charts,
        ServingStockLegacyViewService $legacyView,
    ): JsonResponse {
        $stock = $serving->stock($symbol);
        abort_unless($stock, 404);

        $providerSymbol = $charts->providerSymbol($stock);
        $currency = filled($stock->german_listing_symbol ?? null)
            ? (string) (($stock->german_listing_currency ?? null) ?: $stock->currency)
            : (string) $stock->currency;
        $chart = $charts->load((int) $stock->instrument_id, $providerSymbol, $currency);
        $candles = collect($chart['points'] ?? [])->map(function (array $point): array {
            $close = (float) $point['close'];

            return [
                'x' => (int) $point['timestamp'] * 1000,
                'y' => [
                    (float) ($point['open'] ?? $close),
                    (float) ($point['high'] ?? $close),
                    (float) ($point['low'] ?? $close),
                    $close,
                ],
                'volume' => is_numeric($point['volume'] ?? null) ? (float) $point['volume'] : null,
            ];
        })->values();

        return response()->json([
            'symbol' => (string) $stock->symbol,
            'candles' => $candles,
            'currency' => (string) ($chart['currency'] ?? $currency),
            'source' => ($chart['cache_hit'] ?? false) ? 'Dateicache' : 'Twelve Data',
            'provider_symbol' => $providerSymbol,
            'cached_at' => $chart['cached_at'] ?? null,
            'updated_at' => $chart['cached_at'] ?? now()->toIso8601String(),
            'chart_patterns' => $legacyView->chartPatternData($candles)['recent'],
            'watchlist_entry' => null,
            'historical_chart_allowed' => true,
            'message' => $candles->isEmpty() ? __('Aktuell sind keine Kursdaten verfügbar.') : null,
        ]);
    }
}
