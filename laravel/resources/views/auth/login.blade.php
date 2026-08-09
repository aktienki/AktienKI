<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Bei AktienKI anmelden.') }}">
    <title>{{ __('Anmelden – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .auth-bg { --ak-accent:#fb923c;--ak-accent-soft:rgba(251,146,60,.10);--ak-card-strong:rgba(52,65,95,.60);--ak-border:rgba(251,146,60,.24);background-color:#090d22;background-image:radial-gradient(circle at 73% 34%,rgba(124,58,237,.16),transparent 34%),radial-gradient(circle at 28% 92%,rgba(251,146,60,.13),transparent 34%),radial-gradient(circle at 8% 16%,rgba(251,191,36,.04),transparent 22%),linear-gradient(135deg,#090d22 0%,#10162f 48%,#171033 100%); }
        .auth-topbar { background:rgba(11,20,36,.96);border-bottom:1px solid rgba(251,146,60,.14);box-shadow:0 10px 30px rgba(2,6,23,.24),inset 0 -1px 0 rgba(251,146,60,.035);backdrop-filter:blur(18px) saturate(115%); }
        .auth-shell { background-color:rgba(52,65,95,.60);border-color:rgba(251,146,60,.30);box-shadow:0 18px 46px rgba(2,6,23,.34),inset 0 1px 0 rgba(251,146,60,.04);backdrop-filter:blur(10px); }
        .auth-panel { background:rgba(11,24,48,.62); }
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
                <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-bold leading-5 text-orange-400 sm:inline-flex">{{ __('Anmelden') }}</a>
                <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-lg border border-orange-400/25 bg-orange-400/10 px-3 py-2.5 text-sm font-bold leading-5 text-orange-400 transition hover:bg-orange-400/20 lg:inline-flex">{{ __('Als Tester registrieren') }}</a>
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="flex min-h-[calc(100svh-73px)] items-center justify-center px-5 py-6 lg:h-[calc(100svh-73px)] lg:min-h-0 lg:overflow-hidden lg:py-4">
        <section class="auth-shell grid w-full max-w-5xl overflow-hidden rounded-2xl border lg:h-[calc(100svh-105px)] lg:max-h-[560px] lg:grid-cols-[.9fr_1.1fr]">
            <div class="auth-panel relative hidden overflow-hidden border-r border-orange-400/15 p-8 lg:flex lg:flex-col lg:justify-center">
                <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-orange-400/10 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-orange-400">{{ __('Willkommen zurück') }}</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight">{{ __('Daten verstehen.') }}<br><span class="text-orange-400">{{ __('Fundiert entscheiden.') }}</span></h1>
                    <p class="mt-5 max-w-sm leading-7 text-slate-400">{{ __('Greife auf deine KI-gestützten Aktienanalysen, Marktübersichten und persönlichen Watchlists zu.') }}</p>
                    <p class="mt-8 text-xs leading-5 text-slate-600">{{ __('AktienKI stellt Analysewerkzeuge bereit und ersetzt keine persönliche Anlageberatung.') }}</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 lg:p-8">
                <h2 class="text-2xl font-bold tracking-tight">{{ __('Anmelden') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('Melde dich mit deinen Zugangsdaten an.') }}</p>

                @if (session('status'))
                    <div class="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('E-Mail-Adresse') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition placeholder:text-slate-600 focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10" placeholder="name@example.com">
                        @error('email')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between"><label for="password" class="text-[10px] font-bold uppercase tracking-wider text-orange-400">{{ __('Passwort') }}</label>@if(Route::has('password.request'))<a href="{{ route('password.request') }}" class="text-xs font-semibold text-orange-400 hover:text-white">{{ __('Passwort vergessen?') }}</a>@endif</div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="h-11 w-full rounded-lg border border-orange-400/15 bg-[#0b1830]/75 px-4 text-white outline-none transition focus:border-orange-400/55 focus:ring-4 focus:ring-orange-400/10">
                        @error('password')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-2xl border border-amber-300/20 bg-amber-300/[.06] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-200">{{ __('Wichtiger Hinweis') }}</p>
                        <p class="mt-1 text-[11px] leading-4 text-slate-400">{{ __('AktienKI bietet automatisierte Analysen und KI-basierte Einschätzungen zu Informationszwecken. Dies ist keine Anlageberatung, Kauf- oder Verkaufsempfehlung. Kapitalanlagen sind mit Verlustrisiken verbunden.') }}</p>
                    </div>

                    <div class="flex items-center"><label class="flex items-center gap-2 text-xs text-slate-400"><input type="checkbox" name="remember" class="rounded border-slate-600 bg-[#0b1830] text-orange-4000 focus:ring-orange-4000"><span>{{ __('Angemeldet bleiben') }}</span></label></div>
                    <button type="submit" class="h-11 w-full rounded-lg border border-orange-400/35 bg-orange-400/20 font-bold text-orange-400 shadow-lg shadow-orange-400/20 transition hover:bg-orange-400/30">{{ __('Anmelden') }}</button>
                </form>
                <p class="mt-4 text-center text-xs text-slate-500">{{ __('Noch kein Konto?') }} <a href="{{ route('register') }}" class="font-bold text-orange-400 hover:text-white">{{ __('Jetzt registrieren') }}</a></p>
            </div>
        </section>
    </main>
</body>
</html>
