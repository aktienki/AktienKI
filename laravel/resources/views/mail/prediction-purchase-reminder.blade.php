<x-mail::message>
<div style="color:#087681;font-size:10px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase">PRO · {{ __('Prognose-Erinnerung') }}</div>
<h1 style="margin:6px 0 4px;color:#122033;font-size:25px">{{ $instrument->name }}</h1>
<div style="color:#60758a;font-size:13px">{{ $instrument->symbol }} · {{ $reminder->horizon_days }}T {{ $purchased ? __('Positions-Check') : __('Kauf-Check') }}</div>

<p style="margin:20px 0;color:#42566c;font-size:14px;line-height:1.65">
{{ $purchased
    ? __('Hallo :name, heute prüfen wir deine vorgemerkte Position. AktienKI hat Entwicklung, Signal und die aktuellen Remote-Prognosen erneut geladen.', ['name' => $user->name ?? __('AktienKI-Nutzer')])
    : __('Hallo :name, heute ist dein vorgemerkter Kaufprüftag. AktienKI hat Kurs, Signal und die aktuellen Remote-Prognosen erneut geladen, damit du deine Kaufentscheidung neu bewerten kannst.', ['name' => $user->name ?? __('AktienKI-Nutzer')]) }}
</p>

<div style="background:#101f33;border:1px solid #29475e;border-radius:14px;padding:20px;color:#f7fbfc">
<table role="presentation" width="100%" cellpadding="7" cellspacing="0" style="font-size:12px">
<tr><td style="color:#91a8bb">{{ __('Aktuelles Signal') }}</td><td align="right" style="font-weight:800;color:#e5b95d">{{ $currentSignal }}</td></tr>
<tr><td style="color:#91a8bb;border-top:1px solid #29445e">{{ $purchased ? __('Kaufkurs') : __('Kurs bei Vormerkung') }}</td><td align="right" style="font-weight:800;border-top:1px solid #29445e">{{ number_format($reminder->purchase_price, 2, ',', '.') }} {{ $instrument->currency }}</td></tr>
<tr><td style="color:#91a8bb;border-top:1px solid #29445e">{{ __('Aktueller Kurs') }}</td><td align="right" style="font-weight:800;border-top:1px solid #29445e">{{ number_format($currentPrice, 2, ',', '.') }} {{ $instrument->currency }}</td></tr>
<tr><td style="color:#91a8bb;border-top:1px solid #29445e">{{ __('Entwicklung') }}</td><td align="right" style="font-weight:800;border-top:1px solid #29445e;color:{{ $performance >= 0 ? '#34d399' : '#fb7185' }}">{{ $performance >= 0 ? '+' : '' }}{{ number_format($performance, 2, ',', '.') }} %</td></tr>
</table>

<div style="margin-top:15px"><img src="cid:prediction-chart.png" width="570" alt="{{ __('Kursverlauf und aktuelle Prognosen') }}" style="display:block;width:100%;max-width:570px;height:auto;border-radius:10px"></div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="margin-top:14px;border-collapse:separate">
<tr>
@foreach([10,20,40] as $days)
@php($target = $forecasts[$days] ?? null)
@php($return = $target && $currentPrice > 0 ? (($target - $currentPrice) / $currentPrice) * 100 : null)
<td align="center" style="padding:10px 4px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px"><b style="display:block;color:#64e5f2;font-size:12px">{{ $days }}T</b>@if($target)<span style="display:block;margin-top:5px;color:#f7fbfc;font-weight:800">{{ number_format($target,2,',','.') }}</span><span style="color:{{ $return >= 0 ? '#34d399' : '#fb7185' }}">{{ $return >= 0 ? '+' : '' }}{{ number_format($return,2,',','.') }} %</span>@else<span style="display:block;margin-top:8px">—</span>@endif</td>
@endforeach
</tr>
</table>
</div>

<x-mail::button :url="$stockUrl">
{{ $purchased ? __('Position jetzt prüfen') : __('Kaufentscheidung jetzt prüfen') }}
</x-mail::button>

<div style="margin-top:18px;padding:13px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">
{{ __('Diese E-Mail ist eine persönliche Prognose-Erinnerung und keine Anlageberatung. Prognosen können fehlerhaft sein; Verluste sind jederzeit möglich.') }}
</div>
</x-mail::message>
