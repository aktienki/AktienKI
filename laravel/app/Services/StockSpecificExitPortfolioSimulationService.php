<?php

namespace App\Services;

use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class StockSpecificExitPortfolioSimulationService
{
    public const STRATEGY_DEFAULT = 'strategy_default';

    public const STOCK_SPECIFIC_FINAL_EXIT = 'stock_specific_final_exit';

    public const FIXED_HORIZON_20T = 'fixed_horizon_20t';

    public const ADAPTER_VERSION = 'stock-specific-final-exit-to-serving-portfolio-adapter-v2';

    public const SOURCE_TYPE = 'filtered_entry_stock_specific_exit_backtest';

    /** @var array<int, string> */
    public const EXIT_POLICIES = [
        self::STOCK_SPECIFIC_FINAL_EXIT,
        self::FIXED_HORIZON_20T,
    ];

    public function __construct(private readonly ServingPortfolioCalculator $calculator) {}

    /** @return array<string, string> */
    public static function selectablePolicies(): array
    {
        return [
            self::STOCK_SPECIFIC_FINAL_EXIT => 'Aktienspezifischer Exit',
            self::FIXED_HORIZON_20T => 'Fester Exit nach 20 Handelstagen',
            self::STRATEGY_DEFAULT => 'Bisheriger Exit der zugeordneten Strategie',
        ];
    }

    public static function isPreparedExitPolicy(string $policy): bool
    {
        return in_array($policy, self::EXIT_POLICIES, true);
    }

    /** @return array<string, mixed> */
    public function calculate(
        Portfolio $portfolio,
        Collection $assignments,
        string $exitPolicy,
        float $initialCapital,
        string $allocationMode,
        int $maximumPositions,
        float $maxStockAllocationPercent,
    ): array {
        if (strtoupper((string) $portfolio->currency) !== 'EUR') {
            throw new RuntimeException('Der vorbereitete Exit-Depottest verwendet ausschließlich EUR-Kurse.');
        }
        if (! self::isPreparedExitPolicy($exitPolicy)) {
            throw new RuntimeException('Die gewählte Exit-Policy ist für diesen Depottest nicht verfügbar.');
        }
        if ($assignments->isEmpty()) {
            throw new RuntimeException('Dem Musterdepot ist keine Strategie zugeordnet.');
        }

        [$payload, $payloadSha256] = $this->verifiedPayload();
        $window = (string) config('aktienki.portfolio_exit_backtest.window', 'full_common');
        $policyPayload = data_get($payload, "payloads.{$window}.{$exitPolicy}");
        if (! is_array($policyPayload) || ! is_array($policyPayload['sources'] ?? null)
            || $policyPayload['sources'] === []) {
            throw new RuntimeException("Für {$window}/{$exitPolicy} enthält der Exit-Datensatz keine Trades.");
        }

        $symbols = collect($policyPayload['sources'])
            ->map(fn (mixed $source): string => strtoupper(trim((string) data_get($source, 'symbol'))))
            ->filter()->unique()->values();
        $instrumentIds = DB::table('instruments')
            ->whereIn(DB::raw('UPPER(symbol)'), $symbols->all())
            ->where('type', 'stock')
            ->where('is_active', true)
            ->where('is_tradeable', true)
            ->whereNull('deleted_at')
            ->get(['id', 'symbol'])
            ->mapWithKeys(fn (object $instrument): array => [
                strtoupper((string) $instrument->symbol) => (int) $instrument->id,
            ]);

        $sources = $this->prepareSources(
            $policyPayload['sources'],
            $assignments,
            $instrumentIds,
            $exitPolicy,
            $payloadSha256,
        );
        $result = $this->calculator->calculate(
            $sources,
            $initialCapital,
            $maximumPositions,
            $allocationMode,
            $maxStockAllocationPercent / 100,
            ServingPortfolioSimulationService::FEE_RATE,
            ServingPortfolioSimulationService::MINIMUM_FEE,
        );

        return [
            ...$result,
            'source_type' => self::SOURCE_TYPE,
            'calculation_version' => 'filtered-entry-stock-specific-exit-portfolio-v1',
            'exit_policy' => $exitPolicy,
            'exit_policy_label' => self::selectablePolicies()[$exitPolicy],
            'entry_policy' => 'final_filtered_buy_transitions',
            'entry_policy_label' => 'Finale BUY-Übergänge nach aktienspezifischem Eintrittsfilter',
            'exit_models_entry_veto' => false,
            'dataset_window' => $window,
            'source_payload_sha256' => $payloadSha256,
            'adapter_version' => (string) $payload['adapter_version'],
            'research_only' => true,
            'validated_symbols' => $symbols->all(),
            'assigned_strategy_ids' => $assignments->pluck('saved_prediction_filter_id')
                ->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'source_trade_diagnostics' => (array) ($policyPayload['source_trade_diagnostics'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawSources
     * @param  Collection<int, object>  $assignments
     * @param  Collection<string, int>  $instrumentIds
     * @return array<int, array<string, mixed>>
     */
    public function prepareSources(
        array $rawSources,
        Collection $assignments,
        Collection $instrumentIds,
        string $exitPolicy,
        string $payloadSha256,
    ): array {
        if (! self::isPreparedExitPolicy($exitPolicy)) {
            throw new RuntimeException('Die Exit-Policy des vorbereiteten Datensatzes ist ungültig.');
        }
        $assignment = $assignments->sortBy(fn (object $item): array => [
            (int) ($item->priority ?? PHP_INT_MAX),
            (int) ($item->saved_prediction_filter_id ?? PHP_INT_MAX),
        ])->first();
        if (! $assignment || (int) ($assignment->saved_prediction_filter_id ?? 0) < 1) {
            throw new RuntimeException('Die Strategiezuordnung des Musterdepots ist unvollständig.');
        }

        $seenSymbols = [];
        $prepared = [];
        foreach ($rawSources as $source) {
            $symbol = strtoupper(trim((string) ($source['symbol'] ?? '')));
            if ($symbol === '' || isset($seenSymbols[$symbol])) {
                throw new RuntimeException('Der Exit-Datensatz enthält ein leeres oder doppeltes Aktiensymbol.');
            }
            $seenSymbols[$symbol] = true;
            $instrumentId = (int) $instrumentIds->get($symbol, 0);
            if ($instrumentId < 1) {
                throw new RuntimeException("Die Benutzer-Datenbank enthält keine aktive Aktienreferenz für {$symbol}.");
            }
            $trades = collect((array) ($source['trades'] ?? []))->map(function (mixed $trade) use ($source): array {
                if (! is_array($trade)) {
                    throw new RuntimeException('Der Exit-Datensatz enthält einen ungültigen Trade.');
                }

                return [
                    ...$trade,
                    'entry_signal' => (string) ($trade['entry_signal'] ?? 'BUY'),
                    'holding_days' => (int) ($trade['holding_days'] ?? $trade['holding_sessions'] ?? $source['horizon_days'] ?? 20),
                    'strategy_run_id' => (string) ($trade['strategy_run_id'] ?? $source['strategy_run_id'] ?? ''),
                    'strategy' => (string) ($trade['strategy'] ?? $source['strategy'] ?? ''),
                ];
            })->all();
            if ($trades === []) {
                throw new RuntimeException("Der Exit-Datensatz enthält für {$symbol} keine Trades.");
            }

            $prepared[] = [
                ...$source,
                'configuration_key' => implode(':', [
                    'filtered-entry-exit-v1',
                    $exitPolicy,
                    $symbol,
                    substr($payloadSha256, 0, 12),
                ]),
                'instrument_id' => $instrumentId,
                'strategy_id' => (int) $assignment->saved_prediction_filter_id,
                'strategy_name' => trim((string) ($assignment->strategy_name ?? 'Strategie'))
                    .' · '.self::selectablePolicies()[$exitPolicy],
                'priority' => (int) ($assignment->priority ?? 10),
                'variant' => $exitPolicy,
                'variant_label' => self::selectablePolicies()[$exitPolicy],
                'quality_label' => 'Validierter Exit-Vergleich',
                'trades' => $trades,
            ];
        }

        return $prepared;
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function verifiedPayload(): array
    {
        $path = (string) config('aktienki.portfolio_exit_backtest.payload_path');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Der vorbereitete Exit-Datensatz ist auf dem Server nicht lesbar.');
        }
        $actualSha256 = hash_file('sha256', $path);
        $expectedSha256 = strtolower(trim((string) config('aktienki.portfolio_exit_backtest.payload_sha256')));
        if (! is_string($actualSha256) || $expectedSha256 === '' || ! hash_equals($expectedSha256, $actualSha256)) {
            throw new RuntimeException('Die Prüfsumme des vorbereiteten Exit-Datensatzes stimmt nicht.');
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Der vorbereitete Exit-Datensatz enthält kein gültiges JSON.', 0, $exception);
        }
        $contract = (array) ($payload['contract'] ?? []);
        if (($payload['adapter_version'] ?? null) !== self::ADAPTER_VERSION
            || ($payload['research_only'] ?? null) !== true
            || ($contract['signal_adapter_only'] ?? null) !== true
            || ($contract['portfolio_calculator'] ?? null) !== 'App\\Services\\ServingPortfolioCalculator::calculate'
            || ($contract['portfolio_calculator_modified'] ?? null) !== false
            || ($contract['exit_models_entry_veto'] ?? null) !== false
            || ($contract['no_global_strategy_replacement'] ?? null) !== true
            || array_values((array) ($contract['exit_policy_enum'] ?? [])) !== self::EXIT_POLICIES) {
            throw new RuntimeException('Der Vertrag des vorbereiteten Exit-Datensatzes ist nicht kompatibel.');
        }

        return [$payload, $actualSha256];
    }
}
