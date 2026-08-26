@php
    $showTutorial = request()->routeIs('dashboard') && (request()->boolean('tutorial') || ! data_get(auth()->user()?->preferences, 'tutorial.completed_at'));
    $en = app()->getLocale() === 'en';
    $steps = $en ? [
        ['Welcome to AktienKI', 'This short tour shows the most important areas. You can restart it anytime from Help.', '[data-nav-key="dashboard"]'],
        ['Your dashboard', 'Signals, appointments, market data and personal lists come together here.', 'main'],
        ['Screeners', 'Search and compare stocks, indices, sectors, market conditions and news.', '[data-nav-key="screener"]'],
        ['Forecasts', 'Review AI signals and directions for 5, 10, 15 and 20 trading days.', '[data-nav-key="predictions"]'],
        ['Organize', 'Build watchlists, labels and model portfolios from the portfolio menu.', '[data-nav-key="depots"]'],
        ['Ready to begin', 'Open Help whenever you need an explanation or the downloadable PDF guide.', '[data-tour-help]'],
    ] : [
        ['Willkommen bei AktienKI', 'Diese kurze Tour zeigt dir die wichtigsten Bereiche. Du kannst sie später jederzeit unter Hilfe neu starten.', '[data-nav-key="dashboard"]'],
        ['Dein Dashboard', 'Hier laufen Signale, Termine, Marktdaten und persönliche Listen zusammen.', 'main'],
        ['Die Screener', 'Suche und vergleiche Aktien, Indizes, Sektoren, Marktlage und Nachrichten.', '[data-nav-key="screener"]'],
        ['Die Prognosen', 'Prüfe KI-Signale und Richtungen für 5, 10, 15 und 20 Handelstage.', '[data-nav-key="predictions"]'],
        ['Auswahl organisieren', 'Erstelle über das Depot-Menü Watchlists, Labels und Musterdepots.', '[data-nav-key="depots"]'],
        ['Du kannst starten', 'Unter Hilfe findest du jederzeit Erklärungen und das PDF-Handbuch.', '[data-tour-help]'],
    ];
@endphp
@if($showTutorial)
<div x-data="tutorialTour(@js($steps))" x-init="start()" x-cloak x-show="open" class="fixed inset-0 z-[250]" @keydown.escape.window="finish()">
    <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-[2px]"></div>
    <div x-ref="spotlight" class="pointer-events-none fixed rounded-2xl ring-4 ring-cyan-400 shadow-[0_0_0_9999px_rgba(2,6,23,.68),0_0_40px_rgba(34,211,238,.55)] transition-all duration-300"></div>
    <section class="fixed bottom-4 left-4 right-4 mx-auto max-w-md rounded-3xl border border-cyan-400/35 bg-[#0b1d2c] p-5 text-white shadow-2xl sm:bottom-8 sm:p-6" role="dialog" aria-modal="true">
        <div class="flex items-center justify-between"><span class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-300" x-text="`${current + 1} / ${steps.length}`"></span><button type="button" @click="finish()" class="text-xs font-bold text-slate-400 hover:text-white">{{ $en ? 'Skip' : 'Überspringen' }}</button></div>
        <h2 class="mt-3 text-xl font-black" x-text="steps[current].title"></h2><p class="mt-2 text-sm leading-6 text-slate-300" x-text="steps[current].text"></p>
        <div class="mt-5 flex items-center justify-between gap-3"><button type="button" @click="previous()" x-show="current > 0" class="rounded-xl border border-white/15 px-4 py-2.5 text-sm font-black">{{ $en ? 'Back' : 'Zurück' }}</button><span x-show="current === 0"></span><button type="button" @click="next()" class="rounded-xl bg-cyan-400 px-5 py-2.5 text-sm font-black text-slate-950" x-text="current === steps.length - 1 ? @js($en ? 'Finish' : 'Fertig') : @js($en ? 'Next' : 'Weiter')"></button></div>
    </section>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('tutorialTour', (rawSteps) => ({
    open: false, current: 0, steps: rawSteps.map(([title, text, selector]) => ({ title, text, selector })),
    start() { this.open = true; this.$nextTick(() => this.focus()); },
    focus() { const el = document.querySelector(this.steps[this.current].selector); if (!el) return; el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }); setTimeout(() => { const r = el.getBoundingClientRect(); Object.assign(this.$refs.spotlight.style, { left: `${Math.max(6, r.left - 6)}px`, top: `${Math.max(6, r.top - 6)}px`, width: `${Math.min(innerWidth - 12, r.width + 12)}px`, height: `${Math.min(innerHeight - 12, r.height + 12)}px` }); }, 280); },
    previous() { this.current--; this.focus(); }, next() { if (this.current >= this.steps.length - 1) return this.finish(); this.current++; this.focus(); },
    finish() { fetch(@js(route('tutorial.complete')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } }).finally(() => this.open = false); }
})));
</script>
@endif
