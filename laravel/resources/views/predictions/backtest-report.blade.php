<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 28px 24px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #18263a; font-size: 7.7px; line-height: 1.22; }
        h1, h2, p { margin: 0; }
        .header { padding: 11px 15px; background: #101e33; color: #fff; border-radius: 8px; }
        .brand { color: #22d3ee; font-weight: 800; font-size: 9px; letter-spacing: 1px; }
        h1 { margin-top: 3px; font-size: 17px; }
        .subtitle { margin-top: 3px; color: #bdcadb; }
        .meta { margin-top: 6px; color: #e1e8f1; }
        .section { margin-top: 9px; page-break-inside: avoid; }
        .section-title { margin-bottom: 4px; color: #0e7490; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dce7ef; color: #314258; font-size: 7px; text-align: left; padding: 4px 5px; }
        td { padding: 3.5px 5px; border-bottom: 1px solid #dce4ec; vertical-align: top; }
        tr:nth-child(even) td { background: #f4f7fa; }
        .comparison th:not(:first-child), .comparison td:not(:first-child) { text-align: right; }
        .positive { color: #087f5b; font-weight: 800; }
        .negative { color: #c92a2a; font-weight: 800; }
        .chart-box { padding: 5px 7px; border: 1px solid #d8e1ea; border-radius: 7px; background: #f8fafc; }
        .legend { margin-top: 2px; text-align: center; }
        .legend span { margin: 0 7px; font-weight: 700; }
        .filter-grid { width: 100%; }
        .filter-grid td { width: 25%; padding: 3px 5px; border: 1px solid #fff; background: #eef3f7; }
        .filter-name { display: block; color: #66758a; font-size: 6px; text-transform: uppercase; }
        .filter-value { display: block; margin-top: 1px; font-weight: 800; color: #1f3046; }
        .note { margin-top: 5px; padding: 4px 7px; background: #fff7df; border-left: 3px solid #d97706; color: #5d4a22; font-size: 6.4px; line-height: 1.16; page-break-inside: avoid; }
        .report-page { page-break-before: always; }
        .model-summary { margin-top: 8px; }
        .model-summary td { width: 25%; padding: 7px 8px; border: 2px solid #fff; background: #eef3f7; }
        .model-summary strong { display: block; margin-top: 2px; color: #0e7490; font-size: 13px; }
        .model-table th:not(:first-child), .model-table td:not(:first-child) { text-align: right; }
        .model-table td:first-child { font-weight: 800; color: #1f3046; }
        .tier { display: inline-block; min-width: 74px; padding: 2px 5px; border-radius: 4px; background: #dce7ef; color: #314258; font-weight: 800; text-align: center; }
        .exit-matrix th:not(:first-child), .exit-matrix td:not(:first-child) { text-align: center; }
        .exit-matrix td:first-child { width: 22%; font-weight: 800; color: #1f3046; }
        .exit-matrix strong, .exit-matrix span { display: block; }
        .exit-matrix span { margin-top: 1px; color: #66758a; font-size: 6.3px; }
        .stock-table th:not(:nth-child(2)), .stock-table td:not(:nth-child(2)) { text-align: center; }
        .stock-table td:first-child { font-weight: 800; color: #0e7490; }
        .stock-table td:nth-child(2) { color: #1f3046; }
        .footer { position: fixed; bottom: -20px; left: 0; right: 0; color: #7a8797; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    $filters = (array) data_get($settings, 'selection_filters', []);
    $capital = (float) data_get($settings, 'capital.initial', $result['initial_capital']);
    $tradeCost = (float) data_get($settings, 'capital.trade_cost_eur', $result['trade_cost']);
    $filterLabels = [
        'q' => 'Aktie', 'country' => 'Land', 'exchange' => 'Börse', 'sector' => 'Sektor',
        'ai_type' => 'KI-Typ', 'model' => 'Modell', 'quality_tier' => 'Modellstufe mindestens', 'signal' => 'Signal',
        'score_min' => 'KI-Score mindestens', 'confidence_min' => 'Konfidenz mindestens',
        'drawdown_max' => 'Drawdown maximal', 'profit_factor_min' => 'Profitfaktor mindestens',
        'volatility_max' => 'Volatilität maximal', 'pe_max' => 'KGV maximal',
        'dividend_yield_min' => 'Dividendenrendite mindestens', 'market_cap_min' => 'Marktkapitalisierung mindestens',
        'revenue_growth_min' => 'Umsatzwachstum mindestens', 'hit_rate_min' => 'Hitrate mindestens',
        'sector_score_rotation' => 'KI-Score-Sektorrotation', 'index_score_rotation' => 'KI-Score-Indexrotation',
        'position_factor' => 'Maximaler Positionsanteil', 'exit_strategy' => 'Exitstrategie',
    ];
    $formatMoney = fn ($value) => number_format((float) $value, 2, ',', '.').' €';
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.').' %';
    $formatFactorUsage = fn ($usage) => collect((array) $usage)
        ->filter(fn ($count, $factor) => (float) $factor > 1 && (int) $count > 0)
        ->map(fn ($count, $factor) => str_replace('.', ',', (string) $factor).'×: '.number_format((int) $count, 0, ',', '.'))
        ->implode(' · ') ?: 'Keine Aufstockung';
    $spPerformance = (float) ($result['benchmark_performance'] ?? 0);
    $selectedExitStrategy = (string) ($filters['exit_strategy'] ?? 'fixed_20d');
    $exitStrategyLabels = [
        'fixed_20d' => '20 Tage',
        'winner_runner' => 'Winner Runner',
        'prediction_target' => 'Prognoseziel',
        'buy_and_hold' => 'Buy and Hold',
    ];
    $selectedExitStrategyLabel = $exitStrategyLabels[$selectedExitStrategy] ?? $selectedExitStrategy;
    $moneyManagerEnabled = $selectedExitStrategy !== 'buy_and_hold';
    $executionHorizon = (int) ($run->horizon_days ?? 20);
    $optimizationWeights = (array) data_get($settings, 'optimization.horizon_weights', []);
    $multiHorizonOptimization = data_get($settings, 'optimization.mode') === 'automatic_multi_horizon';
    $benchmarkStartDate = ! empty($result['benchmark']) ? date('d.m.Y', (int) ($result['benchmark'][0]['x'] / 1000)) : '—';
    $benchmarkEndDate = ! empty($result['benchmark']) ? date('d.m.Y', (int) (end($result['benchmark'])['x'] / 1000)) : '—';
@endphp

<div class="header">
    <div class="brand">aktienKI.com</div>
    <h1>Persönlicher 3-Jahres-Backtest</h1>
    <p class="subtitle">Ausführungshorizont {{ $executionHorizon }} Tage · Winner Runner, Prognoseziel, Buy and Hold, adaptive Marktrotation und S&amp;P 500 im direkten Vergleich</p>
    <p class="meta">Bericht erstellt am {{ now()->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr · Lauf {{ $run->public_id }}</p>
</div>

<div class="section">
    <div class="section-title">Verwendeter Horizont und Gesamtstatistik</div>
    <div class="note">
        <strong>Tatsächlich gehandelt:</strong> {{ $executionHorizon }}-Tage-Horizont.
        @if ($multiHorizonOptimization)
            Für die automatische Vorauswahl wurden zusätzlich 5, 10, 15 und 20 Tage ausgewertet. Diese Horizonte beeinflussen die Auswahl, werden in diesem Lauf aber nicht gleichzeitig gehandelt.
        @endif
    </div>
    <table style="margin-top:5px" class="comparison">
        <thead><tr><th>Horizont</th><th>Gewichtung</th><th>Aktien</th><th>Trades</th><th>Hitrate</th><th>Ø Netto-Rendite/Trade</th><th>Profitfaktor</th></tr></thead>
        <tbody>
        @forelse ($horizonStatistics as $horizon)
            @php($days = $horizon['horizon_days'])
            <tr>
                <td><strong>{{ $days === null ? 'Gesamt' : $days.' Tage' }}</strong>{{ $days === $executionHorizon ? ' · gehandelt' : '' }}</td>
                <td>{{ $days !== null && isset($optimizationWeights[$days]) ? number_format((float) $optimizationWeights[$days], 0, ',', '.').' %' : ($days === $executionHorizon && ! $multiHorizonOptimization ? '100 %' : '—') }}</td>
                <td>{{ number_format((int) $horizon['instruments'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $horizon['trades'], 0, ',', '.') }}</td>
                <td class="{{ (float) $horizon['hit_rate'] >= 50 ? 'positive' : 'negative' }}">{{ $formatPercent($horizon['hit_rate']) }}</td>
                <td class="{{ (float) $horizon['average_return'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($horizon['average_return']) }}</td>
                <td>{{ $horizon['profit_factor'] === null ? '∞' : number_format((float) $horizon['profit_factor'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Für die verwendeten Horizonte ist keine Walk-Forward-Gesamtstatistik verfügbar.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Buy-and-Hold-Vergleich</div>
    <table>
        <tr><th>Erster Kauf</th><th>Endkapital</th><th>Performance</th><th>Max. Drawdown</th><th>Gekaufte Aktien</th><th>Money Manager</th></tr>
        <tr>
            <td>{{ ! empty($result['buy_and_hold_entry_at']) ? date('d.m.Y', (int) ($result['buy_and_hold_entry_at'] / 1000)) : '—' }}</td>
            <td>{{ $formatMoney($result['buy_and_hold_final_capital']) }}</td>
            <td class="{{ $result['buy_and_hold_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['buy_and_hold_performance']) }}</td>
            <td class="negative">{{ $formatPercent($result['buy_and_hold_max_drawdown']) }}</td>
            <td>{{ number_format($result['buy_and_hold_executed_trades'], 0, ',', '.') }}</td>
            <td>Deaktiviert · 1×</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Portfolio-Annahmen</div>
    <table>
        <tr><th>Startkapital</th><th>Maximale Aktien</th><th>Grundanteil</th><th>Max. Anteil je Aktie</th><th>Kosten je Trade</th></tr>
        <tr>
            <td>{{ $formatMoney($capital) }}</td>
            <td>{{ (int) data_get($settings, 'capital.max_parallel_positions', 5) }}</td>
            <td>{{ $formatMoney(data_get($settings, 'capital.position', $capital / max(1, (int) data_get($settings, 'capital.max_parallel_positions', 5)))) }}</td>
            <td>{{ (int) data_get($settings, 'capital.position_factor', 1) }}/{{ (int) data_get($settings, 'capital.max_parallel_positions', 5) }} · {{ $formatMoney(data_get($settings, 'capital.maximum_position', data_get($settings, 'capital.position', 0))) }}</td>
            <td>{{ $formatMoney($tradeCost) }}</td>
        </tr>
    </table>
    <table style="margin-top:6px">
        <tr><th>Gewählte Strategie</th><th>Funktionsweise</th><th>Money Manager</th><th>Positionsfaktor</th></tr>
        <tr>
            <td><strong>{{ $selectedExitStrategyLabel }}</strong></td>
            <td>{{ match ($selectedExitStrategy) {
                'winner_runner' => 'Gewinne laufen lassen, Schutz- und Trend-Exit',
                'prediction_target' => 'Ausstieg beim Erreichen des Prognoseziels',
                'buy_and_hold' => 'Gekaufte Aktien werden dauerhaft gehalten',
                default => 'Ausstieg nach 20 Handelstagen',
            } }}</td>
            <td class="{{ $moneyManagerEnabled ? 'positive' : 'negative' }}">{{ $moneyManagerEnabled ? 'Aktiv' : 'Deaktiviert' }}</td>
            <td>{{ $moneyManagerEnabled ? ((int) data_get($settings, 'capital.position_factor', 1)).'×' : '1× (fest)' }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Ergebnisvergleich</div>
    <table class="comparison">
        <tr><th>Kennzahl</th><th>20 Tage</th><th>Winner Runner</th><th>Prognoseziel</th><th>Adaptive Rotation</th><th>S&amp;P 500</th></tr>
        <tr><td>Endkapital</td><td>{{ $formatMoney($result['final_capital']) }}</td><td>{{ $formatMoney($result['winner_runner_final_capital']) }}</td><td>{{ $formatMoney($result['prediction_target_final_capital']) }}</td><td>{{ $formatMoney($result['adaptive_rotation_final_capital']) }}</td><td>{{ $formatMoney($result['benchmark_final_capital'] ?? 0) }}<br><small>{{ $benchmarkStartDate }}–{{ $benchmarkEndDate }}</small></td></tr>
        <tr><td>Performance</td><td class="{{ $result['strategy_gross_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['strategy_gross_performance']) }}</td><td class="{{ $result['winner_runner_gross_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['winner_runner_gross_performance']) }}</td><td class="{{ $result['prediction_target_gross_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['prediction_target_gross_performance']) }}</td><td class="{{ $result['adaptive_rotation_gross_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['adaptive_rotation_gross_performance']) }}</td><td class="{{ $spPerformance >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($spPerformance) }}</td></tr>
        <tr><td>Handelskosten</td><td>{{ $formatMoney($result['total_costs']) }}</td><td>{{ $formatMoney($result['winner_runner_executed_trades'] * $tradeCost) }}</td><td>{{ $formatMoney($result['prediction_target_executed_trades'] * $tradeCost) }}</td><td>{{ $formatMoney($result['adaptive_rotation_executed_trades'] * $tradeCost) }}</td><td>nicht berücksichtigt</td></tr>
        <tr><td>Performance nach Kosten</td><td class="{{ $result['strategy_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['strategy_performance']) }}</td><td class="{{ $result['winner_runner_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['winner_runner_performance']) }}</td><td class="{{ $result['prediction_target_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['prediction_target_performance']) }}</td><td class="{{ $result['adaptive_rotation_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['adaptive_rotation_performance']) }}</td><td class="{{ $spPerformance >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($spPerformance) }}</td></tr>
        @php
            $fixedDifference = (float) $result['strategy_performance'] - $spPerformance;
            $runnerDifference = (float) $result['winner_runner_performance'] - $spPerformance;
            $targetDifference = (float) $result['prediction_target_performance'] - $spPerformance;
            $adaptiveDifference = (float) $result['adaptive_rotation_performance'] - $spPerformance;
            $formatPoints = fn ($value) => ($value > 0 ? '+' : '').number_format((float) $value, 2, ',', '.').' Prozentpunkte';
        @endphp
        <tr><td>Differenz zum S&amp;P 500</td><td class="{{ $fixedDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($fixedDifference) }}</td><td class="{{ $runnerDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($runnerDifference) }}</td><td class="{{ $targetDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($targetDifference) }}</td><td class="{{ $adaptiveDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($adaptiveDifference) }}</td><td>0,00 Prozentpunkte</td></tr>
        <tr><td>Max. Portfolio-Drawdown</td><td class="negative">{{ $formatPercent($result['portfolio_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['winner_runner_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['prediction_target_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['adaptive_rotation_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['benchmark_max_drawdown']) }}</td></tr>
        <tr><td>Ausgeführte Trades</td><td>{{ number_format($result['executed_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['winner_runner_executed_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['prediction_target_executed_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['adaptive_rotation_executed_trades'], 0, ',', '.') }}</td><td>1</td></tr>
        <tr><td>Aufgestockte Positionen</td><td>{{ $formatFactorUsage($result['position_factor_usage'] ?? []) }}</td><td>{{ $formatFactorUsage($result['winner_runner_position_factor_usage'] ?? []) }}</td><td>{{ $formatFactorUsage($result['prediction_target_position_factor_usage'] ?? []) }}</td><td>{{ $formatFactorUsage($result['adaptive_rotation_position_factor_usage'] ?? []) }}</td><td>—</td></tr>
        <tr><td>Trades pro Monat</td><td>{{ number_format($result['trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format($result['winner_runner_trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format($result['prediction_target_trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format($result['adaptive_rotation_trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format(1 / max(1, (float) $result['backtest_months']), 2, ',', '.') }}</td></tr>
        <tr><td>Übersprungene Signale</td><td>{{ number_format($result['skipped_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['winner_runner_skipped_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['prediction_target_skipped_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['adaptive_rotation_skipped_trades'], 0, ',', '.') }}</td><td>0</td></tr>
        <tr><td>Ø Kapitalbindung</td><td>{{ $formatPercent($result['average_capital_binding']) }}</td><td>{{ $formatPercent($result['winner_runner_average_capital_binding']) }}</td><td>{{ $formatPercent($result['prediction_target_average_capital_binding']) }}</td><td>{{ $formatPercent($result['adaptive_rotation_average_capital_binding']) }}</td><td>100,00 %</td></tr>
        <tr><td>Max. Kapitalbindung</td><td>{{ $formatPercent($result['maximum_capital_binding']) }}</td><td>{{ $formatPercent($result['winner_runner_maximum_capital_binding']) }}</td><td>{{ $formatPercent($result['prediction_target_maximum_capital_binding']) }}</td><td>{{ $formatPercent($result['adaptive_rotation_maximum_capital_binding']) }}</td><td>100,00 %</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Kapitalentwicklung</div>
    <div class="chart-box">
        <img src="{{ $chart['image'] }}" style="display:block;width:100%;height:130px" alt="Kapitalentwicklung">
        <div class="legend">
            @foreach ($chart['series'] as $line)<span style="color: {{ $line['color'] }}">● {{ $line['name'] }}</span>@endforeach
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Statistik der adaptiven Marktrotation</div>
    <table class="model-summary">
        <tr>
            <td><span class="filter-name">Kandidaten</span><strong>{{ number_format($adaptiveStatistics->candidates, 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Zugelassen</span><strong>{{ number_format($adaptiveStatistics->eligible, 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Ausgesondert</span><strong>{{ number_format($adaptiveStatistics->rejected, 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Schwache Marktphase</span><strong>{{ number_format($adaptiveStatistics->weak, 0, ',', '.') }}</strong></td>
        </tr>
    </table>
    @if ($adaptiveStatistics->sectors->isNotEmpty())
        <p class="meta" style="color:#52647a"><strong>Bevorzugte Sektoren in schwachen Marktphasen:</strong> {{ $adaptiveStatistics->sectors->map(fn ($sector) => $sector->sector.' ('.$sector->trades.')')->implode(', ') }}</p>
    @endif
    @if ($adaptiveStatistics->sector_overweights || $adaptiveStatistics->index_overweights)
        <p class="meta" style="color:#52647a"><strong>KI-Score-Übergewichtung:</strong> bester Sektor {{ number_format($adaptiveStatistics->sector_overweights, 0, ',', '.') }} Trades · bester Index {{ number_format($adaptiveStatistics->index_overweights, 0, ',', '.') }} Trades · Gewichtungsfaktor maximal 1,5</p>
    @endif
</div>

<div class="section">
    <div class="section-title">Verwendete Filter</div>
    <table class="filter-grid">
        @foreach (collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->chunk(4) as $row)
            <tr>
                @foreach ($row as $key => $value)
                    <td><span class="filter-name">{{ $filterLabels[$key] ?? $key }}</span><span class="filter-value">{{ $key === 'exit_strategy' ? ($exitStrategyLabels[$value] ?? $value) : (is_array($value) ? implode(', ', $value) : $value) }}</span></td>
                @endforeach
                @for ($empty = $row->count(); $empty < 4; $empty++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
</div>

<div class="note"><strong>Methodik:</strong> Alle aKI-Strategien verwenden dieselben Einstiegssignale, dasselbe Startkapital und dieselben Kosten. Neue Positionen werden nur eröffnet, wenn Kapital und ein freier Aktienplatz verfügbar sind. Bei aktivierter KI-Score-Rotation erhält der Sektor beziehungsweise Index mit dem höchsten damaligen Durchschnittsscore eine um 50 % erhöhte Positionsgröße; beide Faktoren werden nicht multipliziert. Winner Runner nutzt ATR-Hard-Stop, Gewinnschutz, Trendbruch, Exit-Modell und maximal 90 Handelstage. Die Prognoseziel-Strategie verkauft beim Erreichen der prognostizierten Rendite, andernfalls nach 20 Handelstagen. Historische Ergebnisse sind keine Garantie für zukünftige Wertentwicklungen und keine Anlageberatung.</div>

<div class="report-page">
    <div class="header">
        <div class="brand">aktienKI.com</div>
        <h1>Statistik der verwendeten Modelle</h1>
        <p class="subtitle">Historische Ergebnisse der im gefilterten Backtest enthaltenen Modelle</p>
    </div>

    @php
        $modelTrades = (int) $modelStatistics->sum('trades');
        $qualifiedModels = $modelStatistics->filter(fn ($model) => (string) $model->quality_tier !== 'Nicht qualifiziert')->count();
        $weightedHitRate = $modelTrades > 0
            ? $modelStatistics->sum(fn ($model) => (float) $model->hit_rate * (int) $model->trades) / $modelTrades
            : 0;
        $bestModel = $modelStatistics->sortByDesc(fn ($model) => (float) $model->hit_rate)->first();
        $tierStatistics = $modelStatistics
            ->groupBy('quality_tier')
            ->map(function ($models, $tier) use ($modelTrades) {
                $trades = (int) $models->sum('trades');
                $deployedCapital = (float) $models->sum('deployed_capital');
                return (object) [
                    'tier' => $tier,
                    'models' => $models->count(),
                    'trades' => $trades,
                    'share' => $modelTrades > 0 ? ($trades / $modelTrades) * 100 : 0,
                    'hit_rate' => $trades > 0 ? $models->sum(fn ($model) => (float) $model->hit_rate * (int) $model->trades) / $trades : 0,
                    'average_return' => $deployedCapital > 0 ? $models->sum(fn ($model) => (float) $model->average_return * (float) $model->deployed_capital) / $deployedCapital : 0,
                    'max_drawdown' => (float) $models->max('max_drawdown'),
                ];
            })
            ->sortBy(function ($tier) {
                $position = array_search($tier->tier, ['Quality Gate', 'Top', 'Stark', 'Solide', 'Basis', 'Start', 'Nicht qualifiziert'], true);
                return $position === false ? 99 : $position;
            })
            ->values();
    @endphp
    <table class="model-summary">
        <tr>
            <td><span class="filter-name">Verwendete Modelle</span><strong>{{ number_format($modelStatistics->count(), 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Qualifizierte Modelle</span><strong>{{ number_format($qualifiedModels, 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Modell-Trades</span><strong>{{ number_format($modelTrades, 0, ',', '.') }}</strong></td>
            <td><span class="filter-name">Gewichtete Hitrate</span><strong>{{ $formatPercent($weightedHitRate) }}</strong></td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Ergebnisse nach Modellstufe</div>
        <table class="model-table">
            <thead>
                <tr><th>Modellstufe</th><th>Modelle</th><th>Trades</th><th>Trade-Anteil</th><th>Gewichtete Hitrate</th><th>Performance</th><th>Max. Drawdown</th></tr>
            </thead>
            <tbody>
            @foreach ($tierStatistics as $tier)
                <tr>
                    <td><span class="tier">{{ $tier->tier }}</span></td>
                    <td>{{ number_format($tier->models, 0, ',', '.') }}</td>
                    <td>{{ number_format($tier->trades, 0, ',', '.') }}</td>
                    <td>{{ $formatPercent($tier->share) }}</td>
                    <td class="{{ $tier->hit_rate >= 50 ? 'positive' : 'negative' }}">{{ $formatPercent($tier->hit_rate) }}</td>
                    <td class="{{ $tier->average_return >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($tier->average_return) }}</td>
                    <td class="negative">{{ $formatPercent($tier->max_drawdown) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Ergebnisse nach Modell</div>
        <table class="model-table">
            <thead>
                <tr><th>Modell</th><th>Qualitätsstufe</th><th>Trades</th><th>Hitrate</th><th>Performance</th><th>Profitfaktor</th><th>Max. Drawdown</th><th>Zeitraum</th></tr>
            </thead>
            <tbody>
            @forelse ($modelStatistics as $model)
                <tr>
                    <td>{{ $model->model_name }}</td>
                    <td><span class="tier">{{ $model->quality_tier }}</span></td>
                    <td>{{ number_format((int) $model->trades, 0, ',', '.') }}</td>
                    <td class="{{ (float) $model->hit_rate >= 50 ? 'positive' : 'negative' }}">{{ $formatPercent($model->hit_rate) }}</td>
                    <td class="{{ (float) $model->average_return >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($model->average_return) }}</td>
                    <td>{{ $model->profit_factor === null ? '∞' : number_format((float) $model->profit_factor, 2, ',', '.') }}</td>
                    <td class="negative">{{ $formatPercent($model->max_drawdown) }}</td>
                    <td>{{ date('m/y', strtotime((string) $model->first_trade)) }}–{{ date('m/y', strtotime((string) $model->last_trade)) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Für diesen Lauf sind keine Modellstatistiken verfügbar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Matrix Modell × Exit-Strategie</div>
        @php
            $exitStrategies = [
                'fixed_20d' => '20 Tage',
                'winner_runner' => 'Winner Runner',
                'prediction_target' => 'Prognoseziel',
                'adaptive_rotation_20d' => 'Adaptive Rotation',
            ];
        @endphp
        <table class="exit-matrix">
            <thead>
                <tr><th>Modell</th>@foreach ($exitStrategies as $strategyLabel)<th>{{ $strategyLabel }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @forelse ($modelExitMatrix as $modelName => $strategyRows)
                <tr>
                    <td>{{ $modelName }}</td>
                    @foreach ($exitStrategies as $strategyCode => $strategyLabel)
                        @php($cell = $strategyRows->firstWhere('strategy', $strategyCode))
                        <td>
                            @if ($cell)
                                <strong class="{{ (float) $cell->average_return >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($cell->average_return) }}</strong>
                                <span>{{ $formatPercent($cell->hit_rate) }} Hitrate · {{ number_format((int) $cell->trades, 0, ',', '.') }} Trades</span>
                            @else
                                <span>Keine Daten</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5">Für diesen Lauf sind keine Daten zur Exit-Matrix verfügbar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Im Backtest berücksichtigte Aktien ({{ number_format($backtestStocks->count(), 0, ',', '.') }})</div>
        <table class="stock-table">
            <thead><tr><th>Symbol</th><th>Aktie</th><th>Land</th><th>Börse</th><th>Trades</th><th>Hitrate</th><th>Ø Rendite</th></tr></thead>
            <tbody>
            @forelse ($backtestStocks as $stock)
                <tr>
                    <td>{{ $stock->symbol }}</td>
                    <td>{{ $stock->name }}</td>
                    <td>{{ $stock->country ?: '—' }}</td>
                    <td>{{ $stock->exchange }}</td>
                    <td>{{ number_format((int) $stock->trades, 0, ',', '.') }}</td>
                    <td class="{{ (float) $stock->hit_rate >= 50 ? 'positive' : 'negative' }}">{{ $formatPercent($stock->hit_rate) }}</td>
                    <td class="{{ (float) $stock->average_return >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($stock->average_return) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Für diesen Lauf sind keine Aktien verfügbar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($bestModel)
        <div class="note"><strong>Einordnung:</strong> Die höchste historische Hitrate in dieser Auswahl erreicht {{ $bestModel->model_name }} mit {{ $formatPercent($bestModel->hit_rate) }} bei {{ number_format((int) $bestModel->trades, 0, ',', '.') }} Trades. Kennzahlen mit wenigen Trades besitzen nur eine begrenzte statistische Aussagekraft.</div>
    @endif
</div>
<div class="footer">aktienKI.com · Research - Intelligence - Decisions · Seite <script type="text/php">if (isset($pdf)) { $pdf->text(535, 815, "{PAGE_NUM} / {PAGE_COUNT}", null, 7); }</script></div>
</body>
</html>
