<x-app-layout>
    @php
        $simulateLiveQuotes = request()->boolean('simulate_live')
            && ((bool) (auth()->user()?->is_admin ?? false) || strtolower((string) (auth()->user()?->role ?? '')) === 'admin');
    @endphp
    <style>
        @media (min-width:768px) {
            .screener-page .screener-table-head,.screener-page .screener-desktop-summary{grid-template-columns:360px minmax(135px,1fr) 100px 100px 160px 160px repeat(3,72px) 24px!important}
            .screener-page .screener-desktop-forecasts>i{box-sizing:border-box;width:72px!important;min-width:72px!important;max-width:72px!important}
            .screener-page .screener-desktop-scale-grade{display:grid!important;width:160px!important;min-width:160px!important;align-content:center;align-items:stretch!important;justify-self:stretch;gap:.48rem;padding:0 .45rem}
            .screener-page .screener-desktop-scale-grade>strong{overflow:hidden;color:var(--ak-text);font-size:.68rem;font-weight:850;line-height:1;text-align:left;text-overflow:ellipsis;white-space:nowrap}
            .screener-page .screener-desktop-scale-grade>.screener-desktop-scale{position:relative;display:block!important;box-sizing:border-box;width:145px!important;min-width:145px!important;max-width:145px!important;height:.34rem;align-self:center;flex:0 0 145px!important;border-radius:.12rem;font-style:normal}
            .screener-page .screener-row-profile-badge{display:inline-grid;width:1.65rem;height:1.65rem;flex:none;place-items:center;border:1px solid var(--profile-border);border-radius:.48rem;background:var(--profile-bg);color:var(--profile-color)}
            .screener-page .screener-row-profile-badge svg{width:.95rem;height:.95rem;stroke-width:2}
            .screener-page .screener-trigger-model{display:flex;min-width:0;align-items:center;justify-content:center}
            .screener-page .screener-desktop-signal>strong,.screener-page .screener-trigger-model>strong{box-sizing:border-box;width:84px!important;min-width:84px!important;max-width:84px!important;text-align:center}
            .screener-page .screener-trigger-model>strong{overflow:hidden;border:1px solid color-mix(in srgb,#22d3ee 42%,transparent);border-radius:.38rem;background:color-mix(in srgb,#22d3ee 9%,transparent);padding:.3rem .42rem;color:#67e8f9;font-size:.58rem;font-weight:900;line-height:1;text-overflow:ellipsis;white-space:nowrap}
            .screener-page .screener-trigger-model[data-model="tcn"]>strong{border-color:color-mix(in srgb,#a78bfa 48%,transparent);background:color-mix(in srgb,#a78bfa 10%,transparent);color:#c4b5fd}
            .screener-page .screener-table-head>span:not(:first-child),.screener-page .screener-desktop-summary>.screener-desktop-price,.screener-page .screener-desktop-summary>.screener-desktop-signal,.screener-page .screener-desktop-summary>.screener-trigger-model,.screener-page .screener-desktop-summary>.screener-desktop-grade,.screener-page .screener-desktop-summary>.screener-desktop-forecasts>i,.screener-page .screener-desktop-summary>svg{border-left:1px solid color-mix(in srgb,var(--ak-muted) 13%,transparent)!important}
            .screener-page .screener-desktop-summary>.screener-desktop-forecasts>i:first-of-type{border-left:1px solid color-mix(in srgb,var(--ak-muted) 13%,transparent)!important}
            .screener-page .screener-desktop-forecasts>i.is-trigger-horizon{border-left-color:color-mix(in srgb,var(--ak-muted) 13%,transparent)!important;border-bottom:2px solid color-mix(in srgb,#22d3ee 72%,transparent)!important;background:transparent!important;box-shadow:none!important}
            .screener-page .screener-desktop-forecasts>i.is-time-downgraded{border-bottom:2px solid #fbbf24!important}
            .screener-page .screener-desktop-forecasts>i.is-time-downgraded>strong:not(.text-rose-400){color:#fbbf24!important}
            .screener-page .screener-desktop-forecasts>i.is-negative-forecast{border-bottom:2px solid #fb7185!important}
            .screener-page .screener-desktop-forecasts>i.is-live-recalculated{position:relative}
            .screener-page .screener-desktop-forecasts>i.is-live-recalculated::after{position:absolute;top:.25rem;right:.25rem;width:.28rem;height:.28rem;border-radius:999px;background:#34d399;box-shadow:0 0 .35rem rgba(52,211,153,.72);content:""}
            .screener-page .screener-desktop-forecasts>i.is-delayed-recalculated::after{position:absolute;top:.25rem;right:.25rem;width:.28rem;height:.28rem;border-radius:999px;background:#fbbf24;content:""}
            .screener-page .screener-risk-choice span{display:inline-flex;align-items:center;justify-content:center;gap:.35rem}
            .screener-page .screener-risk-choice svg{width:1rem;height:1rem;flex:none;stroke-width:2}
            .screener-page .screener-desktop-scale::before{position:absolute;inset:0;border-radius:inherit;background:linear-gradient(90deg,#fb7185,#fbbf24 48%,#34d399);content:"";-webkit-mask:repeating-linear-gradient(90deg,#000 0 calc(10% - 2px),transparent calc(10% - 2px) 10%);mask:repeating-linear-gradient(90deg,#000 0 calc(10% - 2px),transparent calc(10% - 2px) 10%);opacity:.32}
            .screener-page .screener-desktop-scale>em{position:absolute;top:50%;left:clamp(5%,var(--position),95%);z-index:2;width:calc(10% - 2px);min-width:5px;height:.72rem;border:1px solid color-mix(in srgb,var(--marker,#22d3ee) 72%,white);border-radius:.14rem;background:var(--marker,#22d3ee);box-shadow:0 0 .35rem color-mix(in srgb,var(--marker,#22d3ee) 70%,transparent);transform:translate(-50%,-50%)}
        }
        @media (max-width:767px) {
            .screener-page{padding-top:.75rem!important}
            .screener-page>header{margin-bottom:.55rem!important}
            .screener-page>header h1{font-size:1.65rem!important}
            .screener-page .screener-filter-shell{margin-bottom:.8rem!important}
            .screener-page .screener-filter-shell>button{height:2.6rem!important;padding-inline:.85rem!important;font-size:.78rem!important}
            .screener-page .screener-filter-bar{gap:.45rem!important;margin-top:.45rem!important;padding:.6rem!important}
            .screener-page .screener-filter-bar :is(input,select,button,.screener-custom-select-trigger),.screener-page .screener-filter-bar>a{box-sizing:border-box!important;width:100%!important;max-width:100%!important;min-height:2.5rem!important;height:2.5rem!important;zoom:1!important;padding-block:0!important;font-size:.88rem!important;line-height:1.1!important}
            .screener-page .screener-risk-choice{width:100%!important;min-width:0!important;min-height:2.5rem!important;height:2.5rem!important}
            .screener-page .screener-risk-choice span{display:grid!important;height:100%!important;place-items:center!important;padding:0!important}
            .screener-page .screener-risk-choice svg{width:1.2rem!important;height:1.2rem!important;stroke-width:2!important}
            .screener-page .screener-risk-choice .risk-profile-text{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}
            .screener-page .screener-mobile-profile-badge{display:inline-grid!important;width:1rem!important;height:1rem!important;margin-right:.28rem;place-items:center!important;border:1px solid var(--profile-border);border-radius:.3rem;background:var(--profile-bg);color:var(--profile-color);vertical-align:-.18rem}
            .screener-page .screener-mobile-profile-badge svg{width:.65rem!important;height:.65rem!important;stroke-width:2.2!important}
            .screener-page .screener-mobile-details .screener-fundamental-strip,
            .screener-page .screener-mobile-details .screener-fundamentals-slide,
            .screener-page .screener-mobile-details .screener-mobile-fundamentals{display:none!important}
        }
    </style>
    <div data-simulate-live="{{ $simulateLiveQuotes ? '1' : '0' }}" x-data="{ filtering: false, submitFilters(form) { this.filtering = true; requestAnimationFrame(() => form.submit()) } }" @pageshow.window="filtering = false" class="screener-page mx-auto max-w-[96rem] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
        <header class="mb-3">
            <h1 class="text-3xl font-black tracking-tight">{{ __('Aktienscreener') }}</h1>
        </header>

        @if($isFreeRegional)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-400/25 bg-amber-400/[.06] px-4 py-3"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-amber-400">{{ __('Free · Regionales Top-100-Portfolio') }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Der Aktienscreener zeigt ausschließlich die 100 wichtigsten Aktien deiner Region (:country).', ['country' => $regionalCountry]) }}</p></div><a href="{{ route('pricing') }}" class="text-[9px] font-black text-amber-300">{{ __('Alle Aktien ab Plus') }} →</a></div>
        @endif

        @if (session('status'))
            <div class="mb-3 rounded-lg border border-emerald-400/25 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-300">
                {{ match(session('status')) {
                    'watchlist-item-added' => __('Aktie wurde zur Watchlist hinzugefügt.'),
                    'watchlist-item-removed' => __('Aktie wurde aus der Watchlist entfernt.'),
                    'paper-depot-item-added' => __('Aktie wurde ins Musterdepot gelegt.'),
                    default => session('status'),
                } }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-400/25 bg-rose-400/10 px-3 py-2 text-xs font-bold text-rose-300">{{ $errors->first() }}</div>
        @endif

        <section x-data="{ filtersOpen: localStorage.getItem('screenerFilters') !== 'closed', toggleFilters() { this.filtersOpen = ! this.filtersOpen; localStorage.setItem('screenerFilters', this.filtersOpen ? 'open' : 'closed') } }" class="screener-filter-shell mb-5 shrink-0">
        <button type="button" @click="toggleFilters()" :aria-expanded="filtersOpen" class="flex h-10 w-full items-center justify-between rounded-xl border border-cyan-400/30 bg-[var(--ak-card)] px-4 text-xs font-black text-cyan-300 shadow-[var(--ak-shadow)]">
            <span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span>
            <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
        </button>
        <form x-cloak x-show.important="filtersOpen" method="GET" action="{{ route('screener.index') }}" class="screener-filter-bar mt-2 flex flex-nowrap gap-2 overflow-x-auto rounded-lg border border-cyan-400/30 bg-[var(--ak-card)] p-3 text-[17px] shadow-[var(--ak-shadow)]" style="font-size:17px">
            <label class="relative min-w-[180px] flex-[1.5]">
                <span class="sr-only">{{ __('Aktie suchen') }}</span>
                <input name="q" value="{{ request('q') }}" @input.debounce.500ms="submitFilters($el.form)" placeholder="{{ __('Aktie oder Symbol') }}" class="ak-input h-10 w-full text-sm" />
            </label>
            <select name="sector" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('Alle Sektoren') }}</option>@foreach($sectors as $sector)<option value="{{ $sector }}" @selected(request('sector') === $sector)>{{ $sector }}</option>@endforeach</select>
            @php $selectedRiskProfiles=collect(request('risk_class',[]));$defaultRiskProfiles=!request()->boolean('risk_profiles'); @endphp
            <fieldset class="screener-risk-choice"><legend class="sr-only">{{ __('Profil') }}</legend><input type="hidden" name="risk_profiles" value="1"><button type="button" aria-label="{{ __('Alle') }}" title="{{ __('Alle') }}" @click="$el.closest('fieldset').querySelectorAll('input[type=checkbox]').forEach(input => input.checked = true); submitFilters($el.form)"><span><x-heroicon-o-squares-2x2 /><b class="risk-profile-text">{{ __('Alle') }}</b></span></button><label title="{{ __('Defensiv') }}"><input type="checkbox" name="risk_class[]" value="defensive" aria-label="{{ __('Defensiv') }}" @checked($defaultRiskProfiles||$selectedRiskProfiles->contains('defensive')) @change="submitFilters($el.form)"><span><x-heroicon-o-shield-check /><b class="risk-profile-text">{{ __('Defensiv') }}</b></span></label><label title="{{ __('Ausgewogen') }}"><input type="checkbox" name="risk_class[]" value="balanced" aria-label="{{ __('Ausgewogen') }}" @checked($defaultRiskProfiles||$selectedRiskProfiles->contains('balanced')) @change="submitFilters($el.form)"><span><x-heroicon-o-scale /><b class="risk-profile-text">{{ __('Ausgewogen') }}</b></span></label><label title="{{ __('Offensiv') }}"><input type="checkbox" name="risk_class[]" value="offensive" aria-label="{{ __('Offensiv') }}" @checked($defaultRiskProfiles||$selectedRiskProfiles->contains('offensive')) @change="submitFilters($el.form)"><span><x-heroicon-o-bolt /><b class="risk-profile-text">{{ __('Offensiv') }}</b></span></label></fieldset>
            <select name="index" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('Alle Indizes') }}</option>@foreach($indices as $index)<option value="{{ $index->symbol }}" @selected(request('index') === $index->symbol)>{{ $index->name ?: $index->symbol }}</option>@endforeach</select>
            <select name="signal" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[125px] flex-1 text-sm"><option value="">{{ __('BUY, WAIT und WATCH') }}</option>@foreach(['BUY','WAIT','WATCH'] as $signal)<option value="{{ $signal }}" @selected(request('signal') === $signal)>{{ $signal }}</option>@endforeach</select>
            <select name="min_max_return" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[185px] flex-1 text-base font-bold" aria-label="{{ __('Maximale Rendite') }}">
                <option value="">{{ __('Max. Rendite') }} · {{ __('Alle') }}</option>
                @foreach(range(0,10,2) as $minimum)<option value="{{ $minimum }}" @selected((string)request('min_max_return')===(string)$minimum)>{{ __('Max. Rendite') }} ≥ {{ $minimum > 0 ? '+' : '' }}{{ $minimum }} %</option>@endforeach
            </select>
            <select name="transition_days" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[150px] flex-1 text-sm">
                <option value="">{{ __('Alle Signalübergänge') }}</option>
                @foreach([1, 5, 10, 20] as $days)
                    <option value="{{ $days }}" @selected((int) request('transition_days') === $days)>{{ trans_choice('Letzter :days Tag|Letzte :days Tage', $days, ['days' => $days]) }}</option>
                @endforeach
            </select>
            <select name="bestand" @change="submitFilters($el.form)" aria-label="{{ __('Nach Watchlist oder Musterdepot filtern') }}" class="ak-input h-10 min-w-[190px] flex-1 text-sm">
                <option value="">{{ __('Alle Aktien') }}</option>
                @if($userWatchlists->isNotEmpty())
                    <option value="watchlists" @selected(request('bestand') === 'watchlists')>{{ __('Alle Watchlists') }}</option>
                    <optgroup label="{{ __('Watchlists') }}">
                        @foreach($userWatchlists as $watchlist)
                            <option value="watchlist:{{ $watchlist->id }}" @selected(request('bestand') === 'watchlist:'.$watchlist->id)>{{ $watchlist->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($paperPortfolios->isNotEmpty())
                    <optgroup label="{{ __('Musterdepots') }}">
                        @foreach($paperPortfolios as $portfolio)
                            <option value="portfolio:{{ $portfolio->id }}" @selected(request('bestand') === 'portfolio:'.$portfolio->id)>{{ $portfolio->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <select name="limit" @change="submitFilters($el.form)" class="ak-input h-10 min-w-[105px] flex-1 text-sm">
                @foreach(['10' => 'Top 10', '25' => 'Top 25', '50' => 'Top 50', '100' => 'Top 100', 'all' => __('Alle')] as $value => $label)
                    <option value="{{ $value }}" @selected((string) request('limit', 'all') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a href="{{ route('screener.index') }}" @click="filtering = true" class="screener-filter-reset inline-flex h-10 shrink-0 items-center justify-center border border-amber-400/40 bg-amber-400/[.10] px-4 text-xs font-black text-amber-300 transition hover:bg-amber-400/[.18]">{{ __('Reset') }}</a>
        </form>
        </section>

        @php
            $sectorSymbolFor = static function (?string $sector): string {
                $sectorKey = mb_strtolower((string) $sector);

                return match (true) {
                    str_contains($sectorKey, 'technolog') => '💻',
                    str_contains($sectorKey, 'finanz'), str_contains($sectorKey, 'financial') => '🏦',
                    str_contains($sectorKey, 'gesund'), str_contains($sectorKey, 'health') => '🧬',
                    str_contains($sectorKey, 'industrie'), str_contains($sectorKey, 'industrial') => '⚙️',
                    str_contains($sectorKey, 'kommunikation'), str_contains($sectorKey, 'communication') => '📡',
                    str_contains($sectorKey, 'energie'), str_contains($sectorKey, 'energy') => '⚡',
                    str_contains($sectorKey, 'versorgung'), str_contains($sectorKey, 'utilit') => '💡',
                    str_contains($sectorKey, 'immobil'), str_contains($sectorKey, 'real estate') => '🏢',
                    str_contains($sectorKey, 'grundstoff'), str_contains($sectorKey, 'material') => '⛏️',
                    str_contains($sectorKey, 'defensiv'), str_contains($sectorKey, 'staple') => '🛒',
                    str_contains($sectorKey, 'zykl'), str_contains($sectorKey, 'consumer') => '🛜️',
                    default => '◇',
                };
            };
        @endphp

        <nav class="screener-country-quick-filter hidden lg:flex" aria-label="{{ __('Länder-Schnellfilter') }}">
            <span class="screener-country-quick-filter-label">{{ __('Land') }}</span>
            <div class="screener-country-quick-filter-items">
                <a
                    href="{{ route('screener.index', request()->except(['country', 'page'])) }}"
                    @click="filtering = true"
                    class="screener-country-quick-filter-button {{ blank(request('country')) ? 'is-active' : '' }}"
                    @if(blank(request('country'))) aria-current="true" @endif
                    title="{{ __('Alle Länder') }}"
                ><span aria-hidden="true">🌐</span><small>{{ __('Alle') }}</small></a>
                @foreach($countries as $country)
                    @php $quickFilterFlag = \App\Support\CountryFlag::emoji($country); @endphp
                    <a
                        href="{{ route('screener.index', array_merge(request()->except(['country', 'limit', 'page']), ['country' => $country, 'limit' => 'all'])) }}"
                        @click="filtering = true"
                        class="screener-country-quick-filter-button {{ request('country') === $country ? 'is-active' : '' }}"
                        @if(request('country') === $country) aria-current="true" @endif
                        title="{{ $country }}"
                    ><span aria-hidden="true">{{ $quickFilterFlag }}</span><small>{{ $country }}</small></a>
                @endforeach
            </div>
        </nav>

        <div
            x-cloak
            x-show="filtering"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="pointer-events-none fixed left-1/2 top-24 z-[90] -translate-x-1/2"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-center gap-3 rounded-xl border border-cyan-400/35 bg-[var(--ak-card-strong)] px-4 py-3 text-xs font-black text-cyan-400 shadow-2xl backdrop-blur-xl">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-cyan-400/25 border-t-cyan-400"></span>
                <span>{{ __('Aktien werden gefiltert …') }}</span>
            </div>
        </div>

        <div class="screener-results-scroll transition duration-200" :class="filtering ? 'scale-[.998] opacity-45' : 'scale-100 opacity-100'">
        <section class="screener-desktop-table grid grid-cols-1 gap-4">
            <div class="screener-table-head hidden lg:grid" aria-hidden="true">
                <span>{{ __('Aktie') }}</span>
                <span>{{ __('Kurs') }}</span>
                <span>{{ __('Signal') }}</span>
                <span>{{ __('Modell') }}</span>
                <span>{{ __('Bewertung') }}</span>
                <span>{{ __('Risiko') }}</span>
                @foreach([10, 20, 40] as $days)
                    <span><button type="button" data-screener-sort="forecast-{{ $days }}">{{ $days }}T <i>↕</i></button></span>
                @endforeach
                <span></span>
            </div>
            @forelse($stocks as $stock)
                @php
                    // Top-10 ranking and its explanation are based on the model
                    // signal. Keep the visible badge consistent with that ranking
                    // instead of applying the user's stricter short-term overlay.
                    $signal = strtoupper((string) ($stock->personalized_signal ?: $stock->model_signal ?: 'HOLD'));
                    $tone = match ($signal) {
                        'BUY' => 'border-emerald-300/80 bg-emerald-500/[.35] text-white shadow-[0_0_18px_rgba(16,185,129,.42)]',
                        'SELL' => 'border-rose-400/45 bg-rose-400/[.10] text-rose-300',
                        'WATCH' => 'border-lime-400/40 bg-lime-400/[.08] text-lime-300',
                        'WAIT' => 'border-emerald-300/80 bg-emerald-500/[.35] text-white shadow-[0_0_18px_rgba(16,185,129,.42)]',
                        default => 'border-amber-400/40 bg-amber-400/[.08] text-amber-300',
                    };
                    $signalLabel = $signal;
                    $recentNews = $recentNewsByInstrument->get((int) $stock->instrument_id);
                    $recentNewsSentiment = is_numeric($recentNews?->sentiment_score) ? (float) $recentNews->sentiment_score : null;
                    [$recentNewsTone, $recentNewsLabel] = match (true) {
                        $recentNews === null => ['border-slate-400/20 bg-slate-400/[.04] text-slate-500', __('Keine News in den letzten 48 Stunden')],
                        $recentNewsSentiment !== null && $recentNewsSentiment >= .35 => ['border-emerald-400/45 bg-emerald-400/[.14] text-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.18)]', __('Positive News in den letzten 48 Stunden')],
                        $recentNewsSentiment !== null && $recentNewsSentiment <= -.35 => ['border-rose-400/45 bg-rose-400/[.14] text-rose-400 shadow-[0_0_12px_rgba(251,113,133,.18)]', __('Negative News in den letzten 48 Stunden')],
                        default => ['border-amber-400/45 bg-amber-400/[.14] text-amber-400 shadow-[0_0_12px_rgba(251,191,36,.16)]', __('Neutrale News in den letzten 48 Stunden')],
                    };
                    $return = is_numeric($stock->expected_return_20d) ? (float) $stock->expected_return_20d : null;
                    $rankingScorePercent = is_numeric($stock->ranking_score)
                        ? max(0, min(100, (float) $stock->ranking_score))
                        : 0;
                    $qualityDonutColor = static function (float $percent): string {
                        $percent = max(0, min(100, $percent));
                        $hue = $percent <= 50
                            ? ($percent / 50) * 48
                            : 48 + (($percent - 50) / 50) * 94;
                        return sprintf('hsl(%.1f 78%% 52%%)', $hue);
                    };
                    $rankingScoreColor = $qualityDonutColor($rankingScorePercent);
                    $rankingConfidencePercent = is_numeric($stock->confidence_percent)
                        ? max(0, min(100, (float) $stock->confidence_percent))
                        : 0;
                    $rankingRiskPercent = \App\Support\RiskScore::toPercent($stock->risk_percent, $stock->ranking_drawdown ?? null);
                    $riskDonutColor = $rankingRiskPercent !== null ? $qualityDonutColor(100 - $rankingRiskPercent) : '#64748b';
                    $riskDonutTone = $rankingRiskPercent === null ? 'unknown' : ($rankingRiskPercent >= 50 ? 'high' : ($rankingRiskPercent >= 30 ? 'medium' : 'low'));
                    $rankingHitRateAvailable = is_numeric($stock->ranking_hit_rate);
                    $rankingHitRatePercent = $rankingHitRateAvailable
                        ? max(0, min(100, (float) $stock->ranking_hit_rate))
                        : 0;
                    $rankingProfitFactorAvailable = is_numeric($stock->ranking_profit_factor);
                    $rankingProfitFactor = $rankingProfitFactorAvailable ? \App\Support\ProfitFactor::cap($stock->ranking_profit_factor) : 0;
                    $rankingProfitFactorPercent = $rankingProfitFactorAvailable
                        ? max(0, min(100, ($rankingProfitFactor / 3) * 100))
                        : 0;
                    $rankingStabilityAvailable = (bool) $stock->ranking_stability_available;
                    $rankingStabilityPercent = $rankingStabilityAvailable
                        ? max(0, min(100, (float) $stock->ranking_stability_percent))
                        : 0;
                    $rankingConfidenceColor = $qualityDonutColor($rankingConfidencePercent);
                    $rankingHitRateColor = $rankingHitRateAvailable ? $qualityDonutColor($rankingHitRatePercent) : '#64748b';
                    $rankingProfitFactorColor = $rankingProfitFactorAvailable
                        ? sprintf(
                            'hsl(%.1f 78%% 47%%)',
                            24 + (min(1, $rankingProfitFactor / 1.8) * 118)
                        )
                        : '#64748b';
                    $rankingStabilityColor = $rankingStabilityAvailable ? $qualityDonutColor($rankingStabilityPercent) : '#64748b';
                    $dividendYield = is_numeric($stock->dividend_yield)
                        ? (float) $stock->dividend_yield * (abs((float) $stock->dividend_yield) <= 1 ? 100 : 1)
                        : null;
                    $priceEarningsRatio = is_numeric($stock->trailing_pe)
                        ? (float) $stock->trailing_pe
                        : (is_numeric($stock->forward_pe) ? (float) $stock->forward_pe : null);
                    $stockWatchlistIds = collect($watchlistMemberships->get($stock->instrument_id, []));
                    $stockPaperPortfolioIds = collect($paperPortfolioMemberships->get($stock->instrument_id, []));
                    $isOnWatchlist = $stockWatchlistIds->isNotEmpty();
                    $isInPaperPortfolio = $stockPaperPortfolioIds->isNotEmpty();
                    $matchingPaperPortfolios = $paperPortfolios->where('currency', $stock->currency);
                    $returnClass = $return !== null && $return >= 0 ? 'text-emerald-300' : 'text-rose-300';
                    $chartPoints = collect($stock->chart_points ?? []);
                    $predictionPrice = is_numeric($stock->predicted_price_20d) ? (float) $stock->predicted_price_20d : null;
                    $currencySymbol = static fn (?string $currency): string => match (strtoupper(trim((string) $currency))) {
                        'EUR' => '€',
                        'USD' => '$',
                        'GBP' => '£',
                        'JPY', 'CNY' => '¥',
                        'HKD' => 'HK$',
                        'CHF' => 'CHF',
                        'CAD' => 'C$',
                        'AUD' => 'A$',
                        'SEK' => 'kr',
                        default => strtoupper(trim((string) $currency)) ?: '—',
                    };
                    $displayCurrencySymbol = $currencySymbol($stock->currency);
                    $originalCurrencySymbol = $currencySymbol($stock->original_currency ?? null);
                    $currencyName = static fn (?string $currency): string => match (strtoupper(trim((string) $currency))) {
                        'EUR' => __('Euro'),
                        'USD' => __('US-Dollar'),
                        'GBP' => __('Britisches Pfund'),
                        'JPY' => __('Japanischer Yen'),
                        'CNY' => __('Chinesischer Renminbi'),
                        'HKD' => __('Hongkong-Dollar'),
                        'CHF' => __('Schweizer Franken'),
                        'CAD' => __('Kanadischer Dollar'),
                        'AUD' => __('Australischer Dollar'),
                        'SEK' => __('Schwedische Krone'),
                        default => strtoupper(trim((string) $currency)) ?: __('Unbekannt'),
                    };
                    $originalCurrencyName = $currencyName($stock->original_currency ?? null);
                    $showOriginalPrice = is_numeric($stock->original_price ?? null)
                        && filled($stock->original_currency ?? null)
                        && strtoupper((string) $stock->original_currency) !== strtoupper((string) $stock->currency);
                    // Price bars use the instrument's original listing currency, while
                    // the visible quote can already have been converted to EUR. Anchor
                    // the forecast to the last chart close and apply the model return so
                    // both parts of the chart always use the same currency and scale.
                    $latestChartPrice = $chartPoints->isNotEmpty() ? (float) $chartPoints->last() : null;
                    $chartPredictionPrice = $latestChartPrice !== null && $return !== null
                        ? $latestChartPrice * (1 + ($return / 100))
                        : null;
                    $chartMin = $chartPoints->isNotEmpty() ? (float) $chartPoints->min() : 0;
                    $chartMax = $chartPoints->isNotEmpty() ? (float) $chartPoints->max() : 1;
                    if ($chartPredictionPrice !== null) {
                        $chartMin = min($chartMin, $chartPredictionPrice);
                        $chartMax = max($chartMax, $chartPredictionPrice);
                    }
                    $chartRange = max($chartMax - $chartMin, 0.000001);
                    $chartScalePadding = $chartRange * 0.16;
                    $chartMin -= $chartScalePadding;
                    $chartMax += $chartScalePadding;
                    $chartRange = max($chartMax - $chartMin, 0.000001);
                    // Keep a visible vertical safety margin. Without it, extrema sit
                    // directly on the SVG edge and are clipped on wide desktop cards.
                    $latestChartY = $latestChartPrice !== null ? 100 - (($latestChartPrice - $chartMin) / $chartRange) * 76 : null;
                    $predictionY = $chartPredictionPrice !== null ? 100 - (($chartPredictionPrice - $chartMin) / $chartRange) * 76 : null;
                    $forecastStartX = 500.0;
                    $predictionSignalDate = filled($stock->prediction_time ?? null)
                        ? \Illuminate\Support\Carbon::parse($stock->prediction_time)->format('d.m.Y')
                        : null;
                    $signalTransitionX = is_numeric($stock->signal_transition_x) ? (float) $stock->signal_transition_x : null;
                    $signalTransitionDate = $stock->signal_transition_at
                        ? \Illuminate\Support\Carbon::parse($stock->signal_transition_at)->format('d.m.Y')
                        : null;
                    $chartPolyline = $chartPoints->count() > 1
                        ? $chartPoints->values()->map(fn (float $value, int $index): string => sprintf('%.1f,%.1f', $index * $forecastStartX / ($chartPoints->count() - 1), 100 - (($value - $chartMin) / $chartRange) * 76))->implode(' ')
                        : '';
                    $miniChartPoints = $chartPoints->take(-20)->values();
                    $miniChartMin = $miniChartPoints->isNotEmpty() ? (float) $miniChartPoints->min() : 0;
                    $miniChartRange = $miniChartPoints->isNotEmpty() ? max((float) $miniChartPoints->max() - $miniChartMin, .000001) : 1;
                    $miniChartPolyline = $miniChartPoints->count() > 1
                        ? $miniChartPoints->map(fn ($value, $index) => sprintf('%.1f,%.1f', $index * 88 / ($miniChartPoints->count() - 1), 22 - (((float) $value - $miniChartMin) / $miniChartRange) * 18))->implode(' ')
                        : '';
                    $miniChartPositive = $miniChartPoints->count() > 1 && (float) $miniChartPoints->last() >= (float) $miniChartPoints->first();
                @endphp
                @php
                    $countryFlag = \App\Support\CountryFlag::emoji($stock->country);
                    $businessSummary = app()->getLocale() === 'en'
                        ? ($stock->business_description_en ?: $stock->business_summary_en ?: $stock->business_description ?: $stock->business_summary)
                        : ($stock->business_description ?: $stock->business_summary);
                    $ranking = (int) ($stock->screening_rank ?? 0);
                    $rankClass = match ($ranking) {
                        1 => 'border-amber-300/75 shadow-[0_0_26px_rgba(251,191,36,.20)]',
                        2 => 'border-slate-200/65 shadow-[0_0_22px_rgba(226,232,240,.14)]',
                        3 => 'border-orange-700/70 shadow-[0_0_22px_rgba(194,65,12,.18)]',
                        default => 'border-orange-400/35',
                    };
                    $hasLongCompanyName = mb_strlen((string) ($stock->name ?: $stock->symbol)) > 45;
                    $forecastHorizons = ($stock->data_source ?? null) === 'serving' ? [10, 20, 40] : [5, 10, 15, 20];
                    $mobileForecasts = collect($forecastHorizons)->mapWithKeys(function (int $days) use ($stock): array {
                        $value = $stock->{"expected_return_{$days}d"} ?? null;
                        return [$days => is_numeric($value) ? (float) $value : null];
                    });
                    $primaryForecastHorizon = $mobileForecasts->has(20) ? 20 : (int) $mobileForecasts->keys()->last();
                    $primaryForecast = $mobileForecasts->get($primaryForecastHorizon);
                    $calibratedSignalQuality = data_get($stock->stock_signal_calibration, 'quality_percent');
                    $buySignalRating = ($stock->data_source ?? null) === 'serving' && filled($stock->serving_buy_rating ?? null)
                        ? [
                            'percent' => $rankingScorePercent,
                            'label' => (string) $stock->serving_buy_rating,
                            'weighted_return' => (float) ($primaryForecast ?? 0),
                            'quality' => is_numeric($calibratedSignalQuality) ? (float) $calibratedSignalQuality : $rankingScorePercent,
                        ]
                        : \App\Support\DirectionalSignalRating::calculate(
                            $mobileForecasts->all(),
                            is_numeric($calibratedSignalQuality) ? (float) $calibratedSignalQuality : $rankingScorePercent,
                        );
                    $timeAdjustedRating = \App\Support\TimeAdjustedSignalRating::calculate(
                        $mobileForecasts->all(),
                        $stock->prediction_time ?? null,
                        is_numeric($calibratedSignalQuality) ? (float) $calibratedSignalQuality : $rankingScorePercent,
                    );
                    $buySignalRating = array_merge($buySignalRating, $timeAdjustedRating);
                    $buySignalScorePercent = (float) $buySignalRating['percent'];
                    $buySignalScoreLabel = (string) $buySignalRating['label'];
                    $buySignalScoreColor = $qualityDonutColor($buySignalScorePercent);
                    $modelQualityBadge = match ((string) ($stock->model_quality_tier_code ?? '')) {
                        'top' => 'Top Quality',
                        'strong' => 'Quality',
                        'solid' => 'Solid',
                        'test' => 'Basic',
                        default => ($stock->model_quality_tier_name ?? __('Validiert')),
                    };
                    $riskClassBadge = match (true) {
                        $rankingRiskPercent === null => __('Nicht bewertet'),
                        $rankingRiskPercent <= 25 => __('Defensiv'),
                        $rankingRiskPercent <= 50 => __('Ausgewogen'),
                        $rankingRiskPercent <= 75 => __('Dynamisch'),
                        default => __('Spekulativ'),
                    };
                    [$riskProfileKey, $riskProfileLabel] = match (true) {
                        $rankingRiskPercent === null => [null, __('Nicht bewertet')],
                        $rankingRiskPercent <= 25 => ['defensive', __('Defensiv')],
                        $rankingRiskPercent <= 50 => ['balanced', __('Ausgewogen')],
                        default => ['offensive', __('Offensiv')],
                    };
                    $buySignalSectorStart = max(0, $buySignalScorePercent - 5);
                    $buySignalSectorEnd = max(1, $buySignalScorePercent);
                    $riskSectorStart = max(0, (float) ($rankingRiskPercent ?? 0) - 5);
                    $riskSectorEnd = max(1, (float) ($rankingRiskPercent ?? 0));
                    $signalStrength = \App\Support\SignalStrength::label($primaryForecast);
                    $priceChange = is_numeric($stock->price_change_percent ?? null) ? (float) $stock->price_change_percent : null;
                    $triggerModelName = trim((string) ($stock->trigger_model_name ?? ''));
                    $triggerModelShort = match ($triggerModelName) {
                        'Standard · Tabular + TCN' => 'Standard',
                        'Pure TCN' => 'TCN',
                        default => $triggerModelName,
                    };
                    $triggerHorizon = is_numeric($stock->trigger_model_horizon ?? null) ? (int) $stock->trigger_model_horizon : null;
                    $triggerRelease = trim((string) ($stock->trigger_model_release_id ?? ''));
                    $triggerDescription = implode(' · ', array_filter([
                        $triggerModelName,
                        $triggerHorizon ? $triggerHorizon.'T' : null,
                        $triggerRelease ? 'Release '.$triggerRelease : null,
                    ]));
                @endphp
                <article
                    data-ranking="{{ $ranking }}"
                    data-forecast-10="{{ is_numeric($stock->expected_return_10d ?? null) ? (float) $stock->expected_return_10d : '' }}"
                    data-forecast-20="{{ is_numeric($stock->expected_return_20d ?? null) ? (float) $stock->expected_return_20d : '' }}"
                    data-forecast-40="{{ is_numeric($stock->expected_return_40d ?? null) ? (float) $stock->expected_return_40d : '' }}"
                    data-indicators="{{ is_numeric($stock->indicator_strength_percent ?? null) ? (float) $stock->indicator_strength_percent : '' }}"
                    data-assessment-quality="{{ number_format(is_numeric($calibratedSignalQuality) ? (float) $calibratedSignalQuality : $rankingScorePercent, 2, '.', '') }}"
                    data-assessment-cost="{{ number_format(max(0, (float) config('aktienki.signals.round_trip_cost_percent', .5)), 3, '.', '') }}"
                    data-assessment-minimum-return="{{ number_format(max(0, (float) config('aktienki.signals.minimum_net_return_percent', 1)), 3, '.', '') }}"
                    data-prediction-date="{{ filled($stock->prediction_time ?? null) ? \Illuminate\Support\Carbon::parse($stock->prediction_time)->toDateString() : '' }}"
                    class="screener-stock-card {{ $hasLongCompanyName ? 'screener-stock-card-long-name' : '' }} ak-card ak-dashboard-card relative overflow-hidden p-3 {{ $rankClass }}"
                    x-data="{ signalInfoOpen: false, mobileExpanded: false }"
                >
                    <a href="{{ route('stocks.show', array_filter(['symbol' => $stock->symbol, 'prediction' => ($stock->data_source ?? null) === 'serving' ? null : $stock->id, 'return_to' => request()->getRequestUri()], fn ($value) => $value !== null)) }}" class="screener-desktop-summary hidden w-full items-center gap-4 text-left md:grid" aria-label="{{ __('Details zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}">
                        <span class="screener-desktop-stock">
                            <b>{{ $ranking > 0 ? '#'.$ranking : '—' }}</b><i>{{ $countryFlag }}</i><i class="screener-row-sector-symbol" title="{{ $stock->sector ?: __('Sektor nicht hinterlegt') }}">{{ $sectorSymbolFor($stock->sector) }}</i>
                            @if($riskProfileKey)
                                <span class="screener-row-profile-badge" style="{{ $riskProfileKey === 'defensive' ? '--profile-color:#6ee7b7;--profile-border:rgba(52,211,153,.34);--profile-bg:rgba(52,211,153,.06)' : ($riskProfileKey === 'balanced' ? '--profile-color:#fcd34d;--profile-border:rgba(251,191,36,.34);--profile-bg:rgba(251,191,36,.06)' : '--profile-color:#fda4af;--profile-border:rgba(251,113,133,.34);--profile-bg:rgba(251,113,133,.06)') }}" title="{{ __('Profil') }}: {{ $riskProfileLabel }}" aria-label="{{ __('Profil') }}: {{ $riskProfileLabel }}">@if($riskProfileKey === 'defensive')<x-heroicon-o-shield-check />@elseif($riskProfileKey === 'balanced')<x-heroicon-o-scale />@else<x-heroicon-o-bolt />@endif</span>
                            @endif
                            <span><strong>{{ $stock->name ?: $stock->symbol }}</strong><small>{{ $stock->symbol }} · {{ $stock->sector ?: __('Sektor nicht hinterlegt') }}</small></span>
                        </span>
                        <span class="screener-desktop-price">
                            <span><strong
                                @if($realtimeQuotes ?? false)
                                    data-live-symbol="{{ $stock->symbol }}"
                                    data-live-decimals="2"
                                    data-live-currency="{{ $displayCurrencySymbol }}"
                                    data-live-base-price="{{ (float) $stock->current_price }}"
                                    data-screener-live-price="{{ $stock->symbol }}"
                                @endif
                            >{{ is_numeric($stock->current_price) ? number_format((float) $stock->current_price, 2, ',', '.') : '—' }} <em>{{ $displayCurrencySymbol }}</em></strong>@if($priceChange !== null)<b class="{{ $priceChange >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ ($priceChange > 0 ? '+' : '').number_format($priceChange, 2, ',', '.').' %' }}</b>@endif @if($realtimeQuotes ?? false)<i class="screener-live-status" data-screener-live-status="{{ $stock->symbol }}" hidden>{{ __('Live') }}</i>@endif</span>
                            <svg class="screener-price-sparkline {{ $miniChartPolyline === '' ? 'invisible' : '' }}" viewBox="0 0 88 26" preserveAspectRatio="none" role="img" aria-label="{{ __('Kursverlauf der letzten 20 Handelstage') }}" @if($miniChartPolyline === '') data-screener-minichart-url="{{ route('stocks.chart-data', ['symbol' => $stock->symbol]) }}" @endif><polyline points="{{ $miniChartPolyline }}" fill="none" stroke="{{ $miniChartPositive ? '#34d399' : '#fb7185' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" /></svg>
                            <small>{{ __('Kurs') }}</small>
                        </span>
                        <span class="screener-desktop-signal" data-signal="{{ strtolower($signal) }}"><strong>{{ $signalLabel }}</strong><small>{{ __('Signal') }}</small></span>
                        <span class="screener-trigger-model" data-model="{{ strtolower($triggerModelShort) }}" title="{{ $triggerDescription }}"><strong>{{ $triggerModelShort ?: '—' }}</strong></span>
                        <span class="screener-desktop-grade screener-desktop-scale-grade" data-screener-assessment>
                            <strong data-screener-assessment-label>{{ __('Bewertung') }} · {{ $buySignalScoreLabel }}</strong>
                            <i class="screener-desktop-scale signal" data-screener-assessment-scale style="--position:{{ number_format($buySignalScorePercent, 2, '.', '') }}%;--marker:{{ $buySignalScoreColor }}" role="meter" aria-label="{{ __('Signalqualität') }} {{ $buySignalScoreLabel }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($buySignalScorePercent, 1, '.', '') }}"><em></em></i>
                        </span>
                        <span class="screener-desktop-grade screener-desktop-scale-grade">
                            <strong>{{ __('Risiko') }} · {{ \App\Support\QualityGrade::risk($rankingRiskPercent) ?? '—' }}</strong>
                            <i class="screener-desktop-scale risk" style="--position:{{ number_format(100 - ($rankingRiskPercent ?? 0), 2, '.', '') }}%;--marker:{{ $riskDonutColor }}" role="meter" aria-label="{{ __('Risiko') }} {{ \App\Support\QualityGrade::risk($rankingRiskPercent) ?? '—' }}" aria-valuemin="0" aria-valuemax="100" @if($rankingRiskPercent !== null) aria-valuenow="{{ number_format(100 - $rankingRiskPercent, 1, '.', '') }}" @endif><em @if($rankingRiskPercent === null) hidden @endif></em></i>
                        </span>
                        <span class="screener-desktop-forecasts">
                            <span class="screener-desktop-forecast-label"><small>{{ __('Prognose') }}</small><strong>{{ __('Mögliche Rendite') }}</strong></span>
                            @foreach($mobileForecasts as $days => $forecast)
                                <i data-assessment-horizon="{{ $days }}" data-assessment-return="{{ is_numeric($forecast) ? number_format((float) $forecast, 6, '.', '') : '' }}" class="{{ $triggerHorizon === (int) $days ? 'is-trigger-horizon ' : '' }}{{ is_numeric($forecast) && (float) $forecast < 1.0 ? 'is-time-downgraded ' : '' }}{{ is_numeric($forecast) && (float) $forecast < 0 ? 'is-negative-forecast' : '' }}" title="{{ $triggerHorizon === (int) $days ? __('Signalauslösender Horizont').' · '.$days.'T' : '' }}"><small>{{ $days }}T</small><strong
                                    class="{{ $forecast === null ? 'text-slate-400' : ($forecast >= 0 ? 'text-emerald-400' : 'text-rose-400') }}"
                                    @if(($realtimeQuotes ?? false) && is_numeric($stock->{"predicted_price_{$days}d"} ?? null))
                                        data-screener-live-forecast="{{ $stock->symbol }}"
                                        data-horizon="{{ $days }}"
                                        data-target-price="{{ (float) $stock->{"predicted_price_{$days}d"} }}"
                                    @endif
                                >{{ $forecast === null ? '—' : (($forecast > 0 ? '+' : '').number_format($forecast, 1, ',', '.').' %') }}</strong></i>
                            @endforeach
                        </span>
                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-cyan-300" />
                    </a>
                    <button type="button" class="screener-mobile-summary screener-mobile-summary-v2 md:hidden" @click="mobileExpanded = ! mobileExpanded; if (mobileExpanded) $nextTick(async () => { await window.loadAktienKiCharts?.(); window.initializeServingCharts?.() })" :aria-expanded="mobileExpanded.toString()">
                        <span class="sms-v2-head"><b>{{ $ranking > 0 ? '#'.$ranking : '—' }}</b><i>{{ $countryFlag }}</i><span><strong>{{ $stock->name ?: $stock->symbol }}</strong><small>@if($riskProfileKey)<span class="screener-mobile-profile-badge" style="{{ $riskProfileKey === 'defensive' ? '--profile-color:#6ee7b7;--profile-border:rgba(52,211,153,.34);--profile-bg:rgba(52,211,153,.06)' : ($riskProfileKey === 'balanced' ? '--profile-color:#fcd34d;--profile-border:rgba(251,191,36,.34);--profile-bg:rgba(251,191,36,.06)' : '--profile-color:#fda4af;--profile-border:rgba(251,113,133,.34);--profile-bg:rgba(251,113,133,.06)') }}" title="{{ __('Profil') }}: {{ $riskProfileLabel }}">@if($riskProfileKey === 'defensive')<x-heroicon-o-shield-check />@elseif($riskProfileKey === 'balanced')<x-heroicon-o-scale />@else<x-heroicon-o-bolt />@endif</span>@endif{{ $stock->symbol }} · {{ $stock->sector ?: '—' }}</small></span><em>{{ is_numeric($stock->current_price) ? number_format((float)$stock->current_price,2,',','.') : '—' }} {{ $displayCurrencySymbol }}</em><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300 transition" x-bind:class="mobileExpanded && 'rotate-180'" /></span>
                        <span class="sms-v2-forecast"><strong data-signal="{{ strtolower($signal) }}">{{ $signalLabel }}</strong>@foreach($mobileForecasts as $days=>$forecast)<i><small>{{ $days }}T</small><b class="{{ $forecast===null?'text-slate-400':($forecast>=0?'text-emerald-400':'text-rose-400') }}">{{ $forecast===null?'—':(($forecast>0?'+':'').number_format($forecast,1,',','.').' %') }}</b></i>@endforeach</span>
                        <span class="sms-v2-scales"><i><small data-screener-assessment-label>{{ __('Bewertung') }} · {{ $buySignalScoreLabel }}</small><span class="sms-v2-scale signal" data-screener-assessment-scale style="--position:{{ $buySignalScorePercent }}%;--marker:{{ $buySignalScoreColor }}"><em></em></span></i><i><small>{{ __('Risiko') }} · {{ \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—' }}</small><span class="sms-v2-scale risk" style="--position:{{ 100 - ($rankingRiskPercent ?? 0) }}%;--marker:{{ $riskDonutColor }}"><em></em></span></i></span>
                    </button>
                    <template x-if="mobileExpanded">
                    <div class="screener-mobile-details screener-desktop-details grid h-full min-h-0 gap-2 md:grid-cols-2 xl:grid-cols-6" x-bind:class="{ 'is-mobile-open': mobileExpanded }">
                        <div class="screener-chart-panel relative h-full min-h-0 rounded-xl border border-transparent p-3 pt-5 xl:col-span-2">
                            <div class="grid gap-3 md:grid-cols-[.7fr_1.3fr]">
                                <div class="screener-expanded-identity">
                                    <p class="screener-border-title text-amber-300">{{ __('Globales Ranking') }} @if($stock->screening_rank)<strong>#{{ $stock->screening_rank }}</strong>@endif</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <p class="text-base font-black">{{ $stock->name ?: $stock->symbol }}</p>
                                    </div>
                                    <p class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ $stock->symbol }}</p>
                                    <p class="mt-2 text-sm">{{ $countryFlag }} {{ $stock->country ?: '—' }}</p>
                                    <p class="mt-1 text-[10px] font-bold text-[var(--ak-muted)]">{{ $stock->exchange_name ?: $stock->exchange_code ?: __('Index nicht hinterlegt') }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-[10px] font-bold text-cyan-300">
                                        <x-heroicon-o-squares-2x2 class="h-3.5 w-3.5 shrink-0" />
                                        <span>{{ $stock->sector ?: __('Sektor nicht hinterlegt') }}</span>
                                    </p>
                                    <span class="relative z-20 mt-3 inline-flex items-center gap-1.5">
                                        <span class="inline-flex w-28 justify-center rounded-lg border px-2.5 py-1 text-[10px] font-black tracking-[.08em] {{ $tone }}">{{ $signalLabel }}</span>
                                        @if($stock->external_review_ranking_downgraded ?? false)
                                            <span class="inline-flex rounded-md border border-rose-400/35 bg-rose-400/10 px-2 py-1 text-[8px] font-black uppercase tracking-wide text-rose-400" title="{{ __('Externer KI-Widerspruch: Ranking um einen Punkt reduziert') }}">KI −1</span>
                                        @endif
                                        <button type="button" @click.prevent.stop="signalInfoOpen = true" class="screener-signal-info-button inline-grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/35 bg-cyan-400/[.08] text-cyan-300 transition hover:bg-cyan-400/[.16]" aria-label="{{ __('Signalbegründung anzeigen') }}">
                                            <x-heroicon-o-information-circle class="h-4 w-4" />
                                        </button>
                                    </span>
                                    <p class="mt-1 text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ __('Persönliches Profil') }}: {{ $stock->personal_risk_profile }}</p>
                                    <template x-teleport="body">
                                        <div x-cloak x-show="signalInfoOpen" x-transition.opacity @keydown.escape.window="signalInfoOpen = false" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="{{ __('Warum :signal?', ['signal' => $signal]) }}">
                                            <button type="button" class="absolute inset-0 cursor-default" @click="signalInfoOpen = false" aria-label="{{ __('Schließen') }}"></button>
                                            <section x-show="signalInfoOpen" x-transition.scale.origin.center class="relative z-10 w-full max-w-lg rounded-2xl border border-cyan-300/30 bg-[var(--ak-card)] p-5 text-left shadow-2xl">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Warum dieser Signalstatus?') }}</p>
                                                        <h3 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ $stock->name ?: $stock->symbol }} · {{ $signalLabel }}</h3>
                                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('Profil') }}: {{ $stock->personal_risk_profile }}</p>
                                                    </div>
                                                    <button type="button" @click="signalInfoOpen = false" class="inline-grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)] transition hover:text-[var(--ak-text)]" aria-label="{{ __('Schließen') }}">
                                                        <x-heroicon-o-x-mark class="h-5 w-5" />
                                                    </button>
                                                </div>
                                                <div class="mt-4 rounded-xl border border-cyan-300/15 bg-cyan-400/[.06] p-4">
                                                    <p class="text-sm font-bold leading-5 text-[var(--ak-text)]">{{ $stock->personal_signal_breakdown['summary'] }}</p>
                                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <p class="text-[10px] font-black uppercase tracking-[.12em] text-emerald-400">{{ __('Dafür spricht') }}</p>
                                                            <ul class="mt-2 space-y-2 text-xs leading-5 text-[var(--ak-text)]">
                                                                @forelse($stock->personal_signal_breakdown['pros'] as $point)
                                                                    <li class="flex gap-2"><span class="text-emerald-400">●</span><span>{{ $point }}</span></li>
                                                                @empty
                                                                    <li class="text-[var(--ak-muted)]">{{ __('Aktuell kein Faktor für ein stärkeres Signal.') }}</li>
                                                                @endforelse
                                                            </ul>
                                                        </div>
                                                        <div>
                                                            <p class="text-[10px] font-black uppercase tracking-[.12em] text-rose-400">{{ __('Dagegen spricht') }}</p>
                                                            <ul class="mt-2 space-y-2 text-xs leading-5 text-[var(--ak-text)]">
                                                                @forelse($stock->personal_signal_breakdown['cons'] as $point)
                                                                    <li class="flex gap-2"><span class="text-rose-400">●</span><span>{{ $point }}</span></li>
                                                                @empty
                                                                    <li class="text-[var(--ak-muted)]">{{ __('Keine wesentlichen Gegenargumente erkannt.') }}</li>
                                                                @endforelse
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="mt-3 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Das Signal berücksichtigt Quality Gate, erwartete Nettorendite, Nutzerprofil sowie die Volatilität im Vergleich zum jeweiligen Sektor.') }}</p>
                                            </section>
                                        </div>
                                    </template>
                                </div>
                                <div class="screener-expanded-price">
                                <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Kurs') }}</p>
                                <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <p class="text-2xl font-black">{{ is_numeric($stock->current_price) ? number_format((float) $stock->current_price, 2, ',', '.') : '—' }} <span class="text-sm text-[var(--ak-muted)]">{{ $displayCurrencySymbol }}</span></p>
                                    @if($showOriginalPrice)
                                        <p class="whitespace-nowrap text-[10px] font-bold text-[var(--ak-muted)]" title="{{ __('Originalkurs') }} · {{ $stock->original_currency }}">
                                            {{ __('Originalwährung') }} ({{ $originalCurrencyName }}): {{ number_format((float) $stock->original_price, 2, ',', '.') }} {{ $originalCurrencySymbol }}
                                        </p>
                                    @endif
                                </div>
                                <p class="mt-3 text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Performance · Prognosehorizonte') }}</p>
                                <div class="screener-performance-horizons mt-1.5 grid grid-cols-2 gap-1 {{ $mobileForecasts->count() === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }}">
                                    @foreach($mobileForecasts as $days => $forecast)
                                        @php
                                            $forecastBadgeTone = $forecast === null
                                                ? 'border-slate-400/20 bg-slate-400/[.06] text-[var(--ak-muted)]'
                                                : ($forecast > 0
                                                    ? 'border-emerald-400/35 bg-emerald-400/[.10] text-emerald-400'
                                                    : ($forecast < 0
                                                        ? 'border-rose-400/35 bg-rose-400/[.10] text-rose-400'
                                                        : 'border-amber-400/35 bg-amber-400/[.10] text-amber-400'));
                                        @endphp
                                        <span class="flex min-w-0 flex-col items-center justify-center rounded-md border px-1 py-1 {{ $forecastBadgeTone }}" title="{{ __('Mögliche Rendite in :days Tagen', ['days' => $days]) }}">
                                            <small class="text-[7px] font-black uppercase tracking-wide opacity-75">{{ $days }}T</small>
                                            <b class="max-w-full truncate text-[9px] font-black tabular-nums">{{ $forecast === null ? '—' : sprintf('%+.1f%%', $forecast) }}</b>
                                        </span>
                                    @endforeach
                                </div>
                                </div>
                            <div class="screener-expanded-chart md:col-span-2">
                                <div class="mb-3 md:hidden">
                                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Mögliche Renditen') }}</p>
                                    <div class="mt-2 grid {{ $mobileForecasts->count() === 3 ? 'grid-cols-3' : 'grid-cols-4' }} gap-1.5">
                                        @foreach($mobileForecasts as $days => $forecast)
                                            <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.05] px-1.5 py-2 text-center">
                                                <p class="text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ $days }} {{ __('Tage') }}</p>
                                                <p class="mt-1 text-[11px] font-black {{ $forecast === null ? 'text-slate-400' : ($forecast > 0 ? 'text-emerald-400' : ($forecast < 0 ? 'text-rose-400' : 'text-slate-400')) }}">
                                                    {{ $forecast === null ? '—' : (($forecast > 0 ? '↗ +' : ($forecast < 0 ? '↘ ' : '→ ')).number_format($forecast, 2, ',', '.').' %') }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div data-screener-chart data-chart-url="{{ route('screener.chart', ['instrument' => $stock->instrument_id, '_v' => '20260902-2']) }}" class="min-h-[150px]" aria-live="polite">
                                    <div class="flex h-[116px] items-center justify-center text-xs italic text-[var(--ak-muted)]">{{ __('Chart wird aus dem Cache geladen …') }}</div>
                                </div>
                            </div>
                            <div class="screener-card-actions absolute right-3 top-2 z-30 flex gap-2">
                                <a href="{{ route('setup.labels.index') }}" title="{{ __('Labels') }}" aria-label="{{ __('Labels verwalten') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition {{ $stock->has_matching_label ? 'border-cyan-400/30 bg-cyan-400/[.08] text-cyan-300 hover:bg-cyan-400/[.16]' : 'border-slate-500/15 bg-slate-500/[.04] text-slate-500/40 hover:text-cyan-300' }}">
                                    <x-heroicon-o-tag class="h-4 w-4" />
                                </a>
                                <a href="{{ route('setup.saved-filters.index') }}" title="{{ __('Strategielabels') }}" aria-label="{{ __('Strategielabels verwalten') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition {{ $stock->has_matching_strategy ? 'border-teal-400/30 bg-teal-400/[.08] text-teal-300 hover:bg-teal-400/[.16]' : 'border-slate-500/15 bg-slate-500/[.04] text-slate-500/40 hover:text-teal-300' }}">
                                    <x-heroicon-o-bookmark-square class="h-4 w-4" />
                                </a>
                                <details class="screener-watchlist-picker group relative">
                                    <summary title="{{ $isOnWatchlist ? __('In Watchlist') : __('Watchlist') }}" aria-label="{{ __('Watchlist') }}" class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-xl border border-amber-400/30 bg-amber-400/[.08] text-amber-300 {{ $isOnWatchlist ? 'shadow-[0_0_12px_rgba(251,191,36,.30)]' : '' }}">
                                        @if($isOnWatchlist)<x-heroicon-s-star class="h-4 w-4" />@else<x-heroicon-o-star class="h-4 w-4" />@endif
                                    </summary>
                                    <div class="screener-watchlist-menu absolute right-0 top-10 z-40 min-w-52 space-y-1 rounded-xl border border-amber-400/25 bg-[var(--ak-card)] p-2 shadow-2xl">
                                        <div class="screener-watchlist-menu-header">
                                            <strong>{{ __('Watchlist auswählen') }}</strong>
                                            <button type="button" onclick="event.preventDefault();event.stopPropagation();this.closest('details').removeAttribute('open')">{{ __('Abbrechen') }}</button>
                                        </div>
                                        @forelse($userWatchlists as $watchlist)
                                            <form method="POST" action="{{ route('watchlists.items.toggle', ['watchlist' => $watchlist->id, 'instrument' => $stock->instrument_id]) }}">
                                                @csrf
                                                @if (($stock->data_source ?? null) !== 'serving')<input type="hidden" name="prediction_id" value="{{ $stock->id }}">@endif
                                                <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-[10px] font-bold text-[var(--ak-text)] hover:bg-amber-400/10">
                                                    <span>{{ $watchlist->name }}</span>
                                                    <span class="text-amber-300">{{ $stockWatchlistIds->contains((int) $watchlist->id) ? '✓' : '+' }}</span>
                                                </button>
                                            </form>
                                        @empty
                                            <a href="{{ route('watchlists.index') }}" class="block rounded-lg px-2.5 py-2 text-[10px] font-bold text-amber-300">{{ __('Watchlist erstellen') }}</a>
                                        @endforelse
                                    </div>
                                </details>
                                <x-paper-depot-buy :portfolios="$paperPortfolios" :instrument-id="$stock->instrument_id" :instrument-name="$stock->name ?: $stock->symbol" :currency="$stock->currency" :price="$stock->current_price" :score="$rankingScorePercent" :active="$isInPaperPortfolio" compact />
                                <a href="{{ route('news.index', ['q' => $stock->symbol, 'days' => 2]) }}" title="{{ $recentNewsLabel }}{{ $recentNews ? ' · '.($recentNews->news_count ?? 1).' · '.$recentNews->headline : '' }}" aria-label="{{ $recentNewsLabel }}" class="relative inline-flex h-8 w-8 items-center justify-center rounded-xl border transition hover:brightness-110 {{ $recentNewsTone }}">
                                    <x-heroicon-o-newspaper class="h-4 w-4" />
                                    @if($recentNews && ($recentNews->news_count ?? 1) > 1)<span class="absolute -right-1.5 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--ak-card-strong)] px-1 text-[7px] font-black text-current">{{ $recentNews->news_count }}</span>@endif
                                </a>
                                @if(Route::has('certificates.index') && $certificateInstrumentIds->contains((int) $stock->instrument_id))
                                    <a href="{{ route('certificates.index', ['underlying' => $stock->instrument_id]) }}" title="{{ __('Zertifikate zu :stock', ['stock' => $stock->name ?: $stock->symbol]) }}" aria-label="{{ __('Zertifikate zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-amber-400/30 bg-amber-400/[.08] text-amber-300 transition hover:bg-amber-400/[.16]">
                                        <x-heroicon-o-document-chart-bar class="h-4 w-4" />
                                    </a>
                                @else
                                    <span title="{{ __('Keine Zertifikate für :stock verfügbar', ['stock' => $stock->name ?: $stock->symbol]) }}" aria-label="{{ __('Keine Zertifikate verfügbar') }}" aria-disabled="true" class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-xl border border-slate-500/15 bg-slate-500/[.04] text-slate-500/40">
                                        <x-heroicon-o-document-chart-bar class="h-4 w-4" />
                                    </span>
                                @endif
                                <a href="{{ route('stocks.show', array_filter(['symbol' => $stock->symbol, 'prediction' => ($stock->data_source ?? null) === 'serving' ? null : $stock->id, 'return_to' => request()->getRequestUri()], fn ($value) => $value !== null)) }}" title="{{ __('Zur Aktiendetailseite') }}" aria-label="{{ __('Details zu :stock anzeigen', ['stock' => $stock->name ?: $stock->symbol]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-violet-400/30 bg-violet-400/[.08] text-violet-300 transition hover:bg-violet-400/[.16]">
                                    <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                </a>
                            </div>
                            </div>
                        </div>
                        <div class="screener-desktop-analysis grid h-full min-h-0 gap-2 sm:grid-cols-2 xl:col-span-2">
                            <div class="screener-transparent-panel relative hidden rounded-xl border p-3 sm:col-span-2 md:block">
                            <div class="screener-ranking-donuts screener-stock-primary-donuts">
                                <div class="screener-metric-wrap screener-metric-wrap-primary" title="{{ __('Gewichtete Prognose') }}: {{ number_format((float) $buySignalRating['weighted_return'], 2, ',', '.') }} % · {{ __('Modellqualität') }}: {{ number_format((float) $buySignalRating['quality'], 0, ',', '.') }}/100">
                                    @php
                                        $buySignalSectorCenter = max(0, min(100, (float) $buySignalScorePercent));
                                        $buySignalSectorStart = max(0, $buySignalSectorCenter - 3.5);
                                        $buySignalSectorEnd = min(100, $buySignalSectorCenter + 3.5);
                                    @endphp
                                    <div class="screener-metric-donut screener-buy-signal-donut" style="--donut-value: {{ number_format($buySignalScorePercent, 2, '.', '') }}%; --donut-color: {{ $buySignalScoreColor }}; --active-sector-start: {{ $buySignalSectorStart }}%; --active-sector-end: {{ $buySignalSectorEnd }}%; --active-sector-color: {{ $buySignalScoreColor }}" role="meter" aria-label="{{ __('Signalqualität') }} {{ $buySignalScoreLabel }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($buySignalScorePercent, 1, '.', '') }}"><span>{{ $buySignalScoreLabel }}</span></div>
                                    <small>{{ __('Signalqualität') }}</small>
                                </div>
                                <div class="screener-metric-wrap" title="{{ __('Rohwert') }}: {{ $rankingRiskPercent !== null ? number_format($rankingRiskPercent, 0, ',', '.').' %' : '—' }}">
                                    <div class="screener-metric-donut screener-risk-donut" style="--donut-value: {{ number_format($rankingRiskPercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $riskDonutColor }}; --active-sector-start: {{ $riskSectorStart }}%; --active-sector-end: {{ $riskSectorEnd }}%; --active-sector-color: {{ $riskDonutColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" @if($rankingRiskPercent !== null) aria-valuenow="{{ number_format($rankingRiskPercent, 1, '.', '') }}" @endif><span>{{ \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—' }}</span></div>
                                    <small>{{ __('Risiko') }}</small>
                                </div>
                            </div>
                            <div class="screener-donut-spacer"></div>
                            </div>
                            <div class="screener-fundamental-strip screener-transparent-panel grid grid-cols-3 overflow-hidden rounded-xl border sm:col-span-2">
                                <div class="px-3 py-2">
                                    <small>{{ __('Dividende') }}</small>
                                    <b>{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—' }}</b>
                                </div>
                                <div class="border-x border-cyan-300/15 px-3 py-2">
                                    <small>{{ __('KGV') }}</small>
                                    <b>{{ $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 1, ',', '.') : '—' }}</b>
                                </div>
                                <div class="px-3 py-2">
                                    <small>{{ __('Sektorplatz') }}</small>
                                    <b>{{ is_numeric($stock->sector_rank ?? null) ? '#'.number_format((float) $stock->sector_rank, 0, ',', '.') : '—' }}</b>
                                </div>
                            </div>
                            @php
                                $percentiles = $stock->global_percentiles ?? [];
                                $indexPercentiles = $stock->index_percentiles ?? [];
                                $sectorPercentiles = $stock->sector_percentiles ?? [];
                                $percentileRows = [
                                    [__('KI-Score'), $rankingScorePercent, 'score', '/100'],
                                    [__('Prognose 20T'), $return, 'return_20d', '%'],
                                    [__('Konfidenz'), $rankingConfidencePercent, 'confidence', '%'],
                                    [__('Profitfaktor'), $rankingProfitFactorAvailable ? $rankingProfitFactor : null, 'profit_factor', ''],
                                    [__('Hit-Rate'), $rankingHitRateAvailable ? $rankingHitRatePercent : null, 'hit_rate', '%'],
                                    [__('Risiko'), $rankingRiskPercent, 'risk', '%'],
                                    [__('Volatilität'), is_numeric($stock->annualized_volatility) ? (float) $stock->annualized_volatility * 100 : null, 'volatility', '%'],
                                    [__('Indikatoren'), $stock->indicator_strength_percent, 'indicators', '%'],
                                    [__('KGV'), $priceEarningsRatio, 'pe_ratio', ''],
                                    [__('Dividendenrendite'), $dividendYield, 'dividend_yield', '%'],
                                ];
                            @endphp
                            <div class="screener-percentile-profile screener-transparent-panel h-full min-h-0 overflow-hidden rounded-xl border sm:col-span-2">
                                <div class="screener-company-slider hidden h-full xl:block" x-data="{ companySlide: 0, slideCount: 3 }">
                                    <div class="flex items-center justify-between border-b border-cyan-300/15 px-3 py-2">
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300" x-text="companySlide === 0 ? @js(__('Unternehmen')) : (companySlide === 2 ? @js(__('Fundamentaldaten')) : @js(__('Qualitätsprofil')))"></p>
                                            <p class="mt-0.5 text-[8px] font-bold text-[var(--ak-muted)]" x-show="companySlide === 1">{{ __('Aktuelle Modell- und Risikobewertung') }}</p>
                                            <p class="mt-0.5 text-[8px] font-bold text-[var(--ak-muted)]" x-show="companySlide === 2">{{ __('Die wichtigsten Unternehmenskennzahlen') }}</p>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="companySlide = (companySlide - 1 + slideCount) % slideCount" class="grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/20 text-cyan-300 transition hover:bg-cyan-400/10" aria-label="{{ __('Vorherige Ansicht') }}"><x-heroicon-o-chevron-left class="h-3.5 w-3.5" /></button>
                                            <template x-for="dot in slideCount" :key="dot"><button type="button" @click="companySlide = dot - 1" class="h-1.5 w-1.5 rounded-full transition" :class="companySlide === dot - 1 ? 'bg-cyan-300 scale-125' : 'bg-slate-600'" :aria-label="`Slide ${dot}`"></button></template>
                                            <button type="button" @click="companySlide = (companySlide + 1) % slideCount" class="grid h-7 w-7 place-items-center rounded-lg border border-cyan-300/20 text-cyan-300 transition hover:bg-cyan-400/10" aria-label="{{ __('Nächste Ansicht') }}"><x-heroicon-o-chevron-right class="h-3.5 w-3.5" /></button>
                                        </div>
                                    </div>
                                    <div class="h-[calc(100%-3rem)] p-3">
                                        <div x-show="companySlide === 0" x-transition.opacity class="h-full">
                                            <p class="line-clamp-[9] text-[11px] leading-[1.65] text-[var(--ak-muted)]">{{ $businessSummary ?: __('Für dieses Unternehmen ist noch keine Beschreibung verfügbar.') }}</p>
                                        </div>
                                        <div x-cloak x-show="companySlide === 2" x-transition.opacity class="screener-fundamentals-slide grid h-full grid-cols-2 content-center gap-2.5">
                                            @foreach ([
                                                [__('KGV'), $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 1, ',', '.') : '—'],
                                                [__('Dividendenrendite'), $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—'],
                                                [__('Sektorplatz'), is_numeric($stock->sector_rank ?? null) ? '#'.number_format((float) $stock->sector_rank, 0, ',', '.') : '—'],
                                                [__('Prognose 20T'), $return !== null ? (($return > 0 ? '+' : '').number_format($return, 1, ',', '.').' %') : '—'],
                                            ] as [$fundamentalLabel, $fundamentalValue])
                                                <div>
                                                    <small>{{ $fundamentalLabel }}</small>
                                                    <b>{{ $fundamentalValue }}</b>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div x-cloak x-show="companySlide === 1" x-transition.opacity class="screener-quality-slide h-full">
                                            <div class="screener-quality-table-wrap">
                                                <table class="screener-quality-table">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Kennzahl') }}</th>
                                                            <th>{{ __('Bewertung') }}</th>
                                                            <th>{{ __('Wert') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ([
                                                            [__('Signalqualität'), $rankingScoreColor, \App\Support\QualityGrade::fromPercent($rankingScorePercent) ?? '—', number_format($rankingScorePercent, 0, ',', '.').'/100'],
                                                            [__('Konfidenz'), $rankingConfidenceColor, \App\Support\QualityGrade::fromPercent($rankingConfidencePercent) ?? '—', number_format($rankingConfidencePercent, 0, ',', '.').' %'],
                                                            [__('Hit-Rate'), $rankingHitRateColor, $rankingHitRateAvailable ? \App\Support\QualityGrade::fromPercent($rankingHitRatePercent) : '—', $rankingHitRateAvailable ? number_format($rankingHitRatePercent, 1, ',', '.').' %' : '—'],
                                                            [__('Profitfaktor'), $rankingProfitFactorColor, $rankingProfitFactorAvailable ? \App\Support\QualityGrade::fromPercent($rankingProfitFactorPercent) : '—', $rankingProfitFactorAvailable ? number_format($rankingProfitFactor, 2, ',', '.') : '—'],
                                                            [__('Stabilität'), $rankingStabilityColor, $rankingStabilityAvailable ? \App\Support\QualityGrade::fromPercent($rankingStabilityPercent) : '—', $rankingStabilityAvailable ? number_format($rankingStabilityPercent, 0, ',', '.').' %' : '—'],
                                                            [__('Risiko'), $riskDonutColor, \App\Support\QualityGrade::riskLevel($rankingRiskPercent) ?? '—', $rankingRiskPercent !== null ? number_format($rankingRiskPercent, 0, ',', '.').' %' : '—'],
                                                        ] as [$qualityTableLabel, $qualityTableColor, $qualityTableGrade, $qualityTableValue])
                                                            <tr style="--quality-row-color: {{ $qualityTableColor }}">
                                                                <td><span class="screener-quality-table-dot" aria-hidden="true"></span>{{ $qualityTableLabel }}</td>
                                                                <td><span class="screener-quality-table-grade">{{ $qualityTableGrade }}</span></td>
                                                                <td>{{ $qualityTableValue }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @if($canViewModelOverview ?? false)
                                                <a href="{{ route('stocks.models', ['symbol' => $stock->symbol]) }}" class="screener-model-overview-cta" aria-label="{{ __('Detaillierte Modell- und Backtestanalyse für :stock öffnen', ['stock' => $stock->name ?: $stock->symbol]) }}">
                                                    <span><small>{{ __('Du möchtest mehr erfahren?') }}</small><strong>{{ __('Modell- und Backtestanalyse öffnen') }}</strong></span>
                                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                                </a>
                                            @else
                                                <a href="{{ route('pricing') }}" class="screener-model-overview-cta is-locked" aria-label="{{ __('Modellübersicht im Pro-Tarif ansehen') }}">
                                                    <span><small>{{ __('Du möchtest mehr erfahren?') }}</small><strong>{{ __('Modellanalyse mit Pro öffnen') }}</strong></span>
                                                    <x-heroicon-o-lock-closed class="h-4 w-4" />
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="border-t border-cyan-300/15 px-3 py-2.5">
                                    <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-start">
                                        <details class="group min-w-0">
                                            <summary class="cursor-pointer list-none">
                                                <span class="flex items-center justify-between gap-2"><span class="text-[8px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Unternehmen') }}</span>@if($businessSummary)<span class="text-[8px] font-black text-cyan-300/70 group-open:hidden">{{ __('Mehr') }} ↓</span>@endif</span>
                                                <span class="mt-1 block max-h-16 overflow-hidden text-[10px] leading-4 text-[var(--ak-muted)] group-open:hidden">{{ $businessSummary ?: __('Für dieses Unternehmen ist noch keine Beschreibung verfügbar.') }}</span>
                                            </summary>
                                            @if($businessSummary)<p class="mt-1 text-[10px] leading-4 text-[var(--ak-muted)]">{{ $businessSummary }}</p><span class="mt-1 inline-block text-[8px] font-black text-cyan-300/70">{{ __('Weniger') }} ↑</span>@endif
                                        </details>
                                        <div class="screener-mobile-fundamentals grid grid-cols-2 gap-1.5">
                                            <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.045] px-2 py-1.5 text-center">
                                                <small class="block text-[7px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ __('KGV') }}</small>
                                                <b class="mt-0.5 block text-[10px] font-black tabular-nums text-[var(--ak-text)]">{{ $priceEarningsRatio !== null ? number_format($priceEarningsRatio, 2, ',', '.') : '—' }}</b>
                                            </div>
                                            <div class="rounded-lg border border-emerald-400/15 bg-emerald-400/[.045] px-2 py-1.5 text-center">
                                                <small class="block text-[7px] font-black uppercase tracking-[.06em] text-[var(--ak-muted)]">{{ __('Div.-Rendite') }}</small>
                                                <b class="mt-0.5 block text-[10px] font-black tabular-nums {{ $dividendYield !== null && $dividendYield > 0 ? 'text-emerald-300' : 'text-[var(--ak-text)]' }}">{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—' }}</b>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid h-full min-h-0 gap-3 md:col-span-2 xl:col-span-2">
                        @if ($stock->external_review_is_current)
                        @php
                            [$externalReviewLabel, $externalReviewTone] = match ($stock->external_review_status === 'completed' ? $stock->external_review_verdict : $stock->external_review_status) {
                                'NO_OBJECTION' => [__('Kein wesentlicher Einwand'), 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300'],
                                'CAUTION' => [__('Vorsicht'), 'border-amber-400/35 bg-amber-400/10 text-amber-300'],
                                'OBJECTION' => [__('Wesentlicher Einwand'), 'border-rose-400/35 bg-rose-400/10 text-rose-300'],
                                'INSUFFICIENT_EVIDENCE' => [__('Datenlage unzureichend'), 'border-slate-400/25 bg-slate-400/10 text-slate-300'],
                                'failed' => [__('Recherche fehlgeschlagen'), 'border-rose-400/30 bg-rose-400/10 text-rose-300'],
                                default => [__('Webrecherche läuft'), 'border-cyan-400/30 bg-cyan-400/10 text-cyan-300'],
                            };
                            $externalReviewSummary = $stock->external_review_summary ?: match ($stock->external_review_status) {
                                'failed' => __('Der externe Check konnte noch nicht abgeschlossen werden.'),
                                default => __('Öffentliche Quellen werden unabhängig vom internen Modell geprüft.'),
                            };
                            $externalReviewIsYes = $stock->external_review_status === 'completed'
                                && $stock->external_review_verdict === 'NO_OBJECTION';
                            $externalReviewDecision = $externalReviewIsYes ? __('JA') : __('NEIN');
                            $externalReviewDecisionTone = $externalReviewIsYes
                                ? 'border-emerald-400/45 bg-emerald-400/15 text-emerald-300'
                                : 'border-rose-400/45 bg-rose-400/15 text-rose-300';
                            $externalReviewAdjustment = match ($stock->external_review_status === 'completed' ? $stock->external_review_verdict : $stock->external_review_status) {
                                'NO_OBJECTION' => __('BUY bestätigt'),
                                'CAUTION', 'OBJECTION' => __('BUY extern abgestuft'),
                                'INSUFFICIENT_EVIDENCE', 'failed' => __('BUY nicht bestätigt'),
                                default => __('Prüfung offen'),
                            };
                            $externalReviewAdjustmentTone = in_array($stock->external_review_verdict, ['CAUTION', 'OBJECTION'], true)
                                ? 'border-amber-400/35 bg-amber-400/10 text-amber-300'
                                : ($externalReviewIsYes
                                    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300'
                                    : 'border-slate-400/25 bg-slate-400/10 text-slate-300');
                        @endphp
                        <details class="screener-transparent-panel assessment-details-card relative z-20 h-full min-h-0 rounded-xl border p-3">
                            <summary class="flex min-h-0 cursor-pointer list-none flex-col">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex min-w-14 items-center justify-center rounded-lg border px-3 py-1 text-lg font-black {{ $externalReviewDecisionTone }}">{{ $externalReviewDecision }}</span>
                                    <span class="rounded-md border px-2 py-1 text-[9px] font-black uppercase tracking-wide {{ $externalReviewAdjustmentTone }}">{{ $externalReviewAdjustment }}</span>
                                </div>
                                <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Unabhängiger KI-Check · Webrecherche') }}</p>
                                        <span class="rounded-md border px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide {{ $externalReviewTone }}">{{ $externalReviewLabel }}</span>
                                        @if ($stock->external_review_researched_at ?: $stock->external_review_triggered_at)
                                            <span class="text-[9px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->external_review_researched_at ?: $stock->external_review_triggered_at)->format('d.m.Y H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span aria-label="{{ __('Vollständige Bewertung anzeigen') }}" class="ml-2 shrink-0 text-xs font-black text-cyan-300">{{ __('Mehr') }} ↓</span>
                                </div>
                                <p class="assessment-preview mt-2 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">{{ $externalReviewSummary }}</p>
                            </summary>
                            <div class="assessment-full mt-3">
                                <p class="text-xs leading-5 text-[var(--ak-muted)]">{{ $externalReviewSummary }}</p>
                                <p class="mt-1 text-[9px] font-bold text-cyan-300/80">{{ __('Shadow-Modus: Das externe Urteil verändert das ML-Signal nicht.') }}</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    [__('Externe positive Faktoren'), $stock->external_review_positive_factors, 'text-emerald-300', ''],
                                    [__('Externe Risikofaktoren'), $stock->external_review_risk_factors, 'text-rose-300', ''],
                                ] as [$assessmentTitle, $assessmentItems, $assessmentTone, $assessmentBox])
                                    <div class="{{ $assessmentBox }}">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $assessmentTone }}">{{ $assessmentTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @forelse (array_slice($assessmentItems, 0, 3) as $assessmentItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $assessmentTone }}"></span>
                                                    <span>{{ is_scalar($assessmentItem) ? $assessmentItem : json_encode($assessmentItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                                </li>
                                            @empty
                                                <li class="text-[10px] text-[var(--ak-muted)]">{{ __('Keine Daten') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            @if ($stock->external_review_sources !== [])
                                <div class="mt-3 border-t border-cyan-300/10 pt-2">
                                    <p class="text-[9px] font-black uppercase tracking-[.1em] text-cyan-300">{{ __('Verifizierte Webquellen') }}</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach (array_slice($stock->external_review_sources, 0, 5) as $externalSource)
                                            @if (filled(data_get($externalSource, 'url')))
                                                <a href="{{ data_get($externalSource, 'url') }}" target="_blank" rel="noopener noreferrer nofollow" class="max-w-full truncate rounded-md border border-cyan-300/15 bg-cyan-400/[.04] px-2 py-1 text-[9px] font-bold text-cyan-300 hover:bg-cyan-400/10">
                                                    {{ data_get($externalSource, 'title', data_get($externalSource, 'domain', __('Quelle'))) }} ↗
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if ($stock->external_review_status === 'completed')
                                <p class="mt-2 text-[9px] text-[var(--ak-muted)]">
                                    {{ $stock->external_review_model }}
                                    · {{ $stock->external_review_confidence }}% {{ __('Recherche-Konfidenz') }}
                                    · {{ count($stock->external_review_sources) }} {{ __('Quellen') }}
                                    @if ($stock->external_review_cost_usd !== null)
                                        · ca. ${{ number_format($stock->external_review_cost_usd, 4, '.', '') }}
                                    @endif
                                </p>
                            @endif
                            </div>
                        </details>
                        @else
                        @php
                            $internalAssessmentSignal = strtoupper((string) ($stock->assessment_recommendation ?: $stock->personalized_signal ?: ''));
                            $internalAssessmentIsYes = $internalAssessmentSignal === 'BUY';
                            $internalAssessmentDecision = $internalAssessmentIsYes ? __('JA') : __('NEIN');
                            $internalAssessmentDecisionTone = $internalAssessmentIsYes
                                ? 'border-emerald-400/45 bg-emerald-400/15 text-emerald-300'
                                : 'border-rose-400/45 bg-rose-400/15 text-rose-300';
                            $internalAssessmentAdjustment = $internalAssessmentIsYes
                                ? __('BUY bestätigt')
                                : ($internalAssessmentSignal !== ''
                                    ? __('BUY auf :signal abgestuft', ['signal' => $internalAssessmentSignal])
                                    : __('BUY nicht bestätigt'));
                            $internalAssessmentAdjustmentTone = $internalAssessmentIsYes
                                ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300'
                                : 'border-amber-400/35 bg-amber-400/10 text-amber-300';
                        @endphp
                        <details class="screener-transparent-panel simple-assessment-card relative z-20 h-full min-h-0 rounded-xl border p-3">
                            <summary class="flex min-h-0 cursor-pointer list-none flex-col">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex min-w-14 items-center justify-center rounded-lg border px-3 py-1 text-lg font-black {{ $internalAssessmentDecisionTone }}">{{ $internalAssessmentDecision }}</span>
                                <span class="rounded-md border px-2 py-1 text-[9px] font-black uppercase tracking-wide {{ $internalAssessmentAdjustmentTone }}">{{ $internalAssessmentAdjustment }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-violet-300">{{ __('Chancen und Risiken') }}</p>
                                    @if ($stock->simple_assessment_is_stored && $stock->assessment_date)
                                        <span class="text-[9px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->assessment_date)->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                                <span aria-label="{{ __('Vollständige Bewertung anzeigen') }}" class="ml-2 shrink-0 text-xs font-black text-violet-300">{{ __('Mehr') }} ↓</span>
                            </div>
                            <div class="simple-assessment-preview mt-2 grid min-h-0 flex-1 gap-3 overflow-hidden sm:grid-cols-2">
                                @foreach ([
                                    [__('Chancen'), $stock->simple_pros, 'text-emerald-300'],
                                    [__('Risiken'), $stock->simple_cons, 'text-rose-300'],
                                ] as [$previewTitle, $previewItems, $previewTone])
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $previewTone }}">{{ $previewTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($previewItems as $previewItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $previewTone }}"></span>
                                                    <span>{{ $previewItem }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            </summary>
                            <div class="simple-assessment-full mt-3">
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    [__('Chancen'), $stock->simple_pros, 'text-emerald-300', ''],
                                    [__('Risiken'), $stock->simple_cons, 'text-rose-300', ''],
                                ] as [$assessmentTitle, $assessmentItems, $assessmentTone, $assessmentBox])
                                    <div class="{{ $assessmentBox }}">
                                        <p class="text-[9px] font-black uppercase tracking-[.1em] {{ $assessmentTone }}">{{ $assessmentTitle }}</p>
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($assessmentItems as $assessmentItem)
                                                <li class="flex gap-1.5 text-[10px] leading-4 text-[var(--ak-muted)]">
                                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-current {{ $assessmentTone }}"></span>
                                                    <span>{{ $assessmentItem }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-[9px] text-[var(--ak-muted)]">
                                @if (! $stock->simple_assessment_is_stored)
                                    {{ __('Automatisch aus den aktuellen Modell- und Filterwerten abgeleitet.') }}
                                @endif
                            </p>
                            </div>
                        </details>
                        @endif
                        </div>
                    </div>
                    </template>
                </article>
            @empty
                <div class="rounded-2xl border border-cyan-400/25 bg-[var(--ak-card)] p-8 text-center text-sm text-[var(--ak-muted)] sm:col-span-2 xl:col-span-3">{{ __('Keine Aktien für diese Auswahl gefunden.') }}</div>
            @endforelse
        </section>
        </div>
    </div>

    @once
        <script>
            (() => {
                const chartInstances = new WeakMap();
                const average = (values, period) => values.map((_, index) => index < period - 1 ? null : values.slice(index - period + 1, index + 1).reduce((sum, value) => sum + value, 0) / period);
                const rsi = (values, period = 14) => values.map((_, index) => {
                    if (index < period) return null;
                    const changes = values.slice(index - period, index + 1).slice(1).map((value, offset) => value - values[index - period + offset]);
                    const gain = changes.reduce((sum, value) => sum + Math.max(0, value), 0) / period;
                    const loss = changes.reduce((sum, value) => sum + Math.max(0, -value), 0) / period;
                    return loss === 0 ? 100 : 100 - (100 / (1 + gain / loss));
                });

                window.initializeScreenerChartPlus = async (root) => {
                    if (!root || root.dataset.initialized || !window.matchMedia('(min-width: 1024px)').matches) return;
                    root.dataset.initialized = 'true';
                    const response = await fetch(root.dataset.chartDataUrl, { credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const payload = await response.json();
                    const source = Array.isArray(payload.candles) ? payload.candles : [];
                    const canvas = root.querySelector('[data-chart-plus-canvas]');
                    const rsiCanvas = root.querySelector('[data-chart-plus-rsi]');
                    const state = { period:'1m', indicators:new Set(), main:null, rsi:null };
                    chartInstances.set(root, state);

                    const render = async () => {
                        if (!window.ApexCharts) await new Promise(resolve => window.addEventListener('aktienki:charts-ready', resolve, { once:true }));
                        const limit = { '1m':22, '3m':66, '6m':132, '1y':264, all:source.length }[state.period] || 22;
                        const roundPrice = value => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
                        const candles = source.slice(-limit).map(point => ({...point,y:Array.isArray(point.y) ? point.y.map(roundPrice) : point.y}));
                        const closes = candles.map(point => Number(point.y?.[3] ?? 0));
                        const line = (label, values, color) => ({ name:label, type:'line', data:candles.map((point,index) => ({ x:point.x, y:values[index] === null || values[index] === undefined ? null : roundPrice(values[index]) })), color });
                        const series = [{ name:root.dataset.symbol, type:'candlestick', data:candles }];
                        const forecastValues = JSON.parse(root.dataset.forecastPoints || '{}');
                        const latest = candles.at(-1);
                        const forecastData = latest ? [{x:Number(latest.x),y:roundPrice(latest.y?.[3])}, ...Object.entries(forecastValues).map(([days,value])=>({days:Number(days),x:Number(latest.x)+(Number(days)*1.4*86400000),y:roundPrice(Number(latest.y?.[3])*(1+Number(value)/100))}))] : [];
                        if (forecastData.length > 1) series.push({name:'{{ __('Prognose') }}',type:'line',data:forecastData,color:'#fbbf24'});
                        if (state.indicators.has('sma20')) series.push(line('SMA 20', average(closes,20), '#22d3ee'));
                        if (state.indicators.has('sma50')) series.push(line('SMA 50', average(closes,50), '#fbbf24'));
                        state.main?.destroy();
                        canvas.innerHTML='';
                        const forecastAnnotations=forecastData.slice(1).map(point=>({x:point.x,y:point.y,seriesIndex:1,marker:{size:0},label:{text:`${point.days}T`,borderColor:'#fbbf24',style:{fontSize:'8px',fontWeight:800,background:'#fbbf24',color:'#071423'}}}));
                        state.main = new window.ApexCharts(canvas, { chart:{type:'candlestick',height:285,toolbar:{show:false},animations:{enabled:false},background:'transparent'}, series, annotations:{points:forecastAnnotations}, theme:{mode:document.documentElement.dataset.theme==='light'?'light':'dark'}, grid:{borderColor:'rgba(148,163,184,.12)'}, xaxis:{type:'datetime',labels:{style:{fontSize:'9px'}}}, yaxis:{decimalsInFloat:2,tooltip:{enabled:true},labels:{style:{fontSize:'9px'},formatter:value=>Number(value).toLocaleString(document.documentElement.lang||'de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})}},tooltip:{y:{formatter:value=>Number(value).toLocaleString(document.documentElement.lang||'de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})}},plotOptions:{candlestick:{colors:{upward:'#34d399',downward:'#fb7185'}}}, stroke:{width:series.map((item)=>item.type==='candlestick'?1:2),dashArray:series.map(item=>item.name==='{{ __('Prognose') }}'?5:0)}, legend:{show:series.length>1,fontSize:'10px'} });
                        await state.main.render();
                        await state.main.updateOptions({ chart:{ height:285 }, legend:{ show:false } }, false, false);
                        const chartGlobals=state.main.w?.globals;
                        const chartInner=canvas.querySelector('.apexcharts-inner');
                        if(chartGlobals&&chartInner&&forecastData.length>1){
                            chartInner.querySelector('[data-forecast-triangles]')?.remove();
                            const ns='http://www.w3.org/2000/svg';
                            const group=document.createElementNS(ns,'g');group.dataset.forecastTriangles='true';group.setAttribute('pointer-events','none');
                            const x=value=>((value-chartGlobals.minX)/(chartGlobals.maxX-chartGlobals.minX))*chartGlobals.gridWidth;
                            const y=value=>chartGlobals.gridHeight-((value-chartGlobals.minY)/(chartGlobals.maxY-chartGlobals.minY))*chartGlobals.gridHeight;
                            forecastData.slice(1).forEach((point,index)=>{
                                const previous=forecastData[index];const rising=point.y>=previous.y;const color=rising?'#22c55e':'#ef4444';
                                const patternId=`screener-forecast-${root.dataset.symbol.replace(/[^a-z0-9]/gi,'')}-${index}`;
                                const defs=document.createElementNS(ns,'defs');const pattern=document.createElementNS(ns,'pattern');pattern.setAttribute('id',patternId);pattern.setAttribute('width','7');pattern.setAttribute('height','7');pattern.setAttribute('patternUnits','userSpaceOnUse');pattern.setAttribute('patternTransform','rotate(35)');
                                const hatch=document.createElementNS(ns,'line');hatch.setAttribute('x1','0');hatch.setAttribute('y1','0');hatch.setAttribute('x2','0');hatch.setAttribute('y2','7');hatch.setAttribute('stroke',color);hatch.setAttribute('stroke-width','2');hatch.setAttribute('stroke-opacity','.38');pattern.appendChild(hatch);defs.appendChild(pattern);group.appendChild(defs);
                                const px=x(previous.x),py=y(previous.y),cx=x(point.x),cy=y(point.y);const triangle=document.createElementNS(ns,'polygon');triangle.setAttribute('points',`${px},${py} ${cx},${cy} ${rising?cx:px},${Math.max(py,cy)}`);triangle.setAttribute('fill',`url(#${patternId})`);triangle.setAttribute('stroke',color);triangle.setAttribute('stroke-width','1.6');triangle.setAttribute('stroke-opacity','.9');triangle.setAttribute('stroke-linejoin','round');triangle.setAttribute('vector-effect','non-scaling-stroke');group.appendChild(triangle);
                            });
                            chartInner.appendChild(group);
                        }
                        state.rsi?.destroy(); state.rsi=null;
                        if (state.indicators.has('rsi')) {
                            rsiCanvas.hidden=false; rsiCanvas.innerHTML='';
                            state.rsi=new window.ApexCharts(rsiCanvas,{chart:{type:'line',height:105,toolbar:{show:false},animations:{enabled:false},background:'transparent'},series:[line('RSI 14',rsi(closes),'#c084fc')],theme:{mode:document.documentElement.dataset.theme==='light'?'light':'dark'},stroke:{width:2,curve:'smooth'},markers:{size:0},xaxis:{type:'datetime',labels:{show:false}},yaxis:{min:0,max:100,tickAmount:2,decimalsInFloat:2,labels:{formatter:value=>Number(value).toLocaleString(document.documentElement.lang||'de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})}},tooltip:{y:{formatter:value=>Number(value).toLocaleString(document.documentElement.lang||'de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})}},annotations:{yaxis:[{y:30,borderColor:'#34d399'},{y:70,borderColor:'#fb7185'}]},grid:{borderColor:'rgba(148,163,184,.12)'},legend:{show:false}});
                            await state.rsi.render();
                        } else rsiCanvas.hidden=true;
                    };
                    root.querySelectorAll('[data-chart-plus-period]').forEach(button => button.addEventListener('click',()=>{root.querySelectorAll('[data-chart-plus-period]').forEach(item=>item.classList.remove('is-active'));button.classList.add('is-active');state.period=button.dataset.chartPlusPeriod;render()}));
                    root.querySelectorAll('[data-chart-plus-indicator]').forEach(button => button.addEventListener('click',()=>{const key=button.dataset.chartPlusIndicator;state.indicators.has(key)?state.indicators.delete(key):state.indicators.add(key);button.classList.toggle('is-active',state.indicators.has(key));render()}));
                    await render();
                };

                const initializeServingCharts = () => {
                    const charts = document.querySelectorAll('[data-screener-chart]:not([data-chart-state])');
                    if (!charts.length) return;

                    const load = async (chart) => {
                        if (chart.dataset.chartState) return;
                        chart.dataset.chartState = 'loading';
                        try {
                            const response = await fetch(chart.dataset.chartUrl, {
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (!response.ok) throw new Error(`HTTP ${response.status}`);
                            chart.innerHTML = await response.text();
                            await window.initializeScreenerChartPlus(chart.querySelector('[data-chart-plus]'));
                            chart.dataset.chartState = 'loaded';
                        } catch (error) {
                            chart.dataset.chartState = 'failed';
                            chart.innerHTML = '<div class="flex h-24 items-center justify-center px-4 text-center text-xs italic text-[var(--ak-muted)]">{{ __('Der Kurschart ist momentan nicht verfügbar.') }}</div>';
                        }
                    };

                    if (!('IntersectionObserver' in window)) {
                        charts.forEach(load);
                        return;
                    }

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;
                            observer.unobserve(entry.target);
                            load(entry.target);
                        });
                    }, { rootMargin: '320px 0px' });
                    charts.forEach((chart) => observer.observe(chart));
                };
                window.initializeServingCharts = initializeServingCharts;

                const initializeScreenerSorting = () => {
                    const table=document.querySelector('.screener-desktop-table');
                    if(!table||table.dataset.sortingReady)return;
                    table.dataset.sortingReady='true';
                    table.querySelectorAll('[data-screener-sort]').forEach(button=>button.addEventListener('click',()=>{
                        const key=button.dataset.screenerSort;
                        const direction=button.dataset.direction==='desc'?'asc':'desc';
                        table.querySelectorAll('[data-screener-sort]').forEach(item=>{item.classList.toggle('is-active',item===button);item.dataset.direction=item===button?direction:'';item.querySelector('i').textContent=item===button?(direction==='asc'?'↑':'↓'):'↕'});
                        [...table.querySelectorAll(':scope > .screener-stock-card')].sort((left,right)=>{
                            const a=left.dataset[key.replace('-','').replace('forecast','forecast')];
                            const b=right.dataset[key.replace('-','').replace('forecast','forecast')];
                            if(a===''&&b==='')return 0;if(a==='')return 1;if(b==='')return-1;
                            return direction==='asc'?Number(a)-Number(b):Number(b)-Number(a);
                        }).forEach(row=>table.appendChild(row));
                    }));
                };

                const updateTimeAdjustedAssessment=(row)=>{
                    if(!row)return;
                    const weights={5:.10,10:.20,15:.20,20:.30,40:.20};
                    const cost=Math.max(0,Number(row.dataset.assessmentCost??.5));
                    const minimum=Math.max(0,Number(row.dataset.assessmentMinimumReturn??1));
                    const quality=Math.max(0,Math.min(100,Number(row.dataset.assessmentQuality??50)));
                    const startValue=row.dataset.predictionDate;
                    const start=startValue?new Date(`${startValue}T12:00:00`):null;
                    const today=new Date();today.setHours(12,0,0,0);
                    let elapsed=0;
                    if(start&&!Number.isNaN(start.getTime())&&today>start){
                        const cursor=new Date(start);
                        while(cursor<today){cursor.setDate(cursor.getDate()+1);const day=cursor.getDay();if(day!==0&&day!==6)elapsed++}
                    }
                    const values=[];
                    row.querySelectorAll('[data-assessment-horizon]').forEach(cell=>{
                        const horizon=Number(cell.dataset.assessmentHorizon);
                        const rawReturn=cell.dataset.assessmentReturn;
                        const gross=Number(rawReturn);
                        const remaining=Math.max(0,horizon-elapsed);
                        if(rawReturn===''||!weights[horizon]||!Number.isFinite(gross)||remaining===0)return;
                        values.push({net:gross-cost,weight:weights[horizon]*(remaining/horizon)});
                    });
                    const weightSum=values.reduce((sum,value)=>sum+value.weight,0);
                    let percent=0,weightedReturn=0;
                    if(weightSum>0){
                        let positive=0,negative=0;
                        values.forEach(value=>{const weight=value.weight/weightSum;weightedReturn+=value.net*weight;if(value.net>.25)positive+=weight;else if(value.net<-.25)negative+=weight});
                        const agreement=Math.max(positive,negative);
                        const direction=Math.sign(weightedReturn);
                        const strength=Math.min(1,Math.tanh(Math.abs(weightedReturn)/6)*(.55+.45*quality/100)*(.70+.30*agreement));
                        percent=50+direction*50*strength;
                        if(weightedReturn<minimum)percent=Math.min(percent,49.99);
                    }
                    percent=Math.max(0,Math.min(100,percent));
                    const grades=['5−','5+','4−','4+','3−','3+','2−','2+','1−','1+'];
                    const label=grades[Math.min(9,Math.floor(percent/10))];
                    const hue=percent<=50?(percent/50)*48:48+((percent-50)/50)*94;
                    const color=`hsl(${hue.toFixed(1)} 78% 52%)`;
                    row.querySelectorAll('[data-screener-assessment-label]').forEach(element=>element.textContent=`{{ __('Bewertung') }} · ${label}`);
                    row.querySelectorAll('[data-screener-assessment-scale]').forEach(element=>{
                        element.style.setProperty('--position',`${percent.toFixed(2)}%`);
                        element.style.setProperty('--marker',color);
                        element.setAttribute('aria-valuenow',percent.toFixed(1));
                        element.title=`Netto ${weightedReturn.toLocaleString(document.documentElement.lang,{minimumFractionDigits:2,maximumFractionDigits:2})} % · Kosten ${cost.toLocaleString(document.documentElement.lang)} %`;
                    });
                    row.querySelectorAll('[data-assessment-horizon]').forEach(cell=>{
                        const rawDistance=cell.dataset.assessmentReturn;
                        const distance=Number(rawDistance);
                        cell.classList.toggle('is-time-downgraded',rawDistance!==''&&Number.isFinite(distance)&&distance<1);
                        cell.classList.toggle('is-negative-forecast',rawDistance!==''&&Number.isFinite(distance)&&distance<0);
                    });
                };

                const applyScreenerLivePrice = (event) => {
                    const symbol=String(event.detail?.symbol??'');
                    const price=Number(event.detail?.price);
                    if(!symbol||!Number.isFinite(price)||price<=0)return;
                    const escaped=CSS.escape(symbol);
                    document.querySelectorAll(`[data-screener-live-status="${escaped}"]`).forEach(status=>{
                        status.hidden=false;
                        if(event.detail?.simulation===true)status.textContent='Simulation';
                        status.title=new Date(Number(event.detail?.timestamp??Date.now()/1000)*1000).toLocaleTimeString(
                            document.documentElement.lang,
                            {hour:'2-digit',minute:'2-digit',second:'2-digit',timeZone:'Europe/Berlin'},
                        );
                    });
                    document.querySelectorAll(`[data-screener-live-forecast="${escaped}"]`).forEach(forecast=>{
                        const target=Number(forecast.dataset.targetPrice);
                        if(!Number.isFinite(target)||target<=0)return;
                        const value=((target-price)/price)*100;
                        const updatedAt=new Date(Number(event.detail?.timestamp??Date.now()/1000)*1000);
                        forecast.textContent=`${value>0?'+':''}${value.toLocaleString(document.documentElement.lang,{minimumFractionDigits:2,maximumFractionDigits:2})} %`;
                        forecast.classList.remove('text-emerald-400','text-rose-400','text-slate-400');
                        forecast.classList.add(value>=0?'text-emerald-400':'text-rose-400');
                        forecast.dataset.liveUpdatedAt=String(Number(event.detail?.timestamp??Date.now()/1000));
                        forecast.closest('[data-assessment-horizon]')?.setAttribute('data-assessment-return',String(value));
                        forecast.title=`${event.detail?.realtime===true?'Live':'15 Min. verzögert'} neu berechnet · ${updatedAt.toLocaleTimeString(document.documentElement.lang,{hour:'2-digit',minute:'2-digit',second:'2-digit',timeZone:'Europe/Berlin'})}`;
                        const forecastCell=forecast.closest('i');
                        forecastCell?.classList.remove('is-live-recalculated','is-delayed-recalculated');
                        forecastCell?.classList.add(event.detail?.realtime===true?'is-live-recalculated':'is-delayed-recalculated');
                        const row=forecast.closest('.screener-stock-card');
                        if(row){row.dataset[`forecast${forecast.dataset.horizon}`]=String(value);updateTimeAdjustedAssessment(row)}
                    });
                };

                window.addEventListener('aktienki:live-price',applyScreenerLivePrice);

                const initializeLiveSimulation=()=>{
                    const page=document.querySelector('[data-simulate-live="1"]');
                    if(!page||page.dataset.simulationReady)return;
                    page.dataset.simulationReady='true';
                    const prices=[...page.querySelectorAll('[data-live-symbol][data-live-base-price]')];
                    let tick=0;
                    const update=()=>{
                        const timestamp=Math.floor(Date.now()/1000);
                        prices.forEach((element,index)=>{
                            const base=Number(element.dataset.liveBasePrice);
                            if(!Number.isFinite(base)||base<=0)return;
                            const price=base*(1+(Math.sin((tick+index)*.73)*.004));
                            const decimals=Number(element.dataset.liveDecimals??2);
                            const currency=element.dataset.liveCurrency??'';
                            element.textContent=`${price.toLocaleString(document.documentElement.lang,{minimumFractionDigits:decimals,maximumFractionDigits:decimals})}${currency?` ${currency}`:''}`;
                            window.dispatchEvent(new CustomEvent('aktienki:live-price',{detail:{symbol:element.dataset.liveSymbol,price,timestamp,realtime:true,simulation:true}}));
                        });
                        tick+=1;
                    };
                    update();
                    window.setInterval(update,5000);
                };

                const initializeScreenerMiniCharts = () => {
                    const charts=document.querySelectorAll('[data-screener-minichart-url]:not([data-minichart-state])');
                    if(!charts.length)return;
                    const load=async chart=>{
                        chart.dataset.minichartState='loading';
                        try{
                            const response=await fetch(chart.dataset.screenerMinichartUrl,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                            if(!response.ok)throw new Error(`HTTP ${response.status}`);
                            const payload=await response.json();
                            const values=(Array.isArray(payload.candles)?payload.candles:[]).map(point=>Number(point?.y?.[3])).filter(Number.isFinite).slice(-20);
                            if(values.length<2)throw new Error('no chart data');
                            const minimum=Math.min(...values),range=Math.max(.000001,Math.max(...values)-minimum);
                            const points=values.map((value,index)=>`${(index*88/(values.length-1)).toFixed(1)},${(22-((value-minimum)/range)*18).toFixed(1)}`).join(' ');
                            const polyline=chart.querySelector('polyline');
                            polyline.setAttribute('points',points);
                            polyline.setAttribute('stroke',values.at(-1)>=values[0]?'#34d399':'#fb7185');
                            chart.classList.remove('invisible');
                            chart.dataset.minichartState='ready';
                        }catch(error){chart.dataset.minichartState='unavailable'}
                    };
                    if(!('IntersectionObserver' in window)){charts.forEach(load);return}
                    const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){observer.unobserve(entry.target);load(entry.target)}}),{rootMargin:'250px'});
                    charts.forEach(chart=>observer.observe(chart));
                };

                document.readyState === 'loading'
                    ? document.addEventListener('DOMContentLoaded', ()=>{initializeServingCharts();initializeScreenerSorting();initializeScreenerMiniCharts();initializeLiveSimulation()}, { once: true })
                    : (initializeServingCharts(),initializeScreenerSorting(),initializeScreenerMiniCharts(),initializeLiveSimulation());
                document.addEventListener('livewire:navigated', ()=>{initializeServingCharts();initializeScreenerMiniCharts()});
            })();
        </script>
    @endonce
</x-app-layout>
