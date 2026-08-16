<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('AktienKI verbindet Kursdaten, Modelle und Backtests zu verständlichen Aktienanalysen.') }}">
    <title>{{ __('AktienKI – Daten. Modelle. Entscheidungen.') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { color-scheme: dark; }
        body { background:#06111f; }
        .home-grid {
            background-image:linear-gradient(rgba(34,211,238,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(34,211,238,.035) 1px,transparent 1px);
            background-size:64px 64px;
        }
        .home-glow { background:radial-gradient(circle,rgba(34,211,238,.14),transparent 68%); }
        .glass { background:linear-gradient(145deg,rgba(13,31,49,.86),rgba(7,22,37,.76)); border:1px solid rgba(34,211,238,.18); box-shadow:0 20px 60px rgba(1,8,18,.32),inset 0 1px rgba(255,255,255,.025); backdrop-filter:blur(18px); }
        .glass-soft { background:rgba(10,28,45,.58); border:1px solid rgba(34,211,238,.13); }
        .accent-edge { position:relative; }
        .accent-edge::before { content:"";position:absolute;inset:14% auto 14% -1px;width:3px;border-radius:99px;background:linear-gradient(#22d3ee,#0ea5e9);box-shadow:0 0 20px rgba(34,211,238,.55); }
        .product-scene { opacity:0; transform:translateY(10px) scale(.985); pointer-events:none; transition:opacity .55s ease,transform .55s ease; }
        .product-scene.is-active { opacity:1;transform:none;pointer-events:auto; }
        .signal-line { stroke-dasharray:8 9; animation:dash 13s linear infinite; }
        .pulse-dot { animation:pulse 2.2s ease-in-out infinite; transform-origin:center; }
        .float-card { animation:float 5.5s ease-in-out infinite; }
        @keyframes dash { to { stroke-dashoffset:-170; } }
        @keyframes pulse { 50% { opacity:.38;transform:scale(.72); } }
        @keyframes float { 50% { transform:translateY(-5px); } }
        @media (prefers-reduced-motion:reduce) { .product-scene,.signal-line,.pulse-dot,.float-card { animation:none;transition:none; } }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden text-slate-100 antialiased">
<div class="home-grid relative min-h-screen overflow-hidden">
    <div class="home-glow pointer-events-none absolute -left-40 top-16 h-[32rem] w-[32rem]"></div>
    <div class="home-glow pointer-events-none absolute -right-52 top-0 h-[42rem] w-[42rem] opacity-60"></div>

    <header class="sticky top-0 z-40 border-b border-cyan-300/10 bg-[#06111f]/88 backdrop-blur-xl">
        <div class="mx-auto flex h-[72px] max-w-[1540px] items-center justify-between px-5 sm:px-8 xl:px-10">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}"><x-brand-wordmark /></a>
            <nav class="hidden items-center gap-1 lg:flex">
                <a href="#produkt" class="rounded-lg px-4 py-2 text-sm font-bold text-cyan-300">{{ __('Produkt') }}</a>
                <a href="{{ route('features') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-400 transition hover:bg-cyan-300/[.06] hover:text-white">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-400 transition hover:bg-cyan-300/[.06] hover:text-white">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-400 transition hover:bg-cyan-300/[.06] hover:text-white">{{ __('Preise') }}</a>
            </nav>
            <div class="flex items-center gap-2">
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl border border-cyan-300/25 bg-cyan-300/10 px-4 py-2.5 text-sm font-black text-cyan-300 transition hover:bg-cyan-300/15">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-3 py-2 text-sm font-bold text-slate-300 sm:block">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-black text-slate-950 shadow-[0_10px_28px_rgba(34,211,238,.2)] transition hover:bg-cyan-300">{{ __('Kostenlos testen') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main>
        <section class="mx-auto grid max-w-[1540px] gap-8 px-5 pb-10 pt-8 sm:px-8 lg:min-h-[700px] lg:grid-cols-[.88fr_1.12fr] lg:items-center lg:py-12 xl:px-10">
            <div class="relative z-10 max-w-2xl">
                @if ($showBetaNotice ?? false)
                    @php
                        $limit = $betaTesterLimit ?? 25; $count = min($limit, $betaTesterCount ?? 0);
                        $progress = $limit > 0 ? ($count / $limit) * 100 : 100;
                    @endphp
                    <div class="mb-7 inline-flex max-w-full items-center gap-3 rounded-xl border border-cyan-300/15 bg-cyan-300/[.055] px-3 py-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-cyan-300/10 text-cyan-300"><x-heroicon-o-beaker class="h-4 w-4" /></span>
                        <div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Beta · Tester gesucht') }}</p><p class="truncate text-[11px] font-semibold text-slate-300">{{ __('Die ersten 25 Tester erhalten dauerhaften Pro-Zugang.') }}</p></div>
                        <span class="ml-2 text-xs font-black text-white">{{ $count }}/{{ $limit }}</span>
                        <span class="hidden h-1.5 w-20 overflow-hidden rounded-full bg-white/10 sm:block"><i class="block h-full rounded-full bg-cyan-400" style="width:{{ $progress }}%"></i></span>
                    </div>
                @endif

                <p class="mb-5 flex items-center gap-3 text-[11px] font-black uppercase tracking-[.24em] text-cyan-300"><span class="h-px w-10 bg-cyan-400"></span>{{ __('KI-gestützte Marktanalyse') }}</p>
                <h1 class="max-w-[13ch] text-[clamp(2.8rem,6vw,5.8rem)] font-black leading-[.94] tracking-[-.055em] text-white">
                    {{ __('Mehr als eine') }} <span class="text-cyan-300">{{ __('Prognose.') }}</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-slate-300 sm:text-xl">
                    {{ __('AktienKI verbindet Kursdaten, Fundamentaldaten, technische Indikatoren und Walk-Forward-Backtests zu nachvollziehbaren Signalen und Strategien.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @guest<a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-cyan-400 px-6 py-3.5 text-sm font-black text-slate-950 shadow-[0_12px_34px_rgba(34,211,238,.2)]">{{ __('Plattform testen') }} <span>→</span></a>@endguest
                    <a href="#produkt" class="inline-flex items-center gap-2 rounded-xl border border-cyan-300/20 bg-cyan-300/[.04] px-6 py-3.5 text-sm font-black text-cyan-200">{{ __('So funktioniert es') }}</a>
                </div>
                <div class="mt-10 grid max-w-xl grid-cols-3 gap-3 border-t border-cyan-300/10 pt-6">
                    @foreach ([['stocks',__('Aktien')],['forecasts',__('Prognosen')],['data-points',__('Datenpunkte')]] as [$key,$label])
                        <div><b class="block text-xl font-black text-white sm:text-2xl">{{ isset($welcomeStats[$key]) ? number_format((int)$welcomeStats[$key],0,',','.') : '—' }}</b><span class="mt-1 block text-[9px] font-black uppercase tracking-[.15em] text-cyan-300/75">{{ $label }}</span></div>
                    @endforeach
                </div>
            </div>

            <div id="produkt" class="glass relative min-h-[520px] overflow-hidden rounded-[28px] p-3 sm:p-5 lg:min-h-[610px]" data-product-player>
                <div class="flex items-center justify-between border-b border-cyan-300/10 px-2 pb-4">
                    <div><p class="text-[9px] font-black uppercase tracking-[.2em] text-cyan-300">{{ __('AktienKI Intelligence') }}</p><h2 class="mt-1 text-lg font-black text-white">{{ __('Vom Markt zum Signal') }}</h2></div>
                    <div class="flex gap-1.5" role="tablist">
                        @foreach ([__('Analyse'),__('Prognose'),__('Strategie')] as $i=>$label)<button type="button" data-product-dot="{{ $i }}" class="rounded-lg px-3 py-2 text-[9px] font-black uppercase tracking-wide {{ $i===0?'bg-cyan-300/12 text-cyan-300':'text-slate-500' }}">{{ $label }}</button>@endforeach
                    </div>
                </div>

                <div class="relative mt-4 min-h-[430px] sm:min-h-[490px]">
                    <div class="product-scene is-active absolute inset-0" data-product-scene>
                        <div class="grid h-full gap-3 sm:grid-cols-[.92fr_1.08fr]">
                            <article class="glass-soft accent-edge rounded-2xl p-5">
                                <div class="flex items-start justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Globales Ranking') }}</p><h3 class="mt-2 text-2xl font-black">{{ __('Top-Aktie') }}</h3><p class="mt-1 font-bold text-cyan-300">KLAC · Technology</p></div><span class="rounded-lg border border-amber-300/25 bg-amber-300/10 px-3 py-1.5 text-xs font-black text-amber-300">#1</span></div>
                                <svg viewBox="0 0 360 150" class="mt-7 w-full"><defs><linearGradient id="lineFill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#22d3ee" stop-opacity=".3"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></linearGradient></defs><path d="M8 126 C45 115 58 118 88 96 S130 105 158 74 S202 80 229 48 S276 61 307 31 S340 28 352 15 L352 145 L8 145Z" fill="url(#lineFill)"/><path class="signal-line" d="M8 126 C45 115 58 118 88 96 S130 105 158 74 S202 80 229 48 S276 61 307 31 S340 28 352 15" fill="none" stroke="#22d3ee" stroke-width="4"/><circle class="pulse-dot" cx="352" cy="15" r="6" fill="#22d3ee"/></svg>
                                <div class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-xl bg-cyan-300/[.055] p-3"><small class="font-black uppercase text-slate-500">{{ __('20T Rendite') }}</small><b class="mt-1 block text-xl text-emerald-400">+8,4%</b></div><div class="rounded-xl bg-cyan-300/[.055] p-3"><small class="font-black uppercase text-slate-500">{{ __('Signal') }}</small><b class="mt-1 block text-xl text-cyan-300">BUY</b></div></div>
                            </article>
                            <div class="grid gap-3 sm:grid-rows-[auto_1fr]">
                                <article class="glass-soft float-card rounded-2xl p-4"><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('KI-Bewertung') }}</p><div class="mt-4 flex items-center justify-around gap-2">@foreach ([[82,'KI'],[88,'Konf.'],[74,'Stabil.']] as [$v,$l])<div class="grid h-20 w-20 place-items-center rounded-full p-1" style="background:conic-gradient(#22d3ee {{ $v }}%,#334155 0)"><span class="grid h-full w-full place-items-center rounded-full bg-[#0b1c2d] text-center"><b class="text-lg text-white">{{ $v }}</b><small class="block text-[8px] text-slate-400">{{ $l }}</small></span></div>@endforeach</div></article>
                                <article class="glass-soft rounded-2xl p-5"><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Warum dieses Signal?') }}</p><ul class="mt-4 space-y-4 text-sm leading-6 text-slate-300"><li class="flex gap-3"><i class="mt-2 h-2 w-2 shrink-0 rounded-full bg-emerald-400"></i>{{ __('Positive erwartete Rendite nach Kosten') }}</li><li class="flex gap-3"><i class="mt-2 h-2 w-2 shrink-0 rounded-full bg-emerald-400"></i>{{ __('Walk-Forward-Ergebnis bestätigt das Modell') }}</li><li class="flex gap-3"><i class="mt-2 h-2 w-2 shrink-0 rounded-full bg-amber-300"></i>{{ __('Risiko wird am Nutzerprofil ausgerichtet') }}</li></ul></article>
                            </div>
                        </div>
                    </div>

                    <div class="product-scene absolute inset-0" data-product-scene>
                        <article class="glass-soft h-full rounded-2xl p-5 sm:p-7"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Multi-Horizont-Prognose') }}</p><h3 class="mt-2 text-2xl font-black">{{ __('Nicht nur ein Zielwert') }}</h3></div><span class="rounded-xl border border-cyan-300/20 bg-cyan-300/[.06] px-4 py-2 text-xs font-black text-cyan-300">5 · 10 · 15 · 20 {{ __('Tage') }}</span></div><svg viewBox="0 0 700 300" class="mt-6 w-full"><g stroke="#334155" stroke-dasharray="5 10"><path d="M35 60H670"/><path d="M35 145H670"/><path d="M35 230H670"/></g><path d="M40 228 C105 215 140 220 205 180 S310 205 360 135 S470 155 525 90" fill="none" stroke="#64748b" stroke-width="3"/><path d="M525 90 L565 122 L605 78 L640 96 L675 38" fill="none" stroke="#22d3ee" stroke-width="4"/><path d="M525 90 L565 122 L565 145 L525 145Z" fill="#fb7185" opacity=".17"/><path d="M565 122 L605 78 L605 145 L565 145Z M605 78 L640 96 L640 145 L605 145Z M640 96 L675 38 L675 145 L640 145Z" fill="#34d399" opacity=".15"/>@foreach ([[565,122,'5T'],[605,78,'10T'],[640,96,'15T'],[675,38,'20T']] as [$x,$y,$t])<circle cx="{{ $x }}" cy="{{ $y }}" r="7" fill="#071625" stroke="#22d3ee" stroke-width="3"/><text x="{{ $x }}" y="{{ $y-15 }}" text-anchor="middle" fill="#67e8f9" font-size="14" font-weight="800">{{ $t }}</text>@endforeach</svg><div class="grid grid-cols-4 gap-2">@foreach ([['5T','-1,2%','text-rose-400'],['10T','+1,8%','text-emerald-400'],['15T','+2,4%','text-emerald-400'],['20T','+6,1%','text-emerald-400']] as [$d,$v,$c])<div class="rounded-xl border border-cyan-300/10 bg-[#081827]/70 p-3 text-center"><small class="text-slate-500">{{ $d }}</small><b class="mt-1 block {{ $c }}">{{ $v }}</b></div>@endforeach</div></article>
                    </div>

                    <div class="product-scene absolute inset-0" data-product-scene>
                        <article class="glass-soft h-full rounded-2xl p-5 sm:p-7"><div class="flex items-start justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Strategietester') }}</p><h3 class="mt-2 text-2xl font-black">{{ __('Aus Signalen wird ein System') }}</h3></div><span class="rounded-xl border border-emerald-300/20 bg-emerald-300/[.07] px-3 py-2 text-[9px] font-black text-emerald-300">{{ __('WALK-FORWARD') }}</span></div><div class="mt-6 grid gap-4 sm:grid-cols-[.72fr_1.28fr]"><div class="space-y-3">@foreach ([__('Risikoprofil'),__('Wait-Einstieg'),__('Dynamischer Stop'),__('Sektorrotation')] as $i=>$label)<div class="flex items-center justify-between rounded-xl border border-cyan-300/10 bg-[#081827]/70 p-3 text-xs font-bold text-slate-300"><span>{{ $label }}</span><i class="relative h-5 w-9 rounded-full {{ $i===0?'bg-amber-300/30':'bg-cyan-400/25' }}"><b class="absolute top-1 h-3 w-3 rounded-full bg-cyan-300 {{ $i===0?'left-1':'right-1' }}"></b></i></div>@endforeach</div><div class="rounded-2xl border border-cyan-300/10 bg-[#081827]/70 p-4"><div class="flex justify-between text-[9px] font-black uppercase text-slate-500"><span>{{ __('Strategie') }}</span><span>{{ __('Benchmark') }}</span></div><svg viewBox="0 0 420 210" class="mt-4 w-full"><g stroke="#334155" stroke-dasharray="4 8"><path d="M15 35H405"/><path d="M15 100H405"/><path d="M15 165H405"/></g><path d="M15 173 C58 164 76 140 112 143 S165 109 202 117 S253 74 292 84 S355 44 405 50" fill="none" stroke="#22d3ee" stroke-width="4"/><path d="M15 176 C68 171 90 159 132 160 S215 142 260 144 S340 124 405 130" fill="none" stroke="#64748b" stroke-width="3"/></svg><div class="grid grid-cols-3 gap-2 text-center"><div><b class="text-emerald-400">+18,7%</b><small class="block text-[8px] text-slate-500">{{ __('Rendite') }}</small></div><div><b class="text-white">61%</b><small class="block text-[8px] text-slate-500">{{ __('Treffer') }}</small></div><div><b class="text-white">1,64</b><small class="block text-[8px] text-slate-500">{{ __('Profitfaktor') }}</small></div></div></div></div></article>
                    </div>
                </div>
                <button type="button" data-product-prev class="absolute bottom-5 left-5 grid h-10 w-10 place-items-center rounded-xl border border-cyan-300/15 bg-[#06111f]/80 text-xl text-cyan-300">‹</button>
                <button type="button" data-product-next class="absolute bottom-5 right-5 grid h-10 w-10 place-items-center rounded-xl border border-cyan-300/15 bg-[#06111f]/80 text-xl text-cyan-300">›</button>
            </div>
        </section>

        <section class="border-y border-cyan-300/10 bg-[#071523]/78">
            <div class="mx-auto grid max-w-[1540px] gap-px px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 xl:px-10">
                @foreach ([[__('Datenbasis'),__('Kurse, Fundamentals und Makrodaten'),'database'],[__('Modellprüfung'),__('Walk-Forward, Filter und Stabilität'),'chart'],[__('Klare Signale'),__('BUY, WAIT und risikobasiertes HOLD'),'signal'],[__('Strategien'),__('Backtests mit dynamischen Exits'),'strategy']] as $i=>[$title,$text,$icon])
                    <article class="accent-edge px-5 py-7 lg:px-7"><span class="text-[10px] font-black text-cyan-300">0{{ $i+1 }}</span><h2 class="mt-3 text-lg font-black text-white">{{ $title }}</h2><p class="mt-2 text-sm leading-6 text-slate-400">{{ $text }}</p></article>
                @endforeach
            </div>
        </section>
    </main>
</div>
<script>
document.querySelectorAll('[data-product-player]').forEach((player) => {
    const scenes=[...player.querySelectorAll('[data-product-scene]')], dots=[...player.querySelectorAll('[data-product-dot]')]; let active=0,timer;
    const show=(n)=>{ active=(n+scenes.length)%scenes.length; scenes.forEach((s,i)=>s.classList.toggle('is-active',i===active)); dots.forEach((d,i)=>{d.classList.toggle('bg-cyan-300/12',i===active);d.classList.toggle('text-cyan-300',i===active);d.classList.toggle('text-slate-500',i!==active);}); };
    const play=()=>{clearInterval(timer);if(!matchMedia('(prefers-reduced-motion: reduce)').matches)timer=setInterval(()=>show(active+1),7000);};
    player.querySelector('[data-product-prev]').addEventListener('click',()=>{show(active-1);play();}); player.querySelector('[data-product-next]').addEventListener('click',()=>{show(active+1);play();}); dots.forEach((d,i)=>d.addEventListener('click',()=>{show(i);play();})); player.addEventListener('mouseenter',()=>clearInterval(timer));player.addEventListener('mouseleave',play);play();
});
</script>
</body>
</html>
