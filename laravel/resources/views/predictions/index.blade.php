<x-app-layout>
    <div id="predictions-page" class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300 shadow-[0_0_22px_rgba(245,158,11,.08)]">
                    <x-heroicon-o-chart-bar class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ __('Prognosen') }}</h1>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Historische KI-Prognosen, Modellwerte und Validierungsergebnisse.') }}</p>
                    <button type="button" data-open-prediction-heatmap class="mt-2 inline-flex h-8 items-center gap-2 rounded-lg border border-teal-500/25 bg-teal-500/10 px-3 text-[10px] font-black uppercase tracking-wide text-teal-700 transition hover:border-teal-500/45 hover:bg-teal-500/15">
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" />{{ __('Historische Erfolgs-Heatmap') }}
                    </button>
                </div>
            </div>

            <div class="grid w-full grid-cols-4 gap-2 xl:w-auto">
                @foreach ([
                    [__('Prognosen'), (int) ($summary->total ?? 0)],
                    [__('Aktien'), (int) ($summary->instruments ?? 0)],
                    [__('Validiert'), (int) ($summary->validated ?? 0)],
                    [__('Letzter Lauf'), $summary?->latest ? \Illuminate\Support\Carbon::parse($summary->latest)->format('d.m. H:i') : '—'],
                ] as [$label, $value])
                    <div class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-2 py-2 sm:px-3 xl:min-w-28">
                        <p class="truncate text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)] sm:text-[9px] sm:tracking-[.12em]">{{ $label }}</p>
                        <p class="mt-1 truncate text-xs font-black tabular-nums sm:text-sm">{{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <form
                method="GET"
                action="{{ route('predictions.index') }}"
                x-data="{ searchTimer: null, submitSearch() { window.clearTimeout(this.searchTimer); this.searchTimer = window.setTimeout(() => this.$root.requestSubmit(), 450) } }"
                class="flex shrink-0 flex-nowrap gap-2 overflow-x-auto border-b border-[var(--ak-border)] p-3"
            >
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <label class="relative min-w-[220px] flex-1">
                    <span class="sr-only">{{ __('Aktie suchen') }}</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" />
                    <input name="q" value="{{ request('q') }}" @input="submitSearch()" placeholder="{{ __('Symbol oder Unternehmen') }}" class="ak-input h-10 w-full pl-9 pr-3 text-sm">
                </label>
                <select name="ai_type" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle KI-Typen') }}</option>
                    @foreach ($aiTypes as $aiType)
                        <option value="{{ $aiType }}" @selected(request('ai_type') === $aiType)>{{ ucfirst((string) $aiType) }} KI</option>
                    @endforeach
                </select>
                <select name="model" @change="$root.requestSubmit()" class="ak-input h-10 w-44 shrink-0 text-sm">
                    <option value="">{{ __('Alle Modelle') }}</option>
                    @foreach ($models as $model)
                        <option value="{{ $model->id }}" @selected((int) request('model') === (int) $model->id)>{{ $model->public_alias }}</option>
                    @endforeach
                </select>
                <select name="quality_tier" @change="$root.requestSubmit()" class="ak-input h-10 w-48 shrink-0 text-sm">
                    <option value="">{{ __('Alle Modellstufen') }}</option>
                    @foreach ($qualityTiers as $qualityTier)
                        <option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>
                    @endforeach
                </select>
                <select name="signal" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle Signale') }}</option>
                    @foreach (['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)
                        @continue(! $signals->contains($signal))
                        <option value="{{ $signal }}" @selected(strtoupper((string) request('signal')) === $signal)>{{ $signal }}</option>
                    @endforeach
                </select>
                <select name="score_min" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle KI-Scores') }}</option>
                    @foreach ([8 => '8,0', 7 => '7,0', 6 => '6,0', 5 => '5,0'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('score_min') === (string) $value)>{{ __('KI-Score ab') }} {{ $label }}</option>
                    @endforeach
                </select>
                <select name="confidence_min" @change="$root.requestSubmit()" class="ak-input h-10 w-44 shrink-0 text-sm">
                    <option value="">{{ __('Alle Konfidenzen') }}</option>
                    @foreach ([90, 80, 70, 60, 50] as $value)
                        <option value="{{ $value }}" @selected((string) request('confidence_min') === (string) $value)>{{ __('Konfidenz ab') }} {{ $value }} %</option>
                    @endforeach
                </select>
                <select name="validation" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle Validierungen') }}</option>
                    @if ($validationStates->contains('validated'))
                        <option value="validated" @selected(request('validation') === 'validated')>{{ __('Validiert') }}</option>
                    @endif
                    @if ($validationStates->contains('pending'))
                        <option value="pending" @selected(request('validation') === 'pending')>{{ __('Ausstehend') }}</option>
                    @endif
                </select>
                <div class="flex w-48 shrink-0">
                    <a href="{{ route('predictions.index') }}" class="inline-flex h-10 w-full shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] px-3 text-xs font-bold text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700">
                        <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Filter zurücksetzen') }}
                    </a>
                </div>
            </form>

            <div id="predictions-table-scroll" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden">
                @php
                    $sortUrl = fn (string $column): string => route('predictions.index', array_merge(
                        request()->except('page'),
                        [
                            'sort' => $column,
                            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        ],
                    ));
                    $sortIndicator = fn (string $column): string => $sort === $column
                        ? ($direction === 'asc' ? '↑' : '↓')
                        : '↕';
                @endphp
                <table class="ak-stocks-table w-full table-fixed border-separate border-spacing-0 text-left text-[11px]">
                    <colgroup>
                        <col style="width: 3.5%;">
                        <col style="width: 9%;">
                        <col style="width: 15%;">
                        <col style="width: 11%;">
                        <col style="width: 6%;">
                        <col style="width: 8%;">
                        <col style="width: 7%;">
                        <col style="width: 7%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                        <col style="width: 7%;">
                        <col style="width: 8.5%;">
                    </colgroup>
                    <thead class="sticky top-0 z-20 bg-[#151426] text-[10px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)] shadow-[0_1px_0_var(--ak-border),0_8px_18px_rgba(0,0,0,.22)]">
                        <tr>
                            <th class="border-b border-[var(--ak-border)] px-3 py-3 text-center" aria-label="{{ __('Watchlist') }}">
                                <x-heroicon-o-star class="mx-auto h-4 w-4 text-[var(--ak-muted)]" />
                            </th>
                            @foreach ([
                                ['time', __('Zeitpunkt'), 'text-left'],
                                ['stock', __('Aktie'), 'text-left'],
                                ['model', __('Modell'), 'text-left'],
                                ['signal', __('Signal'), 'text-center'],
                                ['price', __('Kurs'), 'text-right'],
                                ['return_5d', __('5 Tage'), 'text-right'],
                                ['return_20d', __('20 Tage'), 'text-right'],
                                ['score', __('KI-Score'), 'text-center'],
                                ['confidence', __('Konfidenz'), 'text-center'],
                                ['risk', __('Risiko'), 'text-center'],
                                ['validation', __('Validierung'), 'text-center'],
                            ] as [$column, $heading, $alignment])
                                <th class="border-b border-[var(--ak-border)] px-2 py-3 {{ $alignment }}">
                                    <a href="{{ $sortUrl($column) }}" class="inline-flex max-w-full items-center gap-1 whitespace-nowrap transition hover:text-teal-200 {{ $sort === $column ? 'text-teal-200' : '' }}">
                                        <span class="truncate">{{ $heading }}</span>
                                        <span class="inline-block w-3 shrink-0 text-center text-[11px] {{ $sort === $column ? 'text-teal-200' : 'text-slate-600' }}">{{ $sortIndicator($column) }}</span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($predictions as $prediction)
                            @php
                                $signal = strtoupper((string) ($prediction->personalized_signal ?? 'HOLD'));
                                $signalClass = match ($signal) {
                                    'BUY' => 'border-[#2b8f7b] bg-[#197864] text-white shadow-[0_0_10px_rgba(25,120,100,.20)] ring-1 ring-emerald-200/20',
                                    'WATCH' => 'border-[#789545] bg-[#657f39] text-white shadow-[0_0_8px_rgba(101,127,57,.14)]',
                                    'SELL' => 'border-[#bd5b6c] bg-[#a94759] text-white shadow-[0_0_8px_rgba(169,71,89,.16)]',
                                    default => 'border-[#bd8737] bg-[#a97429] text-white shadow-[0_0_8px_rgba(169,116,41,.15)]',
                                };
                                $currency = $prediction->currency ?: 'EUR';
                                $score = is_numeric($prediction->score_10) ? max(0, min(10, (float) $prediction->score_10)) : null;
                                $scorePercent = is_numeric($prediction->score_10) ? max(0, min(100, (float) $prediction->score_10 * 10)) : null;
                                $confidencePercent = is_numeric($prediction->confidence_percent) ? max(0, min(100, (float) $prediction->confidence_percent)) : null;
                                $riskPercent = is_numeric($prediction->risk_percent) ? max(0, min(100, (float) $prediction->risk_percent)) : null;
                                $confidenceColor = match (true) {
                                    $confidencePercent === null => '#64748b',
                                    $confidencePercent < 40 => '#ef4444',
                                    $confidencePercent < 60 => '#f97316',
                                    $confidencePercent < 75 => '#eab308',
                                    $confidencePercent < 88 => '#84cc16',
                                    default => '#10b981',
                                };
                                $riskColor = match (true) {
                                    $riskPercent === null => '#64748b',
                                    $riskPercent < 10 => '#10b981',
                                    $riskPercent < 20 => '#84cc16',
                                    $riskPercent < 30 => '#eab308',
                                    $riskPercent < 40 => '#f97316',
                                    default => '#ef4444',
                                };
                                $modelTierCode = $prediction->model_quality_tier_code ?: 'unqualified';
                                $modelTierName = $prediction->model_quality_tier_name ? __($prediction->model_quality_tier_name) : __('Nicht qualifiziert');
                                $modelTierClass = match ($modelTierCode) {
                                    'top' => 'ak-model-tier-top',
                                    'strong' => 'ak-model-tier-strong',
                                    'solid' => 'ak-model-tier-solid',
                                    'test' => 'ak-model-tier-test',
                                    default => 'ak-model-tier-unqualified',
                                };
                                $predictionWatchlistIds = $watchlistMemberships->get((int) $prediction->instrument_id, collect());
                                $isWatched = $predictionWatchlistIds->isNotEmpty();
                            @endphp
                            <tr onclick="window.location='{{ route('stocks.show', ['symbol' => $prediction->symbol, 'prediction' => $prediction->id, 'return_to' => request()->getRequestUri()]) }}'" class="cursor-pointer transition hover:bg-teal-500/[.075]">
                                <td onclick="event.stopPropagation()" class="relative border-b border-[var(--ak-border)] px-3 py-3 text-center">
                                    @if ($userWatchlists->count() === 1)
                                        @php $singleWatchlist = $userWatchlists->first(); @endphp
                                        <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $prediction->instrument_id]) }}" data-prediction-watchlist-form>
                                            @csrf
                                            <input type="hidden" name="prediction_id" value="{{ $prediction->id }}">
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}" title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}">
                                                @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                            </button>
                                        </form>
                                    @elseif ($userWatchlists->count() > 1)
                                        <button
                                            type="button"
                                            data-open-watchlist-picker
                                            data-instrument-id="{{ $prediction->instrument_id }}"
                                            data-prediction-id="{{ $prediction->id }}"
                                            data-symbol="{{ $prediction->symbol }}"
                                            data-name="{{ $prediction->name }}"
                                            data-memberships='@json($predictionWatchlistIds->values())'
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}"
                                            title="{{ __('Watchlist auswählen') }}"
                                        >
                                            @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                        </button>
                                    @else
                                        <a href="{{ route('watchlists.index') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition hover:bg-teal-500/10 hover:text-teal-700" title="{{ __('Zuerst Watchlist erstellen') }}">
                                            <x-heroicon-o-star class="h-5 w-5" />
                                        </a>
                                    @endif
                                </td>
                                <td class="truncate border-b border-[var(--ak-border)] px-2 py-3 tabular-nums text-[var(--ak-muted)]">{{ $prediction->prediction_time ? \Illuminate\Support\Carbon::parse($prediction->prediction_time)->format('d.m.Y H:i') : '—' }}</td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-3">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <img src="{{ route('stocks.icon', $prediction->instrument_id) }}" alt="" class="h-7 w-7 shrink-0 rounded-lg object-contain">
                                        <div class="min-w-0"><p class="truncate font-black text-teal-700">{{ $prediction->symbol }}</p><p class="mt-0.5 truncate text-[9px] text-[var(--ak-muted)]">{{ $prediction->name }}</p></div>
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <p class="truncate font-bold text-[var(--ak-text)]">{{ $prediction->model_alias ?: ucfirst((string) $prediction->ai_type) }}</p>
                                    <div class="mt-1 flex min-w-0 items-center gap-1">
                                        <span class="ak-model-tier {{ $modelTierClass }}">{{ $modelTierName }}</span>
                                        @if (is_numeric($prediction->model_quality_score))
                                            <small class="shrink-0 text-[8px] font-bold text-[var(--ak-muted)]">{{ number_format((float) $prediction->model_quality_score * 100, 0, ',', '.') }} %</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-3 text-center"><span class="inline-flex h-7 w-full max-w-16 items-center justify-center rounded-lg border px-1 text-[9px] font-black {{ $signalClass }}">{{ $signal }}</span></td>
                                <td class="truncate border-b border-[var(--ak-border)] px-2 py-3 text-right font-bold tabular-nums text-[var(--ak-text)]">{{ is_numeric($prediction->current_price) ? number_format($prediction->current_price, 2, ',', '.').' '.$currency : '—' }}</td>
                                @foreach (['expected_return_5d', 'expected_return_20d'] as $returnField)
                                    @php $return = $prediction->{$returnField}; @endphp
                                    <td class="truncate border-b border-[var(--ak-border)] px-2 py-3 text-right font-black tabular-nums {{ is_numeric($return) ? ($return >= 0 ? 'text-emerald-400' : 'text-rose-400') : 'text-[var(--ak-muted)]' }}">{{ is_numeric($return) ? ($return >= 0 ? '+' : '').number_format($return, 2, ',', '.').' %' : '—' }}</td>
                                @endforeach
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    @if ($score !== null)
                                        <div class="flex h-full flex-col justify-center">
                                            <div class="mb-1 flex items-baseline justify-between"><strong class="text-xs font-black text-[var(--ak-text)]">{{ number_format($score, 1, ',', '.') }}</strong><small class="text-[8px] text-[var(--ak-muted)]">/ 10</small></div>
                                            <x-dashboard.score-stripes :percent="$scorePercent" />
                                        </div>
                                    @else<span class="block text-center text-[var(--ak-muted)]">—</span>@endif
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex h-full items-center justify-center">
                                        @if ($confidencePercent !== null)
                                            <div class="ak-prediction-donut" style="--value:{{ $confidencePercent }}%;--color:{{ $confidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuenow="{{ round($confidencePercent) }}">
                                                <span>{{ number_format($confidencePercent, 0, ',', '.') }}<small>%</small></span>
                                            </div>
                                        @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-2 py-2">
                                    <div class="flex h-full items-center justify-center">
                                        @if ($riskPercent !== null)
                                            <div class="ak-prediction-donut" style="--value:{{ $riskPercent }}%;--color:{{ $riskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuenow="{{ round($riskPercent) }}">
                                                <span>{{ number_format($riskPercent, 0, ',', '.') }}<small>%</small></span>
                                            </div>
                                        @else<span class="text-[var(--ak-muted)]">—</span>@endif
                                    </div>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-1 py-3 text-center">
                                    @if ($prediction->validated_at)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold {{ $prediction->direction_correct === true ? 'text-emerald-300' : ($prediction->direction_correct === false ? 'text-rose-300' : 'text-slate-300') }}"><x-heroicon-o-check-badge class="h-4 w-4" />{{ __('Validiert') }}</span>
                                    @else
                                        <span class="text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Ausstehend') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-6 py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Prognosen für diese Filter gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <style>
                #predictions-page {
                    --ak-muted: #b8c2d4;
                }

                :root[data-theme="light"] #predictions-page {
                    --ak-muted: #64748b;
                }

                .ak-prediction-donut {
                    position: relative;
                    display: grid;
                    width: 44px;
                    height: 44px;
                    flex: 0 0 44px;
                    place-items: center;
                    border-radius: 999px;
                    background: conic-gradient(var(--color) 0 var(--value), rgba(148, 163, 184, .16) var(--value) 100%);
                    box-shadow: 0 0 12px color-mix(in srgb, var(--color) 16%, transparent);
                }

                .ak-prediction-donut::after {
                    position: absolute;
                    inset: 5px;
                    border-radius: inherit;
                    background: var(--ak-card);
                    content: '';
                }

                .ak-prediction-donut span {
                    position: relative;
                    z-index: 1;
                    color: var(--ak-text);
                    font-size: 10px;
                    font-weight: 900;
                    line-height: 1;
                }

                .ak-prediction-donut small {
                    margin-left: 1px;
                    color: var(--ak-muted);
                    font-size: 7px;
                }
            </style>

        </section>
    </div>

    <div id="prediction-heatmap-modal" class="fixed inset-0 z-[95] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="prediction-heatmap-title">
        <section class="flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-violet-400/25 bg-[#171325] shadow-2xl shadow-black/70">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Historische Validierung') }}</p>
                    <h2 id="prediction-heatmap-title" class="mt-1 text-xl font-black text-white">{{ __('Erfolg nach KI-Score und Konfidenz') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Trefferquote validierter Prognosen; aktuelle Modell- und Signalfilter werden berücksichtigt.') }}</p>
                </div>
                <button type="button" data-close-prediction-heatmap class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-white/5 hover:text-white" aria-label="{{ __('Schließen') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-auto p-5">
                <div class="mx-auto min-w-[720px] max-w-4xl">
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <span class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Y: Konfidenz') }}</span>
                        <div class="flex items-center gap-3 text-[9px] font-bold text-slate-400">
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-rose-400/25"></i>&lt; 45 %</span>
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-amber-300/20"></i>45–54 %</span>
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-emerald-400/20"></i>≥ 55 %</span>
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-slate-500/15"></i>{{ __('< 5 Datenpunkte') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-[52px_repeat(10,minmax(54px,1fr))] gap-1.5">
                        @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                            <div class="flex items-center justify-end pr-2 text-[10px] font-bold tabular-nums text-slate-500">
                                {{ $confidenceBucket * 10 }}–{{ ($confidenceBucket + 1) * 10 }} %
                            </div>
                            @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                @php
                                    $cell = $heatmap->get($scoreBucket.'-'.$confidenceBucket);
                                    $samples = (int) ($cell->samples ?? 0);
                                    $hitRate = is_numeric($cell?->hit_rate) ? (float) $cell->hit_rate : null;
                                    $averageReturn = is_numeric($cell?->average_return) ? (float) $cell->average_return : null;
                                    $cellClass = $samples < 5 || $hitRate === null
                                        ? 'border-white/[.05] bg-slate-500/[.08] text-slate-600'
                                        : ($hitRate >= 65
                                            ? 'border-emerald-300/30 bg-emerald-400/28 text-emerald-100'
                                            : ($hitRate >= 55
                                                ? 'border-emerald-400/20 bg-emerald-400/15 text-emerald-200'
                                                : ($hitRate >= 45
                                                    ? 'border-amber-300/20 bg-amber-300/12 text-amber-200'
                                                    : 'border-rose-400/20 bg-rose-400/15 text-rose-200')));
                                @endphp
                                <div
                                    class="group relative flex aspect-square min-h-14 items-center justify-center rounded-lg border {{ $cellClass }} transition hover:z-20 hover:scale-105 hover:border-white/30"
                                    title="{{ __('Score :scoreFrom–:scoreTo · Konfidenz :confidenceFrom–:confidenceTo % · :samples Prognosen · Trefferquote :hitRate · Ø Rendite :return', [
                                        'scoreFrom' => $scoreBucket,
                                        'scoreTo' => $scoreBucket + 1,
                                        'confidenceFrom' => $confidenceBucket * 10,
                                        'confidenceTo' => ($confidenceBucket + 1) * 10,
                                        'samples' => $samples,
                                        'hitRate' => $hitRate !== null ? number_format($hitRate, 1, ',', '.').' %' : '—',
                                        'return' => $averageReturn !== null ? ($averageReturn > 0 ? '+' : '').number_format($averageReturn, 2, ',', '.').' %' : '—',
                                    ]) }}"
                                >
                                    <span class="text-xs font-black tabular-nums">{{ $samples >= 5 && $hitRate !== null ? number_format($hitRate, 0, ',', '.').'%' : ($samples ?: '—') }}</span>
                                </div>
                            @endfor
                        @endfor
                        <div></div>
                        @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                            <div class="pt-1 text-center text-[10px] font-bold tabular-nums text-slate-500">{{ $scoreBucket }}–{{ $scoreBucket + 1 }}</div>
                        @endfor
                    </div>
                    <p class="mt-3 text-center text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('X: KI-Score') }}</p>
                    <p class="mt-4 text-center text-[10px] text-slate-500">{{ __('Graue Felder enthalten weniger als fünf validierte Prognosen und werden nicht farblich bewertet.') }}</p>
                </div>
            </div>
        </section>
    </div>

    @if ($userWatchlists->count() > 1)
        <div id="prediction-watchlist-picker" class="fixed inset-0 z-[90] hidden place-items-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="prediction-watchlist-picker-title">
            <section class="w-full max-w-sm overflow-hidden rounded-2xl border border-violet-400/25 bg-[#171325] shadow-2xl shadow-black/60">
                <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                    <div class="min-w-0">
                        <p id="prediction-watchlist-picker-symbol" class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300"></p>
                        <h2 id="prediction-watchlist-picker-title" class="mt-1 text-lg font-black text-white">{{ __('Watchlist auswählen') }}</h2>
                        <p id="prediction-watchlist-picker-name" class="mt-1 truncate text-xs text-slate-400"></p>
                    </div>
                    <button type="button" data-close-watchlist-picker class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-white" aria-label="{{ __('Schließen') }}">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <p class="px-5 pt-4 text-xs text-slate-400">{{ __('In welche Watchlist soll die Aktie übernommen werden?') }}</p>
                <div class="max-h-80 space-y-2 overflow-y-auto p-3">
                    @foreach ($userWatchlists as $watchlist)
                        <form method="POST" data-picker-watchlist-form data-watchlist-id="{{ $watchlist->id }}" data-url-template="{{ url('/watchlists/'.$watchlist->id.'/items/__instrument__') }}" data-prediction-watchlist-form>
                            @csrf
                            <input type="hidden" name="prediction_id" value="">
                            <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-xl border border-white/[.07] bg-white/[.025] px-4 py-3 text-left transition hover:border-violet-400/25 hover:bg-violet-500/10">
                                <span class="min-w-0">
                                    <strong class="block truncate text-sm text-white">{{ $watchlist->name }}</strong>
                                    @if ($watchlist->is_default)<small class="text-[10px] font-bold text-violet-300">{{ __('Standard') }}</small>@endif
                                </span>
                                <span class="shrink-0">
                                    <x-heroicon-s-star data-picker-filled class="hidden h-5 w-5 text-amber-300" />
                                    <x-heroicon-o-star data-picker-empty class="h-5 w-5 text-slate-600" />
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableScroll = document.querySelector('#predictions-table-scroll');
            if (!tableScroll) return;

            const storageKey = `aktienki:predictions-scroll:${window.location.pathname}${window.location.search}`;

            try {
                const savedPosition = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
                if (savedPosition) {
                    requestAnimationFrame(() => {
                        tableScroll.scrollTop = Number(savedPosition.top) || 0;
                        tableScroll.scrollLeft = Number(savedPosition.left) || 0;
                    });
                    sessionStorage.removeItem(storageKey);
                }
            } catch (_) {
                sessionStorage.removeItem(storageKey);
            }

            document.querySelectorAll('[data-prediction-watchlist-form]').forEach(form => {
                form.addEventListener('submit', () => {
                    sessionStorage.setItem(storageKey, JSON.stringify({
                        top: tableScroll.scrollTop,
                        left: tableScroll.scrollLeft,
                    }));
                });
            });

            const picker = document.querySelector('#prediction-watchlist-picker');
            const pickerSymbol = document.querySelector('#prediction-watchlist-picker-symbol');
            const pickerName = document.querySelector('#prediction-watchlist-picker-name');
            const pickerForms = document.querySelectorAll('[data-picker-watchlist-form]');
            const closePicker = () => {
                picker?.classList.add('hidden');
                picker?.classList.remove('grid');
            };

            document.querySelectorAll('[data-open-watchlist-picker]').forEach(button => {
                button.addEventListener('click', () => {
                    const memberships = JSON.parse(button.dataset.memberships || '[]').map(Number);
                    pickerSymbol.textContent = button.dataset.symbol || '';
                    pickerName.textContent = button.dataset.name || '';

                    pickerForms.forEach(form => {
                        const isMember = memberships.includes(Number(form.dataset.watchlistId));
                        form.action = form.dataset.urlTemplate.replace('__instrument__', button.dataset.instrumentId);
                        form.querySelector('input[name="prediction_id"]').value = button.dataset.predictionId;
                        form.querySelector('[data-picker-filled]').classList.toggle('hidden', !isMember);
                        form.querySelector('[data-picker-empty]').classList.toggle('hidden', isMember);
                    });

                    picker.classList.remove('hidden');
                    picker.classList.add('grid');
                });
            });

            document.querySelectorAll('[data-close-watchlist-picker]').forEach(button => button.addEventListener('click', closePicker));
            picker?.addEventListener('click', event => {
                if (event.target === picker) closePicker();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closePicker();
            });

            const heatmapModal = document.querySelector('#prediction-heatmap-modal');
            const closeHeatmap = () => {
                heatmapModal?.classList.add('hidden');
                heatmapModal?.classList.remove('grid');
            };
            document.querySelectorAll('[data-open-prediction-heatmap]').forEach(button => button.addEventListener('click', () => {
                heatmapModal?.classList.remove('hidden');
                heatmapModal?.classList.add('grid');
            }));
            document.querySelectorAll('[data-close-prediction-heatmap]').forEach(button => button.addEventListener('click', closeHeatmap));
            heatmapModal?.addEventListener('click', event => {
                if (event.target === heatmapModal) closeHeatmap();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeHeatmap();
            });
        });
    </script>
</x-app-layout>
