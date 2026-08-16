@extends('layouts.aktienki')

@section('content')
    <x-detail-page-theme />
    @php
        $preferences = $user->preferences ?? [];
        $selectedLocale = old('locale', $preferences['locale'] ?? app()->getLocale());
        $selectedCountry = strtoupper((string) old('country_code', $preferences['country_code'] ?? 'DE'));
        $selectedRiskLevel = old('risk_level', data_get($user->meta, 'risk_profile.level', 'normal'));
        $mobileNavDefaults = ['welcome','features','roadmap','dashboard','predictions','depots', ...($user->is_admin ? ['accounts'] : []), 'setup','news','pricing','contact','community'];
        $mobileNavLabels = ['welcome' => __('Startseite'), 'features' => __('Features'), 'roadmap' => __('Roadmap'), 'dashboard' => __('Dashboard'), 'predictions' => __('Prognosen'), 'depots' => __('Depots & Watchlist'), 'accounts' => __('Konten'), 'setup' => __('Setup'), 'news' => __('News'), 'pricing' => __('Preise'), 'contact' => __('Kontakt'), 'community' => __('Community')];
        $savedMobileNav = data_get($preferences, 'mobile_navigation', []);
        $mobileNavOrder = array_values(array_unique(array_merge((array) data_get($savedMobileNav, 'order', []), $mobileNavDefaults)));
        $mobileNavHidden = (array) data_get($savedMobileNav, 'hidden', []);
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

            <details open class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                <summary class="ak-detail-card-head -mx-6 -mt-6 flex cursor-pointer list-none items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-teal-400/25 bg-teal-500/10 text-teal-500"><x-heroicon-o-user class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Kontodaten') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Deine persönlichen Zugangsdaten') }}</p></div>
                </summary>

                <div class="mt-6 space-y-4">
                    <div><label for="name" class="ak-label">{{ __('Name') }}</label><input id="name" class="ak-input mt-2" name="name" type="text" value="{{ old('name', $user->name) }}" required>@error('name')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="ak-label">{{ __('E-Mail-Adresse') }}</label><input id="email" class="ak-input mt-2" name="email" type="email" value="{{ old('email', $user->email) }}" required>@error('email')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div>
                        <label for="country_code" class="ak-label">{{ __('Land') }}</label>
                        @if($countryLocked)
                            <input type="hidden" name="country_code" value="{{ $selectedCountry }}">
                        @endif
                        <select id="country_code" @unless($countryLocked) name="country_code" @endunless class="ak-input mt-2 disabled:cursor-not-allowed disabled:opacity-55" required @disabled($countryLocked)>
                            @foreach(['DE'=>'Deutschland','AT'=>'Österreich','BE'=>'Belgien','BG'=>'Bulgarien','HR'=>'Kroatien','CY'=>'Zypern','CZ'=>'Tschechien','DK'=>'Dänemark','EE'=>'Estland','FI'=>'Finnland','FR'=>'Frankreich','GR'=>'Griechenland','HU'=>'Ungarn','IE'=>'Irland','IT'=>'Italien','LV'=>'Lettland','LT'=>'Litauen','LU'=>'Luxemburg','MT'=>'Malta','NL'=>'Niederlande','PL'=>'Polen','PT'=>'Portugal','RO'=>'Rumänien','SK'=>'Slowakei','SI'=>'Slowenien','ES'=>'Spanien','SE'=>'Schweden','US'=>'Vereinigte Staaten','CA'=>'Kanada','CH'=>'Schweiz','GB'=>'Vereinigtes Königreich','AU'=>'Australien','CN'=>'China','HK'=>'Hongkong','JP'=>'Japan'] as $code => $countryName)
                                <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ __($countryName) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-[var(--ak-muted)]">
                            {{ __('Bestimmt die bevorzugte Kursnotierung: EUR innerhalb der EU, USD außerhalb der EU.') }}
                            @if($countryLocked)
                                <span class="mt-1 block font-bold text-amber-400">{{ __('Deine einmalige Länderänderung im Free-Tarif wurde bereits genutzt.') }}</span>
                            @else
                                <span class="mt-1 block">{{ __('Im Free-Tarif kannst du das bei der Registrierung gewählte Land später genau einmal ändern.') }}</span>
                            @endif
                        </p>
                        @error('country_code')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </details>

            <details id="darstellung" open class="ak-detail-panel scroll-mt-24 overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                <summary class="ak-detail-card-head -mx-6 -mt-6 flex cursor-pointer list-none items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-language class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Sprache & Darstellung') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Passe AktienKI an deine Vorlieben an') }}</p></div>
                </summary>

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
            </details>

            <details class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <summary class="ak-detail-card-head -mx-6 -mt-6 flex cursor-pointer list-none items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-device-phone-mobile class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Mobile Navigation') }}</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Ordne die mobile Topbar per Drag-and-drop und blende Menüpunkte aus. Die Desktop-Navigation bleibt unverändert.') }}</p></div>
                </summary>
                <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_auto]" x-data="{
                    order: @js($mobileNavOrder),
                    hidden: @js($mobileNavHidden),
                    drag: null,
                    sync() { this.$refs.order.value = JSON.stringify(this.order); this.$refs.hidden.value = JSON.stringify(this.hidden); },
                    drop(key) { if (!this.drag || this.drag === key) return; const from = this.order.indexOf(this.drag), to = this.order.indexOf(key); this.order.splice(from, 1); this.order.splice(to, 0, this.drag); this.drag = null; this.sync(); },
                    toggle(key) { this.hidden = this.hidden.includes(key) ? this.hidden.filter(item => item !== key) : [...this.hidden, key]; this.sync(); },
                    reset() { this.order = @js($mobileNavDefaults); this.hidden = []; this.sync(); }
                }" x-init="sync()">
                    <div class="grid gap-2" @dragover.prevent>
                        @foreach ($mobileNavOrder as $navKey)
                            <div draggable="true" @dragstart="drag='{{ $navKey }}'" @drop.prevent="drop('{{ $navKey }}')" class="flex cursor-grab items-center gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5 active:cursor-grabbing">
                                <x-heroicon-o-bars-3 class="h-5 w-5 shrink-0 text-[var(--ak-muted)]" />
                                <span class="min-w-0 flex-1 text-sm font-bold text-[var(--ak-text)]">{{ $mobileNavLabels[$navKey] ?? $navKey }}</span>
                                <button type="button" @click="toggle('{{ $navKey }}')" class="rounded-lg border border-[var(--ak-border)] px-2 py-1 text-[11px] font-black" :class="hidden.includes('{{ $navKey }}') ? 'text-rose-400' : 'text-emerald-400'" x-text="hidden.includes('{{ $navKey }}') ? '{{ __('Ausgeblendet') }}' : '{{ __('Sichtbar') }}'"></button>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-start justify-between gap-3 lg:block">
                        <p class="max-w-sm text-xs leading-5 text-[var(--ak-muted)]">{{ __('Ziehe die Einträge auf dem Smartphone in deine Wunschreihenfolge. Ausgeblendete Punkte erscheinen nicht in der mobilen Navigation.') }}</p>
                        <button type="button" @click="reset()" class="mt-3 inline-flex h-10 items-center gap-2 rounded-xl border border-amber-400/35 bg-amber-400/10 px-4 text-xs font-black text-amber-500"> <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Standard wiederherstellen') }}</button>
                    </div>
                    <input type="hidden" name="mobile_nav_order" x-ref="order">
                    <input type="hidden" name="mobile_nav_hidden" x-ref="hidden">
                </div>
            </details>

            <details class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <summary class="ak-detail-card-head -mx-6 -mt-6 flex cursor-pointer list-none items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-teal-400/25 bg-teal-500/10 text-teal-500"><x-heroicon-o-shield-check class="h-5 w-5" /></span>
                    <div>
                        <h2 class="font-black text-[var(--ak-text)]">{{ __('Risikoprofil') }}</h2>
                        <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Du kannst das Risikoprofil jederzeit in deinem Nutzerprofil ändern. Welche Aktien und Auswertungen dir angezeigt werden, hängt sowohl von deinem gebuchten Tarif als auch vom gewählten Risikoprofil ab.') }}</p>
                    </div>
                </summary>

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
            </details>

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

            <details class="ak-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] backdrop-blur-xl lg:col-span-2">
                <summary class="ak-detail-card-head -mx-6 -mt-6 mb-4 flex cursor-pointer list-none items-center gap-3 px-6 py-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-chat-bubble-left-right class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">WhatsApp</h2><p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Sichere Benachrichtigungen direkt im Profil verwalten.') }}</p></div>
                </summary>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <label><span class="ak-label">Access Token</span><input type="password" name="whatsapp_access_token" class="ak-input mt-2" autocomplete="new-password" placeholder="{{ data_get($whatsapp->credentials,'access_token') ? __('Gespeichert') : '' }}"></label>
                    <label><span class="ak-label">Phone Number ID</span><input name="whatsapp_phone_number_id" class="ak-input mt-2" placeholder="{{ data_get($whatsapp->credentials,'phone_number_id') ? __('Gespeichert') : '' }}"></label>
                    <label><span class="ak-label">{{ __('Empfänger mit Ländervorwahl') }}</span><input name="whatsapp_recipient" value="{{ $whatsapp->recipient }}" class="ak-input mt-2" placeholder="491701234567"></label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-xs font-bold"><input type="hidden" name="whatsapp_enabled" value="0"><input type="checkbox" name="whatsapp_enabled" value="1" @checked($whatsapp->enabled) class="h-4 w-4 accent-cyan-500">{{ __('Benachrichtigungen aktiv') }}</label>
                </div>
            </details>

            <div class="flex items-center justify-end lg:col-span-2">
                <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-500 px-7 text-sm font-black text-white shadow-lg shadow-teal-950/20 transition hover:brightness-110" type="submit"><x-heroicon-o-check class="h-4 w-4" />{{ __('Einstellungen speichern') }}</button>
            </div>
        </form>
    </div>
@endsection
