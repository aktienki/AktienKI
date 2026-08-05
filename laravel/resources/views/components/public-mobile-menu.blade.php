<div x-data="{ open: false }" class="relative lg:hidden">
    <button type="button" @click="open = !open" @click.outside="open = false" class="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-text)]" aria-label="{{ __('Menü öffnen') }}" :aria-expanded="open.toString()">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
    </button>
    <nav x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-12 z-50 w-56 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface)] p-2 shadow-2xl shadow-black/40">
        <a href="{{ route('welcome') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Startseite') }}</a>
        <a href="{{ route('features') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Features') }}</a>
        <a href="{{ route('roadmap') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Roadmap') }}</a>
        <a href="{{ route('pricing') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Preise') }}</a>
        @auth
            <a href="{{ route('contact') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Kontakt') }}</a>
        @endauth
        <a href="{{ route('reviews.index') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Bewertungen') }}</a>
        <div class="my-1 h-px bg-[var(--ak-border)]"></div>
        @auth
            <a href="{{ route('dashboard') }}" class="block rounded-xl bg-[var(--ak-accent-soft)] px-4 py-3 text-sm font-bold text-[var(--ak-accent)]">{{ __('Dashboard') }}</a>
        @else
            <a href="{{ route('login') }}" class="block rounded-xl px-4 py-3 text-sm text-[var(--ak-text)] hover:bg-[var(--ak-accent-soft)]">{{ __('Anmelden') }}</a>
            <a href="{{ route('register') }}" class="mt-1 block rounded-xl bg-gradient-to-r from-violet-600 to-orange-4000 px-4 py-3 text-center text-sm font-bold text-white">{{ __('Registrieren') }}</a>
        @endauth
    </nav>
</div>
