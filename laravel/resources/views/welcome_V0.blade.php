<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AktienKI – Institutional AI for Smarter Investing</title>
    <meta name="description" content="AktienKI verbindet Market Intelligence, adaptive KI-Modelle und nachvollziehbare Aktienanalysen.">
    <style>
        .scene-learning.active,
        .scene-ai-engine.active {
            display: grid;
            place-items: center;
        }

        .learning-slide {
            width: 100%;
            min-height: 520px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 1.2rem;
            border: 1px solid rgba(148, 163, 184, 0.17);
            background: linear-gradient(180deg, rgba(14, 26, 58, 0.76), rgba(9, 15, 36, 0.78));
        }

        .learning-slide__image {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 520px;
            max-height: 520px;
            object-fit: contain;
            object-position: center;
            filter: drop-shadow(0 0 30px rgba(168, 85, 247, 0.32));
        }

        @media (max-width: 720px) {
            .learning-slide,
            .learning-slide__image {
                min-height: 340px;
                max-height: 340px;
            }
        }

:root{color-scheme:dark;--violet:#a855f7;--violet2:#6d28d9;--cyan:#38bdf8;--green:#22e3a1;--orange:#fb923c;--muted:#94a3b8}
        *{box-sizing:border-box}html{scroll-behavior:smooth}html,body{height:100%;overflow:hidden}body{margin:0;min-height:100vh;overflow-x:hidden;color:#f8fafc;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 84% 34%,rgba(124,58,237,.28),transparent 27%),radial-gradient(circle at 58% 78%,rgba(37,99,235,.11),transparent 32%),linear-gradient(135deg,#02040b 0%,#050917 48%,#0c0520 100%)}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.17;z-index:-1;background-image:linear-gradient(rgba(124,58,237,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.04) 1px,transparent 1px);background-size:58px 58px;mask-image:radial-gradient(circle at 72% 42%,black,transparent 72%)}
        .container{width:min(100% - 2rem,1480px);margin:auto}.topbar{position:sticky;top:0;z-index:30;border-bottom:1px solid rgba(255,255,255,.06);background:rgba(2,4,11,.78);backdrop-filter:blur(24px)}.topbar-inner{min-height:70px;display:flex;align-items:center;justify-content:space-between;gap:2rem}
        .brand{display:inline-flex;align-items:baseline;text-decoration:none;white-space:nowrap;letter-spacing:-.045em}.brand-main{font-size:clamp(1.35rem,2.3vw,2.35rem);font-weight:900;color:#fff}.brand-ki{margin-left:.36rem;font-size:clamp(2rem,3.6vw,3.7rem);line-height:.82;font-weight:950;color:transparent;background:linear-gradient(135deg,#d8b4fe,#a855f7 52%,#7c3aed);-webkit-background-clip:text;background-clip:text}.brand-domain{margin-left:.2rem;color:#94a3b8;font-size:clamp(.8rem,1.1vw,1.1rem);font-weight:700}
        .nav,.actions{display:flex;align-items:center;gap:1rem}.nav{gap:clamp(1.5rem,2.8vw,3.2rem)}.nav a,.mobile-menu a{color:#dce4ef;text-decoration:none;font-size:.96rem;font-weight:650}.nav a:hover,.mobile-menu a:hover{color:#c084fc}.status{display:flex;align-items:center;gap:.7rem}.status-dot{width:.72rem;height:.72rem;border-radius:50%;background:var(--green);box-shadow:0 0 0 .5rem rgba(34,227,161,.08),0 0 1.4rem rgba(34,227,161,.68)}.status-copy{display:grid}.status-copy strong{font-size:.88rem}.status-copy small{font-size:.7rem;color:var(--muted)}
        .button{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:.8rem;padding:.78rem 1.35rem;border-radius:.82rem;border:1px solid transparent;text-decoration:none;font-weight:800;color:#fff;transition:.2s ease}.button:hover{transform:translateY(-2px)}.button-primary{background:linear-gradient(135deg,#a855f7,#6d28d9);box-shadow:0 15px 38px rgba(109,40,217,.35)}.button-ghost{border-color:rgba(148,163,184,.28);background:rgba(15,23,42,.34)}.button-large{min-height:50px;padding-inline:2rem}
        .menu-button{display:none;width:46px;height:46px;border-radius:.9rem;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#fff}.menu-button svg{width:1.35rem;height:1.35rem}.mobile-menu{display:none;border-top:1px solid rgba(255,255,255,.06);background:rgba(2,4,11,.96)}.mobile-menu.open{display:block}.mobile-menu-inner{display:grid;gap:1rem;padding-block:1rem 1.2rem}.mobile-menu-actions{display:grid;grid-template-columns:repeat(2,1fr);gap:.8rem}
        .hero{height:calc(100svh - 70px);padding:.8rem 0;display:flex;flex-direction:column;overflow:hidden}.hero-grid{display:grid;grid-template-columns:minmax(0,.84fr) minmax(620px,1.16fr);align-items:center;gap:clamp(2rem,4vw,5rem);min-height:0;flex:1}.pill{display:inline-flex;padding:.65rem 1.15rem;border-radius:999px;border:1px solid rgba(168,85,247,.72);color:#d8b4fe;background:rgba(88,28,135,.10);font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.title{margin:1rem 0 0;font-size:clamp(2.8rem,4.8vw,5rem);line-height:.98;letter-spacing:-.058em;font-weight:950}.title span{color:transparent;background:linear-gradient(135deg,#d8b4fe,#a855f7 44%,#6d28d9);-webkit-background-clip:text;background-clip:text}.subtitle{margin:.8rem 0 0;color:#b4bfd1;font-size:clamp(1.08rem,1.7vw,1.42rem);line-height:1.55}.benefits{margin-top:1rem;display:grid;grid-template-columns:repeat(2,1fr);gap:.9rem 1.45rem;color:#e2e8f0}.benefit{display:flex;align-items:center;gap:.72rem}.check{width:1.4rem;height:1.4rem;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;border:1px solid #a855f7;color:#c084fc;font-size:.78rem}.hero-actions{margin-top:1rem;display:flex;gap:1.15rem;flex-wrap:wrap}.trust{margin-top:1rem;display:flex;align-items:center;gap:1rem}.avatars{display:flex;padding-left:.5rem}.avatars span{width:2.4rem;height:2.4rem;margin-left:-.5rem;display:grid;place-items:center;border-radius:50%;border:2px solid #0f172a;background:linear-gradient(135deg,#334155,#0f172a);font-size:.78rem;font-weight:800}.stars{color:#facc15;letter-spacing:.16em}.trust p{margin:.15rem 0 0;color:#d6dbea;font-size:.88rem}
        .visual{position:relative;min-width:0}.glow{position:absolute;border-radius:999px;filter:blur(85px);pointer-events:none}.glow-one{width:17rem;height:17rem;right:-4%;top:12%;background:rgba(124,58,237,.34)}.glow-two{width:12rem;height:12rem;left:12%;bottom:-2%;background:rgba(37,99,235,.18)}
        .terminal{position:relative;overflow:hidden;border-radius:2rem;border:1px solid rgba(129,140,248,.72);background:linear-gradient(145deg,rgba(9,18,45,.95),rgba(14,8,42,.97));box-shadow:0 34px 90px rgba(0,0,0,.52),0 0 42px rgba(124,58,237,.28);transform:perspective(1200px) rotateY(-2deg) rotateX(1deg)}.terminal-top{min-height:72px;padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.08)}.terminal-title{display:flex;align-items:center;gap:.65rem;font-size:.8rem;color:#dbeafe}.terminal-title span{color:#a78bfa}.scene-switch{display:flex;align-items:center;gap:.9rem;padding:.45rem .8rem;border-radius:999px;border:1px solid rgba(139,92,246,.28);background:rgba(76,29,149,.18);color:#c4b5fd;font-size:.72rem}.dots{display:flex;gap:.4rem}.dots button{width:.55rem;height:.55rem;padding:0;border:0;border-radius:50%;background:rgba(167,139,250,.28);cursor:pointer}.dots button.active{background:#c084fc;box-shadow:0 0 1rem rgba(192,132,252,.7)}
        .terminal-body{height:min(56svh,500px);min-height:0;padding:1.2rem}.scene{height:100%;min-height:0;display:none;animation:fadeSlide .48s ease both}.scene.active{display:block}.scene-market.active{display:grid;grid-template-columns:minmax(0,1.04fr) minmax(0,.96fr);gap:1rem}@keyframes fadeSlide{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
        .panel,.stock-card,.ensemble-card,.decision-card{border-radius:1.2rem;border:1px solid rgba(148,163,184,.17);background:linear-gradient(180deg,rgba(14,26,58,.76),rgba(9,15,36,.78))}.panel{padding:1rem;display:flex;flex-direction:column;gap:.95rem}.metric{padding:.88rem;border-bottom:1px solid rgba(255,255,255,.055)}.metric:last-of-type{border-bottom:0}.metric-head{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.7rem}.metric-head strong{font-size:.78rem}.metric-icon{width:2rem;height:2rem;display:grid;place-items:center;border-radius:50%}.metric-icon.green{color:var(--green);border:1px solid rgba(34,227,161,.5)}.metric-icon.orange{color:var(--orange);border:1px solid rgba(251,146,60,.5)}.metric-icon.blue{color:var(--cyan);border:1px solid rgba(56,189,248,.5)}.metric-value{font-size:1.55rem}.metric-value.green{color:var(--green)}.metric-value.orange{color:var(--orange)}.metric-value.blue{color:var(--cyan)}.bars{display:grid;grid-template-columns:repeat(20,1fr);gap:.2rem;margin-top:.8rem}.bars span{height:.84rem;border-radius:.18rem;background:rgba(71,85,105,.28)}.bars span.on.green{background:linear-gradient(180deg,#34d399,#10b981)}.bars span.on.orange{background:linear-gradient(180deg,#fb923c,#f97316)}.bars span.on.blue{background:linear-gradient(180deg,#38bdf8,#0ea5e9)}
        .regime-card{margin-top:auto;padding:1rem;display:grid;grid-template-columns:1fr 1.1fr;align-items:end;gap:.8rem;border-radius:1rem;border:1px solid rgba(139,92,246,.18);background:rgba(76,29,149,.12)}.regime-card span{display:block;color:#d8b4fe;font-size:.7rem}.regime-card strong{display:block;margin-top:.75rem;color:var(--violet);font-size:1.55rem}.regime-card small{color:var(--muted)}.regime-chart{width:100%;color:var(--violet)}
        .stock-card{padding:1rem;display:flex;flex-direction:column}.stock-top{display:flex;align-items:center;gap:.6rem;font-size:.74rem}.stock-top span{color:#facc15}.stock-card h2{margin:1rem 0 .3rem;font-size:2.1rem}.chip{margin:.8rem auto 1rem;width:9.5rem;height:7rem;display:grid;place-items:center;transform:perspective(600px) rotateX(58deg) rotateZ(-8deg);border-radius:1rem;background:linear-gradient(135deg,rgba(99,102,241,.3),rgba(168,85,247,.45));box-shadow:0 0 2.2rem rgba(124,58,237,.48)}.chip-core{width:4.6rem;height:4.6rem;display:grid;place-items:center;border-radius:.9rem;background:linear-gradient(145deg,#7c3aed,#312e81);font-size:1.4rem;font-weight:950;box-shadow:0 0 2rem rgba(192,132,252,.7)}.ai-score{display:grid;grid-template-columns:auto auto 1fr;align-items:end;gap:.45rem}.ai-score span{grid-column:1/-1;color:#a78bfa;font-size:.72rem}.ai-score strong{color:var(--violet);font-size:3.6rem;line-height:.9}.ai-score small{color:#64748b}.stock-grid{margin-top:1rem;display:grid;grid-template-columns:repeat(2,1fr);gap:.8rem}.stock-grid>div{padding:.9rem;border-radius:.8rem;background:rgba(15,23,42,.62)}.stock-grid span{display:block;color:#94a3b8;font-size:.62rem}.stock-grid strong{display:block;margin-top:.42rem;font-size:1.22rem}.champion{margin-top:auto;padding:.9rem;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.8rem;border-radius:.9rem;border:1px solid rgba(168,85,247,.32);background:rgba(88,28,135,.18)}.champion-icon{width:2.2rem;height:2.2rem;display:grid;place-items:center;border-radius:.7rem;color:#c084fc;background:rgba(168,85,247,.14)}.champion small{display:block;color:#c4b5fd;font-size:.62rem}.champion strong{display:block;margin-top:.15rem;color:#c084fc}.champion b{font-size:.7rem;color:#cbd5e1}
        .scene-ensemble.active,.scene-decision.active{display:grid;place-items:center}.ensemble-card,.decision-card{width:min(100%,680px);padding:1.4rem}.ensemble-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}.ensemble-header span,.decision-badge{color:#c084fc;font-size:.72rem;font-weight:800;letter-spacing:.12em}.ensemble-header h2{margin:.25rem 0 0}.ensemble-header b{padding:.5rem .8rem;border-radius:999px;background:rgba(139,92,246,.14);color:#d8b4fe;font-size:.72rem}.model-row{margin-top:.8rem;display:grid;grid-template-columns:2.4rem minmax(7rem,.8fr) minmax(10rem,1.4fr) 3rem;align-items:center;gap:.8rem;padding:1rem;border-radius:1rem;background:rgba(15,23,42,.52)}.model-rank{width:2.2rem;height:2.2rem;display:grid;place-items:center;border-radius:.65rem;background:rgba(139,92,246,.14);color:#d8b4fe;font-weight:900}.model-name{display:grid}.model-name small{color:#64748b}.model-bar{height:.55rem;overflow:hidden;border-radius:999px;background:rgba(71,85,105,.25)}.model-bar span{display:block;height:100%;background:linear-gradient(90deg,#7c3aed,#c084fc)}.model-score{font-size:1.25rem;font-weight:900;text-align:right}.ensemble-summary{margin-top:1rem;display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}.ensemble-summary>div{padding:1rem;border-radius:.9rem;background:rgba(88,28,135,.16);text-align:center}.ensemble-summary span{display:block;color:#a78bfa;font-size:.62rem}.ensemble-summary strong{display:block;margin-top:.4rem}
        .decision-card{text-align:center}.decision-card h2{margin:1rem 0 .2rem;font-size:2.4rem}.decision-card>p{margin:0;color:#64748b}.decision-score{width:9rem;height:9rem;margin:1.4rem auto;display:grid;place-items:center;align-content:center;border-radius:50%;border:1px solid rgba(192,132,252,.4);background:radial-gradient(circle,rgba(168,85,247,.22),rgba(76,29,149,.08));box-shadow:0 0 2.5rem rgba(124,58,237,.3)}.decision-score span{font-size:3.6rem;line-height:1;font-weight:950;color:#c084fc}.decision-score small{color:#94a3b8}.decision-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}.decision-stats>div{padding:1rem;border-radius:.9rem;background:rgba(15,23,42,.58)}.decision-stats span{display:block;color:#64748b;font-size:.7rem}.decision-stats strong{display:block;margin-top:.4rem}.decision-reasons{margin-top:1rem;padding:1rem;border-radius:.9rem;background:rgba(88,28,135,.12);text-align:left}.decision-reasons p{margin:.45rem 0 0;color:#cbd5e1;font-size:.84rem}.terminal-caption{margin-top:1rem;display:grid;place-items:center;gap:.65rem;color:#94a3b8;font-size:.8rem}.terminal-caption .dots button{width:.7rem;height:.7rem}
        .stats{margin-top:.6rem}.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);overflow:hidden;border-radius:1.3rem;border:1px solid rgba(129,140,248,.25);background:rgba(8,14,34,.82);box-shadow:0 18px 55px rgba(0,0,0,.32)}.stat{min-height:72px;display:flex;align-items:center;justify-content:center;gap:1rem;padding:1rem;border-right:1px solid rgba(255,255,255,.07)}.stat:last-child{border-right:0}.stat-icon{color:#a855f7;font-size:2.2rem}.stat strong,.stat small{display:block}.stat strong{font-size:1.6rem}.stat small{margin-top:.22rem;color:#cbd5e1}
        @media(max-width:1260px){.status{display:none}.hero-grid{grid-template-columns:minmax(0,.82fr) minmax(560px,1.18fr)}}
        @media(max-width:1060px){.nav,.actions{display:none}.menu-button{display:inline-flex}.hero-grid{grid-template-columns:1fr;min-height:auto}.copy{max-width:760px;margin:auto;text-align:center}.benefits{max-width:680px;margin-inline:auto;text-align:left}.hero-actions,.trust{justify-content:center}.terminal{transform:none}.stats-grid{grid-template-columns:repeat(3,1fr)}.stat:nth-child(3){border-right:0}.stat:nth-child(-n+3){border-bottom:1px solid rgba(255,255,255,.07)}}
        @media(max-width:720px){.container{width:min(100% - 1.2rem,1480px)}.topbar-inner{min-height:76px}.brand-main{font-size:1.15rem}.brand-ki{font-size:2rem}.brand-domain{font-size:.75rem}.hero{padding-top:1.3rem}.pill{font-size:.65rem;padding:.55rem .8rem}.title{margin-top:1.3rem;font-size:clamp(2.7rem,13vw,4rem)}.subtitle{font-size:1rem}.benefits{grid-template-columns:1fr}.hero-actions{flex-direction:column}.button-large{width:100%}.mobile-menu-actions{grid-template-columns:1fr}.terminal{border-radius:1.35rem}.terminal-top{padding:1rem}.scene-switch>span{display:none}.terminal-body{min-height:auto;padding:.8rem}.scene{min-height:auto}.scene-market.active{grid-template-columns:1fr}.chip{width:7.7rem;height:5.5rem}.chip-core{width:3.8rem;height:3.8rem}.model-row{grid-template-columns:2.2rem 1fr 3rem}.model-bar{grid-column:2/-1}.ensemble-summary,.decision-stats{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}.stat{min-height:96px;border-bottom:1px solid rgba(255,255,255,.07)}.stat:nth-child(odd){border-right:1px solid rgba(255,255,255,.07)}.stat:nth-child(even){border-right:0}.stat:last-child{grid-column:1/-1;border-bottom:0}.stat:nth-child(3){border-right:1px solid rgba(255,255,255,.07)}}

        .scene-world.active{display:grid}
        .world-slide{width:100%;height:100%;min-height:0;display:grid;grid-template-rows:auto minmax(0,1fr) auto;overflow:hidden;border-radius:1.2rem;border:1px solid rgba(148,163,184,.17);background:radial-gradient(circle at 72% 38%,rgba(124,58,237,.18),transparent 40%),linear-gradient(180deg,rgba(14,26,58,.78),rgba(9,15,36,.84))}
        .world-slide__header,.world-slide__footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;position:relative;z-index:2}
        .world-slide__header{padding:.9rem 1rem;border-bottom:1px solid rgba(255,255,255,.06)}
        .world-slide__header span{display:block;color:#a78bfa;font-size:.66rem;font-weight:800;letter-spacing:.14em}
        .world-slide__header h2{margin:.25rem 0 0;font-size:1.25rem;line-height:1.1}
        .world-live{display:inline-flex;align-items:center;gap:.45rem;padding:.42rem .68rem;border:1px solid rgba(34,227,161,.24);border-radius:999px;background:rgba(34,227,161,.08);color:#bbf7d0;font-size:.7rem;font-weight:800}
        .world-live i{width:.46rem;height:.46rem;border-radius:50%;background:#22e3a1;box-shadow:0 0 .8rem rgba(34,227,161,.78);animation:worldLivePulse 2.6s ease-in-out infinite}
        .world-slide__map{display:grid;place-items:center;min-height:0;overflow:hidden;padding:.25rem .35rem;position:relative}
        .world-slide__map:before{content:"";position:absolute;inset:8%;background:radial-gradient(circle at 27% 42%,rgba(34,227,161,.08),transparent 26%),radial-gradient(circle at 69% 43%,rgba(168,85,247,.16),transparent 34%),radial-gradient(circle at 56% 76%,rgba(56,189,248,.07),transparent 24%);filter:blur(24px)}
        .world-slide__map object,.world-slide__map img{position:relative;z-index:1;display:block;width:100%;height:100%;max-height:360px;object-fit:contain}
        .world-slide__footer{padding:.72rem 1rem;border-top:1px solid rgba(255,255,255,.06);color:#94a3b8;font-size:.68rem}
        .world-legend{display:flex;flex-wrap:wrap;gap:.78rem}
        .world-legend span{display:inline-flex;align-items:center;gap:.35rem;color:#cbd5e1}
        .world-legend i{width:.48rem;height:.48rem;border-radius:50%;box-shadow:0 0 .6rem currentColor}
        .world-legend .bullish{color:#22e3a1;background:#22e3a1}.world-legend .neutral{color:#fb923c;background:#fb923c}.world-legend .bearish{color:#ef4444;background:#ef4444}
        @keyframes worldLivePulse{0%,100%{opacity:.62;transform:scale(.9)}50%{opacity:1;transform:scale(1.08)}}
        @media(max-width:720px){.world-slide__map object,.world-slide__map img{max-height:280px}.world-slide__footer{align-items:flex-start;flex-direction:column}}
        @media(prefers-reduced-motion:reduce){.world-live i{animation:none}}

    </style>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a href="{{ url('/') }}" class="brand"><span class="brand-main">AKTIEN</span><span class="brand-ki">KI</span><span class="brand-domain">.com</span></a>
        <nav class="nav"><a href="#technology">Technologie</a><a href="#features">Features</a><a href="#pricing">Preise</a><a href="#resources">Ressourcen</a></nav>
        <div class="actions">
            <div class="status"><span class="status-dot"></span><span class="status-copy"><strong>AI Engine Online</strong><small>Letzte Aktualisierung: vor 12 Sek.</small></span></div>
            @auth
                <a href="{{ url('/dashboard') }}" class="button button-primary">Intelligence Center</a>
            @else
                <a href="{{ route('login') }}" class="button button-ghost">Login</a>
                @if (Route::has('register'))<a href="{{ route('register') }}" class="button button-primary">Kostenlos starten</a>@endif
            @endauth
        </div>
        <button id="menuButton" class="menu-button" type="button" aria-label="Navigation öffnen">
            <svg id="menuIcon" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <svg id="closeIcon" viewBox="0 0 24 24" fill="none" hidden><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div id="mobileMenu" class="mobile-menu"><div class="container mobile-menu-inner"><a href="#technology">Technologie</a><a href="#features">Features</a><a href="#pricing">Preise</a><a href="#resources">Ressourcen</a><div class="mobile-menu-actions">@auth<a href="{{ url('/dashboard') }}" class="button button-primary">Intelligence Center</a>@else<a href="{{ route('login') }}" class="button button-ghost">Login</a>@if (Route::has('register'))<a href="{{ route('register') }}" class="button button-primary">Kostenlos starten</a>@endif @endauth</div></div></div>
</header>
<main>
<section class="hero">
    <div class="container hero-grid">
        <section class="copy">
            <div class="pill">Institutionelle KI für private Investoren</div>
            <h1 class="title">Die nächste<br>Generation der <span>Aktienanalyse.</span></h1>
            <p class="subtitle">Adaptive KI-Modelle. 10 Jahre Historie.<br>Marktintelligenz in Echtzeit.</p>
            <div class="benefits"><div class="benefit"><span class="check">✓</span>Adaptive Ensemble KI</div><div class="benefit"><span class="check">✓</span>Marktregime-Erkennung</div><div class="benefit"><span class="check">✓</span>10 Jahre Trainingsdaten</div><div class="benefit"><span class="check">✓</span>Institutionelle Qualität</div></div>
            <div class="hero-actions">@auth<a href="{{ url('/dashboard') }}" class="button button-primary button-large">Intelligence Center →</a>@else @if (Route::has('register'))<a href="{{ route('register') }}" class="button button-primary button-large">Kostenlos starten →</a>@endif <a href="#heroDemo" class="button button-ghost button-large">Demo ansehen ▷</a>@endauth</div>
        </section>
        <section id="heroDemo" class="visual">
            <div class="glow glow-one"></div><div class="glow glow-two"></div>
            <div class="terminal">
                <div class="terminal-top"><div class="terminal-title"><span>▥</span><strong id="sceneLabel">DATENIMPORT &amp; HISTORIE</strong></div><div class="scene-switch"><span id="sceneCounter">SZENE 1 / 5</span><div class="dots" data-dots></div></div></div>
                <div class="terminal-body">

                    <div class="scene scene-learning active" data-scene="0">
                        <div class="learning-slide">
                            <img
                                src="{{ asset('images/welcome/scene-data-import.svg') }}"
                                alt="Machine-Learning-Pipeline von AktienKI"
                                class="learning-slide__image"
                                width="1536"
                                height="1024"
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>
                    <div class="scene scene-learning" data-scene="1">
                        <div class="learning-slide">
                            <img
                                src="{{ asset('images/welcome/scene-machine-learning.svg') }}"
                                alt="Machine-Learning-Pipeline von AktienKI"
                                class="learning-slide__image"
                                width="1536"
                                height="1024"
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>

                    <div class="scene scene-learning" data-scene="2">
                        <div class="learning-slide">
                            <img
                                src="{{ asset('images/welcome/scene-ai-score.svg') }}"
                                alt="Machine-Learning-Pipeline von AktienKI"
                                class="learning-slide__image"
                                width="1536"
                                height="1024"
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>


                    <div class="scene scene-market" data-scene="3">
                        <div class="panel">
                            <div class="metric"><div class="metric-head"><span class="metric-icon green">◌</span><strong>BULL SCORE</strong><b class="metric-value green">82%</b></div><div class="bars" data-bars="16" data-color="green"></div></div>
                            <div class="metric"><div class="metric-head"><span class="metric-icon orange">◇</span><strong>RISK SCORE</strong><b class="metric-value orange">28%</b></div><div class="bars" data-bars="6" data-color="orange"></div></div>
                            <div class="metric"><div class="metric-head"><span class="metric-icon blue">↗</span><strong>MOMENTUM</strong><b class="metric-value blue">74%</b></div><div class="bars" data-bars="15" data-color="blue"></div></div>
                            <div class="regime-card"><div><span>MARKTREGIME</span><strong>BULLISH</strong><small>Wahrscheinlichkeit 68%</small></div><svg class="regime-chart" viewBox="0 0 180 80"><path d="M4 70 C22 65, 28 56, 44 60 S68 75, 88 45 S118 14, 132 36 S156 48, 176 10" fill="none" stroke="currentColor" stroke-width="3"/></svg></div>
                        </div>
                        <div class="stock-card"><div class="stock-top"><span>★</span><strong>TOP RECOMMENDATION</strong></div><h2>NVIDIA</h2><div class="chip"><div class="chip-core">AI</div></div><div class="ai-score"><span>AI SCORE</span><strong>97</strong><small>/100</small></div><div class="stock-grid"><div><span>ERWARTETE RENDITE</span><strong style="color:var(--green)">+12.6%</strong></div><div><span>KONFIDENZ</span><strong style="color:var(--cyan)">89%</strong></div></div><div class="champion"><span class="champion-icon">♛</span><span><small>CHAMPION MODELL</small><strong>XGBoost</strong></span><b>Trefferquote 92%</b></div></div>
                    </div>

                    <div class="scene scene-world" data-scene="4">
                        <div class="world-slide">
                            <div class="world-slide__header">
                                <div>
                                    <span>GLOBAL AI MARKET SENTIMENT</span>
                                    <h2>Weltweite Marktlage</h2>
                                </div>
                                <div class="world-live"><i></i>LIVE</div>
                            </div>



                            <div class="world-slide__footer">
                                <div class="world-legend">
                                    <span><i class="bullish"></i>Bullish</span>
                                    <span><i class="neutral"></i>Neutral</span>
                                    <span><i class="bearish"></i>Bearish</span>
                                </div>
                                <span>Aktuelle KI-Marktlage</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="terminal-caption"><div class="dots" data-dots></div></div>
        </section>
    </div>
    <section class="stats"><div class="container stats-grid">@foreach ([['◔','5.142+','Aktien'],['◎','41','Börsen'],['✺','3','KI-Modelle'],['◫','10','Jahre Historie'],['◷','24/7','KI-Analyse']] as [$icon,$value,$label])<div class="stat"><span class="stat-icon">{{ $icon }}</span><div><strong>{{ $value }}</strong><small>{{ $label }}</small></div></div>@endforeach</div></section>
</section>
</main>
<script>
(()=>{const menuButton=document.getElementById('menuButton'),mobileMenu=document.getElementById('mobileMenu'),menuIcon=document.getElementById('menuIcon'),closeIcon=document.getElementById('closeIcon');menuButton?.addEventListener('click',()=>{const open=mobileMenu.classList.toggle('open');menuIcon.hidden=open;closeIcon.hidden=!open});mobileMenu?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{mobileMenu.classList.remove('open');menuIcon.hidden=false;closeIcon.hidden=true}));document.querySelectorAll('[data-bars]').forEach(container=>{const active=Number(container.dataset.bars||0),color=container.dataset.color||'green';for(let i=1;i<=20;i++){const bar=document.createElement('span');if(i<=active)bar.classList.add('on',color);container.appendChild(bar)}});const scenes=[...document.querySelectorAll('[data-scene]')],dotContainers=[...document.querySelectorAll('[data-dots]')],labels=['DATENIMPORT & HISTORIE','MACHINE LEARNING PIPELINE','AI SCORE & PROGNOSE','LIVE MARKET INTELLIGENCE','GLOBAL AI MARKET SENTIMENT'],sceneLabel=document.getElementById('sceneLabel'),sceneCounter=document.getElementById('sceneCounter');let active=0,timer;function renderDots(){dotContainers.forEach(container=>{container.innerHTML='';scenes.forEach((_,index)=>{const button=document.createElement('button');button.type='button';button.className=index===active?'active':'';button.addEventListener('click',()=>{setScene(index);startTimer()});container.appendChild(button)})})}function setScene(index){active=index;scenes.forEach((scene,i)=>scene.classList.toggle('active',i===active));sceneLabel.textContent=labels[active];sceneCounter.textContent=`${active+1} / ${scenes.length}`;renderDots()}function startTimer(){clearInterval(timer);if(window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;timer=setInterval(()=>setScene((active+1)%scenes.length),5000)}setScene(0);startTimer()})();
</script>
</body>
</html>
