<!doctype html>
<html lang="de"><head><meta charset="utf-8"><style>
@page{margin:20px 28px 24px}*{box-sizing:border-box}body{margin:0;color:#18263a;font-family:DejaVu Sans,sans-serif;font-size:8px;line-height:1.3}h1,h2,h3,p{margin:0}.header{position:relative;min-height:86px;padding:12px 16px;background:#101e33;color:#fff;border-radius:9px;margin-bottom:10px}.logo{float:left;width:68px;height:30px;object-fit:contain;margin:2px 10px 7px 0}.brand{color:#22d3ee;font-size:8px;font-weight:bold;letter-spacing:1.4px}.title{max-width:390px;font-size:17px;margin-top:3px}.symbol{display:inline-block;margin-left:6px;padding:2px 6px;border:1px solid #22d3ee;border-radius:4px;color:#67e8f9;font-size:8px}.meta{max-width:390px;margin-top:4px;color:#bdcadb}.header-donuts{position:absolute;right:12px;top:18px;width:auto!important;border-collapse:collapse}.header-donuts td{padding:0 1px;border:0;background:transparent}.section{margin-top:9px;page-break-inside:avoid}.section-title{margin-bottom:4px;color:#0e7490;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}.grid{width:100%;border-spacing:5px 4px;margin:0 -5px}.card{border:1px solid #d8e1ea;border-radius:7px;padding:7px;background:#f8fafc;vertical-align:top}.label{color:#66758a;text-transform:uppercase;font-size:6px;letter-spacing:.5px}.value{font-size:12px;font-weight:bold;margin-top:2px}.positive{color:#087f5b}.negative{color:#c92a2a}.amber{color:#d97706}table.data{width:100%;border-collapse:collapse;margin-top:4px}table.data th{padding:4px 5px;text-align:left;background:#dce7ef;color:#314258;text-transform:uppercase;font-size:6.5px;letter-spacing:.5px}table.data td{padding:3.5px 5px;border-bottom:1px solid #dce4ec}table.data tr:nth-child(even) td{background:#f4f7fa}table.data tr.group td{border-top:1px solid #f59e0b}.right{text-align:right!important}.badge{display:inline-block;padding:2px 6px;border-radius:4px;background:#ecfeff;color:#0e7490;font-weight:bold}.chart-box{padding:5px 7px;border:1px solid #d8e1ea;border-radius:7px;background:#f8fafc}.chart-box img{display:block;width:100%;height:170px}.note{margin-top:7px;padding:5px 7px;background:#fff7df;border-left:3px solid #d97706;color:#5d4a22;font-size:6.5px}.columns{width:100%;border-spacing:6px;margin:0 -6px}.columns td{width:50%;vertical-align:top}.list{margin:4px 0 0;padding-left:13px}.list li{margin-bottom:3px}.footer{position:fixed;left:0;right:0;bottom:-18px;color:#7a8797;font-size:7px;text-align:center}.page:after{content:counter(page)}
</style></head><body>
@php
$score = \App\Support\AiScore::toTen($prediction?->prediction_score);
$current = is_numeric($prediction?->current_price) ? (float)$prediction->current_price : null;
$fmtPct = fn($v) => is_numeric($v) ? number_format((abs((float)$v)<=1?(float)$v*100:(float)$v),2,',','.').' %' : '—';
$fmtNum = fn($v,$d=2) => is_numeric($v) ? number_format((float)$v,$d,',','.') : '—';
$fmtLarge = function($v) use ($fmtNum) { if(!is_numeric($v)) return $v?:'—'; $n=(float)$v; if(abs($n)>=1e9)return $fmtNum($n/1e9).' Mrd.'; if(abs($n)>=1e6)return $fmtNum($n/1e6).' Mio.'; return $fmtNum($n); };
$json = fn($v) => is_array($v) ? $v : (is_string($v) ? (json_decode($v,true) ?: []) : []);
$reportRisk = \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor, $trainedModel?->max_drawdown);
@endphp
<x-reports.pdf-header
    :logo-data="$logoData"
    :eyebrow="'aktienKI.com · Aktienbericht'"
    :title="$instrument->name"
    :symbol="$instrument->symbol"
    :meta="($instrument->sector ?: '—').' · '.($instrument->industry ?: '—').' · '.($instrument->country ?: '—').' · erstellt '.now()->format('d.m.Y H:i').' Uhr'"
    :donuts="$reportDonuts"
