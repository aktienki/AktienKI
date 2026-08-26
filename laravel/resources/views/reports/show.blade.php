@php
    $instrument = $data['instrument'] ?? [];
    $transition = $data['transition'] ?? [];
    $current = $data['current_prediction'] ?? [];
    $previous = $data['previous_prediction'] ?? [];
    $fundamentals = $data['fundamentals'] ?? [];
    $bars = array_values(array_filter($data['bars'] ?? [], fn ($bar) => is_numeric($bar['c'] ?? $bar['close'] ?? null)));
    $values = array_map(fn ($bar) => (float) ($bar['c'] ?? $bar['close']), $bars);
    $low = $values ? min($values) : 0;
    $high = $values ? max($values) : 1;
    $span = max(0.0001, $high - $low);
    $points = [];
    foreach ($values as $index => $value) {
        $x = count($values) > 1 ? 24 + ($index * 852 / (count($values) - 1)) : 450;
        $y = 224 - (($value - $low) / $span * 178);
        $points[] = number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
    }
    $target = (float) ($current['predicted_price_20d'] ?? $current['predicted_price_10d'] ?? $current['predicted_price_5d'] ?? 0);
    $targetY = 224 - (($target - $low) / $span * 178);
    $to = strtoupper((string) ($transition['to'] ?? $current['signal'] ?? 'HOLD'));
    $accent = $to === 'BUY' ? '#34d399' : ($to === 'SELL' ? '#fb7185' : '#fbbf24');
    $transitionDate = $transition['current_at'] ?? optional($report->transition_at)->toDateTimeString();
    $reportText = $data['report_html'] ?? $report->report_text ?? __('Für diesen Signalwechsel liegt noch kein KI-Text vor.');
    $decimalSeparator = app()->getLocale() === 'en' ? '.' : ',';
    $thousandsSeparator = app()->getLocale() === 'en' ? ',' : '.';
    $formatPercent = fn ($value) => is_numeric($value) ? number_format((float) $value * 100, 1, $decimalSeparator, $thousandsSeparator) . ' %' : '—';
    $formatNumber = fn ($value, $suffix = '') => is_numeric($value) ? number_format((float) $value, 2, $decimalSeparator, $thousandsSeparator) . $suffix : '—';
    $scoreTen = \App\Support\AiScore::toTen($current['ai_score'] ?? $current['prediction_score'] ?? null);
    $indicatorRows = array_values($data['indicators'] ?? []);
    $buildHeatmap = function (string $xKey, string $yKey, array $xBins, array $yBins) use ($indicatorRows): array {
        $cells = [];
        foreach ($yBins as $yi => $yBin) {
            foreach ($xBins as $xi => $xBin) {
                $matches = array_filter($indicatorRows, function ($row) use ($xKey, $yKey, $xBin, $yBin): bool {
                    return is_numeric($row[$xKey] ?? null) && is_numeric($row[$yKey] ?? null)
                        && (float) $row[$xKey] >= $xBin[0] && (float) $row[$xKey] < $xBin[1]
                        && (float) $row[$yKey] >= $yBin[0] && (float) $row[$yKey] < $yBin[1];
                });
                $returns = array_values(array_filter(array_map(fn ($row) => is_numeric($row['target_return_20d'] ?? null) ? (float) $row['target_return_20d'] : null, $matches), fn ($value) => $value !== null));
                $cells[$yi][$xi] = ['count' => count($matches), 'score' => $returns ? (int) round(count(array_filter($returns, fn ($value) => $value > 0)) / count($returns) * 100) : null];
            }
        }
        return $cells;
    };
    $momentumBins = [[-INF,-2],[-2,0],[0,2],[2,INF]];
    $stochBins = [[0,20],[20,40],[40,60],[60,80],[80,101]];
    $adxBins = [[0,15],[15,25],[25,35],[35,INF]];
    $rsiBins = [[0,30],[30,45],[45,60],[60,70],[70,101]];
    $momentumHeatmap = $buildHeatmap('momentum_10', 'stochastic_k', $momentumBins, $stochBins);
    $adxHeatmap = $buildHeatmap('adx_14', 'rsi_14', $adxBins, $rsiBins);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Signalbericht') }} · {{ $instrument['symbol'] ?? $report->symbol }}</title>
    <style>
        :root{color-scheme:dark;--bg:#071525;--surface:#0d2135;--surface2:#122d45;--line:#1c6079;--muted:#9bb0c6;--text:#f6f9fc;--cyan:#5ee7f7;--teal:#24d2b3;--amber:#fbbf24}
        *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 80% 0%,#12334b 0,#071525 48%,#050d19 100%);color:var(--text);font:14px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.shell{max-width:1140px;margin:0 auto;padding:24px 18px 44px}.hero,.card{border:1px solid var(--line);border-radius:20px;background:linear-gradient(145deg,rgba(18,48,70,.96),rgba(7,25,42,.96));box-shadow:0 14px 38px #0005}.hero{padding:24px 28px;position:relative;overflow:hidden}.hero:after{content:"";position:absolute;inset:auto -10% -75% 30%;height:200px;background:radial-gradient(ellipse,#1c827e33,transparent 68%);pointer-events:none}.eyebrow{color:var(--cyan);font-size:11px;font-weight:900;letter-spacing:.2em;text-transform:uppercase}.hero h1{margin:7px 0 3px;font-size:clamp(28px,4vw,45px);letter-spacing:-.04em}.sub{color:var(--muted);font-size:15px}.actions{position:absolute;right:24px;top:24px;display:flex;gap:8px}.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid #48d5df88;border-radius:10px;padding:8px 12px;color:var(--text);text-decoration:none;background:#123349;font-weight:800}.btn.primary{background:#109b92;color:white;border-color:#45e4d4}.transition{display:inline-flex;margin-top:14px;padding:6px 13px;border-radius:999px;border:1px solid {{ $accent }}99;color:{{ $accent }};background:{{ $accent }}1c;font-weight:900;letter-spacing:.08em}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:11px;margin-top:12px}.metric{padding:15px 17px;min-height:94px}.metric .label{color:var(--muted);font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.metric .value{margin-top:4px;font-size:25px;font-weight:900;letter-spacing:-.03em}.metric .hint{color:var(--muted);font-size:11px}.layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.9fr);gap:12px;margin-top:12px}.card{padding:18px}.compact-card{padding:17px}.card h2{margin:0 0 4px;font-size:18px}.card h3{margin:18px 0 6px;color:var(--cyan);font-size:11px;letter-spacing:.13em;text-transform:uppercase}.muted{color:var(--muted)}.chart{width:100%;height:280px;display:block;margin-top:13px;border:1px solid #2b6174;border-radius:14px;background:linear-gradient(180deg,#102d43,#0a1c2e)}.chart-label{fill:#8fa8bc;font-size:12px}.rows{display:grid;gap:0;margin-top:10px}.row{display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid #2d5267}.row span:first-child{color:var(--muted)}.row strong{text-align:right}.pill{display:inline-flex;border:1px solid #5b7890;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:900}.statgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.stat{padding:10px;border:1px solid #28536b;border-radius:12px;background:#0d2940}.stat b{display:block;color:var(--muted);font-size:9px;letter-spacing:.09em;text-transform:uppercase}.stat strong{display:block;margin-top:2px;font-size:16px}.heatmap-card,.ai-card{margin-top:12px}.heatmap-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.heatmap-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:14px}.heatmap{border:1px solid #28536b;border-radius:14px;padding:12px;background:#0b2438}.heatmap-title{display:flex;justify-content:space-between;gap:8px;color:var(--cyan);font-size:12px}.heatmap-title span{color:var(--muted);font-size:10px}.heatmap-body{display:flex;gap:7px;margin-top:10px}.heatmap-ylabels{display:grid;grid-template-rows:repeat(5,1fr);gap:4px;color:var(--muted);font-size:9px;text-align:right}.heatmap-cells{display:grid;gap:4px;flex:1}.heatmap-row{display:grid;grid-template-columns:repeat(5,1fr);gap:4px}.heat-cell{display:grid;place-items:center;min-height:25px;border-radius:5px;background:var(--cell-color,#183b52);color:#eef8fc;font-size:10px;font-weight:900}.heatmap-xlabels{display:grid;grid-template-columns:repeat(5,1fr);gap:4px;margin-top:4px;color:var(--muted);font-size:9px;text-align:center}.axis-x{margin:5px 0 0 30px;color:var(--muted);font-size:9px;text-align:center}.report-text{white-space:pre-wrap;color:#dce8f3;font-size:13px}.source{margin-top:20px;color:#718aa1;font-size:11px;text-align:center}@media(max-width:850px){.actions{position:static;margin-top:15px}.layout,.heatmap-grid{grid-template-columns:1fr}.grid{grid-template-columns:1fr}.shell{padding:14px 10px 34px}.hero{padding:20px 17px}.chart{height:245px}}
    </style>
</head>
<body>
<main class="shell">
    <header class="hero">
        <div class="eyebrow">{{ __('Aktuelles Signal') }} · {{ __('Offline-Bericht') }}</div>
        <div class="actions"><a class="btn primary" href="{{ route('analysis-reports.pdf', $report) }}" target="_blank" rel="noopener">{{ __('PDF öffnen') }} ↗</a></div>
        <h1>{{ $instrument['symbol'] ?? $report->symbol }} · {{ $instrument['name'] ?? __('Aktie') }}</h1>
        <div class="sub">{{ $instrument['country'] ?? '—' }} · {{ $instrument['sector'] ?? '—' }} · {{ __('Erstellt') }} {{ optional($report->created_at)->format(app()->getLocale() === 'en' ? 'Y-m-d H:i' : 'd.m.Y H:i') }}</div>
        <span class="transition">{{ strtoupper($transition['from'] ?? $report->signal_from ?? '—') }} → {{ $to }}</span>
    </header>

    <section class="grid">
        <article class="card metric"><div class="label">{{ __('Signal') }}</div><div class="value" style="color:{{ $accent }}">{{ $to }}</div><div class="hint">{{ __('Signalwechsel am :date', ['date' => $transitionDate ? date(app()->getLocale() === 'en' ? 'Y-m-d H:i' : 'd.m.Y H:i', strtotime($transitionDate)) : '—']) }}</div></article>
        <article class="card metric"><div class="label">{{ __('KI-Score') }}</div><div class="value">{{ $scoreTen !== null ? number_format($scoreTen, 1, $decimalSeparator, $thousandsSeparator) : '—' }} <span class="hint">/ 10</span></div><div class="hint">{{ __('Qualitätsband') }}: {{ $current['quality_band'] ?? '—' }}</div></article>
        <article class="card metric"><div class="label">{{ __('Konfidenz') }}</div><div class="value" style="color:var(--teal)">{{ $formatPercent($current['confidence'] ?? null) }}</div><div class="hint">{{ __('Risiko') }}: {{ $formatPercent($current['risk_score'] ?? null) }}</div></article>
    </section>

    <div class="layout">
        <section class="card compact-card">
            <h2>{{ __('Kurs & Prognose') }}</h2><div class="muted">{{ __('Historischer Verlauf mit Zielkurs des aktuellen Modells') }}</div>
            <svg class="chart" viewBox="0 0 900 280" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf und Prognose') }}">
                <line x1="24" x2="876" y1="48" y2="48" stroke="#2b5369" stroke-dasharray="5 7"/><line x1="24" x2="876" y1="136" y2="136" stroke="#2b5369" stroke-dasharray="5 7"/><line x1="24" x2="876" y1="224" y2="224" stroke="#2b5369" stroke-dasharray="5 7"/>
                <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#5ee7c7" stroke-width="4" stroke-linejoin="round" stroke-linecap="round"/>
                @if($target > 0)<line x1="24" x2="876" y1="{{ number_format($targetY,1,'.','') }}" y2="{{ number_format($targetY,1,'.','') }}" stroke="#fbbf24" stroke-width="2" stroke-dasharray="8 7"/><polygon points="850,{{ number_format($targetY,1,'.','') }} 870,{{ number_format($targetY-9,1,'.','') }} 870,{{ number_format($targetY+9,1,'.','') }}" fill="#fbbf24"/><text x="690" y="{{ number_format(max(18,$targetY-10),1,'.','') }}" fill="#fbbf24" font-size="13">{{ __('20-Tage-Ziel') }}</text>@endif
                <text class="chart-label" x="28" y="270">{{ __('Historie') }}</text><text class="chart-label" x="790" y="270">{{ __('aktuell') }}</text>
            </svg>
            <div class="rows">
                <div class="row"><span>{{ __('Aktueller Kurs') }}</span><strong>{{ $formatNumber($current['current_price'] ?? null, ' '.($instrument['currency'] ?? '')) }}</strong></div>
                <div class="row"><span>{{ __('20-Tage-Zielkurs') }}</span><strong>{{ $formatNumber($current['predicted_price_20d'] ?? null, ' '.($instrument['currency'] ?? '')) }}</strong></div>
                <div class="row"><span>{{ __('Vorheriges Signal') }}</span><strong>{{ strtoupper($transition['from'] ?? $previous['signal'] ?? '—') }}</strong></div>
            </div>
        </section>
        <aside class="card compact-card">
            <h2>{{ __('Modell- und Risikokarte') }}</h2><div class="muted">{{ __('Werte zum Zeitpunkt des Signalwechsels') }}</div>
            <div class="statgrid" style="margin-top:18px">
                <div class="stat"><b>{{ __('Qualität') }}</b><strong>{{ $current['quality_band'] ?? '—' }}</strong></div>
                <div class="stat"><b>Quality Gate</b><strong>{{ !empty($current['quality_gate_passed']) ? __('bestanden') : __('offen') }}</strong></div>
                <div class="stat"><b>{{ __('Modellalter') }}</b><strong>{{ $formatNumber($current['model_age_days'] ?? null) }} {{ __('Tage') }}</strong></div>
                <div class="stat"><b>{{ __('Backtest') }}</b><strong>{{ $current['backtest_version'] ?? '—' }}</strong></div>
            </div>
            <h3>{{ __('Fundamentals') }}</h3>
            <div class="rows">@foreach(array_slice($fundamentals, 0, 8, true) as $key => $value)<div class="row"><span>{{ __(str_replace('_',' ',ucwords($key))) }}</span><strong>{{ is_numeric($value) ? number_format((float)$value,2,$decimalSeparator,$thousandsSeparator) : ($value ?: '—') }}</strong></div>@endforeach</div>
        </aside>
    </div>

    <section class="card heatmap-card">
        <div class="heatmap-head"><div><h2>{{ __('Indikator-Heatmaps') }}</h2><div class="muted">{{ __('Historische Kombinationen aus Trend, Momentum und Oszillator. Die Zahl zeigt den Anteil positiver 20-Tage-Ziele.') }}</div></div><span class="pill">{{ __(':count Beobachtungen', ['count' => count($indicatorRows)]) }}</span></div>
        <div class="heatmap-grid">
            @foreach([['Momentum 10','Stochastik %K',$momentumHeatmap,$momentumBins,$stochBins],['ADX 14','RSI 14',$adxHeatmap,$adxBins,$rsiBins]] as [$xLabel,$yLabel,$matrix,$xBins,$yBins])
                <div class="heatmap"><div class="heatmap-title"><strong>{{ $xLabel }} × {{ $yLabel }}</strong><span>↑ {{ __('positive Ziele') }}</span></div><div class="heatmap-axis"><span class="axis-y">{{ $yLabel }}</span><div class="heatmap-body"><div class="heatmap-ylabels">@foreach($yBins as $bin)<span>{{ $bin[1] === INF ? $bin[0].'+' : $bin[0].'–'.$bin[1] }}</span>@endforeach</div><div><div class="heatmap-cells">@foreach($matrix as $row)<div class="heatmap-row">@foreach($row as $cell)@php $score=$cell['score']; $hue=$score===null?210:(int)($score*1.2+175); @endphp<span class="heat-cell" style="--cell-color:hsl({{ $hue }},70%,55%)" title="{{ __(':count Fälle', ['count' => $cell['count']]) }}">{{ $score === null ? '—' : $score.'%' }}</span>@endforeach</div>@endforeach</div><div class="heatmap-xlabels">@foreach($xBins as $bin)<span>{{ $bin[1] === INF ? $bin[0].'+' : $bin[0].'–'.$bin[1] }}</span>@endforeach</div></div></div><div class="axis-x">{{ $xLabel }}</div></div></div>
            @endforeach
        </div>
    </section>

    <section class="card ai-card"><div class="eyebrow">ChatGPT · GPT‑5.4 mini</div><h2>{{ __('KI-Einschätzung & Marktimpulse') }}</h2><div class="muted">{{ __('Die Einschätzung stammt aus der KI-Abfrage und ergänzt die unveränderten Kennzahlen aus der Datenbank. News und Termine sind als Szenario gekennzeichnet.') }}</div><div class="report-text" style="margin-top:14px">{{ strip_tags($reportText) }}</div></section>
    <div class="source">{{ __('Datenbasis: aktienKI-Datenbank · Dieser Bericht dient ausschließlich Informationszwecken und ist keine Anlageberatung.') }}</div>
</main>
</body>
</html>
