@props(['showTheme' => false, 'showLocale' => true])

<div {{ $attributes->class([
    'flex shrink-0 self-center items-center',
    'h-10 w-[4.25rem] translate-y-2 justify-center gap-1' => $showLocale && ! $showTheme,
    'h-9 gap-0.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-0.5 backdrop-blur' => $showTheme,
]) }}>
    @if ($showTheme)
        <button type="button" data-theme-toggle class="flex h-8 w-8 items-center justify-center rounded-lg text-[var(--ak-muted)] transition hover:bg-[var(--ak-accent-soft)] hover:text-[var(--ak-text)]" title="{{ __('Theme wechseln') }}" aria-label="{{ __('Theme wechseln') }}">
            <svg data-theme-icon="light" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
            <svg data-theme-icon="dark" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A9 9 0 1111.2 3 7 7 0 0021 12.8z"/></svg>
        </button>
    @endif
    @if ($showTheme && $showLocale)
        <span class="h-4 w-px bg-[var(--ak-border)]"></span>
    @endif
    @if ($showLocale)
        @foreach (['de', 'en'] as $locale)
            <form method="POST" action="{{ route('locale.update', $locale) }}" class="flex h-8 w-8 shrink-0 items-center justify-center">
                @csrf
                <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md transition {{ app()->getLocale() === $locale ? 'bg-[var(--ak-accent-soft)] ring-1 ring-inset ring-[var(--ak-border-strong)]' : 'opacity-55 hover:bg-[var(--ak-accent-soft)] hover:opacity-100' }}" lang="{{ $locale }}" title="{{ $locale === 'de' ? __('Deutsch wählen') : __('Englisch wählen') }}" aria-label="{{ $locale === 'de' ? __('Deutsch wählen') : __('Englisch wählen') }}">
                    @if ($locale === 'de')
                        <svg class="h-5 w-6 overflow-hidden rounded-[2px]" viewBox="0 0 30 18" aria-hidden="true"><path fill="#181818" d="M0 0h30v6H0z"/><path fill="#dd0000" d="M0 6h30v6H0z"/><path fill="#ffce00" d="M0 12h30v6H0z"/></svg>
                    @else
                        <svg class="h-5 w-6 overflow-hidden rounded-[2px]" viewBox="0 0 30 18" aria-hidden="true"><path fill="#012169" d="M0 0h30v18H0z"/><path stroke="#fff" stroke-width="4" d="m0 0 30 18M30 0 0 18"/><path stroke="#c8102e" stroke-width="2" d="m0 0 30 18M30 0 0 18"/><path stroke="#fff" stroke-width="6" d="M15 0v18M0 9h30"/><path stroke="#c8102e" stroke-width="3.5" d="M15 0v18M0 9h30"/></svg>
                    @endif
                </button>
            </form>
        @endforeach
    @endif
</div>
