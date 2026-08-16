<x-mail::message>
<div style="font-size:24px;font-weight:800;color:#17283e;margin-bottom:5px">{{ __('Aktuelle Signale') }}</div>
<div style="font-size:13px;line-height:1.55;color:#687c91;margin-bottom:20px">{{ __('Deine aktuell stärksten qualifizierten aKI-Signale in einer kompakten Übersicht.') }}</div>

@foreach($recommendations as $index => $recommendation)
<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:20px;color:#e7edf5;{{ $index > 0 ? 'margin-top:18px' : '' }}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
<tr>
<td><div style="color:#d9a84e;font-size:10px;font-weight:800;letter-spacing:1.7px">TOP {{ $index + 1 }}</div><div style="color:#ffffff;font-size:21px;font-weight:800;margin-top:4px">{!! str_ireplace('.com', '.&#8203;com', e($recommendation['name'])) !!}</div><div style="color:#d9a84e;font-size:12px;font-weight:700">{{ $recommendation['symbol'] }}</div></td>
<td align="right" valign="top"><span style="display:inline-block;background:#153d3b;border:1px solid #2b746c;border-radius:6px;padding:6px 12px;color:#4ad9bd;font-size:13px;font-weight:800">{{ $recommendation['signal'] }}</span></td>
</tr>
</table>

<div style="margin:15px 0 11px">
<img src="cid:{{ $recommendation['chart_cid'] }}" alt="{{ __('Kurschart mit 20-Tage-Prognose') }}" width="720" style="display:block;width:100%;height:auto;border-radius:9px;border:1px solid #29445a">
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="7" style="margin:0 -7px;border-collapse:separate">
<tr>
@foreach([
    [__('Kurs'), number_format($recommendation['current_price'], 2, ',', '.').' '.$recommendation['currency'], '#ffffff'],
    [__('KI-Score'), number_format($recommendation['score'], 1, ',', '.').' / 10', '#ffffff'],
    [__('Model Qualität'), number_format($recommendation['confidence'], 1, ',', '.').' %', '#ffffff'],
    [__('20-Tage-Prognose'), ($recommendation['expected_return'] !== null ? number_format($recommendation['expected_return'], 2, ',', '.').' %' : '–'), ($recommendation['expected_return'] ?? 0) >= 0 ? '#4ad9bd' : '#d77987'],
] as [$label, $value, $color])
<td width="25%" style="background:#192b42;border:1px solid #2f4b61;border-radius:8px;padding:9px"><div style="color:#8fa4ba;font-size:8px;text-transform:uppercase">{{ $label }}</div><div style="color:{{ $color }};font-size:13px;font-weight:800;margin-top:3px;white-space:nowrap">{{ $value }}</div></td>
@endforeach
</tr>
</table>

@if(!empty($recommendation['analysis']))
<div style="margin-top:12px;background:#182a40;border-left:3px solid #d9a84e;border-radius:5px;padding:11px 12px;color:#b9c8d7;font-size:12px;line-height:1.55">
<strong style="color:#ffffff">{{ __('aKI Analyse') }}:</strong> {{ $recommendation['analysis'] }}
@if(!empty($recommendation['analysis_risks']))<div style="color:#d9a8af;margin-top:6px"><strong>{{ __('Risiken') }}:</strong> {{ collect($recommendation['analysis_risks'])->take(2)->map(fn($item) => is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE))->implode(' · ') }}</div>@endif
</div>
@endif

<div style="text-align:center;margin-top:14px"><a href="{{ $recommendation['analysis_url'] }}" style="display:inline-block;background:#276f69;color:#ffffff;text-decoration:none;border-radius:7px;padding:9px 17px;font-size:12px;font-weight:800">{{ __('Analyse öffnen') }}</a></div>
</div>
@endforeach

<div style="text-align:center;color:#8398ad;font-size:11px;margin-top:16px">{{ __('Datenstand: :date', ['date' => $recommendations[0]['prediction_date'] ?? '–']) }}</div>

<div style="margin-top:20px;padding:14px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">
{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Sie stellen keine Anlageberatung, Kaufempfehlung oder Aufforderung zum Handel dar. Prognosen können fehlerhaft sein und Verluste sind jederzeit möglich.') }}
</div>
</x-mail::message>
