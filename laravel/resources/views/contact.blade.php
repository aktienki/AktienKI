<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Nimm Kontakt mit dem AktienKI-Team auf.') }}">
    <title>{{ __('Kontakt') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="auth()->guest()" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .contact-bg{background-color:#070b22;background-image:radial-gradient(circle at 72% 20%,rgba(139,92,246,.19),transparent 30%),radial-gradient(circle at 18% 72%,rgba(251,146,60,.1),transparent 27%),linear-gradient(rgba(43,29,93,.3) 1px,transparent 1px),linear-gradient(90deg,rgba(43,29,93,.3) 1px,transparent 1px);background-size:auto,auto,60px 60px,60px 60px}
        .contact-topbar{background:rgba(7,11,34,.82);border-bottom:1px solid rgba(139,92,246,.24);box-shadow:0 12px 45px rgba(0,0,0,.24);backdrop-filter:blur(22px)}
        [data-theme="light"] .contact-bg{background-color:#f8fafc;background-image:radial-gradient(circle at 75% 15%,rgba(139,92,246,.13),transparent 30%),radial-gradient(circle at 15% 80%,rgba(251,146,60,.1),transparent 28%),linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px)}
        [data-theme="light"] .contact-topbar{background:rgba(255,255,255,.82);border-color:rgba(124,58,237,.16)}
    </style>
</head>
<body class="contact-bg min-h-screen text-[var(--ak-text)] antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <header class="ak-public-topbar contact-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl bg-[var(--ak-accent-soft)] text-[var(--ak-accent)] ring-1 ring-[var(--ak-border-strong)] lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-xl bg-white px-3 py-2.5 text-sm font-semibold leading-5 text-slate-950 sm:inline-flex">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold leading-5 text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-xl border border-orange-400/25 bg-gradient-to-r from-violet-600 to-orange-4000 px-3 py-2.5 text-sm font-bold leading-5 text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5 hover:brightness-110 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="flex min-h-[calc(100svh-73px)] items-center justify-center px-5 py-8 sm:px-8 lg:h-[calc(100svh-73px)] lg:min-h-0 lg:overflow-hidden lg:py-5">
        <section class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-[var(--ak-border)] bg-[var(--ak-card-strong)] shadow-[var(--ak-shadow)] backdrop-blur-xl md:grid-cols-[.75fr_1.25fr] lg:h-[min(590px,calc(100svh-113px))] lg:grid-cols-[.85fr_1.15fr]">
            <div class="relative flex flex-col justify-center overflow-hidden border-b border-[var(--ak-border)] bg-[#070b22] p-7 text-white md:border-b-0 md:border-r lg:p-10">
                <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-violet-600/20 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-black uppercase tracking-[.22em] text-orange-400">{{ __('Kontakt') }}</p>
                    <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight">{{ __('Wir freuen uns auf deine Nachricht.') }}</h1>
                    <p class="mt-5 text-sm leading-6 text-slate-400">{{ __('Du hast Fragen zu AktienKI, unseren Funktionen oder Tarifen? Schreib uns – wir melden uns so schnell wie möglich zurück.') }}</p>
                    <div class="mt-8 space-y-3 text-xs leading-5 text-slate-400">
                        <p class="flex gap-3"><span class="text-orange-400">✓</span>{{ __('Fragen zu Funktionen und Tarifen') }}</p>
                        <p class="flex gap-3"><span class="text-orange-400">✓</span>{{ __('Technische Unterstützung') }}</p>
                        <p class="flex gap-3"><span class="text-orange-400">✓</span>{{ __('Allgemeines Feedback') }}</p>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8 lg:p-9">
                @if (session('contact_success'))
                    <div class="mb-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-400">{{ session('contact_success') }}</div>
                @endif
                <form method="POST" action="{{ route('contact.store') }}" class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                    @csrf
                    <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <div><label for="name" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--ak-accent)]">{{ __('Name') }}</label><input id="name" name="name" type="text" value="{{ old('name', auth()->user()?->name) }}" required class="h-11 w-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10">@error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--ak-accent)]">{{ __('E-Mail-Adresse') }}</label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required class="h-11 w-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10">@error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label for="subject" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--ak-accent)]">{{ __('Betreff') }}</label><input id="subject" name="subject" type="text" value="{{ old('subject') }}" required class="h-11 w-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10">@error('subject')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label for="message" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--ak-accent)]">{{ __('Nachricht') }}</label><textarea id="message" name="message" rows="5" required class="w-full resize-none rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 py-3 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-500/10">{{ old('message') }}</textarea>@error('message')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div class="flex flex-col items-stretch justify-between gap-3 sm:col-span-2 sm:flex-row sm:items-center sm:gap-4"><p class="text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Mit dem Absenden stimmst du der Verarbeitung deiner Angaben zur Bearbeitung der Anfrage zu.') }}</p><button type="submit" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-orange-4000 px-6 text-sm font-bold text-white shadow-lg shadow-violet-950/30 transition hover:-translate-y-0.5 hover:brightness-110">{{ __('Nachricht senden') }} <span>→</span></button></div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
