    <style>
        @media (max-width: 767px) {
            #personal-dashboard {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }
            #personal-dashboard > .ak-container {
                width: 100%;
                max-width: 100%;
                padding-inline: 1rem !important;
            }
            #personal-dashboard .dashboard-main-header {
                position: relative;
                align-items: flex-end;
            }
            #personal-dashboard .dashboard-expanded-market-report {
                display: none !important;
            }
            #personal-dashboard .dashboard-collapsible-header {
                width: 100%;
                height: 5rem;
                min-height: 5rem;
            }
            #personal-dashboard .strategy-summary-metrics small {
                font-size: .52rem !important;
                letter-spacing: -.02em;
            }
            #personal-dashboard .strategy-summary-metrics b {
                font-size: .66rem !important;
                letter-spacing: -.025em;
            }
            #personal-dashboard .dashboard-main-header > div:first-child {
                min-width: 0;
                padding-right: 8.75rem;
            }
            #personal-dashboard .dashboard-mobile-risk-label {
                display: flex !important;
            }
            #personal-dashboard .dashboard-page-title {
                margin-top: .55rem !important;
                font-size: 1.25rem !important;
                line-height: 1.5rem !important;
            }
            #personal-dashboard .dashboard-personal-eyebrow,
            #personal-dashboard .dashboard-risk-profile {
                display: none !important;
            }
            #personal-dashboard .dashboard-header-actions {
                width: 100%;
                padding-top: .5rem;
            }
            #personal-dashboard .dashboard-aki-button {
                display: none !important;
            }
            #personal-dashboard .dashboard-theme-switch {
                position: absolute;
                top: 0;
                right: 0;
                z-index: 2;
                gap: 0;
                padding: .25rem;
            }
            #personal-dashboard .dashboard-theme-switch button {
                height: 2rem;
                padding-inline: .55rem;
            }
            #personal-dashboard .dashboard-bento {
                width: calc(100% - .5rem);
                max-width: calc(100% - .5rem);
                margin-inline: auto;
                grid-template-columns: minmax(0, 1fr) !important;
                grid-auto-rows: auto !important;
                align-items: start !important;
            }
            #personal-dashboard .dashboard-right-column {
                display: contents !important;
            }
            #personal-dashboard .dashboard-bento > *,
            #personal-dashboard .dashboard-bento [data-dashboard-card] {
                width: 100%;
                min-width: 0 !important;
                max-width: 100%;
                grid-column: 1 / -1 !important;
                overflow-x: hidden;
            }
            #personal-dashboard :is(
                .dashboard-bento [data-dashboard-card]:not(#dashboard-middle-column),
                #dashboard-middle-column > article
            ) {
                box-sizing: border-box;
                margin-inline: 0 !important;
                border-style: solid !important;
                border-width: 1px 1px 1px 4px !important;
                border-color: var(--ak-border) var(--ak-border) var(--ak-border) #0e7490 !important;
                border-radius: 1.25rem !important;
                background: transparent !important;
                box-shadow: 0 5px 16px rgba(15, 23, 42, .08) !important;
                transform: none !important;
            }
            #personal-dashboard #dashboard-newscenter-card {
                border-left: 4px solid #0e7490 !important;
                background: transparent !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            :root[data-theme="light"] #personal-dashboard :is(
                .dashboard-bento [data-dashboard-card]:not(#dashboard-middle-column),
                #dashboard-middle-column > article
            ) {
                border-color: rgba(14, 116, 144, .30) rgba(14, 116, 144, .30) rgba(14, 116, 144, .30) #087786 !important;
                box-shadow: 0 8px 22px rgba(15, 71, 79, .13) !important;
            }
            :root:not([data-theme="light"]) #personal-dashboard #dashboard-newscenter-card {
                border-left-color: #22d3ee !important;
            }
            :root:not([data-theme="light"]) #personal-dashboard :is(
                .dashboard-bento [data-dashboard-card]:not(#dashboard-middle-column),
                #dashboard-middle-column > article
            ) {
                border-color: rgba(34, 211, 238, .24) rgba(34, 211, 238, .24) rgba(34, 211, 238, .24) #22d3ee !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, .22) !important;
            }
            #personal-dashboard .dashboard-bento-market,
            #personal-dashboard #dashboard-middle-column,
            #personal-dashboard #dashboard-middle-column > article:first-child {
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
                grid-row: auto !important;
            }
            #personal-dashboard #dashboard-middle-column {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) !important;
                grid-template-rows: auto !important;
                grid-auto-rows: auto !important;
                gap: .75rem !important;
                align-content: start !important;
                flex: 0 0 auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            #personal-dashboard #dashboard-newscenter-card {
                order: -130 !important;
            }
            #personal-dashboard #dashboard-middle-column {
                display: contents !important;
            }
            #personal-dashboard #dashboard-middle-column > article:first-child {
                order: -120 !important;
            }
            #personal-dashboard .dashboard-daily-tips {
                order: -78 !important;
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit {
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="schedule"] {
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
            }
            #personal-dashboard .dashboard-bento-personal {
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
            }
            #personal-dashboard .dashboard-newscenter {
                height: auto !important;
                min-height: 0 !important;
                align-self: start !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="market"] {
                order: -100 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="market-summary"] {
                order: -110 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="schedule"] {
                order: -90 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="strategy"] {
                order: -80 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="signal-cockpit"] {
                order: -79 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="mobile-view"] {
                order: 999 !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card="community"] {
                order: 998 !important;
            }
            #personal-dashboard #dashboard-middle-column > article:first-child {
                padding: 1rem !important;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-grid {
                gap: .75rem !important;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-grid > div:first-child {
                align-items: flex-start;
                gap: .75rem;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-grid > div:first-child > div:first-child {
                flex: 1 1 auto;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-grid > div:first-child > div:last-child {
                gap: .4rem;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-grid > div:first-child > div:last-child > span {
                min-width: 0 !important;
                height: 2.5rem;
                padding-inline: .7rem;
            }
            #personal-dashboard #dashboard-middle-column .aki-profile-universe-score {
                margin-top: .1rem;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                column-gap: .4rem !important;
                row-gap: .4rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-grid > span:first-child {
                grid-column: 1 / -1 !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock b {
                overflow: visible !important;
                white-space: normal !important;
                text-overflow: clip !important;
                line-height: 1.15rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock small {
                width: 100%;
                margin-top: .2rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock time {
                display: inline !important;
                margin-left: auto;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-forecast-badge {
                width: 100% !important;
                min-width: 0 !important;
                max-width: none !important;
                height: 2rem !important;
                padding: 0 .12rem !important;
                overflow: hidden;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-forecast-badge svg {
                width: .82rem !important;
                height: .82rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-forecast-badge svg + svg {
                margin-left: -.28rem !important;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card] :is(table, svg, section, article, div) {
                max-width: 100%;
                min-width: 0;
            }
            /* Champion card's own factor box (distinct from the narrower
               desktop-column shrink further below, and from the smaller one
               used inside .dashboard-alternative-scores) - without this the
               stock name column is squeezed uncomfortably narrow on phones. */
            #personal-dashboard .dashboard-champion-entry .dashboard-champion-donut .segmented-score {
                width: 2.75rem;
                height: 2.75rem;
            }
            #personal-dashboard .dashboard-champion-entry .dashboard-champion-donut .segmented-score b {
                font-size: .68rem;
            }
            #personal-dashboard .dashboard-champion-entry .dashboard-champion-factors {
                height: 2.75rem;
                min-width: 3.4rem;
                padding: .1rem .3rem;
            }
            #personal-dashboard .dashboard-champion-entry .dashboard-champion-factors span {
                font-size: 6.5px;
            }
        }
        @media (max-width: 380px) {
            #personal-dashboard .dashboard-champion-entry .dashboard-champion-factors {
                display: none;
            }
        }
        @media (min-width: 768px) {
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-grid {
                grid-template-columns: minmax(15rem, 1fr) 3.75rem repeat(4, minmax(3rem, 1fr)) !important;
                column-gap: .5rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-head > span:nth-child(2),
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-date {
                display: block !important;
                width: 100%;
                text-align: center;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-date-mobile {
                display: none !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock small > span:last-child {
                margin-left: auto !important;
            }
        }
        @media (min-width: 1280px) and (max-width: 1535px) {
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-grid {
                grid-template-columns: minmax(9rem, 1fr) 3rem repeat(4, minmax(2.5rem, 1fr)) !important;
                column-gap: .3rem !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock small {
                flex-wrap: wrap;
                gap: .2rem .4rem !important;
                white-space: normal !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-cockpit-row > .aki-signal-stock small > span:last-child {
                width: 100%;
                margin-left: 0 !important;
            }
            #personal-dashboard .dashboard-bento-signal-cockpit .aki-signal-forecast-badge {
                min-width: 0 !important;
                padding-inline: .2rem !important;
                font-size: .58rem !important;
            }
        }
        @media (min-width: 1280px) {
            html:has(#personal-dashboard),
            body:has(#personal-dashboard) {
                height: 100dvh;
                overflow: hidden;
                overscroll-behavior: none;
            }
        }

        .ak-dashboard-card {
            background-color: color-mix(in srgb, var(--ak-card) 60%, transparent);
            border-color: color-mix(in srgb, #fb923c 32%, transparent);
            box-shadow: 0 12px 30px rgba(194, 65, 12, .10), inset 0 1px 0 rgba(251, 146, 60, .045);
            backdrop-filter: blur(8px);
        }
        .dashboard-plan-locked {
            filter: grayscale(.92) saturate(.18);
            opacity: .5;
            border-color: color-mix(in srgb, var(--ak-muted) 28%, transparent) !important;
            box-shadow: none !important;
        }
        .dashboard-plan-locked:hover { opacity: .66; }
        .dashboard-plan-badge,
        .dashboard-plan-mini-badge {
            position: absolute;
            z-index: 30;
            border: 1px solid rgba(251, 191, 36, .32);
            background: color-mix(in srgb, var(--ak-card) 88%, #fbbf24 12%);
            color: #fcd34d;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .dashboard-plan-badge { right: .7rem; top: .6rem; border-radius: .5rem; padding: .25rem .5rem; font-size: .48rem; }
        .dashboard-plan-mini-badge { right: .4rem; top: .35rem; border-radius: .35rem; padding: .15rem .3rem; font-size: .42rem; }
        .dashboard-bento-signal-cockpit section h3 { font-size: .72rem !important; line-height: 1rem; }
        .dashboard-bento-signal-cockpit section a { font-size: .68rem; line-height: 1rem; }
        .dashboard-bento-signal-cockpit section a b { font-size: .68rem !important; }
        .dashboard-bento-signal-cockpit section a span { font-size: .61rem; }
        .dashboard-bento-signal-cockpit .aki-horizon-signal,
        .dashboard-bento-signal-cockpit .aki-horizon-signal span { color: #67e8f9 !important; font-size: .72rem !important; line-height: .78rem; }
        .dashboard-bento-signal-cockpit svg,
        .dashboard-bento-signal-cockpit [aria-hidden="true"] { color: #67e8f9 !important; }
        .dashboard-bento-signal-cockpit section small { font-size: .61rem !important; line-height: .9rem; }
        .dashboard-bento-signal-cockpit section [style*="grid-template-columns:50px"] { font-size: .61rem !important; line-height: 1rem; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel h3 { font-size: .72rem !important; line-height: 1rem; letter-spacing: .12em; color: #67e8f9 !important; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] { font-size: .72rem !important; line-height: 1.1rem; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] b,
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] span { font-size: .72rem !important; }
        :root:not([data-theme="light"]) #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="positive"] {
            background: rgba(16, 185, 129, .24) !important;
            border-color: rgba(52, 211, 153, .72) !important;
            color: #a7f3d0 !important;
            box-shadow: inset 0 0 0 1px rgba(110, 231, 183, .08), 0 3px 10px rgba(5, 150, 105, .12);
        }
        :root:not([data-theme="light"]) #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="negative"] {
            background: rgba(244, 63, 94, .22) !important;
            border-color: rgba(251, 113, 133, .68) !important;
            color: #fecdd3 !important;
            box-shadow: inset 0 0 0 1px rgba(253, 164, 175, .07), 0 3px 10px rgba(225, 29, 72, .12);
        }
        :root:not([data-theme="light"]) #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="empty"] {
            background: rgba(71, 85, 105, .42) !important;
            border-color: rgba(148, 163, 184, .38) !important;
            color: #cbd5e1 !important;
        }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="positive"] { background: #d1fae5 !important; border-color: #34d399 !important; color: #047857 !important; }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="negative"] { background: #ffe4e6 !important; border-color: #fb7185 !important; color: #be123c !important; }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="empty"] { background: #e2e8f0 !important; border-color: #94a3b8 !important; color: #475569 !important; }
        @media (min-width: 1280px) {
            #dashboard-middle-column > .dashboard-daily-tips {
                grid-column: auto;
                grid-row: 3 / span 5;
                height: 100%;
                overflow: hidden;
            }
            #dashboard-middle-column {
                grid-template-rows: subgrid;
                grid-row: 1 / -1;
                row-gap: inherit;
                height: 100%;
            }
            #dashboard-middle-column > article:first-child {
                grid-row: 1 / span 2;
                height: 100%;
                min-height: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                padding: .65rem !important;
            }
            #dashboard-middle-column > article:first-child > div:first-child { margin-bottom: .3rem !important; }
            #dashboard-middle-column > article:first-child > div:first-child > span:first-child > span:first-child {
                width: 2.5rem;
                height: 2.5rem;
            }
            #dashboard-middle-column > article:first-child > div:first-child > span:first-child > span:first-child > svg {
                width: 1.35rem;
                height: 1.35rem;
            }
            #dashboard-middle-column > article:first-child #dashboard-champion-title {
                margin-top: .15rem;
                font-size: 1rem;
                line-height: 1.2rem;
            }
            #dashboard-middle-column > article:first-child > div:last-child {
                flex: 1 1 0%;
                min-height: 0;
                overflow: hidden;
            }
            #dashboard-middle-column > article:first-child > div:last-child > :is(a, .dashboard-champion-entry) {
                max-height: 100%;
                overflow-y: auto;
                gap: .45rem;
                padding: .5rem .75rem;
            }
            #dashboard-middle-column > article:first-child .dashboard-champion-donut .segmented-score {
                width: 3.25rem;
                height: 3.25rem;
            }
            #dashboard-middle-column > article:first-child .dashboard-champion-donut .segmented-score b {
                font-size: .75rem;
            }
            #dashboard-middle-column > article:first-child .dashboard-champion-factors {
                height: 3.25rem;
                min-width: 4.1rem;
                padding: .15rem .4rem;
            }
            #dashboard-middle-column > article:first-child .dashboard-champion-factors span {
                font-size: 7px;
            }
            #dashboard-middle-column > article:first-child .dashboard-champion-reason {
                padding-top: .3rem;
                line-height: .75rem;
            }
            .dashboard-bento-strategy {
                grid-column: 1 / span 4;
                grid-row: 1 / span 2;
                align-self: stretch;
                height: 100% !important;
                min-height: 0;
            }
            #personal-dashboard [data-dashboard-top-row-card] {
                box-sizing: border-box;
                align-self: stretch;
                height: 100% !important;
                min-height: 0 !important;
                max-height: 100%;
                overflow: hidden;
            }
            .dashboard-personal-column {
                scrollbar-width: thin;
                scrollbar-color: rgba(34, 211, 238, .7) rgba(15, 23, 42, .25);
            }
            .dashboard-personal-column::-webkit-scrollbar { width: 7px; }
            .dashboard-personal-column::-webkit-scrollbar-track {
                background: rgba(15, 23, 42, .25);
                border-radius: 999px;
            }
            .dashboard-personal-column::-webkit-scrollbar-thumb {
                background: rgba(34, 211, 238, .7);
                border-radius: 999px;
            }
            .dashboard-bento-signal-cockpit { margin-bottom: 0; }
            .dashboard-bento-signal-cockpit .dashboard-collapsible-header { margin-bottom: .45rem !important; }
            .dashboard-daily-tips .dashboard-collapsible-header { margin-bottom: .45rem !important; }
            .dashboard-bento-signal-cockpit .dashboard-signal-list {
                grid-template-rows: repeat(4, minmax(0, 1fr));
                align-content: stretch;
                overflow: hidden;
                padding-right: 0;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card {
                height: 100%;
                min-height: 0;
                overflow: hidden;
                gap: .45rem;
                padding: .35rem .55rem !important;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:first-child {
                width: 1.75rem;
                height: 1.75rem;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:first-child svg {
                width: 1rem;
                height: 1rem;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:last-child { overflow: hidden; }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:last-child > span:first-child b {
                font-size: .66rem !important;
                line-height: .8rem;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card time {
                padding: .1rem .3rem;
                font-size: .46rem;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:last-child > small:nth-child(2) {
                margin-top: 0 !important;
                font-size: .52rem !important;
                line-height: .65rem !important;
            }
            .dashboard-bento-signal-cockpit .aki-signal-compact-card > span:last-child > small:last-child {
                margin-top: .1rem !important;
                flex-wrap: nowrap !important;
                gap: .35rem !important;
                overflow: hidden;
                font-size: .48rem !important;
                line-height: .6rem !important;
            }
            .dashboard-bento {
                grid-template-rows: minmax(0, 2.05fr) repeat(8, minmax(0, 1fr));
            }
            .dashboard-bento-personal { grid-column: 1 / span 4; grid-row: 3 / span 5; }
            .dashboard-bento-community { grid-column: 1 / span 4; grid-row: 8 / span 2; align-self: stretch; height: 100%; }
            .dashboard-bento-personal [data-dashboard-tile="best-buy"],
            .dashboard-bento-personal [data-dashboard-tile="best-wait"] { display: none !important; }
            .dashboard-bento-personal > div:last-child { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .dashboard-bento-market { grid-column: 5 / span 4; grid-row: 1 / -1; align-self: stretch; height: 100%; }
            .dashboard-bento > #dashboard-market-overview-card { grid-column: 9 / span 4; grid-row: 1 / span 2; }
            .dashboard-bento-models { grid-column: 9 / span 4; grid-row: 1 / span 3; }
            .dashboard-bento-signal-cockpit { grid-column: 9 / span 4; grid-row: 1 / -1; align-self: stretch; height: 100%; }
            .dashboard-right-column { display: none; }
            .dashboard-right-column .dashboard-bento-signals { order: 1; flex: 0 0 auto; min-height: 0; height: auto !important; }
            .dashboard-right-column .dashboard-bento-market-summary { order: 2; flex: 0 0 auto; height: auto !important; }
            .dashboard-right-column .dashboard-bento-earnings { order: 3; flex: 1 1 0%; min-height: 0; height: auto !important; }
        }

        /* Compact desktop mode: retain the complete dashboard within one viewport. */
        @media (min-width: 1280px) and (max-height: 1100px) {
            html:has(#personal-dashboard),
            body:has(#personal-dashboard) {
                height: 100dvh;
                overflow: hidden;
                overscroll-behavior: none;
            }
            #personal-dashboard {
                height: calc(100dvh - 89px) !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }
            #personal-dashboard > .ak-container {
                height: 100% !important;
                min-height: 0 !important;
                padding-top: .75rem;
                padding-bottom: .75rem;
            }
            #personal-dashboard header { margin-bottom: .65rem; }
            #personal-dashboard header h1 { margin-top: .1rem; font-size: 1.55rem; line-height: 1.8rem; }
            #personal-dashboard .dashboard-bento {
                flex: 1 1 0%;
                min-height: 0;
                gap: .6rem;
                grid-template-rows: minmax(0, 2.05fr) repeat(8, minmax(0, 1fr));
            }
            #personal-dashboard .ak-dashboard-card { padding: .7rem; }
            #personal-dashboard .dashboard-bento-strategy { min-height: 0; padding: .55rem .7rem; }
            #personal-dashboard .dashboard-bento-personal > div:last-child,
            #personal-dashboard .dashboard-bento-community > div:last-child { margin-top: .45rem; gap: .4rem; }
            #personal-dashboard .dashboard-bento-personal a,
            #personal-dashboard .dashboard-bento-personal button,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div,
            #personal-dashboard .dashboard-bento-community > div:last-child > div { padding: .45rem .55rem; }
            #personal-dashboard .dashboard-bento-personal a small,
            #personal-dashboard .dashboard-bento-personal button small,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div small,
            #personal-dashboard .dashboard-bento-community small { margin-top: .3rem; }
            #personal-dashboard .dashboard-bento-market > div:nth-child(2) { margin-top: 0; }
            #personal-dashboard .dashboard-bento-market > p { margin-top: .65rem; font-size: .78rem; line-height: 1.28rem; }
            #personal-dashboard .dashboard-bento-models > div:first-child,
            #personal-dashboard .dashboard-bento-signals > div:first-child { margin-bottom: .55rem; }
            #personal-dashboard .dashboard-bento-models > div:last-child { gap: .3rem; }
            #personal-dashboard .dashboard-bento-models > div:last-child > div { padding-top: .35rem; padding-bottom: .35rem; }
        }

        @media (min-width: 1280px) and (max-height: 850px) {
            #personal-dashboard > .ak-container { padding-top: .5rem; padding-bottom: .5rem; }
            #personal-dashboard header { margin-bottom: .4rem; }
            #personal-dashboard header h1 { font-size: 1.3rem; line-height: 1.5rem; }
            #personal-dashboard .dashboard-bento { gap: .45rem; }
            #personal-dashboard .ak-dashboard-card { padding: .55rem; }
            #personal-dashboard .dashboard-bento-personal > div:first-child,
            #personal-dashboard .dashboard-bento-community > div:first-child { transform: scale(.9); transform-origin: left top; }
            #personal-dashboard .dashboard-bento-personal a,
            #personal-dashboard .dashboard-bento-personal button,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div,
            #personal-dashboard .dashboard-bento-community > div:last-child > div { padding: .3rem .45rem; }
            #personal-dashboard .dashboard-bento-personal .h-8,
            #personal-dashboard .dashboard-bento-community .h-8 { height: 1.55rem; width: 1.55rem; }
            #personal-dashboard .dashboard-bento-personal .text-lg,
            #personal-dashboard .dashboard-bento-community .text-lg { font-size: .9rem; }
            #personal-dashboard .dashboard-bento-market > p { font-size: .72rem; line-height: 1.12rem; }
        }
        @media (min-width: 1280px) {
            .dashboard-bento[data-dashboard-card-layout="custom"] {
                grid-auto-flow: dense;
                grid-template-columns: repeat(12, minmax(0, 1fr));
                grid-template-rows: minmax(0, 2.05fr) repeat(8, minmax(0, 1fr));
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card] {
                order: var(--dashboard-card-order);
                min-height: 0 !important;
                grid-column: span 4 !important;
                grid-row: span 3 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="signals"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="earnings"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="market-summary"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-size="small"] {
                grid-column: span 4 !important;
                grid-row: span 1 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-size="large"] {
                grid-column: span 8 !important;
                grid-row: span 3 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="small"] { grid-column: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="medium"] { grid-column: span 8 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="large"] { grid-column: span 12 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="small"] { grid-row: span 1 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="medium"] { grid-row: span 2 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="large"] { grid-row: span 3 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="1"] { grid-column: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="2"] { grid-column: span 8 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="3"] { grid-column: span 12 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="1"] { grid-row: span 1 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="2"] { grid-row: span 2 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="3"] { grid-row: span 3 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="4"] { grid-row: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="5"] { grid-row: span 5 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="6"] { grid-row: span 6 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="signal-cockpit"] {
                grid-column: 9 / span 4 !important;
                grid-row: 1 / -1 !important;
                align-self: stretch;
                height: 100%;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="market"] {
                grid-column: 5 / span 4 !important;
                grid-row: 1 / -1 !important;
                align-self: stretch;
                height: 100%;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="personal"] {
                grid-column: 1 / span 4 !important;
                grid-row: 3 / -1 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="community"] {
                grid-column: 1 / span 4 !important;
                grid-row: 8 / span 2 !important;
                align-self: stretch !important;
                height: 100% !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="strategy"] {
                grid-column: 1 / span 4 !important;
                grid-row: 1 / span 2 !important;
            }
            .dashboard-personal-column {
                display: grid !important;
                grid-column: 1 / span 4 !important;
                grid-row: 1 / -1 !important;
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: subgrid;
                gap: inherit;
                min-height: 0;
            }
            .dashboard-personal-column > [data-dashboard-card="strategy"] {
                grid-column: 1 !important;
                grid-row: 1 / span 2 !important;
                align-self: stretch !important;
                height: 100% !important;
                min-height: 0 !important;
            }
            .dashboard-personal-column > [data-dashboard-card="personal"] {
                grid-column: 1 !important;
                grid-row: 3 / -1 !important;
                align-self: stretch !important;
                height: 100% !important;
                min-height: 0 !important;
                display: flex !important;
                flex-direction: column;
            }
            .dashboard-personal-column > [data-dashboard-card="personal"] > div:last-child {
                flex: 1 1 auto;
                min-height: 0;
                grid-auto-rows: minmax(4.25rem, 1fr);
                align-content: start;
                overflow-y: auto;
                overscroll-behavior: contain;
                padding-right: .2rem;
            }
            .dashboard-personal-column > [data-dashboard-card="personal"] > div:last-child > :is(a, button, div) {
                min-height: 4.25rem;
                overflow: hidden;
            }
            .dashboard-personal-column > [data-dashboard-card="personal"] > div:last-child small {
                margin-top: .18rem !important;
                line-height: .7rem !important;
            }
            .dashboard-personal-column > [data-dashboard-card="community"] {
                grid-column: 1 !important;
                grid-row: 8 / span 2 !important;
                align-self: stretch !important;
                height: 100% !important;
                min-height: 0 !important;
                transform: none;
            }
            .dashboard-personal-column > [data-dashboard-card="community"] > div:last-child {
                width: 100%;
                min-height: 0;
                align-items: stretch;
            }
            .dashboard-personal-column > [data-dashboard-card="community"] > div:last-child > div {
                height: 4.25rem !important;
                min-height: 4.25rem !important;
                padding-top: .45rem !important;
                padding-bottom: .45rem !important;
                overflow: hidden;
            }
            .dashboard-bento > .dashboard-daily-tips {
                grid-column: 9 / span 4 !important;
                grid-row: 3 / span 7 !important;
                align-self: stretch !important;
                height: 100% !important;
            }
            .dashboard-bento > #dashboard-market-overview-card {
                grid-column: 9 / span 4 !important;
                grid-row: 1 / span 2 !important;
                align-self: stretch !important;
                height: 100% !important;
                min-height: 0 !important;
                overflow: hidden;
            }
            #dashboard-middle-column > [data-dashboard-card="signal-cockpit"] {
                grid-column: auto !important;
                grid-row: 3 / span 7 !important;
                align-self: stretch !important;
                width: 100% !important;
                height: 100% !important;
            }
        }
        @media (min-width: 1280px) and (max-height: 850px) {
            .dashboard-personal-column > [data-dashboard-card="personal"] > div:last-child {
                grid-auto-rows: minmax(3.65rem, 1fr);
            }
            .dashboard-personal-column > [data-dashboard-card="personal"] > div:last-child > :is(a, button, div) {
                min-height: 3.65rem;
            }
            .dashboard-personal-column > [data-dashboard-card="community"] > div:last-child > div {
                height: 3.65rem !important;
                min-height: 3.65rem !important;
            }
        }
        @media (max-width: 1279px) {
            .dashboard-bento [data-dashboard-card] { order: var(--dashboard-card-order); }
        }
        #personal-dashboard .dashboard-center-combined-card {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto auto;
            min-width: 0;
        }
        #personal-dashboard .dashboard-center-combined-card > #dashboard-newscenter-card,
        #personal-dashboard .dashboard-center-combined-card > [data-dashboard-card="signal-cockpit"] {
            grid-column: 1 !important;
            width: 100% !important;
            min-width: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
            #personal-dashboard .dashboard-center-combined-card > #dashboard-newscenter-card {
                grid-row: 1 !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                border-bottom: 1px solid var(--ak-border) !important;
        }
        #personal-dashboard .dashboard-center-combined-card > [data-dashboard-card="signal-cockpit"] {
            grid-row: 2 !important;
        }
        #personal-dashboard .dashboard-center-combined-card > article > .dashboard-card-help-btn {
            display: none !important;
        }
        #personal-dashboard .dashboard-alternative-entry {
            display: flex;
            min-height: 3.9rem;
            min-width: 0;
            align-items: center;
            gap: .65rem;
            border: 1px solid rgba(34, 211, 238, .25);
            border-radius: .9rem;
            background: rgba(255, 255, 255, .018);
            padding: .5rem .7rem;
            transition: border-color .15s ease, background .15s ease, transform .15s ease;
        }
        #personal-dashboard .dashboard-alternative-entry:hover {
            border-color: rgba(34, 211, 238, .5);
            transform: translateY(-1px);
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry {
            background: rgba(255, 255, 255, .5);
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry:hover {
            background: rgba(255, 255, 255, .78);
        }
        /* Same height and spacing as the middle-column stock cards
           (.dashboard-alternative-entry), so both columns' rows start at the
           same height and look like one consistent design. */
        #personal-dashboard .dashboard-opportunity-card {
            min-height: 3.9rem;
            box-sizing: border-box;
            gap: .65rem;
            border-radius: .9rem;
            padding: .5rem .7rem;
        }
        #personal-dashboard .dashboard-opportunity-card > span:first-child {
            height: 1.85rem;
            width: 1.85rem;
        }
        #personal-dashboard #dashboard-ranking-stocks-row,
        #personal-dashboard #dashboard-best-stocks-row {
            row-gap: .4rem !important;
        }
        #personal-dashboard .dashboard-alternative-rank {
            display: grid;
            width: 1.85rem;
            height: 1.85rem;
            flex: 0 0 1.85rem;
            place-items: center;
            border: 1px solid rgba(34, 211, 238, .4);
            border-radius: .6rem;
            background: rgba(34, 211, 238, .1);
            color: #0891b2;
            font-size: .74rem;
            font-weight: 950;
            line-height: 1;
        }
        /* Medal hints: #2 silver, #3 bronze — subtle, mirrors the champion's gold */
        #personal-dashboard .dashboard-alternative-entry--silver {
            border-color: rgba(148, 163, 184, .45);
            background-image: linear-gradient(135deg, rgba(203, 213, 225, .10), rgba(203, 213, 225, .02) 60%);
            box-shadow: inset 0 0 0 1px rgba(203, 213, 225, .12), 0 6px 20px -12px rgba(148, 163, 184, .35);
        }
        #personal-dashboard .dashboard-alternative-entry--silver:hover {
            border-color: rgba(148, 163, 184, .7);
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry--silver {
            background-image: linear-gradient(135deg, rgba(148, 163, 184, .14), rgba(148, 163, 184, .04) 60%);
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, .18), 0 8px 20px -12px rgba(100, 116, 139, .25);
        }
        #personal-dashboard .dashboard-alternative-entry--silver .dashboard-alternative-rank {
            border-color: rgba(148, 163, 184, .5);
            background: rgba(148, 163, 184, .16);
            color: #64748b;
        }
        #personal-dashboard .dashboard-alternative-entry--bronze {
            border-color: rgba(180, 120, 70, .45);
            background-image: linear-gradient(135deg, rgba(205, 127, 50, .10), rgba(205, 127, 50, .02) 60%);
            box-shadow: inset 0 0 0 1px rgba(205, 127, 50, .12), 0 6px 20px -12px rgba(160, 100, 50, .35);
        }
        #personal-dashboard .dashboard-alternative-entry--bronze:hover {
            border-color: rgba(180, 120, 70, .7);
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry--bronze {
            background-image: linear-gradient(135deg, rgba(180, 100, 50, .13), rgba(180, 100, 50, .03) 60%);
            box-shadow: inset 0 0 0 1px rgba(180, 100, 50, .18), 0 8px 20px -12px rgba(150, 90, 45, .26);
        }
        #personal-dashboard .dashboard-alternative-entry--bronze .dashboard-alternative-rank {
            border-color: rgba(180, 120, 70, .55);
            background: rgba(205, 127, 50, .16);
            color: #b45309;
        }
        /* "Vielleicht in ein paar Tagen interessant" — set apart from the ranked alternatives */
        #personal-dashboard .dashboard-alternative-entry--soon,
        #personal-dashboard .dashboard-alternative-entry--soon:hover {
            margin-top: 1rem;
            border-style: solid;
            border-width: 3px;
            border-color: #07111f;
            background: transparent;
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry--soon,
        :root[data-theme="light"] #personal-dashboard .dashboard-alternative-entry--soon:hover {
            border-color: #e7edf3;
            background: transparent;
        }
        #personal-dashboard .dashboard-alternative-entry--soon .dashboard-alternative-rank {
            border-color: rgba(251, 191, 36, .5);
            background: rgba(251, 191, 36, .14);
            color: #d97706;
        }
        #personal-dashboard .dashboard-alternative-scores {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: .5rem;
        }
        #personal-dashboard .dashboard-alternative-scores .dashboard-champion-donut .segmented-score {
            width: 44px;
            height: 44px;
        }
        #personal-dashboard .dashboard-alternative-scores .dashboard-champion-donut .segmented-score b {
            font-size: 11px;
        }
        #personal-dashboard .dashboard-alternative-scores .dashboard-champion-factors {
            height: 44px;
            min-width: 64px;
            padding: .1rem .35rem;
            gap: 0;
        }
        #personal-dashboard .dashboard-alternative-scores .dashboard-champion-factors span {
            font-size: 6.5px;
        }
        @media (max-width: 420px) {
            #personal-dashboard .dashboard-alternative-scores .dashboard-champion-factors { display: none; }
        }
        @media (min-width: 1280px) {
            #personal-dashboard .dashboard-center-combined-card {
                grid-column: 5 / span 4 !important;
                grid-row: 1 / -1 !important;
                grid-template-rows: auto minmax(0, 1fr);
                align-self: stretch;
                height: 100%;
            }
            #personal-dashboard #dashboard-middle-column.dashboard-market-shell-empty { display: none !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] .dashboard-center-combined-card > [data-dashboard-card="signal-cockpit"] {
                grid-column: 1 !important;
                grid-row: 2 !important;
                height: 100% !important;
            }
        }
        .dashboard-aki-dots { display: inline-block; min-width: 1.6em; letter-spacing: .12em; animation: dashboard-aki-pulse 1.1s steps(4, end) infinite; }
        @@keyframes dashboard-aki-pulse { 0%,20% { opacity: .25; } 40% { opacity: .65; } 60%,100% { opacity: 1; } }
        #dashboard-aki-messages { scrollbar-width: thin; scrollbar-color: rgba(34, 211, 238,.7) rgba(15,23,42,.45); }
        #dashboard-aki-messages::-webkit-scrollbar { width: 8px; }
        #dashboard-aki-messages::-webkit-scrollbar-track { background: rgba(15,23,42,.45); border-radius: 999px; }
        #dashboard-aki-messages::-webkit-scrollbar-thumb { background: rgba(34, 211, 238,.7); border-radius: 999px; }
        .ak-dashboard-card, [data-dashboard-card], [data-mobile-dashboard-card], [data-help-card] { position: relative; }
        .dashboard-card-help-btn {
            position: absolute; top: .35rem; right: .35rem; z-index: 60;
            display: grid; place-items: center;
            width: 2.25rem; height: 2.25rem; padding: 0; margin: 0;
            border-radius: .5rem;
            border: 1px solid rgba(148, 163, 184, .55);
            background: rgba(15, 23, 42, .45);
            color: #cbd5e1;
            font-size: .7rem; font-weight: 900; line-height: 1;
            cursor: pointer; opacity: .8;
            transition: opacity .12s ease, color .12s ease, border-color .12s ease, background .12s ease;
        }
        :root[data-theme="light"] .dashboard-card-help-btn { background: rgba(255,255,255,.85); color: #475569; border-color: rgba(100,116,139,.5); }
        .dashboard-card-help-btn:hover,
        .dashboard-card-help-btn:focus-visible { opacity: 1; color: #fff; background: #0891b2; border-color: #22d3ee; outline: none; }
        @media (max-width: 640px) { .dashboard-card-help-btn { width: 2rem; height: 2rem; font-size: .62rem; } }
        /* Inline "?" placed inside a card header's control cluster (e.g. next to the cog) */
        .dashboard-card-help-btn--inline {
            position: static;
        }
        :root[data-theme="light"] .dashboard-card-help-btn--inline { background: rgba(255,255,255,.85); }
        /* Help modal: force readable white text over the dark panel */
        :root:not([data-theme="light"]) #dashboard-card-help-modal,
        :root:not([data-theme="light"]) #dashboard-card-help-modal h2,
        :root:not([data-theme="light"]) #dashboard-card-help-modal p,
        :root:not([data-theme="light"]) #dashboard-card-help-modal button,
        :root:not([data-theme="light"]) #dashboard-card-help-modal #dashboard-card-help-body { color: #ffffff !important; }
        :root:not([data-theme="light"]) #dashboard-card-help-modal .dashboard-card-help-eyebrow { color: #67e8f9 !important; }
        :root:not([data-theme="light"]) #dashboard-card-help-modal #dashboard-card-help-body p { color: #f1f5f9 !important; }

    </style>
