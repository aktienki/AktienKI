<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scan a predicted-return cutoff grid on walk_forward_horizon_forecasts and
 * report how the realised, cost-adjusted forward performance behaves per
 * cutoff: full sample, and as a walk-forward out-of-sample check where the
 * cutoff is picked only on prior calendar years and applied to the next one.
 *
 * Realised returns are close-to-close on price_bars (interval 1d,
 * adjusted_close): entry on the signal_date bar, exit horizon_days trading
 * days later via LEAD(). Forecasts whose signal_date is not a 1d bar are
 * dropped.
 *
 * --by groups the analysis (sector / instrument). --join-panel adds the
 * panel-model decile as a second, fixed filter dimension and prints an
 * OOS comparison of model-only vs. panel-only vs. both.
 */
final class ScanForecastThresholds extends Command
{
    protected $signature = 'thresholds:scan
        {--algorithm=hist_gradient_boosting_regressor : walk_forward_horizon_forecasts.algorithm to scan}
        {--horizon=5,10,15,20 : comma list of horizon_days}
        {--grid=-0.05,-0.02,0,0.01,0.02,0.03,0.05,0.08,0.1 : predicted_return cutoffs as fractions}
        {--cost-bps=20 : round-trip cost in basis points, subtracted from every trade}
        {--min-n=200 : minimum trades for a bucket to be reliable / eligible for walk-forward selection}
        {--by=none : grouping — none | sector | instrument}
        {--join-panel : attach panel_predictions.decile and print an OOS model/panel/both comparison}
        {--panel-min-decile=7 : required panel decile when --join-panel is set (fixed, not scanned)}
        {--csv= : optional path for the bucket-level long CSV}
        {--trades-csv= : optional path for the per-trade CSV}';

    protected $description = 'Scan a predicted_return cutoff grid on walk_forward_horizon_forecasts vs. realised price_bars returns.';

