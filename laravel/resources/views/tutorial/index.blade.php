@extends('layouts.aktienki')

@section('content')
@php
    $en = app()->getLocale() === 'en';
    $videoLocale = $en ? 'en' : 'de';
    $chapters = $en ? [
        ['Dashboard', 'Signals, forecasts, market conditions, appointments and personal lists come together on your dashboard.', 'squares-2x2', route('dashboard')],
        ['Understand signals', 'BUY supports an entry under current model conditions. HOLD means observe or maintain. WAIT/WATCH is not ready yet. SELL warns of weakness.', 'signal', route('predictions.index')],
        ['Read forecasts', 'The 5, 10, 15 and 20 trading-day arrows show model direction. Stock details add target prices, possible returns, confidence and risk.', 'chart', route('predictions.index')],
        ['Use screeners', 'Compare stocks, indices, sectors, market conditions and news. Narrow results by country, sector, score, signal and risk.', 'funnel', route('screener.index')],
        ['Organize stocks', 'Use watchlists, labels and model portfolios. Strategy filters can monitor your criteria and optionally notify you.', 'bookmark', route('watchlists.index')],
        ['Quality and risk', 'AI score, confidence, hit rate, average return and stability describe model quality. Always check risk and the Quality Gate.', 'shield', route('setup.quality')],
        ['Pro opportunities', 'Trade opportunities highlight HOLD/WAIT setups with short-term weakness and a positive 20-day outlook.', 'sparkles', route('opportunities.index')],
        ['Ask AKI', 'Use AKI chat for explanations of the displayed stock, signal or metric. Keep questions specific and verify important decisions.', 'chat', route('dashboard')],
    ] : [
        ['Dashboard', 'Im Dashboard laufen Signale, Prognosen, Marktlage, Termine und persönliche Listen zusammen.', 'squares-2x2', route('dashboard')],
        ['Signale verstehen', 'BUY spricht unter aktuellen Modellbedingungen für einen Einstieg. HOLD bedeutet beobachten oder halten. WAIT/WATCH ist noch nicht bereit. SELL warnt vor Schwäche.', 'signal', route('predictions.index')],
        ['Prognosen lesen', 'Die Pfeile für 5, 10, 15 und 20 Handelstage zeigen die Modellrichtung. Die Aktiendetails ergänzen Zielkurse, mögliche Renditen, Konfidenz und Risiko.', 'chart', route('predictions.index')],
        ['Screener nutzen', 'Vergleiche Aktien, Indizes, Sektoren, Marktlage und Nachrichten. Filtere nach Land, Sektor, Score, Signal und Risiko.', 'funnel', route('screener.index')],
        ['Aktien organisieren', 'Nutze Watchlists, Labels und Musterdepots. Strategiefilter überwachen deine Kriterien und informieren dich auf Wunsch.', 'bookmark', route('watchlists.index')],
        ['Qualität und Risiko', 'KI-Score, Konfidenz, Trefferquote, Durchschnittsrendite und Stabilität beschreiben die Modellqualität. Prüfe immer Risiko und Quality Gate.', 'shield', route('setup.quality')],
        ['Pro-Handelschancen', 'Handelschancen markieren HOLD/WAIT-Setups mit kurzfristiger Schwäche und positiver 20-Tage-Aussicht.', 'sparkles', route('opportunities.index')],
        ['AKI fragen', 'Nutze den AKI-Chat für Erklärungen zur angezeigten Aktie, zum Signal oder zu einer Kennzahl. Stelle konkrete Fragen und prüfe wichtige Entscheidungen.', 'chat', route('dashboard')],
    ];
