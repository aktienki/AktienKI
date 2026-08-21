<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
@page{margin:20px 28px 24px}*{box-sizing:border-box}body{margin:0;color:#18263a;font-family:DejaVu Sans,sans-serif;font-size:8px;line-height:1.3}h1,h2,h3,p{margin:0}.header{position:relative;min-height:86px;padding:12px 16px;background:#101e33;color:#fff;border-radius:9px;margin-bottom:10px}.logo{float:left;width:68px;height:30px;object-fit:contain;margin:2px 10px 7px 0}.brand{color:#22d3ee;font-size:8px;font-weight:bold;letter-spacing:1.4px}.title{max-width:390px;font-size:17px;margin-top:3px}.symbol{display:inline-block;margin-left:6px;padding:2px 6px;border:1px solid #22d3ee;border-radius:4px;color:#67e8f9;font-size:8px}.meta{max-width:390px;margin-top:4px;color:#bdcadb}.header-donuts{position:absolute;right:12px;top:18px;width:auto!important;border-collapse:collapse}.header-donuts td{padding:0 1px;border:0;background:transparent}.section{margin-top:9px;page-break-inside:avoid}.section-title{margin-bottom:4px;color:#0e7490;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}.grid{width:100%;border-spacing:5px 4px;margin:0 -5px}.card{border:1px solid #d8e1ea;border-radius:7px;padding:7px;background:#f8fafc;vertical-align:top}.label{color:#66758a;text-transform:uppercase;font-size:6px;letter-spacing:.5px}.value{font-size:12px;font-weight:bold;margin-top:2px}.positive{color:#087f5b}.negative{color:#c92a2a}.amber{color:#d97706}table.data{width:100%;border-collapse:collapse;margin-top:4px}table.data th{padding:4px 5px;text-align:left;background:#dce7ef;color:#314258;text-transform:uppercase;font-size:6.5px;letter-spacing:.5px}table.data td{padding:3.5px 5px;border-bottom:1px solid #dce4ec}table.data tr:nth-child(even) td{background:#f4f7fa}table.data tr.group td{border-top:1px solid #f59e0b}.right{text-align:right!important}.badge{display:inline-block;padding:2px 6px;border-radius:4px;background:#ecfeff;color:#0e7490;font-weight:bold}.chart-box{padding:5px 7px;border:1px solid #d8e1ea;border-radius:7px;background:#f8fafc}.chart-box img{display:block;width:100%;height:170px}.note{margin-top:7px;padding:5px 7px;background:#fff7df;border-left:3px solid #d97706;color:#5d4a22;font-size:6.5px}.columns{width:100%;border-spacing:6px;margin:0 -6px}.columns td{width:50%;vertical-align:top}.list{margin:4px 0 0;padding-left:13px}.list li{margin-bottom:3px}.footer{position:fixed;left:0;right:0;bottom:-18px;color:#7a8797;font-size:7px;text-align:center}.page:after{content:counter(page)}
</style></head><body>
@php
$en = app()->getLocale() === 'en';
$t = fn($de, $english) => $en ? $english : $de;
$score = \App\Support\AiScore::toTen($prediction?->prediction_score);
$current = is_numeric($prediction?->current_price) ? (float)$prediction->current_price : null;
$fmtPct = fn($v) => is_numeric($v) ? number_format((abs((float)$v)<=1?(float)$v*100:(float)$v),2,$en?'.':',',$en?',':'.').' %' : '—';
$fmtNum = fn($v,$d=2) => is_numeric($v) ? number_format((float)$v,$d,$en?'.':',',$en?',':'.') : '—';
$fmtLarge = function($v) use ($fmtNum,$en) { if(!is_numeric($v)) return $v?:'—'; $n=(float)$v; if(abs($n)>=1e9)return $fmtNum($n/1e9).' '.($en?'bn':'Mrd.'); if(abs($n)>=1e6)return $fmtNum($n/1e6).' '.($en?'m':'Mio.'); return $fmtNum($n); };
$json = fn($v) => is_array($v) ? $v : (is_string($v) ? (json_decode($v,true) ?: []) : []);
$reportRisk = \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor, $trainedModel?->max_drawdown);
@endphp
<x-reports.pdf-header
    :logo-data="$logoData"
    :eyebrow="$t('aktienKI.com · Aktienbericht', 'aktienKI.com · Stock Report')"
    :title="$instrument->name"
    :symbol="$instrument->symbol"
    :meta="($instrument->sector ?: '—').' · '.($instrument->industry ?: '—').' · '.($instrument->country ?: '—').' · '.$t('erstellt ', 'created ').now()->format($en ? 'Y-m-d H:i' : 'd.m.Y H:i').' '.$t('Uhr', '')"
    :donuts="$reportDonuts"
/>

