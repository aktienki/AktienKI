{{-- resources/views/livewire/dashboard/market-data.blade.php --}}

@php
    $score = $overallAssessment['score'] ?? collect($dailyAiScores)->last()['y'] ?? null;
    $positiveMarkets = (int) ($overallAssessment['positiveMarkets'] ?? 0);
    $marketCount = max(1, (int) ($overallAssessment['marketCount'] ?? count($markets)));
    $breadth = ($positiveMarkets / $marketCount) * 100;
    $averageChange = (float) ($overallAssessment['averageChange'] ?? 0);
    $tone = $overallAssessment['tone'] ?? 'neutral';
    $toneLabel = $overallAssessment['status'] ?? __('Neutral');
    $analysisHeadline = $marketAnalysis['headline'] ?? __('Marktdaten statt Marktgeräusche');
    $analysisSummary = $marketAnalysis['summary'] ?? $marketComment ?? __('Die Marktübersicht bündelt globale Entwicklung, KI-Score und Signalwechsel zu einem kompakten Lagebild.');
    $opportunities = collect($marketAnalysis['opportunities'] ?? [])->take(3);
    $risks = collect($marketAnalysis['risks'] ?? [])->take(3);
@endphp

<section class="ak-container ak-market-command py-5 lg:py-7">
    <header class="ak-market-command-hero ak-detail-hero">
        <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(330px,.8fr)] lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="ak-market-eyebrow">{{ __('Market Command Center') }}</span>
                    <span class="ak-market-live"><i></i>{{ __('Aktuelles Lagebild') }}</span>
                </div>
                <h1 class="mt-4 max-w-3xl text-3xl font-black tracking-[-.035em] text-[var(--ak-text)] sm:text-4xl">
                    {{ __('Globale Märkte auf einen Blick.') }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ak-muted)] sm:text-base">
                    {{ __('Erkenne Marktbreite, Dynamik und Signalwechsel, bevor du einzelne Aktien bewertest.') }}
                </p>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <div class="ak-market-hero-stat">
                    <span>{{ __('KI-Score') }}</span>
                    <strong>{{ $score !== null ? number_format($score, 1, ',', '.') : '—' }}</strong>
                    <small>/ 10</small>
                </div>
                <div class="ak-market-hero-stat">
                    <span>{{ __('Marktbreite') }}</span>
                    <strong>{{ number_format($breadth, 0, ',', '.') }}<small>%</small></strong>
                    <small>{{ $positiveMarkets }}/{{ $marketCount }} {{ __('positiv') }}</small>
                </div>
                <div class="ak-market-hero-stat" data-tone="{{ $tone }}">
                    <span>{{ __('Regime') }}</span>
                    <strong class="text-base sm:text-lg">{{ $toneLabel }}</strong>
                    <small>{{ $averageChange >= 0 ? '+' : '' }}{{ number_format($averageChange, 2, ',', '.') }} %</small>
                </div>
            </div>
        </div>
    </header>

    <div class="ak-market-tape ak-detail-panel ak-standard-card mt-4 grid gap-px overflow-hidden sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($markets as $market)
            @php $change = $market['change'] ?? null; @endphp
            <div class="ak-market-tape-item">
                <div class="min-w-0">
                    <p class="truncate text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ $market['name'] }}</p>
                    <p class="mt-1 truncate text-sm font-extrabold text-[var(--ak-text)]">
                        {{ is_numeric($market['price'] ?? null) ? number_format($market['price'], 2, ',', '.') : '—' }}
                        <small class="font-semibold text-[var(--ak-muted)]">{{ $market['currency'] ?? '' }}</small>
                    </p>
                </div>
                <span class="ak-market-change {{ ($change ?? 0) >= 0 ? 'is-positive' : 'is-negative' }}">
                    {{ $change !== null ? (($change >= 0 ? '+' : '').number_format($change, 2, ',', '.').' %') : '—' }}
                </span>
            </div>
        @endforeach
    </div>

    <div class="ak-market-primary-grid mt-4 grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <x-dashboard.market-atlas :country-ai-scores="$countryAiScores" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-1">
            <x-dashboard.overall-market-situation :assessment="$overallAssessment" />
            <x-dashboard.market-ai-score :daily-ai-scores="$dailyAiScores" :assessment="$overallAssessment" />
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <x-dashboard.signal-overview :stats="$signalTransitionStats" />
        </div>
        <div class="lg:col-span-5">
            <x-dashboard.signal-transition-heatmap :stats="$signalTransitionStats" />
        </div>
    </div>

    <x-dashboard.macro-indicator-cards :cards="$macroCards" />

    <article class="ak-market-briefing ak-detail-panel ak-standard-card mt-4">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.85fr)]">
            <div>
                <p class="ak-market-eyebrow">{{ __('KI Market Briefing') }}</p>
                <h2 class="mt-3 text-2xl font-black tracking-[-.025em] text-[var(--ak-text)]">{{ $analysisHeadline }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ak-muted)]">{{ $analysisSummary }}</p>
                @if (!empty($marketAnalysis['breadth']))
                    <div class="ak-market-briefing-note mt-4">
                        <x-heroicon-o-chart-pie class="h-4 w-4 shrink-0" />
                        <span>{{ $marketAnalysis['breadth'] }}</span>
                    </div>
                @endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                <section class="ak-market-list is-opportunity">
                    <h3><x-heroicon-o-arrow-trending-up class="h-4 w-4" />{{ __('Chancen') }}</h3>
                    @forelse ($opportunities as $item)
                        <p>{{ is_array($item) ? ($item['title'] ?? $item['name'] ?? $item['description'] ?? json_encode($item)) : $item }}</p>
                    @empty
                        <p>{{ __('Relative Stärke und verbesserte Signale gezielt beobachten.') }}</p>
                    @endforelse
                </section>
                <section class="ak-market-list is-risk">
                    <h3><x-heroicon-o-shield-exclamation class="h-4 w-4" />{{ __('Risiken') }}</h3>
                    @forelse ($risks as $item)
                        <p>{{ is_array($item) ? ($item['title'] ?? $item['name'] ?? $item['description'] ?? json_encode($item)) : $item }}</p>
                    @empty
                        <p>{{ __('Volatilität und negative Signalwechsel weiter im Blick behalten.') }}</p>
                    @endforelse
                </section>
            </div>
        </div>
        <footer class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--ak-border)] pt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-[var(--ak-muted)]">
            <span>{{ __('Automatisierte KI-Auswertung · keine Anlageberatung') }}</span>
            @if (!empty($marketAnalysis['date']))<span>{{ __('Analyse vom') }} {{ \Illuminate\Support\Carbon::parse($marketAnalysis['date'])->format('d.m.Y') }}</span>@endif
        </footer>
    </article>

</section>
