<x-app-layout>
<div id="earnings-calendar-page" class="mx-auto max-w-7xl px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-4">
        <h1 class="text-3xl font-black tracking-tight">{{ __('Anstehende Termine') }}</h1>
        <p class="mt-1 text-xs font-semibold text-[var(--ak-muted)]">
            {{ __(':n Quartalstermine bis :date angekündigt. Datenabdeckung ist lückenhaft (Feed deckt nicht das gesamte Aktien-Universum ab) - fehlende Wochen bedeuten nicht zwangsläufig, dass es dort keine Berichte gibt.', ['n' => $total, 'date' => $range_end->format('d.m.Y')]) }}
        </p>
    </header>

    <div class="ak-master-card mb-4">
        <div class="ak-master-card-header"><h2 class="text-base font-black">{{ __('Termine pro Woche') }}</h2></div>
        <div class="calendar-chart">
            @foreach($weeks as $week)
                <div class="calendar-week {{ $week['is_current'] ? 'is-current' : '' }}">
                    <div class="calendar-week-bar-track">
                        @if($week['count'] > 0)
                            <div class="calendar-week-bar" style="height: {{ max(6, round($week['intensity'] * 100)) }}%" title="{{ $week['label'] }} ({{ $week['range'] }}): {{ $week['count'] }}">
                                <span class="calendar-week-count">{{ $week['count'] }}</span>
                            </div>
                        @endif
                    </div>
                    <span class="calendar-week-label">{{ $week['label'] }}</span>
                    <span class="calendar-week-range">{{ $week['range'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="ak-master-card">
        <div class="ak-master-card-header"><h2 class="text-base font-black">{{ __('Nächste Termine') }}</h2></div>
        <div class="calendar-list">
            @forelse($events as $event)
                <a href="{{ route('stocks.show', $event['symbol']) }}" class="calendar-row">
                    <div class="calendar-row-date">
                        <b>{{ $event['date']->format('d.m.') }}</b>
                        <span>{{ $event['date']->locale('de')->isoFormat('ddd') }}</span>
                    </div>
                    <div class="calendar-row-main">
                        <span class="calendar-row-symbol">{{ $event['symbol'] }}</span>
                        <span class="calendar-row-name">{{ $event['name'] }}</span>
                        @if($event['sector'])
                            <span class="calendar-row-sector">{{ __($event['sector']) }}</span>
                        @endif
                    </div>
                    <div class="calendar-row-countdown">
                        {{ $event['days_until'] === 0 ? __('heute') : ($event['days_until'] === 1 ? __('morgen') : __(':n Tage', ['n' => $event['days_until']])) }}
                    </div>
                </a>
            @empty
                <p class="p-4 text-xs text-[var(--ak-muted)]">{{ __('Keine anstehenden Termine im Feed gefunden.') }}</p>
            @endforelse
        </div>
    </div>
</div>

<style>
    #earnings-calendar-page .calendar-chart {
        display: flex; align-items: flex-end; gap: 3px; padding: 1.1rem 1.1rem 1.6rem;
        overflow-x: auto;
    }
    #earnings-calendar-page .calendar-week {
        position: relative; flex: 1 1 0; min-width: 26px; display: flex; flex-direction: column;
        align-items: center; height: 130px;
    }
    #earnings-calendar-page .calendar-week-bar-track {
        position: relative; width: 100%; height: 90px; display: flex; align-items: flex-end; justify-content: center;
        border-bottom: 1px dashed var(--ak-border);
    }
    #earnings-calendar-page .calendar-week-bar {
        width: 65%; min-height: 6px; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #22d3ee, color-mix(in srgb, #22d3ee 45%, transparent));
        display: flex; align-items: flex-start; justify-content: center;
    }
    #earnings-calendar-page .calendar-week-count { margin-top: -14px; font-size: .6rem; font-weight: 900; color: var(--ak-text); }
    #earnings-calendar-page .calendar-week-label { margin-top: .35rem; font-size: .58rem; font-weight: 800; color: var(--ak-text); white-space: nowrap; }
    #earnings-calendar-page .calendar-week-range { font-size: .52rem; font-weight: 600; color: var(--ak-muted); white-space: nowrap; }
    #earnings-calendar-page .calendar-week.is-current .calendar-week-label { color: #22d3ee; }
    #earnings-calendar-page .calendar-week.is-current .calendar-week-bar-track { border-bottom-color: #22d3ee; }

    #earnings-calendar-page .calendar-list { display: flex; flex-direction: column; }
    #earnings-calendar-page .calendar-row {
        display: flex; align-items: center; gap: 1rem; padding: .7rem 1.1rem;
        border-top: 1px solid var(--ak-border); text-decoration: none; color: inherit;
        transition: background .15s ease;
    }
    #earnings-calendar-page .calendar-row:first-child { border-top: none; }
    #earnings-calendar-page .calendar-row:hover { background: color-mix(in srgb, #22d3ee 6%, transparent); }
    #earnings-calendar-page .calendar-row-date { flex: 0 0 48px; text-align: center; }
    #earnings-calendar-page .calendar-row-date b { display: block; font-size: .78rem; font-weight: 900; }
    #earnings-calendar-page .calendar-row-date span { display: block; font-size: .58rem; font-weight: 700; color: var(--ak-muted); text-transform: uppercase; }
    #earnings-calendar-page .calendar-row-main { flex: 1 1 auto; display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; min-width: 0; }
    #earnings-calendar-page .calendar-row-symbol { font-size: .78rem; font-weight: 900; color: #22d3ee; }
    #earnings-calendar-page .calendar-row-name { font-size: .74rem; font-weight: 600; color: var(--ak-text); }
    #earnings-calendar-page .calendar-row-sector { font-size: .62rem; font-weight: 700; color: var(--ak-muted); }
    #earnings-calendar-page .calendar-row-countdown {
        flex: 0 0 auto; font-size: .62rem; font-weight: 800; color: var(--ak-muted);
        padding: .25rem .6rem; border: 1px solid var(--ak-border); border-radius: .6rem; white-space: nowrap;
    }
</style>
</x-app-layout>
