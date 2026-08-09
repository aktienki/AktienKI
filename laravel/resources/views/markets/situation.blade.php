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
            border-color: rgba(103, 232, 249, .12) !important;
        }
        .ak-market-situation-page.ak-detail-design .ak-market-tape {
            overflow: hidden;
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
        @media (min-width: 1024px) {
            .ak-market-situation-page .ak-market-command .ak-market-primary-grid > div > .ak-dashboard-card {
                min-height: 360px !important;
                height: 360px !important;
            }
        }
    </style>
    <div class="ak-market-situation-page ak-detail-design min-h-[calc(100dvh-73px)] overflow-visible pb-28 lg:pb-0">
        <livewire:dashboard.market-data />
    </div>

    <x-dashboard.bottom-bar />
</x-app-layout>
