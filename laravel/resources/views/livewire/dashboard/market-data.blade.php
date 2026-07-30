{{-- resources/views/livewire/dashboard/market-data.blade.php --}}

<section class="ak-dashboard-content">

    <h2 class="ak-section-title">
        {{ __('Märkte & Marktsituation') }}
    </h2>

    <div class="ak-dashboard-primary grid items-stretch gap-5 lg:grid-cols-2">
        <x-dashboard.market-atlas :country-ai-scores="$countryAiScores" />
        <x-dashboard.overall-market-situation
            :daily-ai-scores="$dailyAiScores"
            :assessment="$overallAssessment"
        />
    </div>

    <article class="ak-card ak-dashboard-comment mt-5 p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-teal-500/20 bg-teal-500/10 text-teal-500">
                <x-heroicon-o-sparkles class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-500">{{ __('aKI Tageskommentar') }}</p>
                    <a href="{{ route('daily-market-analysis') }}" class="text-[10px] font-black text-teal-500 hover:text-teal-400">
                        {{ __('Vollständige Analyse') }} →
                    </a>
                </div>
                <p class="mt-3 min-h-[15rem] max-h-[15rem] overflow-y-auto overscroll-contain pr-3 text-sm leading-6 text-[var(--ak-muted)] [scrollbar-color:rgba(20,184,166,.35)_transparent] [scrollbar-width:thin] sm:text-base sm:leading-6">
                    {{ $marketComment ?: ($overallAssessment['summary'] ?? __('Noch kein Marktkommentar verfügbar.')) }}
                </p>
            </div>
        </div>
    </article>

</section>
