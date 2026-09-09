<style id="ak-global-light-theme">
@layer ak-global-light {
    :root[data-theme="light"] {
        /* Global light-theme tokens: change values only in this block. */
        --ak-bg:#f4f4f5 !important;
        --ak-bg-1:var(--ak-bg) !important;
        --ak-bg-2:#fafafa !important;
        --ak-bg-3:#e5e7eb !important;
        --ak-grid-line:rgba(82,82,91,.032) !important;
        --ak-card:#ffffff !important;
        --ak-card-hover:#fafafa !important;
        --ak-card-alt:#fafafa !important;
        --ak-card-strong:#ffffff !important;
        --ak-surface:#ffffff !important;
        --ak-surface-muted:#f4f4f5 !important;
        --ak-border:#d4d4d8 !important;
        --ak-border-soft:#e4e4e7 !important;
        --ak-border-hover:#a1a1aa !important;
        --ak-border-strong:#71717a !important;
        --ak-text:#0f172a !important;
        --ak-muted:#475569 !important;
        --ak-text-soft:#334155 !important;
        --ak-table-head-text:#27272a !important;
        --ak-card-radius:1rem !important;
        --ak-inner-radius:.7rem !important;
        --ak-accent:#475569 !important;
        --ak-accent-soft:#e5e7eb !important;
        --ak-violet:#475569 !important;
        --ak-violet-soft:#64748b !important;
        --ak-cyan:#475569 !important;
        --ak-warm:#475569 !important;
        --ak-warm-soft:#e5e7eb !important;
        --ak-header-background:linear-gradient(105deg,#fafafa 0%,#f4f4f5 62%,#e5e7eb 100%) !important;
        --ak-group-head-background:linear-gradient(105deg,#ffffff 0%,#fafafa 58%,#eeeef0 100%) !important;
        --ak-index-card-head-background:linear-gradient(105deg,#d4d4d8 0%,#f4f4f5 54%,#ffffff 100%) !important;
        --ak-table-head-background:linear-gradient(105deg,#e4e4e7 0%,#dedee2 58%,#d4d4d8 100%) !important;
        /* Shared Market Overview skin. Market and stock detail pages consume
           these tokens so their chrome stays synchronized. */
        --ak-overview-page:#f1f5f9 !important;
        --ak-overview-card:linear-gradient(105deg,#f1f5f9 0%,#f8fafc 68%,#f1f5f9 100%) !important;
        --ak-overview-card-hover:linear-gradient(105deg,#f1f5f9 0%,#f8fafc 100%) !important;
        --ak-overview-header:linear-gradient(105deg,#e2e8f0 0%,#f1f5f9 62%,#f1f5f9 100%) !important;
        --ak-overview-subcard:linear-gradient(105deg,#f1f5f9 0%,#f8fafc 100%) !important;
        --ak-overview-border:#cbd5e1 !important;
        --ak-overview-border-strong:#94a3b8 !important;
        --ak-overview-accent:#64748b !important;
        --ak-overview-title:#0f172a !important;
        --ak-overview-copy:#475569 !important;
        --ak-overview-muted:#64748b !important;
        --ak-overview-shadow:0 10px 24px rgba(51,65,85,.08),inset 2px 0 0 #cbd5e1 !important;
        --ak-overview-shadow-hover:0 13px 28px rgba(71,85,105,.10),inset 2px 0 0 #94a3b8 !important;
        --ak-focus-ring:0 0 0 3px rgba(82,82,91,.14) !important;
        --ak-shadow:0 8px 24px rgba(24,24,27,.07) !important;
        --ak-shadow-topbar:0 6px 20px rgba(24,24,27,.06) !important;
        --ak-shadow-hover:0 12px 30px rgba(24,24,27,.11) !important;
        --ak-shadow-menu:0 16px 38px rgba(24,24,27,.12) !important;
        --ak-table-head-shadow:0 1px 0 var(--ak-border-hover),0 7px 16px rgba(24,24,27,.09) !important;
        --ak-positive:#047857 !important;
        --ak-positive-alt:#15803d !important;
        --ak-negative:#be123c !important;
        --ak-negative-alt:#b91c1c !important;
        --ak-warning:#a16207 !important;
        --ak-scale-negative:#be123c !important;
        --ak-scale-neutral:#b45309 !important;
        --ak-scale-positive:#15803d !important;
        --ak-scale-opacity:.24 !important;
    }

    :root[data-theme="light"] body:not(.welcome-background),
    :root[data-theme="light"] body:not(.welcome-background) .ak-background,
    :root[data-theme="light"] body:not(.welcome-background) :is(.ak-body,.ak-dashboard-viewport,.ak-page-background) {
        background-color:var(--ak-bg) !important;
        background-image:
            linear-gradient(var(--ak-grid-line) 1px,transparent 1px),
            linear-gradient(90deg,var(--ak-grid-line) 1px,transparent 1px) !important;
        background-size:30px 30px !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        .ak-card,.ak-card-soft,.ak-card-static,.ak-dashboard-card,.ak-dashboard-panel,
        .ak-dashboard-market-card,.ak-detail-hero,.ak-detail-panel,.ak-standard-card,
        .ak-panel,.ak-signal-card,.ak-table-wrap,.model-variant-card
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
        color:var(--ak-text) !important;
        box-shadow:var(--ak-shadow) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        .ak-card,.ak-card-soft,.ak-card-static,.ak-dashboard-card,.ak-dashboard-panel,
        .ak-dashboard-market-card,.ak-detail-hero,.ak-detail-panel,.ak-standard-card,
        .ak-panel,.ak-signal-card,.ak-table-wrap,.model-variant-card
    ):hover {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-card-hover) !important;
        box-shadow:var(--ak-shadow-hover) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        .ak-standard-card-head,.ak-detail-card-head,.ak-market-section-head,.ak-macro-card-head,
        .ak-table-head,.ak-panel-head,.dashboard-modal-header,.screener-table-head
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-header-background) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        .ak-standard-card-head,.ak-detail-card-head,.ak-market-section-head,.ak-macro-card-head,
        .ak-table-head,.ak-panel-head,.dashboard-modal-header,.screener-table-head
    ) :is(p,small) {
        color:var(--ak-muted) !important;
        opacity:1 !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(h1,h2,h3,h4,h5,h6,.ak-card-value,.ak-label) {
        color:var(--ak-text) !important;
        opacity:1 !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        p,small,label,dt,.ak-muted,.ak-card-label,.ak-card-sub,[class*="text-slate-3"],
        [class*="text-slate-4"],[class*="text-slate-5"],[class*="text-slate-6"]
    ) {
        opacity:1 !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        [class*="text-cyan-"],[class*="text-teal-"],[class*="text-violet-"],
        [class*="text-indigo-"],[class*="text-orange-"]
    ) {
        color:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        [class*="border-cyan-"],[class*="border-teal-"],[class*="border-violet-"],
        [class*="border-indigo-"],[class*="border-orange-"]
    ) {
        border-color:var(--ak-border-hover) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        [class*="bg-cyan-"],[class*="bg-teal-"],[class*="bg-violet-"],
        [class*="bg-indigo-"],[class*="bg-orange-"]
    ) {
        background-color:var(--ak-surface-muted) !important;
        background-image:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        input:not([type="checkbox"]):not([type="radio"]),select,textarea,.ak-input
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-surface) !important;
        color:var(--ak-text) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        input:not([type="checkbox"]):not([type="radio"]),select,textarea,.ak-input
    ):focus {
        border-color:var(--ak-border-strong) !important;
        box-shadow:var(--ak-focus-ring) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        table,thead,tbody,tr,th,td,.ak-powergrid,.screener-desktop-table
    ) {
        border-color:var(--ak-border) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(thead,.ak-table-head,.screener-table-head) {
        background:var(--ak-surface-muted) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) tbody tr {
        background-color:var(--ak-card) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) tbody tr:hover {
        background-color:var(--ak-card-hover) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-app-topbar {
        border-color:var(--ak-border) !important;
        background:color-mix(in srgb,var(--ak-card) 96%,transparent) !important;
        box-shadow:var(--ak-shadow-topbar) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(.ak-top-link,.ak-profile-trigger,.ak-nav-scroll-button) {
        border-color:var(--ak-border-soft) !important;
        background:var(--ak-card-hover) !important;
        color:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-top-link-active {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-accent-soft) !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-topbar-menu {
        border-color:var(--ak-border) !important;
        background:#fff !important;
        background-color:#fff !important;
        background-image:none !important;
        backdrop-filter:none !important;
        -webkit-backdrop-filter:none !important;
        box-shadow:var(--ak-shadow-menu) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-topbar-menu > a {
        border:0 !important;
        background:#fff !important;
        background-color:#fff !important;
        background-image:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-topbar-menu > a + a {
        border-top:1px solid var(--ak-border-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .ak-topbar-menu > a:hover {
        background:var(--ak-card-hover) !important;
        background-color:var(--ak-card-hover) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        [data-dashboard-tile],.dashboard-alternative-entry,.dashboard-opportunity-card,
        .aki-model-run-row,.aki-signal-cockpit-row
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
    }

    /* Stock screener: the same neutral workspace, surfaces and hierarchy. */
    :root[data-theme="light"] body:not(.welcome-background) .screener-page {
        background:transparent !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page > header :is(h1,h2) {
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page :is(
        .screener-filter-shell > button,
        .screener-filter-bar,
        .screener-desktop-table
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
        box-shadow:var(--ak-shadow) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-filter-shell > button {
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-filter-bar :is(
        .ak-input,.screener-custom-select-trigger,.screener-risk-choice,input,select
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-surface-muted) !important;
        color:var(--ak-text) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-filter-bar :is(
        .ak-input,.screener-custom-select-trigger,.screener-risk-choice,input,select
    ):hover {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-surface) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-filter-reset {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-surface-muted) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-country-quick-filter {
        border-bottom-color:var(--ak-border) !important;
        background:transparent !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-country-quick-filter-button {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-country-quick-filter-button:is(:hover,.is-active) {
        border-color:var(--ak-border-strong) !important;
        background:var(--ak-accent-soft) !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-table-head {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-table-head-background) !important;
        box-shadow:var(--ak-table-head-shadow) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-table-head :is(span,button) {
        color:var(--ak-table-head-text) !important;
        opacity:1 !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-table-head button i {
        color:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-stock-card {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-stock-card:nth-of-type(even) {
        background:var(--ak-card-alt) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-stock-card:hover {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-card-hover) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-desktop-summary::before {
        background:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page :is(
        .screener-chart-panel,.screener-transparent-panel,.screener-desktop-details,
        .screener-mobile-details > :last-child,.screener-desktop-forecasts > i
    ) {
        border-color:var(--ak-border) !important;
        background:var(--ak-surface-muted) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-mobile-summary-v2 {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page .is-trigger-horizon {
        border-top-color:var(--ak-muted) !important;
    }

    /* Aggregate screeners: one shared card language for indices, sectors and commodities. */
    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) {
        background:transparent !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) > header > span {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        color:var(--ak-muted) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-region-head {
        position:static !important;
        border:1px solid var(--ak-border-hover) !important;
        border-radius:var(--ak-inner-radius) !important;
        background:var(--ak-group-head-background) !important;
        color:var(--ak-table-head-text) !important;
        box-shadow:var(--ak-table-head-shadow) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-region-head span {
        border-color:var(--ak-border-hover) !important;
        background:var(--ak-card) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        #aggregate-screener .index-tile,
        #aggregate-screener .index-screener-card,
        .commodity-screener .index-screener-card
    ) {
        border:1.5px solid var(--ak-border-hover) !important;
        border-radius:var(--ak-card-radius) !important;
        background:var(--ak-card) !important;
        background-image:none !important;
        color:var(--ak-text) !important;
        box-shadow:var(--ak-shadow) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        #aggregate-screener .index-tile,
        #aggregate-screener .index-screener-card,
        .commodity-screener .index-screener-card
    ):hover {
        border-color:var(--ak-border-strong) !important;
        background:var(--ak-card-hover) !important;
        box-shadow:var(--ak-shadow-hover) !important;
        transform:translateY(-1px);
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-tile::before {
        display:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-tile-head {
        margin:0 !important;
        border:0 !important;
        border-bottom:1px solid var(--ak-border-hover) !important;
        background:var(--ak-index-card-head-background) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .sms-v2-head {
        margin:0 !important;
        border:0 !important;
        background:transparent !important;
        box-shadow:none !important;
    }

    /* Index and sector cards share one header treatment. */
    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-tile-head {
        margin:-.85rem -.9rem 0 !important;
        padding:.8rem .9rem !important;
        border:0 !important;
        border-bottom:1px solid var(--ak-border-hover) !important;
        background:var(--ak-index-card-head-background) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(
        #aggregate-screener.sector-screener,
        .commodity-screener
    ) .index-screener-card > .screener-desktop-summary {
        margin:-.75rem -.75rem 0 !important;
        width:calc(100% + 1.5rem) !important;
        padding:.72rem .85rem !important;
        border-bottom:1px solid var(--ak-border) !important;
        background:var(--ak-header-background) !important;
    }

    @media (max-width:767px) {
        :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener:not(.sector-screener) .index-screener-card .sms-v2-head {
            margin:-.45rem -.45rem .35rem !important;
            padding:.7rem .65rem !important;
            border:0 !important;
            border-bottom:1px solid var(--ak-border-hover) !important;
            background:var(--ak-index-card-head-background) !important;
            box-shadow:none !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) :is(
            #aggregate-screener.sector-screener,
            .commodity-screener
        ) .index-screener-card .sms-v2-head {
            border-bottom:1px solid var(--ak-border) !important;
            background:var(--ak-header-background) !important;
        }
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-tile-flag {
        border:0 !important;
        background:transparent !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-tile-metrics,.index-card-details>section,.commodity-chart
    ) {
        border-color:var(--ak-border) !important;
        border-radius:var(--ak-inner-radius) !important;
        background:var(--ak-surface-muted) !important;
        background-image:none !important;
        color:var(--ak-text) !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-card-details,.commodity-details
    ) {
        border-top-color:var(--ak-border) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-tile-name,.sms-v2-head strong,.index-card-details h3,.index-card-chart header,
        .commodity-chart header b
    ) {
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-tile-sym,.index-tile-metrics small,.index-tile-forecasts small,
        .index-tile-forecast-label,.sms-v2-head small,.commodity-chart header small,
        .commodity-chart header em
    ) {
        color:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #aggregate-screener .index-tile-chev {
        border-color:var(--ak-border) !important;
        background:var(--ak-surface-muted) !important;
        color:var(--ak-muted) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-tile-metrics>i+ i,.index-tile-forecasts>i+ i,.index-card-copy dl div,
        .index-card-members>a:not(.index-card-all)
    ) {
        border-color:var(--ak-border) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) :is(#aggregate-screener,.commodity-screener) :is(
        .index-card-all,.index-card-details h3,.index-card-chart header,.commodity-chart header b
    ) {
        color:var(--ak-accent) !important;
    }

    /* Screener scales: restrained in light mode, without neon glow. */
    :root[data-theme="light"] body:not(.welcome-background) .screener-page :is(
        .screener-desktop-scale,.sms-v2-scale
    )::before {
        background:linear-gradient(90deg,var(--ak-scale-negative),var(--ak-scale-neutral) 48%,var(--ak-scale-positive)) !important;
        opacity:var(--ak-scale-opacity) !important;
        filter:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page :is(
        .screener-desktop-scale,.sms-v2-scale
    ) > em {
        border-color:color-mix(in srgb,var(--marker) 58%,var(--ak-border-strong)) !important;
        background:color-mix(in srgb,var(--marker) 58%,var(--ak-text-soft)) !important;
        box-shadow:none !important;
        filter:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) .screener-page :is(
        .screener-desktop-scale,.sms-v2-scale
    ) > .screener-dynamic-risk-marker,
    :root[data-theme="light"] body:not(.welcome-background) .screener-page .screener-dynamic-risk-marker::after {
        background:color-mix(in srgb,var(--dynamic-risk-color) 58%,var(--ak-text-soft)) !important;
        box-shadow:none !important;
        filter:none !important;
    }

    /* Semantic finance colours are deliberately preserved. */
    :root[data-theme="light"] body:not(.welcome-background) [class*="text-emerald-"] { color:var(--ak-positive) !important; }
    :root[data-theme="light"] body:not(.welcome-background) [class*="text-green-"] { color:var(--ak-positive-alt) !important; }
    :root[data-theme="light"] body:not(.welcome-background) [class*="text-rose-"] { color:var(--ak-negative) !important; }
    :root[data-theme="light"] body:not(.welcome-background) [class*="text-red-"] { color:var(--ak-negative-alt) !important; }
    :root[data-theme="light"] body:not(.welcome-background) [class*="text-amber-"] { color:var(--ak-warning) !important; }

    /* Dashboard schedule modal: quiet slate surfaces and readable actions. */
    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal > .dashboard-modal-panel {
        border-color:var(--ak-border-strong) !important;
        border-width:1.5px !important;
        border-radius:var(--ak-card-radius) !important;
        background:var(--ak-card) !important;
        color:var(--ak-text) !important;
        box-shadow:0 24px 60px rgba(15,23,42,.22) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal > .dashboard-modal-panel > .dashboard-modal-header {
        border-color:var(--ak-border) !important;
        background:linear-gradient(105deg,#f8fafc 0%,#eef1f4 55%,#e5e7eb 100%) !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .dashboard-modal-header :is(h2,svg) {
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .dashboard-modal-header > div > span {
        border-color:var(--ak-border-strong) !important;
        background:var(--ak-surface-muted) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .dashboard-modal-header button {
        border-color:var(--ak-border) !important;
        background:var(--ak-card) !important;
        color:var(--ak-text-soft) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal :is(.message-reminder-row,article) {
        border-color:var(--ak-border) !important;
        background:var(--ak-surface-muted) !important;
        color:var(--ak-text) !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-actions button[class*="border-rose"],
    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal article button[class*="border-rose"] {
        border-color:#e11d48 !important;
        background:#fff1f2 !important;
        color:#9f1239 !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-actions button[class*="border-emerald"] {
        border-color:#059669 !important;
        background:#ecfdf5 !important;
        color:#047857 !important;
        box-shadow:none !important;
    }

    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-actions button :is(svg,path),
    :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal article button[class*="border-rose"] :is(svg,path) {
        color:inherit !important;
        stroke:currentColor !important;
        opacity:1 !important;
    }

    @media (min-width:640px) {
        :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-row {
            grid-template-columns:minmax(0,1fr) auto !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-actions {
            display:flex !important;
            width:auto !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) #message-settings-modal .message-reminder-actions :is(form,button) {
            width:auto !important;
        }
    }
}
</style>
