<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Erfahrungen und Bewertungen zu AktienKI.com.') }}">
    <title>{{ __('Bewertungen') }} – AktienKI.com</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="auth()->guest()" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .reviews-bg{background-color:#070b22;background-image:radial-gradient(circle at 78% 18%,rgba(139,92,246,.2),transparent 30%),radial-gradient(circle at 14% 82%,rgba(251,146,60,.1),transparent 28%),linear-gradient(rgba(43,29,93,.3) 1px,transparent 1px),linear-gradient(90deg,rgba(43,29,93,.3) 1px,transparent 1px);background-size:auto,auto,60px 60px,60px 60px}.reviews-topbar{background:rgba(7,11,34,.82);border-bottom:1px solid rgba(139,92,246,.24);backdrop-filter:blur(22px)}[data-theme="light"] .reviews-bg{background-color:#f8fafc;background-image:radial-gradient(circle at 75% 15%,rgba(139,92,246,.13),transparent 30%),radial-gradient(circle at 15% 80%,rgba(251,146,60,.1),transparent 28%)}[data-theme="light"] .reviews-topbar{background:rgba(255,255,255,.82);border-color:rgba(124,58,237,.16)}
        .star-choice{display:flex;flex-direction:row-reverse;justify-content:flex-end}.star-choice input{position:absolute;opacity:0}.star-choice label{cursor:pointer;color:#475569;font-size:1.75rem;line-height:1;transition:.15s}.star-choice label:hover,.star-choice label:hover~label,.star-choice input:checked~label{color:#a78bfa}
    </style>
</head>
<body class="reviews-bg min-h-screen text-[var(--ak-text)] antialiased">
    <header class="ak-public-topbar reviews-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl bg-[var(--ak-accent-soft)] text-[var(--ak-accent)] ring-1 ring-[var(--ak-border-strong)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-xl bg-white px-3 py-2.5 text-sm font-semibold leading-5 text-slate-950 sm:inline-flex">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold leading-5 text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-xl border border-orange-400/25 bg-gradient-to-r from-violet-600 to-orange-4000 px-3 py-2.5 text-sm font-bold leading-5 text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5 hover:brightness-110 lg:inline-flex">{{ __('Als Tester registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="mx-auto grid w-full max-w-7xl gap-6 px-5 py-8 sm:px-8 md:py-10 lg:grid-cols-[1.2fr_.8fr] lg:px-10">
        <section>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><p class="text-xs font-black uppercase tracking-[.22em] text-[var(--ak-accent)]">{{ __('Stimmen aus der Community') }}</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">{{ __('Bewertungen zu AktienKI.com') }}</h1><p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Erfahrungen von Nutzerinnen und Nutzern auf einen Blick.') }}</p></div>
                <div class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-5 py-3 text-center"><div class="text-2xl tracking-wider text-violet-400">★★★★★</div><strong class="text-lg">{{ number_format($averageRating, 1, ',', '.') }}</strong><span class="ml-1 text-xs text-[var(--ak-muted)]">({{ $reviewCount }})</span></div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @forelse ($reviews as $review)
                    <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                        <div class="flex items-center justify-between gap-3"><span class="text-lg tracking-wider text-violet-400">{{ str_repeat('★', $review->rating) }}<span class="text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span></span><time class="text-[10px] text-[var(--ak-muted)]">{{ $review->created_at->format('d.m.Y') }}</time></div>
                        @if ($review->title)<h2 class="mt-3 font-black">{{ $review->title }}</h2>@endif
                        <p class="mt-2 text-sm leading-6 text-[var(--ak-muted)]">{{ $review->comment }}</p>
                        <p class="mt-4 text-xs font-bold text-[var(--ak-text)]">{{ $review->name }}</p>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)] p-8 text-center text-sm text-[var(--ak-muted)] sm:col-span-2">{{ __('Noch keine Bewertungen vorhanden. Teile als Erste oder Erster deine Erfahrung.') }}</div>
                @endforelse
            </div>
            @if ($reviews->hasPages())<div class="mt-6">{{ $reviews->links() }}</div>@endif
        </section>

        <aside class="self-start rounded-[1.75rem] border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-5 shadow-[var(--ak-shadow)] backdrop-blur-xl sm:p-7 lg:sticky lg:top-[93px]">
            <h2 class="text-xl font-black">{{ __('Deine Bewertung') }}</h2>
            <p class="mt-1 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Wie ist deine Erfahrung mit AktienKI.com?') }}</p>
            @if (session('review_success'))<div class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-400">{{ session('review_success') }}</div>@endif
            @auth
                <form method="POST" action="{{ route('reviews.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 py-3"><span class="text-[10px] font-bold uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Bewertung als') }}</span><strong class="mt-1 block text-sm">{{ auth()->user()->name }}</strong></div>
                    <fieldset><legend class="ak-label">{{ __('Bewertung') }}</legend><div class="star-choice mt-2">@for ($star = 5; $star >= 1; $star--)<input id="rating-{{ $star }}" name="rating" type="radio" value="{{ $star }}" required @checked((int) old('rating') === $star)><label for="rating-{{ $star }}" title="{{ $star }} {{ __('Sterne') }}">★</label>@endfor</div>@error('rating')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</fieldset>
                    <div><label class="ak-label" for="review-title">{{ __('Titel') }} <span class="normal-case text-[var(--ak-muted)]">({{ __('optional') }})</span></label><input id="review-title" name="title" value="{{ old('title') }}" maxlength="100" class="ak-input mt-2">@error('title')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <div><label class="ak-label" for="review-comment">{{ __('Deine Erfahrung') }}</label><textarea id="review-comment" name="comment" rows="5" required maxlength="1500" class="ak-input mt-2 h-auto resize-none py-3">{{ old('comment') }}</textarea>@error('comment')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
                    <button class="h-11 w-full rounded-xl bg-gradient-to-r from-violet-600 to-orange-4000 text-sm font-bold text-white shadow-lg shadow-violet-950/30 transition hover:brightness-110">{{ __('Bewertung veröffentlichen') }}</button>
                </form>
            @else
                <div class="mt-5 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-5 text-center">
                    <p class="text-sm leading-6 text-[var(--ak-muted)]">{{ __('Nur registrierte Nutzer können eine Bewertung abgeben.') }}</p>
                    <a href="{{ route('login') }}" class="mt-4 flex h-11 items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-orange-4000 text-sm font-bold text-white">{{ __('Jetzt anmelden') }}</a>
                    <a href="{{ route('register') }}" class="mt-2 block text-xs font-bold text-[var(--ak-accent)]">{{ __('Noch kein Konto? Jetzt registrieren') }}</a>
                </div>
            @endauth
        </aside>
    </main>
</body>
</html>
