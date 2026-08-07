<div class="aki-strategy-demo h-full w-full" role="img" aria-label="{{ __('Animierte Vorschau des AktienKI Strategietesters') }}">
    <style>
        .aki-strategy-demo{--st-amber-bright:#e6c875;--st-amber:#c99a45;--st-amber-deep:#9f7435;--st-grid:rgba(148,163,184,.13);display:flex;align-items:center;justify-content:center;padding:6.5% 7%;color:#e8eef8}
        .aki-st-shell{position:relative;width:100%;max-width:920px;overflow:hidden;border:1px solid rgba(201,154,69,.45);border-radius:20px;background:linear-gradient(145deg,rgba(10,24,39,.97),rgba(13,31,46,.95));box-shadow:0 28px 70px rgba(0,0,0,.32),inset 0 1px rgba(230,200,117,.10)}
        .aki-st-shell:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 84% 6%,rgba(201,154,69,.16),transparent 32%),linear-gradient(90deg,transparent 49.8%,rgba(230,200,117,.035) 50%,transparent 50.2%);pointer-events:none}
        .aki-st-head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;border-bottom:1px solid rgba(201,154,69,.28);background:linear-gradient(90deg,rgba(201,154,69,.13),transparent 55%,rgba(230,200,117,.07))}
        .aki-st-eyebrow{font-size:9px;font-weight:900;letter-spacing:.21em;color:var(--st-amber-bright)}
        .aki-st-title{margin-top:3px;font-size:clamp(16px,2vw,24px);font-weight:900;letter-spacing:-.025em}
        .aki-st-running{display:flex;align-items:center;gap:7px;border:1px solid rgba(230,200,117,.38);border-radius:9px;background:rgba(201,154,69,.12);padding:7px 10px;color:var(--st-amber-bright);font-size:9px;font-weight:900;letter-spacing:.1em}
        .aki-st-running i{width:7px;height:7px;border-radius:50%;background:currentColor;box-shadow:0 0 12px currentColor;animation:aki-st-pulse 1.2s ease-in-out infinite}
        .aki-st-body{position:relative;display:grid;grid-template-columns:minmax(150px,.72fr) minmax(0,2fr);gap:13px;padding:13px}
        .aki-st-panel{border:1px solid rgba(148,163,184,.16);border-radius:13px;background:rgba(255,255,255,.035);padding:12px}
        .aki-st-label{font-size:8px;font-weight:900;letter-spacing:.16em;color:#8291a8;text-transform:uppercase}
        .aki-st-filter{margin-top:10px}
        .aki-st-filter:first-of-type{margin-top:8px}
        .aki-st-filter-top{display:flex;justify-content:space-between;font-size:8px;font-weight:800;color:#a9b6c9}
        .aki-st-track{position:relative;margin-top:6px;height:4px;border-radius:99px;background:#26374a}
        .aki-st-track:before{content:"";position:absolute;inset:0 auto 0 0;width:var(--fill);border-radius:inherit;background:linear-gradient(90deg,var(--st-amber-deep),var(--st-amber-bright));animation:aki-st-fill 5.6s cubic-bezier(.2,.8,.2,1) infinite}
        .aki-st-track:after{content:"";position:absolute;left:var(--fill);top:50%;width:10px;height:10px;border:2px solid var(--st-amber-bright);border-radius:50%;background:var(--st-amber);box-shadow:0 0 12px rgba(201,154,69,.72);transform:translate(-50%,-50%);animation:aki-st-knob 5.6s cubic-bezier(.2,.8,.2,1) infinite}
        .aki-st-run{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:13px;height:32px;border-radius:9px;background:linear-gradient(90deg,var(--st-amber-deep),var(--st-amber));font-size:9px;font-weight:900;letter-spacing:.08em;box-shadow:0 9px 22px rgba(159,116,53,.24);animation:aki-st-button 5.6s ease infinite}
        .aki-st-run svg{width:13px;height:13px}
        .aki-st-chart{position:relative;min-height:220px;overflow:hidden}
        .aki-st-chart-head{display:flex;align-items-start;justify-content:space-between;gap:10px}
        .aki-st-legend{display:flex;gap:10px;font-size:8px;font-weight:800;color:#91a0b4}
        .aki-st-legend i{display:inline-block;width:13px;height:2px;margin-right:4px;vertical-align:middle;background:currentColor}
        .aki-st-chart svg{display:block;width:100%;height:145px;margin-top:8px;overflow:visible}
        .aki-st-grid{stroke:var(--st-grid);stroke-width:1;stroke-dasharray:4 5}
        .aki-st-benchmark{fill:none;stroke:#64748b;stroke-width:2;stroke-dasharray:5 5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:900;stroke-dashoffset:900;animation:aki-st-draw 5.6s .25s ease-in-out infinite}
        .aki-st-equity-glow,.aki-st-equity{fill:none;stroke:var(--st-amber-bright);stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:900;stroke-dashoffset:900;animation:aki-st-draw 5.6s .55s ease-in-out infinite}
        .aki-st-equity-glow{stroke-width:8;opacity:.14}.aki-st-equity{stroke-width:3}
        .aki-st-scan{stroke:var(--st-amber);stroke-width:1.5;stroke-dasharray:3 4;opacity:0;animation:aki-st-scan 5.6s ease-in-out infinite}
        .aki-st-point{fill:#102635;stroke:var(--st-amber-bright);stroke-width:3;opacity:0;animation:aki-st-point 5.6s ease infinite}
        .aki-st-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin-top:2px}
        .aki-st-metric{border:1px solid rgba(148,163,184,.14);border-radius:9px;background:rgba(4,13,24,.34);padding:8px;opacity:0;transform:translateY(8px);animation:aki-st-result 5.6s ease infinite}
        .aki-st-metric:nth-child(2){animation-delay:.1s}.aki-st-metric:nth-child(3){animation-delay:.2s}.aki-st-metric:nth-child(4){animation-delay:.3s}
        .aki-st-metric small{display:block;font-size:7px;font-weight:900;letter-spacing:.1em;color:#7e8da2;text-transform:uppercase}
        .aki-st-metric b{display:block;margin-top:2px;font-size:clamp(11px,1.5vw,17px);color:var(--st-amber-bright)}
        .aki-st-metric:nth-child(3) b{color:var(--st-amber)}
        @keyframes aki-st-pulse{50%{opacity:.35;transform:scale(.75)}}
        @keyframes aki-st-fill{0%,10%{width:18%}35%,100%{width:var(--fill)}}
        @keyframes aki-st-knob{0%,10%{left:18%}35%,100%{left:var(--fill)}}
        @keyframes aki-st-button{0%,33%{filter:brightness(.82)}38%,46%{filter:brightness(1.45);transform:scale(.985)}52%,100%{filter:brightness(1)}}
        @keyframes aki-st-draw{0%,42%{stroke-dashoffset:900}76%,100%{stroke-dashoffset:0}}
        @keyframes aki-st-scan{0%,40%{opacity:0;transform:translateX(-210px)}48%{opacity:.8}76%{opacity:.8;transform:translateX(205px)}82%,100%{opacity:0;transform:translateX(205px)}}
        @keyframes aki-st-point{0%,70%{opacity:0;transform:scale(.4);transform-origin:center}78%,100%{opacity:1;transform:scale(1)}}
        @keyframes aki-st-result{0%,73%{opacity:0;transform:translateY(8px)}82%,100%{opacity:1;transform:translateY(0)}}
        @media(max-width:640px){.aki-strategy-demo{padding:5%}.aki-st-body{grid-template-columns:1fr}.aki-st-controls{display:none}.aki-st-chart{min-height:205px}.aki-st-head{padding:12px}.aki-st-metrics{grid-template-columns:repeat(2,1fr)}}
        @media(prefers-reduced-motion:reduce){.aki-strategy-demo *{animation:none!important}.aki-st-benchmark,.aki-st-equity-glow,.aki-st-equity{stroke-dashoffset:0}.aki-st-point,.aki-st-metric{opacity:1;transform:none}}
    </style>
    <div class="aki-st-shell">
        <div class="aki-st-head">
            <div><p class="aki-st-eyebrow">{{ __('STRATEGIE · SIMULATION') }}</p><h2 class="aki-st-title">{{ __('Strategietester') }}</h2></div>
            <span class="aki-st-running"><i></i>{{ __('BACKTEST LÄUFT') }}</span>
        </div>
        <div class="aki-st-body">
            <div class="aki-st-panel aki-st-controls">
                <p class="aki-st-label">{{ __('Strategie konfigurieren') }}</p>
                @foreach ([
                    [__('KI-Score'), '7,2', '72%'],
                    [__('Konfidenz'), '68 %', '68%'],
                    [__('Profitfaktor'), '2,4', '58%'],
                    [__('Max. Drawdown'), '12 %', '38%'],
                ] as [$label, $value, $fill])
                    <div class="aki-st-filter"><div class="aki-st-filter-top"><span>{{ $label }}</span><b>{{ $value }}</b></div><div class="aki-st-track" style="--fill:{{ $fill }}"></div></div>
                @endforeach
                <div class="aki-st-run"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 5 11 7-11 7V5Z"/></svg>{{ __('BACKTEST STARTEN') }}</div>
            </div>
            <div class="aki-st-panel aki-st-chart">
                <div class="aki-st-chart-head"><div><p class="aki-st-label">{{ __('Kapitalentwicklung') }}</p><strong>{{ __('Strategie gegen Benchmark') }}</strong></div><div class="aki-st-legend"><span style="color:var(--st-amber-bright)"><i></i>{{ __('Strategie') }}</span><span><i></i>S&amp;P 500</span></div></div>
                <svg viewBox="0 0 480 155" preserveAspectRatio="none" aria-hidden="true">
                    <g><path class="aki-st-grid" d="M8 25H472M8 65H472M8 105H472M8 145H472"/></g>
                    <path class="aki-st-benchmark" d="M8 131 C52 124 72 119 108 121 S165 104 202 108 S255 92 292 94 S344 78 379 79 S431 68 472 61"/>
                    <path class="aki-st-equity-glow" d="M8 137 C42 132 61 123 92 127 S141 111 174 113 S216 91 249 98 S287 74 317 79 S354 55 385 62 S428 35 472 22"/>
                    <path class="aki-st-equity" d="M8 137 C42 132 61 123 92 127 S141 111 174 113 S216 91 249 98 S287 74 317 79 S354 55 385 62 S428 35 472 22"/>
                    <path class="aki-st-scan" d="M240 10V147"/><circle class="aki-st-point" cx="472" cy="22" r="5"/>
                </svg>
                <div class="aki-st-metrics">
                    <div class="aki-st-metric"><small>{{ __('Rendite') }}</small><b>+38,6 %</b></div>
                    <div class="aki-st-metric"><small>{{ __('Trefferquote') }}</small><b>67 %</b></div>
                    <div class="aki-st-metric"><small>{{ __('Profitfaktor') }}</small><b>2,41</b></div>
                    <div class="aki-st-metric"><small>{{ __('Trades') }}</small><b>184</b></div>
                </div>
            </div>
        </div>
    </div>
</div>
