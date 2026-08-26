<x-mail::message>
<style>
@media only screen and (max-width: 620px) {
    .aki-mail-card { padding: 15px !important; border-radius: 12px !important; }
    .aki-mail-title { font-size: 22px !important; line-height: 1.2 !important; }
    .aki-horizon-table, .aki-horizon-table tbody, .aki-horizon-table tr, .aki-horizon-table td { display:block !important; width:100% !important; box-sizing:border-box !important; }
    .aki-horizon-table thead { display:none !important; }
    .aki-horizon-table tr { border-top:1px solid #355167 !important; padding:9px 0 !important; }
    .aki-horizon-table tbody tr:first-child { border-top:0 !important; }
    .aki-horizon-table td { border:0 !important; padding:4px 0 !important; text-align:right !important; }
    .aki-horizon-table td::before { content:attr(data-label); float:left; color:#ffffff !important; font-weight:700; }
    .aki-status-cell { display:block !important; width:100% !important; box-sizing:border-box !important; padding:8px 0 !important; border-left:0 !important; }
}
</style>
<div style="background:#071827;border-left:5px solid #35d7ef;border-top:1px solid #21445a;border-right:1px solid #21445a;border-bottom:1px solid #21445a;border-radius:15px;padding:20px;color:#eaf7fb">
<div style="color:#48d9ef;font-size:10px;font-weight:900;letter-spacing:1.8px;text-transform:uppercase">Training abgeschlossen</div>
<div class="aki-mail-title" style="color:#ffffff;font-size:27px;font-weight:900;margin-top:7px">{{ $name }} <span style="color:#48d9ef;font-size:15px">{{ $symbol }}</span></div>
<div style="color:#8fa8ba;font-size:12px;margin-top:7px">{{ $sourceLabel }} · {{ $finishedAt }}</div>
</div>

<div class="aki-mail-card" style="background:#102235;border:1px solid #29475d;border-radius:13px;padding:16px;margin-top:16px;color:#ffffff">
<div style="font-size:19px;font-weight:900;color:#ffffff;margin:0 0 10px">Walk-Forward nach Horizont</div>
<table class="aki-horizon-table" role="presentation" width="100%" cellpadding="8" cellspacing="0" style="font-size:11px;color:#ffffff">
<thead><tr><th align="left" style="color:#ffffff">Horizont</th><th align="right" style="color:#ffffff">Signale</th><th align="right" style="color:#ffffff">Treffer</th><th align="right" style="color:#ffffff">PF</th><th align="right" style="color:#ffffff">Ø Rendite</th></tr></thead>
<tbody>
@forelse($horizons as $horizon)
<tr>
<td data-label="Horizont" style="border-top:1px solid #284055;font-weight:900;color:#ffffff">{{ $horizon['days'] }}T</td>
<td data-label="Signale" align="right" style="border-top:1px solid #284055;color:#ffffff">{{ $horizon['trades'] }}</td>
<td data-label="Treffer" align="right" style="border-top:1px solid #284055;color:#ffffff">{{ $horizon['hitRate'] }}</td>
<td data-label="PF" align="right" style="border-top:1px solid #284055;color:#ffffff;font-weight:800">{{ $horizon['profitFactor'] }}</td>
<td data-label="Ø Rendite" align="right" style="border-top:1px solid #284055;color:#ffffff;font-weight:800">{{ $horizon['averageReturn'] }}</td>
</tr>
@empty
<tr><td colspan="5" style="border-top:1px solid #284055;color:#ffffff">Noch keine Walk-Forward-Auswertung vorhanden.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div class="aki-mail-card" style="background:#102235;border-left:4px solid #48d9ef;border-top:1px solid #29475d;border-right:1px solid #29475d;border-bottom:1px solid #29475d;border-radius:13px;padding:16px;margin-top:16px;color:#ffffff">
<div style="color:#48d9ef;font-size:10px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase">Performance nach Filtern</div>
@if($filteredPerformance['available'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;color:#ffffff">
<tr>
@foreach([['Signale', $filteredPerformance['trades']], ['Trefferquote', $filteredPerformance['hitRate']], ['Profitfaktor', $filteredPerformance['profitFactor']], ['Ø Rendite', $filteredPerformance['averageReturn']]] as $metric)
<td class="aki-status-cell" width="25%" valign="top" style="padding:8px 9px;border-left:1px solid #29475d">
<div style="color:#ffffff;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.7px">{{ $metric[0] }}</div>
<div style="color:#ffffff;font-size:16px;font-weight:900;margin-top:4px">{{ $metric[1] }}</div>
</td>
@endforeach
</tr>
</table>
@else
<div style="color:#ffffff;font-size:13px;line-height:1.5;margin-top:8px">Die individuelle Kalibrierung und Filterauswertung wurde noch nicht abgeschlossen.</div>
@endif
</div>

<div class="aki-mail-card" style="background:#102235;border-left:4px solid {{ $validationPassed ? '#48d6b3' : '#f0bd4f' }};border-top:1px solid #29475d;border-right:1px solid #29475d;border-bottom:1px solid #29475d;border-radius:13px;padding:16px;margin-top:16px;color:#ffffff">
<div style="color:#48d9ef;font-size:10px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase">Modellstatus</div>
<div style="font-size:19px;line-height:1.3;font-weight:900;color:#ffffff;margin-top:5px">{{ $validationPassed ? 'Für die Verwendung freigegeben' : 'Dokumentiert · noch nicht freigegeben' }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;color:#ffffff">
<tr>
<td class="aki-status-cell" width="50%" valign="top" style="padding:9px 12px 9px 0">
<div style="color:#ffffff;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px">Qualitätsklasse</div>
<div style="color:#ffffff;font-size:15px;font-weight:900;margin-top:3px">{{ $qualityClass }}</div>
</td>
<td class="aki-status-cell" width="50%" valign="top" style="padding:9px 0 9px 12px;border-left:1px solid #29475d">
<div style="color:#ffffff;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px">Freigabe</div>
<div style="color:#ffffff;font-size:15px;font-weight:900;margin-top:3px">{{ $statusLabel }}</div>
</td>
</tr>
<tr>
<td class="aki-status-cell" width="50%" valign="top" style="padding:9px 12px 2px 0;border-top:1px solid #29475d">
<div style="color:#ffffff;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px">Individuelle KI-Schwelle</div>
<div style="color:#ffffff;font-size:15px;font-weight:900;margin-top:3px">{{ $minimumAiScore }}</div>
</td>
<td class="aki-status-cell" width="50%" valign="top" style="padding:9px 0 2px 12px;border-top:1px solid #29475d;border-left:1px solid #29475d">
<div style="color:#ffffff;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px">Gesamtdauer</div>
<div style="color:#ffffff;font-size:15px;font-weight:900;margin-top:3px">{{ $duration }}</div>
</td>
</tr>
</table>
</div>

<div style="margin-top:18px;padding:13px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">Die Kennzahlen dokumentieren historische Modelltests und sind keine Garantie für zukünftige Ergebnisse oder Anlageberatung.</div>
</x-mail::message>
