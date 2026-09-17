<x-app-layout>
    <style>
        #dashboard-concept-page { max-width: 96rem; margin-inline: auto; padding: 1.25rem .5rem 3rem; }
        #dashboard-concept-page .concept-card {
            border: 1px solid var(--ak-border);
            background: var(--ak-card);
            border-radius: 1.25rem;
            box-shadow: var(--ak-shadow);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        #dashboard-concept-page .concept-card:hover {
            transform: translateY(-3px);
            border-color: var(--ak-border-strong);
            box-shadow: var(--ak-shadow-hover);
        }
        #dashboard-concept-page .concept-layout {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            #dashboard-concept-page .concept-layout { grid-template-columns: 96px minmax(0, 1fr); align-items: start; }
        }
        /* Left column: a fixed 1-wide grid, not flex-wrap, so it can never
           accidentally reflow into more than one column. */
        #dashboard-concept-page .concept-icon-grid {
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: repeat(10, 1fr);
            gap: .5rem;
            padding: .75rem;
            background: none;
            border: 0;
            box-shadow: none;
        }
        #dashboard-concept-page .concept-icon-tile {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem;
            aspect-ratio: 1 / 1;
            border-radius: .9rem;
            border: 1.5px solid var(--ak-border-strong);
            background: none;
            color: var(--ak-text);
            text-decoration: none;
            cursor: pointer;
            width: 100%;
            transition: border-color .15s ease, background .15s ease, color .15s ease;
        }
        #dashboard-concept-page .concept-icon-tile:hover {
            border-color: color-mix(in srgb, #22d3ee 45%, transparent);
        }
        /* Highlighted state for the currently active section. */
        #dashboard-concept-page .concept-icon-tile.is-active {
            border-color: #22d3ee;
            background: color-mix(in srgb, #22d3ee 14%, transparent);
            color: #22d3ee;
        }
        #dashboard-concept-page .concept-icon-tile svg { width: 1.15rem; height: 1.15rem; flex: none; }
        #dashboard-concept-page .concept-icon-tile small { font-size: .5rem; font-weight: 800; text-align: center; line-height: 1.05; }
        #dashboard-concept-page .concept-main-card { padding: 1.25rem; }
        #dashboard-concept-page .concept-main-header { display: flex; align-items: center; gap: .65rem; margin-bottom: .9rem; }
        #dashboard-concept-page .concept-main-header b { display: block; font-size: 1rem; font-weight: 900; color: var(--ak-text); }
        #dashboard-concept-page .concept-main-header small { display: block; font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .14em; }
        #dashboard-concept-page .concept-icon-badge { display: grid; height: 2.25rem; width: 2.25rem; flex: none; place-items: center; border-radius: .6rem; border: 1px solid; }
        #dashboard-concept-page .concept-metric { display: flex; flex-direction: column; gap: .15rem; }
        #dashboard-concept-page .concept-metric b { font-size: 1.4rem; font-weight: 900; color: var(--ak-text); }
        #dashboard-concept-page .concept-metric small { font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ak-muted); }
        #dashboard-concept-page .concept-empty {
            display: grid; place-items: center; min-height: 8rem; padding: 1rem; border: 1px dashed var(--ak-border); border-radius: .8rem; text-align: center; font-size: .78rem; font-weight: 700; color: var(--ak-muted);
        }
        #dashboard-concept-page .concept-list-item {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem; border-radius: .6rem; padding: .5rem .6rem;
            border: 1px solid var(--ak-border); font-size: .78rem; font-weight: 700; color: var(--ak-text);
            text-decoration: none; transition: border-color .15s ease;
        }
        #dashboard-concept-page a.concept-list-item:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); }
        #dashboard-concept-page .concept-open-link { margin-top: 1rem; display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 800; }
        /* Full-width opportunity cards (champion / candidates / signal changes) */
        #dashboard-concept-page .concept-opp-stack { display: grid; gap: .6rem; }
        #dashboard-concept-page .concept-opp-card {
            display: block; width: 100%; border-radius: .85rem; border: 1px solid var(--ak-border);
            background: var(--ak-surface-muted, transparent); padding: .85rem 1rem; text-decoration: none; color: var(--ak-text);
            transition: border-color .15s ease;
        }
        #dashboard-concept-page .concept-opp-card:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); }
        #dashboard-concept-page .concept-opp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .75rem; }
        #dashboard-concept-page .concept-opp-badge {
            flex: none; border-radius: .4rem; border: 1px solid var(--ak-border); padding: .15rem .5rem;
            font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); white-space: nowrap;
        }
        #dashboard-concept-page .concept-horizon-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        #dashboard-concept-page .concept-horizon-bar .bar-track { position: relative; height: .35rem; border-radius: .3rem; background: color-mix(in srgb, var(--ak-border) 70%, transparent); overflow: hidden; }
        #dashboard-concept-page .concept-horizon-bar .bar-fill { position: absolute; inset: 0 auto 0 0; border-radius: .3rem; }
        #dashboard-concept-page .concept-opp-footer { margin-top: .8rem; padding-top: .65rem; border-top: 1px solid var(--ak-border); font-size: .72rem; line-height: 1.4; }
        #dashboard-concept-page .concept-drift-toggle {
            margin-top: .6rem; border: 0; background: none; padding: 0; cursor: pointer;
            font-size: .68rem; font-weight: 800; color: #22d3ee;
        }
        #dashboard-concept-page .concept-drift-toggle:hover { color: #67e8f9; }
        #dashboard-concept-page .concept-drift-events { margin-top: .6rem; display: grid; gap: .35rem; border-top: 1px solid var(--ak-border); padding-top: .6rem; }
        #dashboard-concept-page .concept-drift-event-row {
            display: flex; align-items: center; gap: .6rem; font-size: .68rem; font-weight: 700; color: var(--ak-text);
        }
        #dashboard-concept-page .concept-panel-score { margin-top: .6rem; display: grid; gap: .3rem; border-top: 1px solid var(--ak-border); padding-top: .6rem; }
        #dashboard-concept-page .concept-panel-score-label { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ak-muted); }
        #dashboard-concept-page .concept-panel-score-corr { margin-top: .15rem; font-size: .64rem; font-weight: 700; color: var(--ak-muted); }
        #dashboard-concept-page .concept-market-overview-card { margin-top: 1rem; }
        #dashboard-concept-page .concept-market-overview-card #dashboard-market-overview-card { height: auto !important; min-height: 0 !important; }
        /* "Klassisches Dashboard" tab: the real dashboard's middle column
           (Champion+Alternativen, Remote-Aktien-Übersicht, Zusätzliche
           Kandidaten) and right column (Bestätigte POSITIV-Signale,
           Earnings, Marktausblick) as two columns, matching the real page's
           layout - not its full drag/resize-customizable 12-column bento
           grid, which these cards' own CSS classes are tightly coupled to
           (see below) and which only really resolves correctly with every
           other dashboard card also present. */
        #dashboard-concept-page .concept-classic-dashboard-top { width: 100%; min-width: 0; margin-bottom: 1rem; }
        /* Strategiedepots and Letzte Transaktionen live in their own row,
           separate from the two free-flowing columns below, so this one can
           use align-items: stretch to force both cards to the same height -
           doing that on the whole grid would also force every other card
           pair (Champion vs. Anstehende Termine, etc.) to match, which
           don't share a natural height relationship. */
        #dashboard-concept-page .concept-classic-dashboard-row { display: grid; gap: 1rem; width: 100%; min-width: 0; grid-template-columns: 1fr; align-items: stretch; margin-bottom: 1rem; }
        #dashboard-concept-page .concept-classic-dashboard-row > article { display: flex; flex-direction: column; }
        @media (min-width: 1100px) {
            #dashboard-concept-page .concept-classic-dashboard-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        }
        #dashboard-concept-page .concept-classic-dashboard-grid { display: grid; gap: 1rem; width: 100%; min-width: 0; grid-template-columns: 1fr; align-items: start; }
        @media (min-width: 1100px) {
            #dashboard-concept-page .concept-classic-dashboard-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        }
        #dashboard-concept-page .concept-classic-dashboard-col { display: grid; gap: 1rem; min-width: 0; align-content: start; }
        /* segmented-score-donut ships no CSS of its own - every page that
           uses it defines its own sizing/ring styling locally, so this
           page needs the same base rules (fill:none is the important one -
           without it the ring's <circle> elements render solid black). */
        #dashboard-concept-page .segmented-score { position: relative; display: grid; width: 4rem; height: 4rem; place-items: center; }
        #dashboard-concept-page .segmented-score-ring { position: absolute; inset: 0; width: 100%; height: 100%; transform: rotate(-90deg); }
        #dashboard-concept-page .segmented-score-sector { fill: none; stroke-width: 8; stroke-linecap: butt; opacity: .72; }
        #dashboard-concept-page .segmented-score-sector.is-active { opacity: 1; }
        #dashboard-concept-page .segmented-score-sector.is-end { stroke-width: 11; filter: drop-shadow(0 1px 2px rgba(16, 32, 52, .22)); }
        #dashboard-concept-page .segmented-score b { position: relative; font-size: 1.05rem; font-weight: 900; }
        /* The real dashboard's own <style> block (dashboard-styles.blade.php,
           included above) pins these same card classes into an explicit
           12-column/N-row bento grid ("grid-column: 5 / span 4; grid-row:
           1 / -1; height: 100%", etc.) - bare class selectors that keep
           matching here too even though this preview's grid has none of
           those columns/rows, producing empty stretched space and cards
           escaping their card border. Force every such card back to normal
           block flow, full width of its own grid column, natural height.
           Scoped to the whole concept page (not just the two columns) so
           it also covers the full-width "Aktuelle Remote-Aktien" row above
           them. */
        #dashboard-concept-page [class*="dashboard-bento-"],
        #dashboard-concept-page #dashboard-middle-column,
        #dashboard-concept-page #dashboard-center-combined-card,
        #dashboard-concept-page .dashboard-center-combined-card,
        #dashboard-concept-page #dashboard-market-overview-card,
        #dashboard-concept-page #dashboard-daily-tips-card,
        #dashboard-concept-page .dashboard-daily-tips {
            grid-column: auto !important;
            grid-row: auto !important;
            align-self: auto !important;
            position: static !important;
            width: 100% !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
        }
        /* A desktop-only breakpoint in dashboard-styles.blade.php sets a bare
           ".dashboard-right-column { display: none }" that only the real
           page's #personal-dashboard ancestor overrides back to visible -
           this preview has no such ancestor, so the right column would
           silently vanish above 1280px without this. */
        #dashboard-concept-page .concept-classic-dashboard-col .dashboard-right-column {
            display: flex !important;
            flex-direction: column;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
        }
        /* Without an explicit width, this column's grid-item default
           min-width:auto lets its cards' intrinsic content (KPI tiles etc.)
           push it past the 22rem track - the real dashboard never hits this
           because .dashboard-right-column is display:none at this
           breakpoint there, so its content never has to fit a narrow
           column. Clamp every card (and anything inside them) back down. */
        #dashboard-concept-page .concept-classic-dashboard-col .dashboard-right-column,
        #dashboard-concept-page .concept-classic-dashboard-col .dashboard-right-column > * {
            min-width: 0 !important;
            max-width: 100% !important;
        }
        #dashboard-concept-page .concept-classic-dashboard-col .dashboard-right-column * {
            overflow-wrap: break-word;
        }
    </style>

    <div id="dashboard-concept-page" x-data="{ active: null }">
        <header class="mb-5">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Konzept') }}</p>
            <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Persönlicher Bereich') }}</h1>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Links: 1×8-Symbolraster. Ein Klick auf ein Symbol wechselt den Inhalt rechts zur Kurzübersicht dieses Bereichs.') }}</p>
            <a href="{{ route('dashboard') }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">← {{ __('Zurück zum Dashboard') }}</a>
            <a href="{{ route('upcoming-events.index') }}" class="mt-1 ml-3 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">{{ __('Anstehende News') }} →</a>
        </header>

        <div class="concept-layout">
            {{-- Left: 1x8 icon grid - stays exactly as-is, just gains an active state --}}
            <nav class="concept-card concept-icon-grid" aria-label="{{ __('Persönlicher Bereich') }}">
                @foreach($leftIcons as $item)
                    <button
                        type="button"
                        class="concept-icon-tile"
                        :class="{ 'is-active': active === '{{ $item['id'] }}' }"
                        @click="active = (active === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')"
                        title="{{ $item['label'] }}"
                    >
                        <x-dynamic-component :component="$item['icon']" />
                        <small>{{ $item['label'] }}</small>
                    </button>
                @endforeach
            </nav>

            {{-- Right: Handelsmöglichkeiten by default, swaps to the active section's short overview --}}
            <div>
                <section class="concept-card concept-main-card" x-show="active === null" x-cloak>
                    <div class="concept-main-header">
                        <span class="concept-icon-badge border-amber-400/25 bg-amber-400/10 text-amber-400"><x-heroicon-o-bolt class="h-5 w-5" /></span>
                        <span>
                            <small class="text-amber-500">{{ __('Kurzübersicht') }}</small>
                            <b>{{ __('Aktuelle Handelsmöglichkeiten') }}</b>
                        </span>
                    </div>

                    <p class="mb-2 text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Drei-Faktoren-Champion') }}</p>
                    @if($opportunities['champion'])
                        <div class="concept-opp-stack">
                            <x-dashboard-concept.opportunity-card :card="$opportunities['champion']" />
                        </div>
                    @else
                        <div class="concept-empty">{{ __('Kein Champion aktuell verfügbar.') }}</div>
                    @endif

                    <p class="mb-2 mt-4 text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Weitere Kandidaten') }}</p>
                    @if(count($opportunities['candidates']))
                        <div class="concept-opp-stack">
                            @foreach($opportunities['candidates'] as $card)
                                <x-dashboard-concept.opportunity-card :card="$card" />
                            @endforeach
                        </div>
                    @else
                        <div class="concept-empty">{{ __('Aktuell keine weiteren Kandidaten verfügbar.') }}</div>
                    @endif

                    <p class="mb-2 mt-4 text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Signalwechsel') }}</p>
                    @if(count($opportunities['signalChanges']))
                        <div class="concept-opp-stack">
                            @foreach($opportunities['signalChanges'] as $card)
                                <x-dashboard-concept.opportunity-card :card="$card" />
                            @endforeach
                        </div>
                    @else
                        <div class="concept-empty">{{ __('Keine aktuellen Signalwechsel.') }}</div>
                    @endif
                </section>

                @foreach($sections as $section)
                    <section class="concept-card concept-main-card" x-show="active === '{{ $section['id'] }}'" x-cloak>
                        <div class="concept-main-header">
                            <span class="concept-icon-badge border-cyan-400/25 bg-cyan-400/10 text-cyan-400"><x-dynamic-component :component="$section['icon']" class="h-5 w-5" /></span>
                            <span>
                                <small class="text-cyan-500">{{ __('Kurzübersicht') }}</small>
                                <b>{{ $section['label'] }}</b>
                            </span>
                        </div>

                        @if($section['kind'] === 'today-focus')
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-stretch">
                                @foreach($section['highlights'] as $highlight)
                                    @php $wrapper = $highlight['url'] ? 'a' : 'div'; $details = $highlight['details'] ?? null; @endphp
                                    <{{ $wrapper }} @if($highlight['url']) href="{{ $highlight['url'] }}" @endif class="concept-card flex h-full flex-col p-4 hover:border-{{ $highlight['color'] }}-400">
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-{{ $highlight['color'] }}-500/10 text-{{ $highlight['color'] }}-500">
                                                <x-dynamic-component :component="$highlight['icon']" class="h-4.5 w-4.5" />
                                            </span>
                                            <div class="min-w-0">
                                                <b class="block text-[12px] font-black text-[var(--ak-text)]">{{ $highlight['label'] }}</b>
                                                <small class="block text-[9px] text-[var(--ak-muted)]">{{ $highlight['subtitle'] }}</small>
                                            </div>
                                        </div>

                                        @if(($highlight['kind'] ?? null) === 'indicators')
                                            @php $indicators = $highlight['indicators'] ?? null; @endphp
                                            @if($indicators)
                                                <div class="mb-2 text-[8px] text-[var(--ak-muted)]">{{ __('Stand: :date', ['date' => \Illuminate\Support\Carbon::parse($indicators['as_of'])->format('d.m.Y')]) }}</div>
                                                <div class="grid grid-cols-2 gap-1.5 text-[9px]">
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1.5">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Relative Stärke (14 Tage)') }}</span>
                                                        <span class="font-bold text-[var(--ak-text)]">{{ $indicators['rsi'] }}</span>
                                                        <span class="block text-[8px] text-[var(--ak-muted)]">{{ $indicators['rsi_state'] }}</span>
                                                    </div>
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1.5">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Trendfolge-Indikator') }}</span>
                                                        <span class="font-bold {{ $indicators['macd_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['macd'] }}</span>
                                                        <span class="block text-[8px] text-[var(--ak-muted)]">{{ $indicators['macd_bullish'] ? __('Bullisch') : __('Bärisch') }}</span>
                                                    </div>
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1.5 col-span-2">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Kurstrend') }}</span>
                                                        <span class="font-bold {{ $indicators['trend_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['trend'] }}</span>
                                                        <span class="block text-[8px] text-[var(--ak-muted)]">{{ __('gleitender 20-Tage- vs. 50-Tage-Durchschnitt') }}</span>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex flex-1 items-center justify-center text-[9px] text-[var(--ak-muted)] italic">{{ __('Keine Daten heute') }}</div>
                                            @endif
                                        @elseif($highlight['data'] && $details)
                                            <div class="flex items-center gap-1.5 mb-2">
                                                <span class="text-base leading-none">{{ $details['country_flag'] }}</span>
                                                <span class="text-[13px] font-black text-[var(--ak-text)]">{{ explode(' ', $highlight['data'])[0] }}</span>
                                            </div>
                                            @if($details['sector'])
                                                <div class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)] mb-2">{{ $details['sector'] }}</div>
                                            @endif

                                            <div class="mb-2 rounded-lg bg-{{ $highlight['color'] }}-500/[.08] px-2.5 py-2">
                                                <div class="text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $highlight['metric_label'] }}</div>
                                                <div class="text-[15px] font-black text-{{ $highlight['color'] }}-500">{{ $highlight['metric_value'] }}</div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-1.5 mb-2 text-[9px]">
                                                @if($details['current_price'])
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Kurs') }}</span>
                                                        <span class="font-bold text-[var(--ak-text)]">{{ number_format($details['current_price'], 2, ',', '.') }} {{ $details['currency'] }}</span>
                                                    </div>
                                                @endif
                                                @if($details['risk'] !== null)
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Risiko') }}</span>
                                                        <span class="font-bold text-[var(--ak-text)]">{{ $details['risk'] }}/10</span>
                                                    </div>
                                                @endif
                                                @if($details['confidence'] !== null)
                                                    <div class="rounded bg-[var(--ak-surface-muted)] px-2 py-1">
                                                        <span class="block text-[var(--ak-muted)]">{{ __('Konfidenz') }}</span>
                                                        <span class="font-bold text-[var(--ak-text)]">{{ $details['confidence'] }}%</span>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($highlight['analog'])
                                                @php
                                                    $analog = $highlight['analog'];
                                                    $daysAgo = (int) round(\Illuminate\Support\Carbon::parse($analog['signal_date'])->diffInDays(now()));
                                                    $outcomeClass = $analog['outcome_pct'] >= 0 ? 'text-emerald-500' : 'text-rose-500';
                                                @endphp
                                                {{-- A <div>, not <a>: this sits inside the card's own link (nested
                                                     anchors are invalid HTML), and the analog's stock can differ
                                                     from the card's own, so it isn't safe to just reuse the outer
                                                     href either. --}}
                                                <div class="mb-2 flex items-center gap-2 rounded-lg bg-[var(--ak-surface-muted)] px-2 py-1.5">
                                                    <span class="min-w-0 flex-1 text-[9px] leading-4 text-[var(--ak-muted)]">
                                                        <span class="text-[var(--ak-text)]">•</span>
                                                        @if($analog['type'] === 'same_stock')
                                                            {{ __('Ähnlicher Fall vor :days Tagen', ['days' => $daysAgo]) }}
                                                        @else
                                                            {{ $analog['analog_symbol'] }} {{ __('vor :days Tagen ähnlich', ['days' => $daysAgo]) }}
                                                        @endif
                                                        · <span class="font-black {{ $outcomeClass }}">{{ $analog['outcome_pct'] >= 0 ? '+' : '' }}{{ number_format($analog['outcome_pct'], 1, ',', '.') }}%</span>
                                                    </span>
                                                    @if($analog['sparkline'])
                                                        <svg viewBox="0 0 100 32" class="h-6 w-14 shrink-0" preserveAspectRatio="none">
                                                            <polyline points="{{ $analog['sparkline'] }}" fill="none" stroke="currentColor" stroke-width="3" class="{{ $outcomeClass }}" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    @endif
                                                </div>
                                            @endif

                                            @if($highlight['insight'])
                                                <div class="mt-auto pt-2 border-t border-[var(--ak-border)] text-[9px] leading-4 text-[var(--ak-muted)]">{{ $highlight['insight'] }}</div>
                                            @endif
                                        @else
                                            <div class="flex flex-1 items-center justify-center text-[9px] text-[var(--ak-muted)] italic">{{ __('Keine Daten heute') }}</div>
                                        @endif
                                    </{{ $wrapper }}>
                                @endforeach
                            </div>
                        @elseif($section['kind'] === 'list')
                            @if(count($section['items']))
                                <div class="grid gap-1.5 sm:grid-cols-2">
                                    @foreach($section['items'] as $name)
                                        <div class="concept-list-item">{{ $name }}</div>
                                    @endforeach
                                </div>
                                @if($section['total'] > count($section['items']))
                                    <p class="mt-2 text-[11px] font-black text-[var(--ak-muted)]">{{ __('Insgesamt :count', ['count' => $section['total']]) }}</p>
                                @endif
                            @else
                                <div class="concept-empty">{{ $section['emptyText'] }}</div>
                            @endif
                        @elseif($section['kind'] === 'market')
                            @if($section['available'] && $section['assessment'])
                                <p class="text-sm font-bold text-[var(--ak-text)]">{{ $section['assessment']['status'] }} · {{ number_format($section['assessment']['score'], 0) }}/100</p>
                                <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $section['assessment']['summary'] }}</p>
                                @if(count($section['metrics']))
                                    <div class="mt-3 grid grid-cols-2 gap-3 border-t border-[var(--ak-border)] pt-3 sm:grid-cols-4">
                                        @foreach($section['metrics'] as $metric)
                                            <div class="concept-metric">
                                                <b class="text-base">{{ $metric['value'] }}</b>
                                                <small>{{ $metric['label'] }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <div class="concept-empty">{{ __('Marktdaten aktuell nicht verfügbar.') }}</div>
                            @endif

                            {{-- The Screener/dashboard's "Aktuelle Remote-Aktien"
                                 stock count + signal-distribution card - same
                                 partial the classic-dashboard tab uses. --}}
                            @php $profileUniverseStats = $section['profileUniverseStats']; @endphp
                            @include('partials.dashboard-styles')
                            <div class="concept-market-overview-card">
                                @include('partials.dashboard-market-overview-card')
                            </div>
                        @elseif($section['kind'] === 'events')
                            @if(count($section['events']))
                                <div class="grid gap-1.5">
                                    @foreach($section['events'] as $event)
                                        <a href="{{ $event['url'] }}" class="concept-list-item">
                                            <span class="min-w-0 flex-1 truncate">{{ $event['name'] }} ({{ $event['symbol'] }})</span>
                                            <span class="shrink-0 text-[10px] font-black text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d.m.') }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="concept-empty">{{ $section['emptyText'] }}</div>
                            @endif
                        @elseif($section['kind'] === 'earnings-drift')
                            @if(count($section['rows']))
                                <div class="concept-opp-stack">
                                    @foreach($section['rows'] as $row)
                                        <div class="concept-opp-card" x-data="{ expanded: false }">
                                            <div class="concept-opp-head">
                                                <a href="{{ $row['url'] }}" class="min-w-0 no-underline">
                                                    <b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $row['name'] }}</b>
                                                    <small class="mt-0.5 block text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $row['symbol'] }} · {{ __('nächste Zahlen') }} {{ \Illuminate\Support\Carbon::parse($row['nextDate'])->format('d.m.Y') }}</small>
                                                </a>
                                                <span class="concept-opp-badge">{{ $row['tendency'] }}</span>
                                            </div>
                                            <p class="text-xs text-[var(--ak-muted)]">
                                                n={{ $row['n'] }} ·
                                                {{ __('Ø Kurs +3T bei Beat') }}:
                                                <span class="font-black {{ ($row['post3dBeat'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $row['post3dBeat'] === null ? '—' : (($row['post3dBeat'] >= 0 ? '+' : '').number_format($row['post3dBeat'], 2, ',', '.').' %') }}</span>
                                                ·
                                                {{ __('bei Miss') }}:
                                                <span class="font-black {{ ($row['post3dMiss'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $row['post3dMiss'] === null ? '—' : (($row['post3dMiss'] >= 0 ? '+' : '').number_format($row['post3dMiss'], 2, ',', '.').' %') }}</span>
                                            </p>

                                            <button type="button" class="concept-drift-toggle" @click="expanded = !expanded">
                                                <span x-show="!expanded">{{ __('Einzelereignisse anzeigen') }} ({{ count($row['events']) }}) ↓</span>
                                                <span x-show="expanded" x-cloak>{{ __('Einzelereignisse ausblenden') }} ↑</span>
                                            </button>

                                            <div x-show="expanded" x-cloak class="concept-drift-events">
                                                @foreach($row['events'] as $event)
                                                    <div class="concept-drift-event-row">
                                                        <span class="shrink-0 tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d.m.Y') }}</span>
                                                        <span class="min-w-0 flex-1 truncate">{{ __('Schätzung') }} {{ $event['epsEstimate'] === null ? '—' : number_format($event['epsEstimate'], 2, ',', '.') }} / {{ __('Ist') }} {{ $event['epsActual'] === null ? '—' : number_format($event['epsActual'], 2, ',', '.') }} ({{ $event['surprisePercent'] === null ? '—' : (($event['surprisePercent'] >= 0 ? '+' : '').number_format($event['surprisePercent'], 1, ',', '.').' %') }})</span>
                                                        <span class="shrink-0 tabular-nums {{ ($event['post3d'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">-3T {{ $event['pre3d'] === null ? '—' : number_format($event['pre3d'], 1, ',', '.').'%' }} · +3T {{ $event['post3d'] === null ? '—' : number_format($event['post3d'], 1, ',', '.').'%' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if(count($row['panelDeciles']))
                                                <div class="concept-panel-score">
                                                    <p class="concept-panel-score-label">{{ __('Panel-Score (Extremdezile)') }}</p>
                                                    @foreach($row['panelDeciles'] as $decile)
                                                        <div class="concept-drift-event-row">
                                                            <span class="shrink-0 tabular-nums text-[var(--ak-muted)]">{{ __('Dezil') }} {{ $decile['decile'] }}</span>
                                                            <span class="min-w-0 flex-1 truncate">n={{ $decile['n'] }}</span>
                                                            <span class="shrink-0 tabular-nums {{ $decile['avgForwardReturn'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">Ø 20T {{ $decile['avgForwardReturn'] >= 0 ? '+' : '' }}{{ number_format($decile['avgForwardReturn'] * 100, 2, ',', '.') }} %</span>
                                                        </div>
                                                    @endforeach
                                                    @if($row['panelCorrelation'])
                                                        <p class="concept-panel-score-corr">r = {{ $row['panelCorrelation']['corrPercentile'] === null ? '—' : number_format($row['panelCorrelation']['corrPercentile'], 3, ',', '.') }} ({{ __('Perzentil') }}, n={{ $row['panelCorrelation']['n'] }})</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="concept-empty">{{ $section['emptyText'] }}</div>
                            @endif
                        @elseif($section['kind'] === 'stock-of-day')
                            @if($section['available'])
                                <a href="{{ $section['url'] }}" class="block text-decoration-none">
                                    <div class="concept-opp-card">
                                        <div class="concept-opp-head">
                                            <span class="min-w-0">
                                                <span class="flex min-w-0 items-center gap-1.5">
                                                    <span class="shrink-0">{{ $transactionCountryFlags[strtoupper((string) $section['country'])] ?? '🌐' }}</span>
                                                    <b class="min-w-0 truncate font-bold text-[var(--ak-text)]">{{ $section['name'] }}</b>
                                                </span>
                                                <small class="mt-0.5 block truncate text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $section['symbol'] }}</small>
                                            </span>
                                            <span class="concept-opp-badge">{{ __('Beste Prognose') }}</span>
                                        </div>
                                        @if($section['currentPrice'] !== null)
                                            <p class="text-xs text-[var(--ak-muted)]">
                                                {{ __('Aktueller Kurs') }}: <span class="font-black text-[var(--ak-text)]">{{ number_format($section['currentPrice'], 2, ',', '.') }} €</span>
                                            </p>
                                        @endif
                                        @if($section['compositeScore'] !== null || $section['riskScore'] !== null)
                                            <div class="mt-2 flex items-center gap-3 border-t border-[var(--ak-border)] pt-2">
                                                @if($section['compositeScore'] !== null)
                                                    <div class="flex items-center gap-1">
                                                        <span class="text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Score') }}</span>
                                                        <span class="text-[11px] font-black text-[var(--ak-text)]">{{ number_format($section['compositeScore'], 0, ',', '.') }}</span>
                                                    </div>
                                                @endif
                                                @if($section['riskScore'] !== null)
                                                    <div class="flex items-center gap-1">
                                                        <span class="text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Risiko') }}</span>
                                                        <span class="text-[11px] font-black text-[var(--ak-text)]">{{ number_format($section['riskScore'] / 10, 1, ',', '.') }}/10</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="concept-horizon-row mt-3 border-t border-[var(--ak-border)] pt-3">
                                            @foreach(['10T', '20T', '40T'] as $horizon)
                                                @php $return = $section['horizons'][$horizon]; @endphp
                                                <div class="flex flex-col items-center gap-1">
                                                    <small class="text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ $horizon }}</small>
                                                    <span class="text-[13px] font-black {{ $return === null ? 'text-[var(--ak-muted)]' : ($return >= 0 ? 'text-emerald-500' : 'text-rose-500') }}">
                                                        {{ $return === null ? '—' : (($return >= 0 ? '+' : '').number_format($return, 1, ',', '.').' %') }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </a>
                            @else
                                <div class="concept-empty">{{ __('Keine Prognosen für heute verfügbar.') }}</div>
                            @endif
                        @elseif($section['kind'] === 'news')
                            @if($section['generated_at'])
                                <p class="mb-3 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                    {{ __('Stand: :date · letzte 48h', ['date' => \Illuminate\Support\Carbon::parse($section['generated_at'])->format('d.m.Y H:i')]) }}
                                </p>
                            @endif
                            @if(count($section['items']) > 0)
                                <div class="space-y-2">
                                    @foreach($section['items'] as $news)
                                        @php
                                            $sentimentClass = $news['sentiment'] === null ? 'text-[var(--ak-muted)]' : ($news['sentiment'] > 0.15 ? 'text-emerald-500' : ($news['sentiment'] < -0.15 ? 'text-rose-500' : 'text-[var(--ak-muted)]'));
                                        @endphp
                                        <a @if($news['url']) href="{{ $news['url'] }}" @endif class="concept-card block p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                        <span>{{ $news['symbol'] }}</span>
                                                        @if($news['sector'])<span>· {{ $news['sector'] }}</span>@endif
                                                        <span>· {{ \Illuminate\Support\Carbon::parse($news['published_at'])->diffForHumans() }}</span>
                                                    </div>
                                                    <b class="mt-1 block text-[12px] leading-4 text-[var(--ak-text)]">{{ $news['headline'] }}</b>
                                                    @if($news['summary'])
                                                        <p class="mt-1 text-[10px] leading-4 text-[var(--ak-muted)]">{{ \Illuminate\Support\Str::limit($news['summary'], 180) }}</p>
                                                    @endif
                                                </div>
                                                @if($news['sentiment'] !== null)
                                                    <span class="shrink-0 text-[9px] font-black {{ $sentimentClass }}">
                                                        {{ $news['sentiment'] > 0 ? '+' : '' }}{{ number_format($news['sentiment'], 1, ',', '.') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="concept-empty">{{ __('Keine aktuellen Meldungen der letzten 48 Stunden.') }}</div>
                            @endif
                        @elseif($section['kind'] === 'classic-dashboard')
                            @php
                                extract($section['viewData']);
                                // Defined once here rather than relying on
                                // $dashboardCountryFlags from dashboard-middle-column/
                                // daily-tips-card - those partials are included further
                                // below and wouldn't be in scope for the cards above them.
                                $transactionCountryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹', 'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺'];
                            @endphp
                            @include('partials.dashboard-styles')
                            <div class="concept-classic-dashboard-row">
                                @if ($strategyPortfolios->isNotEmpty())
                                        <article class="concept-card p-4">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-black text-[var(--ak-text)]">{{ __('Strategiedepots') }}</span>
                                                        <span class="mt-0.5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                            {{ trans_choice(':count Depot|:count Depots', $strategyPortfolios->count(), ['count' => $strategyPortfolios->count()]) }}
                                                            @if ($recentBuysCount > 0)
                                                                · <span class="text-emerald-500">{{ trans_choice(':count neuer Kauf (7T)|:count neue Käufe (7T)', $recentBuysCount, ['count' => $recentBuysCount]) }}</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-3 border-t border-[var(--ak-border)] pt-3">
                                                <small class="block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Gesamt') }} (EUR)</small>
                                                <span class="mt-0.5 flex items-baseline gap-1.5">
                                                    <b class="truncate text-lg font-black tabular-nums text-[var(--ak-text)]">{{ number_format($strategyPortfolioTotals['total_value_eur'], 0, ',', '.') }} €</b>
                                                    <small class="text-[9px] font-black tabular-nums {{ $strategyPortfolioTotals['performance'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $strategyPortfolioTotals['performance'] >= 0 ? '+' : '' }}{{ number_format($strategyPortfolioTotals['performance'], 1, ',', '.') }} %</small>
                                                </span>
                                            </div>
                                            <div class="mt-2 overflow-x-auto">
                                                <table class="w-full border-collapse text-[12px]">
                                                    <thead>
                                                        <tr class="text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                            <th class="border-0 pb-1.5 pr-2 text-left font-black">{{ __('Depot') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Pos.') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Frei') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Gebunden') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Bindung') }}</th>
                                                            <th class="border-0 pb-1.5 pl-2 text-right font-black">{{ __('Gesamt') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($strategyPortfolios as $portfolio)
                                                            <tr class="group cursor-pointer" onclick="window.location='{{ route('depots.show', ['portfolio' => $portfolio, 'return_to' => request()->getRequestUri()]) }}'">
                                                                <td class="border-0 py-1.5 pr-2 group-hover:text-orange-500">
                                                                    <span class="flex min-w-0 items-center gap-1.5">
                                                                        <i class="h-1.5 w-1.5 shrink-0 rounded-full {{ $portfolio->dashboard_live_enabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></i>
                                                                        <span class="truncate font-bold text-[var(--ak-text)] group-hover:text-orange-500">{{ $portfolio->name }}</span>
                                                                    </span>
                                                                </td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-muted)]">{{ $portfolio->dashboard_position_count }}</td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format((float) $portfolio->dashboard_cash_eur, 0, ',', '.') }} €</td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format((float) $portfolio->dashboard_positions_value_eur, 0, ',', '.') }} €</td>
                                                                <td class="border-0 px-2 py-1.5">
                                                                    <span class="flex items-center gap-1.5">
                                                                        <span class="h-1.5 w-10 shrink-0 overflow-hidden rounded-full bg-[var(--ak-border)]"><span class="block h-full rounded-full bg-orange-400" style="width: {{ number_format(min(100, (float) $portfolio->dashboard_capital_bound_pct), 1) }}%"></span></span>
                                                                        <span class="shrink-0 tabular-nums text-[var(--ak-muted)]">{{ number_format((float) $portfolio->dashboard_capital_bound_pct, 0, ',', '.') }} %</span>
                                                                    </span>
                                                                </td>
                                                                <td class="border-0 py-1.5 pl-2 text-right">
                                                                    <span class="flex items-baseline justify-end gap-1">
                                                                        <b class="tabular-nums text-[var(--ak-text)]">{{ number_format((float) $portfolio->dashboard_total_value_eur, 0, ',', '.') }} €</b>
                                                                        <small class="text-[8px] font-black tabular-nums {{ $portfolio->dashboard_performance >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $portfolio->dashboard_performance >= 0 ? '+' : '' }}{{ number_format($portfolio->dashboard_performance, 1, ',', '.') }} %</small>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </article>
                                    @endif
                                @if ($strategyCompositionByCountry !== [] || $strategyCompositionBySector !== [] || $strategyAverageMetrics['count'] > 0)
                                        @php $compositionPalette = ['#06b6d4', '#f97316', '#10b981', '#8b5cf6', '#f43f5e', '#eab308']; @endphp
                                        <article class="concept-card p-4">
                                            <div class="flex items-center gap-2">
                                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-chart-pie class="h-4 w-4" /></span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-black text-[var(--ak-text)]">{{ __('Depot-Zusammensetzung') }}</span>
                                                    <span class="mt-0.5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Gehaltene Positionen · aktive Strategiedepots') }}</span>
                                                </span>
                                            </div>
                                            <svg width="0" height="0" class="absolute" aria-hidden="true">
                                                <defs>
                                                    <linearGradient id="concept-gauge-chance" x1="0%" y1="0%" x2="100%" y2="0%">
                                                        <stop offset="0%" stop-color="#ef4444" />
                                                        <stop offset="50%" stop-color="#f59e0b" />
                                                        <stop offset="100%" stop-color="#22c55e" />
                                                    </linearGradient>
                                                    <linearGradient id="concept-gauge-risk" x1="0%" y1="0%" x2="100%" y2="0%">
                                                        <stop offset="0%" stop-color="#22c55e" />
                                                        <stop offset="50%" stop-color="#f59e0b" />
                                                        <stop offset="100%" stop-color="#ef4444" />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            <div class="mt-4 grid grid-cols-4 gap-2">
                                                @foreach ([
                                                    ['kind' => 'pie', 'label' => __('Nach Land'), 'rows' => $strategyCompositionByCountry, 'flags' => true],
                                                    ['kind' => 'pie', 'label' => __('Nach Sektor'), 'rows' => $strategyCompositionBySector, 'flags' => false],
                                                    ['kind' => 'gauge', 'label' => __('Ø Score'), 'value' => $strategyAverageMetrics['score'], 'type' => 'chance'],
                                                    ['kind' => 'gauge', 'label' => __('Ø Risiko'), 'value' => $strategyAverageMetrics['risk'], 'type' => 'risk'],
                                                ] as $chart)
                                                    <div class="min-w-0">
                                                        <small class="block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $chart['label'] }}</small>
                                                        @if ($chart['kind'] === 'gauge')
                                                            <div class="mt-2 flex flex-col items-center gap-1">
                                                                @if ($chart['value'] === null)
                                                                    <div class="concept-empty w-full">—</div>
                                                                @else
                                                                    @php $gaugePct = max(0, min(100, (float) $chart['value'])); @endphp
                                                                    <div class="relative grid h-16 w-16 place-items-center">
                                                                        <svg viewBox="0 0 120 120" class="absolute inset-0 h-full w-full -rotate-90" aria-hidden="true">
                                                                            <circle cx="60" cy="60" r="48" fill="none" stroke="var(--ak-border)" stroke-width="12" />
                                                                            <circle
                                                                                cx="60" cy="60" r="48" fill="none" stroke-width="12" pathLength="100" stroke-linecap="round"
                                                                                stroke="url(#concept-gauge-{{ $chart['type'] }})"
                                                                                stroke-dasharray="{{ max(0.3, $gaugePct) }} {{ 100 - max(0.3, $gaugePct) }}"
                                                                            />
                                                                        </svg>
                                                                        <b class="relative text-[10px] font-black text-[var(--ak-text)]">
                                                                            {{ $chart['type'] === 'risk' ? number_format($gaugePct / 10, 1, ',', '.') : number_format($gaugePct, 0, ',', '.') }}
                                                                            @if ($chart['type'] === 'risk')
                                                                                <small class="text-[6px] font-black text-[var(--ak-muted)]">/10</small>
                                                                            @endif
                                                                        </b>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @elseif ($chart['rows'] === [])
                                                            <div class="concept-empty mt-2">{{ __('Keine Positionen.') }}</div>
                                                        @else
                                                            @php $cumulative = 0.0; @endphp
                                                            <div class="mt-2 flex flex-col items-center gap-2">
                                                                <svg viewBox="0 0 120 120" class="h-16 w-16 shrink-0 -rotate-90" aria-hidden="true">
                                                                    <circle cx="60" cy="60" r="48" fill="none" stroke="var(--ak-border)" stroke-width="16" />
                                                                    @foreach ($chart['rows'] as $index => $row)
                                                                        <circle
                                                                            cx="60" cy="60" r="48" fill="none" stroke-width="16" pathLength="100"
                                                                            stroke="{{ $row['label'] === __('Sonstige') ? '#94a3b8' : $compositionPalette[$index % count($compositionPalette)] }}"
                                                                            stroke-dasharray="{{ max(0.3, $row['pct']) }} {{ 100 - max(0.3, $row['pct']) }}"
                                                                            stroke-dashoffset="{{ -$cumulative }}"
                                                                        />
                                                                        @php $cumulative += $row['pct']; @endphp
                                                                    @endforeach
                                                                </svg>
                                                                <ul class="w-full min-w-0 space-y-1">
                                                                    @foreach ($chart['rows'] as $index => $row)
                                                                        <li class="flex items-center gap-1 text-[8px]">
                                                                            <i class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $row['label'] === __('Sonstige') ? '#94a3b8' : $compositionPalette[$index % count($compositionPalette)] }}"></i>
                                                                            @if ($chart['flags'] ?? false)
                                                                                <span class="shrink-0">{{ $transactionCountryFlags[$row['label']] ?? '🌐' }}</span>
                                                                            @endif
                                                                            <span class="min-w-0 flex-1 truncate font-bold text-[var(--ak-text)]">{{ $row['label'] }}</span>
                                                                            <span class="shrink-0 tabular-nums text-[var(--ak-muted)]">{{ number_format($row['pct'], 0, ',', '.') }} %</span>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if (collect($strategyHorizonReturns)->contains(fn ($h) => $h['avg_return'] !== null))
                                                @php $maxAbsReturn = max(0.01, collect($strategyHorizonReturns)->max(fn ($h) => abs((float) ($h['avg_return'] ?? 0)))); @endphp
                                                <div class="mt-5 border-t border-[var(--ak-border)] pt-4">
                                                    <small class="block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ø Prognose je Horizont') }}</small>
                                                    <div class="mt-2 space-y-2">
                                                        @foreach ($strategyHorizonReturns as $horizonRow)
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-7 shrink-0 text-[9px] font-black text-[var(--ak-muted)]">{{ $horizonRow['horizon'] }}T</span>
                                                                <span class="relative h-3 min-w-0 flex-1 rounded-full bg-[var(--ak-border)]">
                                                                    @if ($horizonRow['avg_return'] !== null)
                                                                        <span class="absolute inset-y-0 rounded-full {{ $horizonRow['avg_return'] >= 0 ? 'left-1/2 bg-emerald-500' : 'right-1/2 bg-rose-500' }}" style="width: {{ number_format(abs($horizonRow['avg_return']) / $maxAbsReturn * 50, 1) }}%"></span>
                                                                    @endif
                                                                    <span class="absolute inset-y-0 left-1/2 w-px bg-[var(--ak-muted)]"></span>
                                                                </span>
                                                                <span class="w-14 shrink-0 text-right text-[9px] font-black tabular-nums {{ $horizonRow['avg_return'] === null ? 'text-[var(--ak-muted)]' : ($horizonRow['avg_return'] >= 0 ? 'text-emerald-500' : 'text-rose-500') }}">{{ $horizonRow['avg_return'] === null ? '—' : ($horizonRow['avg_return'] >= 0 ? '+' : '').number_format($horizonRow['avg_return'], 1, ',', '.').' %' }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            @if (collect($strategyModelHorizonReturns)->flatMap(fn ($v) => $v['cells'])->contains(fn ($c) => $c['volume'] > 0))
                                                @php $heatmapMax = max(1, collect($strategyModelHorizonReturns)->flatMap(fn ($v) => $v['cells'])->max(fn ($c) => (float) $c['volume'])); @endphp
                                                <div class="mt-5 border-t border-[var(--ak-border)] pt-4">
                                                    <small class="block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modell vs. Horizont') }}</small>
                                                    <div class="mt-2 grid grid-cols-4 gap-1 text-[9px]">
                                                        <span></span>
                                                        @foreach ([10, 20, 40] as $horizon)
                                                            <span class="text-center font-black text-[var(--ak-muted)]">{{ $horizon }}T</span>
                                                        @endforeach
                                                        @foreach ($strategyModelHorizonReturns as $variantRow)
                                                            <span class="flex min-w-0 items-center truncate font-black text-[var(--ak-text)]">{{ $variantRow['label'] }}</span>
                                                            @foreach ($variantRow['cells'] as $cell)
                                                                @php
                                                                    $intensity = $cell['volume'] > 0 ? min(1, $cell['volume'] / $heatmapMax) : 0;
                                                                    $heatmapBg = $cell['volume'] > 0
                                                                        ? 'color-mix(in srgb, #06b6d4 '.number_format($intensity * 70, 0).'%, transparent)'
                                                                        : 'transparent';
                                                                @endphp
                                                                <span class="grid place-items-center rounded py-1.5 font-black tabular-nums {{ $cell['volume'] <= 0 ? 'text-[var(--ak-muted)]' : 'text-[var(--ak-text)]' }}" style="background: {{ $heatmapBg }}" title="{{ trans_choice(':count Position|:count Positionen', $cell['count'], ['count' => $cell['count']]) }}">
                                                                    {{ $cell['volume'] > 0 ? number_format($cell['volume'], 0, ',', '.').' €' : '—' }}
                                                                </span>
                                                            @endforeach
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </article>
                                    @endif
                            </div>
                            @if ($strategyPositionHoldings !== [])
                                <article class="concept-card mb-4 p-4">
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-briefcase class="h-4 w-4" /></span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-black text-[var(--ak-text)]">{{ __('Depotpositionen') }}</span>
                                            <span class="mt-0.5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Alle gehaltenen Positionen · aktive Strategiedepots') }}</span>
                                        </span>
                                    </div>
                                    <div class="mt-3 overflow-x-auto">
                                        <table class="w-full border-collapse text-[10px]">
                                            <thead>
                                                <tr class="text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                    <th class="border-0 pb-1.5 pr-2 text-left font-black">{{ __('Name') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Depot') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Modell') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Horizont') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Gekauft am') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Haltedauer') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Anzahl') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Kaufwert') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Aktueller Wert') }}</th>
                                                    <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Performance (€)') }}</th>
                                                    <th class="border-0 pb-1.5 pl-2 text-right font-black">{{ __('Performance (%)') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($strategyPositionHoldings as $holding)
                                                    @php $holdingCurrencySuffix = $holding['currency'] === 'EUR' ? '€' : $holding['currency']; @endphp
                                                    <tr>
                                                        <td class="border-0 py-1.5 pr-2">
                                                            <span class="flex min-w-0 items-center gap-1.5">
                                                                <span class="shrink-0">{{ $transactionCountryFlags[strtoupper((string) $holding['symbol'])] ?? '🌐' }}</span>
                                                                <b class="min-w-0 truncate font-bold text-[var(--ak-text)]">{{ $holding['name'] }}</b>
                                                            </span>
                                                        </td>
                                                        <td class="border-0 px-2 py-1.5 truncate text-[var(--ak-muted)]">{{ $holding['portfolio_name'] }}</td>
                                                        <td class="border-0 px-2 py-1.5 text-[var(--ak-muted)]">{{ $holding['model'] ?? '—' }}</td>
                                                        <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-muted)]">{{ $holding['horizon'] !== null ? $holding['horizon'].'T' : '—' }}</td>
                                                        <td class="border-0 px-2 py-1.5 whitespace-nowrap tabular-nums text-[var(--ak-muted)]">{{ $holding['bought_at'] ?? '—' }}</td>
                                                        <td class="border-0 px-2 py-1.5">
                                                            @if ($holding['holding_remaining_pct'] !== null)
                                                                <span class="flex items-center gap-1.5">
                                                                    <span class="h-2.5 w-16 shrink-0 overflow-hidden rounded-full bg-[var(--ak-border)]"><span class="block h-full rounded-full" style="background: #06b6d4; width: {{ number_format($holding['holding_remaining_pct'], 1) }}%"></span></span>
                                                                    <span class="shrink-0 tabular-nums text-[var(--ak-muted)]">{{ $holding['holding_remaining_days'] }}{{ __('T') }} · {{ number_format($holding['holding_remaining_pct'], 0, ',', '.') }} %</span>
                                                                </span>
                                                            @else
                                                                <span class="text-[var(--ak-muted)]">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($holding['quantity'], 0, ',', '.') }}</td>
                                                        <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($holding['buy_value'], 0, ',', '.') }} {{ $holdingCurrencySuffix }}</td>
                                                        <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($holding['current_value'], 0, ',', '.') }} {{ $holdingCurrencySuffix }}</td>
                                                        <td class="border-0 px-2 py-1.5 text-right tabular-nums font-black {{ $holding['performance_eur'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $holding['performance_eur'] >= 0 ? '+' : '' }}{{ number_format($holding['performance_eur'], 0, ',', '.') }} {{ $holdingCurrencySuffix }}</td>
                                                        <td class="border-0 py-1.5 pl-2 text-right tabular-nums font-black {{ $holding['performance_pct'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $holding['performance_pct'] >= 0 ? '+' : '' }}{{ number_format($holding['performance_pct'], 1, ',', '.') }} %</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </article>
                            @endif
                            <div class="concept-classic-dashboard-grid">
                                <div class="concept-classic-dashboard-col">
                                    <article class="concept-card p-4">
                                        <div class="flex items-center gap-2">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/30 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-calendar-days class="h-4 w-4" /></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-black text-[var(--ak-text)]">{{ __('Anstehende Termine') }}</span>
                                                <span class="mt-0.5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Quartalszahlen & geplante Verkäufe · nächste 21 Tage') }}</span>
                                            </span>
                                        </div>
                                        <div class="mt-3 space-y-1.5">
                                            @forelse ($strategyPositionEvents->take(6) as $event)
                                                <div class="flex items-center gap-2 rounded-lg border border-[var(--ak-border)] px-2.5 py-2">
                                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-cyan-400/10 text-cyan-500">
                                                        @if ($event['type'] === 'earnings')
                                                            <x-heroicon-o-chart-bar class="h-3.5 w-3.5" />
                                                        @else
                                                            <x-heroicon-o-banknotes class="h-3.5 w-3.5" />
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $event['symbol'] }} · {{ $event['label'] }}</b>
                                                        <small class="block truncate text-[8px] text-[var(--ak-muted)]">{{ $event['schedule'] }}</small>
                                                    </span>
                                                </div>
                                            @empty
                                                <div class="concept-empty">{{ __('Keine Termine in den nächsten 21 Tagen.') }}</div>
                                            @endforelse
                                        </div>
                                    </article>
                                </div>
                                <div class="concept-classic-dashboard-col">
                                    @if ($strategyRecentTransactions->isNotEmpty())
                                        <article class="concept-card p-4">
                                            <div class="flex items-center gap-2">
                                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-arrow-path class="h-4 w-4" /></span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-black text-[var(--ak-text)]">{{ __('Letzte Transaktionen') }}</span>
                                                    <span class="mt-0.5 block text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Über alle Strategiedepots') }}</span>
                                                </span>
                                            </div>
                                            <div class="mt-3 overflow-x-auto">
                                                <table class="w-full border-collapse text-[12px]">
                                                    <thead>
                                                        <tr class="text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                            <th class="border-0 pb-1.5 pr-2 text-left font-black">{{ __('Datum') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Typ') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-left font-black">{{ __('Aktie') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Menge') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Preis') }}</th>
                                                            <th class="border-0 pb-1.5 px-2 text-right font-black">{{ __('Kaufwert') }}</th>
                                                            <th class="border-0 pb-1.5 pl-2 text-right font-black">{{ __('Anteil') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($strategyRecentTransactions as $transaction)
                                                            @php $currencySuffix = strtoupper($transaction['currency']) === 'EUR' ? '€' : $transaction['currency']; @endphp
                                                            <tr>
                                                                <td class="border-0 py-1.5 pr-2 whitespace-nowrap tabular-nums text-[var(--ak-muted)]">{{ $transaction['date'] }}</td>
                                                                <td class="border-0 px-2 py-1.5">
                                                                    <span class="inline-flex rounded border px-1.5 py-0.5 text-[8px] font-black uppercase {{ $transaction['type'] === 'buy' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-500' : 'border-rose-400/30 bg-rose-400/10 text-rose-500' }}">{{ $transaction['type'] === 'buy' ? __('Kauf') : __('Verkauf') }}</span>
                                                                </td>
                                                                <td class="border-0 px-2 py-1.5">
                                                                    <span class="flex min-w-0 items-center gap-1.5">
                                                                        <span class="shrink-0">{{ $transactionCountryFlags[$transaction['country']] ?? '🌐' }}</span>
                                                                        <span class="min-w-0">
                                                                            <b class="block truncate font-bold text-[var(--ak-text)]">{{ $transaction['name'] }}</b>
                                                                            <small class="block truncate text-[8px] text-[var(--ak-muted)]">{{ $transaction['portfolio_name'] }}</small>
                                                                        </span>
                                                                    </span>
                                                                </td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($transaction['quantity'], 0, ',', '.') }}</td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($transaction['price'], 2, ',', '.') }} {{ $currencySuffix }}</td>
                                                                <td class="border-0 px-2 py-1.5 text-right tabular-nums text-[var(--ak-text)]">{{ number_format($transaction['value'], 0, ',', '.') }} {{ $currencySuffix }}</td>
                                                                <td class="border-0 py-1.5 pl-2 text-right tabular-nums text-[var(--ak-muted)]">{{ $transaction['share_pct'] !== null ? number_format($transaction['share_pct'], 0, ',', '.').' %' : '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </article>
                                    @endif
                                        </div>
                                    </article>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-[var(--ak-muted)]">{{ $section['description'] }}</p>
                        @endif

                        <a href="{{ $section['url'] }}" class="concept-open-link text-cyan-500 hover:text-cyan-400">{{ __('Vollständige Ansicht öffnen') }} →</a>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
