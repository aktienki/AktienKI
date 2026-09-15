<x-app-layout>
    @php
        $dashboardCountryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹', 'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺'];
    @endphp
    <style>
        #dashboard-concept-page { max-width: 78rem; margin-inline: auto; padding: 1.25rem 1rem 3rem; }
        #dashboard-concept-page .concept-card {
            border: 1px solid var(--ak-border);
            background: var(--ak-card);
            border-radius: 1.25rem;
            box-shadow: var(--ak-shadow);
            padding: 1.1rem;
        }
        #dashboard-concept-page .concept-heading {
            display: flex; align-items: center; gap: .6rem; margin-bottom: .9rem;
        }
        #dashboard-concept-page .concept-heading b {
            font-size: .95rem; font-weight: 900; color: var(--ak-text);
        }
        #dashboard-concept-page .concept-heading small {
            display: block; font-size: .62rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--ak-muted);
        }
        #dashboard-concept-page .concept-icon {
            display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .8rem; flex: none;
        }
        #dashboard-concept-page .concept-empty {
            display: grid; place-items: center; min-height: 4.5rem; padding: 1rem; border: 1px dashed var(--ak-border); border-radius: .8rem; text-align: center; font-size: .72rem; font-weight: 700; color: var(--ak-muted);
        }
        #dashboard-concept-page .concept-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 1024px) {
            #dashboard-concept-page .concept-grid { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); align-items: start; }
        }
        #dashboard-concept-page .concept-row {
            display: flex; align-items: center; gap: .65rem; padding: .55rem .1rem; border-bottom: 1px solid color-mix(in srgb, var(--ak-border) 60%, transparent);
            text-decoration: none; color: inherit; transition: background .15s ease;
        }
        #dashboard-concept-page .concept-row:last-child { border-bottom: 0; }
        #dashboard-concept-page .concept-row:hover { background: color-mix(in srgb, var(--ak-accent, #22d3ee) 6%, transparent); border-radius: .6rem; }
        #dashboard-concept-page .concept-row-flag { font-size: 1rem; flex: none; }
        #dashboard-concept-page .concept-row-name { font-size: .78rem; font-weight: 900; color: var(--ak-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #dashboard-concept-page .concept-row-meta { font-size: .64rem; font-weight: 800; color: var(--ak-muted); text-transform: uppercase; letter-spacing: .03em; }
        #dashboard-concept-page .concept-badge {
            flex: none; font-size: .68rem; font-weight: 900; padding: .2rem .5rem; border-radius: .5rem; border: 1px solid transparent;
        }
        #dashboard-concept-page .concept-dist-bar { display: flex; height: .55rem; border-radius: .3rem; overflow: hidden; margin-top: .5rem; }
        #dashboard-concept-page .concept-dist-legend { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .4rem; font-size: .62rem; font-weight: 800; color: var(--ak-muted); }
        #dashboard-concept-page .concept-dist-legend i { display: inline-block; width: .5rem; height: .5rem; border-radius: 999px; margin-right: .25rem; vertical-align: -1px; }
        #dashboard-concept-page .concept-subheading { font-size: .68rem; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; color: var(--ak-muted); margin: 0 0 .5rem; }
    </style>

    <div id="dashboard-concept-page">
        <header class="mb-5">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Konzept') }}</p>
            <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Vier Bereiche, die wichtigsten Informationen zuerst - kein Drag & Drop, keine leeren Karten.') }}</p>
            <a href="{{ route('dashboard') }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">← {{ __('Zurück zum ursprünglichen Dashboard') }}</a>
        </header>

        {{-- Market situation --}}
        <section class="concept-card mb-4">
            <div class="concept-heading">
                <span class="concept-icon" style="background:color-mix(in srgb, #22d3ee 14%, transparent); color:#0891b2;"><x-heroicon-o-globe-alt class="h-5 w-5" /></span>
                <span><small>{{ __('Marktsituation') }}</small><b>{{ __('Wie steht der Markt heute?') }}</b></span>
            </div>
            @if(! $market['available'])
                <div class="concept-empty">{{ __('Aktuell liegt keine Marktbewertung vor.') }}</div>
            @else
                @php
                    $marketTone = match ($market['tone']) {
                        'positive', 'bullish' => ['#10b981', '#ecfdf5'],
                        'negative', 'bearish' => ['#f43f5e', '#fef2f2'],
                        default => ['#f59e0b', '#fffbeb'],
                    };
                    $dist = $market['distribution'];
                    $distTotal = max(1, array_sum($dist));
                @endphp
                <div class="flex flex-wrap items-start gap-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-full border-4" style="border-color: {{ $marketTone[0] }};">
                            <b class="text-lg font-black" style="color: {{ $marketTone[0] }}">{{ $market['score'] !== null ? number_format($market['score'], 0, ',', '.') : '—' }}</b>
                        </div>
                        <div>
                            <b class="block text-sm font-black text-[var(--ak-text)]">{{ $market['status'] }}</b>
                            <small class="text-[10px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Risiko') }}: {{ $market['riskLevel'] }} @if($market['averageChange'] !== null) · Ø {{ sprintf('%+.1f', $market['averageChange']) }}% @endif</small>
                        </div>
                    </div>
                    <p class="min-w-0 flex-1 text-xs leading-5 text-[var(--ak-muted)]">{{ $market['summary'] }}</p>
                </div>
                <div class="concept-dist-bar">
                    <span style="width:{{ $dist['BUY'] / $distTotal * 100 }}%; background:#10b981;" title="BUY {{ $dist['BUY'] }}"></span>
                    <span style="width:{{ $dist['WATCH'] / $distTotal * 100 }}%; background:#84cc16;" title="WATCH {{ $dist['WATCH'] }}"></span>
                    <span style="width:{{ $dist['HOLD'] / $distTotal * 100 }}%; background:#94a3b8;" title="HOLD {{ $dist['HOLD'] }}"></span>
                    <span style="width:{{ $dist['SELL'] / $distTotal * 100 }}%; background:#f43f5e;" title="SELL {{ $dist['SELL'] }}"></span>
                </div>
                <div class="concept-dist-legend">
                    <span><i style="background:#10b981"></i>BUY {{ $dist['BUY'] }}</span>
                    <span><i style="background:#84cc16"></i>WATCH {{ $dist['WATCH'] }}</span>
                    <span><i style="background:#94a3b8"></i>HOLD {{ $dist['HOLD'] }}</span>
                    <span><i style="background:#f43f5e"></i>SELL {{ $dist['SELL'] }}</span>
                    @if($market['strongestSector'])
                        <span class="ml-auto text-emerald-500">▲ {{ $market['strongestSector']['sector'] }} {{ sprintf('%+.1f', $market['strongestSector']['return']) }}%</span>
                    @endif
                    @if($market['weakestSector'])
                        <span class="text-rose-400">▼ {{ $market['weakestSector']['sector'] }} {{ sprintf('%+.1f', $market['weakestSector']['return']) }}%</span>
                    @endif
                </div>
            @endif
        </section>

        <div class="concept-grid">
            {{-- Left column: best & newest stocks --}}
            <section class="concept-card">
                <div class="concept-heading">
                    <span class="concept-icon" style="background:color-mix(in srgb, #f59e0b 14%, transparent); color:#b45309;"><x-heroicon-o-star class="h-5 w-5" /></span>
                    <span><small>{{ __('Aktien') }}</small><b>{{ __('Beste & neueste Aktien') }}</b></span>
                </div>

                <p class="concept-subheading">{{ __('Beste bewertete Aktien') }}</p>
                @forelse($bestStocks['best'] as $stock)
                    <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard/concept']) }}" class="concept-row">
                        <span class="concept-row-flag">{{ $dashboardCountryFlags[strtoupper((string) ($stock->country ?? ''))] ?? '🌐' }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="concept-row-name block">{{ $stock->name ?: $stock->symbol }}</span>
                            <span class="concept-row-meta">{{ $stock->symbol }} · {{ signal_label(strtoupper((string) $stock->personalized_signal)) }}</span>
                        </span>
                        <span class="concept-badge" style="border-color: color-mix(in srgb, #0891b2 35%, transparent); background: color-mix(in srgb, #0891b2 10%, transparent); color:#0891b2;">{{ number_format((float) $stock->composite_score, 0, ',', '.') }}</span>
                    </a>
                @empty
                    <div class="concept-empty">{{ __('Aktuell keine bewerteten POSITIV/WATCH-Aktien verfügbar.') }}</div>
                @endforelse

                <p class="concept-subheading mt-4">{{ __('Neueste Signalwechsel zu POSITIV/WATCH') }}</p>
                @forelse($bestStocks['newest'] as $stock)
                    <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard/concept']) }}" class="concept-row">
                        <span class="concept-row-flag">{{ $dashboardCountryFlags[strtoupper((string) ($stock->country ?? ''))] ?? '🌐' }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="concept-row-name block">{{ $stock->name ?: $stock->symbol }}</span>
                            <span class="concept-row-meta">{{ $stock->symbol }} · {{ signal_label(strtoupper((string) $stock->personalized_signal)) }}</span>
                        </span>
                        <span class="concept-row-meta">{{ \Illuminate\Support\Carbon::parse($stock->signal_transition_at)->diffForHumans() }}</span>
                    </a>
                @empty
                    <div class="concept-empty">{{ __('Keine neuen Signalwechsel.') }}</div>
                @endforelse
            </section>

            {{-- Right column: recent changes + reminders --}}
            <div class="grid gap-4">
                <section class="concept-card">
                    <div class="concept-heading">
                        <span class="concept-icon" style="background:color-mix(in srgb, #a78bfa 14%, transparent); color:#7c3aed;"><x-heroicon-o-arrow-path class="h-5 w-5" /></span>
                        <span><small>{{ __('Bewegung') }}</small><b>{{ __('Letzte Signalwechsel') }}</b></span>
                    </div>
                    @forelse($recentChanges as $stock)
                        @php
                            $changeSignal = strtoupper((string) ($stock->personalized_signal ?: 'HOLD'));
                            $changeTone = match ($changeSignal) { 'BUY' => 'color:#10b981;border-color:color-mix(in srgb,#10b981 35%,transparent);background:color-mix(in srgb,#10b981 10%,transparent);', 'WATCH' => 'color:#84cc16;border-color:color-mix(in srgb,#84cc16 35%,transparent);background:color-mix(in srgb,#84cc16 10%,transparent);', 'SELL' => 'color:#f43f5e;border-color:color-mix(in srgb,#f43f5e 35%,transparent);background:color-mix(in srgb,#f43f5e 10%,transparent);', default => 'color:#94a3b8;border-color:color-mix(in srgb,#94a3b8 35%,transparent);background:color-mix(in srgb,#94a3b8 10%,transparent);' };
                        @endphp
                        <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard/concept']) }}" class="concept-row">
                            <span class="min-w-0 flex-1">
                                <span class="concept-row-name block">{{ $stock->symbol }}</span>
                                <span class="concept-row-meta">{{ strtoupper((string) ($stock->signal_transition_from ?: '—')) }} → {{ $changeSignal }}</span>
                            </span>
                            <span class="concept-badge" style="{{ $changeTone }}">{{ signal_label($changeSignal) }}</span>
                        </a>
                    @empty
                        <div class="concept-empty">{{ __('Keine Signalwechsel in den letzten Tagen.') }}</div>
                    @endforelse
                </section>

                <section class="concept-card">
                    <div class="concept-heading">
                        <span class="concept-icon" style="background:color-mix(in srgb, #34d399 14%, transparent); color:#059669;"><x-heroicon-o-calendar class="h-5 w-5" /></span>
                        <span><small>{{ __('Kalender') }}</small><b>{{ __('Anstehende Termine') }}</b></span>
                    </div>
                    @forelse($reminders as $reminder)
                        <a href="{{ route('stocks.show', ['symbol' => $reminder['symbol'], 'return_to' => '/dashboard/concept']) }}" class="concept-row">
                            <span class="min-w-0 flex-1">
                                <span class="concept-row-name block">{{ $reminder['name'] ?: $reminder['symbol'] }}</span>
                                <span class="concept-row-meta">{{ $reminder['label'] }}</span>
                            </span>
                            <span class="concept-row-meta">{{ $reminder['date']->format('d.m.Y') }}</span>
                        </a>
                    @empty
                        <div class="concept-empty">{{ __('Keine anstehenden Termine.') }}</div>
                    @endforelse
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