/>

<div class="section"><h2 class="section-title">Kurzüberblick</h2><table class="grid"><tr>
<td class="card"><div class="label">Signal</div><div class="value">{{ strtoupper(data_get($prediction, 'personalized_signal') ?: data_get($prediction, 'signal') ?: '—') }}</div></td>
<td class="card"><div class="label">Aktueller Kurs</div><div class="value">{{ $current!==null?$fmtNum($current).' '.($instrument->currency?:''):'—' }}</div></td>
<td class="card"><div class="label">Konfidenz</div><div class="value">{{ $fmtPct($prediction?->confidence) }}</div></td>
<td class="card"><div class="label">Risiko</div><div class="value">{{ $reportRisk !== null ? number_format($reportRisk, 0, ',', '.').' %' : '—' }}</div></td>
</tr></table></div>

<div class="section"><h2 class="section-title">Kursverlauf und Prognosepfad</h2><div class="chart-box">@if($chart)<img src="{{ $chart }}" alt="Kurschart mit Prognosehorizonten">@else<p>Keine Kursreihe verfügbar.</p>@endif</div></div>

<div class="section"><h2 class="section-title">Prognosehorizonte</h2><table class="data"><thead><tr><th>Horizont</th><th class="right">Kursziel</th><th class="right">Erwartete Rendite</th><th class="right">Richtung</th><th class="right">Abstand zum vorigen Ziel</th></tr></thead><tbody>
@php $previousTarget=$current; @endphp
@foreach([5,10,15,20] as $days) @php $target=$horizonTargets[$days]??null;$ret=$target!==null&&$current?($target/$current-1)*100:null;$step=$target!==null&&$previousTarget?($target/$previousTarget-1)*100:null; @endphp
<tr><td><strong>{{ $days }} Handelstage</strong></td><td class="right">{{ $target!==null?$fmtNum($target).' '.($instrument->currency?:''):'—' }}</td><td class="right {{ $ret!==null&&$ret>=0?'positive':'negative' }}">{{ $ret!==null?(($ret>0?'+':'').$fmtNum($ret).' %'):'—' }}</td><td class="right">{{ $ret===null?'—':($step>=0?'Steigend':'Fallend') }}</td><td class="right {{ $step!==null&&$step>=0?'positive':'negative' }}">{{ $step!==null?(($step>0?'+':'').$fmtNum($step).' %'):'—' }}</td></tr>@php if($target!==null)$previousTarget=$target; @endphp @endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">Modell und historische Qualität</h2><table class="data"><tbody>
@foreach([
['Modell',$trainedModel?->model_alias],['Modellqualität',$fmtPct($trainedModel?->quality_score)],['Hit-Rate',$fmtPct($trainedModel?->hit_rate)],['Profit-Faktor',$fmtNum($trainedModel?->profit_factor)],['Max. Drawdown',$fmtPct($trainedModel?->max_drawdown)],['Letztes Training',$trainedModel?->trained_at?\Illuminate\Support\Carbon::parse($trainedModel->trained_at)->format('d.m.Y H:i'):null],['Quality Gate',$prediction?->quality_gate_passed===null?'—':($prediction->quality_gate_passed?'Bestanden':'Nicht bestanden')]
] as $i=>$row)<tr class="{{ $i===0?'group':'' }}"><td>{{ $row[0] }}</td><td class="right"><strong>{{ $row[1]?:'—' }}</strong></td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">Fundamentaldaten</h2><table class="data"><thead><tr><th>Kennzahl</th><th class="right">Wert</th><th>Kennzahl</th><th class="right">Wert</th></tr></thead><tbody>
@php $fundRows=[['Marktkapitalisierung',$fundamentals['marketCap']??null,'KGV',$fundamentals['trailingPE']??null],['Forward-KGV',$fundamentals['forwardPE']??null,'Kurs/Buchwert',$fundamentals['priceToBook']??null],['Dividendenrendite',$fmtPct($fundamentals['dividendYield']??null),'Nettomarge',$fmtPct($fundamentals['profitMargins']??null)],['Operative Marge',$fmtPct($fundamentals['operatingMargins']??null),'Eigenkapitalrendite',$fmtPct($fundamentals['returnOnEquity']??null)],['Umsatz',$fundamentals['revenue']??null,'Umsatzwachstum',$fmtPct($fundamentals['revenueGrowth']??null)],['EBITDA',$fundamentals['ebitda']??null,'Liquide Mittel',$fundamentals['totalCash']??null],['Gesamtverschuldung',$fundamentals['totalDebt']??null,'Freier Cashflow',$fundamentals['freeCashflow']??null]]; @endphp
@foreach($fundRows as $row)<tr><td>{{ $row[0] }}</td><td class="right"><strong>{{ $row[1]===null?'—':(is_numeric($row[1])?$fmtLarge($row[1]):$row[1]) }}</strong></td><td>{{ $row[2] }}</td><td class="right"><strong>{{ $row[3]===null?'—':(is_numeric($row[3])?$fmtLarge($row[3]):$row[3]) }}</strong></td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">Technische Indikatorstatistik</h2><table class="data"><thead><tr><th>Indikator</th><th class="right">Aktueller Wert</th><th class="right">20T Steigwahrscheinlichkeit</th><th class="right">Einordnung</th></tr></thead><tbody>
@foreach($indicators as $card)<tr><td>{{ $card['label'] }}</td><td class="right">{{ is_numeric($card['currentValue'])?$fmtNum($card['currentValue']).' '.$card['unit']:'—' }}</td><td class="right">{{ is_numeric($card['currentProbability'])?$fmtNum($card['currentProbability'],1).' %':'—' }}</td><td class="right {{ is_numeric($card['currentProbability'])&&$card['currentProbability']>=50?'positive':'negative' }}">{{ !is_numeric($card['currentProbability'])?'—':($card['currentProbability']>=50?'Positiv':'Negativ') }}</td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">Chartformationen - historische Statistik</h2><table class="data"><thead><tr><th>Formation</th><th>Richtung</th><th class="right">Fälle</th><th class="right">Trefferquote</th><th class="right">Ø 20T</th></tr></thead><tbody>
@foreach($patterns as $pattern)<tr><td>{{ $pattern['name'] }}</td><td>{{ ucfirst($pattern['direction']) }}</td><td class="right">{{ $pattern['samples'] }}</td><td class="right">{{ is_numeric($pattern['hit_rate'])?$fmtNum($pattern['hit_rate'],1).' %':'—' }}</td><td class="right {{ is_numeric($pattern['average_performance'])&&$pattern['average_performance']>=0?'positive':'negative' }}">{{ is_numeric($pattern['average_performance'])?$fmtNum($pattern['average_performance']).' %':'—' }}</td></tr>@endforeach
</tbody></table></div>

