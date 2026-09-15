<x-app-layout>
    <style>
        #dashboard-concept-page { max-width: 72rem; margin-inline: auto; padding: 1.25rem 1rem 3rem; }
        #dashboard-concept-page .concept-card {
            border: 1px solid var(--ak-border);
            background: var(--ak-card);
            border-radius: 1.25rem;
            box-shadow: var(--ak-shadow);
        }
        #dashboard-concept-page .concept-layout {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            #dashboard-concept-page .concept-layout { grid-template-columns: 96px minmax(0, 1fr); align-items: start; }
        }
        /* Left column: exactly 1 wide x 8 tall - a fixed grid, not flex-wrap,
           so it can never accidentally reflow into more than one column. */
        #dashboard-concept-page .concept-icon-grid {
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: repeat(8, 1fr);
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
            transition: border-color .15s ease;
        }
        #dashboard-concept-page .concept-icon-tile:hover {
            border-color: color-mix(in srgb, #22d3ee 45%, transparent);
        }
        #dashboard-concept-page .concept-icon-tile svg { width: 1.15rem; height: 1.15rem; flex: none; }
        #dashboard-concept-page .concept-icon-tile small { font-size: .5rem; font-weight: 800; text-align: center; line-height: 1.05; }
        #dashboard-concept-page .concept-depot-card { padding: 1.25rem; }
        #dashboard-concept-page .concept-depot-metric { display: flex; flex-direction: column; gap: .15rem; }
        #dashboard-concept-page .concept-depot-metric b { font-size: 1.4rem; font-weight: 900; color: var(--ak-text); }
        #dashboard-concept-page .concept-depot-metric small { font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ak-muted); }
        #dashboard-concept-page .concept-empty {
            display: grid; place-items: center; min-height: 8rem; padding: 1rem; border: 1px dashed var(--ak-border); border-radius: .8rem; text-align: center; font-size: .78rem; font-weight: 700; color: var(--ak-muted);
        }
    </style>

    <div id="dashboard-concept-page">
        <header class="mb-5">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Konzept') }}</p>
            <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Persönlicher Bereich') }}</h1>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Links: 1×8-Symbolraster für die wichtigsten Bereiche. Mitte: das Musterdepot als eigene Karte statt einer Icon-Kachel.') }}</p>
            <a href="{{ route('dashboard') }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">← {{ __('Zurück zum Dashboard') }}</a>
        </header>

        <div class="concept-layout">
            {{-- Left: 1x8 icon grid --}}
            <nav class="concept-card concept-icon-grid" aria-label="{{ __('Persönlicher Bereich') }}">
                @foreach($leftIcons as $item)
                    <a href="{{ $item['url'] }}" class="concept-icon-tile" title="{{ $item['label'] }}">
                        <x-dynamic-component :component="$item['icon']" />
                        <small>{{ $item['label'] }}</small>
                    </a>
                @endforeach
            </nav>

            {{-- Middle: Musterdepot --}}
            <section class="concept-card concept-depot-card">
                <div class="mb-3 flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg border border-emerald-400/25 bg-emerald-400/10 text-emerald-400"><x-heroicon-o-beaker class="h-5 w-5" /></span>
                    <span>
                        <small class="block text-[9px] font-black uppercase tracking-[.16em] text-emerald-500">{{ __('Musterdepot') }}</small>
                        <b class="block text-base font-black text-[var(--ak-text)]">{{ $depot['name'] ?? __('Kein Musterdepot vorhanden') }}</b>
                    </span>
                </div>

                @if($depot)
                    <div class="grid grid-cols-3 gap-4 border-t border-[var(--ak-border)] pt-3">
                        <div class="concept-depot-metric">
                            <b>{{ number_format($depot['cashBalance'], 0, ',', '.') }} {{ $depot['currency'] }}</b>
                            <small>{{ __('Barbestand') }}</small>
                        </div>
                        <div class="concept-depot-metric">
                            <b>{{ number_format($depot['positionsValue'], 0, ',', '.') }} {{ $depot['currency'] }}</b>
                            <small>{{ __('Positionswert') }}</small>
                        </div>
                        <div class="concept-depot-metric">
                            <b>{{ $depot['positionCount'] }}</b>
                            <small>{{ __('Positionen') }}</small>
                        </div>
                    </div>
                    <a href="{{ route('paper-depots.index') }}" class="mt-4 inline-flex items-center gap-1 text-[11px] font-black text-emerald-500 hover:text-emerald-400">{{ __('Musterdepot öffnen') }} →</a>
                @else
                    <div class="concept-empty">
                        {{ __('Noch kein Musterdepot angelegt.') }}
                        <a href="{{ route('paper-depots.index') }}" class="mt-2 block text-cyan-500 hover:text-cyan-400">{{ __('Jetzt anlegen') }} →</a>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
