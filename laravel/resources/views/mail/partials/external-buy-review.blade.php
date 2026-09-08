@php
    $reviewStatus = $review?->status;
    $reviewVerdict = $review?->verdict;
    $verdictLabel = match ($reviewVerdict) {
        'NO_OBJECTION' => __('Kein wesentlicher Einwand'),
        'CAUTION' => __('Vorsicht'),
        'OBJECTION' => __('Wesentlicher Einwand'),
        'INSUFFICIENT_EVIDENCE' => __('Datenlage unzureichend'),
        default => $reviewStatus === 'failed' ? __('Recherche fehlgeschlagen') : __('Webrecherche läuft'),
    };
    $verdictColor = match ($reviewVerdict) {
        'NO_OBJECTION' => '#169c77',
        'CAUTION' => '#b97900',
        'OBJECTION' => '#c43d52',
        default => '#63758b',
    };
    $reviewIsYes = $reviewStatus === 'completed' && $reviewVerdict === 'NO_OBJECTION';
    $reviewDecision = $reviewIsYes ? __('JA') : __('NEIN');
    $reviewDecisionColor = $reviewIsYes ? '#169c77' : '#c43d52';
    $reviewAdjustment = match ($reviewStatus === 'completed' ? $reviewVerdict : $reviewStatus) {
        'NO_OBJECTION' => __('BUY bestätigt'),
        'CAUTION', 'OBJECTION' => __('BUY extern abgestuft'),
        'INSUFFICIENT_EVIDENCE', 'failed' => __('BUY nicht bestätigt'),
        default => __('Prüfung offen'),
    };
    $reviewAdjustmentColor = in_array($reviewVerdict, ['CAUTION', 'OBJECTION'], true)
        ? '#b97900'
        : ($reviewIsYes ? '#169c77' : '#63758b');
    $positiveFactors = collect($review?->positive_factors ?? [])->take(4);
    $riskFactors = collect($review?->risk_factors ?? [])->take(4);
    $findings = collect($review?->key_findings ?? [])->take(4);
    $sources = collect($review?->sources ?? [])->take(6);
    $signalScope = is_array($review?->signal_scope)
        ? $review->signal_scope
        : (json_decode((string) ($review?->signal_scope ?? '{}'), true) ?: []);
    $mlAnalysis = (array) data_get($signalScope, 'aktienki_ml_analysis', []);
    $mlConfidence = data_get($mlAnalysis, 'confidence_percent');
