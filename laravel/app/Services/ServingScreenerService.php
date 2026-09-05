<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\ExternalBuyReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ServingScreenerService
{
    private const HORIZONS = [10, 20, 40];

    public function __construct(
        private readonly ServingChartCacheService $charts,
        private readonly PlanAccessService $plans,
        private readonly FreeRegionalStockUniverseService $regionalUniverse,
        private readonly PersonalizedSignalService $personalizedSignals,
        private readonly TradeEligibilityStatusService $tradeEligibility,
    ) {}

    /** @return array<string, mixed> */
    public function data(Request $request): array
    {
        $isFreeRegional = $this->plans->level($request->user()) === PlanLevel::Free;
        $allowedInstrumentIds = $isFreeRegional
            ? $this->regionalUniverse->instrumentIds($request->user())->map(fn ($id): int => (int) $id)
            : collect();

        $rows = $this->servingRows();
        if ($isFreeRegional) {
            $rows = $rows->whereIn('instrument_id', $allowedInstrumentIds);
        }

        $instrumentIds = $rows->pluck('instrument_id')->map(fn ($id): int => (int) $id)->all();
        $batchIds = $rows->pluck('batch_id')->filter()->unique()->all();
        $snapshotKey = sha1(implode(',', $instrumentIds).'|'.implode(',', $batchIds));
        $predictions = $instrumentIds === []
            ? collect()
            : Cache::store('file')->remember('screener.serving.predictions.v1.'.$snapshotKey, now()->addMinutes(5), fn () => DB::connection('serving')->table('serving_predictions')
                ->whereIn('instrument_id', $instrumentIds)
                ->whereIn('batch_id', $batchIds)
                ->orderByDesc('as_of')
                ->orderByDesc('id')
                ->get())
                ->groupBy('instrument_id');
        $statuses = $instrumentIds === []
            ? collect()
            : Cache::store('file')->remember('screener.serving.statuses.v1.'.$snapshotKey, now()->addMinutes(5), fn () => DB::connection('serving')->table('serving_model_horizon_status')
                ->whereIn('instrument_id', $instrumentIds)
                ->get())
                ->groupBy('instrument_id');
        $transitions = $instrumentIds === []
            ? collect()
            : Cache::store('file')->remember('screener.serving.transitions.v1.'.$snapshotKey, now()->addMinutes(5), fn () => DB::connection('serving')->table('serving_signal_transitions')
                ->whereIn('instrument_id', $instrumentIds)
                ->orderBy('instrument_id')
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->get())
                ->unique('instrument_id')
                ->keyBy('instrument_id');

        $stocks = $rows->map(function (object $row) use ($predictions, $statuses, $transitions, $request): object {
            $stockPredictions = collect($predictions->get((int) $row->instrument_id, collect()))
                ->filter(fn (object $prediction): bool => (string) $prediction->batch_id === (string) $row->batch_id)
                ->values();
            $stockStatuses = collect($statuses->get((int) $row->instrument_id, collect()));

            return $this->stock(
                $row,
                $stockPredictions,
                $stockStatuses,
                $transitions->get((int) $row->instrument_id),
                $request,
            );
        });
        $canUsePro = $this->plans->allowsTariff($request->user(), PlanLevel::Pro);
        if ($canUsePro) {
            $this->tradeEligibility->apply($stocks);
            $this->applyExternalReviewAdjustments($stocks);
        }
        $indexMemberships = $this->hasTable('market_indices') && $this->hasTable('index_memberships') && $instrumentIds !== []
            ? DB::table('index_memberships as membership')
                ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
                ->whereIn('membership.instrument_id', $instrumentIds)
                ->whereNull('membership.removed_at')
                ->where('market_index.is_active', true)
                ->orderBy('market_index.global_rank')
                ->get(['membership.instrument_id', 'market_index.symbol', 'market_index.name'])
            : collect();
        $membershipsByInstrument = $indexMemberships->groupBy(fn (object $membership): int => (int) $membership->instrument_id);
        $stocks->each(function (object $stock) use ($membershipsByInstrument): void {
            $primaryIndex = $membershipsByInstrument->get((int) $stock->instrument_id)?->first();
            if ($primaryIndex) {
                $stock->primary_index_symbol = (string) $primaryIndex->symbol;
                $stock->primary_index_name = (string) ($primaryIndex->name ?: $primaryIndex->symbol);
            }
        });
        $indices = $indexMemberships->unique('symbol')->map(fn (object $membership): object => (object) [
            'symbol' => (string) $membership->symbol,
            'name' => (string) ($membership->name ?: $membership->symbol),
        ])->values();
        if ($indices->isEmpty()) {
            $indices = $stocks->pluck('primary_index_symbol')->filter()->unique()->sort()->values()
                ->map(fn (string $symbol): object => (object) ['symbol' => $symbol, 'name' => $symbol]);
        }

        $ranked = $stocks
            ->filter(fn (object $stock): bool => in_array($stock->personalized_signal, ['BUY', 'WATCH'], true))
            ->sortByDesc(fn (object $stock): float => $this->rankingPriority($stock))
            ->values();
        $ranked->each(fn (object $stock, int $index) => $stock->screening_rank = $index + 1);
        $this->applyComparisonPercentiles($ranked);

        // Quick filters only advertise countries that contain screener-eligible
        // stocks. Sector availability is narrowed by the selected country so
        // impossible combinations can be shown as disabled in the UI.
        $sectors = $ranked->pluck('sector')->filter()->unique()->sort()->values();
        $selectedQuickSector = trim((string) $request->query('sector'));
        $selectedQuickCountry = strtoupper(trim((string) $request->query('country')));
        $countries = $ranked
            ->when($selectedQuickSector !== '', fn (Collection $items) => $items->where('sector', $selectedQuickSector))
            ->pluck('country')->filter()->unique()->sort()->values();
        $availableSectorFilters = $ranked
            ->when($selectedQuickCountry !== '', fn (Collection $items) => $items->where('country', $selectedQuickCountry))
            ->pluck('sector')->filter()->unique()->values();

        $requestedSignal = strtoupper(trim((string) $request->query('signal')));
        $requestedSignal = $requestedSignal === 'WAIT' ? 'WATCH' : $requestedSignal;
        $selectedIndex = trim((string) $request->query('index'));
        $queryText = mb_strtolower(trim((string) $request->query('q')));
        $country = strtoupper(trim((string) $request->query('country')));
        $sector = trim((string) $request->query('sector'));
        $riskFilterPresent = $request->boolean('risk_profiles');
        $riskClasses = collect($request->query('risk_class', []))
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => in_array($value, ['defensive', 'balanced', 'offensive'], true))
            ->unique()
            ->values();
        $transitionDays = in_array((int) $request->query('transition_days'), [1, 5, 10, 20], true)
            ? (int) $request->query('transition_days')
            : null;
        $holdingSource = trim((string) $request->query('bestand'));
        $holdingInstrumentIds = $this->holdingInstrumentIds($holdingSource, (int) $request->user()->id);
        $minimumMaximumReturn = is_numeric($request->query('min_max_return'))
            ? (float) $request->query('min_max_return')
            : null;

        $ranked = $ranked
            ->when($queryText !== '', fn (Collection $items) => $items->filter(fn (object $stock): bool => str_contains(mb_strtolower((string) $stock->symbol), $queryText)
                || str_contains(mb_strtolower((string) $stock->name), $queryText)))
            ->when($country !== '', fn (Collection $items) => $items->where('country', $country))
            ->when($sector !== '', fn (Collection $items) => $items->where('sector', $sector))
            ->when($riskFilterPresent && $riskClasses->count() < 3, fn (Collection $items) => $items->filter(function (object $stock) use ($riskClasses): bool {
                if (! is_numeric($stock->risk_percent ?? null)) return false;
                $risk = (float) $stock->risk_percent;
                $stockRiskClass = match (true) {
                    $risk <= 25 => 'defensive',
                    $risk <= 50 => 'balanced',
                    default => 'offensive',
                };

                return $riskClasses->contains($stockRiskClass);
            }))
            ->when($selectedIndex !== '', function (Collection $items) use ($selectedIndex, $indexMemberships): Collection {
                $memberIds = $indexMemberships->where('symbol', $selectedIndex)
                    ->pluck('instrument_id')->map(fn ($id): int => (int) $id);

                return $memberIds->isNotEmpty()
                    ? $items->whereIn('instrument_id', $memberIds)
                    : $items->where('primary_index_symbol', $selectedIndex);
            })
            ->when(in_array($requestedSignal, ['BUY', 'WATCH'], true), fn (Collection $items) => $items->where('personalized_signal', $requestedSignal))
            ->when($holdingSource !== '', fn (Collection $items) => $items->whereIn('instrument_id', $holdingInstrumentIds))
            ->when($minimumMaximumReturn !== null, fn (Collection $items) => $items->filter(function (object $stock) use ($minimumMaximumReturn): bool {
                $maximum = collect(self::HORIZONS)
                    ->map(fn (int $horizon) => $stock->{"expected_return_{$horizon}d"} ?? null)
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value): float => (float) $value)
                    ->max();

                return $maximum !== null && $maximum >= $minimumMaximumReturn;
            }))
            ->when($transitionDays !== null, fn (Collection $items) => $items->filter(fn (object $stock): bool => filled($stock->signal_transition_at)
                && Carbon::parse($stock->signal_transition_at)->gte(now()->subDays($transitionDays))))
            ->values();

        $limit = match (strtolower((string) $request->query('limit', 'all'))) {
            '25' => 25,
            '50' => 50,
            '100' => 100,
            'all' => null,
            default => null,
        };
        if ($limit !== null) {
            $ranked = $ranked->take($limit)->values();
        }

        $isMobileRequest = preg_match('/Mobile|iPhone|iPod|Android/i', (string) $request->userAgent()) === 1;
        $mobilePerPage = 25;
        $mobileTotal = $ranked->count();
        $mobileLastPage = max(1, (int) ceil($mobileTotal / $mobilePerPage));
        $mobilePage = min(max(1, (int) $request->query('mobile_page', 1)), $mobileLastPage);
        if ($isMobileRequest) {
            $ranked = $ranked->slice(($mobilePage - 1) * $mobilePerPage, $mobilePerPage)->values();
        }

        $this->applyCachedCharts($ranked);
        $this->applyStoredChartFallbacks($ranked);

        return [
            'stocks' => $this->addApplicationData($ranked, $request),
            'countries' => $countries,
            'sectors' => $sectors,
            'availableSectorFilters' => $availableSectorFilters,
            'indices' => $indices,
            ...$this->applicationCollections($ranked, $request),
            'certificateInstrumentIds' => collect(),
            'recentNewsByInstrument' => collect(),
            'isFreeRegional' => $isFreeRegional,
            'canViewModelOverview' => $this->plans->allowsTariff($request->user(), PlanLevel::Pro),
            'realtimeQuotes' => $this->plans->allowsTariff($request->user(), PlanLevel::Pro),
            'regionalCountry' => $this->regionalUniverse->country($request->user()),
            'mobilePagination' => [
                'enabled' => $isMobileRequest,
                'page' => $mobilePage,
                'last_page' => $mobileLastPage,
                'per_page' => $mobilePerPage,
                'total' => $mobileTotal,
            ],
        ];
    }

    private function holdingInstrumentIds(string $source, int $userId): Collection
    {
        if ($source === 'watchlists') {
            return DB::table('watchlist_items as item')
                ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
                ->where('watchlist.user_id', $userId)
                ->where('watchlist.active', true)
                ->pluck('item.instrument_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
        }

        if (preg_match('/^watchlist:(\d+)$/', $source, $matches) === 1) {
            return DB::table('watchlist_items as item')
                ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
                ->where('watchlist.id', (int) $matches[1])
                ->where('watchlist.user_id', $userId)
                ->where('watchlist.active', true)
                ->pluck('item.instrument_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
        }

        if (preg_match('/^portfolio:(\d+)$/', $source, $matches) === 1) {
            return DB::table('portfolio_positions as position')
                ->join('portfolios as portfolio', 'portfolio.id', '=', 'position.portfolio_id')
                ->where('portfolio.id', (int) $matches[1])
                ->where('portfolio.user_id', $userId)
                ->where('portfolio.type', 'paper')
                ->where('portfolio.active', true)
                ->pluck('position.instrument_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
        }

        return collect();
    }

    /** @return array<string, mixed> */
    public function chart(int $instrumentId, Request $request): array
    {
        if ($this->plans->level($request->user()) === PlanLevel::Free) {
            abort_unless(
                $this->regionalUniverse->instrumentIds($request->user())->contains($instrumentId),
                403,
            );
        }

        $instrument = DB::connection('serving')->table('serving_instruments')
            ->where('id', $instrumentId)
            ->where('instrument_type', 'stock')
            ->where('is_active', true)
            ->where('is_tradeable', true)
            ->first();
        abort_unless($instrument, 404);

        $restricted = in_array(
            strtoupper(trim((string) $instrument->country_code)),
            config('aktienki.market_data.restricted_historical_chart_countries', []),
            true,
        );
        $providerSymbol = $this->charts->providerSymbol($instrument);
        $currency = $this->displayCurrency($instrument, $providerSymbol);
        $chart = $restricted
            ? ['points' => [], 'currency' => $currency, 'provider_symbol' => $providerSymbol, 'cached_at' => null, 'cache_hit' => false]
            : $this->charts->load($instrumentId, $providerSymbol, $currency);

        $signal = DB::connection('serving')->table('serving_current_stock_signals')
            ->where('instrument_id', $instrumentId)
            ->first();
        abort_unless($signal, 404);
        $predictions = DB::connection('serving')->table('serving_predictions')
            ->where('instrument_id', $instrumentId)
            ->where('batch_id', $signal->batch_id)
            ->get();
        $statuses = DB::connection('serving')->table('serving_model_horizon_status')
            ->where('instrument_id', $instrumentId)
            ->get();
        $forecast = collect([20, 40, 10])
            ->map(fn (int $horizon) => $this->selectPrediction($predictions, $statuses, $horizon))
            ->first();
        $forecastPoints = collect([10, 20, 40])->mapWithKeys(function (int $horizon) use ($predictions, $statuses): array {
            $prediction = $this->selectPrediction($predictions, $statuses, $horizon);
            return [$horizon => is_numeric($prediction?->expected_return) ? (float) $prediction->expected_return * 100 : null];
        })->filter(fn ($value) => $value !== null)->all();
        $transition = DB::connection('serving')->table('serving_signal_transitions')
            ->where('instrument_id', $instrumentId)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->first();

        return [
            'instrumentId' => $instrumentId,
            'symbol' => (string) $instrument->symbol,
            'chart' => $chart,
            'restricted' => $restricted,
            'restrictionReason' => $restricted
                ? __('Historische Kurscharts sind für diesen Markt aufgrund der aktuellen Datenlizenz nicht verfügbar.')
                : null,
            'expectedReturnPercent' => is_numeric($forecast?->expected_return)
                ? (float) $forecast->expected_return * 100
                : null,
            'forecastPoints' => $forecastPoints,
            'forecastHorizon' => is_numeric($forecast?->horizon) ? (int) $forecast->horizon : null,
            'predictionAt' => $forecast?->as_of ?: $signal->as_of,
            'signal' => strtoupper((string) ($signal->signal ?: 'HOLD')),
            'transitionAt' => $transition?->changed_at,
            'transitionFrom' => $transition?->from_signal,
        ];
    }

    private function servingRows(): Collection
    {
        return Cache::store('file')->remember('screener.serving.rows.v1', now()->addMinutes(5), fn () => DB::connection('serving')->table('serving_current_stock_signals as current_signal')
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'current_signal.instrument_id')
            ->leftJoin('serving_instrument_fundamentals as fundamental', 'fundamental.instrument_id', '=', 'instrument.id')
            ->where('instrument.instrument_type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_tradeable', true)
            ->select([
                'current_signal.*',
                'instrument.name', 'instrument.exchange', 'instrument.currency',
                'instrument.country_code', 'instrument.sector_code', 'instrument.home_index_symbol',
                'instrument.display_metadata', 'instrument.provider_symbol', 'instrument.isin',
                'instrument.german_listing_symbol', 'instrument.german_listing_exchange',
                'instrument.german_listing_currency', 'instrument.risk_status',
                'instrument.risk_profit_factor', 'instrument.risk_confidence',
                'instrument.risk_max_drawdown', 'instrument.risk_profit_per_trade',
                'fundamental.trailing_pe', 'fundamental.forward_pe', 'fundamental.dividend_yield',
                'fundamental.market_cap', 'fundamental.price_to_book', 'fundamental.price_to_sales',
                'fundamental.revenue_growth', 'fundamental.profit_margin',
                'fundamental.return_on_equity', 'fundamental.debt_to_equity',
            ])
            ->get());
    }

    private function stock(
        object $row,
        Collection $predictions,
        Collection $statuses,
        ?object $transition,
        Request $request,
    ): object {
        $metadata = $this->json($row->display_metadata);
        $providerSymbol = $this->charts->providerSymbol($row);
        $currency = $this->displayCurrency($row, $providerSymbol);
        $forecastPredictions = collect(self::HORIZONS)->mapWithKeys(fn (int $horizon): array => [
            $horizon => $this->selectPrediction($predictions, $statuses, $horizon),
        ]);
        $primaryPrediction = $forecastPredictions->get(20)
            ?? $forecastPredictions->get(40)
            ?? $forecastPredictions->get(10)
            ?? $this->selectPrediction($predictions, $statuses);
        $primaryStatus = $primaryPrediction
            ? $statuses->first(fn (object $status): bool => (int) $status->horizon === (int) $primaryPrediction->horizon
                && (string) $status->variant === (string) $primaryPrediction->variant)
            : null;
        $metrics = $this->json($primaryStatus?->performance);
        $context = $this->json($primaryPrediction?->compact_context);
        $currentPrice = $this->currentPrice($primaryPrediction, $context);
        $rating = (string) (($row->buy_rating ?? null) ?: ($row->underlying_buy_rating ?? null) ?: '3');
        if ($rating === '1++') {
            $rating = '1+';
        }
        $ratingPercent = $this->ratingPercent($rating);
        $servingRisk = is_numeric($row->risk_score) ? (int) $row->risk_score : null;
        $fallbackRiskPercent = match ($servingRisk) {
            2 => 25.0,
            3 => 50.0,
            4 => 70.0,
            5 => 90.0,
            default => null,
        };
        $riskPercent = $this->predictionRiskPercent(data_get($primaryPrediction, 'risk_score')) ?? $fallbackRiskPercent;
        $qualityClass = (string) ($primaryStatus?->model_quality_class ?: 'basic');
        $qualityName = (string) ($primaryStatus?->model_quality_label ?: ucfirst($qualityClass));
        $qualityTier = match ($qualityClass) {
            'quality' => 'top',
            'solid' => 'solid',
            'basic' => 'test',
            default => null,
        };
        $qualityGatePassed = (bool) ($primaryStatus?->quality_gate_passed ?? false);
        $failedGates = $this->json($primaryStatus?->failed_gates);
        if (array_is_list($failedGates) === false) {
            $failedGates = array_values($failedGates);
        }

        $stock = (object) [
            'data_source' => 'serving',
            'id' => is_numeric($primaryPrediction?->id) ? (int) $primaryPrediction->id : (int) $row->instrument_id,
            'instrument_id' => (int) $row->instrument_id,
            'symbol' => (string) $row->symbol,
            'name' => (string) $row->name,
            'country' => strtoupper(trim((string) $row->country_code)),
            'sector' => (string) ($row->sector_code ?: ''),
            'currency' => $currency,
            'original_currency' => $currency,
            'provider_symbol' => $providerSymbol,
            'exchange_code' => (string) $row->exchange,
            'exchange_name' => (string) $row->exchange,
            'primary_index_symbol' => (string) ($row->home_index_symbol ?: ''),
            'primary_index_name' => (string) ($row->home_index_symbol ?: ''),
            'prediction_time' => $primaryPrediction?->as_of ?: $row->as_of,
            'current_price' => $currentPrice,
            'original_price' => $currentPrice,
            'model_signal' => strtoupper((string) ($row->underlying_signal ?: $row->signal ?: 'HOLD')),
            'personalized_signal' => strtoupper((string) ($row->signal === 'NEUTRAL' ? 'HOLD' : $row->signal)),
            'serving_buy_rating' => $rating,
            'serving_best_buy_quality' => $row->best_buy_quality,
            'serving_quality_gate_buy' => (bool) $row->has_quality_gate_buy,
            'ranking_score' => $ratingPercent,
            'score_10' => $ratingPercent / 10,
            'confidence' => is_numeric($primaryPrediction?->confidence) ? (float) $primaryPrediction->confidence : null,
            'confidence_percent' => is_numeric($primaryPrediction?->confidence) ? (float) $primaryPrediction->confidence * 100 : null,
            'risk_percent' => $riskPercent,
            'risk_score' => $riskPercent,
            'drawdown_risk_factor' => $riskPercent,
            'risk_status' => $row->risk_status ?: match ($servingRisk) {
                2 => 'defensive', 3 => 'balanced', 4 => 'opportunity', 5 => 'risk', default => null,
            },
            'risk_profit_factor' => $row->risk_profit_factor,
            'risk_confidence' => $row->risk_confidence,
            'risk_max_drawdown' => $row->risk_max_drawdown,
            'risk_profit_per_trade' => $row->risk_profit_per_trade,
            'ranking_profit_factor' => is_numeric($metrics['profit_factor'] ?? null) ? (float) $metrics['profit_factor'] : null,
            'ranking_hit_rate' => is_numeric($metrics['hit_rate'] ?? null) ? (float) $metrics['hit_rate'] * 100 : null,
            'ranking_drawdown' => is_numeric($metrics['max_drawdown'] ?? null) ? abs((float) $metrics['max_drawdown']) * 100 : null,
            'display_profit_per_trade_percent' => is_numeric($metrics['average_net_trade'] ?? null) ? (float) $metrics['average_net_trade'] * 100 : null,
            'annualized_volatility' => is_numeric($metrics['stddev_net_trade'] ?? null) && is_numeric($primaryPrediction?->horizon)
                ? abs((float) $metrics['stddev_net_trade']) * sqrt(252 / max(1, (int) $primaryPrediction->horizon))
                : null,
            'ranking_stability_available' => false,
            'ranking_stability_percent' => 0.0,
            'model_quality_tier_code' => $qualityTier,
            'model_quality_tier_name' => $qualityName,
            'trigger_model_name' => match ((string) ($primaryPrediction?->variant ?: '')) {
                'pure_tcn' => 'Pure TCN',
                'standard' => 'Standard · Tabular + TCN',
                default => ucfirst(str_replace('_', ' ', (string) ($primaryPrediction?->variant ?: ''))),
            },
            'trigger_model_horizon' => is_numeric($primaryPrediction?->horizon) ? (int) $primaryPrediction->horizon : null,
            'trigger_model_release_id' => (string) ($primaryPrediction?->release_id ?: ''),
            'trigger_model_quality_gate_passed' => $qualityGatePassed,
            'quality_gate_blockers' => $failedGates,
            'stock_signal_calibration' => ['quality_percent' => $ratingPercent, 'quality_gate_passed' => $qualityGatePassed],
            'trailing_pe' => $row->trailing_pe,
            'forward_pe' => $row->forward_pe,
            'dividend_yield' => $row->dividend_yield,
            'market_cap' => $row->market_cap,
            'price_to_book' => $row->price_to_book,
            'price_to_sales' => $row->price_to_sales,
            'revenue_growth' => $row->revenue_growth,
            'profit_margin' => $row->profit_margin,
            'return_on_equity' => $row->return_on_equity,
            'debt_to_equity' => $row->debt_to_equity,
            'business_summary' => data_get($metadata, 'business_summary'),
            'business_summary_en' => data_get($metadata, 'business_summary_en'),
            'business_description' => data_get($metadata, 'business_description'),
            'business_description_en' => data_get($metadata, 'business_description_en'),
            'signal_transition_at' => $transition?->changed_at,
            'signal_transition_from' => $transition?->from_signal,
            'signal_transition_x' => null,
            'chart_currency' => $currency,
            'chart_points' => [],
            'price_change_percent' => null,
            'indicator_ranking_direction' => 'neutral',
            'indicator_strength_percent' => null,
            'screening_rank' => null,
            'sector_rank' => null,
            'global_percentiles' => [],
            'index_percentiles' => [],
            'sector_percentiles' => [],
            'personal_risk_profile' => $this->personalizedSignals->profileLabel($request->user()),
            'assessment_date' => $primaryPrediction?->as_of ?: $row->as_of,
            'assessment_recommendation' => strtoupper((string) ($row->signal === 'NEUTRAL' ? 'HOLD' : $row->signal)),
            'assessment_confidence' => is_numeric($primaryPrediction?->confidence) ? (float) $primaryPrediction->confidence * 100 : null,
            'assessment_model' => (string) ($primaryPrediction?->variant ?: ''),
            'assessment_summary' => null,
            'assessment_pros' => [],
            'assessment_cons' => [],
            'assessment_is_detailed_buy' => false,
            'external_review_is_current' => false,
            'external_review_status' => null,
            'external_review_verdict' => null,
            'external_review_confidence' => null,
            'external_review_summary' => null,
            'external_review_positive_factors' => [],
            'external_review_risk_factors' => [],
            'external_review_sources' => [],
            'external_review_model' => null,
            'external_review_triggered_at' => null,
            'external_review_researched_at' => null,
            'external_review_cost_usd' => null,
            'external_review_ranking_downgraded' => false,
            'ranking_score_before_external_review' => null,
            'has_matching_label' => false,
            'has_matching_strategy' => false,
        ];

        foreach (self::HORIZONS as $horizon) {
            $prediction = $forecastPredictions->get($horizon);
            $stock->{"expected_return_{$horizon}d"} = is_numeric($prediction?->expected_return)
                ? (float) $prediction->expected_return * 100
                : null;
            $stock->{"predicted_price_{$horizon}d"} = is_numeric($prediction?->target_price)
                ? (float) $prediction->target_price
                : null;
            $stock->{"risk_percent_{$horizon}d"} = $this->predictionRiskPercent(data_get($prediction, 'risk_score'));
        }
        $stock->expected_return_20d = $stock->expected_return_20d ?? null;
        $stock->predicted_price_20d = $stock->predicted_price_20d ?? null;

        [$pros, $cons] = $this->assessmentItems($stock, $qualityGatePassed, $failedGates);
        $stock->simple_pros = $pros;
        $stock->simple_cons = $cons;
        $stock->simple_assessment_is_stored = true;
        $stock->personal_signal_breakdown = [
            'summary' => __('Das Signal :signal und das Rating :rating stammen aus der aktuellen Serving-Prediction.', [
                'signal' => $stock->personalized_signal,
                'rating' => $rating,
            ]),
            'pros' => $pros,
            'cons' => $cons,
        ];
        $stock->personal_signal_explanation = $stock->personal_signal_breakdown['summary'];

        return $stock;
    }

    private function predictionRiskPercent(mixed $risk): ?float
    {
        if (! is_numeric($risk) || (float) $risk <= 0) return null;

        $value = (float) $risk;
        if ($value >= 2 && $value <= 5 && abs($value - round($value)) < .0001) {
            return match ((int) round($value)) {
                2 => 25.0,
                3 => 50.0,
                4 => 70.0,
                5 => 90.0,
            };
        }

        return max(1.0, min(100.0, $value <= 1 ? $value * 100 : $value));
    }

    private function selectPrediction(Collection $predictions, Collection $statuses, ?int $horizon = null): ?object
    {
        return $predictions
            ->when($horizon !== null, fn (Collection $items) => $items->where('horizon', $horizon))
            ->sortByDesc(function (object $prediction) use ($statuses): float {
                $status = $statuses->first(fn (object $candidate): bool => (int) $candidate->horizon === (int) $prediction->horizon
                    && (string) $candidate->variant === (string) $prediction->variant);
                $quality = match ((string) ($status?->model_quality_class ?: '')) {
                    'quality' => 4, 'solid' => 3, 'basic' => 2, 'underperform' => 1, default => 0,
                };

                return (strtoupper((string) $prediction->signal) === 'BUY' ? 1_000_000 : 0)
                    + ((bool) ($status?->quality_gate_passed ?? false) ? 100_000 : 0)
                    + ((bool) ($status?->selected_for_prediction ?? false) ? 10_000 : 0)
                    + ($quality * 1_000)
                    + ((float) ($prediction->confidence ?? 0) * 100)
                    + ((float) ($prediction->expected_return ?? 0) * 10);
            })
            ->first();
    }

    private function currentPrice(?object $prediction, array $context): ?float
    {
        if (is_numeric(data_get($context, 'last_price_eur'))) {
            return (float) data_get($context, 'last_price_eur');
        }
        if (is_numeric($prediction?->target_price) && is_numeric($prediction?->expected_return)) {
            $divisor = 1 + (float) $prediction->expected_return;

            return abs($divisor) > 0.000001 ? (float) $prediction->target_price / $divisor : null;
        }

        return null;
    }

    private function displayCurrency(object $instrument, string $providerSymbol): string
    {
        if (str_contains($providerSymbol, ':XETR')
            || str_contains($providerSymbol, ':XFRA')
            || strtoupper(trim((string) ($instrument->german_listing_currency ?? ''))) === 'EUR') {
            return 'EUR';
        }

        return strtoupper(trim((string) ($instrument->currency ?: 'EUR')));
    }

    private function applyCachedChart(object $stock, array $chart): void
    {
        $closes = collect($chart['points'] ?? [])->pluck('close')->filter(fn ($value) => is_numeric($value))->map(fn ($value): float => (float) $value)->values();
        $stock->chart_points = $closes->all();
        $stock->chart_currency = (string) ($chart['currency'] ?? $stock->currency);
        if ($closes->count() >= 2 && (float) $closes->get($closes->count() - 2) > 0) {
            $stock->price_change_percent = (($closes->last() / $closes->get($closes->count() - 2)) - 1) * 100;
        }
        if ($closes->count() < 10) {
            return;
        }

        $latest = (float) $closes->last();
        $sma20 = (float) $closes->take(-20)->avg();
        $sma50 = (float) $closes->take(-min(50, $closes->count()))->avg();
        $votes = collect([
            $latest >= $sma20,
            $sma20 >= $sma50,
            $latest >= (float) $closes->get(max(0, $closes->count() - 6)),
        ]);
        $positive = $votes->filter()->count();
        $stock->indicator_strength_percent = ($positive / $votes->count()) * 100;
        $stock->indicator_ranking_direction = $positive >= 2 ? 'up' : ($positive <= 1 ? 'down' : 'neutral');
    }

    private function applyStoredChartFallbacks(Collection $stocks): void
    {
        $missingStocks = $stocks->filter(fn (object $stock): bool => count((array) ($stock->chart_points ?? [])) < 2);
        if ($missingStocks->isEmpty()) {
            return;
        }
        $localInstruments = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $missingStocks->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol))->all())
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->get(['id', 'symbol'])
            ->keyBy(fn (object $instrument): string => strtoupper((string) $instrument->symbol));
        $localIds = $localInstruments->pluck('id')->map(fn ($id): int => (int) $id)->values();
        if ($localIds->isEmpty()) {
            return;
        }

        $rankedBars = DB::table('price_bars')
            ->whereIn('instrument_id', $localIds)
            ->where('interval', '1d')
            ->where('close', '>', 0)
            ->select(['instrument_id', 'bar_time', 'close'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument_id ORDER BY bar_time DESC) AS row_number');
        $bars = DB::query()->fromSub($rankedBars, 'ranked_bars')
            ->where('row_number', '<=', 20)
            ->orderBy('instrument_id')
            ->orderBy('bar_time')
            ->get()
            ->groupBy('instrument_id');

        $stocks->each(function (object $stock) use ($bars, $localInstruments): void {
            if (count((array) ($stock->chart_points ?? [])) >= 2) {
                return;
            }
            $localInstrument = $localInstruments->get(strtoupper((string) $stock->symbol));
            if (! $localInstrument) {
                return;
            }
            $points = collect($bars->get((int) $localInstrument->id, collect()))
                ->map(fn (object $bar): array => [
                    'timestamp' => Carbon::parse($bar->bar_time)->timestamp,
                    'close' => (float) $bar->close,
                ])
                ->all();
            if (count($points) >= 2) {
                $this->applyCachedChart($stock, [
                    'points' => $points,
                    'currency' => $stock->currency,
                ]);
            }
        });
    }

    private function applyCachedCharts(Collection $stocks): void
    {
        $stocks->each(function (object $stock): void {
            $providerSymbol = trim((string) ($stock->provider_symbol ?? $stock->symbol));
            if ($cachedChart = $this->charts->peek((int) $stock->instrument_id, $providerSymbol)) {
                $this->applyCachedChart($stock, $cachedChart);
            }
        });
    }

    /** @return array{0:list<string>,1:list<string>} */
    private function assessmentItems(object $stock, bool $qualityGatePassed, array $failedGates): array
    {
        $pros = [];
        $cons = [];
        if ($qualityGatePassed) {
            $pros[] = __('Mindestens eine aktuelle Modell-/Horizont-Konfiguration erfüllt das Quality Gate.');
        }
        if (is_numeric($stock->ranking_profit_factor)) {
            $item = __('Profitfaktor: :value.', [
                'value' => number_format((float) $stock->ranking_profit_factor, 2, ',', '.'),
            ]);
            if ($stock->ranking_profit_factor > 1) {
                $pros[] = $item;
            } else {
                $cons[] = $item;
            }
        }
        if (is_numeric($stock->ranking_hit_rate)) {
            $item = __('Hitrate: :value %.', [
                'value' => number_format((float) $stock->ranking_hit_rate, 1, ',', '.'),
            ]);
            if ($stock->ranking_hit_rate >= 50) {
                $pros[] = $item;
            } else {
                $cons[] = $item;
            }
        }
        if (is_numeric($stock->expected_return_20d)) {
            $item = __('Kalibrierte 20-Tage-Prognose: :value %.', [
                'value' => number_format((float) $stock->expected_return_20d, 2, ',', '.'),
            ]);
            if ($stock->expected_return_20d > 0) {
                $pros[] = $item;
            } else {
                $cons[] = $item;
            }
        }
        if ($stock->personalized_signal === 'WATCH') {
            $cons[] = __('Kurzfristiger Gegenwind: Das Serving-Signal stuft die Aktie aktuell auf WATCH.');
        }
        if (($stock->risk_percent ?? 0) >= 70) {
            $cons[] = __('Die aktuelle Serving-Risikoklasse ist erhöht.');
        }
        foreach (array_slice($failedGates, 0, 2) as $gate) {
            $cons[] = __('Nicht erfülltes Prüfkriterium: :gate.', ['gate' => str_replace('_', ' ', (string) $gate)]);
        }

        return [
            array_slice($pros ?: [__('Aktuelle Serving-Prediction ist verfügbar.')], 0, 5),
            array_slice($cons ?: [__('Keine zusätzlichen Warnsignale in den kompakten Serving-Daten.')], 0, 5),
        ];
    }

    private function rankingPriority(object $stock): float
    {
        return ($stock->personalized_signal === 'BUY' ? 1_000_000 : 0)
            + ((bool) $stock->serving_quality_gate_buy ? 100_000 : 0)
            + ((float) $stock->ranking_score * 1_000)
            + ((float) ($stock->expected_return_20d ?? 0) * 10)
            - (float) ($stock->risk_percent ?? 100);
    }

    private function applyExternalReviewAdjustments(Collection $stocks): void
    {
        if ($stocks->isEmpty() || ! $this->hasTable('external_buy_reviews')) {
            return;
        }

        $reviews = ExternalBuyReview::query()
            ->whereIn('instrument_id', $stocks->pluck('instrument_id')->all())
            ->where('status', 'completed')
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->get()
            ->unique('instrument_id')
            ->keyBy('instrument_id');

        $stocks->each(function (object $stock) use ($reviews): void {
            $review = $reviews->get((int) $stock->instrument_id);
            $isCurrent = $review !== null
                && $stock->personalized_signal === 'BUY'
                && (! filled($stock->signal_transition_at)
                    || $review->triggered_at?->greaterThanOrEqualTo(Carbon::parse($stock->signal_transition_at)));

            if (! $isCurrent) {
                return;
            }

            $stock->external_review_is_current = true;
            $stock->external_review_status = $review->status;
            $stock->external_review_verdict = $review->verdict;
            $stock->external_review_confidence = $review->confidence;
            $stock->external_review_summary = $review->summary;
            $stock->external_review_positive_factors = $review->positive_factors ?? [];
            $stock->external_review_risk_factors = $review->risk_factors ?? [];
            $stock->external_review_sources = $review->sources ?? [];
            $stock->external_review_model = $review->model;
            $stock->external_review_triggered_at = $review->triggered_at;
            $stock->external_review_researched_at = $review->researched_at;
            $stock->external_review_cost_usd = is_numeric($review->estimated_cost_microusd)
                ? (float) $review->estimated_cost_microusd / 1_000_000
                : null;
            $stock->external_review_ranking_downgraded = $review->verdict === 'OBJECTION';

            if ($stock->external_review_ranking_downgraded) {
                $stock->ranking_score_before_external_review = (float) $stock->ranking_score;
                $stock->ranking_score = max(0.0, (float) $stock->ranking_score - 10.0);
                $stock->score_10 = $stock->ranking_score / 10;
            }
        });
    }

    private function ratingPercent(string $rating): float
    {
        $rating = str_replace('−', '-', trim($rating));

        return match ($rating) {
            '1++' => 99, '1+' => 95, '1' => 90, '1-' => 85,
            '2+' => 78, '2' => 72, '2-' => 65,
            '3+' => 58, '3' => 52, '3-' => 45,
            '4+' => 38, '4' => 32, '4-' => 25,
            '5+' => 18, '5' => 12, '5-' => 5,
            default => 50,
        };
    }

    private function applyComparisonPercentiles(Collection $stocks): void
    {
        $metrics = [
            'score' => fn (object $stock) => $stock->ranking_score,
            'return_20d' => fn (object $stock) => $stock->expected_return_20d,
            'confidence' => fn (object $stock) => $stock->confidence_percent,
            'profit_factor' => fn (object $stock) => $stock->ranking_profit_factor,
            'hit_rate' => fn (object $stock) => $stock->ranking_hit_rate,
            'risk' => fn (object $stock) => is_numeric($stock->risk_percent) ? -(float) $stock->risk_percent : null,
            'volatility' => fn (object $stock) => is_numeric($stock->annualized_volatility) ? -(float) $stock->annualized_volatility : null,
            'indicators' => fn (object $stock) => $stock->indicator_strength_percent,
            'pe_ratio' => fn (object $stock) => is_numeric($stock->trailing_pe) ? -(float) $stock->trailing_pe : (is_numeric($stock->forward_pe) ? -(float) $stock->forward_pe : null),
            'dividend_yield' => fn (object $stock) => $stock->dividend_yield,
        ];

        $global = collect($metrics)->map(fn (callable $metric) => $this->percentiles($stocks, $metric));
        $byIndex = collect($metrics)->map(fn (callable $metric) => $this->groupedPercentiles($stocks, 'primary_index_symbol', $metric));
        $bySector = collect($metrics)->map(fn (callable $metric) => $this->groupedPercentiles($stocks, 'sector', $metric));

        $stocks->groupBy('sector')->each(function (Collection $sectorStocks): void {
            $sectorStocks->sortByDesc(fn (object $stock): float => $this->rankingPriority($stock))->values()
                ->each(fn (object $stock, int $index) => $stock->sector_rank = $index + 1);
        });
        $stocks->each(function (object $stock) use ($global, $byIndex, $bySector): void {
            $stock->global_percentiles = $global->map(fn (Collection $values) => $values->get($stock->instrument_id))->all();
            $stock->index_percentiles = $byIndex->map(fn (Collection $values) => $values->get($stock->instrument_id))->all();
            $stock->sector_percentiles = $bySector->map(fn (Collection $values) => $values->get($stock->instrument_id))->all();
        });
    }

    private function percentiles(Collection $stocks, callable $metric): Collection
    {
        $values = $stocks->map($metric)->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->sort()->values();
        if ($values->isEmpty()) {
            return collect();
        }

        return $stocks->mapWithKeys(function (object $stock) use ($metric, $values): array {
            $value = $metric($stock);
            if (! is_numeric($value)) {
                return [$stock->instrument_id => null];
            }
            $value = (float) $value;
            $below = $values->filter(fn (float $candidate): bool => $candidate < $value)->count();
            $equal = $values->filter(fn (float $candidate): bool => abs($candidate - $value) < 0.0000001)->count();

            return [$stock->instrument_id => round((($below + (($equal + 1) / 2)) / $values->count()) * 100, 1)];
        });
    }

    private function groupedPercentiles(Collection $stocks, string $group, callable $metric): Collection
    {
        $result = collect();
        $stocks->groupBy(fn (object $stock): string => (string) ($stock->{$group} ?: '__none__'))
            ->each(function (Collection $items) use ($metric, $result): void {
                $this->percentiles($items, $metric)->each(fn ($value, $instrumentId) => $result->put($instrumentId, $value));
            });

        return $result;
    }

    private function addApplicationData(Collection $stocks, Request $request): Collection
    {
        $instrumentIds = $stocks->pluck('instrument_id');
        $labeledInstrumentIds = $this->hasTable('smart_selection_label_instruments')
            ? DB::table('smart_selection_label_instruments as membership')
                ->join('smart_selection_labels as label', 'label.id', '=', 'membership.smart_selection_label_id')
                ->where('label.user_id', $request->user()->id)
                ->where('label.is_active', true)
                ->whereIn('membership.instrument_id', $instrumentIds)
                ->pluck('membership.instrument_id')->map(fn ($id): int => (int) $id)->unique()
            : collect();
        $savedStrategies = $this->hasTable('saved_prediction_filters')
            ? DB::table('saved_prediction_filters')->where('user_id', $request->user()->id)->get(['filters'])
            : collect();

        $stocks->each(function (object $stock) use ($labeledInstrumentIds, $savedStrategies): void {
            $stock->has_matching_label = $labeledInstrumentIds->contains((int) $stock->instrument_id);
            $stock->has_matching_strategy = $savedStrategies->contains(function (object $strategy) use ($stock): bool {
                $criteria = $this->json($strategy->filters);

                return (float) $stock->score_10 >= (float) ($criteria['score_min'] ?? 0)
                    && (float) ($stock->confidence_percent ?? 0) >= (float) ($criteria['confidence_min'] ?? 0)
                    && (float) ($stock->expected_return_20d ?? -999) >= (float) ($criteria['predicted_return_min'] ?? -20)
                    && ((float) ($criteria['drawdown_max'] ?? 50) >= 50 || (float) ($stock->ranking_drawdown ?? 999) <= (float) $criteria['drawdown_max'])
                    && ((float) ($criteria['profit_factor_min'] ?? 0) <= 0 || (float) ($stock->ranking_profit_factor ?? 0) >= (float) $criteria['profit_factor_min'])
                    && ((float) ($criteria['hit_rate_min'] ?? 0) <= 0 || (float) ($stock->ranking_hit_rate ?? 0) >= (float) $criteria['hit_rate_min']);
            });
        });

        return $stocks;
    }

    /** @return array<string, Collection> */
    private function applicationCollections(Collection $stocks, Request $request): array
    {
        $userWatchlists = DB::table('watchlists')->where('user_id', $request->user()->id)
            ->where('active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);
        $paperPortfolios = DB::table('portfolios')->where('user_id', $request->user()->id)
            ->leftJoin('portfolio_cash_accounts as cash', 'cash.portfolio_id', '=', 'portfolios.id')
            ->where('portfolios.active', true)->where('portfolios.type', 'paper')
            ->orderByDesc('portfolios.is_default')->orderBy('portfolios.name')
            ->get(['portfolios.id', 'portfolios.name', 'portfolios.currency', 'portfolios.meta', DB::raw('COALESCE(cash.balance - cash.reserved_balance, 0) AS available_capital')]);
        $instrumentIds = $stocks->pluck('instrument_id');
        $watchlistMemberships = DB::table('watchlist_items')->whereIn('watchlist_id', $userWatchlists->pluck('id'))
            ->whereIn('instrument_id', $instrumentIds)->get(['watchlist_id', 'instrument_id'])
            ->groupBy('instrument_id')->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id): int => (int) $id)->all());
        $paperPortfolioMemberships = DB::table('portfolio_positions')->whereIn('portfolio_id', $paperPortfolios->pluck('id'))
            ->whereIn('instrument_id', $instrumentIds)->get(['portfolio_id', 'instrument_id'])
            ->groupBy('instrument_id')->map(fn ($items) => $items->pluck('portfolio_id')->map(fn ($id): int => (int) $id)->all());

        return compact('userWatchlists', 'paperPortfolios', 'watchlistMemberships', 'paperPortfolioMemberships');
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function hasTable(string $table): bool
    {
        return Cache::store('file')->remember(
            'screener.schema.table.'.sha1($table),
            now()->addHour(),
            fn (): bool => Schema::hasTable($table),
        );
    }
}
