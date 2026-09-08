@php
    $forceTutorial = request()->boolean('tutorial');
    $showTutorial = request()->routeIs('dashboard') && ($forceTutorial || ! data_get(auth()->user()?->preferences, 'tutorial.completed_at'));
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
<div data-tutorial-tour data-force-tutorial="{{ $forceTutorial ? '1' : '0' }}" class="fixed inset-0 z-[250]">
    <div data-tour-backdrop class="absolute inset-0 bg-slate-950/75 backdrop-blur-[2px]" aria-hidden="true"></div>
    <div data-tour-spotlight hidden class="pointer-events-none fixed rounded-2xl ring-4 ring-cyan-400 shadow-[0_0_0_9999px_rgba(2,6,23,.68),0_0_40px_rgba(34,211,238,.55)] transition-all duration-300"></div>
    <section class="fixed bottom-4 left-4 right-4 mx-auto max-w-md rounded-3xl border border-cyan-400/35 bg-[#0b1d2c] p-5 text-white shadow-2xl sm:bottom-8 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="tutorial-tour-title" aria-describedby="tutorial-tour-description">
        <div class="flex items-center justify-between">
            <span data-tour-progress class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-300">1 / {{ count($steps) }}</span>
            <form method="POST" action="{{ route('tutorial.complete') }}" data-tour-finish>
                @csrf
                <button type="submit" class="text-xs font-bold text-slate-400 hover:text-white">{{ $en ? 'Skip' : 'Überspringen' }}</button>
            </form>
        </div>
        <h2 id="tutorial-tour-title" data-tour-title class="mt-3 text-xl font-black">{{ $steps[0][0] }}</h2>
        <p id="tutorial-tour-description" data-tour-description class="mt-2 text-sm leading-6 text-slate-300">{{ $steps[0][1] }}</p>
        <div class="mt-5 flex items-center justify-between gap-3">
            <button type="button" data-tour-previous hidden class="rounded-xl border border-white/15 px-4 py-2.5 text-sm font-black">{{ $en ? 'Back' : 'Zurück' }}</button>
            <span data-tour-spacer></span>
            <button type="button" data-tour-next class="rounded-xl bg-cyan-400 px-5 py-2.5 text-sm font-black text-slate-950">{{ $en ? 'Next' : 'Weiter' }}</button>
        </div>
    </section>
</div>
<script>
(() => {
    const root=document.querySelector('[data-tutorial-tour]');
    if(!root)return;
    const sessionKey='aktienki-tutorial-dismissed-v1',forced=root.dataset.forceTutorial==='1';
    try{if(!forced&&sessionStorage.getItem(sessionKey)==='1'){root.hidden=true;return}}catch(_){}
    const steps=@js($steps).map(([title,text,selector])=>({title,text,selector}));
    const progress=root.querySelector('[data-tour-progress]'),title=root.querySelector('[data-tour-title]'),description=root.querySelector('[data-tour-description]'),previous=root.querySelector('[data-tour-previous]'),spacer=root.querySelector('[data-tour-spacer]'),next=root.querySelector('[data-tour-next]'),spotlight=root.querySelector('[data-tour-spotlight]'),finishForm=root.querySelector('[data-tour-finish]');
    const labels={next:@js($en ? 'Next' : 'Weiter'),finish:@js($en ? 'Finish' : 'Fertig')};
    let current=0,closing=false,focusTimer=null;
    const focus=()=>{clearTimeout(focusTimer);spotlight.hidden=true;let element=null;try{element=document.querySelector(steps[current].selector)}catch(_){}if(!element)return;element.scrollIntoView({behavior:'smooth',block:'center',inline:'center'});const expected=current;focusTimer=setTimeout(()=>{if(expected!==current||root.hidden)return;const rect=element.getBoundingClientRect();if(rect.width<=0||rect.height<=0)return;Object.assign(spotlight.style,{left:`${Math.max(6,rect.left-6)}px`,top:`${Math.max(6,rect.top-6)}px`,width:`${Math.min(innerWidth-12,rect.width+12)}px`,height:`${Math.min(innerHeight-12,rect.height+12)}px`});spotlight.hidden=false},280)};
    const render=()=>{const step=steps[current];progress.textContent=`${current+1} / ${steps.length}`;title.textContent=step.title;description.textContent=step.text;previous.hidden=current===0;spacer.hidden=current!==0;next.textContent=current===steps.length-1?labels.finish:labels.next;focus()};
    const finish=event=>{event?.preventDefault();if(closing)return;closing=true;clearTimeout(focusTimer);try{sessionStorage.setItem(sessionKey,'1')}catch(_){}root.hidden=true;try{fetch(finishForm.action,{method:'POST',body:new FormData(finishForm),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}).catch(()=>{})}catch(_){}};
    previous.addEventListener('click',()=>{if(current>0){current--;render()}});
    next.addEventListener('click',()=>{if(current>=steps.length-1){finish();return}current++;render()});
    finishForm.addEventListener('submit',finish);
    root.querySelector('[data-tour-backdrop]').addEventListener('click',finish);
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!root.hidden){event.preventDefault();finish()}});
    render();
})();
</script>
@endif
