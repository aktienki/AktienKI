<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Ein AktienKI-Konto erstellen.') }}">
    <title>{{ __('Registrieren – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .auth-bg{--ak-accent:#fb923c;--ak-accent-soft:rgba(251,146,60,.10);--ak-card-strong:rgba(52,65,95,.60);--ak-border:rgba(251,146,60,.24);background-color:#090d22;background-image:radial-gradient(circle at 73% 34%,rgba(124,58,237,.16),transparent 34%),radial-gradient(circle at 28% 92%,rgba(251,146,60,.13),transparent 34%),radial-gradient(circle at 8% 16%,rgba(251,191,36,.04),transparent 22%),linear-gradient(135deg,#090d22 0%,#10162f 48%,#171033 100%)}
        .auth-topbar{background:rgba(11,20,36,.96);border-bottom:1px solid rgba(251,146,60,.14);box-shadow:0 10px 30px rgba(2,6,23,.24),inset 0 -1px 0 rgba(251,146,60,.035);backdrop-filter:blur(18px) saturate(115%)}
        .auth-shell{background-color:rgba(52,65,95,.60);border-color:rgba(251,146,60,.30);box-shadow:0 18px 46px rgba(2,6,23,.34),inset 0 1px 0 rgba(251,146,60,.04);backdrop-filter:blur(10px)}
        .auth-panel{background:rgba(11,24,48,.62)}
    </style>
</head>
<body class="auth-bg min-h-screen text-white antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <header class="ak-public-topbar auth-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-slate-300 transition hover:bg-white/[.05] hover:text-white sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-slate-300 transition hover:bg-white/[.05] hover:text-white sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold leading-5 text-slate-300 transition hover:text-white sm:inline-flex">{{ __('Anmelden') }}</a>
                <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-bold leading-5 text-orange-400 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="flex min-h-[calc(100svh-73px)] items-center justify-center px-5 py-6 lg:h-[calc(100svh-73px)] lg:min-h-0 lg:overflow-hidden lg:py-4">
        @php
            $riskFields = ['risk_level'];
            $initialStep = $errors->hasAny(array_merge($riskFields, ['accept_disclaimer', 'accept_risk_notice'])) ? 2 : 1;
        @endphp
        <section class="auth-shell grid w-full max-w-6xl overflow-hidden rounded-2xl border lg:grid-cols-[1.05fr_.95fr]" x-data="{ step: {{ $initialStep }}, riskLevel: @js(old('risk_level', 'normal')), disclaimerAccepted: @js((bool) old('accept_disclaimer')), riskNoticeAccepted: @js((bool) old('accept_risk_notice')) }">
            <div class="p-6 sm:p-8 lg:p-8">
                <h1 class="text-2xl font-bold tracking-tight">{{ __('Konto erstellen') }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ __('Starte mit KI-gestützten Aktienanalysen.') }}</p>

                <form id="registration-form" method="POST" action="{{ route('register') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="risk_level" x-model="riskLevel">
                    <div class="mb-4 grid grid-cols-2 gap-2" aria-label="{{ __('Registrierungsfortschritt') }}">
                        @foreach ([1 => __('Konto'), 2 => __('Risikoprofil & Bestätigung')] as $number => $label)
                            <div class="flex items-center gap-2 rounded-lg border px-2 py-2 text-[10px] font-bold transition" :class="step >= {{ $number }} ? 'border-orange-400/45 bg-orange-400/10 text-orange-400' : 'border-orange-400/10 text-slate-500'">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md" :class="step >= {{ $number }} ? 'bg-orange-400/25 text-orange-400' : 'bg-white/5'">{{ $number }}</span>
                                <span class="truncate">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="step === 1" data-step="1" class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label for="name" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('Name') }}</label><input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10">@error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-2"><label for="email" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('E-Mail-Adresse') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10">@error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                        <div><label for="password" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('Passwort') }}</label><input id="password" name="password" type="password" autocomplete="new-password" required class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10">@error('password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                        <div><label for="password_confirmation" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('Wiederholen') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10"></div>
                        <div class="sm:col-span-2"><button type="button" @click="if ([...$el.closest('[data-step]').querySelectorAll('input')].every(field => field.reportValidity())) step = 2" class="h-11 w-full rounded-lg border border-orange-400/35 bg-orange-400/20 font-bold text-orange-400 transition hover:bg-orange-400/30">{{ __('Weiter zum Risikoprofil') }}</button></div>
                    </div>

                    <div x-cloak x-show="step === 2" data-step="2">
                        <div class="mb-3">
                            <h2 class="text-lg font-bold">{{ __('Dein Risikoprofil') }}</h2>
                            <p class="mt-1 text-[11px] leading-4 text-slate-400">{{ __('Die Angaben helfen, Analysen passend einzuordnen. Sie ersetzen keine persönliche Geeignetheitsprüfung oder Anlageberatung.') }}</p>
                            <p class="mt-2 rounded-lg border border-orange-400/20 bg-orange-400/[.07] px-3 py-2 text-[10px] leading-4 text-orange-400">{{ __('Du kannst das Risikoprofil jederzeit in deinem Nutzerprofil ändern. Welche Aktien und Auswertungen dir angezeigt werden, hängt sowohl von deinem gebuchten Tarif als auch vom gewählten Risikoprofil ab.') }}</p>
                        </div>
                        <fieldset role="radiogroup" aria-label="{{ __('Gewünschtes Risikoprofil') }}">
                            <legend class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('Gewünschtes Risikoprofil') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <button type="button" role="radio" :aria-checked="riskLevel === 'cautious'" @click="riskLevel = 'cautious'" class="h-full rounded-lg border p-3 text-left transition" :class="riskLevel === 'cautious' ? 'border-orange-400/65 bg-orange-400/[.12] ring-2 ring-orange-400/15' : 'border-orange-400/12 bg-[#0b1830]/45 hover:border-orange-400/35'"><span class="block text-xs font-bold text-orange-400">{{ __('Vorsichtig') }}</span><span class="mt-1 block text-[10px] leading-4 text-slate-400">{{ __('Orientierung: niedrige Volatilität und historischer beziehungsweise modellierter Drawdown bis etwa 15 %.') }}</span></button>
                                <button type="button" role="radio" :aria-checked="riskLevel === 'normal'" @click="riskLevel = 'normal'" class="h-full rounded-lg border p-3 text-left transition" :class="riskLevel === 'normal' ? 'border-orange-400/65 bg-orange-400/[.12] ring-2 ring-orange-400/15' : 'border-orange-400/12 bg-[#0b1830]/45 hover:border-orange-400/35'"><span class="block text-xs font-bold text-orange-400">{{ __('Normal') }} <span class="ml-1 rounded-md bg-orange-400/15 px-2 py-0.5 text-[8px] uppercase">{{ __('Standard') }}</span></span><span class="mt-1 block text-[10px] leading-4 text-slate-300">{{ __('Orientierung: mittlere Volatilität und historischer beziehungsweise modellierter Drawdown bis etwa 25 %.') }}</span></button>
                                <button type="button" role="radio" :aria-checked="riskLevel === 'opportunity_oriented'" @click="riskLevel = 'opportunity_oriented'" class="h-full rounded-lg border p-3 text-left transition" :class="riskLevel === 'opportunity_oriented' ? 'border-orange-400/65 bg-orange-400/[.12] ring-2 ring-orange-400/15' : 'border-orange-400/12 bg-[#0b1830]/45 hover:border-orange-400/35'"><span class="block text-xs font-bold text-orange-400">{{ __('Chancenorientiert') }}</span><span class="mt-1 block text-[10px] leading-4 text-slate-400">{{ __('Orientierung: höhere Volatilität und möglicher historischer beziehungsweise modellierter Drawdown über 25 %.') }}</span></button>
                            </div>
                            @error('risk_level')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </fieldset>
                        <p class="mt-2 text-[9px] leading-4 text-amber-200/80">{{ __('Drawdown bezeichnet den zwischenzeitlichen Rückgang von einem Höchststand. Die Werte dienen nur der Einordnung: Tatsächliche Verluste können höher sein und bis zum vollständigen Kapitalverlust reichen.') }}</p>
                    <div class="mt-3 grid grid-cols-[.7fr_1.3fr] gap-3"><button type="button" @click="step = 1" class="h-11 rounded-lg border border-orange-400/15 font-bold text-slate-300 hover:bg-orange-400/[.06]">{{ __('Zurück') }}</button><button type="submit" :disabled="!disclaimerAccepted || !riskNoticeAccepted" :aria-disabled="(!disclaimerAccepted || !riskNoticeAccepted).toString()" class="h-11 rounded-lg border border-orange-400/35 bg-orange-400/20 font-bold text-orange-400 shadow-lg shadow-orange-400/20 transition hover:bg-orange-400/30 disabled:cursor-not-allowed disabled:opacity-40">{{ __('Jetzt registrieren') }}</button></div>
                    </div>
                </form>
                <p class="mt-3 text-center text-xs text-slate-500">{{ __('Bereits registriert?') }} <a href="{{ route('login') }}" class="font-bold text-orange-400 hover:text-white">{{ __('Jetzt anmelden') }}</a></p>
            </div>

            <aside class="auth-panel border-t border-orange-400/15 p-7 sm:p-8 lg:border-l lg:border-t-0 lg:p-8">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-amber-300">{{ __('Transparenz vor Nutzung') }}</p>
                <h2 class="mt-3 text-2xl font-bold tracking-tight">{{ __('Analyse ist keine Beratung.') }}</h2>
                <p class="mt-3 text-xs leading-5 text-slate-400">{{ __('AktienKI verarbeitet Markt- und Unternehmensdaten mit statistischen Verfahren und künstlicher Intelligenz. Die angezeigten Scores und Prognosen können falsch sein.') }}</p>
                <div class="mt-4 space-y-2">
                    @foreach ([
                        __('Keine individuelle Anlage-, Rechts- oder Steuerberatung'),
                        __('Keine Aufforderung zum Kauf oder Verkauf von Wertpapieren'),
                        __('Keine Garantie für Kursentwicklungen oder Gewinne'),
                        __('Ein vollständiger Verlust des eingesetzten Kapitals ist möglich'),
                        __('Entscheidungen und Verantwortung verbleiben vollständig bei dir'),
                    ] as $notice)
                        <div class="flex gap-3 rounded-xl border border-white/[.06] bg-white/[.03] px-3 py-2 text-[11px] leading-4 text-slate-300"><span class="text-orange-400">✓</span><span>{{ $notice }}</span></div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-2xl border border-amber-300/20 bg-amber-300/[.06] p-3"><p class="text-xs font-bold text-amber-200">{{ __('Risikohinweis') }}</p><p class="mt-1 text-[11px] leading-4 text-slate-400">{{ __('Historische Ergebnisse sind kein verlässlicher Indikator für zukünftige Entwicklungen. Prüfe Informationen selbstständig und ziehe bei Bedarf qualifizierte Beratung hinzu.') }}</p></div>
                <div x-cloak x-show="step === 2">
                <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-lg border border-orange-400/12 bg-[#0b1830]/45 p-2 text-[10px] leading-4 text-slate-300 transition has-[:checked]:border-orange-400/55 has-[:checked]:bg-orange-400/[.09] has-[:checked]:ring-1 has-[:checked]:ring-orange-400/15">
                    <input form="registration-form" type="checkbox" name="accept_disclaimer" value="1" required x-model="disclaimerAccepted" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-600 bg-[#0b1830] text-orange-4000 focus:ring-0 focus:ring-offset-0">
                    <span>{{ __('Ich bestätige, dass AktienKI ausschließlich Analyse- und Informationswerkzeuge bereitstellt, keine Anlageberatung erbringt und keine zukünftigen Ergebnisse garantiert. Anlageentscheidungen treffe ich eigenverantwortlich und unter Berücksichtigung möglicher Kapitalverluste. Ich akzeptiere außerdem die geltenden Nutzungs- und Datenschutzbestimmungen.') }}</span>
                </label>
                @error('accept_disclaimer')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                <label class="mt-2 flex cursor-pointer items-start gap-3 rounded-lg border border-orange-400/12 bg-[#0b1830]/45 p-2 text-[10px] leading-4 text-slate-300 transition has-[:checked]:border-orange-400/55 has-[:checked]:bg-orange-400/[.09] has-[:checked]:ring-1 has-[:checked]:ring-orange-400/15">
                    <input form="registration-form" type="checkbox" name="accept_risk_notice" value="1" required x-model="riskNoticeAccepted" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-600 bg-[#0b1830] text-orange-4000 focus:ring-0 focus:ring-offset-0">
                    <span>{{ __('Ich habe den Risikohinweis gelesen und bestätige, dass Kapitalanlagen mit Verlusten bis hin zum vollständigen Verlust des eingesetzten Kapitals verbunden sein können.') }}</span>
                </label>
                @error('accept_risk_notice')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </aside>
        </section>
    </main>
</body>
</html>
