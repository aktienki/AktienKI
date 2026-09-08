<x-mail::message>
<div style="box-sizing:border-box;width:100%;max-width:100%;background:#071827;border-left:5px solid #35d7ef;border-top:1px solid #21445a;border-right:1px solid #21445a;border-bottom:1px solid #21445a;border-radius:15px;padding:20px;color:#eaf7fb;overflow-wrap:anywhere;word-break:break-word">
<div style="color:#48d9ef;font-size:10px;font-weight:900;letter-spacing:1.8px;text-transform:uppercase">Prediction-Batch abgeschlossen</div>
<div style="color:#ffffff;font-size:25px;font-weight:900;margin-top:7px">{{ $statusOk ? 'Vollständig verarbeitet' : 'Mit Hinweisen abgeschlossen' }}</div>
<div style="color:#8fa8ba;font-size:12px;margin-top:7px">Region {{ $region }} · {{ $finishedAt }}</div>
</div>

<div style="box-sizing:border-box;width:100%;max-width:100%;background:#102235;border:1px solid #29475d;border-radius:13px;padding:16px;margin-top:16px;color:#ffffff;overflow:hidden">
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="width:100%;table-layout:fixed;color:#ffffff;text-align:center">
<tr><td><div style="color:#ffffff;font-size:9px;font-weight:800">ERFOLGREICH</div><div style="color:#42dda3;font-size:23px;font-weight:900">{{ $completed }}</div></td><td style="border-left:1px solid #29475d"><div style="color:#ffffff;font-size:9px;font-weight:800">ÜBERSPRUNGEN</div><div style="color:#ffcc4d;font-size:23px;font-weight:900">{{ $skipped }}</div></td><td style="border-left:1px solid #29475d"><div style="color:#ffffff;font-size:9px;font-weight:800">FEHLER</div><div style="color:#ff7489;font-size:23px;font-weight:900">{{ $failed }}</div></td></tr>
</table>
</div>

<div style="box-sizing:border-box;width:100%;max-width:100%;background:#102235;border:1px solid #29475d;border-radius:13px;padding:16px;margin-top:16px;color:#ffffff;overflow-wrap:anywhere;word-break:break-word">
<div style="color:#48d9ef;font-size:10px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase">Fehler und Hinweise</div>
@forelse($errors as $error)
<div style="box-sizing:border-box;max-width:100%;margin-top:8px;padding:8px 10px;border:1px solid #533044;border-radius:8px;background:#321d2a;color:#ffb0bd;font-size:11px;line-height:1.45;overflow-wrap:anywhere;word-break:break-word;white-space:normal">{{ $error }}</div>
@empty
<div style="margin-top:8px;color:#42dda3;font-size:13px;font-weight:800">Keine Fehler gemeldet.</div>
@endforelse
</div>

<div style="margin-top:18px;padding:13px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">Diese E-Mail wird erst nach dem vollständig durchlaufenen Gesamtbatch und seiner Finalisierung versendet.</div>
</x-mail::message>
