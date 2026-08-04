<x-app-layout>
    <div id="sectors-page" class="ak-body text-[var(--ak-text)]">
        <div id="sectors-page-heading" class="z-30 border-b border-[var(--ak-border)] bg-[var(--ak-bg)]/95 py-4 backdrop-blur-xl">
            <div class="ak-container flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-500">aKI Sector Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black">{{ __('Sektoren') }}</h1>
                </div>
                <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                    {{ $sectors->count() }} {{ __('Sektoren') }}
                </span>
            </div>
        </div>

        <main id="sectors-page-content" class="ak-container mt-5 pb-4" x-data="{ active: 'sectors' }">
            <div id="sectors-tabs" class="mb-2 inline-flex items-end gap-1.5">
                <button type="button" @click="active = 'sectors'" class="ak-sector-tab" :class="active === 'sectors' ? 'ak-sector-tab-active' : 'ak-sector-tab-idle'">
                    <x-heroicon-o-squares-2x2 class="h-3.5 w-3.5" />
                    {{ __('Sektoren') }}
                </button>
                <button type="button" @click="active = 'comments'" class="ak-sector-tab" :class="active === 'comments' ? 'ak-sector-tab-active' : 'ak-sector-tab-idle'">
                    <x-heroicon-o-chat-bubble-left-right class="h-3.5 w-3.5" />
                    {{ __('Analyse') }}
                </button>
            </div>

            <section id="sectors-table-pane" x-show="active === 'sectors'">
                <div id="sectors-card-scroll" class="h-full overflow-auto pr-1">
                    <div class="grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @forelse ($sectors as $sector)
                            @php
                                $cardScore = \App\Support\AiScore::toTen($sector->average_score);
                                $cardScorePercent = \App\Support\AiScore::toPercent($sector->average_score);
                                $cardScoreChange = is_numeric($sector->five_day_score_change)
                                    ? \App\Support\AiScore::toTen($sector->average_score) - \App\Support\AiScore::toTen($sector->five_day_baseline_score)
                                    : null;
                                $cardForecast = is_numeric($sector->average_expected_return_20d) ? (float) $sector->average_expected_return_20d : null;
                                $cardConfidence = is_numeric($sector->average_confidence)
                                    ? min(100, max(0, (float) $sector->average_confidence <= 1 ? (float) $sector->average_confidence * 100 : (float) $sector->average_confidence))
                                    : null;
                                $cardRisk = is_numeric($sector->risk_p75)
                                    ? min(100, max(0, (float) $sector->risk_p75 <= 1 ? (float) $sector->risk_p75 * 100 : (float) $sector->risk_p75))
                                    : null;
                                $cardScoreClass = ($cardScore ?? 0) >= 6.5 ? 'text-emerald-500' : (($cardScore ?? 10) < 4.5 ? 'text-rose-500' : 'text-amber-500');
                                $cardTarget = route('stocks.index', ['sector' => $sector->sector]);
                            @endphp
                            <article onclick="window.location.href=@js($cardTarget)" class="flex min-w-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                                <div class="flex min-h-[66px] items-center justify-between gap-2 border-b border-[var(--ak-border)] p-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-cyan-500/10 text-cyan-500">
                                            <x-sector-icon :sector="$sector->sector" class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <h2 class="truncate text-sm font-black text-[var(--ak-text)]">{{ __($sector->sector) }}</h2>
                                            <p class="mt-0.5 truncate text-[9px] font-bold text-[var(--ak-muted)]">{{ $sector->analyzed_count }} {{ __('analysiert') }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-md border border-cyan-500/20 bg-cyan-500/10 px-2 py-1 text-[8px] font-black text-cyan-500">{{ $sector->stocks_count }} {{ __('Aktien') }}</span>
                                </div>

                                <div class="p-3">
                                    <div class="mb-1.5 flex items-end justify-between">
                                        <span class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('KI-Score') }}</span>
                                        <strong class="text-base font-black {{ $cardScoreClass }}">{{ $cardScore !== null ? number_format($cardScore, 1, ',', '.') : '—' }}<small class="ml-1 text-[8px] text-[var(--ak-muted)]">/10</small></strong>
                                    </div>
                                    <x-dashboard.score-stripes :percent="$cardScorePercent ?? 0" palette="cyan" />
                                </div>

                                <div class="grid grid-cols-2 gap-2 px-3 pb-3">
                                    <div class="rounded-lg bg-[var(--ak-surface-muted)] p-2">
                                        <p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Trend 5T') }}</p>
                                        <p class="mt-1 flex items-center gap-1 text-xs font-black {{ ($cardScoreChange ?? 0) > .05 ? 'text-emerald-500' : (($cardScoreChange ?? 0) < -.05 ? 'text-rose-500' : 'text-[var(--ak-muted)]') }}">
                                            @if (($cardScoreChange ?? 0) > .05)<x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                                            @elseif (($cardScoreChange ?? 0) < -.05)<x-heroicon-o-arrow-trending-down class="h-4 w-4" />
                                            @else<x-heroicon-o-arrow-right class="h-4 w-4" />@endif
                                            {{ $cardScoreChange !== null ? (($cardScoreChange > 0 ? '+' : '').number_format($cardScoreChange, 1, ',', '.')) : '—' }}
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-[var(--ak-surface-muted)] p-2">
                                        <p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Prognose 20T') }}</p>
                                        <p class="mt-1 text-xs font-black {{ ($cardForecast ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $cardForecast !== null ? (($cardForecast > 0 ? '+' : '').number_format($cardForecast, 1, ',', '.').' %') : '—' }}</p>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg bg-[var(--ak-surface-muted)] p-2">
                                        <span class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Konfidenz') }}</span>
                                        <b class="text-xs">{{ $cardConfidence !== null ? number_format($cardConfidence, 0, ',', '.').' %' : '—' }}</b>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg bg-[var(--ak-surface-muted)] p-2">
                                        <span class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Risiko P75') }}</span>
                                        <b class="text-xs">{{ $cardRisk !== null ? number_format($cardRisk, 0, ',', '.').' %' : '—' }}</b>
                                    </div>
                                </div>

                            </article>
                        @empty
                            <article class="col-span-full grid min-h-[280px] place-items-center rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] text-sm text-[var(--ak-muted)]">{{ __('Noch keine Sektordaten vorhanden.') }}</article>
                        @endforelse
                    </div>
                </div>

                <div id="sectors-table-scroll" class="hidden">
                    <table id="sectors-table" class="w-full table-fixed border-separate border-spacing-x-0 border-spacing-y-2 text-left">
                        <colgroup>
                            <col style="width: 21%">
                            <col style="width: 10%">
                            <col style="width: 18%">
                            <col style="width: 13%">
                            <col style="width: 14%">
                            <col style="width: 12%">
                            <col style="width: 12%">
                        </colgroup>
                        <thead class="text-[10px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
                            <tr>
                                <th class="px-4 py-3"><button type="button" data-sort="sector" data-type="text" class="ak-sector-sort">{{ __('Sektor') }} <span>↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="stocks" data-type="number" class="ak-sector-sort mx-auto">{{ __('Aktien') }} <span>↕</span></button></th>
                                <th class="px-4 py-3 text-center"><button type="button" data-sort="score" data-type="number" class="ak-sector-sort mx-auto">{{ __('KI-Score') }} <span>↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="trend" data-type="number" class="ak-sector-sort mx-auto">{{ __('Trend 5T') }} <span>↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="forecast" data-type="number" class="ak-sector-sort mx-auto">{{ __('Prognose 20T') }} <span>↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="confidence" data-type="number" class="ak-sector-sort mx-auto">{{ __('Konfidenz') }} <span>↕</span></button></th>
                                <th class="px-3 py-3 text-center">
                                    <button type="button" data-sort="risk" data-type="number" class="ak-sector-sort mx-auto" title="{{ __('75 % der Aktien dieses Sektors liegen unter diesem Drawdown-Risikowert.') }}">
                                        {{ __('Risiko P75') }} <span>↕</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sectors as $sector)
                                @php
                                    $score = \App\Support\AiScore::toTen($sector->average_score);
                                    $scorePercent = \App\Support\AiScore::toPercent($sector->average_score);
                                    $scoreChange = is_numeric($sector->five_day_score_change)
                                        ? \App\Support\AiScore::toTen($sector->average_score) - \App\Support\AiScore::toTen($sector->five_day_baseline_score)
                                        : null;
                                    $forecast20d = is_numeric($sector->average_expected_return_20d)
                                        ? (float) $sector->average_expected_return_20d
                                        : null;
                                    $confidence = is_numeric($sector->average_confidence)
                                        ? min(100, max(0, (float) $sector->average_confidence <= 1 ? (float) $sector->average_confidence * 100 : (float) $sector->average_confidence))
                                        : null;
                                    $risk = is_numeric($sector->risk_p75)
                                        ? min(100, max(0, (float) $sector->risk_p75 <= 1 ? (float) $sector->risk_p75 * 100 : (float) $sector->risk_p75))
                                        : null;
                                    $scoreClass = $score >= 6.5 ? 'text-emerald-500' : ($score < 4.5 ? 'text-rose-500' : 'text-amber-500');
                                    $confidenceColor = match (true) {
                                        $confidence === null => '#64748b',
                                        $confidence < 40 => '#ef4444',
                                        $confidence < 60 => '#f97316',
                                        $confidence < 75 => '#eab308',
                                        $confidence < 88 => '#84cc16',
                                        default => '#10b981',
                                    };
                                    $riskColor = match (true) {
                                        $risk === null => '#64748b',
                                        $risk < 10 => '#10b981',
                                        $risk < 20 => '#84cc16',
                                        $risk < 30 => '#eab308',
                                        $risk < 40 => '#f97316',
                                        default => '#ef4444',
                                    };
                                    $target = route('stocks.index', ['sector' => $sector->sector]);
                                @endphp
                                <tr onclick="window.location.href=@js($target)" class="cursor-pointer text-sm">
                                    <td colspan="7" class="p-0">
                                        <div class="ak-sector-row-grid">
                                            <div data-column="sector" data-value="{{ $sector->sector }}" class="flex min-w-0 items-center gap-3 px-4 py-3">
                                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-cyan-500/10 text-cyan-500">
                                                    <x-sector-icon :sector="$sector->sector" class="h-5 w-5" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="truncate font-black">{{ __($sector->sector) }}</p>
                                                    <p class="truncate text-[10px] text-[var(--ak-muted)]">{{ $sector->analyzed_count }} {{ __('analysiert') }}</p>
                                                </div>
                                            </div>
                                            <div data-column="stocks" data-value="{{ $sector->stocks_count }}" class="flex items-center justify-center px-3 font-black">{{ $sector->stocks_count }}</div>
                                            <div data-column="score" data-value="{{ $score ?? '' }}" class="flex flex-col justify-center px-4">
                                                @if ($score !== null)
                                                    <div class="mb-1.5 flex items-baseline justify-between"><strong class="{{ $scoreClass }}">{{ number_format($score, 1, ',', '.') }}</strong><small class="text-[9px] text-[var(--ak-muted)]">/ 10</small></div>
                                                    <x-dashboard.score-stripes :percent="$scorePercent" palette="cyan" />
                                                @else
                                                    <span class="text-center text-[var(--ak-muted)]">—</span>
                                                @endif
                                            </div>
                                            <div data-column="trend" data-value="{{ $scoreChange ?? '' }}" class="flex items-center justify-center px-3">
                                                <span class="ak-sector-trend {{ ($scoreChange ?? 0) > .05 ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-400' : (($scoreChange ?? 0) < -.05 ? 'border-rose-400/30 bg-rose-400/10 text-rose-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)]') }}">
                                                    @if (($scoreChange ?? 0) > .05)<x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                                                    @elseif (($scoreChange ?? 0) < -.05)<x-heroicon-o-arrow-trending-down class="h-4 w-4" />
                                                    @else<x-heroicon-o-arrow-right class="h-4 w-4" />@endif
                                                    {{ $scoreChange !== null ? (($scoreChange > 0 ? '+' : '').number_format($scoreChange, 1, ',', '.')) : '—' }}
                                                </span>
                                            </div>
                                            <div data-column="forecast" data-value="{{ $forecast20d ?? '' }}" class="flex items-center justify-center px-3">
                                                <span class="ak-sector-trend {{ ($forecast20d ?? 0) > .05 ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-400' : (($forecast20d ?? 0) < -.05 ? 'border-rose-400/30 bg-rose-400/10 text-rose-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)]') }}">
                                                    @if (($forecast20d ?? 0) > .05)<x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                                                    @elseif (($forecast20d ?? 0) < -.05)<x-heroicon-o-arrow-trending-down class="h-4 w-4" />
                                                    @else<x-heroicon-o-arrow-right class="h-4 w-4" />@endif
                                                    {{ $forecast20d !== null ? (($forecast20d > 0 ? '+' : '').number_format($forecast20d, 1, ',', '.').' %') : '—' }}
                                                </span>
                                            </div>
                                            <div data-column="confidence" data-value="{{ $confidence ?? '' }}" class="flex items-center justify-center px-3">
                                                @if ($confidence !== null)
                                                    <div class="ak-sector-donut" style="--value: {{ $confidence }}%; --color: {{ $confidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuenow="{{ round($confidence) }}">
                                                        <span>{{ number_format($confidence, 0, ',', '.') }}<small>%</small></span>
                                                    </div>
                                                @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                            </div>
                                            <div data-column="risk" data-value="{{ $risk ?? '' }}" class="flex items-center justify-center px-3">
                                                @if ($risk !== null)
                                                    <div class="ak-sector-donut" style="--value: {{ $risk }}%; --color: {{ $riskColor }}" role="meter" aria-label="{{ __('Risiko P75') }}" aria-valuenow="{{ round($risk) }}">
                                                        <span>{{ number_format($risk, 0, ',', '.') }}<small>%</small><em>P75</em></span>
                                                    </div>
                                                @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine Sektordaten vorhanden.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="sectors-comments-pane" x-cloak x-show="active === 'comments'">
                @if ($sectorComments->isNotEmpty())
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($sectorComments as $comment)
                            @php
                                $outlook = strtoupper((string) ($comment['outlook'] ?? 'NEUTRAL'));
                                $outlookClass = match ($outlook) {
                                    'BULLISH' => 'border-emerald-500/35 bg-emerald-500/15 text-emerald-400',
                                    'BEARISH' => 'border-rose-500/35 bg-rose-500/15 text-rose-400',
                                    default => 'border-amber-500/35 bg-amber-500/15 text-amber-400',
                                };
                            @endphp
                            <article class="ak-card ak-card-static flex min-h-[172px] flex-col p-4">
                                <div class="ak-sector-comment-heading flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-500/20 bg-cyan-500/10 text-cyan-500">
                                            <x-sector-icon :sector="$comment['sector'] ?? null" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Sektorenkommentar') }}</p>
                                            <h2 class="mt-0.5 truncate text-base font-black text-[var(--ak-text)]">{{ __((string) ($comment['sector'] ?? '—')) }}</h2>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-md border px-2.5 py-1.5 text-[9px] font-black {{ $outlookClass }}">{{ __($outlook) }}</span>
                                </div>
                                <p class="ak-comment-copy mt-3 flex-1 border-t border-[var(--ak-border)] pt-3 text-[13px] leading-5">{{ $comment['summary'] ?? '—' }}</p>
                                @if ($sectorAnalysisDate)
                                    <p class="mt-2 border-t border-[var(--ak-border)] pt-2 text-right text-[9px] font-bold text-[var(--ak-muted)]">{{ \Carbon\Carbon::parse($sectorAnalysisDate)->format('d.m.Y') }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <article class="ak-card grid min-h-[280px] place-items-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine Sektorenkommentare verfügbar.') }}</article>
                @endif
            </section>
        </main>
    </div>

    <style>
        #sectors-page{display:flex;flex-direction:column;height:calc(100dvh - 73px - 1rem)!important;min-height:0!important;overflow:hidden!important}
        #sectors-page-heading,#sectors-tabs{flex:0 0 auto}
        #sectors-page-content{display:flex;flex:1 1 auto;flex-direction:column;min-height:0;overflow:hidden}
        #sectors-table-pane,#sectors-comments-pane{flex:1 1 auto;min-height:0;overflow:hidden}
        #sectors-table-scroll{height:100%;overflow:auto;overscroll-behavior:contain}
        #sectors-card-scroll{overscroll-behavior:contain}
        #sectors-comments-pane{height:100%;overflow:auto;overscroll-behavior:contain}
        #sectors-comments-pane .ak-sector-comment-heading{flex:0 0 60px;height:60px;min-height:60px;overflow:visible}
        #sectors-comments-pane .ak-comment-copy{color:color-mix(in srgb,var(--ak-text) 86%,var(--ak-muted) 14%)}
        .ak-sector-tab{display:inline-flex;width:116px;height:34px;align-items:center;justify-content:center;gap:6px;border:1px solid var(--ak-border);border-bottom-width:2px;border-radius:9px 9px 5px 5px;padding:0 10px;font-size:10px;font-weight:900;letter-spacing:.035em;box-shadow:0 4px 10px rgba(3,7,18,.1);transition:none}
        .ak-sector-tab-active{border-color:color-mix(in srgb,rgb(6 182 212) 38%,var(--ak-border));border-bottom-color:rgb(6 182 212);background:var(--ak-card);color:rgb(6 182 212);box-shadow:inset 0 1px 0 rgba(255,255,255,.04),0 4px 12px rgba(3,7,18,.14)}
        .ak-sector-tab-idle{background:color-mix(in srgb,var(--ak-card) 72%,var(--ak-bg) 28%);color:var(--ak-muted)}
        #sectors-table{min-width:1180px}
        #sectors-table thead,#sectors-table thead th{background:var(--ak-surface)!important}
        #sectors-table thead th{position:sticky;top:0;z-index:20;box-shadow:0 1px 0 var(--ak-border),0 8px 16px rgba(3,7,18,.15)}
        #sectors-table tbody td{border:0!important;background:transparent!important}
        .ak-sector-row-grid{display:grid;grid-template-columns:21% 10% 18% 13% 14% 12% 12%;height:72px;align-items:stretch;overflow:hidden;border:1px solid var(--ak-border);border-radius:16px;background:var(--ak-card);box-shadow:0 5px 14px rgba(5,10,28,.14)}
        #sectors-table tbody tr:nth-child(even) .ak-sector-row-grid{background:var(--ak-card-hover)}
        #sectors-table tbody tr:hover .ak-sector-row-grid{background:color-mix(in srgb,var(--ak-card-hover) 90%,rgb(34 211 238) 10%)}
        .ak-sector-sort{display:flex;align-items:center;gap:.35rem;white-space:nowrap}
        .ak-sector-sort span{font-size:.7rem;opacity:.55}
        .ak-sector-sort[aria-sort] span{color:rgb(6 182 212);opacity:1}
        .ak-sector-trend{display:inline-flex;width:84px;height:30px;flex:0 0 84px;align-items:center;justify-content:center;gap:6px;border-width:1px;border-radius:8px;padding:0 7px;font-weight:900;white-space:nowrap}
        .ak-sector-donut{position:relative;display:grid;width:48px;height:48px;flex:0 0 48px;place-items:center;border-radius:999px;background:conic-gradient(var(--color) 0 var(--value),rgba(148,163,184,.16) var(--value) 100%);box-shadow:0 0 14px color-mix(in srgb,var(--color) 18%,transparent)}
        .ak-sector-donut:after{position:absolute;inset:5px;border-radius:inherit;background:var(--ak-card);content:''}
        #sectors-table tbody tr:nth-child(even) .ak-sector-donut:after{background:var(--ak-card-hover)}
        .ak-sector-donut span{position:relative;z-index:1;display:grid;grid-template-columns:auto auto;place-items:center;font-size:11px;font-weight:900;line-height:1}
        .ak-sector-donut small{margin-left:1px;color:var(--ak-muted);font-size:7px}
        .ak-sector-donut em{grid-column:1/-1;margin-top:2px;color:var(--ak-muted);font-size:6px;font-style:normal;letter-spacing:.08em}
    </style>

    <script>
        document.addEventListener('DOMContentLoaded',()=>{const table=document.getElementById('sectors-table');if(!table)return;const body=table.tBodies[0],buttons=[...table.querySelectorAll('[data-sort]')];let active=null,direction=1;buttons.forEach(button=>button.addEventListener('click',()=>{const key=button.dataset.sort,type=button.dataset.type;direction=active===key?direction*-1:1;active=key;const rows=[...body.rows].filter(row=>row.querySelector(`[data-column="${key}"]`));rows.sort((a,b)=>{const left=a.querySelector(`[data-column="${key}"]`)?.dataset.value??'',right=b.querySelector(`[data-column="${key}"]`)?.dataset.value??'';if(left===''&&right!=='')return 1;if(right===''&&left!=='')return-1;const result=type==='number'?Number(left)-Number(right):left.localeCompare(right,document.documentElement.lang,{numeric:true,sensitivity:'base'});return result*direction});rows.forEach(row=>body.appendChild(row));buttons.forEach(item=>{item.removeAttribute('aria-sort');item.querySelector('span').textContent='↕'});button.setAttribute('aria-sort',direction===1?'ascending':'descending');button.querySelector('span').textContent=direction===1?'↑':'↓'}))});
    </script>
</x-app-layout>
