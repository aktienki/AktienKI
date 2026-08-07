<style>
    .ak-detail-design {
        --detail-accent: #14b8a6;
        --detail-accent-bright: #22d3ee;
    }

    .ak-detail-design .ak-detail-hero,
    .ak-detail-design .ak-detail-panel,
    .ak-detail-design .ak-dashboard-card {
        position: relative;
        border-color: color-mix(in srgb, var(--ak-border) 76%, var(--detail-accent) 24%) !important;
        background:
            radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .12), transparent 28%),
            linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(244, 250, 249, .96)) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .9),
            0 0 0 1px rgba(15, 118, 110, .055),
            0 14px 34px rgba(15, 23, 42, .105),
            0 4px 12px rgba(15, 118, 110, .07) !important;
    }

    :root:not([data-theme="light"]) .ak-detail-design .ak-detail-hero,
    :root:not([data-theme="light"]) .ak-detail-design .ak-detail-panel,
    :root:not([data-theme="light"]) .ak-detail-design .ak-dashboard-card {
        background:
            radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .11), transparent 30%),
            linear-gradient(145deg, rgba(22, 35, 45, .98), rgba(13, 25, 34, .98)) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .045),
            0 0 0 1px rgba(34, 211, 238, .065),
            0 18px 42px rgba(0, 0, 0, .34),
            0 5px 16px rgba(6, 182, 212, .055) !important;
    }

    .ak-detail-design .ak-detail-panel::before,
    .ak-detail-design .ak-dashboard-card::before {
        content: '';
        position: absolute;
        z-index: 3;
        inset: 0 18% auto;
        height: 2px;
        border-radius: 0 0 999px 999px;
        background: linear-gradient(90deg, transparent, rgba(13, 148, 136, .62), rgba(34, 211, 238, .78), transparent);
        pointer-events: none;
    }

    .ak-detail-design .ak-detail-card-head {
        border-bottom-color: rgba(13, 148, 136, .28) !important;
        background:
            radial-gradient(circle at 5% 0%, rgba(34, 211, 238, .19), transparent 40%),
            linear-gradient(108deg, rgba(13, 148, 136, .20), rgba(20, 184, 166, .11) 44%, rgba(6, 182, 212, .06) 75%, transparent) !important;
        box-shadow: 0 6px 16px rgba(15, 118, 110, .055);
    }

    .ak-detail-design :is(.text-violet-200, .text-violet-300) { color: #2dd4bf !important; }
    .ak-detail-design :is(.bg-violet-500\/10, .bg-violet-500\/15) { background-color: color-mix(in srgb, var(--detail-accent) 11%, transparent) !important; }
    .ak-detail-design :is(.border-violet-400\/20, .border-violet-400\/25, .border-violet-400\/30) { border-color: color-mix(in srgb, var(--detail-accent) 32%, transparent) !important; }
    .ak-detail-design .hover\:bg-violet-500\/\[\.075\]:hover { background-color: color-mix(in srgb, var(--detail-accent) 7.5%, transparent) !important; }

    :root[data-theme="light"] .ak-detail-design .ak-detail-card-head :is(.text-amber-200, .text-amber-300) { color: #a16207 !important; }
    :root[data-theme="light"] .ak-detail-design .ak-detail-card-head :is(.text-teal-200, .text-teal-300) { color: #0f766e !important; }

    .ak-detail-design .ak-standard-card-head {
        position: relative;
        margin: -1rem -1rem .8rem;
        padding: .9rem 1rem .8rem;
        border-bottom: 1px solid rgba(13, 148, 136, .24);
        background:
            radial-gradient(circle at 6% 0%, rgba(34, 211, 238, .18), transparent 38%),
            linear-gradient(108deg, rgba(13, 148, 136, .18), rgba(20, 184, 166, .10) 46%, transparent 82%);
    }

    :root:not([data-theme="light"]) body:not(.welcome-background) .ak-detail-design .ak-standard-card {
        border-color: color-mix(in srgb, var(--ak-border) 72%, #14b8a6 28%) !important;
        background:
            radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .10), transparent 30%),
            linear-gradient(145deg, rgba(22, 35, 45, .98), rgba(13, 25, 34, .98)) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.045), 0 18px 42px rgba(0,0,0,.30) !important;
    }

    :root[data-theme="light"] .ak-detail-design .ak-standard-card {
        border-color: color-mix(in srgb, var(--ak-border) 72%, #14b8a6 28%) !important;
        background:
            radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .10), transparent 28%),
            linear-gradient(145deg, rgba(255,255,255,.99), rgba(244,250,249,.97)) !important;
        box-shadow: inset 0 1px 0 #fff, 0 14px 34px rgba(15,23,42,.10) !important;
    }

    .ak-detail-design .ak-standard-card :is(.text-orange-400, .text-orange-300) { color: #14b8a6 !important; }
</style>
