<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#ffffff;color:#122033;font-family:Inter,Arial,sans-serif;padding:28px 12px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;margin:0 auto;background:#ffffff">
<tr><td style="padding:12px 22px 20px"><table width="100%"><tr><td><img src="cid:aktienki-logo.png" width="230" alt="aktienKI.com" style="display:block;width:230px;max-width:100%;height:auto"></td><td align="right" style="color:#087681;font-size:11px;font-weight:800;letter-spacing:1.5px">PRO · KAUFERINNERUNG</td></tr></table></td></tr>
<tr><td style="padding:8px 22px 24px;color:#42566c;font-size:16px;line-height:1.65">
    Hallo {{ $user->name }},<br><br>
    du hast uns am {{ \Illuminate\Support\Carbon::parse($reminder->created_at)->format('d.m.Y') }} beauftragt, dir eine Erinnerungsmail zu schreiben. Hier ist der aktuelle Status.<br><br>
    Dein aktienKI.com Team
</td></tr>
<tr><td style="padding:0 22px 28px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#101f33" style="background:#101f33;border:2px solid #29475e;border-radius:16px;box-shadow:0 10px 28px rgba(20,55,70,.16)"><tr><td style="padding:28px">
    <h1 style="margin:0 0 4px;color:#f7fbfc;font-size:29px">{{ $instrument->name }}</h1>
    <div style="color:#64e5f2;font-weight:800">{{ $instrument->symbol }} · {{ $instrument->sector }}</div>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:22px;border-collapse:separate;border-spacing:7px"><tr>
        <td style="padding:14px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:10px;text-transform:uppercase">Kurs bei Erinnerung<div style="margin-top:7px;color:#f7fbfc;font-size:18px;font-weight:800">{{ number_format($reminder->purchase_price,2,',','.') }} {{ $instrument->currency }}</div></td>
        <td style="padding:14px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:10px;text-transform:uppercase">Aktueller Kurs<div style="margin-top:7px;color:#f7fbfc;font-size:18px;font-weight:800">{{ number_format($currentPrice,2,',','.') }} {{ $instrument->currency }}</div></td>
        <td style="padding:14px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:10px;text-transform:uppercase">Performance seit Erinnerung<div style="margin-top:7px;color:{{ $performance >= 0 ? '#34d399' : '#fb7185' }};font-size:18px;font-weight:800">{{ $performance >= 0 ? '+' : '' }}{{ number_format($performance,2,',','.') }} %</div></td>
    </tr></table>

    <div style="margin-top:17px;padding:14px 17px;background:#0d1b2d;border:1px solid #29445e;border-radius:10px;color:#91a8bb">Aktuelles Signal <strong style="float:right;color:#e5b95d">{{ $currentSignal }}</strong></div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-top:18px;background:#0d1b2d;border:1px solid #29445e;border-radius:12px"><tr><td style="padding:14px">
        <img src="cid:prediction-chart.png" width="570" alt="Kursverlauf und aktuelle Prognosen" style="display:block;width:100%;max-width:570px;height:auto;margin:0 auto">
    </td></tr></table>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;border-collapse:separate;border-spacing:6px"><tr>
    @foreach([5,10,15,20] as $days) @php($target=$forecasts[$days] ?? null) @php($return=$target && $currentPrice > 0 ? (($target-$currentPrice)/$currentPrice)*100 : null)
        <td align="center" style="padding:11px 5px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px"><b style="display:block;color:#64e5f2;font-size:12px">{{ $days }}T</b>@if($target)<span style="display:block;margin-top:5px;color:#f7fbfc;font-weight:800">{{ number_format($target,2,',','.') }}</span><span style="color:{{ $return >= 0 ? '#34d399' : '#fb7185' }}">{{ $return >= 0 ? '+' : '' }}{{ number_format($return,2,',','.') }} %</span>@else<span style="display:block;margin-top:8px">—</span>@endif</td>
    @endforeach
    </tr></table>

    <div style="text-align:center;margin-top:25px"><a href="{{ $stockUrl }}" style="display:inline-block;padding:13px 25px;background:#0f9f95;border-radius:8px;color:#fff;text-decoration:none;font-weight:800">Aktie jetzt prüfen</a></div>
</td></tr></table>
</td></tr>
<tr><td style="padding:18px 22px;border-top:1px solid #dbe7e9;color:#718196;font-size:11px;line-height:1.6">Diese E-Mail betrifft nur diese persönliche Kauferinnerung. Andere E-Mail-Einstellungen bleiben unverändert.<br>Keine Anlageberatung. Prognosen können fehlerhaft sein; Verluste sind jederzeit möglich.</td></tr>
</table></body></html>
