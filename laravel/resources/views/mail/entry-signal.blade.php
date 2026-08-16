<x-mail::message>
<div style="margin:0 0 14px;padding:11px 14px;border-radius:10px;background:#f5fafb;border:1px solid #c8e4e8;color:#17283e;font-size:16px;font-weight:800;line-height:1.45">{{ __('Hallo :name,', ['name' => $recipientName]) }}</div>

<div style="background:#122034;border:1px solid #29445a;border-radius:14px;padding:22px;color:#e7edf5">
<div style="color:{{ $signal === 'BUY' ? '#42d6b8' : '#e5b95d' }};font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase">{{ $signal === 'BUY' ? __('Kaufsignal erkannt') : __('Weiter beobachten') }}</div>
<div style="font-size:25px;font-weight:800;margin-top:7px;color:#ffffff">{{ $instrument->name }} <span style="color:#64e5f2;font-size:15px">{{ $instrument->symbol }}</span></div>
<div style="color:#9db0c5;font-size:13px;line-height:1.55;margin-top:6px">{{ $signal === 'BUY'
    ? __('Der zuvor beobachtete Status WAIT hat auf BUY gewechselt.')
    : __('Der Status ist weiterhin WAIT. Der langfristige Ausblick bleibt positiv, kurzfristig wird weiter abgewartet.') }}</div>

<div style="margin:20px 0 12px">
<img src="cid:aki-entry-signal-chart@aktienki.com" alt="{{ __('Kurschart mit 20-Tage-Prognose') }}" width="720" style="display:block;width:100%;height:auto;border-radius:10px;border:1px solid #29445a">
</div>

<div style="margin-top:18px;color:#64e5f2;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase">{{ __('Prognosen und Modellwerte') }}</div>
<table role="presentation" width="100%" cellpadding="9" cellspacing="0" style="margin-top:8px;border-collapse:collapse;color:#dce6ef;font-size:11px">
<tr style="background:#192b42;color:#91a6ba;text-transform:uppercase"><th align="left">{{ __('Horizont') }}</th><th align="right">{{ __('Zielkurs') }}</th><th align="right">{{ __('Rendite') }}</th><th align="right">{{ __('KI-Score') }}</th></tr>
@forelse($horizons as $row)
<tr><td style="border-top:1px solid #29445a;font-weight:800">{{ $row['days'] }} {{ __('Tage') }}</td><td align="right" style="border-top:1px solid #29445a">{{ $row['target'] !== null ? number_format($row['target'], 2, ',', '.').' '.$instrument->currency : '–' }}</td><td align="right" style="border-top:1px solid #29445a;color:{{ ($row['return'] ?? 0) >= 0 ? '#42d6b8' : '#ef8c9a' }}">{{ $row['return'] !== null ? (($row['return'] > 0 ? '+' : '').number_format($row['return'], 2, ',', '.').' %') : '–' }}</td><td align="right" style="border-top:1px solid #29445a">{{ $row['score'] !== null ? number_format($row['score'], 1, ',', '.').' / 10' : '–' }}</td></tr>
@empty
<tr><td colspan="4" style="border-top:1px solid #29445a;color:#91a6ba">{{ __('Noch keine Horizontprognosen verfügbar.') }}</td></tr>
@endforelse
</table>
</div>

<div style="margin-top:16px;background:#f5fafb;border:1px solid #c8e4e8;border-radius:14px;padding:18px;color:#17283e">
<div style="color:#167c89;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase">{{ __('Aktuelle Einschätzung') }}</div>
@if($assessment)<div style="margin-top:7px;font-size:13px;font-weight:800">{{ $assessment->recommendation }}</div>@endif
<div style="margin-top:8px;color:#52677b;font-size:12px;line-height:1.65">{{ $assessmentSummary }}</div>
</div>

<x-mail::button :url="route('stocks.show', $instrument->symbol)">{{ __('Aktie ansehen') }}</x-mail::button>

<div style="color:#7f91a4;font-size:11px;line-height:1.55;text-align:center">{{ __('Dies ist ein Modellsignal und keine Anlageberatung.') }}</div>
</x-mail::message>
