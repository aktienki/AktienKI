<x-mail::message>
@php
    $score = (float) ($prediction->ai_score ?? $prediction->prediction_score ?? 0);
    if ($score > 10) $score /= 10;
    $confidence = (float) ($prediction->confidence ?? 0);
    if ($confidence <= 1) $confidence *= 100;
    $current = (float) ($prediction->current_price ?? 0);
    $target = (float) ($prediction->predicted_price_20d ?? 0);
    $signalColor = strtoupper($signal) === 'BUY' ? '#35d0a5' : '#f2b84b';
    $expectedReturn = $expectedReturn ?? ($current > 0 && $target > 0 ? (($target / $current) - 1) * 100 : null);
    $darkTheme = ($emailTheme ?? 'light') === 'dark';
    $pageBg = $darkTheme ? '#081526' : '#f3f8f8';
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
    <div style="font-size:25px;font-weight:800;margin-top:7px;color:{{ $text }}">{{ $instrument->name }} <span style="color:#62e6f4;font-size:15px">{{ $instrument->symbol }}</span></div>
    <div style="color:{{ $muted }};font-size:13px;margin-top:4px">{{ __('Dein Label :name hat ein neues Kaufsignal erhalten.', ['name' => $strategy->name]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="8" style="margin:20px -8px 8px;border-collapse:separate">
        <tr>
            <td style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:10px;padding:13px"><div style="color:{{ $muted }};font-size:10px;text-transform:uppercase">{{ __('Signal') }}</div><div style="color:{{ $signalColor }};font-size:19px;font-weight:800">{{ strtoupper($signal) }}</div></td>
            <td style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:10px;padding:13px"><div style="color:{{ $muted }};font-size:10px;text-transform:uppercase">{{ __('KI-Score') }}</div><div style="color:{{ $text }};font-size:19px;font-weight:800">{{ number_format($score, 1, ',', '.') }} <span style="font-size:11px;color:{{ $muted }}">/10</span></div></td>
            <td style="background:{{ $mutedCardBg }};border:1px solid {{ $border }};border-radius:10px;padding:13px"><div style="color:{{ $muted }};font-size:10px;text-transform:uppercase">{{ __('Konfidenz') }}</div><div style="color:#35d0a5;font-size:19px;font-weight:800">{{ number_format($confidence, 1, ',', '.') }}%</div></td>
        </tr>
    </table>
    <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-top:10px;color:#dce6ef;font-size:13px">
        <tr><td style="color:{{ $muted }}">{{ __('Aktueller Kurs') }}</td><td align="right" style="font-weight:800;color:{{ $text }}">{{ number_format($current, 2, ',', '.') }} {{ $instrument->currency }}</td></tr>
        <tr><td style="color:{{ $muted }};border-top:1px solid {{ $border }}">{{ __('Zielkurs 20 Tage') }}</td><td align="right" style="font-weight:800;color:{{ $text }};border-top:1px solid {{ $border }}">{{ $target > 0 ? number_format($target, 2, ',', '.').' '.$instrument->currency : '–' }}</td></tr>
        <tr><td style="color:{{ $muted }};border-top:1px solid {{ $border }}">{{ __('Rendite-Prognose 20 Tage') }}</td><td align="right" style="font-weight:800;color:{{ ($expectedReturn ?? 0) >= 0 ? '#35d0a5' : '#f27b8b' }};border-top:1px solid {{ $border }}">{{ $expectedReturn !== null ? number_format($expectedReturn, 2, ',', '.').' %' : '–' }}</td></tr>
        <tr><td style="color:{{ $muted }};border-top:1px solid {{ $border }}">{{ __('Vorheriges Signal') }}</td><td align="right" style="font-weight:700;color:{{ $text }};border-top:1px solid {{ $border }}">{{ strtoupper($previousSignal) }}</td></tr>
    </table>
</div>
<x-mail::button :url="route('stocks.show', $instrument->symbol)">{{ __('Analyse öffnen') }}</x-mail::button>
<div style="text-align:center;color:#8398ad;font-size:11px">{{ __('Signalzeitpunkt: :date', ['date' => $prediction->prediction_time?->timezone('Europe/Berlin')->format('d.m.Y H:i')]) }}</div>
<div style="margin-top:20px;padding:13px;border-radius:9px;background:#f2f4f7;color:#5b6571;font-size:11px;line-height:1.55">{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Prognosen können fehlerhaft sein und stellen keine Anlageberatung dar.') }}</div>
</x-mail::message>
