<x-app-layout>
    <style>
        #upcoming-events-page { max-width: 56rem; margin-inline: auto; padding: 1.25rem 1rem 3rem; }
        #upcoming-events-page .events-section-label {
            margin: 1.75rem 0 .85rem; font-size: .78rem; font-weight: 900; color: var(--ak-text);
            display: flex; align-items: center; gap: .5rem;
        }
        #upcoming-events-page .events-section-label:first-of-type { margin-top: 0; }
        #upcoming-events-page .events-day { margin-bottom: 1.25rem; }
        #upcoming-events-page .events-day-label {
            margin-bottom: .5rem; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--ak-muted);
        }
        #upcoming-events-page .events-card {
            display: flex; align-items: center; gap: .75rem; border-radius: .85rem; border: 1px solid var(--ak-border);
            background: var(--ak-card); padding: .75rem 1rem; text-decoration: none; color: var(--ak-text);
            transition: border-color .15s ease;
        }
        #upcoming-events-page .events-card:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); }
        #upcoming-events-page .events-card + .events-card { margin-top: .5rem; }
        #upcoming-events-page .events-badge {
            flex: none; border-radius: .5rem; border: 1px solid var(--ak-border); padding: .25rem .55rem;
            font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); white-space: nowrap;
        }
        #upcoming-events-page .events-badge--watched { border-color: color-mix(in srgb, #22d3ee 45%, transparent); color: #22d3ee; }
        #upcoming-events-page .events-empty {
            display: grid; place-items: center; min-height: 6rem; padding: 1.5rem; border: 1px dashed var(--ak-border); border-radius: .8rem; text-align: center; font-size: .85rem; font-weight: 700; color: var(--ak-muted);
        }
    </style>

    <div id="upcoming-events-page">
        <header class="mb-5">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Konzept') }}</p>
            <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Anstehende News') }}</h1>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Quartalstermine für unser Aktienuniversum, täglich synchronisiert von Twelve Data - kommende :aheadDays Tage und bereits veröffentlichte der letzten :backDays Tage.', ['aheadDays' => $lookaheadDays, 'backDays' => $lookbackDays]) }}</p>
            <a href="{{ route('dashboard.concept') }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">← {{ __('Zurück zum Konzept-Dashboard') }}</a>
        </header>

        <p class="events-section-label"><x-heroicon-o-calendar-days class="h-4 w-4 text-cyan-500" />{{ __('Anstehend') }}</p>
        @if($groupedByDate->isEmpty())
            <div class="events-empty">{{ __('Aktuell keine anstehenden Termine im Zeitraum.') }}</div>
        @else
            @foreach($groupedByDate as $date => $dayEvents)
                <x-upcoming-events.event-day-group :date="$date" :day-events="$dayEvents" />
            @endforeach
        @endif

        <p class="events-section-label"><x-heroicon-o-clock class="h-4 w-4 text-[var(--ak-muted)]" />{{ __('Kürzlich veröffentlicht') }}</p>
        @if($recentGroupedByDate->isEmpty())
            <div class="events-empty">{{ __('Keine kürzlich veröffentlichten Quartalszahlen im Zeitraum.') }}</div>
        @else
            @foreach($recentGroupedByDate as $date => $dayEvents)
                <x-upcoming-events.event-day-group :date="$date" :day-events="$dayEvents" />
            @endforeach
        @endif
    </div>
</x-app-layout>