@endphp
<div class="mx-auto max-w-7xl py-6 sm:py-10">
    <section class="relative overflow-hidden rounded-[2rem] border border-cyan-400/25 bg-[var(--ak-card)] px-5 py-8 shadow-2xl shadow-cyan-950/10 sm:px-10 sm:py-12">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,.15),transparent_38%),radial-gradient(circle_at_bottom_left,rgba(245,158,11,.10),transparent_36%)]"></div>
        <div class="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
            <div><p class="text-xs font-black uppercase tracking-[.24em] text-cyan-500">{{ $en ? 'AktienKI learning center' : 'AktienKI Lernbereich' }}</p><h1 class="mt-3 text-3xl font-black text-[var(--ak-text)] sm:text-5xl">{{ $en ? 'Your guide to AktienKI' : 'Dein Wegweiser durch AktienKI' }}</h1><p class="mt-4 max-w-3xl text-base leading-7 text-[var(--ak-muted)] sm:text-lg">{{ $en ? 'Learn the most important workflows in a few minutes - from the first signal to your personal watchlist.' : 'Lerne die wichtigsten Abläufe in wenigen Minuten kennen - vom ersten Signal bis zu deiner persönlichen Beobachtungsliste.' }}</p></div>
            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                <form method="POST" action="{{ route('tutorial.restart') }}">@csrf<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-400 px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-cyan-300"><x-heroicon-o-play class="h-5 w-5" />{{ $en ? 'Start interactive tour' : 'Interaktive Tour starten' }}</button></form>
                <a href="{{ route('tutorial.download') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-400/40 bg-amber-400/10 px-5 py-3 text-sm font-black text-amber-500 transition hover:bg-amber-400/20"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />{{ $en ? 'Download PDF guide' : 'PDF-Handbuch laden' }}</a>
            </div>
        </div>
    </section>
    <section class="mt-6 overflow-hidden rounded-[2rem] border border-cyan-400/25 bg-[var(--ak-card)] shadow-xl">
        <div class="flex flex-col gap-3 border-b border-[var(--ak-border)] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-500">{{ $en ? '24-second overview' : '24-Sekunden-Überblick' }}</p>
                <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ $en ? 'A quick tour of AktienKI' : 'AktienKI in einer kurzen Video-Tour' }}</h2>
                <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ $en ? 'Dashboard, signals, forecasts, screeners, lists and Pro opportunities.' : 'Dashboard, Signale, Prognosen, Screener, Listen und Pro-Handelschancen.' }}</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-cyan-400/25 bg-cyan-400/10 px-3 py-1.5 text-xs font-black text-cyan-500"><span class="h-2 w-2 animate-pulse rounded-full bg-cyan-400"></span>{{ $en ? 'With captions' : 'Mit Untertiteln' }}</span>
        </div>
        <div class="bg-[#061523] p-2 sm:p-4">
            <video class="aspect-video w-full rounded-2xl bg-[#061523] object-cover" controls autoplay muted playsinline preload="metadata" poster="{{ asset('media/aktienki-tour-'.$videoLocale.'-poster.jpg') }}" aria-label="{{ $en ? 'AktienKI product tour' : 'AktienKI Programm-Tour' }}">
                <source src="{{ asset('media/aktienki-tour-'.$videoLocale.'.mp4') }}" type="video/mp4">
                <track default kind="captions" srclang="{{ $videoLocale }}" label="{{ $en ? 'English' : 'Deutsch' }}" src="{{ asset('media/aktienki-tour-'.$videoLocale.'.vtt') }}">
                {{ $en ? 'Your browser cannot play this video.' : 'Dein Browser kann dieses Video nicht wiedergeben.' }}
            </video>
        </div>
    </section>
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($chapters as $index => [$title, $description, $icon, $url])
        <article class="group flex min-h-72 flex-col rounded-3xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 transition hover:-translate-y-1 hover:border-cyan-400/40 hover:shadow-xl">
            <div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-2xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-500">@switch($icon) @case('squares-2x2') <x-heroicon-o-squares-2x2 class="h-6 w-6" /> @break @case('signal') <x-heroicon-o-signal class="h-6 w-6" /> @break @case('chart') <x-heroicon-o-chart-bar-square class="h-6 w-6" /> @break @case('funnel') <x-heroicon-o-funnel class="h-6 w-6" /> @break @case('bookmark') <x-heroicon-o-bookmark-square class="h-6 w-6" /> @break @case('shield') <x-heroicon-o-shield-check class="h-6 w-6" /> @break @case('sparkles') <x-heroicon-o-sparkles class="h-6 w-6" /> @break @default <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" /> @endswitch</span><span class="text-4xl font-black text-cyan-500/15">{{ sprintf('%02d', $index + 1) }}</span></div>
            <h2 class="mt-5 text-lg font-black text-[var(--ak-text)]">{{ $title }}</h2><p class="mt-3 flex-1 text-sm leading-6 text-[var(--ak-muted)]">{{ $description }}</p><a href="{{ $url }}" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-cyan-500">{{ $en ? 'Open feature' : 'Funktion öffnen' }} <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-1" /></a>
        </article>
        @endforeach
    </section>
    <section class="mt-6 rounded-3xl border border-amber-400/25 bg-amber-400/[.06] p-5 sm:p-7"><div class="flex items-start gap-4"><x-heroicon-o-information-circle class="mt-0.5 h-7 w-7 shrink-0 text-amber-500" /><div><h2 class="font-black text-[var(--ak-text)]">{{ $en ? 'Important note' : 'Wichtiger Hinweis' }}</h2><p class="mt-2 text-sm leading-6 text-[var(--ak-muted)]">{{ $en ? 'AktienKI supports analysis and decision-making. Forecasts, scores and signals are not investment advice and cannot guarantee future performance.' : 'AktienKI unterstützt Analyse und Entscheidungsfindung. Prognosen, Scores und Signale sind keine Anlageberatung und garantieren keine zukünftige Entwicklung.' }}</p></div></div></section>
</div>
@endsection
