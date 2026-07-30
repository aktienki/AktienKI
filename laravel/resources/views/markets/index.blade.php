<x-app-layout>
    @php
        $countryFlag = fn (string $country): string => strlen($country) === 2
            ? mb_chr(127397 + ord(strtoupper($country[0]))) . mb_chr(127397 + ord(strtoupper($country[1])))
            : '🌐';
    @endphp

    <div id="markets-page" class="ak-body">
        <div id="markets-page-heading" class="z-30 border-b border-[var(--ak-border)] bg-[var(--ak-bg)]/95 py-4 backdrop-blur-xl">
            <div class="ak-container flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-teal-500">aKI Market Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Märkte') }}</h1>
                </div>
                <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                    {{ $exchanges->count() }} {{ __('Exchanges') }}
                </span>
            </div>
        </div>

        <main id="markets-page-content" class="ak-container mt-5 pb-4" x-data="{ active: 'exchanges' }">
            <div id="markets-tabs" class="mb-2 inline-flex items-end gap-1.5">
                <button type="button" @click="active = 'exchanges'" class="ak-market-tab" :class="active === 'exchanges' ? 'ak-market-tab-active' : 'ak-market-tab-idle'">
                    <x-heroicon-o-building-library class="h-3.5 w-3.5" />
                    {{ __('Exchanges') }}
                </button>
                <button type="button" @click="active = 'comments'" class="ak-market-tab" :class="active === 'comments' ? 'ak-market-tab-active' : 'ak-market-tab-idle'">
                    <x-heroicon-o-chat-bubble-left-right class="h-3.5 w-3.5" />
                    {{ __('Kommentare') }}
                </button>
            </div>

            <section id="markets-exchange-pane" x-show="active === 'exchanges'" class="rounded-2xl">
                <div id="markets-exchange-scroll">
                    <table id="exchange-table" class="w-full table-fixed border-separate border-spacing-x-0 border-spacing-y-2 text-left">
                        <colgroup>
                            <col style="width: 12%">
                            <col style="width: 12%">
                            <col style="width: 8%">
                            <col style="width: 10%">
                            <col style="width: 12%">
                            <col style="width: 7%">
                            <col style="width: 6%">
                            <col style="width: 5%">
                            <col style="width: 28%">
                        </colgroup>
                        <thead class="text-[10px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
                            <tr>
                                <th class="px-3 py-3"><button type="button" data-sort="exchange" data-type="text" class="ak-exchange-sort">{{ __('Exchange') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3"><button type="button" data-sort="reference" data-type="text" class="ak-exchange-sort">{{ __('Referenzindex') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3 text-right"><button type="button" data-sort="price" data-type="number" class="ak-exchange-sort ml-auto">{{ __('Kurs') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3 text-right"><button type="button" data-sort="performance" data-type="number" class="ak-exchange-sort ml-auto">{{ __('Performance 1T') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="score" data-type="number" class="ak-exchange-sort mx-auto">{{ __('KI-Score') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="confidence" data-type="number" class="ak-exchange-sort mx-auto">{{ __('Konfidenz') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-3 py-3 text-center">
                                    <button type="button" data-sort="risk" data-type="number" class="ak-exchange-sort mx-auto" title="{{ __('75 % der Aktien dieses Handelsplatzes liegen unter diesem Drawdown-Risikowert.') }}">
                                        {{ __('Risiko P75') }} <span aria-hidden="true">↕</span>
                                    </button>
                                </th>
                                <th class="px-3 py-3 text-center"><button type="button" data-sort="stocks" data-type="number" class="ak-exchange-sort mx-auto">{{ __('Aktien') }} <span aria-hidden="true">↕</span></button></th>
                                <th class="px-4 py-3">{{ __('Signale') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exchanges as $exchange)
                                @php
                                    $score = \App\Support\AiScore::toTen($exchange->average_score);
                                    $confidence = is_numeric($exchange->average_confidence)
                                        ? min(100, max(0, (float) $exchange->average_confidence <= 1 ? (float) $exchange->average_confidence * 100 : (float) $exchange->average_confidence))
                                        : null;
                                    $risk = is_numeric($exchange->risk_p75)
                                        ? min(100, max(0, (float) $exchange->risk_p75 <= 1 ? (float) $exchange->risk_p75 * 100 : (float) $exchange->risk_p75))
                                        : null;
                                    $scoreClass = $score >= 6.5 ? 'text-emerald-500' : ($score < 4.5 ? 'text-rose-500' : 'text-amber-500');
                                    $target = route('stocks.index', ['exchange' => $exchange->code]);
                                @endphp
                                <tr onclick="window.location.href=@js($target)" class="group cursor-pointer text-sm text-[var(--ak-text)]">
                                    <td colspan="9" class="p-0">
                                        <div class="ak-exchange-row-grid">
                                            <div data-column="exchange" data-value="{{ $exchange->code }}" class="flex items-center gap-3 px-4 py-4">
                                                <span class="text-xl">{{ $countryFlag($exchange->country) }}</span>
                                                <div><p class="font-black">{{ $exchange->code }}</p><p class="text-[10px] text-[var(--ak-muted)]">{{ $exchange->name }}</p></div>
                                            </div>
                                            <div data-column="reference" data-value="{{ $exchange->reference_name }}" class="px-4 py-4"><p class="font-bold">{{ $exchange->reference_name ?: '—' }}</p><p class="text-[10px] text-[var(--ak-muted)]">{{ $exchange->reference_symbol }}</p></div>
                                            <div data-column="price" data-value="{{ is_numeric($exchange->market_price) ? $exchange->market_price : '' }}" class="px-4 py-4 text-right font-black tabular-nums">{{ is_numeric($exchange->market_price) ? number_format($exchange->market_price, 2, ',', '.') : '—' }} <small class="text-[9px] text-[var(--ak-muted)]">{{ $exchange->market_currency }}</small></div>
                                            <div data-column="performance" data-value="{{ is_numeric($exchange->market_change) ? $exchange->market_change : '' }}" class="px-4 py-4 text-right font-black tabular-nums {{ ($exchange->market_change ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ is_numeric($exchange->market_change) ? (($exchange->market_change > 0 ? '+' : '') . number_format($exchange->market_change, 2, ',', '.') . ' %') : '—' }}</div>
                                            <div data-column="score" data-value="{{ $score ?? '' }}" class="px-4 py-4">
                                                @if ($score !== null)
                                                    <div class="mb-1.5 flex items-baseline justify-between">
                                                        <strong class="font-black {{ $scoreClass }}">{{ number_format($score, 1, ',', '.') }}</strong>
                                                        <span class="text-[9px] font-bold text-[var(--ak-muted)]">/ 10</span>
                                                    </div>
                                                    <x-dashboard.score-stripes :percent="$score * 10" />
                                                @else
                                                    <span class="block text-center text-[var(--ak-muted)]">—</span>
                                                @endif
                                            </div>
                                            <div data-column="confidence" data-value="{{ $confidence ?? '' }}" class="flex items-center justify-center px-3 py-2">
                                                @if ($confidence !== null)
                                                    @php
                                                        $confidenceColor = match (true) {
                                                            $confidence < 40 => '#ef4444',
                                                            $confidence < 60 => '#f97316',
                                                            $confidence < 75 => '#eab308',
                                                            $confidence < 88 => '#84cc16',
                                                            default => '#10b981',
                                                        };
                                                    @endphp
                                                    <div
                                                        class="ak-confidence-donut"
                                                        style="--confidence: {{ $confidence }}%; --confidence-color: {{ $confidenceColor }}"
                                                        role="meter"
                                                        aria-label="{{ __('Konfidenz') }}"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                        aria-valuenow="{{ round($confidence) }}"
                                                    >
                                                        <span>{{ number_format($confidence, 0, ',', '.') }}<small>%</small></span>
                                                    </div>
                                                @else
                                                    <span class="text-[var(--ak-muted)]">—</span>
                                                @endif
                                            </div>
                                            <div data-column="risk" data-value="{{ $risk ?? '' }}" class="flex flex-col items-center justify-center px-3 py-2 text-center">
                                                @if ($risk !== null)
                                                    @php
                                                        $riskColor = match (true) {
                                                            $risk < 10 => '#10b981',
                                                            $risk < 20 => '#84cc16',
                                                            $risk < 30 => '#eab308',
                                                            $risk < 40 => '#f97316',
                                                            default => '#ef4444',
                                                        };
                                                    @endphp
                                                    <div
                                                        class="ak-risk-donut"
                                                        style="--risk: {{ $risk }}%; --risk-color: {{ $riskColor }}"
                                                        role="meter"
                                                        aria-label="{{ __('Risiko P75') }}"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                        aria-valuenow="{{ round($risk) }}"
                                                    >
                                                        <span>{{ number_format($risk, 0, ',', '.') }}<small>%</small><em>P75</em></span>
                                                    </div>
                                                @else
                                                    <span class="text-[var(--ak-muted)]">—</span>
                                                @endif
                                            </div>
                                            <div data-column="stocks" data-value="{{ $exchange->instrument_count }}" class="px-4 py-4 text-center font-black">{{ $exchange->instrument_count }}</div>
                                            <div class="flex items-center px-4 py-4">
                                                <div class="ak-signal-grid">
                                            @if ((int) $exchange->sell_count > 0)
                                            <a
                                                href="{{ route('stocks.index', ['exchange' => $exchange->code, 'signal' => 'SELL']) }}"
                                                onclick="event.stopPropagation()"
                                                class="ak-signal-button ak-signal-sell"
                                            >
                                                <span>{{ __('Sell') }}</span><b>{{ $exchange->sell_count }}</b>
                                            </a>
                                            @else
                                                <span class="ak-signal-button ak-signal-sell ak-signal-empty" aria-hidden="true"></span>
                                            @endif
                                            @if ((int) $exchange->hold_count > 0)
                                            <a
                                                href="{{ route('stocks.index', ['exchange' => $exchange->code, 'signal' => 'HOLD']) }}"
                                                onclick="event.stopPropagation()"
                                                class="ak-signal-button ak-signal-hold"
                                            >
                                                <span>{{ __('Hold') }}</span><b>{{ $exchange->hold_count }}</b>
                                            </a>
                                            @else
                                                <span class="ak-signal-button ak-signal-hold ak-signal-empty" aria-hidden="true"></span>
                                            @endif
                                            @if ((int) $exchange->watch_count > 0)
                                            <a
                                                href="{{ route('stocks.index', ['exchange' => $exchange->code, 'signal' => 'WATCH']) }}"
                                                onclick="event.stopPropagation()"
                                                class="ak-signal-button ak-signal-watch"
                                            >
                                                <span>{{ __('Watch') }}</span><b>{{ $exchange->watch_count }}</b>
                                            </a>
                                            @else
                                                <span class="ak-signal-button ak-signal-watch ak-signal-empty" aria-hidden="true"></span>
                                            @endif
                                            @if ((int) $exchange->buy_count > 0)
                                            <a
                                                href="{{ route('stocks.index', ['exchange' => $exchange->code, 'signal' => 'BUY']) }}"
                                                onclick="event.stopPropagation()"
                                                class="ak-signal-button ak-signal-buy"
                                            >
                                                <span>{{ __('Buy') }}</span><b>{{ $exchange->buy_count }}</b>
                                            </a>
                                            @else
                                                <span class="ak-signal-button ak-signal-buy ak-signal-empty" aria-hidden="true"></span>
                                            @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Exchange-Daten vorhanden.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="markets-comments-pane" x-cloak x-show="active === 'comments'">
                @if ($marketAnalysis)
                    @php
                        $marketOutlook = strtoupper((string) $marketAnalysis->market_outlook);
                        $marketOutlookClass = match ($marketOutlook) {
                            'BULLISH' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-500',
                            'BEARISH' => 'border-rose-500/25 bg-rose-500/10 text-rose-500',
                            default => 'border-amber-500/25 bg-amber-500/10 text-amber-500',
                        };
                    @endphp
                    <div class="grid items-stretch gap-4 lg:grid-cols-2">
                        <article class="ak-card ak-card-static flex min-h-[360px] flex-col p-6">
                            <div class="ak-market-comment-heading flex flex-wrap items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-teal-500/20 bg-teal-500/10 text-teal-500">
                                        <x-heroicon-o-sparkles class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-[.16em] text-teal-500">{{ __('Zusammenfassung') }}</p>
                                        <h2 class="mt-1 text-lg font-black leading-6 text-[var(--ak-text)]">{{ $marketAnalysis->headline }}</h2>
                                    </div>
                                </div>
                                <span class="rounded-lg border px-3 py-2 text-xs font-black {{ $marketOutlookClass }}">{{ __($marketOutlook) }}</span>
                            </div>
                            <p class="ak-comment-copy mt-5 flex-1 border-t border-[var(--ak-border)] pt-5 text-sm leading-7">{{ $marketAnalysis->executive_summary }}</p>
                            <div class="mt-5 flex items-center justify-between border-t border-[var(--ak-border)] pt-4 text-[10px] font-bold text-[var(--ak-muted)]">
                                <span>{{ \Carbon\Carbon::parse($marketAnalysis->analysis_date)->format('d.m.Y') }}</span>
                                <span>{{ __('Konfidenz') }} {{ $marketAnalysis->confidence }} %</span>
                            </div>
                        </article>

                        <article class="ak-card ak-card-static flex min-h-[360px] flex-col p-6">
                            <div class="ak-market-comment-heading flex items-start gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-teal-500/20 bg-teal-500/10 text-teal-500">
                                    <x-heroicon-o-chart-bar-square class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[.16em] text-teal-500">{{ __('Analyse') }}</p>
                                    <h2 class="mt-1 text-lg font-black leading-6 text-[var(--ak-text)]">{{ __('Ausführliche Marktanalyse') }}</h2>
                                </div>
                            </div>
                            <p class="ak-comment-copy mt-5 flex-1 border-t border-[var(--ak-border)] pt-5 text-sm leading-7">{{ $marketAnalysis->breadth_analysis }}</p>
                            <div class="mt-5 flex items-center justify-between border-t border-[var(--ak-border)] pt-4 text-[10px] font-bold text-[var(--ak-muted)]">
                                <span>{{ __('Marktbreite') }}</span>
                                <span>{{ \Carbon\Carbon::parse($marketAnalysis->analysis_date)->format('d.m.Y') }}</span>
                            </div>
                        </article>
                    </div>
                @else
                    <article class="ak-card grid min-h-[320px] place-items-center text-sm text-[var(--ak-muted)]">{{ __('Noch kein Marktkommentar verfügbar.') }}</article>
                @endif
            </section>
        </main>

        <style>
            #markets-page {
                display: flex;
                flex-direction: column;
                height: calc(100dvh - 73px - 1rem) !important;
                min-height: 0 !important;
                overflow: hidden !important;
                padding-bottom: 0 !important;
            }

            #markets-page-heading,
            #markets-tabs {
                flex: 0 0 auto;
            }

            #markets-page-content {
                display: flex;
                flex: 1 1 auto;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
            }

            #markets-exchange-pane,
            #markets-comments-pane {
                flex: 1 1 auto;
                min-height: 0;
                overflow: hidden;
            }

            #markets-exchange-scroll,
            #markets-comments-pane {
                height: 100%;
                overflow: auto;
                overscroll-behavior: contain;
            }

            #markets-comments-pane .ak-market-comment-heading {
                flex: 0 0 82px;
                height: 82px;
                min-height: 82px;
                overflow: hidden;
            }

            #markets-comments-pane .ak-comment-copy {
                color: color-mix(in srgb, var(--ak-text) 86%, var(--ak-muted) 14%);
            }

            .ak-market-tab {
                display: inline-flex;
                width: 116px;
                height: 34px;
                align-items: center;
                justify-content: center;
                gap: 6px;
                border: 1px solid var(--ak-border);
                border-bottom-width: 2px;
                border-radius: 9px 9px 5px 5px;
                padding: 0 10px;
                font-size: 10px;
                font-weight: 900;
                letter-spacing: .035em;
                box-shadow: 0 4px 10px rgba(3, 7, 18, .1);
                transition: none;
            }

            .ak-market-tab-active {
                border-color: color-mix(in srgb, rgb(20 184 166) 38%, var(--ak-border));
                border-bottom-color: rgb(20 184 166);
                background: var(--ak-card);
                color: rgb(20 184 166);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), 0 4px 12px rgba(3, 7, 18, .14);
            }

            .ak-market-tab-idle {
                background: color-mix(in srgb, var(--ak-card) 72%, var(--ak-bg) 28%);
                color: var(--ak-muted);
            }

            #exchange-table {
                min-width: 1220px;
            }

            #exchange-table thead th {
                position: sticky;
                top: 0;
                z-index: 20;
                background: var(--ak-surface) !important;
                box-shadow: 0 1px 0 var(--ak-border), 0 8px 16px color-mix(in srgb, var(--ak-bg) 78%, transparent);
            }

            #exchange-table thead {
                background: var(--ak-surface) !important;
            }

            #exchange-table tbody tr {
                background: transparent;
            }

            #exchange-table tbody td {
                border: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }

            .ak-exchange-row-grid {
                display: grid;
                grid-template-columns: 12% 12% 8% 10% 12% 7% 6% 5% 28%;
                align-items: stretch;
                height: 72px;
                overflow: hidden;
                border: 1px solid var(--ak-border);
                border-radius: 16px;
                background: var(--ak-card);
                box-shadow: 0 5px 14px rgba(5, 10, 28, .14);
            }

            .ak-exchange-row-grid > * {
                min-width: 0;
                overflow: hidden;
            }

            .ak-exchange-row-grid p {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #exchange-table tbody tr:nth-child(even) .ak-exchange-row-grid {
                background: var(--ak-card-hover);
            }

            #exchange-table tbody tr:hover .ak-exchange-row-grid {
                background: color-mix(in srgb, var(--ak-card-hover) 90%, rgb(45 212 191) 10%);
            }

            .ak-confidence-donut {
                position: relative;
                display: grid;
                width: 48px;
                height: 48px;
                flex: 0 0 48px;
                place-items: center;
                border-radius: 999px;
                background: conic-gradient(
                    var(--confidence-color) 0 var(--confidence),
                    rgba(148, 163, 184, .16) var(--confidence) 100%
                );
                box-shadow: 0 0 14px color-mix(in srgb, var(--confidence-color) 18%, transparent);
            }

            .ak-risk-donut {
                position: relative;
                display: grid;
                width: 48px;
                height: 48px;
                flex: 0 0 48px;
                place-items: center;
                border-radius: 999px;
                background: conic-gradient(
                    var(--risk-color) 0 var(--risk),
                    rgba(148, 163, 184, .16) var(--risk) 100%
                );
                box-shadow: 0 0 14px color-mix(in srgb, var(--risk-color) 18%, transparent);
            }

            .ak-confidence-donut::after {
                position: absolute;
                inset: 5px;
                border-radius: inherit;
                background: var(--ak-card);
                content: '';
            }

            .ak-risk-donut::after {
                position: absolute;
                inset: 5px;
                border-radius: inherit;
                background: var(--ak-card);
                content: '';
            }

            #exchange-table tbody tr:nth-child(even) .ak-confidence-donut::after {
                background: var(--ak-card-hover);
            }

            #exchange-table tbody tr:nth-child(even) .ak-risk-donut::after {
                background: var(--ak-card-hover);
            }

            .ak-confidence-donut span {
                position: relative;
                z-index: 1;
                color: var(--ak-text);
                font-size: 11px;
                font-weight: 900;
                line-height: 1;
            }

            .ak-risk-donut span {
                position: relative;
                z-index: 1;
                display: grid;
                grid-template-columns: auto auto;
                place-items: center;
                color: var(--ak-text);
                font-size: 11px;
                font-style: normal;
                font-weight: 900;
                line-height: 1;
            }

            .ak-confidence-donut small {
                margin-left: 1px;
                color: var(--ak-muted);
                font-size: 7px;
            }

            .ak-risk-donut small {
                margin-left: 1px;
                color: var(--ak-muted);
                font-size: 7px;
            }

            .ak-risk-donut em {
                grid-column: 1 / -1;
                margin-top: 2px;
                color: var(--ak-muted);
                font-size: 6px;
                font-style: normal;
                letter-spacing: .08em;
            }

            .ak-signal-grid {
                display: grid;
                grid-template-columns: repeat(4, 80px);
                gap: 6px;
                width: max-content;
                font-size: 10px;
                font-weight: 900;
            }

            .ak-signal-button {
                display: flex;
                height: 30px;
                width: 100%;
                align-items: center;
                justify-content: space-between;
                gap: 5px;
                border-style: solid;
                border-width: 1px;
                border-radius: 8px;
                padding: 0 8px;
                color: white;
                white-space: nowrap;
            }

            .ak-signal-button b {
                min-width: 21px;
                border: 0;
                border-radius: 0;
                padding: 0;
                background: transparent;
                color: inherit;
                text-align: center;
            }

            .ak-signal-empty {
                pointer-events: none;
                opacity: 0;
                user-select: none;
            }

            .ak-signal-sell {
                border-color: rgba(251, 113, 133, .72);
                background: rgba(225, 29, 72, .58);
                box-shadow: 0 0 12px rgba(244, 63, 94, .13);
            }

            .ak-signal-hold {
                border-color: rgba(252, 211, 77, .72);
                background: rgba(217, 119, 6, .56);
                box-shadow: 0 0 12px rgba(245, 158, 11, .12);
            }

            .ak-signal-watch {
                border-color: rgba(190, 242, 100, .68);
                background: rgba(101, 163, 13, .52);
                box-shadow: 0 0 12px rgba(132, 204, 22, .11);
            }

            .ak-signal-buy {
                border-color: rgba(110, 231, 183, .82);
                background: rgba(5, 150, 105, .72);
                box-shadow: 0 0 14px rgba(16, 185, 129, .20);
            }

            .ak-exchange-sort {
                display: flex;
                align-items: center;
                gap: .35rem;
                white-space: nowrap;
            }

            .ak-exchange-sort span {
                color: var(--ak-muted);
                font-size: .7rem;
                opacity: .55;
            }

            .ak-exchange-sort[aria-sort="ascending"] span,
            .ak-exchange-sort[aria-sort="descending"] span {
                color: rgb(20 184 166);
                opacity: 1;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const table = document.getElementById('exchange-table');
                if (!table) return;

                const body = table.tBodies[0];
                const buttons = Array.from(table.querySelectorAll('[data-sort]'));
                let activeKey = null;
                let direction = 1;

                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const key = button.dataset.sort;
                        const type = button.dataset.type;
                        direction = activeKey === key ? direction * -1 : 1;
                        activeKey = key;

                        const rows = Array.from(body.rows).filter((row) => row.querySelector(`[data-column="${key}"]`));
                        rows.sort((leftRow, rightRow) => {
                            const left = leftRow.querySelector(`[data-column="${key}"]`)?.dataset.value ?? '';
                            const right = rightRow.querySelector(`[data-column="${key}"]`)?.dataset.value ?? '';

                            if (left === '' && right !== '') return 1;
                            if (right === '' && left !== '') return -1;

                            const comparison = type === 'number'
                                ? Number(left) - Number(right)
                                : left.localeCompare(right, document.documentElement.lang, { numeric: true, sensitivity: 'base' });

                            return comparison * direction;
                        });

                        rows.forEach((row) => body.appendChild(row));
                        buttons.forEach((item) => {
                            item.removeAttribute('aria-sort');
                            item.querySelector('span').textContent = '↕';
                        });
                        button.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
                        button.querySelector('span').textContent = direction === 1 ? '↑' : '↓';
                    });
                });
            });
        </script>
    </div>
</x-app-layout>
