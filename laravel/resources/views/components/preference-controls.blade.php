@props(['showTheme' => false, 'showLocale' => true])

<div {{ $attributes->class([
    'relative flex shrink-0 self-center items-center',
    'h-10 justify-center' => $showLocale && ! $showTheme,
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
        @php($languageNames = ['de' => 'Deutsch', 'en' => 'English'])
        <details class="group relative">
            <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-lg px-2 text-xs font-black uppercase tracking-wider text-[var(--ak-muted)] transition hover:bg-[var(--ak-accent-soft)] hover:text-[var(--ak-text)] [&::-webkit-details-marker]:hidden" title="{{ __('Sprache wählen') }}" aria-label="{{ __('Sprache wählen') }}">
                <svg class="h-5 w-5 text-cyan-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                <span>{{ strtoupper(app()->getLocale()) }}</span>
                <svg class="h-3 w-3 transition group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
            </summary>
            <div class="absolute right-0 z-[100] mt-2 min-w-36 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-1.5 shadow-[var(--ak-shadow)]">
                @foreach ($languageNames as $locale => $languageName)
                    <form method="POST" action="{{ route('locale.update', $locale) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-bold transition {{ app()->getLocale() === $locale ? 'bg-cyan-400/[.12] text-cyan-300' : 'text-[var(--ak-muted)] hover:bg-[var(--ak-accent-soft)] hover:text-[var(--ak-text)]' }}" lang="{{ $locale }}">
                            <span>{{ $languageName }}</span><span class="ml-4 uppercase opacity-60">{{ $locale }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </details>
    @endif
</div>
