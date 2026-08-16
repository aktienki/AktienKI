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
    $opportunities = collect($marketAnalysis['opportunities'] ?? []);
    $risks = collect($marketAnalysis['risks'] ?? []);
    $watchlist = collect($marketAnalysis['watchlist'] ?? []);
    $analysisMetrics = collect($marketAnalysis['metrics'] ?? []);
    $reportSources = collect($marketAnalysis['sources'] ?? []);
    $isExternalAiReport = (bool) ($marketAnalysis['is_external_ai'] ?? false);
    $analysisItemText = function (mixed $item): string {
        if (is_string($item) || is_numeric($item)) return (string) $item;
        if (!is_array($item)) return '';
        return (string) ($item['summary'] ?? $item['description'] ?? $item['name'] ?? $item['title'] ?? collect($item)->filter(fn ($value) => is_scalar($value))->implode(' · '));
    };
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

    <div class="ak-market-primary-grid mt-4 grid items-stretch gap-4 lg:grid-cols-12">
        <div class="lg:col-span-6">
            <x-dashboard.market-atlas :country-ai-scores="$countryAiScores" />
        </div>
        <div class="lg:col-span-3">
            <x-dashboard.overall-market-situation :assessment="$overallAssessment" />
        </div>
        <div class="lg:col-span-3">
            <x-dashboard.signal-overview :stats="$signalTransitionStats" />
        </div>
    </div>

    <x-dashboard.macro-indicator-cards :cards="$macroCards" />

    <article class="ak-market-briefing ak-detail-panel ak-standard-card mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="ak-market-eyebrow">{{ $isExternalAiReport ? __('Externer Marktbericht') : __('Regelbasierter Marktbericht') }}</p>
            <span class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.06] px-2.5 py-1 text-[9px] font-black uppercase tracking-[.1em] text-cyan-400">{{ $isExternalAiReport ? __('Aktuelle Web-Recherche') : __('Datenbasierte Auswertung') }}</span>
        </div>
        <h2 class="mt-3 text-2xl font-black tracking-[-.025em] text-[var(--ak-text)]">{{ $analysisHeadline }}</h2>
        <p class="mt-3 max-w-5xl text-sm leading-6 text-[var(--ak-muted)]">{{ $analysisSummary }}</p>
        @if ($analysisMetrics->isNotEmpty())
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($analysisMetrics as $metric)
                    <div class="rounded-xl border border-cyan-400/15 bg-cyan-400/[.04] px-3 py-2.5">
                        <p class="text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-lg font-black text-[var(--ak-text)]">{{ $metric['value'] }}</p>
                        <p class="text-[9px] text-[var(--ak-muted)]">{{ $metric['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
        @if (!empty($marketAnalysis['breadth']))
            <div class="ak-market-briefing-note mt-4">
                <x-heroicon-o-chart-pie class="h-4 w-4 shrink-0" />
                <span>{{ $marketAnalysis['breadth'] }}</span>
            </div>
        @endif
        <footer class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--ak-border)] pt-3 text-[9px] font-semibold uppercase tracking-[.1em] text-[var(--ak-muted)]">
            <span>{{ $isExternalAiReport ? __('Markteinschätzung mit aktuellen externen Quellen · keine Anlageberatung') : __('Regelbasierte Auswertung aktueller Marktdaten · keine Anlageberatung') }}</span>
            @if (!empty($marketAnalysis['date']))<span>{{ __('Analyse vom') }} {{ \Illuminate\Support\Carbon::parse($marketAnalysis['date'])->format('d.m.Y') }}</span>@endif
        </footer>
    </article>

    <section class="mt-4" aria-labelledby="market-opportunities-risks-title">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="ak-market-eyebrow">{{ __('Bericht') }}</p>
                <h2 id="market-opportunities-risks-title" class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Chancen & Risiken') }}</h2>
            </div>
            @if (!empty($marketAnalysis['date']))
                <span class="text-[9px] font-bold uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('Analyse vom') }} {{ \Illuminate\Support\Carbon::parse($marketAnalysis['date'])->format('d.m.Y') }}</span>
            @endif
        </div>
        @if($isRegionalFreeView)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.15em] text-amber-400">{{ __('Free · Regionales Aktienuniversum') }}</p>
                    <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Chancen und Risiken basieren auf den 100 wichtigsten Aktien deiner Region (:country).', ['country' => $regionalCountry]) }}</p>
                </div>
                <a href="{{ route('pricing') }}" class="inline-flex h-9 items-center rounded-lg border border-amber-400/30 bg-amber-400/[.08] px-3 text-[9px] font-black text-amber-300 transition hover:bg-amber-400/[.15]">{{ __('Internationale Auswahl ab Plus') }} →</a>
            </div>
        @endif
        <div class="grid gap-4">
            @foreach ([
                [__('Chancen'), $opportunities, 'opportunity', 'text-emerald-500', '↗'],
                [__('Risiken'), $risks, 'risk', 'text-rose-500', '!'],
                [__('Beobachtungsliste'), $watchlist, 'watch', 'text-amber-500', '◉'],
            ] as [$title, $items, $tone, $titleClass, $symbol])
                <article class="ak-analysis-panel ak-analysis-panel-{{ $tone }} ak-detail-panel ak-standard-card ak-card ak-card-static overflow-hidden p-5">
                    <div class="ak-analysis-card-head ak-detail-card-head -mx-5 -mt-5 flex items-center justify-between gap-3 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="ak-analysis-icon grid h-9 w-9 place-items-center rounded-xl border text-lg font-black {{ $titleClass }}">{{ $symbol }}</span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.18em] text-[var(--ak-muted)]">{{ __('Markteinschätzung') }}</p>
                                <h3 class="mt-0.5 text-lg font-black {{ $titleClass }}">{{ $title }}</h3>
                            </div>
                        </div>
                        <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1 text-[10px] font-black tabular-nums text-[var(--ak-muted)]">{{ $items->count() }}</span>
                    </div>
                    <ul class="mt-4 grid gap-2.5 md:grid-cols-2 xl:grid-cols-3">
                        @forelse ($items as $key => $item)
                            <li class="ak-analysis-copy min-w-0 flex gap-3 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3 text-xs leading-[1.45]">
                                <span class="ak-analysis-number grid h-6 w-6 shrink-0 place-items-center rounded-lg text-[10px] font-black {{ $titleClass }}">{{ $loop->iteration }}</span>
                                <span class="min-w-0 break-words pt-0.5 [overflow-wrap:anywhere]">
                                    @if (!is_numeric($key))<strong class="text-[var(--ak-text)]">{{ __((string) $key) }}: </strong>@endif
                                    {{ $analysisItemText($item) }}
                                </span>
                            </li>
                        @empty
                            <li class="text-xs text-[var(--ak-muted)]">—</li>
                        @endforelse
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    @if ($reportSources->isNotEmpty())
        <section class="mt-4" aria-labelledby="report-sources-title">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="ak-market-eyebrow">{{ __('Quellennachweis') }}</p>
                    <h2 id="report-sources-title" class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Im Marktbericht verwendete Quellen') }}</h2>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ $reportSources->count() }} {{ __('Quellen') }}</span>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($reportSources as $source)
                    <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="ak-dashboard-card ak-standard-card group p-4 transition hover:border-cyan-400/40">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-400">{{ $source['publisher'] }}</p>
                                <h3 class="mt-1 text-sm font-black leading-5 text-[var(--ak-text)]">{{ $source['title'] }}</h3>
                                @if ($source['used_for'])<p class="mt-1 text-[10px] leading-4 text-[var(--ak-muted)]">{{ $source['used_for'] }}</p>@endif
                            </div>
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 shrink-0 text-[var(--ak-muted)] transition group-hover:text-cyan-400" />
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-4" aria-labelledby="official-market-sources-title">
        <div class="mb-3">
            <p class="ak-market-eyebrow">{{ __('Externer Kontext') }}</p>
            <h2 id="official-market-sources-title" class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Offizielle Markt- und Konjunkturberichte') }}</h2>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Originalquellen für Geldpolitik, Inflation und Konjunktur.') }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['EZB', __('Economic Bulletin'), __('Euroraum · Geldpolitik und Inflation'), 'https://www.ecb.europa.eu/press/economic-bulletin/html/index.en.html'],
                [__('Bundesbank'), __('Monatsbericht'), __('Deutschland · Wirtschaft und Finanzmärkte'), 'https://www.bundesbank.de/de/publikationen/berichte/monatsberichte'],
                ['IWF', __('World Economic Outlook'), __('Globale Konjunktur und Risikoszenarien'), 'https://www.imf.org/en/Publications/WEO'],
                ['OECD', __('Economic Outlook'), __('Internationale Prognosen und Risiken'), 'https://www.oecd.org/en/topics/economic-outlook.html'],
            ] as [$source, $title, $description, $url])
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="ak-dashboard-card ak-standard-card group p-4 transition hover:border-cyan-400/40">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-400">{{ $source }}</span>
                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-[var(--ak-muted)] transition group-hover:text-cyan-400" />
                    </div>
                    <h3 class="mt-2 text-sm font-black text-[var(--ak-text)]">{{ $title }}</h3>
                    <p class="mt-1 text-[10px] leading-4 text-[var(--ak-muted)]">{{ $description }}</p>
                </a>
            @endforeach
        </div>
    </section>

</section>
