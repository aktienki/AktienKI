<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketQuotesController extends Controller
{
    public const REFERENCE_INDICES = [
        'AMS' => ['^AEX', 'AEX'],
        'BTS' => ['^GSPC', 'S&P 500'],
        'EBS' => ['^SSMI', 'SMI'],
        'GER' => ['^GDAXI', 'DAX'],
        'LSE' => ['^FTSE', 'FTSE 100'],
        'PAR' => ['^FCHI', 'CAC 40'],
        'XASX' => ['^AXJO', 'ASX 200'],
        'XHKG' => ['^HSI', 'Hang Seng'],
        'XJSE' => ['^J203.JO', 'FTSE/JSE All Share'],
        'XNAS' => ['^IXIC', 'NASDAQ Composite'],
        'XNYS' => ['^NYA', 'NYSE Composite'],
        'XTKS' => ['^N225', 'Nikkei 225'],
    ];

    public function __invoke(): JsonResponse
    {
        $bySymbol = self::referenceQuotes();
        $quotes = collect(self::REFERENCE_INDICES)->mapWithKeys(function (array $reference, string $exchange) use ($bySymbol): array {
            $quote = $bySymbol->get($reference[0]);

            return $quote ? [$exchange => $quote] : [];
        });

        return response()->json([
            'quotes' => $quotes,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public static function referenceQuotes()
    {
        return Cache::remember('market_reference_index_quotes', now()->addSeconds(20), function () {
        $symbols = collect(self::REFERENCE_INDICES)->pluck(0);
        $bars = DB::table('instruments as instrument')
            ->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
            ->where('instrument.type', 'index')
            ->whereIn('instrument.symbol', $symbols)
            ->whereIn('bar.interval', ['1m', '1d'])
            ->orderBy('instrument.symbol')
            ->orderByDesc('bar.bar_time')
            ->orderByDesc('bar.id')
            ->get(['instrument.symbol', 'instrument.currency', 'bar.interval', 'bar.close', 'bar.bar_time'])
            ->groupBy('symbol');

        return $bars->map(function ($symbolBars): array {
            $latest = $symbolBars->get(0);
            $previous = $symbolBars
                ->first(fn (object $bar): bool =>
                    $bar->interval === '1d'
                    && \Illuminate\Support\Carbon::parse($bar->bar_time)->lt(
                        \Illuminate\Support\Carbon::parse($latest->bar_time)->startOfDay()
                    ));
            $price = (float) $latest->close;
            if ($previous && (! is_numeric($previous->close) || (float) $previous->close < $price * .5 || (float) $previous->close > $price * 2)) {
                $previous = $symbolBars->first(fn (object $bar): bool =>
                    $bar->interval === '1d'
                    && is_numeric($bar->close)
                    && \Illuminate\Support\Carbon::parse($bar->bar_time)->lt(\Illuminate\Support\Carbon::parse($latest->bar_time)->startOfDay())
                    && (float) $bar->close >= $price * .5
                    && (float) $bar->close <= $price * 2
                );
            }
            $previousPrice = $previous && is_numeric($previous->close) ? (float) $previous->close : null;

            return [
                'price' => $price,
                'change_percent' => $previousPrice && $previousPrice !== 0.0
                    ? (($price - $previousPrice) / $previousPrice) * 100
                    : null,
                'currency' => $latest->currency,
                'quote_time' => $latest->bar_time,
            ];
        });
        });
    }
}
