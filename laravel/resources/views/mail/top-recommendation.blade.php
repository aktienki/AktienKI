<x-mail::message>
<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:22px;color:#e7edf5">
<div style="color:#d9a84e;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase">{{ __('aKI Top-Empfehlung') }}</div>
<div style="font-size:25px;font-weight:800;margin-top:6px;color:#ffffff">{!! str_ireplace('.com', '.&#8203;com', e($name)) !!} <span style="color:#d9a84e;font-size:15px">{{ $symbol }}</span></div>
<div style="color:#9db0c5;font-size:13px;margin-top:4px">{{ __('Qualifizierte Empfehlung auf Basis der aktuellen Modellbewertung') }}</div>

<div style="margin:20px 0 10px">
<img src="cid:aki-recommendation-chart.png" alt="{{ __('Kurschart mit 20-Tage-Prognose') }}" width="720" style="display:block;width:100%;height:auto;border-radius:10px;border:1px solid #29445a">
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="8" style="margin:12px -8px 4px;border-collapse:separate">
<tr>
<td style="background:#192b42;border:1px solid #2f4b61;border-radius:9px;padding:12px"><div style="color:#8fa4ba;font-size:10px;text-transform:uppercase">{{ __('Signal') }}</div><div style="color:#42d6b8;font-size:18px;font-weight:800">{{ $signal }}</div></td>
<td style="background:#192b42;border:1px solid #2f4b61;border-radius:9px;padding:12px"><div style="color:#8fa4ba;font-size:10px;text-transform:uppercase">{{ __('KI-Score') }}</div><div style="color:#ffffff;font-size:18px;font-weight:800">{{ number_format($score, 1, ',', '.') }} <span style="font-size:11px;color:#8fa4ba">/10</span></div></td>
<td style="background:#192b42;border:1px solid #2f4b61;border-radius:9px;padding:12px"><div style="color:#8fa4ba;font-size:10px;text-transform:uppercase">{{ __('Model Qualität') }}</div><div style="color:#ffffff;font-size:18px;font-weight:800">{{ number_format($confidence, 1, ',', '.') }}%</div></td>
</tr>
</table>

<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-top:10px;color:#dce6ef;font-size:13px">
<tr><td style="color:#91a6ba">{{ __('Aktueller Kurs') }}</td><td align="right" style="font-weight:700">{{ number_format($current_price, 2, ',', '.') }} {{ $currency }}</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Zielkurs 20 Tage') }}</td><td align="right" style="font-weight:700;border-top:1px solid #263d52">{{ $target_price !== null ? number_format($target_price, 2, ',', '.').' '.$currency : '–' }}</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Rendite-Prognose 20 Tage') }}</td><td align="right" style="font-weight:800;color:{{ ($expected_return ?? 0) >= 0 ? '#42d6b8' : '#d46a78' }};border-top:1px solid #263d52">{{ $expected_return !== null ? number_format($expected_return, 2, ',', '.').' %' : '–' }}</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Risiko') }}</td><td align="right" style="font-weight:700;border-top:1px solid #263d52">{{ number_format($risk, 1, ',', '.') }} %</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Modell') }}</td><td align="right" style="font-weight:700;border-top:1px solid #263d52">{{ $model_alias }} · {{ $quality_tier }}</td></tr>
</table>
</div>

@if(!empty($analysis))
<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:20px;margin-top:16px;color:#dce6ef">
<div style="color:#d9a84e;font-size:11px;font-weight:800;letter-spacing:1.7px;text-transform:uppercase">{{ __('aKI Analyse') }}</div>
<div style="font-size:16px;font-weight:800;color:#ffffff;margin:5px 0 10px">{{ __('Chancen und Risikoeinordnung') }}</div>
<div style="font-size:13px;line-height:1.65;color:#b9c8d7">{{ $analysis }}</div>

@if(!empty($analysis_opportunities) || !empty($analysis_risks))
<table role="presentation" width="100%" cellpadding="0" cellspacing="8" style="margin:14px -8px 0;border-collapse:separate">
<tr>
<td width="50%" valign="top" style="background:#18313a;border:1px solid #275052;border-radius:9px;padding:12px">
<div style="color:#42d6b8;font-size:10px;font-weight:800;text-transform:uppercase;margin-bottom:7px">{{ __('Chancen') }}</div>
@forelse($analysis_opportunities as $item)<div style="font-size:12px;line-height:1.45;color:#c9d8df;margin-top:5px">＋ {{ is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE) }}</div>@empty<div style="color:#8094a7">–</div>@endforelse
</td>
<td width="50%" valign="top" style="background:#352631;border:1px solid #5e3946;border-radius:9px;padding:12px">
<div style="color:#d77987;font-size:10px;font-weight:800;text-transform:uppercase;margin-bottom:7px">{{ __('Risiken') }}</div>
@forelse($analysis_risks as $item)<div style="font-size:12px;line-height:1.45;color:#d9ccd2;margin-top:5px">− {{ is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE) }}</div>@empty<div style="color:#9b8790">–</div>@endforelse
</td>
</tr>
</table>
@endif
</div>
@endif

<x-mail::button :url="$analysis_url">
{{ __('Vollständige Analyse öffnen') }}
</x-mail::button>

<div style="text-align:center;color:#8398ad;font-size:11px">{{ __('Datenstand: :date', ['date' => $prediction_date]) }}</div>

<div style="margin-top:22px;padding:14px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">
{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Sie stellen keine Anlageberatung, Kaufempfehlung oder Aufforderung zum Handel dar. Prognosen können fehlerhaft sein und Verluste sind jederzeit möglich.') }}
</div>
</x-mail::message>
