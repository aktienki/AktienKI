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
        .brand { color: #2dd4bf; font-weight: 800; font-size: 9px; letter-spacing: 1px; }
        h1 { margin-top: 3px; font-size: 17px; }
        .subtitle { margin-top: 3px; color: #bdcadb; }
        .meta { margin-top: 6px; color: #e1e8f1; }
        .section { margin-top: 9px; page-break-inside: avoid; }
        .section-title { margin-bottom: 4px; color: #0f766e; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
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
        .note { margin-top: 7px; padding: 5px 7px; background: #fff7df; border-left: 3px solid #d97706; color: #5d4a22; font-size: 6.7px; }
        .report-page { page-break-before: always; }
        .model-summary { margin-top: 8px; }
        .model-summary td { width: 25%; padding: 7px 8px; border: 2px solid #fff; background: #eef3f7; }
        .model-summary strong { display: block; margin-top: 2px; color: #0f766e; font-size: 13px; }
        .model-table th:not(:first-child), .model-table td:not(:first-child) { text-align: right; }
        .model-table td:first-child { font-weight: 800; color: #1f3046; }
        .tier { display: inline-block; min-width: 74px; padding: 2px 5px; border-radius: 4px; background: #dce7ef; color: #314258; font-weight: 800; text-align: center; }
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
    ];
    $formatMoney = fn ($value) => number_format((float) $value, 2, ',', '.').' €';
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.').' %';
    $spPerformance = (float) ($result['benchmark_performance'] ?? 0);
@endphp

<div class="header">
    <div class="brand">aktienKI.com</div>
    <h1>Persönlicher 3-Jahres-Backtest</h1>
    <p class="subtitle">20-Tage-Exit, Winner Runner, Prognoseziel und S&amp;P 500 im direkten Vergleich</p>
    <p class="meta">Bericht erstellt am {{ now()->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr · Lauf {{ $run->public_id }}</p>
</div>

<div class="section">
    <div class="section-title">Portfolio-Annahmen</div>
    <table>
        <tr><th>Startkapital</th><th>Maximale Aktien</th><th>Kapital je Aktie</th><th>Kosten je Trade</th></tr>
        <tr>
            <td>{{ $formatMoney($capital) }}</td>
            <td>{{ (int) data_get($settings, 'capital.max_parallel_positions', 10) }}</td>
            <td>{{ $formatMoney(data_get($settings, 'capital.position', $capital / max(1, (int) data_get($settings, 'capital.max_parallel_positions', 10)))) }}</td>
            <td>{{ $formatMoney($tradeCost) }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Ergebnisvergleich</div>
    <table class="comparison">
        <tr><th>Kennzahl</th><th>20 Tage</th><th>Winner Runner</th><th>Prognoseziel</th><th>S&amp;P 500</th></tr>
        <tr><td>Endkapital</td><td>{{ $formatMoney($result['final_capital']) }}</td><td>{{ $formatMoney($result['winner_runner_final_capital']) }}</td><td>{{ $formatMoney($result['prediction_target_final_capital']) }}</td><td>{{ $formatMoney($capital * (1 + $spPerformance / 100)) }}</td></tr>
        <tr><td>Performance</td><td class="{{ $result['strategy_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['strategy_performance']) }}</td><td class="{{ $result['winner_runner_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['winner_runner_performance']) }}</td><td class="{{ $result['prediction_target_performance'] >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($result['prediction_target_performance']) }}</td><td class="{{ $spPerformance >= 0 ? 'positive' : 'negative' }}">{{ $formatPercent($spPerformance) }}</td></tr>
        @php
            $fixedDifference = (float) $result['strategy_performance'] - $spPerformance;
            $runnerDifference = (float) $result['winner_runner_performance'] - $spPerformance;
            $targetDifference = (float) $result['prediction_target_performance'] - $spPerformance;
            $formatPoints = fn ($value) => ($value > 0 ? '+' : '').number_format((float) $value, 2, ',', '.').' Prozentpunkte';
        @endphp
        <tr><td>Differenz zum S&amp;P 500</td><td class="{{ $fixedDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($fixedDifference) }}</td><td class="{{ $runnerDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($runnerDifference) }}</td><td class="{{ $targetDifference >= 0 ? 'positive' : 'negative' }}">{{ $formatPoints($targetDifference) }}</td><td>0,00 Prozentpunkte</td></tr>
        <tr><td>Max. Portfolio-Drawdown</td><td class="negative">{{ $formatPercent($result['portfolio_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['winner_runner_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['prediction_target_max_drawdown']) }}</td><td class="negative">{{ $formatPercent($result['benchmark_max_drawdown']) }}</td></tr>
        <tr><td>Ausgeführte Trades</td><td>{{ number_format($result['executed_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['winner_runner_executed_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['prediction_target_executed_trades'], 0, ',', '.') }}</td><td>1</td></tr>
        <tr><td>Trades pro Monat</td><td>{{ number_format($result['trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format($result['winner_runner_trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format($result['prediction_target_trades_per_month'], 2, ',', '.') }}</td><td>{{ number_format(1 / max(1, (float) $result['backtest_months']), 2, ',', '.') }}</td></tr>
        <tr><td>Übersprungene Signale</td><td>{{ number_format($result['skipped_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['winner_runner_skipped_trades'], 0, ',', '.') }}</td><td>{{ number_format($result['prediction_target_skipped_trades'], 0, ',', '.') }}</td><td>0</td></tr>
        <tr><td>Ø Kapitalbindung</td><td>{{ $formatPercent($result['average_capital_binding']) }}</td><td>{{ $formatPercent($result['winner_runner_average_capital_binding']) }}</td><td>{{ $formatPercent($result['prediction_target_average_capital_binding']) }}</td><td>100,00 %</td></tr>
        <tr><td>Max. Kapitalbindung</td><td>{{ $formatPercent($result['maximum_capital_binding']) }}</td><td>{{ $formatPercent($result['winner_runner_maximum_capital_binding']) }}</td><td>{{ $formatPercent($result['prediction_target_maximum_capital_binding']) }}</td><td>100,00 %</td></tr>
        <tr><td>Handelskosten</td><td>{{ $formatMoney($result['total_costs']) }}</td><td>{{ $formatMoney($result['winner_runner_executed_trades'] * $tradeCost) }}</td><td>{{ $formatMoney($result['prediction_target_executed_trades'] * $tradeCost) }}</td><td>nicht berücksichtigt</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Kapitalentwicklung</div>
    <div class="chart-box">
        <img src="{{ $chart['image'] }}" style="display:block;width:100%;height:142px" alt="Kapitalentwicklung">
        <div class="legend">
            @foreach ($chart['series'] as $line)<span style="color: {{ $line['color'] }}">● {{ $line['name'] }}</span>@endforeach
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Verwendete Filter</div>
    <table class="filter-grid">
        @foreach (collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->chunk(4) as $row)
            <tr>
                @foreach ($row as $key => $value)
                    <td><span class="filter-name">{{ $filterLabels[$key] ?? $key }}</span><span class="filter-value">{{ is_array($value) ? implode(', ', $value) : $value }}</span></td>
                @endforeach
                @for ($empty = $row->count(); $empty < 4; $empty++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
</div>

<div class="note"><strong>Methodik:</strong> Alle aKI-Strategien verwenden dieselben Einstiegssignale, dasselbe Startkapital und dieselben Kosten. Neue Positionen werden nur eröffnet, wenn Kapital und ein freier Aktienplatz verfügbar sind. Winner Runner nutzt ATR-Hard-Stop, Gewinnschutz, Trendbruch, Exit-Modell und maximal 90 Handelstage. Die Prognoseziel-Strategie verkauft beim Erreichen der prognostizierten Rendite, andernfalls nach 20 Handelstagen. Historische Ergebnisse sind keine Garantie für zukünftige Wertentwicklungen und keine Anlageberatung.</div>

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
                return (object) [
                    'tier' => $tier,
                    'models' => $models->count(),
                    'trades' => $trades,
                    'share' => $modelTrades > 0 ? ($trades / $modelTrades) * 100 : 0,
                    'hit_rate' => $trades > 0 ? $models->sum(fn ($model) => (float) $model->hit_rate * (int) $model->trades) / $trades : 0,
                    'average_return' => $trades > 0 ? $models->sum(fn ($model) => (float) $model->average_return * (int) $model->trades) / $trades : 0,
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
                <tr><th>Modellstufe</th><th>Modelle</th><th>Trades</th><th>Trade-Anteil</th><th>Gewichtete Hitrate</th><th>Ø Rendite</th><th>Max. Drawdown</th></tr>
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
                <tr><th>Modell</th><th>Qualitätsstufe</th><th>Trades</th><th>Hitrate</th><th>Ø Rendite</th><th>Profitfaktor</th><th>Max. Drawdown</th><th>Zeitraum</th></tr>
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

    @if ($bestModel)
        <div class="note"><strong>Einordnung:</strong> Die höchste historische Hitrate in dieser Auswahl erreicht {{ $bestModel->model_name }} mit {{ $formatPercent($bestModel->hit_rate) }} bei {{ number_format((int) $bestModel->trades, 0, ',', '.') }} Trades. Kennzahlen mit wenigen Trades besitzen nur eine begrenzte statistische Aussagekraft.</div>
    @endif
</div>
<div class="footer">aktienKI.com · Research - Intelligence - Decisions · Seite <script type="text/php">if (isset($pdf)) { $pdf->text(535, 815, "{PAGE_NUM} / {PAGE_COUNT}", null, 7); }</script></div>
</body>
</html>
