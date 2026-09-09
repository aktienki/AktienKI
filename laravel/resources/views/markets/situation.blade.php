<x-app-layout>
    <x-detail-page-theme />
    <style>
        /* The global-markets page uses the same deep dashboard card surface, not the
           softer detail-page slate surface. Keep this local so other detail pages
           retain their existing treatment. */
        .ak-market-situation-page.ak-detail-design .ak-market-command-hero,
        .ak-market-situation-page.ak-detail-design .ak-market-tape,
        .ak-market-situation-page.ak-detail-design .ak-dashboard-card {
            border-color: rgba(34, 211, 238, .28) !important;
            background: linear-gradient(145deg, rgba(9, 29, 47, .98), rgba(6, 22, 38, .98)) !important;
            box-shadow: 0 18px 42px rgba(0, 0, 0, .30), inset 3px 0 0 rgba(34, 211, 238, .62), inset 0 1px 0 rgba(207, 250, 254, .045) !important;
        }
        .ak-market-situation-page.ak-detail-design .ak-market-command-hero::after {
            border-color: rgba(103, 232, 249, .18) !important;
            box-shadow: 0 0 0 2.5rem rgba(34, 211, 238, .035), 0 0 0 5rem rgba(34, 211, 238, .02) !important;
        }
        .ak-market-situation-page.ak-detail-design .ak-market-eyebrow {
            color: #67e8f9 !important;
        }
        .ak-market-situation-page.ak-detail-design .ak-market-tape-item {
            border: 1px solid rgba(103, 232, 249, .18) !important;
            border-radius: .7rem;
        }
        .ak-market-situation-page.ak-detail-design .ak-market-tape {
            overflow: hidden;
            gap: .4rem !important;
            padding: .4rem;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-command-hero,
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-tape,
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-dashboard-card,
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-standard-card {
            border-color: #cbd5e1 !important; /* Tailwind slate-300 */
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0) !important; /* slate-100 → slate-200 */
            box-shadow: 0 14px 34px rgba(68, 64, 60, .12), inset 4px 0 0 #64748b !important; /* slate-500 */
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-eyebrow {
            color: #475569 !important; /* slate-600 */
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-tape-item {
            border-color: #cbd5e1 !important;
            background: #e2e8f0 !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .95), 0 2px 8px rgba(68, 64, 60, .08);
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-hero-stat {
            border-color: #cbd5e1 !important;
            background: #e2e8f0 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design :is(
            .ak-standard-card-head,
            .ak-detail-card-head,
            .ak-standard-market-head
        ) {
            border-bottom-color: #cbd5e1 !important;
            background: linear-gradient(110deg, #e2e8f0, #f1f5f9) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design :is(
            .ak-market-command-hero,
            .ak-market-tape,
            .ak-dashboard-card,
            .ak-standard-card,
            .ak-market-hero-stat,
            .ak-market-tape-item
        ) {
            transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design :is(
            .ak-market-command-hero,
            .ak-market-tape,
            .ak-dashboard-card,
            .ak-standard-card,
            .ak-market-hero-stat,
            .ak-market-tape-item
        ):hover {
            border-color: #475569 !important; /* slate-600 */
            background: linear-gradient(145deg, #cbd5e1, #e2e8f0) !important; /* slate-300 → slate-200 */
            box-shadow: 0 18px 38px rgba(68, 64, 60, .20), inset 4px 0 0 #334155 !important; /* slate-700 */
        }
        .ak-market-situation-page .ak-market-hero-stats {
            display: grid !important;
            width: 100% !important;
            min-width: 0 !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: clamp(.4rem, 1vw, .75rem) !important;
        }
        .ak-market-situation-page .ak-market-hero-stat {
            width: auto !important;
            min-width: 0 !important;
        }
        .ak-market-situation-page .ak-market-tape {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
        .ak-market-situation-page .ak-market-tape > * {
            min-width: 0 !important;
        }
        .ak-market-situation-page .ak-signal-distribution-grid {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            grid-auto-rows: 6.1rem !important;
            align-items: end !important;
            gap: .3rem !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell {
            min-height: 3.35rem !important;
            height: clamp(3.35rem, calc(3.35rem + (var(--signal-share, 0) * .05rem)), 6.1rem) !important;
            padding: .38rem .5rem !important;
            border-radius: .55rem !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell strong {
            margin-top: .05rem !important;
            font-size: .95rem !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell small {
            right: .42rem !important;
            bottom: .36rem !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell[data-signal="sell"] {
            border-color: rgba(225,93,115,.42) !important; background: rgba(225,93,115,.16) !important; color: #f08ca0 !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell[data-signal="wait"] {
            border-color: rgba(100,116,139,.48) !important; background: rgba(100,116,139,.17) !important; color: #cbd5e1 !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell[data-signal="hold"] {
            border-color: rgba(250,204,21,.58) !important; background: rgba(250,204,21,.18) !important; color: #fde047 !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell[data-signal="watch"] {
            border-color: rgba(132,169,94,.48) !important; background: rgba(132,169,94,.18) !important; color: #b5cb8b !important;
        }
        .ak-market-situation-page .ak-signal-distribution-cell[data-signal="buy"] {
            border-color: rgba(52,211,153,.52) !important; background: rgba(16,185,129,.20) !important; color: #6ee7b7 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="sell"] {
            border-color: rgba(190,70,91,.38) !important; background: rgba(225,93,115,.12) !important; color: #bd4b60 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="wait"] {
            border-color: rgba(234,88,12,.36) !important; background: rgba(100,116,139,.14) !important; color: #334155 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="hold"] {
            border-color: #94a3b8 !important; background: #e2e8f0 !important; color: #475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="watch"] {
            border-color: rgba(101,127,57,.38) !important; background: rgba(132,169,94,.15) !important; color: #657f39 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="buy"] {
            border-color: rgba(14,116,144,.42) !important; background: rgba(19,127,115,.15) !important; color: #047857 !important;
        }
        .ak-market-situation-page .ak-market-analysis-metrics {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: .5rem !important;
        }
        .ak-market-situation-page {
            --ak-forecast-line: #c6a15b;
        }
        :root[data-theme="light"] .ak-market-situation-page {
            --ak-forecast-line: #64748b;
        }
        .ak-market-situation-page .ak-market-primary-grid .ak-standard-card-head {
            box-sizing: border-box !important;
            min-height: 5.9rem !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-primary-grid .ak-standard-card-head {
            border-bottom-color: #94a3b8 !important; /* slate-400 */
            background: linear-gradient(110deg, #e2e8f0, #f1f5f9) !important; /* slate-200 → slate-100 */
            box-shadow: inset 0 -1px 0 rgba(87, 83, 78, .16) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-primary-grid .ak-standard-card-head :is(
            .text-cyan-300,
            .text-cyan-400,
            .text-orange-400
        ) {
            color: #475569 !important; /* slate-600 */
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-primary-grid .ak-standard-card-head :is(
            .ak-transition-icon,
            [class*="rounded-xl"][class*="border"]
        ) {
            border-color: #94a3b8 !important;
            background-color: #e2e8f0 !important;
            color: #475569 !important;
        }
        /* Market Overview light theme: replace every cyan/teal UI accent with Tailwind Slate. */
        :root[data-theme="light"] .ak-market-situation-page :is([class*="text-cyan-"],[class*="text-teal-"]) {
            color: #475569 !important; /* slate-600 */
        }
        :root[data-theme="light"] .ak-market-situation-page :is([class*="border-cyan-"],[class*="border-teal-"]) {
            border-color: #cbd5e1 !important; /* slate-300 */
        }
        :root[data-theme="light"] .ak-market-situation-page :is([class*="bg-cyan-"],[class*="bg-teal-"]) {
            background-color: rgba(231, 229, 228, .82) !important; /* slate-200 */
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-market-eyebrow,
            .ak-standard-card-head > div > p:first-child,
            .ak-market-briefing-note,
            .ak-transition-icon
        ) {
            color: #475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-detail-panel,
            .ak-dashboard-card,
            .ak-standard-card
        )::before {
            background: linear-gradient(90deg, transparent, #94a3b8, #475569, transparent) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero::after {
            border-color: rgba(120, 113, 108, .22) !important;
            box-shadow: 0 0 0 2.5rem rgba(168, 162, 158, .08), 0 0 0 5rem rgba(214, 211, 209, .08) !important;
        }
        .ak-market-situation-page .ak-market-score-change strong {
            display: flex;
            align-items: center;
            gap: .25rem;
        }
        .ak-market-situation-page .ak-market-score-change strong i {
            font-size: .72em;
            font-style: normal;
            font-weight: 600;
            opacity: .72;
        }
        .ak-market-situation-page .ak-market-score-change[data-change="positive"] strong { color: #6ee7b7; }
        .ak-market-situation-page .ak-market-score-change[data-change="negative"] strong { color: #fda4af; }
        .ak-market-situation-page .ak-market-score-change[data-change="neutral"] strong { color: #cbd5e1; }
        @media (max-width: 900px) {
            .ak-market-situation-page .ak-market-hero-stat {
                min-height: 5rem !important;
                padding: .5rem !important;
            }
        }
        @media (min-width: 1024px) {
            .ak-market-situation-page .ak-market-hero-stats {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: .45rem !important;
            }
            .ak-market-situation-page .ak-market-hero-stat {
                min-height: 4.6rem !important;
                padding: .55rem !important;
                border-radius: .8rem !important;
            }
            .ak-market-situation-page .ak-market-hero-stat > span {
                font-size: .48rem !important;
                letter-spacing: .07em !important;
            }
            .ak-market-situation-page .ak-market-hero-stat strong {
                margin-top: .25rem !important;
                font-size: 1.05rem !important;
            }
            .ak-market-situation-page .ak-market-hero-stat > small,
            .ak-market-situation-page .ak-market-hero-stat strong small {
                font-size: .5rem !important;
            }
            .ak-market-situation-page .ak-market-tape {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            }
            .ak-market-situation-page .ak-signal-distribution-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                grid-auto-rows: 6.1rem !important;
            }
            .ak-market-situation-page .ak-market-analysis-metrics {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }
            .ak-market-situation-page .ak-market-command .ak-market-primary-grid > div > .ak-dashboard-card {
                min-height: 360px !important;
                height: 360px !important;
            }
        }
        @media (hover: none) and (pointer: coarse) {
            .ak-market-situation-page .ak-market-hero-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: .45rem !important;
            }
            .ak-market-situation-page .ak-market-hero-stat {
                min-height: 5rem !important;
                padding: .5rem !important;
            }
            .ak-market-situation-page .ak-market-tape {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                overflow: hidden !important;
            }
            .ak-market-situation-page .ak-market-tape-item {
                gap: .35rem !important;
                padding: .55rem .6rem !important;
            }
            .ak-market-situation-page .ak-market-change {
                padding: .2rem .32rem !important;
                font-size: .57rem !important;
            }
            .ak-market-situation-page .ak-signal-distribution-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                grid-auto-rows: 6.1rem !important;
            }
            .ak-market-situation-page .ak-market-analysis-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 767px) {
            .ak-market-situation-page .ak-signal-distribution-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
                grid-auto-rows: 5rem !important;
                gap: .22rem !important;
            }
            .ak-market-situation-page .ak-signal-distribution-cell {
                min-width: 0 !important;
                min-height: 3.15rem !important;
                height: clamp(3.15rem, calc(3.15rem + (var(--signal-share, 0) * .018rem)), 5rem) !important;
                padding: .3rem .32rem !important;
                border-radius: .48rem !important;
            }
            .ak-market-situation-page .ak-signal-distribution-cell span {
                overflow: hidden;
                font-size: .42rem !important;
                letter-spacing: .04em !important;
                text-overflow: ellipsis;
            }
            .ak-market-situation-page .ak-signal-distribution-cell strong {
                font-size: .82rem !important;
            }
            .ak-market-situation-page .ak-signal-distribution-cell small {
                right: .28rem !important;
                bottom: .25rem !important;
                font-size: .42rem !important;
            }
        }
        /* Notebook/browser scaling can reduce a wide Retina window to fewer
           than 1024 CSS pixels. From 768 CSS pixels onward, keep the desktop
           KPI and market strips in one row; phones retain the stacked grid. */
        @media (min-width: 768px) {
            .ak-market-situation-page .ak-market-hero-stats {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }
            .ak-market-situation-page .ak-market-hero-stat {
                min-height: 3.7rem !important;
                padding: .4rem .55rem !important;
            }
            .ak-market-situation-page .ak-market-tape {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            }
            .ak-market-situation-page .ak-signal-distribution-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            }
            .ak-market-situation-page .ak-market-analysis-metrics {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }
        }

        /* Final Market Overview skin: complete slate treatment. */
        :root[data-theme="light"] .ak-market-situation-page {
            --ak-bg:#f1f5f9;
            --ak-card:#f8fafc;
            --ak-card-hover:#f1f5f9;
            --ak-surface:#f8fafc;
            --ak-surface-muted:#f1f5f9;
            --ak-border:#cbd5e1;
            --ak-border-strong:#475569;
            --ak-text:#1e293b;
            --ak-muted:#475569;
            --ak-accent:#475569;
            --ak-accent-soft:rgba(71,85,105,.14);
            background-color:var(--ak-overview-page) !important;
            background-image:
                linear-gradient(rgba(87,83,78,.045) 1px,transparent 1px),
                linear-gradient(90deg,rgba(87,83,78,.045) 1px,transparent 1px) !important;
            background-size:30px 30px !important;
        }

        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero {
            border:1px solid #94a3b8 !important;
            background:linear-gradient(90deg,#1e293b 0%,#334155 58%,#475569 100%) !important;
            box-shadow:0 16px 36px rgba(41,37,36,.16),inset 4px 0 0 #475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero:hover {
            border-color:#475569 !important;
            background:linear-gradient(90deg,#1e293b 0%,#334155 55%,#475569 100%) !important;
            box-shadow:0 18px 40px rgba(67,56,202,.17),inset 4px 0 0 #64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero h1 { color:#f8fafc !important; }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero p { color:#cbd5e1 !important; }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero .ak-market-eyebrow,
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero .ak-market-live {
            border-color:#64748b !important;
            background:rgba(71,85,105,.14) !important;
            color:#cbd5e1 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-command-hero::after {
            border-color:rgba(100,116,139,.28) !important;
            box-shadow:0 0 0 2.5rem rgba(71,85,105,.07),0 0 0 5rem rgba(71,85,105,.035) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat {
            border:1px solid #64748b !important;
            background:linear-gradient(90deg,#2b3b50,#475569) !important;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.08) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat:hover {
            border-color:#64748b !important;
            background:linear-gradient(90deg,#334155,#526177) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat > span,
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat > small,
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat strong small { color:#cbd5e1 !important; }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-hero-stat strong { color:#f8fafc !important; }

        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape {
            border:1px solid #94a3b8 !important;
            background:linear-gradient(90deg,#1e293b,#475569) !important;
            box-shadow:0 12px 26px rgba(41,37,36,.14),inset 4px 0 0 #475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape:hover {
            border-color:#475569 !important;
            background:linear-gradient(90deg,#1e293b,#475569) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape-item {
            border-color:#64748b !important;
            background:linear-gradient(90deg,#2b3b50,#475569) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape-item:hover {
            border-color:#64748b !important;
            background:linear-gradient(90deg,#334155,#526177) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape-item p { color:#f1f5f9 !important; }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape-item p:first-child,
        :root[data-theme="light"] .ak-market-situation-page .ak-market-tape-item small { color:#cbd5e1 !important; }

        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-dashboard-card,
            .ak-standard-card,
            .ak-detail-panel,
            .ak-market-briefing
        ) {
            border:1px solid #cbd5e1 !important;
            background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 56%,#f8fafc 100%) !important;
            box-shadow:0 12px 26px rgba(68,64,60,.09),inset 4px 0 0 #64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-dashboard-card,
            .ak-standard-card,
            .ak-detail-panel,
            .ak-market-briefing
        ):hover {
            border-color:#475569 !important;
            background:linear-gradient(90deg,#f1f5f9 0%,#f8fafc 100%) !important;
            box-shadow:0 16px 32px rgba(67,56,202,.13),inset 4px 0 0 #475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-dashboard-card,
            .ak-standard-card,
            .ak-detail-panel
        )::before {
            background:linear-gradient(90deg,transparent,#64748b,#475569,transparent) !important;
        }

        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-standard-card-head,
            .ak-macro-card-head,
            .ak-detail-card-head
        ) {
            border-color:#475569 !important;
            background:linear-gradient(90deg,#1e293b 0%,#334155 58%,#475569 100%) !important;
            box-shadow:inset 0 -1px 0 rgba(100,116,139,.45) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-standard-card-head,
            .ak-macro-card-head,
            .ak-detail-card-head
        ) :is(h2,h3,p,small,span) {
            color:#f1f5f9 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-standard-card-head,
            .ak-macro-card-head,
            .ak-detail-card-head
        ) :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"]) {
            color:#64748b !important;
        }

        :root[data-theme="light"] .ak-market-situation-page :is(
            .ak-market-assessment,
            .ak-market-analysis-metrics > div,
            .ak-market-briefing-note,
            .ak-signal-overview-bias,
            section[class*="bg-[var(--ak-surface-muted)]"]
        ) {
            border-color:#cbd5e1 !important;
            background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 58%,#f8fafc 100%) !important;
            color:#334155 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-assessment-status {
            border-color:#475569 !important;
            background:#f1f5f9 !important;
            color:#334155 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-assessment-title { color:#334155 !important; }
        :root[data-theme="light"] .ak-market-situation-page .ak-transition-icon {
            border-color:#475569 !important;
            background:#e2e8f0 !important;
            color:#334155 !important;
        }

        :root[data-theme="light"] .ak-market-situation-page :is([class*="text-cyan-"],[class*="text-teal-"]) {
            color:#334155 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is([class*="border-cyan-"],[class*="border-teal-"]) {
            border-color:#64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is([class*="bg-cyan-"],[class*="bg-teal-"]) {
            background-color:#e2e8f0 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page :is(button,a)[class*="border-cyan-"]:hover {
            border-color:#475569 !important;
            background-color:#e2e8f0 !important;
            color:#1e293b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-section-head {
            border:1px solid #475569 !important;
            border-bottom:3px solid #475569 !important;
            border-radius:.85rem !important;
            background:linear-gradient(90deg,#1e293b 0%,#334155 58%,#475569 100%) !important;
            padding:.85rem 1rem !important;
            box-shadow:0 8px 18px rgba(41,37,36,.16) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-section-head :is(h2,h3,p,span,button) {
            color:#f1f5f9 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-section-head .ak-market-eyebrow,
        :root[data-theme="light"] .ak-market-situation-page .ak-market-section-head :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"]) {
            color:#64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-market-section-head :is(button,span)[class*="border-cyan-"] {
            border-color:#64748b !important;
            background:#475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg polyline:nth-of-type(3n + 1) {
            stroke:#475569 !important; /* orange-600 */
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg polyline:nth-of-type(3n + 2) {
            stroke:#475569 !important; /* slate-600 */
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg polyline:nth-of-type(3n) {
            stroke:#64748b !important; /* amber-500 */
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg polygon {
            fill:#64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg text {
            fill:#475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div svg polyline {
            filter:drop-shadow(0 1px 2px rgba(68,64,60,.28)) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div .mb-1 > span:nth-child(3n + 1) i {
            background:#475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div .mb-1 > span:nth-child(3n + 2) i {
            background:#475569 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-macro-card-head + div .mb-1 > span:nth-child(3n) i {
            background:#64748b !important;
        }

        /* High-specificity header typography for the three primary market cards. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head {
            border-bottom:3px solid #475569 !important;
            background:linear-gradient(90deg,#1e293b 0%,#334155 58%,#475569 100%) !important;
            box-shadow:inset 0 -1px 0 rgba(100,116,139,.48) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head h3 {
            color:#f8fafc !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head > div > p:first-child,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head > p:first-child {
            color:#64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head > div > p:not(:first-child) {
            color:#cbd5e1 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head > span,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head .ak-transition-icon,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design
        .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head p:first-child > span {
            border-color:#64748b !important;
            background:#475569 !important;
            color:#cbd5e1 !important;
        }

        /* Keep ticker typography readable against the graphite strip. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape {
            background:linear-gradient(90deg,#1e293b,#475569) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape .ak-market-tape-item {
            border-color:#64748b !important;
            background:linear-gradient(90deg,#2b3b50,#475569) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape .ak-market-tape-item p {
            color:#f8fafc !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape .ak-market-tape-item p:first-child,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape .ak-market-tape-item small {
            color:#cbd5e1 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-title,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head strong {
            color:#f8fafc !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-subtitle,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head small {
            color:#cbd5e1 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-copy > p:first-child {
            color:#64748b !important;
        }
        /* Light slate correction for the local Market Overview page. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel,.ak-market-briefing) {
            border-color:#cbd5e1 !important;
            background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 58%,#f8fafc 100%) !important;
            box-shadow:0 12px 28px rgba(68,64,60,.10),inset 4px 0 0 #64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head {
            border-color:#cbd5e1 !important;
            border-bottom:2px solid #64748b !important;
            background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 58%,#f8fafc 100%) !important;
            box-shadow:inset 0 -1px 0 rgba(71,85,105,.18) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(h2,h3,strong) {
            color:#1e293b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(p,small) {
            color:#475569 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"]),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-copy > p:first-child {
            color:#334155 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero h1,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-hero-stat strong,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape-item p {
            color:#1e293b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero p,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-hero-stat :is(span,small),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-tape-item :is(p:first-child,small) {
            color:#64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-market-hero-stat,.ak-market-tape-item) {
            border-color:#cbd5e1 !important;
            background:linear-gradient(90deg,#f1f5f9,#f8fafc) !important;
        }
        /* Slate stock terminal: final visual layer for Market Overview. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design {
            --ak-bg:#f1f5f9;
            --ak-card:#f8fafc;
            --ak-card-hover:#f1f5f9;
            --ak-surface:#f8fafc;
            --ak-surface-muted:#f1f5f9;
            --ak-border:#e2e8f0;
            --ak-border-strong:#64748b;
            --ak-text:#0f172a;
            --ak-muted:#475569;
            --ak-accent:#475569;
            --ak-accent-soft:rgba(100,116,139,.12);
            background-color:#f1f5f9 !important;
            background-image:
                radial-gradient(circle at 86% 8%,rgba(100,116,139,.16),transparent 25%),
                radial-gradient(circle at 8% 82%,rgba(100,116,139,.10),transparent 28%),
                linear-gradient(rgba(71,85,105,.055) 1px,transparent 1px),
                linear-gradient(90deg,rgba(71,85,105,.055) 1px,transparent 1px) !important;
            background-size:auto,auto,32px 32px,32px 32px !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero {
            border-color:#64748b !important;
            background:
                radial-gradient(circle at 88% 12%,rgba(148,163,184,.28),transparent 27%),
                linear-gradient(105deg,#0f172a 0%,#1e1b4b 58%,#1e293b 100%) !important;
            box-shadow:0 18px 42px rgba(49,46,129,.22),inset 4px 0 0 #94a3b8 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero h1 { color:#f8fafc !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero p { color:#cbd5e1 !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero :is(.ak-market-eyebrow,.ak-market-live) {
            border-color:#94a3b8 !important;
            background:rgba(100,116,139,.16) !important;
            color:#e2e8f0 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero::after {
            border-color:rgba(203,213,225,.24) !important;
            box-shadow:0 0 0 2.5rem rgba(100,116,139,.07),0 0 0 5rem rgba(100,116,139,.035) !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel,.ak-market-briefing,.ak-market-tape) {
            border-color:#e2e8f0 !important;
            background:linear-gradient(105deg,rgba(241,245,249,.98),rgba(248,250,252,.98) 62%,rgba(238,242,255,.96)) !important;
            box-shadow:0 14px 32px rgba(49,46,129,.11),inset 4px 0 0 #64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel,.ak-market-briefing,.ak-market-tape):hover {
            border-color:#64748b !important;
            background:linear-gradient(105deg,#f1f5f9,#f8fafc) !important;
            box-shadow:0 18px 38px rgba(71,85,105,.17),inset 4px 0 0 #475569 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel)::before {
            background:linear-gradient(90deg,transparent,#94a3b8,#64748b,transparent) !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head {
            border-color:#64748b !important;
            border-bottom:2px solid #94a3b8 !important;
            background:linear-gradient(105deg,#0f172a 0%,#1e293b 58%,#1e293b 100%) !important;
            box-shadow:inset 0 -1px 0 rgba(203,213,225,.28) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(h2,h3,strong) { color:#f8fafc !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(p,small) { color:#cbd5e1 !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"],[class*="text-amber-"]),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-copy > p:first-child {
            color:#cbd5e1 !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"],[class*="text-amber-"]) { color:#475569 !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is([class*="border-cyan-"],[class*="border-teal-"],[class*="border-orange-"],[class*="border-amber-"]) { border-color:#94a3b8 !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is([class*="bg-cyan-"],[class*="bg-teal-"],[class*="bg-orange-"],[class*="bg-amber-"]) { background-color:#e2e8f0 !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-market-hero-stat,.ak-market-tape-item,.ak-market-assessment,.ak-market-analysis-metrics > div,.ak-market-briefing-note) {
            border-color:#e2e8f0 !important;
            background:linear-gradient(105deg,#f1f5f9,#f1f5f9) !important;
            color:#334155 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-market-hero-stat,.ak-market-tape-item) p,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-hero-stat strong { color:#0f172a !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-market-hero-stat,.ak-market-tape-item) :is(span,small,p:first-child) { color:#64748b !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-transition-icon {
            border-color:#94a3b8 !important;
            background:#e2e8f0 !important;
            color:#475569 !important;
        }
        /* Soft slate refinement: restrained contrast and low-key accents. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design {
            background-color:#f1f5f9 !important;
            background-image:
                radial-gradient(circle at 86% 8%,rgba(100,116,139,.07),transparent 25%),
                radial-gradient(circle at 8% 82%,rgba(100,116,139,.04),transparent 28%),
                linear-gradient(rgba(71,85,105,.035) 1px,transparent 1px),
                linear-gradient(90deg,rgba(71,85,105,.035) 1px,transparent 1px) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel,.ak-market-briefing,.ak-market-tape) {
            border-color:var(--ak-overview-border) !important;
            background:var(--ak-overview-card) !important;
            box-shadow:var(--ak-overview-shadow) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero:hover,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel,.ak-market-briefing,.ak-market-tape):hover {
            border-color:var(--ak-overview-border) !important;
            background:var(--ak-overview-card-hover) !important;
            box-shadow:var(--ak-overview-shadow-hover) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero h1 { color:var(--ak-overview-title) !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero p { color:var(--ak-overview-copy) !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero :is(.ak-market-eyebrow,.ak-market-live) {
            border-color:#e2e8f0 !important;
            background:rgba(238,242,255,.78) !important;
            color:#475569 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-command-hero::after {
            border-color:rgba(148,163,184,.12) !important;
            box-shadow:0 0 0 2.5rem rgba(100,116,139,.025),0 0 0 5rem rgba(100,116,139,.015) !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-primary-grid > div > .ak-standard-card.ak-dashboard-card > .ak-standard-card-head {
            border-color:var(--ak-overview-border) !important;
            border-bottom:1px solid var(--ak-overview-border) !important;
            background:var(--ak-overview-header) !important;
            box-shadow:none !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(h2,h3,strong) { color:var(--ak-overview-title) !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is(p,small) { color:var(--ak-overview-copy) !important; }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-detail-card-head,.ak-market-section-head) :is([class*="text-cyan-"],[class*="text-teal-"],[class*="text-orange-"],[class*="text-amber-"]),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-copy > p:first-child {
            color:#64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-market-hero-stat,.ak-market-tape-item,.ak-market-assessment,.ak-market-analysis-metrics > div,.ak-market-briefing-note) {
            border-color:var(--ak-overview-border) !important;
            background:var(--ak-overview-subcard) !important;
            box-shadow:none !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-dashboard-card,.ak-standard-card,.ak-detail-panel)::before {
            opacity:.42 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head + div svg polyline {
            filter:none !important;
        }
        .ak-market-situation-page .ak-market-index-flag {
            display:inline-flex;
            flex:0 0 auto;
            align-items:center;
            justify-content:center;
            border:0 !important;
            background:transparent !important;
            box-shadow:none !important;
            font-size:1.2rem;
            line-height:1;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="wait"] {
            border-color:#cbd5e1 !important;
            background:#f1f5f9 !important;
            color:#64748b !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="hold"] {
            border-color:#94a3b8 !important;
            background:#e2e8f0 !important;
            color:#475569 !important;
        }
        /* Final header contrast: dark copy on the light slate surfaces. */
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design :is(.ak-standard-card-head,.ak-macro-card-head,.ak-market-section-head) :is(h1,h2,h3,p,small,strong) {
            color:#334155 !important;
            opacity:1 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head > div > p:first-child,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-copy > p:first-child,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-section-head .ak-market-eyebrow {
            color:#475569 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head > div > p:not(:first-child),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-subtitle {
            color:#64748b !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-title,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-macro-card-head .ak-macro-card-values strong,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head h3 {
            color:#0f172a !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head > span,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head .ak-transition-icon,
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head p:first-child > span {
            border-color:#64748b !important;
            background:#475569 !important;
            color:#f8fafc !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head > span :is(span,small,strong),
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-standard-card-head p:first-child > span :is(span,small,strong) {
            color:#f8fafc !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-assessment {
            border-color:#cbd5e1 !important;
            background:linear-gradient(105deg,#f1f5f9,#f8fafc) !important;
            color:#475569 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-assessment-title {
            color:#334155 !important;
        }
        :root[data-theme="light"] body:not(.welcome-background) .ak-market-situation-page.ak-detail-design .ak-market-assessment-status {
            border-color:#94a3b8 !important;
            background:#f8fafc !important;
            color:#334155 !important;
        }
    </style>
    <div class="ak-market-situation-page ak-market-overview-skin ak-detail-design min-h-[calc(100dvh-73px)] overflow-visible pb-28 lg:pb-0">
        <livewire:dashboard.market-data />
    </div>

    <x-dashboard.bottom-bar />
</x-app-layout>
