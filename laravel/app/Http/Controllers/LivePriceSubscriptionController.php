<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LivePriceSubscriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbols' => ['required', 'array', 'max:8'],
            'symbols.*' => ['required', 'string', 'max:32'],
        ]);

        $requestedSymbols = collect($validated['symbols'])->map(
            fn (string $symbol): string => strtoupper(trim($symbol))
        )->unique()->values();
        $instruments = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $requestedSymbols)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['id', 'symbol', 'provider_symbol']);
        $mapping = $instruments->mapWithKeys(
            fn (object $instrument): array => [
                $instrument->symbol => $instrument->provider_symbol ?: $instrument->symbol,
            ]
        )->all();

        $requests = Cache::get('current_stock_quote_requests', []);
        $requests[(string) $request->user()->getAuthIdentifier()] = [
            'instrument_ids' => $instruments->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'expires_at' => now()->addMinutes(2)->timestamp,
        ];
        Cache::put('current_stock_quote_requests', $requests, now()->addMinutes(3));

        return response()->json(['symbols' => $mapping]);
    }
}
