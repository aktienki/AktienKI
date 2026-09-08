<x-app-layout>
    @php
        $signals = collect($signalCockpit['signalChanges'] ?? [])->values();
        $focusIndex = $signals->search(
            fn ($signal): bool => strtoupper((string) data_get($signal, 'to', '')) === 'BUY'
        );
        $focusIndex = $focusIndex === false ? ($signals->isNotEmpty() ? 0 : null) : $focusIndex;
        $focusSignal = $focusIndex !== null ? $signals->get($focusIndex) : null;
        $signalFlow = $signals
            ->reject(fn ($signal, $index): bool => $focusIndex !== null && $index === $focusIndex)
            ->take(4)
            ->values();
        $focusStatus = strtoupper((string) data_get($focusSignal, 'to', 'HOLD'));
        $focusHorizons = collect(data_get($focusSignal, 'horizons', []));
        $focusScore = is_numeric(data_get($focusSignal, 'score')) ? (float) data_get($focusSignal, 'score') : null;
        $focusRisk = is_numeric(data_get($focusSignal, 'risk')) ? (float) data_get($focusSignal, 'risk') : null;

        $activities = collect($dashboardActivities ?? [])->take(5)->values();
        $schedule = collect($dashboardScheduleItems ?? [])->take(4)->values();
        $positions = collect($strategyPortfolio?->positions ?? [])->values();
        $portfolioValue = (float) ($strategyPortfolio?->dashboard_total_value ?? 0);
        $positionsValue = (float) ($strategyPortfolio?->dashboard_positions_value ?? 0);
        $availableCash = (float) ($strategyPortfolio?->dashboard_cash ?? max(0, $portfolioValue - $positionsValue));
        $portfolioPerformance = (float) ($strategyPortfolio?->dashboard_performance ?? 0);
        $capitalBinding = $portfolioValue > 0 ? min(100, max(0, ($positionsValue / $portfolioValue) * 100)) : 0;
        $currency = trim((string) ($strategyPortfolio?->currency ?? 'EUR')) ?: 'EUR';

        $buyCount = (int) ($recentSignalOverview['buy_count'] ?? $signals->filter(fn ($signal) => strtoupper((string) data_get($signal, 'to')) === 'BUY')->count());
        $sellCount = (int) ($recentSignalOverview['sell_count'] ?? $signals->filter(fn ($signal) => strtoupper((string) data_get($signal, 'to')) === 'SELL')->count());
        $activeCount = (int) ($profileUniverseStats['active_count'] ?? 0);
        $marketOutlook = trim((string) ($marketSituation?->market_outlook ?: __('Neutral')));
        $marketHeadline = trim((string) ($marketSituation?->headline ?: __('Selektiv bleiben und bestätigte Signale priorisieren.')));
        $marketSummary = trim((string) ($marketSituation?->executive_summary ?: __('Das Marktumfeld bleibt Kontext. Die eigentliche Entscheidung entsteht aus Modell, Aktienfilter und finalem Signal.')));
        $marketConfidence = is_numeric($marketSituation?->confidence) ? (float) $marketSituation->confidence : null;
        $marketRisk = trim((string) ($marketSituation?->risk_level ?: __('Mittel')));
        $firstName = trim(explode(' ', (string) auth()->user()->name)[0] ?? '');

        $relativeTime = static function ($value): string {
            if (! $value) {
                return '—';
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->diffForHumans();
            } catch (\Throwable) {
                return '—';
            }
        };
        $shortDate = static function ($value, string $format = 'd.m.Y'): string {
            if (! $value) {
                return '—';
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->translatedFormat($format);
            } catch (\Throwable) {
                return '—';
            }
        };
        $signalClass = static fn (string $signal): string => match (strtoupper($signal)) {
            'BUY' => 'is-buy',
            'SELL' => 'is-sell',
            'WAIT', 'WATCH' => 'is-wait',
            default => 'is-hold',
        };
        $barWidth = static fn ($value): float => is_numeric($value)
            ? min(100, max(12, abs((float) $value) * 6))
            : 0;

        $focusSteps = match ($focusStatus) {
            'BUY' => [
                [__('Rohmodell'), 'BUY', 'done'],
                [__('Aktienfilter'), __('bestanden'), 'done'],
                [__('Finales Signal'), 'BUY', 'final-buy'],
            ],
            'SELL' => [
                [__('Position'), __('aktiv'), 'done'],
                [__('Exit-Regel'), __('ausgelöst'), 'done'],
                [__('Finales Signal'), 'SELL', 'final-sell'],
            ],
            default => [
                [__('Rohmodell'), strtoupper((string) data_get($focusSignal, 'from', 'HOLD')), 'done'],
                [__('Prüfung'), __('offen'), 'pending'],
                [__('Finales Signal'), $focusStatus, 'pending'],
            ],
        };
    @endphp

    <style>
        #aurora-dashboard {
            --av-bg: #eef3f9;
            --av-surface: #ffffff;
            --av-surface-soft: #f7f9fc;
            --av-ink: #10172a;
            --av-copy: #5d687b;
            --av-muted: #8a94a6;
            --av-line: #dfe5ee;
            --av-indigo: #414bd8;
            --av-indigo-dark: #29349f;
            --av-indigo-soft: #eef0ff;
            --av-mint: #18a678;
            --av-mint-soft: #e8f8f1;
            --av-coral: #e45764;
            --av-coral-soft: #fff0f2;
            --av-amber: #bf8018;
            --av-amber-soft: #fff6e5;
            min-height: calc(100dvh - 73px);
            color: var(--av-ink);
            background:
                radial-gradient(circle at 4% 12%, rgba(65, 75, 216, .08), transparent 24rem),
                radial-gradient(circle at 96% 32%, rgba(24, 166, 120, .08), transparent 26rem),
                var(--av-bg);
            font-variant-numeric: tabular-nums;
        }
        #aurora-dashboard * { box-sizing: border-box; }
        #aurora-dashboard .av-shell { width: min(1480px, 100%); margin: 0 auto; padding: 1.5rem clamp(1rem, 2.5vw, 2.5rem) 3rem; }
        #aurora-dashboard .av-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
        #aurora-dashboard .av-today { display: flex; align-items: center; gap: .75rem; }
        #aurora-dashboard .av-today-mark { display: grid; width: 2.8rem; height: 2.8rem; place-items: center; border-radius: .9rem; color: #fff; background: var(--av-indigo); box-shadow: 0 8px 18px rgba(65, 75, 216, .24); }
        #aurora-dashboard .av-today small { display: block; color: var(--av-muted); font-size: .68rem; font-weight: 750; }
        #aurora-dashboard .av-today strong { display: block; margin-top: .12rem; font-size: .9rem; }
        #aurora-dashboard .av-market-pulse { display: inline-flex; align-items: center; gap: .5rem; border: 1px solid var(--av-line); border-radius: 999px; padding: .45rem .72rem; color: var(--av-copy); background: rgba(255, 255, 255, .7); font-size: .7rem; font-weight: 750; }
        #aurora-dashboard .av-market-pulse i { width: .48rem; height: .48rem; border-radius: 999px; background: var(--av-mint); box-shadow: 0 0 0 .28rem rgba(24, 166, 120, .11); }
        #aurora-dashboard .av-top-actions { display: flex; align-items: center; gap: .55rem; }
        #aurora-dashboard .av-quiet-link { display: inline-flex; min-height: 2.35rem; align-items: center; gap: .38rem; border: 1px solid var(--av-line); border-radius: .72rem; padding: .5rem .7rem; color: var(--av-copy); background: rgba(255, 255, 255, .74); font-size: .68rem; font-weight: 800; transition: .18s ease; }
        #aurora-dashboard .av-quiet-link:hover { color: var(--av-indigo); border-color: #b8c0ef; transform: translateY(-1px); }
        #aurora-dashboard .av-preview { color: var(--av-amber); background: var(--av-amber-soft); border-color: #edd5a7; }

        #aurora-dashboard .av-hero { position: relative; overflow: hidden; border-radius: 1.8rem; color: #fff; background: linear-gradient(125deg, #17214b 0%, #313cab 58%, #3f53df 100%); box-shadow: 0 24px 58px rgba(31, 43, 112, .2); }
        #aurora-dashboard .av-hero::before { content: ''; position: absolute; width: 24rem; height: 24rem; right: -7rem; top: -11rem; border-radius: 999px; border: 1px solid rgba(255, 255, 255, .14); box-shadow: 0 0 0 4rem rgba(255, 255, 255, .025), 0 0 0 8rem rgba(255, 255, 255, .018); }
        #aurora-dashboard .av-hero-grid { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(22rem, .8fr); gap: 1.25rem; padding: clamp(1.35rem, 3vw, 2.6rem); }
        #aurora-dashboard .av-hero-copy { display: flex; min-height: 20rem; flex-direction: column; justify-content: space-between; }
        #aurora-dashboard .av-kicker { color: #c7ceff; font-size: .7rem; font-weight: 850; letter-spacing: .13em; text-transform: uppercase; }
        #aurora-dashboard .av-hero h1 { max-width: 45rem; margin-top: .75rem; font-size: clamp(2rem, 4.5vw, 4rem); line-height: .98; font-weight: 760; letter-spacing: -.055em; }
        #aurora-dashboard .av-hero h1 em { color: #6ef0c2; font-style: normal; }
        #aurora-dashboard .av-hero-summary { max-width: 38rem; margin-top: 1rem; color: #d8ddff; font-size: .9rem; line-height: 1.6; }
        #aurora-dashboard .av-hero-summary strong { color: #fff; }
        #aurora-dashboard .av-hero-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.3rem; }
        #aurora-dashboard .av-primary { display: inline-flex; min-height: 2.85rem; align-items: center; justify-content: center; gap: .45rem; border-radius: .82rem; padding: .72rem 1rem; color: #14205c; background: #fff; font-size: .76rem; font-weight: 900; box-shadow: 0 10px 24px rgba(8, 15, 53, .2); transition: .18s ease; }
        #aurora-dashboard .av-primary:hover { transform: translateY(-2px); }
        #aurora-dashboard .av-secondary { display: inline-flex; min-height: 2.85rem; align-items: center; gap: .4rem; border: 1px solid rgba(255, 255, 255, .2); border-radius: .82rem; padding: .72rem .9rem; color: #edf0ff; background: rgba(255, 255, 255, .07); font-size: .74rem; font-weight: 800; }
        #aurora-dashboard .av-hero-meta { display: flex; flex-wrap: wrap; gap: 1.25rem; margin-top: 1.3rem; color: #aeb8ed; font-size: .72rem; }
        #aurora-dashboard .av-hero-meta span { display: inline-flex; align-items: center; gap: .35rem; }

        #aurora-dashboard .av-lens { align-self: stretch; border: 1px solid rgba(255, 255, 255, .2); border-radius: 1.35rem; padding: 1.2rem; background: rgba(255, 255, 255, .1); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12); backdrop-filter: blur(12px); }
        #aurora-dashboard .av-lens-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
        #aurora-dashboard .av-lens-symbol { display: flex; min-width: 0; align-items: center; gap: .72rem; }
        #aurora-dashboard .av-lens-logo { display: grid; width: 3.2rem; height: 3.2rem; flex: 0 0 auto; place-items: center; border-radius: 1rem; color: #253091; background: #fff; font-size: .78rem; font-weight: 950; }
        #aurora-dashboard .av-lens-symbol b { display: block; overflow: hidden; font-size: 1.05rem; text-overflow: ellipsis; white-space: nowrap; }
        #aurora-dashboard .av-lens-symbol small { display: block; margin-top: .18rem; overflow: hidden; color: #bdc6ef; font-size: .68rem; text-overflow: ellipsis; white-space: nowrap; }
        #aurora-dashboard .av-badge { display: inline-flex; align-items: center; gap: .32rem; border-radius: 999px; padding: .42rem .65rem; font-size: .65rem; font-weight: 950; letter-spacing: .045em; }
        #aurora-dashboard .av-badge::before { content: ''; width: .4rem; height: .4rem; border-radius: 999px; background: currentColor; }
        #aurora-dashboard .av-badge.is-buy { color: #103e30; background: #6ef0c2; }
        #aurora-dashboard .av-badge.is-sell { color: #58141b; background: #ff99a1; }
        #aurora-dashboard .av-badge.is-wait,
        #aurora-dashboard .av-badge.is-hold { color: #473112; background: #ffd98f; }
        #aurora-dashboard .av-forecast { display: grid; gap: .7rem; margin-top: 1.4rem; }
        #aurora-dashboard .av-forecast-row { display: grid; grid-template-columns: 2.4rem minmax(0, 1fr) 3.8rem; align-items: center; gap: .65rem; color: #c7ceef; font-size: .68rem; font-weight: 750; }
        #aurora-dashboard .av-forecast-track { height: .48rem; overflow: hidden; border-radius: 999px; background: rgba(255, 255, 255, .13); }
        #aurora-dashboard .av-forecast-track i { display: block; height: 100%; min-width: .35rem; border-radius: inherit; background: #6ef0c2; }
        #aurora-dashboard .av-forecast-track i.is-negative { background: #ff909a; }
        #aurora-dashboard .av-forecast-row strong { color: #fff; text-align: right; }
        #aurora-dashboard .av-lens-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin-top: 1.2rem; }
        #aurora-dashboard .av-lens-stat { border: 1px solid rgba(255, 255, 255, .13); border-radius: .78rem; padding: .68rem; background: rgba(7, 13, 52, .12); }
        #aurora-dashboard .av-lens-stat small { display: block; color: #aeb8e7; font-size: .68rem; }
        #aurora-dashboard .av-lens-stat strong { display: block; margin-top: .2rem; font-size: .9rem; }
        #aurora-dashboard .av-path { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top: 1px solid rgba(255, 255, 255, .13); background: rgba(7, 13, 49, .22); }
        #aurora-dashboard .av-path-step { position: relative; padding: .9rem clamp(.8rem, 2vw, 1.5rem); border-right: 1px solid rgba(255, 255, 255, .1); }
        #aurora-dashboard .av-path-step:last-child { border-right: 0; }
        #aurora-dashboard .av-path-step small { color: #9fa9da; font-size: .65rem; font-weight: 750; text-transform: uppercase; }
        #aurora-dashboard .av-path-step strong { display: flex; align-items: center; gap: .42rem; margin-top: .24rem; font-size: .8rem; }
        #aurora-dashboard .av-step-check { display: grid; width: 1.1rem; height: 1.1rem; place-items: center; border-radius: 999px; color: #193729; background: #6ef0c2; }
        #aurora-dashboard .av-path-step.final-sell .av-step-check { color: #5d161d; background: #ff99a1; }

        #aurora-dashboard .av-section { margin-top: 1.4rem; }
        #aurora-dashboard .av-section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; }
        #aurora-dashboard .av-section-head small { color: var(--av-indigo); font-size: .65rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
        #aurora-dashboard .av-section-head h2 { margin-top: .2rem; font-size: 1.4rem; font-weight: 800; letter-spacing: -.025em; }
        #aurora-dashboard .av-section-head p { color: var(--av-copy); font-size: .72rem; }
        #aurora-dashboard .av-link { display: inline-flex; align-items: center; gap: .28rem; color: var(--av-indigo); font-size: .7rem; font-weight: 850; }
        #aurora-dashboard .av-flow { display: grid; gap: .68rem; }
        #aurora-dashboard .av-flow-card { position: relative; display: grid; grid-template-columns: minmax(13rem, 1.25fr) minmax(12rem, 1fr) minmax(10rem, .9fr) auto; align-items: center; gap: 1rem; overflow: hidden; border: 1px solid var(--av-line); border-radius: 1.15rem; padding: .9rem 1rem .9rem 1.25rem; background: var(--av-surface); box-shadow: 0 10px 26px rgba(37, 51, 88, .055); transition: .18s ease; }
        #aurora-dashboard .av-flow-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: .32rem; background: #9ba5b8; }
        #aurora-dashboard .av-flow-card.is-buy::before { background: var(--av-mint); }
        #aurora-dashboard .av-flow-card.is-sell::before { background: var(--av-coral); }
        #aurora-dashboard .av-flow-card.is-wait::before { background: var(--av-amber); }
        #aurora-dashboard .av-flow-card:hover { border-color: #c5ccef; transform: translateY(-2px); box-shadow: 0 14px 32px rgba(37, 51, 88, .09); }
        #aurora-dashboard .av-flow-company { display: flex; min-width: 0; align-items: center; gap: .72rem; }
        #aurora-dashboard .av-flow-logo { display: grid; width: 2.65rem; height: 2.65rem; flex: 0 0 auto; place-items: center; border-radius: .8rem; color: var(--av-indigo); background: var(--av-indigo-soft); font-size: .66rem; font-weight: 950; }
        #aurora-dashboard .av-flow-company b { display: block; overflow: hidden; font-size: .8rem; text-overflow: ellipsis; white-space: nowrap; }
        #aurora-dashboard .av-flow-company small { display: block; margin-top: .18rem; color: var(--av-muted); font-size: .68rem; }
        #aurora-dashboard .av-change { color: var(--av-copy); font-size: .7rem; }
        #aurora-dashboard .av-change strong { display: block; margin-bottom: .22rem; color: var(--av-ink); font-size: .76rem; }
        #aurora-dashboard .av-mini-returns { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
        #aurora-dashboard .av-mini-return { border-radius: .62rem; padding: .46rem; background: var(--av-surface-soft); text-align: center; }
        #aurora-dashboard .av-mini-return small { display: block; color: var(--av-muted); font-size: .56rem; }
        #aurora-dashboard .av-mini-return b { display: block; margin-top: .12rem; color: var(--av-mint); font-size: .68rem; }
        #aurora-dashboard .av-mini-return b.is-negative { color: var(--av-coral); }
        #aurora-dashboard .av-flow-status { justify-self: end; color: var(--av-copy); font-size: .67rem; text-align: right; }
        #aurora-dashboard .av-flow-status .av-badge { margin-bottom: .3rem; }
        #aurora-dashboard .av-flow-status .av-badge.is-buy { color: var(--av-mint); background: var(--av-mint-soft); }
        #aurora-dashboard .av-flow-status .av-badge.is-sell { color: var(--av-coral); background: var(--av-coral-soft); }
        #aurora-dashboard .av-flow-status .av-badge.is-wait,
        #aurora-dashboard .av-flow-status .av-badge.is-hold { color: var(--av-amber); background: var(--av-amber-soft); }
        #aurora-dashboard .av-flow-empty { border: 1px dashed var(--av-line); border-radius: 1.15rem; padding: 2rem; color: var(--av-copy); background: rgba(255, 255, 255, .5); text-align: center; }

        #aurora-dashboard .av-capital { display: grid; grid-template-columns: minmax(15rem, .75fr) minmax(20rem, 1fr) minmax(0, 1.25fr); align-items: center; gap: 1.5rem; overflow: hidden; border: 1px solid var(--av-line); border-radius: 1.35rem; padding: 1.2rem; background: var(--av-surface); box-shadow: 0 12px 30px rgba(37, 51, 88, .06); }
        #aurora-dashboard .av-capital-value small { color: var(--av-muted); font-size: .68rem; font-weight: 800; text-transform: uppercase; }
        #aurora-dashboard .av-capital-value strong { display: block; margin-top: .25rem; font-size: 2rem; line-height: 1; font-weight: 850; letter-spacing: -.045em; }
        #aurora-dashboard .av-capital-performance { display: inline-flex; margin-top: .45rem; align-items: center; gap: .3rem; color: var(--av-mint); font-size: .72rem; font-weight: 900; }
        #aurora-dashboard .av-capital-performance.is-negative { color: var(--av-coral); }
        #aurora-dashboard .av-capital-bar { height: .85rem; overflow: hidden; border-radius: 999px; background: #e9edf4; }
        #aurora-dashboard .av-capital-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--av-indigo), #7280ef); }
        #aurora-dashboard .av-capital-labels { display: flex; justify-content: space-between; gap: .75rem; margin-top: .48rem; color: var(--av-copy); font-size: .68rem; }
        #aurora-dashboard .av-capital-labels b { color: var(--av-ink); }
        #aurora-dashboard .av-position-pills { display: flex; gap: .55rem; overflow-x: auto; padding: .1rem 0 .25rem; scroll-snap-type: x mandatory; }
        #aurora-dashboard .av-position-pill { min-width: 8.5rem; flex: 1 0 8.5rem; scroll-snap-align: start; border: 1px solid var(--av-line); border-radius: .85rem; padding: .65rem; background: var(--av-surface-soft); }
        #aurora-dashboard .av-position-pill span { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
        #aurora-dashboard .av-position-pill b { font-size: .72rem; }
        #aurora-dashboard .av-position-pill em { color: var(--av-mint); font-size: .62rem; font-style: normal; font-weight: 900; }
        #aurora-dashboard .av-position-pill em.is-negative { color: var(--av-coral); }
        #aurora-dashboard .av-position-pill small { display: block; margin-top: .28rem; color: var(--av-muted); font-size: .64rem; }

        #aurora-dashboard .av-timeline-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(20rem, .8fr); gap: 1rem; }
        #aurora-dashboard .av-panel { min-width: 0; border: 1px solid var(--av-line); border-radius: 1.25rem; background: var(--av-surface); box-shadow: 0 10px 26px rgba(37, 51, 88, .05); }
        #aurora-dashboard .av-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; border-bottom: 1px solid var(--av-line); }
        #aurora-dashboard .av-panel-head h3 { font-size: .92rem; font-weight: 850; }
        #aurora-dashboard .av-panel-head span { color: var(--av-muted); font-size: .68rem; }
        #aurora-dashboard .av-event-list { position: relative; padding: .55rem 1.1rem .8rem; }
        #aurora-dashboard .av-event-list::before { content: ''; position: absolute; left: 1.79rem; top: 1.2rem; bottom: 1.25rem; width: 1px; background: var(--av-line); }
        #aurora-dashboard .av-event { position: relative; display: grid; grid-template-columns: 1.45rem minmax(0, 1fr) auto; align-items: start; gap: .7rem; padding: .65rem 0; }
        #aurora-dashboard .av-event-dot { z-index: 1; display: grid; width: 1.45rem; height: 1.45rem; place-items: center; border: 3px solid #fff; border-radius: 999px; color: var(--av-indigo); background: var(--av-indigo-soft); }
        #aurora-dashboard .av-event-copy b { display: block; font-size: .72rem; }
        #aurora-dashboard .av-event-copy p { margin-top: .16rem; color: var(--av-copy); font-size: .68rem; line-height: 1.45; }
        #aurora-dashboard .av-event time { color: var(--av-muted); font-size: .62rem; white-space: nowrap; }
        #aurora-dashboard .av-agenda { display: grid; gap: .5rem; padding: .75rem 1rem 1rem; }
        #aurora-dashboard .av-agenda-item { display: grid; grid-template-columns: 3rem minmax(0, 1fr); align-items: center; gap: .7rem; border-radius: .85rem; padding: .62rem; background: var(--av-surface-soft); }
        #aurora-dashboard .av-agenda-date { display: grid; min-height: 2.8rem; place-items: center; align-content: center; border-radius: .68rem; color: var(--av-indigo); background: var(--av-indigo-soft); line-height: 1; }
        #aurora-dashboard .av-agenda-date b { font-size: .8rem; }
        #aurora-dashboard .av-agenda-date small { margin-top: .15rem; font-size: .5rem; font-weight: 900; text-transform: uppercase; }
        #aurora-dashboard .av-agenda-copy b { display: block; overflow: hidden; font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }
        #aurora-dashboard .av-agenda-copy span { display: block; margin-top: .18rem; color: var(--av-copy); font-size: .66rem; }

        #aurora-dashboard .av-market-footer { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 1.5rem; margin-top: 1.4rem; border-top: 1px solid #d7dfeb; padding: 1.1rem .2rem 0; }
        #aurora-dashboard .av-market-footer h3 { font-size: .78rem; font-weight: 850; }
        #aurora-dashboard .av-market-footer p { margin-top: .25rem; max-width: 58rem; color: var(--av-copy); font-size: .68rem; line-height: 1.5; }
        #aurora-dashboard .av-market-facts { display: flex; gap: 1.35rem; }
        #aurora-dashboard .av-market-fact small { display: block; color: var(--av-muted); font-size: .62rem; font-weight: 800; text-transform: uppercase; }
        #aurora-dashboard .av-market-fact b { display: block; margin-top: .15rem; font-size: .68rem; }
        #aurora-dashboard .av-mobile-dock { display: none; }

        @media (prefers-reduced-motion: reduce) {
            #aurora-dashboard * { scroll-behavior: auto !important; transition: none !important; }
        }
        @media (max-width: 1080px) {
            #aurora-dashboard .av-hero-grid { grid-template-columns: minmax(0, 1fr); }
            #aurora-dashboard .av-hero-copy { min-height: auto; }
            #aurora-dashboard .av-flow-card { grid-template-columns: minmax(12rem, 1.2fr) minmax(10rem, .8fr) minmax(10rem, .8fr); }
            #aurora-dashboard .av-flow-status { grid-column: 2; grid-row: 1; }
            #aurora-dashboard .av-change { display: none; }
            #aurora-dashboard .av-capital { grid-template-columns: .75fr 1.25fr; }
            #aurora-dashboard .av-position-pills { grid-column: 1 / -1; }
        }
        @media (max-width: 760px) {
            #aurora-dashboard .av-shell { padding: 1rem .72rem 1.5rem; }
            #aurora-dashboard .av-market-pulse { display: none; }
            #aurora-dashboard .av-preview { display: none; }
            #aurora-dashboard .av-hero { border-radius: 1.25rem; }
            #aurora-dashboard .av-hero-grid { padding: 1.25rem; }
            #aurora-dashboard .av-hero h1 { font-size: clamp(2rem, 11vw, 3rem); }
            #aurora-dashboard .av-hero-summary { font-size: .78rem; }
            #aurora-dashboard .av-lens { padding: 1rem; }
            #aurora-dashboard .av-path { grid-template-columns: minmax(0, 1fr); }
            #aurora-dashboard .av-path-step { border-right: 0; border-bottom: 1px solid rgba(255, 255, 255, .1); }
            #aurora-dashboard .av-path-step:last-child { border-bottom: 0; }
            #aurora-dashboard .av-flow-card { grid-template-columns: minmax(0, 1fr) auto; gap: .7rem; }
            #aurora-dashboard .av-flow-status { grid-column: 2; grid-row: 1; }
            #aurora-dashboard .av-mini-returns { grid-column: 1 / -1; }
            #aurora-dashboard .av-capital { grid-template-columns: minmax(0, 1fr); }
            #aurora-dashboard .av-position-pills { grid-column: auto; }
            #aurora-dashboard .av-timeline-grid,
            #aurora-dashboard .av-market-footer { grid-template-columns: minmax(0, 1fr); }
            #aurora-dashboard .av-market-facts { flex-wrap: wrap; }
            #aurora-dashboard .av-mobile-dock { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0 .72rem 1.2rem; border: 1px solid rgba(255, 255, 255, .2); border-radius: 1rem; padding: .38rem; color: #dfe4ff; background: #131b43; box-shadow: 0 12px 28px rgba(10, 16, 46, .22); }
            #aurora-dashboard .av-mobile-dock a { display: flex; min-height: 2.7rem; align-items: center; justify-content: center; gap: .35rem; border-radius: .7rem; font-size: .62rem; font-weight: 850; }
            #aurora-dashboard .av-mobile-dock a.is-active { color: #17214b; background: #fff; }
        }
    </style>

    <main id="aurora-dashboard">
        <div class="av-shell">
            <header class="av-top">
                <div class="av-today">
                    <span class="av-today-mark"><x-heroicon-o-sparkles class="h-5 w-5" /></span>
                    <span><small>{{ __('Heute') }}</small><strong>{{ now()->translatedFormat('l, d. F') }}</strong></span>
                </div>
                <span class="av-market-pulse"><i></i>{{ __('Markt') }}: {{ $marketOutlook }}</span>
                <nav class="av-top-actions" aria-label="{{ __('Dashboard-Ansichten') }}">
                    <span class="av-quiet-link av-preview"><x-heroicon-o-beaker class="h-4 w-4" />{{ __('Lokale Vorschau') }}</span>
                    <a href="{{ route('dashboard', ['dashboard_classic' => 1]) }}" class="av-quiet-link"><x-heroicon-o-squares-2x2 class="h-4 w-4" />{{ __('Klassisch') }}</a>
                </nav>
            </header>

            <section class="av-hero" aria-labelledby="aurora-title">
                <div class="av-hero-grid">
                    <div class="av-hero-copy">
                        <div>
                            <p class="av-kicker">{{ __('Dein nächster Schritt') }}</p>
                            <h1 id="aurora-title">
                                {{ __('Hallo') }}{{ $firstName !== '' ? ', '.$firstName : '' }}.<br>
                                @if($buyCount > 0)
                                    <em>{{ $buyCount }} {{ $buyCount === 1 ? __('Kaufchance ist') : __('Kaufchancen sind') }}</em> {{ __('bereit.') }}
                                @else
                                    {{ __('Heute gibt es nichts zu tun.') }}
                                @endif
                            </h1>
                            <p class="av-hero-summary">
                                @if($buyCount > 0)
                                    {{ __('Die Rohsignale wurden in dieser lokalen Simulation geprüft. Nur die bestätigten Ergebnisse erscheinen als BUY.') }}
                                @else
                                    {{ __('Kein neues Signal hat alle Prüfschritte bestanden. Dein Depot läuft unverändert weiter.') }}
                                @endif
                                @if($sellCount > 0)
                                    <strong>{{ $sellCount }} {{ $sellCount === 1 ? __('Ausstieg benötigt') : __('Ausstiege benötigen') }} {{ __('Aufmerksamkeit.') }}</strong>
                                @endif
                            </p>
                            <div class="av-hero-actions">
                                <a href="{{ route('predictions.index') }}" class="av-primary">{{ __('Finale Signale öffnen') }} <x-heroicon-o-arrow-right class="h-4 w-4" /></a>
                                <a href="{{ route('paper-depots.index') }}" class="av-secondary"><x-heroicon-o-briefcase class="h-4 w-4" />{{ __('Strategiedepot') }}</a>
                            </div>
                        </div>
                        <div class="av-hero-meta">
                            <span><x-heroicon-o-check-badge class="h-4 w-4" />{{ __('Aktienfilter berücksichtigt') }}</span>
                            <span><x-heroicon-o-chart-bar-square class="h-4 w-4" />{{ number_format($activeCount, 0, ',', '.') }} {{ __('Aktien überwacht') }}</span>
                            <span><x-heroicon-o-calendar-days class="h-4 w-4" />{{ $schedule->count() }} {{ __('Aktionen geplant') }}</span>
                        </div>
                    </div>

                    <div class="av-lens">
                        @if($focusSignal)
                            <div class="av-lens-top">
                                <span class="av-lens-symbol">
                                    <span class="av-lens-logo">{{ strtoupper(substr((string) data_get($focusSignal, 'symbol', 'AK'), 0, 3)) }}</span>
                                    <span class="min-w-0"><b>{{ data_get($focusSignal, 'symbol', '—') }}</b><small>{{ data_get($focusSignal, 'name', '') }}</small></span>
                                </span>
                                <span class="av-badge {{ $signalClass($focusStatus) }}">{{ $focusStatus }}</span>
                            </div>

                            <div class="av-forecast">
                                @foreach([10, 20, 40] as $horizon)
                                    @php $expectedReturn = $focusHorizons->get($horizon); @endphp
                                    <div class="av-forecast-row">
                                        <span>{{ $horizon }}T</span>
                                        <span class="av-forecast-track"><i class="{{ is_numeric($expectedReturn) && (float) $expectedReturn < 0 ? 'is-negative' : '' }}" style="width: {{ $barWidth($expectedReturn) }}%"></i></span>
                                        <strong>{{ is_numeric($expectedReturn) && (float) $expectedReturn >= 0 ? '+' : '' }}{{ is_numeric($expectedReturn) ? number_format((float) $expectedReturn, 1, ',', '.').' %' : '—' }}</strong>
                                    </div>
                                @endforeach
                            </div>

                            <div class="av-lens-stats">
                                <span class="av-lens-stat"><small>{{ __('Signalstärke') }}</small><strong>{{ $focusScore !== null ? number_format($focusScore, 1, ',', '.').' / 10' : '—' }}</strong></span>
                                <span class="av-lens-stat"><small>{{ __('Risiko') }}</small><strong>{{ $focusRisk !== null ? number_format($focusRisk, 0, ',', '.').' %' : '—' }}</strong></span>
                            </div>
                        @else
                            <div class="av-flow-empty">{{ __('Kein neues finales Signal. Du musst heute nichts tun.') }}</div>
                        @endif
                    </div>
                </div>

                @if($focusSignal)
                    <div class="av-path" aria-label="{{ __('Simulierter Signalweg') }}">
                        @foreach($focusSteps as [$stepLabel, $stepValue, $stepTone])
                            <div class="av-path-step {{ $stepTone }}">
                                <small>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} · {{ $stepLabel }}</small>
                                <strong><span class="av-step-check"><x-heroicon-o-check class="h-3 w-3" /></span>{{ $stepValue }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="av-section" aria-labelledby="signal-flow-title">
                <div class="av-section-head">
                    <div><small>{{ __('Signal Flow') }}</small><h2 id="signal-flow-title">{{ __('Weitere Veränderungen') }}</h2></div>
                    <a href="{{ route('predictions.index') }}" class="av-link">{{ __('Alle Signale') }} <x-heroicon-o-arrow-up-right class="h-4 w-4" /></a>
                </div>
                <div class="av-flow">
                    @forelse($signalFlow as $change)
                        @php
                            $to = strtoupper((string) data_get($change, 'to', 'HOLD'));
                            $from = strtoupper((string) data_get($change, 'from', '—'));
                            $horizons = collect(data_get($change, 'horizons', []));
                            $risk = is_numeric(data_get($change, 'risk')) ? (float) data_get($change, 'risk') : null;
                        @endphp
                        <article class="av-flow-card {{ $signalClass($to) }}">
                            <span class="av-flow-company">
                                <span class="av-flow-logo">{{ strtoupper(substr((string) data_get($change, 'symbol', 'AK'), 0, 3)) }}</span>
                                <span class="min-w-0"><b>{{ data_get($change, 'symbol', '—') }} · {{ data_get($change, 'name', '') }}</b><small>{{ $relativeTime(data_get($change, 'at')) }}</small></span>
                            </span>
                            <span class="av-change"><strong>{{ $from }} → {{ $to }}</strong>{{ $to === 'BUY' ? __('Aktienfilter bestanden') : ($to === 'SELL' ? __('Exit-Regel ausgelöst') : __('Weiter beobachten')) }}</span>
                            <span class="av-mini-returns">
                                @foreach([10, 20, 40] as $horizon)
                                    @php $expectedReturn = $horizons->get($horizon); @endphp
                                    <span class="av-mini-return"><small>{{ $horizon }}T</small><b class="{{ is_numeric($expectedReturn) && (float) $expectedReturn < 0 ? 'is-negative' : '' }}">{{ is_numeric($expectedReturn) && (float) $expectedReturn >= 0 ? '+' : '' }}{{ is_numeric($expectedReturn) ? number_format((float) $expectedReturn, 1, ',', '.').'%' : '—' }}</b></span>
                                @endforeach
                            </span>
                            <span class="av-flow-status"><span class="av-badge {{ $signalClass($to) }}">{{ $to }}</span><br>{{ __('Risiko') }} {{ $risk !== null ? number_format($risk, 0, ',', '.').' %' : '—' }}</span>
                        </article>
                    @empty
                        <div class="av-flow-empty">{{ __('Keine weiteren Signaländerungen. Der Fokus oben enthält alles, was heute relevant ist.') }}</div>
                    @endforelse
                </div>
            </section>

            <section class="av-section av-capital" aria-labelledby="capital-title">
                <div class="av-capital-value">
                    <small>{{ __('Strategiedepot') }}</small>
                    <strong id="capital-title">{{ number_format($portfolioValue, 0, ',', '.') }} {{ $currency }}</strong>
                    <span class="av-capital-performance {{ $portfolioPerformance < 0 ? 'is-negative' : '' }}"><x-heroicon-o-arrow-trending-up class="h-4 w-4" />{{ $portfolioPerformance >= 0 ? '+' : '' }}{{ number_format($portfolioPerformance, 1, ',', '.') }} %</span>
                </div>
                <div>
                    <div class="av-capital-bar" aria-label="{{ __('Kapitalbindung') }}"><span style="width: {{ $capitalBinding }}%"></span></div>
                    <div class="av-capital-labels"><span>{{ __('Investiert') }} <b>{{ number_format($positionsValue, 0, ',', '.') }} {{ $currency }}</b></span><span>{{ __('Frei') }} <b>{{ number_format($availableCash, 0, ',', '.') }} {{ $currency }}</b></span></div>
                </div>
                <div class="av-position-pills">
                    @forelse($positions->take(4) as $position)
                        @php
                            $buyPrice = (float) ($position->average_buy_price ?? 0);
                            $currentPrice = (float) ($position->current_price ?? $buyPrice);
                            $positionReturn = $buyPrice > 0 ? (($currentPrice / $buyPrice) - 1) * 100 : 0;
                        @endphp
                        <span class="av-position-pill"><span><b>{{ $position->instrument?->symbol ?? '—' }}</b><em class="{{ $positionReturn < 0 ? 'is-negative' : '' }}">{{ $positionReturn >= 0 ? '+' : '' }}{{ number_format($positionReturn, 1, ',', '.') }}%</em></span><small>{{ $shortDate($position->opened_at_date ?? null) }} · {{ number_format((float) ($position->quantity ?? 0), 2, ',', '.') }} {{ __('Stück') }}</small></span>
                    @empty
                        <span class="av-position-pill"><b>{{ __('Kein Kapital gebunden') }}</b></span>
                    @endforelse
                </div>
            </section>

            <section class="av-section av-timeline-grid">
                <article class="av-panel" aria-labelledby="activity-title">
                    <div class="av-panel-head"><h3 id="activity-title">{{ __('Was passiert ist') }}</h3><span>{{ $activities->count() }} {{ __('Aktivitäten') }}</span></div>
                    <div class="av-event-list">
                        @forelse($activities as $activity)
                            <div class="av-event">
                                <span class="av-event-dot">
                                    @if(data_get($activity, 'type') === 'email')
                                        <x-heroicon-o-envelope class="h-3 w-3" />
                                    @elseif(data_get($activity, 'type') === 'calendar')
                                        <x-heroicon-o-calendar-days class="h-3 w-3" />
                                    @elseif(data_get($activity, 'type') === 'portfolio')
                                        <x-heroicon-o-briefcase class="h-3 w-3" />
                                    @else
                                        <x-heroicon-o-signal class="h-3 w-3" />
                                    @endif
                                </span>
                                <span class="av-event-copy"><b>{{ data_get($activity, 'title', __('Aktivität')) }}</b><p>{{ data_get($activity, 'detail', '') }}</p></span>
                                <time>{{ $relativeTime(data_get($activity, 'occurred_at')) }}</time>
                            </div>
                        @empty
                            <div class="av-flow-empty">{{ __('Noch keine Aktivitäten vorhanden.') }}</div>
                        @endforelse
                    </div>
                </article>

                <article class="av-panel" aria-labelledby="agenda-title">
                    <div class="av-panel-head"><h3 id="agenda-title">{{ __('Was als Nächstes passiert') }}</h3><span>{{ __('E-Mails & Termine') }}</span></div>
                    <div class="av-agenda">
                        @forelse($schedule as $item)
                            <div class="av-agenda-item">
                                <span class="av-agenda-date"><b>{{ $shortDate(data_get($item, 'sort_at'), 'd') }}</b><small>{{ $shortDate(data_get($item, 'sort_at'), 'M') }}</small></span>
                                <span class="av-agenda-copy"><b>{{ data_get($item, 'symbol', '') }} · {{ data_get($item, 'label', __('Termin')) }}</b><span>{{ data_get($item, 'schedule', '') }}</span></span>
                            </div>
                        @empty
                            <div class="av-flow-empty">{{ __('Keine E-Mails oder Termine geplant.') }}</div>
                        @endforelse
                    </div>
                </article>
            </section>

            <footer class="av-market-footer">
                <div><h3>{{ $marketHeadline }}</h3><p>{{ $marketSummary }}</p></div>
                <div class="av-market-facts">
                    <span class="av-market-fact"><small>{{ __('Konfidenz') }}</small><b>{{ $marketConfidence !== null ? number_format($marketConfidence, 0, ',', '.').' %' : '—' }}</b></span>
                    <span class="av-market-fact"><small>{{ __('Marktrisiko') }}</small><b>{{ $marketRisk }}</b></span>
                    <a href="{{ route('daily-market-analysis') }}" class="av-link">{{ __('Marktbericht') }} <x-heroicon-o-arrow-right class="h-4 w-4" /></a>
                </div>
            </footer>
        </div>

        <nav class="av-mobile-dock" aria-label="{{ __('Mobile Navigation') }}">
            <a href="{{ route('dashboard') }}" class="is-active"><x-heroicon-o-sparkles class="h-4 w-4" />{{ __('Heute') }}</a>
            <a href="{{ route('predictions.index') }}"><x-heroicon-o-signal class="h-4 w-4" />{{ __('Signale') }}</a>
            <a href="{{ route('paper-depots.index') }}"><x-heroicon-o-briefcase class="h-4 w-4" />{{ __('Depot') }}</a>
        </nav>
    </main>
</x-app-layout>