<div class="section"><h2 class="section-title">{{ $t('Kurzüberblick','Overview') }}</h2><table class="grid"><tr>
<td class="card"><div class="label">Signal</div><div class="value">{{ strtoupper(data_get($prediction, 'personalized_signal') ?: data_get($prediction, 'signal') ?: '—') }}</div></td>
<td class="card"><div class="label">{{ $t('Aktueller Kurs','Current price') }}</div><div class="value">{{ $current!==null?$fmtNum($current).' '.($instrument->currency?:''):'—' }}</div></td>
<td class="card"><div class="label">{{ $t('Konfidenz','Confidence') }}</div><div class="value">{{ $fmtPct($prediction?->confidence) }}</div></td>
<td class="card"><div class="label">{{ $t('Risiko','Risk') }}</div><div class="value">{{ $reportRisk !== null ? $fmtNum($reportRisk, 0).' %' : '—' }}</div></td>
</tr></table></div>

<div class="section"><h2 class="section-title">{{ $t('Kursverlauf und Prognosepfad','Price history and forecast path') }}</h2><div class="chart-box">@if($chart)<img src="{{ $chart }}" alt="{{ $t('Kurschart mit Prognosehorizonten','Price chart with forecast horizons') }}">@else<p>{{ $t('Keine Kursreihe verfügbar.','No price series available.') }}</p>@endif</div></div>

<div class="section"><h2 class="section-title">{{ $t('Prognosehorizonte','Forecast horizons') }}</h2><table class="data"><thead><tr><th>{{ $t('Horizont','Horizon') }}</th><th class="right">{{ $t('Kursziel','Price target') }}</th><th class="right">{{ $t('Erwartete Rendite','Expected return') }}</th><th class="right">{{ $t('Richtung','Direction') }}</th><th class="right">{{ $t('Abstand zum vorigen Ziel','Change from previous target') }}</th></tr></thead><tbody>
@php $previousTarget=$current; @endphp
@foreach([5,10,15,20] as $days) @php $target=$horizonTargets[$days]??null;$ret=$target!==null&&$current?($target/$current-1)*100:null;$step=$target!==null&&$previousTarget?($target/$previousTarget-1)*100:null; @endphp
<tr><td><strong>{{ $days }} {{ $t('Handelstage','trading days') }}</strong></td><td class="right">{{ $target!==null?$fmtNum($target).' '.($instrument->currency?:''):'—' }}</td><td class="right {{ $ret!==null&&$ret>=0?'positive':'negative' }}">{{ $ret!==null?(($ret>0?'+':'').$fmtNum($ret).' %'):'—' }}</td><td class="right">{{ $ret===null?'—':($step>=0?$t('Steigend','Rising'):$t('Fallend','Falling')) }}</td><td class="right {{ $step!==null&&$step>=0?'positive':'negative' }}">{{ $step!==null?(($step>0?'+':'').$fmtNum($step).' %'):'—' }}</td></tr>@php if($target!==null)$previousTarget=$target; @endphp @endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">{{ $t('Modell und historische Qualität','Model and historical quality') }}</h2><table class="data"><tbody>
@foreach([
[$t('Modell','Model'),$trainedModel?->model_alias],[$t('Modellqualität','Model quality'),$fmtPct($trainedModel?->quality_score)],['Hit Rate',$fmtPct($trainedModel?->hit_rate)],[$t('Profit-Faktor','Profit factor'),$fmtNum(\App\Support\ProfitFactor::cap($trainedModel?->profit_factor))],['Max. Drawdown',$fmtPct($trainedModel?->max_drawdown)],[$t('Letztes Training','Latest training'),$trainedModel?->trained_at?\Illuminate\Support\Carbon::parse($trainedModel->trained_at)->format($en?'Y-m-d H:i':'d.m.Y H:i'):null],['Quality Gate',$prediction?->quality_gate_passed===null?'—':($prediction->quality_gate_passed?$t('Bestanden','Passed'):$t('Nicht bestanden','Failed'))]
] as $i=>$row)<tr class="{{ $i===0?'group':'' }}"><td>{{ $row[0] }}</td><td class="right"><strong>{{ $row[1]?:'—' }}</strong></td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">{{ $t('Fundamentaldaten','Fundamentals') }}</h2><table class="data"><thead><tr><th>{{ $t('Kennzahl','Metric') }}</th><th class="right">{{ $t('Wert','Value') }}</th><th>{{ $t('Kennzahl','Metric') }}</th><th class="right">{{ $t('Wert','Value') }}</th></tr></thead><tbody>
@php $fundRows=[[$t('Marktkapitalisierung','Market capitalization'),$fundamentals['marketCap']??null,$t('KGV','P/E ratio'),$fundamentals['trailingPE']??null],[$t('Forward-KGV','Forward P/E'),$fundamentals['forwardPE']??null,$t('Kurs/Buchwert','Price/book'),$fundamentals['priceToBook']??null],[$t('Dividendenrendite','Dividend yield'),$fmtPct($fundamentals['dividendYield']??null),$t('Nettomarge','Net margin'),$fmtPct($fundamentals['profitMargins']??null)],[$t('Operative Marge','Operating margin'),$fmtPct($fundamentals['operatingMargins']??null),$t('Eigenkapitalrendite','Return on equity'),$fmtPct($fundamentals['returnOnEquity']??null)],[$t('Umsatz','Revenue'),$fundamentals['revenue']??null,$t('Umsatzwachstum','Revenue growth'),$fmtPct($fundamentals['revenueGrowth']??null)],['EBITDA',$fundamentals['ebitda']??null,$t('Liquide Mittel','Cash'),$fundamentals['totalCash']??null],[$t('Gesamtverschuldung','Total debt'),$fundamentals['totalDebt']??null,$t('Freier Cashflow','Free cash flow'),$fundamentals['freeCashflow']??null]]; @endphp
@foreach($fundRows as $row)<tr><td>{{ $row[0] }}</td><td class="right"><strong>{{ $row[1]===null?'—':(is_numeric($row[1])?$fmtLarge($row[1]):$row[1]) }}</strong></td><td>{{ $row[2] }}</td><td class="right"><strong>{{ $row[3]===null?'—':(is_numeric($row[3])?$fmtLarge($row[3]):$row[3]) }}</strong></td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">{{ $t('Technische Indikatorstatistik','Technical indicator statistics') }}</h2><table class="data"><thead><tr><th>{{ $t('Indikator','Indicator') }}</th><th class="right">{{ $t('Aktueller Wert','Current value') }}</th><th class="right">{{ $t('20T Steigwahrscheinlichkeit','20D rise probability') }}</th><th class="right">{{ $t('Einordnung','Assessment') }}</th></tr></thead><tbody>
@foreach($indicators as $card)<tr><td>{{ $card['label'] }}</td><td class="right">{{ is_numeric($card['currentValue'])?$fmtNum($card['currentValue']).' '.$card['unit']:'—' }}</td><td class="right">{{ is_numeric($card['currentProbability'])?$fmtNum($card['currentProbability'],1).' %':'—' }}</td><td class="right {{ is_numeric($card['currentProbability'])&&$card['currentProbability']>=50?'positive':'negative' }}">{{ !is_numeric($card['currentProbability'])?'—':($card['currentProbability']>=50?$t('Positiv','Positive'):$t('Negativ','Negative')) }}</td></tr>@endforeach
</tbody></table></div>

