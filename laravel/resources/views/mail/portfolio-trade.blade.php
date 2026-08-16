<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#07111f;color:#edf6f7;font-family:Inter,Arial,sans-serif;padding:28px 12px">
@php
    $accent = $isSale ? '#f08a8a' : '#22d3ee';
    $soft = $isSale ? 'rgba(240,138,138,.13)' : 'rgba(34, 211, 238,.13)';
    $actionLabel = $isSale ? __('Verkauf ausgeführt') : ($trade['action'] === 'increase' ? __('Position aufgestockt') : __('Kauf ausgeführt'));
    if ($trade['simulation'] ?? false) $actionLabel = __('Simulation') . ' · ' . $actionLabel;
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#101f33;border:1px solid #263d55;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.35)">
    <tr><td style="padding:18px 26px;border-bottom:1px solid #29475e;background:#0b192b">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
            <td valign="middle" style="width:47%"><img src="cid:aktienki-logo.png" width="205" alt="aktienKI.com" style="display:block;width:205px;max-width:100%;height:auto"></td>
            <td align="right" valign="middle" style="padding-left:16px"><div style="color:#64e5f2;font-size:10px;line-height:1.2;font-weight:800;letter-spacing:1.7px;text-transform:uppercase">AKTIENANALYSE</div><div style="margin-top:5px;color:#a9bac9;font-size:11px;line-height:1.35">Machine Learning · Klare Signale</div></td>
        </tr></table>
    </td></tr>
    <tr><td style="padding:30px">
        <span style="display:inline-block;padding:7px 12px;border-radius:7px;background:{{ $soft }};border:1px solid {{ $accent }};color:{{ $accent }};font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase">{{ $actionLabel }}</span>
        <h1 style="margin:18px 0 5px;font-size:30px;line-height:1.15;color:#f7fbfc">{{ $trade['instrument_name'] }}</h1>
        <div style="color:#e5b95d;font-size:15px;font-weight:800">{{ $trade['symbol'] }} · {{ $trade['sector'] }}</div>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:25px;border-collapse:separate;border-spacing:8px">
            <tr>
                <td style="width:33%;padding:15px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:11px;text-transform:uppercase">{{ __('Kurs') }}<div style="margin-top:7px;color:#f7fbfc;font-size:19px;font-weight:800">{{ number_format($trade['price'], 2, ',', '.') }} {{ $trade['currency'] }}</div></td>
                <td style="width:33%;padding:15px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:11px;text-transform:uppercase">{{ __('Stückzahl') }}<div style="margin-top:7px;color:#f7fbfc;font-size:19px;font-weight:800">{{ number_format(round($trade['quantity']), 0, ',', '.') }}</div></td>
                <td style="width:33%;padding:15px;background:#152943;border:1px solid #29445e;border-radius:10px;color:#91a8bb;font-size:11px;text-transform:uppercase">{{ __('Volumen') }}<div style="margin-top:7px;color:{{ $accent }};font-size:19px;font-weight:800">{{ number_format($trade['allocated_capital'], 2, ',', '.') }} {{ $trade['currency'] }}</div></td>
            </tr>
        </table>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;background:#0d1b2d;border:1px solid #29445e;border-radius:12px">
            <tr><td style="padding:14px 18px;color:#91a8bb">{{ __('Depot') }}</td><td align="right" style="padding:14px 18px;color:#edf6f7;font-weight:700">{{ $trade['portfolio_name'] }}</td></tr>
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Strategie') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ $trade['strategy_name'] }}</td></tr>
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Positionsberechnung') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ number_format($trade['base_position_capital'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }} × {{ $trade['position_factor'] }} = {{ number_format($trade['target_position_capital'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }}<div style="margin-top:4px;color:#e5b95d;font-size:12px">{{ number_format($trade['target_position_capital'], 2, ',', '.') }} ÷ {{ number_format($trade['price'], 2, ',', '.') }} = {{ number_format(round($trade['quantity']), 0, ',', '.') }} {{ __('Stück') }}</div></td></tr>
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Rotationsregeln') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ __('Sektorrotation') }}: {{ $trade['sector_rotation_enabled'] ? __('aktiv') : __('aus') }} · {{ __('Indexrotation') }}: {{ $trade['index_rotation_enabled'] ? __('aktiv') : __('aus') }}@if($trade['sector_rotation_enabled'] && $trade['sector_average_score'] !== null)<div style="margin-top:4px;color:#e5b95d;font-size:12px">{{ __('Sektor-Score') }} {{ number_format($trade['sector_average_score'], 2, ',', '.') }} / 10</div>@endif @if($trade['index_rotation_enabled'] && $trade['index_average_score'] !== null)<div style="margin-top:4px;color:#e5b95d;font-size:12px">{{ __('Index-Score') }} {{ number_format($trade['index_average_score'], 2, ',', '.') }} / 10</div>@endif</td></tr>
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('KI-Score') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ number_format($trade['score'], 1, ',', '.') }} / 10</td></tr>
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Modellqualität') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ number_format($trade['confidence'], 1, ',', '.') }} %</td></tr>
            @if($trade['target_price'])
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Zielkurs 20 Tage') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ number_format($trade['target_price'], 2, ',', '.') }} {{ $trade['currency'] }} @if($trade['expected_return'] !== null)<span style="color:{{ $trade['expected_return'] >= 0 ? '#22d3ee' : '#f08a8a' }}">({{ $trade['expected_return'] >= 0 ? '+' : '' }}{{ number_format($trade['expected_return'], 2, ',', '.') }} %)</span>@endif</td></tr>
            @endif
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Gebühren') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:#edf6f7;font-weight:700">{{ number_format($trade['fees'], 2, ',', '.') }} {{ $trade['currency'] }}</td></tr>
            @if($isSale && isset($trade['realized_profit']))
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Realisiertes Ergebnis') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:{{ $trade['realized_profit'] >= 0 ? '#22d3ee' : '#f08a8a' }};font-size:17px;font-weight:800">{{ $trade['realized_profit'] >= 0 ? '+' : '' }}{{ number_format($trade['realized_profit'], 2, ',', '.') }} {{ $trade['currency'] }}</td></tr>
            @endif
            @if($isSale && isset($trade['transaction_performance_percent']))
            <tr><td style="padding:14px 18px;border-top:1px solid #203850;color:#91a8bb">{{ __('Performance der Transaktion') }}</td><td align="right" style="padding:14px 18px;border-top:1px solid #203850;color:{{ $trade['transaction_performance_percent'] >= 0 ? '#22d3ee' : '#f08a8a' }};font-size:17px;font-weight:800">{{ $trade['transaction_performance_percent'] >= 0 ? '+' : '' }}{{ number_format($trade['transaction_performance_percent'], 2, ',', '.') }} %</td></tr>
            @endif
        </table>

        @if(isset($trade['portfolio_value'], $trade['cash_balance'], $trade['total_value']))
        <div style="margin-top:22px;color:#d8e6ed;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase">{{ __('Depotübersicht') }}</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;border-collapse:separate;border-spacing:6px">
            <tr>
                <td style="width:25%;padding:13px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px;text-transform:uppercase">{{ __('Depotwert') }}<div style="margin-top:6px;color:#f7fbfc;font-size:16px;font-weight:800">{{ number_format($trade['portfolio_value'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }}</div></td>
                <td style="width:25%;padding:13px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px;text-transform:uppercase">{{ __('Kontostand') }}<div style="margin-top:6px;color:#f7fbfc;font-size:16px;font-weight:800">{{ number_format($trade['cash_balance'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }}</div></td>
                <td style="width:25%;padding:13px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px;text-transform:uppercase">{{ __('Gesamtwert') }}<div style="margin-top:6px;color:#e5b95d;font-size:16px;font-weight:800">{{ number_format($trade['total_value'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }}</div></td>
                <td style="width:25%;padding:13px;background:#152943;border:1px solid #29445e;border-radius:9px;color:#91a8bb;font-size:10px;text-transform:uppercase">{{ __('Performance') }}<div style="margin-top:6px;color:{{ $trade['performance_percent'] >= 0 ? '#22d3ee' : '#f08a8a' }};font-size:16px;font-weight:800">{{ $trade['performance_percent'] >= 0 ? '+' : '' }}{{ number_format($trade['performance_percent'], 2, ',', '.') }} %</div></td>
            </tr>
        </table>
        @endif

        @if(!empty($trade['holdings']))
        <div style="margin-top:22px;color:#d8e6ed;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase">{{ __('Depotbestand') }}</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;background:#0d1b2d;border:1px solid #29445e;border-radius:12px">
            @foreach($trade['holdings'] as $holding)
            <tr>
                    <td style="padding:12px 16px;{{ !$loop->first ? 'border-top:1px solid #203850;' : '' }}"><strong style="color:#f7fbfc">{{ $holding['name'] }}</strong><div style="margin-top:3px;color:#e5b95d;font-size:11px;font-weight:700">{{ $holding['symbol'] }} · {{ number_format(round($holding['quantity']), 0, ',', '.') }} {{ __('Stück') }}</div></td>
                <td align="right" style="padding:12px 16px;{{ !$loop->first ? 'border-top:1px solid #203850;' : '' }}color:#edf6f7;font-weight:800">{{ number_format($holding['value'], 2, ',', '.') }} {{ $trade['portfolio_currency'] }}</td>
            </tr>
            @endforeach
        </table>
        @endif

        <div style="text-align:center;margin:28px 0 8px"><a href="{{ $depotUrl }}" style="display:inline-block;padding:13px 24px;background:linear-gradient(135deg,#0f9f95,#277fa4);border-radius:8px;color:#fff;text-decoration:none;font-weight:800">{{ __('Musterdepot öffnen') }}</a></div>
    </td></tr>
    <tr><td style="padding:20px 30px;background:#0a1728;border-top:1px solid #263d55;color:#7f96a9;font-size:11px;line-height:1.6">
        @if($trade['simulation'] ?? false)
            {{ __('Historische Simulation für den :date. Es wurde keine reale Depotbuchung ausgeführt.', ['date' => \Illuminate\Support\Carbon::parse($trade['transaction_date'])->format('d.m.Y')]) }}<br>
        @else
            {{ __('Automatisch ausgeführt am :date. Die Darstellung bezieht sich auf ein virtuelles Musterdepot.', ['date' => \Illuminate\Support\Carbon::parse($trade['transaction_date'])->format('d.m.Y')]) }}<br>
        @endif
        {{ __('Keine Anlageberatung. Prognosen können fehlerhaft sein; Verluste sind jederzeit möglich.') }}
    </td></tr>
</table>
</body></html>
