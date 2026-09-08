<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
@page{margin:22px 28px 28px}*{box-sizing:border-box}body{margin:0;color:#172033;font:9px/1.42 DejaVu Sans,sans-serif}.header{position:relative;min-height:82px;padding:14px 17px;border:1px solid #263d55;border-left:4px solid #22d3ee;border-radius:10px;background:#0b192b;color:#edf6f7}.logo{float:left;width:124px;max-height:50px;object-fit:contain;margin-right:15px}.header-side{position:absolute;right:17px;top:18px;text-align:right}.header-side strong{display:block;color:#64e5f2;font-size:8px;letter-spacing:1.5px}.header-side span{display:block;margin-top:4px;color:#a9bac9;font-size:8px}.brand,.section-title{color:#087989;font-weight:bold;text-transform:uppercase;letter-spacing:1px}.header .brand{color:#64e5f2}.title{max-width:390px;font-size:19px;font-weight:bold;margin:3px 0}.meta{max-width:430px;color:#a9bac9}.header-donuts{display:none}.section{margin-top:12px;page-break-inside:avoid}.section-title{margin-bottom:5px;font-size:9px}.grid{width:100%;border-spacing:5px;margin:0 -5px}.card{width:25%;padding:8px;border:1px solid #d8e1ea;border-radius:7px;background:#f8fafc;vertical-align:top}.label{color:#64748b;font-size:7px;text-transform:uppercase}.value{margin-top:3px;font-size:13px;font-weight:bold}.positive{color:#087f5b}.negative{color:#c92a2a}.chart{padding:6px;border:1px solid #d8e1ea;border-radius:8px;background:#122034}.chart img{display:block;width:100%;height:210px;object-fit:contain}table.data{width:100%;border-collapse:collapse}table.data th{padding:6px;background:#eef4f6;color:#314258;font-size:7px;text-align:left;text-transform:uppercase}table.data td{padding:6px;border-bottom:1px solid #dce4ec}table.data tr:nth-child(even) td{background:#f8fafc}.right{text-align:right!important}.columns{width:100%;border-spacing:7px;margin:0 -7px}.columns td{width:50%;vertical-align:top}.list{margin:4px 0 0;padding-left:13px}.list li{margin-bottom:4px}.matrix-layout{width:100%;border-spacing:5px;margin:0 -5px}.matrix-layout>tbody>tr>td{width:33.333%;vertical-align:top}.matrix-card{padding:6px;border:1px solid #d8e1ea;border-radius:7px}.matrix-title{margin-bottom:4px;color:#314258;font-size:7px;font-weight:bold}.matrix{width:100%;border-collapse:separate;border-spacing:2px}.matrix td{height:22px;padding:2px;text-align:center;border-radius:3px;font-size:6px;font-weight:bold}.matrix small{display:block;color:#64748b;font-size:5px;font-weight:normal}.pattern-chart{width:90px;height:31px;object-fit:contain}.note{margin-top:13px;padding:7px;border-left:3px solid #d97706;background:#fff7df;color:#5d4a22;font-size:7px}.footer{position:fixed;bottom:-18px;left:0;right:0;color:#7a8797;font-size:7px;text-align:center}.page:after{content:counter(page)}
.header-copy{margin-left:139px;padding-right:205px}.header-copy .title{max-width:none;font-size:16px}.header-copy .meta{max-width:none}
</style></head><body>
@php
$en=app()->getLocale()==='en'; $t=fn($de,$eng)=>$en?$eng:$de;
$pct=fn($v,$d=1)=>is_numeric($v)?number_format((float)$v,$d,$en?'.':',',$en?',':'.').' %':'—';
$num=fn($v,$d=2)=>is_numeric($v)?number_format((float)$v,$d,$en?'.':',',$en?',':'.'):'—';
$large=function($v)use($num,$en){if(!is_numeric($v))return '—';$v=(float)$v;if(abs($v)>=1e9)return $num($v/1e9).' '.($en?'bn':'Mrd.');if(abs($v)>=1e6)return $num($v/1e6).' '.($en?'m':'Mio.');return $num($v);};
$prediction=$latestPrediction; $current=is_numeric($prediction?->current_price??null)?(float)$prediction->current_price:null;
@endphp
<x-reports.pdf-header
    :logo-data="$logoData"
    :eyebrow="$t('aktienKI.com · Aktienbericht', 'aktienKI.com · Stock Report')"
    :title="$stock->name"
    :symbol="null"
    :meta="$stock->symbol.' · '.($stock->sector_code ?: '—').' · '.($stock->industry ?: '—').' · '.($stock->country_code ?: '—').' · '.now()->format($en?'Y-m-d H:i':'d.m.Y H:i')"
/>

<div class="section"><div class="section-title">{{ $t('Kurzüberblick','Overview') }}</div><table class="grid"><tr>
<td class="card"><div class="label">Signal</div><div class="value">{{ $stock->recommended_signal ?: '—' }}</div></td>
<td class="card"><div class="label">{{ $t('Aktueller Kurs','Current price') }}</div><div class="value">{{ $current!==null?$num($current).' '.$stock->currency:'—' }}</div></td>
<td class="card"><div class="label">{{ $t('Qualitätsklasse','Quality class') }}</div><div class="value">{{ strtoupper($stock->quality_class ?: '—') }}</div></td>
<td class="card"><div class="label">{{ $t('Risiko','Risk') }}</div><div class="value">{{ is_numeric($stock->risk_status)?$stock->risk_status.'/10':($stock->risk_status?:'—') }}</div></td>
</tr></table></div>

<div class="section"><div class="section-title">{{ $t('Kursverlauf und Prognoseziele','Price history and forecast targets') }}</div><div class="chart"><img src="{{ $chartData }}" alt="{{ $t('Kurschart mit 10T-, 20T- und 40T-Prognose','Price chart with 10D, 20D and 40D forecast') }}"></div></div>

<div class="section"><div class="section-title">{{ $t('Prognosen und aktive Modelle','Forecasts and active models') }}</div><table class="data"><thead><tr><th>{{ $t('Horizont','Horizon') }}</th><th>{{ $t('Modell','Model') }}</th><th>Signal</th><th class="right">{{ $t('Erwartete Rendite','Expected return') }}</th><th class="right">{{ $t('Wahrscheinlichkeit','Probability') }}</th><th class="right">{{ $t('Kursziel','Price target') }}</th></tr></thead><tbody>
@forelse($horizons as $days=>$horizon)
@php $p = $horizon->prediction; @endphp
<tr><td><strong>{{ $days }}T</strong></td><td>{{ $horizon->variant_label }}@if($horizon->champion) · {{ $horizon->champion }}@endif</td><td>{{ $p?->signal ?: '—' }}</td><td class="right {{ is_numeric($p?->expected_return_percent)&&$p->expected_return_percent>=0?'positive':'negative' }}">{{ is_numeric($p?->expected_return_percent)?(($p->expected_return_percent>0?'+':'').$pct($p->expected_return_percent)):'—' }}</td><td class="right">{{ $pct($p?->confidence_percent) }}</td><td class="right">{{ is_numeric($p?->target_price)?$num($p->target_price).' '.$stock->currency:'—' }}</td></tr>
@empty
<tr><td colspan="6">{{ $t('Keine aktive Remote-Prognose vorhanden.','No active remote forecast available.') }}</td></tr>
@endforelse
</tbody></table></div>

<div class="section"><div class="section-title">{{ $t('Historische Modellqualität','Historical model quality') }}</div><table class="data"><thead><tr><th>{{ $t('Horizont','Horizon') }}</th><th class="right">Trades</th><th class="right">Hit Rate</th><th class="right">Profit Factor</th><th class="right">Ø/Trade</th><th class="right">Max. Drawdown</th></tr></thead><tbody>
@foreach($horizons as $days=>$horizon)
@php $m = $horizon->metrics; @endphp
<tr><td><strong>{{ $days }}T</strong></td><td class="right">{{ is_numeric($m->trades??null)?number_format($m->trades,0,$en?'.':',',$en?',':'.'):'—' }}</td><td class="right">{{ $pct($m->hit_rate??null) }}</td><td class="right">{{ $num($m->profit_factor??null) }}</td><td class="right">{{ $pct($m->average_return??null,2) }}</td><td class="right">{{ $pct($m->max_drawdown??null) }}</td></tr>
@endforeach
</tbody></table></div>

<div class="section"><div class="section-title">{{ $t('Technische Indikatorstatistik','Technical indicator statistics') }}</div><table class="data"><thead><tr><th>{{ $t('Indikator','Indicator') }}</th><th class="right">{{ $t('Aktueller Wert','Current value') }}</th><th class="right">{{ $t('20T Steigwahrscheinlichkeit','20D rise probability') }}</th><th class="right">{{ $t('Einordnung','Assessment') }}</th></tr></thead><tbody>
@forelse($indicators as $card)<tr><td><strong>{{ $card['label'] }}</strong></td><td class="right">{{ is_numeric($card['currentValue'])?$num($card['currentValue']).' '.$card['unit']:'—' }}</td><td class="right">{{ is_numeric($card['currentProbability'])?$num($card['currentProbability'],1).' %':'—' }}</td><td class="right {{ is_numeric($card['currentProbability'])&&$card['currentProbability']>=50?'positive':'negative' }}">{{ !is_numeric($card['currentProbability'])?'—':($card['currentProbability']>=50?$t('Positiv','Positive'):$t('Negativ','Negative')) }}</td></tr>@empty<tr><td colspan="4">{{ $t('Keine Indikatorhistorie verfügbar.','No indicator history available.') }}</td></tr>@endforelse
</tbody></table></div>

@if(count($indicatorMatrices))<div class="section"><div class="section-title">{{ $t('Indikator-Heatmaps','Indicator heatmaps') }}</div><table class="matrix-layout"><tr>@foreach($indicatorMatrices as $matrix)<td><div class="matrix-card"><div class="matrix-title">{{ $matrix['x'] }} × {{ $matrix['y'] }} · n={{ $matrix['samples'] }} · <span style="color:#0891b2">{{ $t('Cyan = aktuell','Cyan = current') }}</span></div><table class="matrix">@foreach(array_reverse($matrix['cells'],true) as $y=>$row)<tr>@foreach($row as $x=>$cell)@php $p=$cell['p'];$bg=$p===null?'#f1f5f9':($p>=65?'#bbf7d0':($p>=55?'#dcfce7':($p>=45?'#fef3c7':($p>=35?'#fee2e2':'#fecdd3'))));$current=($matrix['currentX']??null)===$x&&($matrix['currentY']??null)===$y; @endphp<td style="background:{{ $bg }};{{ $current?'border:2px solid #0891b2;box-shadow:inset 0 0 0 1px #ffffff;':'' }}">{{ $current?'● ':'' }}{{ $p===null?'—':$num($p,0).'%' }}<small>n={{ $cell['n'] }}</small></td>@endforeach</tr>@endforeach</table></div></td>@endforeach</tr></table></div>@endif

<div class="section"><div class="section-title">{{ $t('Chartmuster der letzten 5 Tage','Chart patterns in the last 5 days') }}</div><table class="data"><thead><tr><th>{{ $t('Chart','Chart') }}</th><th>{{ $t('Formation','Pattern') }}</th><th>{{ $t('Richtung','Direction') }}</th><th class="right">{{ $t('Fälle','Samples') }}</th><th class="right">{{ $t('Trefferquote','Hit rate') }}</th><th class="right">Ø 20T</th></tr></thead><tbody>
@forelse($patterns as $pattern)<tr><td>@if($pattern['chart'])<img class="pattern-chart" src="{{ $pattern['chart'] }}" alt="">@else—@endif</td><td><strong>{{ $pattern['name'] }}</strong></td><td>{{ ucfirst($pattern['direction']) }}</td><td class="right">{{ $pattern['samples'] }}</td><td class="right">{{ is_numeric($pattern['hit_rate'])?$num($pattern['hit_rate'],1).' %':'—' }}</td><td class="right {{ is_numeric($pattern['average_performance'])&&$pattern['average_performance']>=0?'positive':'negative' }}">{{ is_numeric($pattern['average_performance'])?$num($pattern['average_performance']).' %':'—' }}</td></tr>@empty<tr><td colspan="6">{{ $t('Kein aktuelles Chartmuster erkannt.','No current chart pattern detected.') }}</td></tr>@endforelse
</tbody></table></div>

<div class="section"><div class="section-title">{{ $t('Chancen und Risiken','Opportunities and risks') }}</div><table class="columns"><tr><td class="card"><strong class="positive">{{ $t('Chancen','Opportunities') }}</strong><ul class="list">@forelse($opportunities as $item)<li>{{ $item }}</li>@empty<li>{{ $t('Keine zusätzlichen Chancen ableitbar.','No additional opportunities identified.') }}</li>@endforelse</ul></td><td class="card"><strong class="negative">{{ $t('Risiken','Risks') }}</strong><ul class="list">@foreach($risks as $item)<li>{{ $item }}</li>@endforeach</ul></td></tr></table></div>

<div class="section"><div class="section-title">{{ $t('Fundamentaldaten','Fundamentals') }}</div><table class="data"><tbody>
@foreach([[ $t('Marktkapitalisierung','Market cap'),$stock->market_cap,'KGV',$stock->trailing_pe],[ $t('Forward-KGV','Forward P/E'),$stock->forward_pe,$t('Kurs/Buchwert','Price/book'),$stock->price_to_book],[ $t('Dividendenrendite','Dividend yield'),$pct($stock->dividend_yield),$t('Nettomarge','Net margin'),$pct($stock->profit_margin)],[ $t('Umsatz','Revenue'),$stock->revenue,'EBITDA',$stock->ebitda],[ $t('Liquide Mittel','Cash'),$stock->total_cash,$t('Gesamtverschuldung','Total debt'),$stock->total_debt]] as $row)<tr><td>{{ $row[0] }}</td><td class="right"><strong>{{ is_numeric($row[1])?$large($row[1]):$row[1] }}</strong></td><td>{{ $row[2] }}</td><td class="right"><strong>{{ is_numeric($row[3])?$large($row[3]):$row[3] }}</strong></td></tr>@endforeach
</tbody></table></div>

<p class="note">{{ $t('Datenquelle: Service Datenbank, aktives Release. Dieser Bericht dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar.','Data source: Service database, active release. This report is for information only and does not constitute investment advice.') }}</p>
<div class="footer">aktienKI.com · {{ $stock->symbol }} · {{ $t('Seite','Page') }} <span class="page"></span></div>
</body></html>