@endphp
<div style="margin-top:18px;background:{{ $cardBg }};border:1px solid {{ $border }};border-radius:16px;padding:22px;color:{{ $text }}">
<div style="color:#169db6;font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase">{{ __('UNABHÄNGIGER KI-CHECK · WEBRECHERCHE') }}</div>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:10px;border-collapse:separate"><tr>
<td style="border:2px solid {{ $reviewDecisionColor }};border-radius:10px;padding:7px 14px;color:{{ $reviewDecisionColor }};font-size:22px;font-weight:900;line-height:1">{{ $reviewDecision }}</td>
<td style="padding-left:9px"><span style="display:inline-block;border:1px solid {{ $reviewAdjustmentColor }};border-radius:8px;padding:6px 9px;color:{{ $reviewAdjustmentColor }};font-size:11px;font-weight:900;text-transform:uppercase">{{ $reviewAdjustment }}</span></td>
</tr></table>
<div style="margin-top:9px">
<span style="display:inline-block;border:1px solid {{ $verdictColor }};border-radius:999px;padding:6px 10px;color:{{ $verdictColor }};font-size:13px;font-weight:800">{{ $verdictLabel }}</span>
@if($review?->confidence !== null)
<span style="margin-left:8px;color:{{ $muted }};font-size:12px">{{ __('Recherche-Konfidenz') }}: {{ (int) $review->confidence }} %</span>
@endif
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;border:1px solid {{ $border }};border-radius:10px;border-collapse:separate;overflow:hidden;font-size:12px">
<tr style="color:{{ $muted }};font-size:10px;text-transform:uppercase"><th align="left" style="padding:7px 9px">{{ __('System') }}</th><th align="left" style="padding:7px 9px">{{ __('Urteil') }}</th><th align="right" style="padding:7px 9px">{{ __('Konfidenz') }}</th></tr>
<tr><td style="border-top:1px solid {{ $border }};padding:8px 9px;font-weight:800">AktienKI ML</td><td style="border-top:1px solid {{ $border }};padding:8px 9px;color:#169c77;font-weight:900">BUY</td><td align="right" style="border-top:1px solid {{ $border }};padding:8px 9px">{{ is_numeric($mlConfidence) ? number_format((float) $mlConfidence, 0, ',', '.').' %' : '—' }}</td></tr>
<tr><td style="border-top:1px solid {{ $border }};padding:8px 9px;font-weight:800">GPT‑5.6 Terra</td><td style="border-top:1px solid {{ $border }};padding:8px 9px;color:{{ $verdictColor }};font-weight:900">{{ $reviewAdjustment }}</td><td align="right" style="border-top:1px solid {{ $border }};padding:8px 9px">{{ is_numeric($review?->confidence) ? (int) $review->confidence.' %' : '—' }}</td></tr>
</table>
@if($reviewStatus === 'completed')
<div style="margin-top:13px;color:{{ $text }};font-size:14px;font-weight:700;line-height:1.55">{{ $review->summary }}</div>
@if($riskFactors->isNotEmpty())
<div style="margin-top:15px;color:#c43d52;font-size:11px;font-weight:800;text-transform:uppercase">{{ __('Externe Risikofaktoren') }}</div>
<ul style="margin:7px 0 0;padding-left:20px;color:{{ $text }};font-size:13px;line-height:1.55">
@foreach($riskFactors as $factor)
<li style="margin-bottom:5px">{{ $factor }}</li>
@endforeach
</ul>
@endif
@if($positiveFactors->isNotEmpty())
<div style="margin-top:15px;color:#169c77;font-size:11px;font-weight:800;text-transform:uppercase">{{ __('Externe positive Faktoren') }}</div>
<ul style="margin:7px 0 0;padding-left:20px;color:{{ $text }};font-size:13px;line-height:1.55">
@foreach($positiveFactors as $factor)
<li style="margin-bottom:5px">{{ $factor }}</li>
@endforeach
</ul>
@endif
@if($findings->isNotEmpty())
<div style="margin-top:15px;color:#169db6;font-size:11px;font-weight:800;text-transform:uppercase">{{ __('Verifizierte Kernaussagen') }}</div>
@foreach($findings as $finding)
<div style="margin-top:7px;color:{{ $text }};font-size:13px;line-height:1.5">
{{ $finding['claim'] ?? '' }}
@foreach(collect($finding['source_urls'] ?? [])->take(2) as $url)
<a href="{{ $url }}" style="color:#169db6;text-decoration:underline">{{ __('Quelle') }}</a>@if(! $loop->last), @endif
@endforeach
</div>
@endforeach
@endif
@if($sources->isNotEmpty())
<div style="margin-top:15px;color:{{ $muted }};font-size:11px;font-weight:800;text-transform:uppercase">{{ __('Verifizierte Webquellen') }}</div>
<div style="margin-top:6px;color:{{ $muted }};font-size:12px;line-height:1.6">
@foreach($sources as $source)
<a href="{{ $source['url'] ?? '#' }}" style="color:#169db6;text-decoration:underline">{{ $source['title'] ?? $source['domain'] ?? __('Quelle') }}</a>@if(! $loop->last) · @endif
@endforeach
</div>
@endif
@elseif($reviewStatus === 'failed')
<div style="margin-top:12px;color:{{ $muted }};font-size:13px;line-height:1.55">{{ __('Der unabhängige Webcheck konnte technisch nicht abgeschlossen werden. Das BUY-Signal wurde dadurch weder bestätigt noch abgelehnt.') }}</div>
@else
<div style="margin-top:12px;color:{{ $muted }};font-size:13px;line-height:1.55">{{ __('Für dieses BUY-Signal liegt noch keine abgeschlossene externe Einschätzung vor.') }}</div>
@endif
<div style="margin-top:15px;padding-top:12px;border-top:1px solid {{ $border }};color:{{ $muted }};font-size:11px;line-height:1.5">{{ __('Shadow-Modus: Das externe Urteil verändert das ML-Signal nicht.') }} {{ __('Die externe Recherche ist keine Anlageberatung.') }}</div>
</div>