    public function handle(): int
    {
        $algorithm = (string) $this->option('algorithm');
        $horizons = $this->intList((string) $this->option('horizon'));
        $grid = $this->floatList((string) $this->option('grid'));
        $cost = ((float) $this->option('cost-bps')) / 10_000.0;
        $minN = max(1, (int) $this->option('min-n'));
        $by = (string) $this->option('by');
        $withPanel = (bool) $this->option('join-panel');
        $panelMinDecile = (int) $this->option('panel-min-decile');

        if ($horizons === [] || $grid === []) {
            $this->error('Need at least one --horizon and one --grid value.');

            return self::FAILURE;
        }
        if (! in_array($by, ['none', 'sector', 'instrument'], true)) {
            $this->error('--by must be none, sector or instrument.');

            return self::FAILURE;
        }
        sort($grid);

        $instrumentIds = DB::table('walk_forward_horizon_forecasts')
            ->where('algorithm', $algorithm)
            ->whereIn('horizon_days', $horizons)
            ->distinct()->pluck('instrument_id')->map(fn ($v) => (int) $v)->all();

        if ($instrumentIds === []) {
            $this->error("No forecasts for algorithm={$algorithm} horizons=".implode(',', $horizons));

            return self::FAILURE;
        }

        $meta = DB::table('instruments')->whereIn('id', $instrumentIds)
            ->get(['id', 'symbol', 'sector'])->keyBy('id');

        $this->line(sprintf(
            'algorithm: %s  ·  cost/trade: %.2f%%  ·  min-n: %d  ·  by: %s%s',
            $algorithm, $cost * 100, $minN, $by,
            $withPanel ? "  ·  panel decile ≥ {$panelMinDecile}" : ''
        ));
        $this->newLine();

        $tradesCsv = null;
        if ($path = $this->option('trades-csv')) {
            $tradesCsv = fopen((string) $path, 'w');
            fputcsv($tradesCsv, ['instrument_id', 'symbol', 'sector', 'signal_year', 'horizon', 'predicted_return', 'panel_decile', 'realised_net']);
        }

        $csvRows = [];
        foreach ($horizons as $horizon) {
            $total = DB::table('walk_forward_horizon_forecasts')
                ->where('algorithm', $algorithm)->where('horizon_days', $horizon)->count();
            $trades = $this->realisedTrades($algorithm, $horizon, $instrumentIds, $cost, $meta, $by, $withPanel);

            if ($trades === []) {
                $this->warn("h={$horizon}: {$total} forecasts, 0 matched a bar — skipped.");

                continue;
            }

            $this->components->twoColumnDetail(
                "<fg=cyan;options=bold>Horizon {$horizon}d</>",
                sprintf('%d/%d forecasts priced  ·  %s', count($trades), $total, $this->yearSpan($trades))
            );

            $groups = $this->groupBy($trades, $by);
            $compact = count($groups) > 12;

            foreach ($groups as $label => $gTrades) {
                if ($withPanel) {
                    $this->renderPanelComparison((string) $label, $gTrades, $grid, $minN, $panelMinDecile, $horizon, $csvRows);

                    continue;
                }
                if ($by === 'none') {
                    $this->renderGrid((string) $label, $gTrades, $grid, $minN, $horizon, $csvRows);
                    $this->renderWalkForward($gTrades, $grid, $minN, true);
                } elseif ($compact) {
                    $csvRows = array_merge($csvRows, $this->compactRow((string) $label, $gTrades, $grid, $minN, $horizon));
                } else {
                    $this->line("  <fg=magenta;options=bold>{$label}</> — ".count($gTrades).' trades');
                    $this->renderGrid((string) $label, $gTrades, $grid, $minN, $horizon, $csvRows);
                    $this->renderWalkForward($gTrades, $grid, $minN, false);
                }
            }

            if ($compact && ! $withPanel && $by !== 'none') {
                $this->table(
                    ['group', 'n', 'best cut', 'hit%', 'mean%', 'PF', 'WF-OOS PF'],
                    $this->compactTable($groups, $grid, $minN)
                );
            }

            if ($tradesCsv) {
                foreach ($trades as $t) {
                    fputcsv($tradesCsv, [
                        $t['instrument_id'], $meta[$t['instrument_id']]->symbol ?? '', $meta[$t['instrument_id']]->sector ?? '',
                        $t['year'], $horizon, round($t['predicted'], 6), $t['decile'] ?? '', round($t['net'], 6),
                    ]);
                }
            }
            unset($trades);
            $this->newLine();
        }

        if ($tradesCsv) {
            fclose($tradesCsv);
            $this->info('Trades CSV: '.$this->option('trades-csv'));
        }
        if ($path = $this->option('csv')) {
            $fh = fopen((string) $path, 'w');
            fputcsv($fh, ['horizon', 'group', 'scope', 'cutoff', 'n', 'hit_rate', 'mean_net', 'median_net', 'profit_factor']);
            foreach ($csvRows as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
            $this->info('Bucket CSV: '.$path);
        }

        return self::SUCCESS;
    }

    private function renderGrid(string $group, array $trades, array $grid, int $minN, int $horizon, array &$csvRows): void
    {
        $rows = [$this->row('all', $this->metrics(array_column($trades, 'net')), $minN)];
        $csvRows[] = $this->csvRow($horizon, $group, 'full', null, $this->metrics(array_column($trades, 'net')));
        foreach ($grid as $cut) {
            $sel = array_values(array_filter($trades, fn ($t) => $t['predicted'] >= $cut));
            $m = $this->metrics(array_column($sel, 'net'));
            $rows[] = $this->row($this->pct($cut), $m, $minN);
            $csvRows[] = $this->csvRow($horizon, $group, 'full', $cut, $m);
            foreach ($this->byYear($sel) as $y => $nets) {
                $csvRows[] = $this->csvRow($horizon, $group, "year={$y}", $cut, $this->metrics($nets));
            }
        }
        $this->table(['cutoff ≥', 'n', 'hit%', 'mean%', 'median%', 'PF', ''], $rows);
    }

    private function renderWalkForward(array $trades, array $grid, int $minN, bool $perYear): void
    {
        $years = $this->sortedYears($trades);
        $wfRows = [];
        $oosNets = [];
        foreach ($years as $i => $year) {
            if ($i < 2) {
                continue;
            }
            $prior = array_values(array_filter($trades, fn ($t) => $t['year'] < $year));
            $best = $this->pickCutoff($prior, $grid, $minN);
            $thisYear = array_values(array_filter($trades, fn ($t) => $t['year'] === $year));
            if ($best === null) {
                if ($perYear) {
                    $wfRows[] = [$year, '—', '—', '—', '—', '—'];
                }

                continue;
            }
            $sel = array_column(array_filter($thisYear, fn ($t) => $t['predicted'] >= $best), 'net');
            $oosNets = array_merge($oosNets, $sel);
            $m = $this->metrics($sel);
            if ($perYear) {
                $wfRows[] = [
                    $year, $this->pct($best), $m['n'],
                    $m['n'] ? $this->pct($m['hit']) : '—',
                    $m['n'] ? $this->pct($m['mean']) : '—',
                    $m['pf'] === null ? '∞' : ($m['n'] ? number_format($m['pf'], 2) : '—'),
                ];
            }
        }
        $agg = $this->metrics($oosNets);
        $aggRow = [
            '<options=bold>OOS total</>', '', "<options=bold>{$agg['n']}</>",
            $agg['n'] ? '<options=bold>'.$this->pct($agg['hit']).'</>' : '—',
            $agg['n'] ? '<options=bold>'.$this->pct($agg['mean']).'</>' : '—',
            $agg['pf'] === null ? '∞' : ($agg['n'] ? '<options=bold>'.number_format($agg['pf'], 2).'</>' : '—'),
        ];
        if ($perYear) {
            $this->line('  <options=bold>Walk-forward OOS</> — cutoff = max PF on years &lt; Y with n ≥ min-n');
            $wfRows[] = $aggRow;
            $this->table(['year', 'cutoff', 'n', 'hit%', 'mean%', 'PF'], $wfRows);
        } else {
            $this->line(sprintf(
                '  WF-OOS: n=%d  hit=%s%%  mean=%s%%  PF=%s',
                $agg['n'], $agg['n'] ? $this->pct($agg['hit']) : '—',
                $agg['n'] ? $this->pct($agg['mean']) : '—',
                $agg['pf'] === null ? '∞' : ($agg['n'] ? number_format($agg['pf'], 2) : '—')
            ));
        }
    }

    /** OOS comparison: all vs. model-cutoff vs. panel-decile vs. both. */
    private function renderPanelComparison(string $group, array $trades, array $grid, int $minN, int $minDecile, int $horizon, array &$csvRows): void
    {
        if ($group !== 'ALL') {
            $this->line("  <fg=magenta;options=bold>{$group}</> — ".count($trades).' trades');
        }
        $panelable = array_values(array_filter($trades, fn ($t) => $t['decile'] !== null));
        $panelPredicate = fn ($t) => $t['decile'] !== null && $t['decile'] >= $minDecile;

        $variants = [
            'all' => $this->oosNets($trades, $grid, $minN, null, null),
            'model ≥ X*' => $this->oosNets($trades, $grid, $minN, 'cutoff', null),
            "panel ≥ d{$minDecile}" => $this->oosNets($trades, $grid, $minN, null, $panelPredicate),
            'model & panel' => $this->oosNets($trades, $grid, $minN, 'cutoff', $panelPredicate),
        ];

        $rows = [];
        foreach ($variants as $name => $nets) {
            $m = $this->metrics($nets);
            $rows[] = [
                $name, $m['n'],
                $m['n'] ? $this->pct($m['hit']) : '—',
                $m['n'] ? $this->pct($m['mean']) : '—',
                $m['n'] ? $this->pct($m['median']) : '—',
                $m['pf'] === null ? '∞' : ($m['n'] ? number_format($m['pf'], 2) : '—'),
            ];
            $csvRows[] = $this->csvRow($horizon, $group, 'wf:'.$name, null, $m);
        }
        $this->line(sprintf(
            '  panel coverage: %d/%d priced trades carry a decile', count($panelable), count($trades)
        ));
        $this->table(['OOS variant', 'n', 'hit%', 'mean%', 'median%', 'PF'], $rows);
    }

    /**
     * Accumulate walk-forward OOS net returns for a variant.
     *
     * @param  'cutoff'|null  $mode  scan the predicted_return grid per year, or not
     * @param  callable|null  $predicate  extra per-trade filter applied to every year
     * @return list<float>
     */
    private function oosNets(array $trades, array $grid, int $minN, ?string $mode, ?callable $predicate): array
    {
        if ($predicate !== null) {
            $trades = array_values(array_filter($trades, $predicate));
        }
        $out = [];
        foreach ($this->sortedYears($trades) as $i => $year) {
            if ($i < 2) {
                continue;
            }
            $thisYear = array_values(array_filter($trades, fn ($t) => $t['year'] === $year));
            if ($mode === 'cutoff') {
                $prior = array_values(array_filter($trades, fn ($t) => $t['year'] < $year));
                $best = $this->pickCutoff($prior, $grid, $minN);
                if ($best === null) {
                    continue;
                }
                $thisYear = array_values(array_filter($thisYear, fn ($t) => $t['predicted'] >= $best));
            }
            $out = array_merge($out, array_column($thisYear, 'net'));
        }

        return $out;
    }

    /**
     * @param  list<int>  $instrumentIds
     * @return list<array{instrument_id:int, year:int, predicted:float, net:float, decile:?int, group:string}>
     */
    private function realisedTrades(
        string $algorithm, int $horizon, array $instrumentIds, float $cost, $meta, string $by, bool $withPanel
    ): array {
        $ids = implode(',', array_map('intval', $instrumentIds));
        $panelSelect = $withPanel ? 'pp.decile AS decile' : 'NULL::int AS decile';
        $panelJoin = $withPanel ? <<<'SQL'
            LEFT JOIN LATERAL (
                SELECT p.decile FROM panel_predictions p
                WHERE p.instrument_id = f.instrument_id AND p.as_of_date = f.signal_date::date
                ORDER BY p.model_version DESC LIMIT 1
            ) pp ON true
            SQL : '';

        $sql = <<<SQL
            SELECT f.instrument_id,
                   EXTRACT(YEAR FROM f.signal_date)::int AS yr,
                   f.predicted_return::float8            AS predicted,
                   (b.exit_close / NULLIF(b.entry_close, 0) - 1.0)::float8 AS raw_ret,
                   {$panelSelect}
            FROM walk_forward_horizon_forecasts f
            JOIN (
                SELECT instrument_id,
                       bar_time::date AS d,
                       adjusted_close AS entry_close,
                       LEAD(adjusted_close, ?) OVER (PARTITION BY instrument_id ORDER BY bar_time) AS exit_close
                FROM price_bars
                WHERE interval = '1d' AND instrument_id IN ({$ids})
            ) b ON b.instrument_id = f.instrument_id AND b.d = f.signal_date::date
            {$panelJoin}
            WHERE f.algorithm = ? AND f.horizon_days = ?
              AND b.exit_close IS NOT NULL AND b.entry_close > 0
            SQL;

        $out = [];
        foreach (DB::select($sql, [$horizon, $algorithm, $horizon]) as $r) {
            $iid = (int) $r->instrument_id;
            $out[] = [
                'instrument_id' => $iid,
                'year' => (int) $r->yr,
                'predicted' => (float) $r->predicted,
                'net' => (float) $r->raw_ret - $cost,
                'decile' => $r->decile === null ? null : (int) $r->decile,
                'group' => match ($by) {
                    'sector' => (string) ($meta[$iid]->sector ?? '(no sector)'),
                    'instrument' => (string) ($meta[$iid]->symbol ?? $iid),
                    default => 'ALL',
                },
            ];
        }

        return $out;
    }

    private function groupBy(array $trades, string $by): array
    {
        if ($by === 'none') {
            return ['ALL' => $trades];
        }
        $out = [];
        foreach ($trades as $t) {
            $out[$t['group']][] = $t;
        }
        uksort($out, fn ($a, $b) => count($out[$b]) <=> count($out[$a]));

        return $out;
    }

    private function compactRow(string $group, array $trades, array $grid, int $minN, int $horizon): array
    {
        $rows = [];
        $rows[] = $this->csvRow($horizon, $group, 'full', null, $this->metrics(array_column($trades, 'net')));
        foreach ($grid as $cut) {
            $sel = array_column(array_filter($trades, fn ($t) => $t['predicted'] >= $cut), 'net');
            $rows[] = $this->csvRow($horizon, $group, 'full', $cut, $this->metrics($sel));
        }

        return $rows;
    }

    private function compactTable(array $groups, array $grid, int $minN): array
    {
        $rows = [];
        foreach ($groups as $label => $trades) {
            $best = $this->pickCutoff($trades, $grid, max(1, (int) ($minN / 4)));
            $m = $best === null ? $this->metrics([]) : $this->metrics(array_column(array_filter($trades, fn ($t) => $t['predicted'] >= $best), 'net'));
            $wf = $this->metrics($this->oosNets($trades, $grid, max(1, (int) ($minN / 4)), 'cutoff', null));
            $rows[] = [
                $label, count($trades),
                $best === null ? '—' : $this->pct($best),
                $m['n'] ? $this->pct($m['hit']) : '—',
                $m['n'] ? $this->pct($m['mean']) : '—',
                $m['pf'] === null ? '∞' : ($m['n'] ? number_format($m['pf'], 2) : '—'),
                $wf['pf'] === null ? '∞' : ($wf['n'] ? number_format($wf['pf'], 2) : '—'),
            ];
        }

        return $rows;
    }

    /** @param list<float> $nets */
    private function metrics(array $nets): array
    {
        $n = count($nets);
        if ($n === 0) {
            return ['n' => 0, 'hit' => null, 'mean' => null, 'median' => null, 'pf' => null];
        }
        sort($nets);
        $wins = 0;
        $grossProfit = 0.0;
        $grossLoss = 0.0;
        foreach ($nets as $x) {
            if ($x > 0) {
                $wins++;
                $grossProfit += $x;
            } else {
                $grossLoss += -$x;
            }
        }
        $median = $n % 2 ? $nets[intdiv($n, 2)] : ($nets[$n / 2 - 1] + $nets[$n / 2]) / 2;

        return [
            'n' => $n,
            'hit' => $wins / $n,
            'mean' => array_sum($nets) / $n,
            'median' => $median,
            'pf' => $grossLoss > 0.0 ? $grossProfit / $grossLoss : null,
        ];
    }

    /** Cutoff with the highest profit factor over $trades, among those with n >= $minN. */
    private function pickCutoff(array $trades, array $grid, int $minN): ?float
    {
        $best = null;
        $bestPf = -INF;
        foreach ($grid as $cut) {
            $m = $this->metrics(array_column(array_filter($trades, fn ($t) => $t['predicted'] >= $cut), 'net'));
            if ($m['n'] < $minN) {
                continue;
            }
            $pf = $m['pf'] ?? PHP_FLOAT_MAX;
            if ($pf > $bestPf) {
                $bestPf = $pf;
                $best = $cut;
            }
        }

        return $best;
    }

    private function byYear(array $trades): array
    {
        $out = [];
        foreach ($trades as $t) {
            $out[$t['year']][] = $t['net'];
        }
        ksort($out);

        return $out;
    }

    private function sortedYears(array $trades): array
    {
        $years = array_values(array_unique(array_column($trades, 'year')));
        sort($years);

        return $years;
    }

    private function yearSpan(array $trades): string
    {
        $years = $this->sortedYears($trades);

        return $years === [] ? '—' : $years[0].'–'.$years[count($years) - 1];
    }

    private function row(string $label, array $m, int $minN): array
    {
        return [
            $label,
            $m['n'],
            $m['n'] ? $this->pct($m['hit']) : '—',
            $m['n'] ? $this->pct($m['mean']) : '—',
            $m['n'] ? $this->pct($m['median']) : '—',
            $m['pf'] === null ? ($m['n'] ? '∞' : '—') : number_format($m['pf'], 2),
            $m['n'] === 0 ? '' : ($m['n'] < $minN ? '<fg=yellow>low n</>' : ''),
        ];
    }

    private function csvRow(int $horizon, string $group, string $scope, ?float $cut, array $m): array
    {
        return [
            $horizon, $group, $scope, $cut === null ? '' : round($cut, 6), $m['n'],
            $m['hit'] === null ? '' : round($m['hit'], 6),
            $m['mean'] === null ? '' : round($m['mean'], 6),
            $m['median'] === null ? '' : round($m['median'], 6),
            $m['pf'] === null ? '' : round($m['pf'], 6),
        ];
    }

    private function pct(?float $v): string
    {
        return $v === null ? '—' : number_format($v * 100, 2);
    }

    private function intList(string $raw): array
    {
        return array_values(array_filter(
            array_map(fn ($v) => (int) trim($v), explode(',', $raw)),
            fn ($v) => $v > 0
        ));
    }

    private function floatList(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $v) {
            $v = trim($v);
            if ($v !== '' && is_numeric($v)) {
                $out[] = (float) $v;
            }
        }

        return $out;
    }
}
