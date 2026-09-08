<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LivePriceSubscriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user() && app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro),
            403,
        );
        $validated = $request->validate([
            'symbols' => ['required', 'array', 'max:100'],
            'symbols.*' => ['required', 'string', 'max:32'],
        ]);

        $requestedSymbols = collect($validated['symbols'])->map(
            fn (string $symbol): string => strtoupper(trim($symbol))
        )->unique()->values();
        $instruments = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $requestedSymbols)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['id', 'symbol', 'provider_symbol', 'german_listing_symbol', 'german_listing_currency']);
        $mapping = $instruments->mapWithKeys(
            fn (object $instrument): array => [
                $instrument->symbol => strtoupper((string) (
                    strtoupper((string) $instrument->german_listing_currency) === 'EUR'
                        && filled($instrument->german_listing_symbol)
                            ? $instrument->german_listing_symbol
                            : ($instrument->provider_symbol ?: $instrument->symbol)
                )),
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
