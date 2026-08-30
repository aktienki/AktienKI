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
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-dashboard-card {
            border-color: rgba(6, 182, 212, .28) !important;
            background: linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(240, 253, 250, .96)) !important;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .10), inset 3px 0 0 rgba(6, 182, 212, .58) !important;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-eyebrow {
            color: #0891b2 !important;
        }
        :root[data-theme="light"] .ak-market-situation-page.ak-detail-design .ak-market-tape-item {
            border-color: rgba(8, 145, 178, .30) !important;
            background: rgba(255, 255, 255, .72);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9), 0 2px 8px rgba(14, 116, 144, .05);
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
            border-color: rgba(251,146,60,.48) !important; background: rgba(251,146,60,.17) !important; color: #fdba74 !important;
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
            border-color: rgba(234,88,12,.36) !important; background: rgba(251,146,60,.14) !important; color: #c2410c !important;
        }
        :root[data-theme="light"] .ak-market-situation-page .ak-signal-distribution-cell[data-signal="hold"] {
            border-color: rgba(234,179,8,.48) !important; background: rgba(250,204,21,.16) !important; color: #a16207 !important;
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
            --ak-forecast-line: #a16207;
        }
        .ak-market-situation-page .ak-market-primary-grid .ak-standard-card-head {
            box-sizing: border-box !important;
            min-height: 5.9rem !important;
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
    </style>
    <div class="ak-market-situation-page ak-detail-design min-h-[calc(100dvh-73px)] overflow-visible pb-28 lg:pb-0">
        <livewire:dashboard.market-data />
    </div>

    <x-dashboard.bottom-bar />
</x-app-layout>
