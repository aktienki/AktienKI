<!doctype html><html lang="de"><head><meta charset="utf-8"><style>
@page{margin:9px 11px;background:#f3faf9}body{margin:0;background:#f3faf9;font-family:DejaVu Sans,sans-serif;color:#172033;font-size:6.5px;line-height:1.05}h1{font-size:13px;margin:0 0 1px;color:#0f3d46}h2{font-size:8.5px;margin:4px 0 2px;color:#0f3d46}.muted{color:#64748b;font-size:5.8px}.notice{margin:4px 0;padding:4px 6px;border:1px solid #e1b955;border-radius:4px;background:#fff8df;color:#684b0c;font-size:6px;font-weight:bold}.overview{width:100%;border-collapse:separate;border-spacing:3px;margin:3px -3px}.depot-card,.chart-card{height:104px;padding:6px;border:1px solid #9fd7d2;border-radius:5px;background:#f8fdfc;vertical-align:top}.depot-card h2,.chart-card h2{margin:0 0 5px;font-size:8px;text-transform:uppercase;letter-spacing:.4px;color:#0e7490}.depot-row{padding:2px 0;border-bottom:1px solid #d2e7e4}.depot-row span{color:#64748b}.depot-row b{float:right;color:#172033}.strategy{display:inline-block;margin:3px 2px 0 0;padding:2px 4px;border:1px solid #73aaa5;border-radius:3px;background:#dcebe7;color:#176c68;font-size:5.5px;font-weight:bold}.grid{width:100%;border-collapse:separate;border-spacing:2px;margin:3px -2px 2px}.card{background:#f8fdfc;border:1px solid #b8ddd9;border-radius:3px;padding:3px 3px;white-space:nowrap}.card b{font-size:5.2px;text-transform:uppercase;color:#64748b}.value{font-size:8px;font-weight:bold;margin-top:1px;color:#172033}table.data{width:100%;border-collapse:collapse;margin-top:1px;table-layout:fixed;background:#fbfefd}table.data th,table.data td{padding:1.4px 2.5px;border-bottom:.5px solid #d5e8e5;text-align:left;line-height:1.05}table.data th{background:#155e75;color:#ecfeff;font-size:5.3px;text-transform:uppercase}table.data tbody tr:nth-child(even){background:#eef8f6}table.data td:nth-child(1){width:11%}table.data td:nth-child(2){width:9%;font-weight:bold}table.data td:nth-child(3){width:12%;font-weight:bold}table.data td:nth-child(4){width:9%}table.data td:nth-child(5),table.data td:nth-child(6){width:11%}table.data td:last-child{font-size:5.7px}.buy{color:#087f78}.sell{color:#c2415d}
</style>
<style>
@page{margin:16px 20px;background:#071426}
body{background:#071426;color:#e7f0f7;font-size:7.2px;line-height:1.18}
h1{color:#f8fbff;font-size:16px;letter-spacing:.1px}
h2,.depot-card h2,.chart-card h2{color:#62e3f0;font-size:9px;letter-spacing:.7px}
.muted{color:#91a8bb}
.notice{border:1px solid #e7b84b;background:#241f17;color:#f4cf75}
.depot-card,.chart-card{height:150px;border:1px solid #1b8394;background:#0d2235;color:#e7f0f7;border-radius:8px;box-shadow:0 0 0 1px rgba(34, 211, 238,.08) inset}
.depot-row{border-bottom:1px solid rgba(119,174,195,.2)}.depot-row span{color:#91a8bb}.depot-row b{color:#f4f8fb}
.strategy{border-color:#2aa6b7;background:#123b50;color:#67e4ef;border-radius:4px}
.card{background:#102a40;border:1px solid #24526b;color:#e7f0f7;border-radius:6px}.card b{color:#91a8bb}.value{color:#f8fbff}
table.data{background:#0b1d30}table.data th{background:#123c56;color:#68e4ef}table.data td{border-bottom:1px solid rgba(109,185,207,.2);color:#dbe8f0}table.data tbody tr:nth-child(even) td{background:#10283d}
.buy{color:#35d8a5}.sell{color:#ff8091}
.chart-labels,.chart-dates{display:flex;justify-content:space-between;color:#91a8bb;font-size:6px}.sparkline{height:108px;display:flex;align-items:flex-end;gap:1px;padding:6px 7px 3px;margin-top:2px;border:1px solid rgba(98,227,240,.28);border-radius:7px;background:repeating-linear-gradient(to top,transparent 0,transparent 24px,rgba(98,227,240,.12) 25px),#0a1d30}.sparkbar{display:block;flex:1;min-width:1px;border-radius:2px 2px 0 0;background:linear-gradient(to top,#0ea5a8,#67e8f9);opacity:.92}.chart-dates{margin-top:2px}
/* Dompdf renders the report independently from the application theme. Keep
   the page dark and render the equity curve without flex/SVG dependencies. */
html,body{background:#071426 !important;color:#e7f0f7 !important}
@page{background:#071426}
.sparkline{display:block;padding:6px 7px 3px;margin-top:2px;border:1px solid rgba(98,227,240,.28);border-radius:7px;background:#0a1d30}
.sparkline-table{width:100%;height:108px;border-collapse:collapse;table-layout:fixed;background:repeating-linear-gradient(to top,transparent 0,transparent 24px,rgba(98,227,240,.12) 25px)}
.sparkline-table td{height:108px;padding:0 1px;vertical-align:bottom;border:0}
.sparkbar{display:block;min-height:4px;background:#f59e0b;border-radius:2px 2px 0 0;box-shadow:0 0 4px rgba(245,158,11,.35)}
.report-header{padding:12px 16px;border:1px solid rgba(34, 211, 238,.48);border-radius:9px;background:linear-gradient(135deg,#0d2235 0%,#101d32 60%,#102b3b 100%);box-shadow:0 0 0 1px rgba(34,211,238,.08) inset}
.report-header .brand{color:#62e3f0;font-size:9px;font-weight:900;letter-spacing:1.2px;text-transform:uppercase}
.report-header h1{margin-top:4px;color:#f8fbff;font-size:17px;font-weight:900}
.report-header .muted{margin-top:3px;color:#9fb1c4}
.report-header .notice{margin:8px 0 0}
.equity-chart{width:100%;height:108px;border:1px solid rgba(98,227,240,.28);border-radius:7px;background:transparent}
.equity-chart .equity-area{fill:url(#equity-fade);opacity:.22}
.equity-chart .equity-line{fill:none;stroke:#fff;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 0 3px rgba(255,255,255,.58))}
.sparkline-table{display:none}
/* PDF layout: white document canvas with dark dashboard cards. */
@page{background:#ffffff}
html,body{background:#ffffff !important;color:#172033 !important}
.report-header{background:linear-gradient(135deg,#071426 0%,#0d2235 70%,#102b3b 100%);border-color:#167c91}
.depot-card,.chart-card{background:#0d2235 !important;border-color:#167c91 !important;color:#e7f0f7 !important}
.depot-card h2,.chart-card h2{color:#62e3f0 !important}
.depot-row{border-bottom-color:rgba(119,174,195,.25)}.depot-row span{color:#9fb1c4}.depot-row b{color:#f8fbff}
.card{background:#102a40 !important;border-color:#245d78 !important;color:#e7f0f7 !important}.card b{color:#9fb1c4 !important}.value{color:#f8fbff !important}
table.data{background:#0d2235 !important;border:1px solid #245d78}
table.data th{background:#123c56 !important;color:#68e4ef !important}
table.data td{background:#ffffff !important;color:#172033 !important;border-bottom:1px solid #d9e3ea !important}
table.data tbody tr:nth-child(even) td{background:#f3f7fa !important}
.sparkline-table{display:table !important;background:repeating-linear-gradient(to top,transparent 0,transparent 24px,rgba(98,227,240,.12) 25px)}
.sparkbar{background:linear-gradient(to top,#38bdf8,#ffffff) !important;box-shadow:0 0 4px rgba(56,189,248,.4)}
.chart-labels,.chart-dates{display:block;overflow:hidden;min-height:8px}
.chart-labels span:first-child,.chart-dates span:first-child{float:left}
.chart-labels span:last-child,.chart-dates span:last-child{float:right}
.header-table{width:100%;border-collapse:collapse}.header-table td{vertical-align:top}.header-copy{width:82%}.header-logo-cell{text-align:right;width:18%}.report-logo{width:86px;height:auto;max-height:54px;object-fit:contain}.report-header .brand{font-size:11px;letter-spacing:1.6px}.report-header h1{font-size:23px;line-height:1.05;letter-spacing:.15px}.report-header .muted{font-size:7px}.report-header .notice{font-size:7px;padding:5px 7px}
.index-heading,.stock-heading{margin-top:5px}.compact-data th,.compact-data td{font-size:5.6px;padding:2px 3px}.compact-data td:nth-child(1){width:24%}.compact-data td:nth-child(2){width:28%}
</style></head><body>
@php
    $logoData = $logoData ?? null;
@endphp
<div class="report-header"><table class="header-table"><tr><td class="header-copy"><div class="brand">AKTIENKI · DEPOTSIMULATION</div><h1>{{ $portfolio->name }}</h1><div class="muted">{{ $run->simulation_start_date }} – {{ $run->simulation_end_date }} · erstellt {{ now()->format('d.m.Y H:i') }}</div></td><td class="header-logo-cell">@if($logoData)<img class="report-logo" src="{{ $logoData }}" alt="aktienKI.com">@endif</td></tr></table><div class="notice">Wichtiger Hinweis: Dieser Bericht zeigt ausschließlich eine Simulation auf historischen Daten. Vergangene Ergebnisse sind keine Garantie für zukünftige Entwicklungen und stellen keine Anlageberatung dar.</div></div>
@php
    $reportCurve = collect($summary['equity_curve'] ?? [])->filter(fn ($point) => isset($point['date'], $point['equity']))->values();
    $chartWidth = 560;
    $chartHeight = 82;
    $chartPadX = 24;
    $chartPadY = 10;
    $chartValues = $reportCurve->pluck('equity')->map(fn ($value) => (float) $value);
    $chartMin = $chartValues->isNotEmpty() ? (float) $chartValues->min() : 0.0;
    $chartMax = $chartValues->isNotEmpty() ? (float) $chartValues->max() : 1.0;
    $chartRange = max(1.0, $chartMax - $chartMin);
    $chartPointCount = max(1, $reportCurve->count() - 1);
    $chartPoints = $reportCurve->map(function ($point, $index) use ($chartWidth, $chartHeight, $chartPadX, $chartPadY, $chartMin, $chartRange, $chartPointCount) {
        $x = $chartPadX + (($chartWidth - (2 * $chartPadX)) * ($index / $chartPointCount));
        $y = $chartPadY + (($chartHeight - (2 * $chartPadY)) * (1 - (((float) $point['equity'] - $chartMin) / $chartRange)));
        return round($x, 2).','.round($y, 2);
    })->join(' ');
    $areaPoints = $chartPoints !== '' ? $chartPadX.','.($chartHeight - $chartPadY).' '.$chartPoints.' '.($chartWidth - $chartPadX).','.($chartHeight - $chartPadY) : '';
@endphp
<table class="overview"><tr><td width="31%" class="depot-card"><h2>Depotdaten</h2>
<div class="depot-row"><span>Depot</span><b>{{ $portfolio->name }}</b></div><div class="depot-row"><span>Startkapital</span><b>{{ number_format((float)$run->initial_capital,2,',','.') }} {{ $portfolio->currency }}</b></div><div class="depot-row"><span>Endkapital</span><b>{{ number_format((float)$run->final_capital,2,',','.') }} {{ $portfolio->currency }}</b></div><div class="depot-row"><span>Performance</span><b>{{ number_format((float)($summary['performance_percent']??0),2,',','.') }} %</b></div><div class="depot-row"><span>Max. Drawdown</span><b>{{ number_format((float)($summary['max_drawdown_percent']??0),2,',','.') }} %</b></div>
<div>@foreach($portfolio->strategies as $strategy)<span class="strategy">{{ $strategy->name }}</span>@endforeach</div></td><td width="69%" class="chart-card"><h2>Historische Depotentwicklung</h2>
@if($reportCurve->isNotEmpty())
@php
    $barPoints = $reportCurve->count() > 48 ? $reportCurve->filter(fn ($point, $index) => $index % max(1, (int) floor($reportCurve->count() / 48)) === 0)->values() : $reportCurve;
    $barMin = max(0.0, $chartMin);
    $barRange = max(1.0, $chartMax - $barMin);
@endphp
<div class="chart-labels"><span>{{ number_format($chartMax,0,',','.') }} {{ $portfolio->currency }}</span><span>{{ number_format($chartMin,0,',','.') }} {{ $portfolio->currency }}</span></div>
<svg class="equity-chart" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="none" role="img" aria-label="Historische Depotentwicklung"><defs><linearGradient id="equity-fade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff" stop-opacity=".34"/><stop offset="1" stop-color="#38bdf8" stop-opacity="0"/></linearGradient></defs><polygon class="equity-area" points="{{ $areaPoints }}"/><polyline class="equity-line" points="{{ $chartPoints }}"/></svg>
<table class="sparkline-table" role="img" aria-label="Historische Depotentwicklung"><tr>
@foreach($barPoints as $point)
@php $barHeight = max(4, min(96, (((float) $point['equity'] - $barMin) / $barRange) * 96)); @endphp
<td><span class="sparkbar" style="height:{{ number_format($barHeight,2,'.','') }}px" title="{{ $point['date'] }} · {{ number_format((float) $point['equity'],2,',','.') }} {{ $portfolio->currency }}"></span></td>
@endforeach
</tr></table><div class="chart-dates"><span>{{ $reportCurve->first()['date'] }}</span><span>{{ $reportCurve->last()['date'] }}</span></div>
@else<div class="muted">Keine Entwicklungskurve vorhanden.</div>@endif
</td></tr></table>
<table class="grid"><tr>
@foreach([['Startkapital',$run->initial_capital,' '.$portfolio->currency],['Endkapital',$run->final_capital,' '.$portfolio->currency],['Performance',$summary['performance_percent'] ?? 0,' %'],['Trades',$run->trades_count,''],['Trefferquote',$summary['hit_rate_percent'] ?? 0,' %'],['Profitfaktor',\App\Support\ProfitFactor::cap($summary['profit_factor'] ?? null) ?? '—',''],['Max. Drawdown',$summary['max_drawdown_percent'] ?? 0,' %'],['Kosten',$summary['total_costs'] ?? 0,' '.$portfolio->currency]] as [$label,$value,$suffix])
<td width="12.5%"><div class="card"><b>{{ $label }}</b><div class="value">{{ is_numeric($value)?number_format((float)$value,$label==='Trades'?0:2,',','.'):$value }}{{ $suffix }}</div></div></td>
@endforeach
</tr></table>
<h2>Rotation und Sektorverteilung</h2>
<table class="grid"><tr>
<td width="25%"><div class="card"><b>Sektorrotation</b><div class="value">{{ $rotation['sector_enabled'] ? 'AKTIV' : 'AUS' }}</div></div></td>
<td width="25%"><div class="card"><b>Indexrotation</b><div class="value">{{ $rotation['index_enabled'] ? 'AKTIV' : 'AUS' }}</div></div></td>
<td width="50%"><div class="card"><b>Rotationsstrategien</b><div class="value">{{ collect($rotation['strategies'])->pluck('name')->filter()->join(', ') ?: '—' }}</div></div></td>
</tr></table>
@if(!empty($rotation['sector_trade_counts']))
<table class="data"><thead><tr><th>Sektor</th><th>Käufe</th><th>Anteil</th><th>Ø KI-Score beim Einstieg</th><th>Einordnung</th></tr></thead><tbody>
@foreach($rotation['sector_score_rows'] ?: collect($rotation['sector_trade_counts'])->map(fn ($count, $sector): array => ['sector' => $sector, 'trades' => $count, 'average_score' => null])->values()->all() as $row)
@php $share = (float) $rotation['sector_trade_total'] > 0 ? ((float) $row['trades'] / (float) $rotation['sector_trade_total']) * 100 : 0; @endphp
<tr><td>{{ $row['sector'] }}</td><td>{{ $row['trades'] }}</td><td>{{ number_format($share, 1, ',', '.') }} %</td><td>{{ $row['average_score'] === null ? '—' : number_format((float) $row['average_score'], 2, ',', '.') }}</td><td>{{ $loop->first && $rotation['sector_enabled'] ? 'höchster Ø-Score' : 'beobachtet' }}</td></tr>
@endforeach
</tbody></table>
<div class="muted" style="margin-top:3px">Die Rotation bevorzugt bei aktivierter Sektorrotation Signale aus Sektoren mit höherem aggregiertem KI-Score. Der Ø-Score stammt aus den jeweiligen historischen Einstiegssignalen. Die Tabelle zeigt die tatsächlich verbuchten Käufe dieses Laufs; sie ist kein kausaler Vergleich mit einer deaktivierten Rotation.</div>
@endif
@if(!empty($rotation['index_stats']))
<h2 class="index-heading">Beteiligte Indizes</h2><table class="data compact-data"><thead><tr><th>Index</th><th>Bezeichnung</th><th>Aktien</th><th>Buchungen</th></tr></thead><tbody>
@foreach($rotation['index_stats'] as $index)<tr><td>{{ $index['symbol'] }}</td><td>{{ $index['name'] }}</td><td>{{ $index['stocks'] }}</td><td>{{ $index['trades'] }}</td></tr>@endforeach
</tbody></table>
@endif
@if(!empty($rotation['stock_rows']))
<h2 class="stock-heading">Beteiligte Aktien</h2><table class="data compact-data"><thead><tr><th>Symbol</th><th>Unternehmen</th><th>Käufe</th><th>Verkäufe</th><th>Gewinn / Verlust</th></tr></thead><tbody>
@foreach($rotation['stock_rows'] as $stock)<tr><td>{{ $stock['symbol'] }}</td><td>{{ $stock['name'] }}</td><td>{{ $stock['buys'] }}</td><td>{{ $stock['sells'] }}</td><td class="{{ $stock['pnl'] >= 0 ? 'buy' : 'sell' }}">{{ number_format($stock['pnl'],2,',','.') }} {{ $portfolio->currency }}</td></tr>@endforeach
</tbody></table>
@endif
<h2>Transaktionen</h2><table class="data compact-data"><thead><tr><th>Datum</th><th>Aktion</th><th>Aktie</th><th>Stück</th><th>Kurs</th><th>KI-Score</th><th>Konfidenz</th><th>Risiko</th><th>Gewinn / Verlust</th><th>Gebühr</th><th>Strategien</th></tr></thead><tbody>
@foreach($transactions as $transaction)@php $stat = $transactionRows[$transaction->id] ?? []; @endphp<tr><td>{{ $transaction->transaction_date?->format('d.m.Y') }}</td><td class="{{ $transaction->type==='sell'?'sell':'buy' }}">{{ strtoupper($transaction->type) }}</td><td>{{ $transaction->instrument?->symbol }}</td><td>{{ number_format($transaction->quantity,0,',','.') }}</td><td>{{ number_format($transaction->price,2,',','.') }}</td><td>{{ isset($stat['ki_score']) && is_numeric($stat['ki_score']) ? number_format((float)$stat['ki_score'],1,',','.') : '—' }}</td><td>{{ isset($stat['confidence']) && is_numeric($stat['confidence']) ? number_format((float)$stat['confidence'],1,',','.') : '—' }}{{ isset($stat['confidence']) && is_numeric($stat['confidence']) ? ' %' : '' }}</td><td>{{ isset($stat['risk']) && is_numeric($stat['risk']) ? number_format((float)$stat['risk'],1,',','.') : '—' }}</td><td class="{{ isset($stat['pnl']) && (float)$stat['pnl'] >= 0 ? 'buy' : 'sell' }}">{{ isset($stat['pnl']) && is_numeric($stat['pnl']) ? number_format((float)$stat['pnl'],2,',','.') . ' ' . $portfolio->currency : '—' }}</td><td>{{ number_format($transaction->fees,2,',','.') }}</td><td>{{ collect(data_get($transaction->meta,'strategy_ids',[data_get($transaction->meta,'strategy_id')]))->filter()->join(', ') }}</td></tr>@endforeach
</tbody></table></body></html>
