@extends('layouts.aktienki')

@section('content')
<x-detail-page-theme />
<div class="ak-detail-design mx-auto w-full max-w-screen-2xl space-y-4 py-4 text-[var(--ak-text)]" x-data="{ brokerOpen: false }">
    <header class="ak-detail-hero flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[var(--ak-border)] p-5">
        <div class="flex items-center gap-3"><span class="grid h-12 w-12 place-items-center rounded-2xl border border-teal-400/30 bg-teal-400/10 text-teal-500"><x-heroicon-o-link class="h-6 w-6" /></span><div><p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-500">{{ __('Trading & Nachrichten') }}</p><h1 class="mt-1 text-2xl font-black">{{ __('Integrationen') }}</h1><p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Pepperstone cTrader, eToro und WhatsApp sicher verbinden.') }}</p></div></div>
        <button type="button" @click="brokerOpen=!brokerOpen" class="inline-flex h-10 items-center gap-2 rounded-xl bg-teal-600 px-4 text-xs font-black text-white"><x-heroicon-o-plus class="h-4 w-4" />{{ __('Broker einrichten') }}</button>
    </header>

    @if(session('status'))<div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-500">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm font-bold text-rose-500">{{ $errors->first() }}</div>@endif

    <section x-show="brokerOpen || {{ $connections->isEmpty() ? 'true' : 'false' }}" class="ak-detail-panel overflow-hidden rounded-2xl border border-[var(--ak-border)] p-5">
        <div class="ak-detail-card-head -mx-5 -mt-5 mb-4 px-5 py-4"><h2 class="font-black">{{ __('Neues Brokerkonto verbinden') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Broker auswählen und anschließend die Angaben aus den Kontoeinstellungen des Brokers übernehmen.') }}</p></div>
        <form method="POST" action="{{ route('integrations.broker.store') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" x-data="{ provider: 'pepperstone_ctrader', connectionType: 'openapi' }">@csrf
            <label><span class="ak-label">{{ __('Broker') }}</span><select name="provider" x-model="provider" class="ak-input mt-1" required><option value="pepperstone_ctrader">Pepperstone · cTrader</option><option value="etoro">eToro</option></select></label>
            <label><span class="ak-label">{{ __('Umgebung') }}</span><select name="environment" class="ak-input mt-1" required><option value="demo">Demo</option><option value="live">Live</option></select></label>
            <label x-show="provider === 'pepperstone_ctrader'"><span class="ak-label">{{ __('Verbindungstyp') }}</span><select name="connection_type" x-model="connectionType" class="ak-input mt-1"><option value="openapi">cTrader Open API</option><option value="fix">cTrader FIX API</option></select></label>
            <label><span class="ak-label">{{ __('Kontoname in AktienKI') }}</span><input name="name" class="ak-input mt-1" required placeholder="z. B. Pepperstone Demokonto"></label>
            <label><span class="ak-label" x-text="provider === 'etoro' ? 'eToro Konto-ID' : 'cTrader Kontonummer'">{{ __('cTrader Kontonummer') }}</span><input name="external_account_id" class="ak-input mt-1" inputmode="numeric" placeholder="z. B. 4263622"></label>
            <label x-show="provider === 'etoro'"><span class="ak-label">eToro API Key</span><input name="api_key" type="password" autocomplete="new-password" class="ak-input mt-1"></label>
            <label x-show="provider === 'etoro'"><span class="ak-label">eToro User Key</span><input name="user_key" type="password" autocomplete="new-password" class="ak-input mt-1"></label>
            <label x-show="provider === 'pepperstone_ctrader'"><span class="ak-label">cTrader Open API · Client ID</span><input name="client_id" type="password" autocomplete="new-password" class="ak-input mt-1"></label>
            <label x-show="provider === 'pepperstone_ctrader'"><span class="ak-label">cTrader Open API · Client Secret</span><input name="client_secret" type="password" autocomplete="new-password" class="ak-input mt-1"></label>
            <div x-show="provider === 'pepperstone_ctrader' && connectionType === 'fix'" class="grid gap-3 rounded-xl border border-cyan-500/20 bg-cyan-500/[.045] p-3 md:col-span-2 xl:col-span-4 md:grid-cols-2 xl:grid-cols-4">
                <label><span class="ak-label">{{ __('Server / Hostname') }}</span><input name="fix_host" class="ak-input mt-1" value="demo-uk-eqx-01.p.c-trader.com"></label>
                <label><span class="ak-label">{{ __('Preisverbindung · SSL-Port') }}</span><input name="fix_quote_port" type="number" class="ak-input mt-1" value="5211"></label>
                <label><span class="ak-label">{{ __('Handelsverbindung · SSL-Port') }}</span><input name="fix_trade_port" type="number" class="ak-input mt-1" value="5212"></label>
                <label><span class="ak-label">{{ __('Konto-Verbindungskennung (SenderCompID)') }}</span><input name="fix_sender_comp_id" class="ak-input mt-1" placeholder="demo.pepperstoneuk.4263622"></label>
                <label><span class="ak-label">{{ __('Broker-Serverkennung (TargetCompID)') }}</span><input name="fix_target_comp_id" class="ak-input mt-1" value="cServer"></label>
                <label><span class="ak-label">{{ __('Preis-Kanal (SenderSubID)') }}</span><input name="fix_quote_sender_sub_id" class="ak-input mt-1" value="QUOTE"></label>
                <label><span class="ak-label">{{ __('Handels-Kanal (SenderSubID)') }}</span><input name="fix_trade_sender_sub_id" class="ak-input mt-1" value="TRADE"></label>
                <label><span class="ak-label">{{ __('FIX-Passwort für dieses cTrader-Konto') }}</span><input name="fix_password" type="password" autocomplete="new-password" class="ak-input mt-1" placeholder="{{ __('Passwort aus dem FIX-API-Dialog') }}"></label>
                <p class="text-[10px] leading-4 text-[var(--ak-muted)] md:col-span-2 xl:col-span-4">{{ __('Kein Browser-Login erforderlich: Konto 4263622 entspricht dem FIX Username. Verwende das Passwort, das im cTrader-FIX-Dialog über „Passwort ändern“ festgelegt wird. Kennungen wie cServer werden exakt übernommen.') }}</p>
            </div>
            <label><span class="ak-label">{{ __('Max. Orderwert') }}</span><input name="max_order_value" type="number" min="1" step="0.01" value="100" class="ak-input mt-1" required></label>
            <label><span class="ak-label">{{ __('Tagesverlustlimit') }}</span><input name="daily_loss_limit" type="number" min="1" step="0.01" value="100" class="ak-input mt-1" required></label>
            <label class="flex items-center gap-2 pt-6"><input type="checkbox" name="trading_enabled" value="1" class="h-4 w-4 accent-teal-500"><b class="text-xs">{{ __('Orderausführung erlauben') }}</b></label>
            <label class="flex items-center gap-2 pt-6"><input type="hidden" name="emergency_stop" value="0"><input type="checkbox" name="emergency_stop" value="1" checked class="h-4 w-4 accent-rose-500"><b class="text-xs text-rose-500">{{ __('Not-Aus aktiv') }}</b></label>
            <button class="h-11 rounded-xl bg-teal-600 px-5 text-xs font-black text-white md:col-span-2 xl:col-span-4">{{ __('Verbindung sicher speichern') }}</button>
        </form>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        @forelse($connections as $connection)
        <article class="ak-detail-panel overflow-hidden rounded-2xl border border-[var(--ak-border)] p-5" x-data="{ editOpen: false }">
            <div class="ak-detail-card-head -mx-5 -mt-5 mb-4 flex items-center justify-between gap-3 px-5 py-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-teal-500">{{ $connection->provider === 'etoro' ? 'eToro' : 'Pepperstone · cTrader · '.strtoupper(data_get($connection->credentials, 'connection_type', 'openapi')) }}</p><h2 class="mt-1 font-black">{{ $connection->name }}</h2></div><span class="rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase {{ $connection->environment === 'live' ? 'border-rose-400/30 bg-rose-400/10 text-rose-500' : 'border-teal-400/30 bg-teal-400/10 text-teal-500' }}">{{ $connection->environment }}</span></div>
            <div class="grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-[var(--ak-surface-muted)] p-2"><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Trading') }}</small><b class="block text-xs {{ $connection->trading_enabled ? 'text-emerald-500' : 'text-slate-500' }}">{{ $connection->trading_enabled ? __('Aktiv') : __('Aus') }}</b></div><div class="rounded-xl bg-[var(--ak-surface-muted)] p-2"><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Not-Aus') }}</small><b class="block text-xs {{ $connection->emergency_stop ? 'text-rose-500' : 'text-emerald-500' }}">{{ $connection->emergency_stop ? __('Aktiv') : __('Aus') }}</b></div><div class="rounded-xl bg-[var(--ak-surface-muted)] p-2"><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Orderlimit') }}</small><b class="block text-xs">{{ number_format($connection->max_order_value, 2, ',', '.') }}</b></div></div>
            <div class="mt-3 flex flex-wrap gap-2"><button type="button" @click="editOpen=!editOpen" class="h-9 rounded-lg border border-amber-400/30 bg-amber-400/10 px-3 text-[10px] font-black text-amber-500">{{ __('Konto verwalten') }}</button><form method="POST" action="{{ route('integrations.broker.test',$connection) }}">@csrf<button class="h-9 rounded-lg border border-teal-400/30 bg-teal-400/10 px-3 text-[10px] font-black text-teal-500">{{ __('Verbindung testen') }}</button></form>@if($connection->provider==='pepperstone_ctrader')@if(filled(data_get($connection->credentials,'client_id')) && filled(data_get($connection->credentials,'client_secret')))<a href="{{ route('integrations.ctrader.authorize',$connection) }}" class="inline-flex h-9 items-center rounded-lg border border-cyan-400/30 bg-cyan-400/10 px-3 text-[10px] font-black text-cyan-500">{{ data_get($connection->credentials, 'connection_type', 'openapi') === 'fix' ? __('Live-Kontodaten autorisieren') : __('cTrader autorisieren') }}</a>@else<button type="button" @click="editOpen=true" class="h-9 rounded-lg border border-cyan-400/25 bg-cyan-400/[.06] px-3 text-[10px] font-black text-cyan-500">{{ __('Open API Zugangsdaten eintragen') }}</button>@endif @endif</div>

            <div x-show="editOpen" x-cloak class="mt-4 rounded-xl border border-amber-400/25 bg-amber-400/[.04] p-3">
                <form method="POST" action="{{ route('integrations.broker.store') }}" class="grid gap-2 md:grid-cols-2">@csrf
                    <input type="hidden" name="connection_id" value="{{ $connection->id }}">
                    <input type="hidden" name="provider" value="{{ $connection->provider }}">
                    <label><span class="ak-label">{{ __('Broker') }}</span><select class="ak-input mt-1 opacity-75" disabled><option>{{ $connection->provider==='etoro' ? 'eToro' : 'Pepperstone · cTrader' }}</option></select></label>
                    <label><span class="ak-label">{{ __('Kontoumgebung') }}</span><select name="environment" class="ak-input mt-1"><option value="demo" @selected($connection->environment==='demo')>Demo</option><option value="live" @selected($connection->environment==='live')>Live</option></select></label>
                    <label><span class="ak-label">{{ __('Kontoname in AktienKI') }}</span><input name="name" value="{{ $connection->name }}" class="ak-input mt-1" required></label>
                    <label><span class="ak-label">{{ $connection->provider==='etoro' ? __('eToro Konto-ID') : __('cTrader Kontonummer') }}</span><input name="external_account_id" value="{{ $connection->external_account_id }}" class="ak-input mt-1"></label>
                    @if($connection->provider==='pepperstone_ctrader')
                    <input type="hidden" name="connection_type" value="{{ data_get($connection->credentials,'connection_type','openapi') }}">
                    @if(data_get($connection->credentials,'connection_type','openapi') === 'fix')
                    <label><span class="ak-label">{{ __('Server / Hostname') }}</span><input name="fix_host" value="{{ data_get($connection->credentials,'fix_host') }}" class="ak-input mt-1"></label>
                    <label><span class="ak-label">{{ __('Konto-Verbindungskennung') }}</span><input name="fix_sender_comp_id" value="{{ data_get($connection->credentials,'fix_sender_comp_id') }}" class="ak-input mt-1"></label>
                    @foreach(['fix_quote_port'=>__('Preis-Port'),'fix_trade_port'=>__('Handels-Port'),'fix_target_comp_id'=>'TargetCompID','fix_quote_sender_sub_id'=>'QUOTE SenderSubID','fix_trade_sender_sub_id'=>'TRADE SenderSubID'] as $field=>$label)
                    <label><span class="ak-label">{{ $label }}</span><input name="{{ $field }}" value="{{ data_get($connection->credentials,$field) }}" class="ak-input mt-1"></label>
                    @endforeach
                    <label><span class="ak-label">{{ __('Neues FIX-API-Passwort') }}</span><input name="fix_password" type="password" autocomplete="new-password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    <label><span class="ak-label">{{ __('Open API Client-ID') }}</span><input name="client_id" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    <label><span class="ak-label">{{ __('Open API Client Secret') }}</span><input name="client_secret" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    @else
                    <label><span class="ak-label">{{ __('Neue Client-ID') }}</span><input name="client_id" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    <label><span class="ak-label">{{ __('Neues Client Secret') }}</span><input name="client_secret" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    @endif
                    @else
                    <label><span class="ak-label">{{ __('Neuer eToro API Key') }}</span><input name="api_key" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    <label><span class="ak-label">{{ __('Neuer eToro User Key') }}</span><input name="user_key" type="password" class="ak-input mt-1" placeholder="{{ __('Leer lassen = unverändert') }}"></label>
                    @endif
                    <label><span class="ak-label">{{ __('Max. Orderwert') }}</span><input name="max_order_value" type="number" min="1" step="0.01" value="{{ $connection->max_order_value }}" class="ak-input mt-1" required></label>
                    <label><span class="ak-label">{{ __('Tagesverlustlimit') }}</span><input name="daily_loss_limit" type="number" min="1" step="0.01" value="{{ $connection->daily_loss_limit }}" class="ak-input mt-1" required></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="trading_enabled" value="1" @checked($connection->trading_enabled) class="h-4 w-4 accent-teal-500"><b class="text-xs">{{ __('Orderausführung erlauben') }}</b></label>
                    <label class="flex items-center gap-2"><input type="hidden" name="emergency_stop" value="0"><input type="checkbox" name="emergency_stop" value="1" @checked($connection->emergency_stop) class="h-4 w-4 accent-rose-500"><b class="text-xs text-rose-500">{{ __('Not-Aus aktiv') }}</b></label>
                    <button class="h-10 rounded-lg bg-teal-600 text-xs font-black text-white md:col-span-2">{{ __('Kontoänderungen speichern') }}</button>
                </form>
                <form method="POST" action="{{ route('integrations.broker.destroy',$connection) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf @method('DELETE')
                    <label class="min-w-52 flex-1"><span class="ak-label">{{ __('Zum Löschen „KONTO LÖSCHEN“ eingeben') }}</span><input name="confirmation" class="ak-input mt-1" autocomplete="off"></label>
                    <button class="h-10 rounded-lg border border-rose-400/35 bg-rose-400/10 px-4 text-xs font-black text-rose-500" onclick="return confirm(@js(__('Kontoverbindung und zugehörige Orderhistorie endgültig löschen?')))">{{ __('Konto löschen') }}</button>
                </form>
            </div>

            <form method="POST" action="{{ route('integrations.orders.store',$connection) }}" class="mt-4 grid grid-cols-2 gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">@csrf
                <p class="col-span-2 text-[9px] font-black uppercase tracking-[.14em] text-amber-500">{{ __('Manuelle Order') }}</p>
                <input name="symbol" class="ak-input" required placeholder="{{ $connection->provider==='etoro' ? 'AAPL' : (data_get($connection->credentials, 'connection_type', 'openapi') === 'fix' ? 'FIX-Symbol' : 'cTrader Symbol-ID') }}"><select name="side" class="ak-input"><option value="buy">BUY</option><option value="sell">SELL</option></select>
                <select name="order_type" class="ak-input"><option value="market">Market</option><option value="limit">Limit</option><option value="stop">Stop</option></select><input name="currency" value="USD" maxlength="3" class="ak-input">
                <input name="amount" type="number" min="0.01" step="0.01" class="ak-input" placeholder="{{ data_get($connection->credentials, 'connection_type', 'openapi') === 'fix' ? __('Geschätzter Orderwert (Limitprüfung)') : __('Betrag (eToro)') }}"><input name="quantity" type="number" min="0.01" step="0.01" class="ak-input" placeholder="{{ __('Einheiten (cTrader)') }}">
                <input name="limit_price" type="number" min="0" step="any" class="ak-input" placeholder="Limit"><input name="stop_loss" type="number" min="0" step="any" class="ak-input" placeholder="Stop Loss">
                <input name="take_profit" type="number" min="0" step="any" class="ak-input" placeholder="Take Profit"><input name="confirmation" class="ak-input" required placeholder="{{ $connection->environment==='live' ? 'LIVE ORDER' : 'DEMO ORDER' }}">
                <button class="col-span-2 h-10 rounded-xl bg-gradient-to-r from-amber-500 to-rose-500 text-xs font-black text-white" onclick="return confirm(@js(__('Diese Order jetzt an den Broker senden?')))" @disabled(!$connection->trading_enabled || $connection->emergency_stop)>{{ __('Order verbindlich senden') }}</button>
            </form>
        </article>
        @empty<div class="ak-detail-panel rounded-2xl border border-dashed border-[var(--ak-border)] p-10 text-center text-sm text-[var(--ak-muted)] xl:col-span-2">{{ __('Noch keine Brokerverbindung eingerichtet.') }}</div>@endforelse
    </section>

    <section class="ak-detail-panel overflow-hidden rounded-2xl border border-[var(--ak-border)] p-5">
        <div class="ak-detail-card-head -mx-5 -mt-5 mb-4 px-5 py-4"><p class="text-[9px] font-black uppercase tracking-[.15em] text-teal-500">Meta Cloud API</p><h2 class="mt-1 font-black">WhatsApp</h2></div>
        <form method="POST" action="{{ route('integrations.whatsapp.store') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">@csrf
            <label><span class="ak-label">Access Token</span><input type="password" name="access_token" class="ak-input mt-1" autocomplete="new-password" placeholder="{{ data_get($whatsapp->credentials,'access_token') ? __('Gespeichert') : '' }}"></label>
            <label><span class="ak-label">Phone Number ID</span><input name="phone_number_id" class="ak-input mt-1" placeholder="{{ data_get($whatsapp->credentials,'phone_number_id') ? __('Gespeichert') : '' }}"></label>
            <label><span class="ak-label">{{ __('Empfänger mit Ländervorwahl') }}</span><input name="recipient" value="{{ $whatsapp->recipient }}" class="ak-input mt-1" required placeholder="491701234567"></label>
            <label class="flex items-center gap-2 pt-6"><input type="checkbox" name="enabled" value="1" @checked($whatsapp->enabled) class="h-4 w-4 accent-teal-500"><b class="text-xs">{{ __('Benachrichtigungen aktiv') }}</b></label>
            <button class="h-10 rounded-xl bg-teal-600 px-4 text-xs font-black text-white">{{ __('WhatsApp speichern') }}</button>
        </form>
        @if($whatsapp->exists)<form method="POST" action="{{ route('integrations.whatsapp.test') }}" class="mt-2">@csrf<button class="h-9 rounded-lg border border-teal-400/30 bg-teal-400/10 px-3 text-[10px] font-black text-teal-500">{{ __('Testnachricht senden') }}</button></form>@endif
    </section>
</div>
@endsection
