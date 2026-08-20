<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CalculateMarketFactors extends Command
{
    protected $signature = 'market-factors:calculate {--days=14 : Anzahl zurückzurechnender Handelstage}';
    protected $description = 'Berechnet globalen Markttrend und globale technische Stimmung über alle handelbaren Aktien.';

    public function handle(): int
    {
        $days = max(1, min(28, (int) $this->option('days')));
        $dates = DB::table('technical_indicators as technical')
            ->join('instruments as instrument', 'instrument.id', '=', 'technical.instrument_id')
            ->where('technical.interval', '1d')
            ->where('instrument.type', 'stock')->where('instrument.is_active', true)
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
            ->selectRaw('DATE(technical.bar_time) AS trading_date')->distinct()
            ->orderByDesc('trading_date')->limit($days)->pluck('trading_date')->reverse();

        foreach ($dates as $date) {
            $rows = collect(DB::select(<<<'SQL'
                SELECT DISTINCT ON (technical.instrument_id)
                    technical.instrument_id, technical.rsi_14, technical.stochastic_k,
                    technical.roc_12, technical.macd_histogram,
                    technical.sma_20, technical.sma_50, technical.sma_200,
                    price.close
                FROM technical_indicators technical
                JOIN instruments instrument ON instrument.id = technical.instrument_id
                LEFT JOIN LATERAL (
                    SELECT bar.close
                    FROM price_bars bar
                    WHERE bar.instrument_id = technical.instrument_id
                      AND bar.interval = '1d' AND DATE(bar.bar_time) <= ?
                    ORDER BY bar.bar_time DESC, bar.id DESC LIMIT 1
                ) price ON TRUE
                WHERE technical.interval = '1d' AND DATE(technical.bar_time) <= ?
                  AND instrument.type = 'stock' AND instrument.is_active = TRUE
                  AND (instrument.risk_status IS NULL OR instrument.risk_status <> 'sleep')
                  AND instrument.is_german_tradeable = TRUE AND instrument.deleted_at IS NULL
                ORDER BY technical.instrument_id, technical.bar_time DESC, technical.id DESC
            SQL, [$date, $date]));

            [$trend, $timing, $members] = $this->aggregate($rows);
            if ($members === 0) continue;

            DB::table('market_factor_snapshots')->updateOrInsert(
                ['trading_date' => $date, 'scope_type' => 'market', 'scope_key' => '__aggregate__'],
                ['trend_score' => $trend, 'timing_score' => $timing, 'relative_rank' => null,
                    'member_count' => $members, 'meta' => json_encode(['version' => 'global-market-v1']),
                    'created_at' => now(), 'updated_at' => now()]
            );
            $this->line("{$date}: trend={$trend}, stimmung={$timing}, aktien={$members}");
        }

        DB::table('market_factor_snapshots')->whereDate('trading_date', '<', today()->subDays(27))->delete();
        return self::SUCCESS;
    }

    private function aggregate(Collection $rows): array
    {
        $trendScores = [];
        $timingScores = [];
        foreach ($rows as $row) {
            if (! is_numeric($row->close) || ! is_numeric($row->sma_20) || ! is_numeric($row->sma_50)
                || ! is_numeric($row->sma_200) || ! is_numeric($row->roc_12)) continue;
            $close = (float) $row->close;
            $trend = 50.0;
            $trend += $close >= (float) $row->sma_20 ? 10 : -10;
            $trend += (float) $row->sma_50 >= (float) $row->sma_200 ? 15 : -15;
            if (is_numeric($row->macd_histogram)) $trend += (float) $row->macd_histogram >= 0 ? 10 : -10;
            $trend += max(-15, min(15, (float) $row->roc_12));
            $trendScores[] = max(0, min(100, $trend));

            if (is_numeric($row->rsi_14) && is_numeric($row->stochastic_k)) {
                $macdBreadth = is_numeric($row->macd_histogram) && (float) $row->macd_histogram >= 0 ? 65 : 35;
                $timingScores[] = max(0, min(100,
                    ((float) $row->rsi_14 * .40) + ((float) $row->stochastic_k * .35) + ($macdBreadth * .25)
                ));
            }
        }
        return [round(collect($trendScores)->avg() ?? 0, 2), round(collect($timingScores)->avg() ?? 0, 2), count($trendScores)];
    }
}
