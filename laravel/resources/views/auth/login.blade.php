<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Bei AktienKI anmelden.') }}">
    <title>{{ __('Anmelden – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .auth-bg { background-color:#070b22; background-image:radial-gradient(circle at 76% 18%,rgba(139,92,246,.2),transparent 30%),radial-gradient(circle at 16% 84%,rgba(34,211,238,.1),transparent 27%),linear-gradient(rgba(43,29,93,.3) 1px,transparent 1px),linear-gradient(90deg,rgba(43,29,93,.3) 1px,transparent 1px);background-size:auto,auto,60px 60px,60px 60px; }
    </style>
</head>
<body class="auth-bg min-h-screen text-white antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <header class="ak-public-topbar sticky top-0 z-30 h-[73px] border-b border-violet-300/20 bg-[#070b22]/85 shadow-[0_12px_45px_rgba(0,0,0,.24)] backdrop-blur-xl">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <span class="flex h-10 w-[4.5rem] items-center justify-center"><img src="{{ asset('assets/logo.svg') }}" alt="" class="h-9 w-16"></span>
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
                <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl bg-violet-500/15 px-3 py-2.5 text-sm font-bold leading-5 text-violet-200 sm:inline-flex">{{ __('Anmelden') }}</a>
                <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-xl border border-cyan-300/25 bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2.5 text-sm font-bold leading-5 text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5 hover:brightness-110 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="flex min-h-[calc(100svh-73px)] items-center justify-center px-5 py-6 lg:h-[calc(100svh-73px)] lg:min-h-0 lg:overflow-hidden lg:py-4">
        <section class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-violet-300/20 bg-[#090f2a]/90 shadow-2xl shadow-black/50 backdrop-blur-xl lg:h-[calc(100svh-105px)] lg:max-h-[560px] lg:grid-cols-[.9fr_1.1fr]">
            <div class="relative hidden overflow-hidden border-r border-violet-300/15 bg-[#070b22] p-8 lg:flex lg:flex-col lg:justify-center">
                <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-violet-600/20 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">{{ __('Willkommen zurück') }}</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight">{{ __('Daten verstehen.') }}<br><span class="text-violet-300">{{ __('Fundiert entscheiden.') }}</span></h1>
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
                        <label for="email" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-violet-200">{{ __('E-Mail-Adresse') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="h-11 w-full rounded-xl border border-white/10 bg-[#070b22] px-4 text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10" placeholder="name@example.com">
                        @error('email')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between"><label for="password" class="text-[10px] font-bold uppercase tracking-wider text-violet-200">{{ __('Passwort') }}</label>@if(Route::has('password.request'))<a href="{{ route('password.request') }}" class="text-xs font-semibold text-violet-300 hover:text-white">{{ __('Passwort vergessen?') }}</a>@endif</div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="h-11 w-full rounded-xl border border-white/10 bg-[#070b22] px-4 text-white outline-none transition focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10">
                        @error('password')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-2xl border border-amber-300/20 bg-amber-300/[.06] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-200">{{ __('Wichtiger Hinweis') }}</p>
                        <p class="mt-1 text-[11px] leading-4 text-slate-400">{{ __('AktienKI bietet automatisierte Analysen und KI-basierte Einschätzungen zu Informationszwecken. Dies ist keine Anlageberatung, Kauf- oder Verkaufsempfehlung. Kapitalanlagen sind mit Verlustrisiken verbunden.') }}</p>
                    </div>

                    <div class="flex items-center"><label class="flex items-center gap-2 text-xs text-slate-400"><input type="checkbox" name="remember" class="rounded border-slate-600 bg-[#070b22] text-violet-500 focus:ring-violet-500"><span>{{ __('Angemeldet bleiben') }}</span></label></div>
                    <button type="submit" class="h-11 w-full rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-bold text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5 hover:brightness-110">{{ __('Anmelden') }}</button>
                </form>
                <p class="mt-4 text-center text-xs text-slate-500">{{ __('Noch kein Konto?') }} <a href="{{ route('register') }}" class="font-bold text-violet-300 hover:text-white">{{ __('Jetzt registrieren') }}</a></p>
            </div>
        </section>
    </main>
</body>
</html>
