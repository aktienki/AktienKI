@extends('layouts.aktienki')

@section('content')
<x-detail-page-theme />
<style>
    .account-setup-page .ak-detail-hero,
    .account-setup-page .ak-dashboard-card,
    .account-setup-page .ak-detail-panel { border-radius:14px !important; }
    .account-setup-page .ak-detail-hero { border-left:3px solid #22d3ee !important; }
    .account-setup-page .ak-detail-card-head { background:linear-gradient(108deg,rgba(34,211,238,.14),rgba(6, 182, 212,.06) 52%,transparent) !important; }
    .account-setup-page .account-metric { border:1px solid rgba(34,211,238,.18); background:rgba(34,211,238,.06); border-radius:10px; }
    :root[data-theme="light"] .account-setup-page .account-metric { background:rgba(255,255,255,.72); border-color:#d6e7e7; }
</style>
<div class="account-setup-page ak-detail-design mx-auto w-full max-w-screen-2xl space-y-3 py-3 text-[var(--ak-text)]">
    <header class="ak-detail-hero flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--ak-border)] p-3">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl border border-teal-400/30 bg-teal-400/10 text-teal-500"><x-heroicon-o-building-library class="h-5 w-5" /></span>
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-500">{{ __('Brokerkonten') }}</p>
                <h1 class="text-xl font-black">{{ __('Pepperstone Konten') }}</h1>
            </div>
        </div>
        <a href="{{ route('integrations.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-teal-600 px-4 text-xs font-black text-white"><x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Konten verwalten') }}</a>
    </header>

    @if(session('status'))<div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-500">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm font-bold text-rose-500">{{ $errors->first() }}</div>@endif

    <section class="grid gap-3">
        @forelse($accounts as $account)
            @php
                $isFix = data_get($account->credentials, 'connection_type', 'openapi') === 'fix';
                $connected = $account->last_connected_at && $account->last_connected_at->isAfter(now()->subHours(24));
                $hasOpenApiApp = filled(data_get($account->credentials, 'client_id')) && filled(data_get($account->credentials, 'client_secret'));
                $hasOpenApiToken = filled(data_get($account->credentials, 'access_token'));
            @endphp
            <article
                class="ak-dashboard-card ak-detail-panel overflow-hidden rounded-2xl border border-[var(--ak-border)] p-3 shadow-[var(--ak-shadow)]"
                x-data="{
                    isFix: @js($isFix), positions: [], account: null, accountError: null, loading: true, error: null, updatedAt: null, timer: null,
                    money(value) { return value == null ? '–' : Number(value).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                    async refreshPositions() {
                        try {
                            const response = await fetch(@js(route('accounts.positions', $account)), { headers: { Accept: 'application/json' } });
                            const payload = await response.json();
                            if (!response.ok) throw new Error(payload.message || 'Positionsdaten konnten nicht geladen werden.');
                            this.positions = payload.positions || [];
                            this.account = payload.account || null;
                            this.accountError = payload.account_error || null;
                            this.updatedAt = new Date(payload.updated_at).toLocaleTimeString('de-DE');
                            this.error = payload.positions_error || null;
                        } catch (error) { this.error = error.message; }
                        finally { this.loading = false; }
                    },
                    init() { if (!this.isFix) return; this.refreshPositions(); this.timer = setInterval(() => this.refreshPositions(), 10000); },
                    destroy() { if (this.timer) clearInterval(this.timer); }
                }"
            >
                <div class="ak-detail-card-head -mx-3 -mt-3 mb-2 flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[.16em] text-teal-500">Pepperstone · cTrader {{ $isFix ? 'FIX' : 'Open API' }}</p>
                        <div class="mt-0.5 flex items-baseline gap-2"><h2 class="text-base font-black">{{ $account->name }}</h2><span class="text-[10px] text-[var(--ak-muted)]">#{{ $account->external_account_id ?: '–' }}</span></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase {{ $account->environment === 'live' ? 'border-rose-400/35 bg-rose-400/10 text-rose-500' : 'border-cyan-400/35 bg-cyan-400/10 text-cyan-500' }}">{{ $account->environment }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase {{ $connected ? 'border-emerald-400/35 bg-emerald-400/10 text-emerald-500' : 'border-slate-400/25 bg-slate-400/10 text-[var(--ak-muted)]' }}"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{{ $connected ? __('Geprüft') : __('Nicht geprüft') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4 xl:grid-cols-8">
                    <div class="account-metric px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Kontostand') }}</small><b class="block text-sm" x-text="money(account?.balance)"></b></div>
                    <div class="account-metric px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Equity') }}</small><b class="block text-sm" x-text="money(account?.equity)"></b></div>
                    <div class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.05] px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Freie Margin') }}</small><b class="block text-sm text-cyan-500" x-text="money(account?.free_margin)"></b></div>
                    <div class="account-metric px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Verwendete Margin') }}</small><b class="block text-sm" x-text="money(account?.used_margin)"></b></div>
                    <div class="rounded-lg border border-emerald-400/20 bg-emerald-400/[.05] px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Gewinn') }}</small><b class="block text-sm text-emerald-500" x-text="money(account?.profit)"></b></div>
                    <div class="rounded-lg border border-rose-400/20 bg-rose-400/[.05] px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Verlust') }}</small><b class="block text-sm text-rose-500" x-text="money(account?.loss)"></b></div>
                    <div class="account-metric px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Netto P/L') }}</small><b class="block text-sm" :class="account?.net_pnl >= 0 ? 'text-emerald-500' : 'text-rose-500'" x-text="money(account?.net_pnl)"></b></div>
                    <div class="account-metric px-2.5 py-2"><small class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Positionen') }}</small><b class="block text-sm" x-text="positions.length"></b></div>
                </div>
                @if($hasOpenApiApp && !$hasOpenApiToken)
                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-cyan-400/30 bg-cyan-400/[.07] px-2.5 py-2 text-[10px] font-bold text-cyan-500"><span>{{ __('Client-Daten gespeichert – einmalige Kontofreigabe fehlt noch.') }}</span><a href="{{ route('integrations.ctrader.authorize', $account) }}" class="rounded-lg bg-cyan-600 px-3 py-1.5 text-white">{{ __('Jetzt bei cTrader autorisieren') }}</a></div>
                @else
                    <div x-show="accountError" x-cloak class="mt-1.5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-400/25 bg-amber-400/[.05] px-2.5 py-1.5 text-[10px] font-bold text-amber-500"><span x-text="accountError"></span><a href="{{ route('integrations.index') }}" class="underline">{{ __('Open API prüfen') }}</a></div>
                @endif

                @if($isFix)
                <section class="mt-2 overflow-hidden rounded-xl border border-[var(--ak-border)]">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2">
                        <div><p class="text-[9px] font-black uppercase tracking-[.14em] text-teal-500">{{ __('Live vom Broker') }}</p><h3 class="text-sm font-black">{{ __('Offene Positionen') }}</h3></div>
                        <div class="flex items-center gap-2 text-[10px] text-[var(--ak-muted)]"><i class="h-2 w-2 rounded-full" :class="error ? 'bg-rose-500' : 'animate-pulse bg-emerald-500'"></i><span x-text="updatedAt ? 'Aktualisiert ' + updatedAt : 'Wird geladen …'"></span><button type="button" @click="refreshPositions()" class="rounded-lg border border-[var(--ak-border)] px-2 py-1 font-black text-teal-500">{{ __('Jetzt') }}</button></div>
                    </div>
                    <div x-show="loading" class="px-3 py-3 text-center text-xs text-[var(--ak-muted)]">{{ __('Positionen werden sicher bei Pepperstone abgerufen …') }}</div>
                    <div x-show="!loading && error" x-cloak class="border-l-2 border-rose-500 bg-rose-500/[.06] px-3 py-3 text-xs font-bold text-rose-500" x-text="error"></div>
                    <div x-show="!loading && !error && positions.length === 0" x-cloak class="px-3 py-3 text-center text-xs text-[var(--ak-muted)]">{{ __('Keine offenen Positionen vorhanden.') }}</div>
                    <div x-show="!loading && !error && positions.length" x-cloak class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-xs">
                            <thead class="text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><tr><th class="px-3 py-1.5">{{ __('Position') }}</th><th class="px-3 py-1.5">{{ __('FIX Symbol-ID') }}</th><th class="px-3 py-1.5">{{ __('Richtung') }}</th><th class="px-3 py-1.5 text-right">{{ __('Menge') }}</th><th class="px-3 py-1.5 text-right">{{ __('Einstand') }}</th></tr></thead>
                            <tbody><template x-for="position in positions" :key="position.position_id"><tr class="border-t border-[var(--ak-border)]"><td class="px-3 py-1.5 font-bold" x-text="position.position_id || '–'"></td><td class="px-3 py-1.5 font-black text-teal-500" x-text="position.symbol_id || '–'"></td><td class="px-3 py-1.5"><span class="rounded-md px-2 py-0.5 text-[8px] font-black uppercase" :class="position.side === 'buy' ? 'bg-emerald-400/10 text-emerald-500' : 'bg-rose-400/10 text-rose-500'" x-text="position.side === 'buy' ? 'BUY' : 'SELL'"></span></td><td class="px-3 py-1.5 text-right font-bold" x-text="Number(position.quantity).toLocaleString('de-DE')"></td><td class="px-3 py-1.5 text-right font-bold" x-text="position.average_price == null ? '–' : Number(position.average_price).toLocaleString('de-DE', { maximumFractionDigits: 6 })"></td></tr></template></tbody>
                        </table>
                    </div>
                    <p class="border-t border-[var(--ak-border)] px-3 py-2 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Aktualisierung alle 10 Sekunden. FIX liefert offene Positionen und Einstandsdaten; Kontostand, freie Margin und laufender Gewinn/Verlust benötigen ergänzend die cTrader Open API.') }}</p>
                </section>
                @else
                <div class="mt-4 rounded-xl border border-amber-400/25 bg-amber-400/[.05] px-3 py-3 text-xs text-amber-500">{{ __('Für dieses Open-API-Konto müssen zuerst OAuth-Autorisierung und Konto-ID vollständig eingerichtet sein, bevor Live-Positionen angezeigt werden können.') }}</div>
                @endif

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('integrations.broker.test', $account) }}">@csrf<button class="h-9 rounded-lg border border-teal-400/30 bg-teal-400/10 px-3 text-[10px] font-black text-teal-500">{{ __('Verbindung testen') }}</button></form>
                    <a href="{{ route('integrations.index') }}" class="inline-flex h-9 items-center rounded-lg border border-amber-400/30 bg-amber-400/10 px-3 text-[10px] font-black text-amber-500">{{ __('Einstellungen öffnen') }}</a>
                    <span class="ml-auto text-[10px] text-[var(--ak-muted)]">{{ __('Letzte Prüfung') }}: {{ $account->last_connected_at?->format('d.m.Y · H:i') ?? '–' }}</span>
                </div>

                @if($account->orders->isNotEmpty())
                    <details class="mt-2 overflow-hidden rounded-xl border border-[var(--ak-border)]">
                        <summary class="cursor-pointer bg-[var(--ak-surface-muted)] px-3 py-2 text-[9px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Letzte Orders') }} · {{ $account->orders_count }}</summary>
                        @foreach($account->orders as $order)
                            <div class="grid grid-cols-[1fr_auto_auto] items-center gap-3 border-b border-[var(--ak-border)] px-3 py-2 text-xs last:border-0">
                                <div><b>{{ $order->symbol }}</b><span class="ml-2 text-[var(--ak-muted)]">{{ strtoupper($order->side) }} · {{ $order->order_type }}</span></div>
                                <span class="font-bold {{ $order->status === 'submitted' ? 'text-emerald-500' : ($order->status === 'failed' ? 'text-rose-500' : 'text-amber-500') }}">{{ $order->status }}</span>
                                <time class="text-[10px] text-[var(--ak-muted)]">{{ $order->created_at->format('d.m. H:i') }}</time>
                            </div>
                        @endforeach
                    </details>
                @endif
            </article>
        @empty
            <div class="ak-detail-panel rounded-2xl border border-dashed border-[var(--ak-border)] p-10 text-center xl:col-span-2">
                <x-heroicon-o-building-library class="mx-auto h-9 w-9 text-teal-500" />
                <h2 class="mt-3 font-black">{{ __('Noch kein Pepperstone-Konto verbunden') }}</h2>
                <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Richte zuerst eine Pepperstone-cTrader-Verbindung ein.') }}</p>
                <a href="{{ route('integrations.index') }}" class="mt-4 inline-flex h-10 items-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white">{{ __('Pepperstone verbinden') }}</a>
            </div>
        @endforelse
    </section>
</div>
@endsection
