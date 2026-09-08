<x-mail::message>
@php
    $metrics = $signalMetrics ?? [];
    $score = $metrics['score'] ?? null;
    $riskPercent = $metrics['risk_percent'] ?? null;
    $horizonTargets = $metrics['horizon_targets'] ?? [];
    $current = (float) ($prediction->current_price ?? 0);
    $signalColor = strtoupper($signal) === 'BUY' ? '#35d0a5' : '#f2b84b';
    $darkTheme = ($emailTheme ?? 'light') === 'dark';
    $cardBg = $darkTheme ? '#0f2033' : '#ffffff';
    $mutedCardBg = $darkTheme ? '#173347' : '#eef8f7';
    $text = $darkTheme ? '#e7edf5' : '#17263a';
    $muted = $darkTheme ? '#9db0c5' : '#63758b';
    $border = $darkTheme ? '#1e5367' : '#b9e3df';
@endphp
<div style="margin:0 0 14px;padding:11px 14px;border-radius:10px;background:{{ $cardBg }};border:1px solid {{ $border }};color:{{ $text }};font-size:16px;font-weight:800;line-height:1.45">{{ __('Hallo :name,', ['name' => $recipientName ?: __('Trader')]) }}</div>
<div style="background:{{ $cardBg }};border:1px solid {{ $border }};border-radius:16px;padding:22px;color:{{ $text }}">
    <div style="color:#62e6f4;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase">{{ __('AKTUELLES LABEL-SIGNAL') }}</div>
    <div style="margin-top:7px;color:{{ $text }};font-size:14px;font-weight:800">{{ $strategy->name }} <span style="color:#62e6f4">· {{ $instrument->symbol }}</span></div>
    <div style="font-size:25px;font-weight:800;margin-top:7px;color:{{ $text }}"><span style="font-size:22px">{{ $countryFlag ?? '🌐' }}</span> {{ $instrument->name }} <span style="color:#62e6f4;font-size:15px">{{ $instrument->symbol }}</span></div>
    <div style="color:{{ $muted }};font-size:13px;margin-top:4px">{{ __('Dein Label :name hat ein neues Kaufsignal erhalten.', ['name' => $strategy->name]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="8" style="margin:20px -8px 8px;border-collapse:separate">
        <tr>
            <td width="27%" valign="middle" style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:10px;padding:13px"><div style="color:{{ $muted }};font-size:10px;text-transform:uppercase">{{ __('Signal') }}</div><div style="color:{{ $signalColor }};font-size:21px;font-weight:800">{{ strtoupper($signal) }}</div></td>
            <td width="73%" align="center" valign="middle" style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:10px;padding:3px 8px"><img src="cid:aki-signal-score-risk.png" width="310" alt="{{ __('KI-Score') }} {{ $score !== null ? number_format($score, 1, ',', '.').' / 10' : '–' }} · {{ __('Risiko') }} {{ $riskPercent !== null ? number_format($riskPercent, 0, ',', '.').' %' : '–' }}" style="display:block;width:310px;max-width:100%;height:auto;margin:0 auto;border:0"></td>
        </tr>
    </table>
    <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-top:10px;color:#dce6ef;font-size:13px">
        <tr><td style="color:{{ $muted }}">{{ __('Aktueller Kurs') }}</td><td align="right" style="font-weight:800;color:{{ $text }}">{{ number_format($current, 2, ',', '.') }} {{ $instrument->currency }}</td></tr>
        <tr><td style="color:{{ $muted }};border-top:1px solid {{ $border }}">{{ __('Vorheriges Signal') }}</td><td align="right" style="font-weight:700;color:{{ $text }};border-top:1px solid {{ $border }}">{{ strtoupper($previousSignal) }}</td></tr>
    </table>
    <div style="margin-top:15px;color:#169db6;font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase">{{ __('PROGNOSE-ZIELKURSE') }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="margin:7px -6px 0;border-collapse:separate">
<tr>
@foreach([5, 10, 15, 20] as $days)
@php $targetData = $horizonTargets[$days] ?? ['target' => null, 'return' => null]; @endphp
<td width="25%" align="center" valign="top" style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:9px;padding:10px 5px">
<div style="color:{{ $muted }};font-size:9px;font-weight:800;text-transform:uppercase">{{ $days }} {{ __('Tage') }}</div>
<div style="margin-top:4px;color:{{ $text }};font-size:13px;font-weight:800;white-space:nowrap">{{ is_numeric($targetData['target']) ? number_format((float) $targetData['target'], 2, ',', '.').' '.$instrument->currency : '–' }}</div>
<div style="margin-top:3px;color:{{ is_numeric($targetData['return']) && (float) $targetData['return'] < 0 ? '#f27b8b' : '#35d0a5' }};font-size:10px;font-weight:800;white-space:nowrap">{{ is_numeric($targetData['return']) ? (((float) $targetData['return'] >= 0 ? '+' : '').number_format((float) $targetData['return'], 2, ',', '.').' %') : '–' }}</div>
</td>
@endforeach
</tr>
</table>
</div>
@if(strtoupper($signal) === 'BUY')
@include('mail.partials.external-buy-review', ['review' => $externalReview ?? null])
@endif
<x-mail::button :url="route('stocks.show', $instrument->symbol)">{{ __('Analyse öffnen') }}</x-mail::button>
<div style="text-align:center;color:#8398ad;font-size:11px">{{ __('Signalzeitpunkt: :date', ['date' => $prediction->prediction_time?->timezone('Europe/Berlin')->format('d.m.Y H:i')]) }}</div>
<div style="margin-top:20px;padding:13px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Prognosen können fehlerhaft sein und stellen keine Anlageberatung dar.') }}</div>
</x-mail::message>