@if($assessment)<div class="section"><h2 class="section-title">Chancen, Risiken und Schlüsselfaktoren</h2><table class="columns"><tr>@foreach([['Chancen',$json($assessment->opportunities),'positive'],['Risiken',$json($assessment->risks),'negative']] as $box)<td class="card"><h3 class="{{ $box[2] }}">{{ $box[0] }}</h3><ul class="list">@forelse($box[1] as $item)<li>{{ is_scalar($item)?$item:json_encode($item,JSON_UNESCAPED_UNICODE) }}</li>@empty<li>Keine Daten</li>@endforelse</ul></td>@endforeach</tr></table><div class="card"><h3 class="amber">Schlüsselfaktoren</h3><ul class="list">@forelse($json($assessment->key_factors) as $item)<li>{{ is_scalar($item)?$item:json_encode($item,JSON_UNESCAPED_UNICODE) }}</li>@empty<li>Keine Daten</li>@endforelse</ul></div></div>@endif

<p class="note">Dieser Bericht dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar. Prognosen und historische Auswertungen sind keine Garantie für zukünftige Ergebnisse.</p>
<div class="footer">aktienKI.com · Aktienbericht {{ $instrument->symbol }} <span style="float:right">Seite <span class="page"></span></span></div>
</body></html>
