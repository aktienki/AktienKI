<x-mail::message>
<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:14px;margin-bottom:18px">
<div style="color:#d9a84e;font-size:10px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;margin:0 0 9px 3px">{{ __('Globale Tagesentwicklung') }}</div>
<img src="cid:aki-market-map.png" alt="{{ __('Weltkarte der Tagesentwicklung') }}" width="720" style="display:block;width:100%;height:auto;border-radius:9px">
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 3px 0;color:#9eb1c3;font-size:10px"><tr><td><span style="display:inline-block;width:10px;height:10px;background:#2ba397;border:1px solid #52d7c4;margin-right:5px"></span>{{ __('Gestiegen') }}</td><td style="padding-left:17px"><span style="display:inline-block;width:10px;height:10px;background:#8b525e;border:1px solid #ce7c89;margin-right:5px"></span>{{ __('Gefallen') }}</td></tr></table>
</div>

<div style="font-size:24px;font-weight:800;color:#17283e">{{ __('Märkte & Marktsituation') }}</div>
<div style="color:#687c91;font-size:13px;margin:5px 0 18px">{{ __('Dein täglicher aKI Dashboard-Überblick') }} · {{ $dataDate }}</div>

<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:18px;color:#e7edf5">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
@foreach($markets as $market)
<tr><td style="padding-bottom:9px"><div style="background:#192b42;border:1px solid #2f4b61;border-radius:9px;padding:12px 14px">
<div style="color:#94a9bd;font-size:9px;font-weight:800;text-transform:uppercase">{{ $market['name'] }}</div>
<div style="color:#ffffff;font-size:16px;font-weight:800;margin-top:4px">{{ $market['price'] !== null ? number_format($market['price'], 2, ',', '.') : '–' }} <span style="font-size:9px;color:#8599ad">{{ $market['currency'] }}</span></div>
<div style="color:{{ ($market['change'] ?? 0) >= 0 ? '#42d6b8' : '#d77987' }};font-size:11px;font-weight:800;margin-top:3px">{{ $market['change'] !== null ? (($market['change'] >= 0 ? '+' : '').number_format($market['change'], 2, ',', '.').' %') : '–' }}</div>
</div></td></tr>
@endforeach
</table>
</div>

<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:17px;color:#e7edf5;margin-top:16px">
<div style="color:#d9a84e;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase">{{ __('Gesamtsituation') }}</div>
<div style="color:#ffffff;font-size:31px;font-weight:900;margin-top:8px">{{ number_format((float)($assessment['score'] ?? 5), 1, ',', '.') }}<span style="font-size:12px;color:#8fa4ba"> /10</span></div>
<div style="display:inline-block;margin-top:9px;padding:5px 9px;border-radius:6px;background:#173b3b;color:#42d6b8;font-size:11px;font-weight:800">{{ $assessment['status'] ?? __('Neutral') }}</div>
<div style="color:#9eb1c3;font-size:11px;line-height:1.5;margin-top:11px">{{ $assessment['positiveMarkets'] ?? 0 }} / {{ $assessment['marketCount'] ?? count($markets) }} {{ __('Märkte positiv') }}</div>
</div>
<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:17px;color:#e7edf5;margin-top:16px">
<div style="color:#42c6b5;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase">{{ __('aKI Tageskommentar') }}</div>
@if(!empty($headline))<div style="color:#ffffff;font-size:16px;font-weight:800;margin-top:7px">{{ $headline }}</div>@endif
<div style="color:#b6c6d5;font-size:12px;line-height:1.65;margin-top:9px">{{ $marketComment }}</div>
</div>

@if(!empty($opportunities) || !empty($risks))
<div style="background:#18313a;border:1px solid #275052;border-radius:12px;padding:15px;margin-top:16px"><div style="color:#42d6b8;font-size:10px;font-weight:800;text-transform:uppercase">{{ __('Chancen') }}</div>@foreach(collect($opportunities)->take(3) as $item)<div style="color:#c4d5dc;font-size:11px;line-height:1.5;margin-top:7px">＋ {{ is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE) }}</div>@endforeach</div>
<div style="background:#352631;border:1px solid #5e3946;border-radius:12px;padding:15px;margin-top:16px"><div style="color:#d77987;font-size:10px;font-weight:800;text-transform:uppercase">{{ __('Risiken') }}</div>@foreach(collect($risks)->take(3) as $item)<div style="color:#d9ccd2;font-size:11px;line-height:1.5;margin-top:7px">− {{ is_scalar($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE) }}</div>@endforeach</div>
@endif

@if(!empty($topStock))
<div style="background:#122034;border:1px solid #516044;border-radius:14px;padding:18px;color:#e7edf5;margin-top:16px">
<div style="color:#d9a84e;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase">{{ __('Top-Aktie des Tages') }}</div>
<div style="color:#ffffff;font-size:21px;font-weight:800;margin-top:6px">{!! str_ireplace('.com', '.&#8203;com', e($topStock['name'])) !!} <span style="color:#d9a84e;font-size:12px">{{ $topStock['symbol'] }}</span></div>
<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-top:10px;color:#dce6ef;font-size:12px">
<tr><td style="color:#91a6ba">{{ __('Signal') }}</td><td align="right" style="font-weight:800;color:#42d6b8">{{ $topStock['signal'] }}</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Aktueller Kurs') }}</td><td align="right" style="font-weight:700;border-top:1px solid #263d52">{{ number_format($topStock['price'], 2, ',', '.') }} {{ $topStock['currency'] }}</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('KI-Score') }}</td><td align="right" style="font-weight:700;border-top:1px solid #263d52">{{ number_format($topStock['score'], 1, ',', '.') }} /10</td></tr>
<tr><td style="color:#91a6ba;border-top:1px solid #263d52">{{ __('Rendite-Prognose 20 Tage') }}</td><td align="right" style="font-weight:800;color:{{ ($topStock['expected_return'] ?? 0) >= 0 ? '#42d6b8' : '#d77987' }};border-top:1px solid #263d52">{{ $topStock['expected_return'] !== null ? number_format($topStock['expected_return'], 2, ',', '.').' %' : '–' }}</td></tr>
</table>
<div style="text-align:center;margin-top:12px"><a href="{{ $topStock['url'] }}" style="display:inline-block;background:#276f69;color:#ffffff;text-decoration:none;border-radius:7px;padding:9px 17px;font-size:12px;font-weight:800">{{ __('Aktienanalyse öffnen') }}</a></div>
</div>
@endif

<div style="text-align:center;margin-top:18px"><a href="{{ $dashboardUrl }}" style="display:inline-block;background:#276f69;color:#ffffff;text-decoration:none;border-radius:7px;padding:11px 20px;font-size:12px;font-weight:800">{{ __('Dashboard öffnen') }}</a></div>

<div style="margin-top:20px;padding:14px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Sie stellen keine Anlageberatung oder Aufforderung zum Handel dar.') }}</div>
</x-mail::message>
