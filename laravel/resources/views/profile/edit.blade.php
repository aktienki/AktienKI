@extends('layouts.aktienki')

@section('content')
    <x-detail-page-theme />
    @php
        $preferences = $user->preferences ?? [];
        $selectedLocale = old('locale', $preferences['locale'] ?? app()->getLocale());
        $selectedRiskLevel = old('risk_level', data_get($user->meta, 'risk_profile.level', 'normal'));
    @endphp

    <div class="ak-detail-design mx-auto w-full max-w-6xl py-6">
        <div class="ak-detail-hero mb-6 flex flex-col gap-5 rounded-[1.5rem] border border-[var(--ak-border)] p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-teal-400/30 bg-teal-400/10 text-teal-500"><x-heroicon-o-user-circle class="h-7 w-7" /></span>
                <div>
                <p class="text-xs font-black uppercase tracking-[.2em] text-teal-500">{{ __('Persönlicher Bereich') }}</p>
                <h1 class="mt-2 text-3xl font-black text-[var(--ak-text)]">{{ __('Profil & Einstellungen') }}</h1>
                <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Verwalte dein Konto und bestimme, wie AktienKI dich informiert.') }}</p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a href="{{ route('integrations.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-teal-400/30 bg-teal-400/10 px-4 text-xs font-black text-teal-600"><x-heroicon-o-link class="h-4 w-4" />{{ __('Broker & WhatsApp') }}</a>
                @if (session('status') === 'profile-updated')
                <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-400">{{ __('Einstellungen gespeichert') }}</div>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="grid gap-5 lg:grid-cols-[1fr_1fr]">
            @csrf
            @method('patch')
            @if (old('return_to', request('return_to')))
                <input type="hidden" name="return_to" value="{{ old('return_to', request('return_to')) }}">
            @endif

            <section class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                <div class="ak-detail-card-head -mx-6 -mt-6 flex items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-teal-400/25 bg-teal-500/10 text-teal-500"><x-heroicon-o-user class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Kontodaten') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Deine persönlichen Zugangsdaten') }}</p></div>
                </div>

                <div class="mt-6 space-y-4">
                    <div><label for="name" class="ak-label">{{ __('Name') }}</label><input id="name" class="ak-input mt-2" name="name" type="text" value="{{ old('name', $user->name) }}" required>@error('name')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="ak-label">{{ __('E-Mail-Adresse') }}</label><input id="email" class="ak-input mt-2" name="email" type="email" value="{{ old('email', $user->email) }}" required>@error('email')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                </div>
            </section>

            <section id="darstellung" class="ak-detail-panel scroll-mt-24 overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                <div class="ak-detail-card-head -mx-6 -mt-6 flex items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-language class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Sprache & Darstellung') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Passe AktienKI an deine Vorlieben an') }}</p></div>
                </div>

                <div class="mt-6">
                    <label for="locale" class="ak-label">{{ __('Sprache') }}</label>
                    <select id="locale" name="locale" class="ak-input mt-2">
                        <option value="de" @selected($selectedLocale === 'de')>Deutsch</option>
                        <option value="en" @selected($selectedLocale === 'en')>English</option>
                    </select>
                    @error('locale')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5 flex items-center justify-between gap-5 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-4">
                    <div><h3 class="text-sm font-bold text-[var(--ak-text)]">{{ __('Darstellung') }}</h3><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Zwischen hellem und dunklem Design wechseln.') }}</p></div>
                    <x-preference-controls :show-theme="true" :show-locale="false" />
                </div>
            </section>

            <section class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <div class="ak-detail-card-head -mx-6 -mt-6 flex items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-teal-400/25 bg-teal-500/10 text-teal-500"><x-heroicon-o-shield-check class="h-5 w-5" /></span>
                    <div>
                        <h2 class="font-black text-[var(--ak-text)]">{{ __('Risikoprofil') }}</h2>
                        <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Du kannst das Risikoprofil jederzeit in deinem Nutzerprofil ändern. Welche Aktien und Auswertungen dir angezeigt werden, hängt sowohl von deinem gebuchten Tarif als auch vom gewählten Risikoprofil ab.') }}</p>
                    </div>
                </div>

                <fieldset class="mt-6">
                    <legend class="sr-only">{{ __('Gewünschtes Risikoprofil') }}</legend>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="group relative cursor-pointer rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-4 transition hover:border-orange-400/35 has-[:checked]:border-orange-400/60 has-[:checked]:bg-orange-400/[.08] has-[:checked]:ring-2 has-[:checked]:ring-orange-400/10">
                            <input class="sr-only" type="radio" name="risk_level" value="cautious" @checked($selectedRiskLevel === 'cautious')>
                            <span class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-orange-400">{{ __('Vorsichtig') }}</strong>
                                <x-heroicon-o-check-circle class="h-5 w-5 text-orange-400 opacity-0 transition group-has-[:checked]:opacity-100" />
                            </span>
                            <span class="mt-2 block text-xs leading-5 text-[var(--ak-muted)]">{{ __('Orientierung: niedrige Volatilität und historischer beziehungsweise modellierter Drawdown bis etwa 15 %.') }}</span>
                        </label>

                        <label class="group relative cursor-pointer rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-4 transition hover:border-teal-400/35 has-[:checked]:border-teal-400/60 has-[:checked]:bg-teal-500/[.09] has-[:checked]:ring-2 has-[:checked]:ring-teal-400/10">
                            <input class="sr-only" type="radio" name="risk_level" value="normal" @checked($selectedRiskLevel === 'normal')>
                            <span class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-teal-500">{{ __('Normal') }}</strong>
                                <x-heroicon-o-check-circle class="h-5 w-5 text-teal-500 opacity-0 transition group-has-[:checked]:opacity-100" />
                            </span>
                            <span class="mt-2 block text-xs leading-5 text-[var(--ak-muted)]">{{ __('Orientierung: mittlere Volatilität und historischer beziehungsweise modellierter Drawdown bis etwa 25 %.') }}</span>
                        </label>

                        <label class="group relative cursor-pointer rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-4 transition hover:border-rose-300/35 has-[:checked]:border-rose-300/55 has-[:checked]:bg-rose-400/[.08] has-[:checked]:ring-2 has-[:checked]:ring-rose-300/10">
                            <input class="sr-only" type="radio" name="risk_level" value="opportunity_oriented" @checked($selectedRiskLevel === 'opportunity_oriented')>
                            <span class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-rose-300">{{ __('Chancenorientiert') }}</strong>
                                <x-heroicon-o-check-circle class="h-5 w-5 text-rose-300 opacity-0 transition group-has-[:checked]:opacity-100" />
                            </span>
                            <span class="mt-2 block text-xs leading-5 text-[var(--ak-muted)]">{{ __('Orientierung: höhere Volatilität und möglicher historischer beziehungsweise modellierter Drawdown über 25 %.') }}</span>
                        </label>
                    </div>
                    @error('risk_level')<p class="mt-3 text-xs text-rose-400">{{ $message }}</p>@enderror
                </fieldset>
            </section>

            <section class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <div class="ak-detail-card-head -mx-6 -mt-6 flex items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-envelope class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('E-Mail-Service') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Lege fest, welche Mitteilungen du erhalten möchtest') }}</p></div>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    @foreach ([
                        ['email_service', __('E-Mail-Service aktivieren'), __('Hauptschalter für alle optionalen E-Mails')],
                        ['email_market_summary', __('Marktüberblick'), __('Regelmäßige Zusammenfassung wichtiger Marktbewegungen')],
                        ['email_price_alerts', __('Preis- und Signalsalarme'), __('Hinweise zu deinen Watchlists und festgelegten Signalen')],
                        ['email_product_updates', __('AktienKI Neuigkeiten'), __('Neue Funktionen, Verbesserungen und wichtige Produktinformationen')],
                    ] as [$key, $title, $copy])
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-4 transition hover:border-teal-400/30">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) old($key, $preferences[$key] ?? ($key === 'email_service'))) class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-600 bg-transparent text-teal-500 focus:ring-teal-500">
                            <span><strong class="block text-sm text-[var(--ak-text)]">{{ $title }}</strong><span class="mt-1 block text-xs leading-5 text-[var(--ak-muted)]">{{ $copy }}</span></span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <div class="ak-detail-card-head -mx-6 -mt-6 mb-4 flex items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-chat-bubble-left-right class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">WhatsApp</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Sichere Benachrichtigungen direkt im Profil verwalten.') }}</p></div>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <label><span class="ak-label">Access Token</span><input type="password" name="whatsapp_access_token" class="ak-input mt-2" autocomplete="new-password" placeholder="{{ data_get($whatsapp->credentials,'access_token') ? __('Gespeichert') : '' }}"></label>
                    <label><span class="ak-label">Phone Number ID</span><input name="whatsapp_phone_number_id" class="ak-input mt-2" placeholder="{{ data_get($whatsapp->credentials,'phone_number_id') ? __('Gespeichert') : '' }}"></label>
                    <label><span class="ak-label">{{ __('Empfänger mit Ländervorwahl') }}</span><input name="whatsapp_recipient" value="{{ $whatsapp->recipient }}" class="ak-input mt-2" placeholder="491701234567"></label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-xs font-bold"><input type="hidden" name="whatsapp_enabled" value="0"><input type="checkbox" name="whatsapp_enabled" value="1" @checked($whatsapp->enabled) class="h-4 w-4 accent-cyan-500">{{ __('Benachrichtigungen aktiv') }}</label>
                </div>
            </section>

            <div class="flex items-center justify-end lg:col-span-2">
                <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-500 px-7 text-sm font-black text-white shadow-lg shadow-teal-950/20 transition hover:brightness-110" type="submit"><x-heroicon-o-check class="h-4 w-4" />{{ __('Einstellungen speichern') }}</button>
            </div>
        </form>
    </div>
@endsection