<div class="section"><h2 class="section-title">{{ $t('Chartformationen - historische Statistik','Chart patterns - historical statistics') }}</h2><table class="data"><thead><tr><th>{{ $t('Formation','Pattern') }}</th><th>{{ $t('Richtung','Direction') }}</th><th class="right">{{ $t('Fälle','Samples') }}</th><th class="right">{{ $t('Trefferquote','Hit rate') }}</th><th class="right">Ø 20D</th></tr></thead><tbody>
@foreach($patterns as $pattern)<tr><td>{{ $pattern['name'] }}</td><td>{{ ucfirst($pattern['direction']) }}</td><td class="right">{{ $pattern['samples'] }}</td><td class="right">{{ is_numeric($pattern['hit_rate'])?$fmtNum($pattern['hit_rate'],1).' %':'—' }}</td><td class="right {{ is_numeric($pattern['average_performance'])&&$pattern['average_performance']>=0?'positive':'negative' }}">{{ is_numeric($pattern['average_performance'])?$fmtNum($pattern['average_performance']).' %':'—' }}</td></tr>@endforeach
</tbody></table></div>

@if($assessment && !$en)<div class="section"><h2 class="section-title">Chancen, Risiken und Schlüsselfaktoren</h2><table class="columns"><tr>@foreach([['Chancen',$json($assessment->opportunities),'positive'],['Risiken',$json($assessment->risks),'negative']] as $box)<td class="card"><h3 class="{{ $box[2] }}">{{ $box[0] }}</h3><ul class="list">@forelse($box[1] as $item)<li>{{ is_scalar($item)?$item:json_encode($item,JSON_UNESCAPED_UNICODE) }}</li>@empty<li>Keine Daten</li>@endforelse</ul></td>@endforeach</tr></table><div class="card"><h3 class="amber">Schlüsselfaktoren</h3><ul class="list">@forelse($json($assessment->key_factors) as $item)<li>{{ is_scalar($item)?$item:json_encode($item,JSON_UNESCAPED_UNICODE) }}</li>@empty<li>Keine Daten</li>@endforelse</ul></div></div>@endif

<p class="note">{{ $t('Dieser Bericht dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar. Prognosen und historische Auswertungen sind keine Garantie für zukünftige Ergebnisse.','This report is for information purposes only and does not constitute investment advice. Forecasts and historical analyses do not guarantee future results.') }}</p>
<div class="footer">aktienKI.com · {{ $t('Aktienbericht','Stock Report') }} {{ $instrument->symbol }} <span style="float:right">{{ $t('Seite','Page') }} <span class="page"></span></span></div>
</body></html>
