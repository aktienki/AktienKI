@php
    $flags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'CH' => '🇨🇭', 'NL' => '🇳🇱', 'AU' => '🇦🇺', 'CA' => '🇨🇦'];
    $hasFilters = $search !== '' || $country !== '' || $sector !== '' || $signal !== '' || $exchange !== '' || $minScore !== '' || $maxScore !== '';
@endphp

<div class="flex h-full min-h-0 flex-col">
    <div class="grid shrink-0 gap-3 border-b border-[var(--ak-border)] p-3 lg:grid-cols-[minmax(220px,1.4fr)_repeat(3,minmax(140px,.65fr))_minmax(190px,.8fr)_auto] sm:p-4">
        <label class="relative block">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" />
            <input wire:model.live.debounce.350ms="search" type="search" placeholder="{{ __('Symbol, Unternehmen oder Branche') }}" class="ak-input h-10 pl-9 text-sm">
        </label>

        <select wire:model.live="country" class="ak-input h-10 text-xs">
            <option value="">{{ __('Alle Länder') }}</option>
            @foreach ($countries as $option)<option value="{{ $option }}">{{ $flags[$option] ?? '🌐' }} {{ $option }}</option>@endforeach
        </select>

        <select wire:model.live="sector" class="ak-input h-10 text-xs">
            <option value="">{{ __('Alle Sektoren') }}</option>
            @foreach ($sectors as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
        </select>

        <label class="relative block">
            <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] {{ $signal !== '' ? 'text-teal-700' : 'text-[var(--ak-muted)]' }}">
                {{ __('Signal') }}
            </span>
            <select
                wire:model.live="signal"
                aria-label="{{ __('Nach Signal filtern') }}"
                class="ak-input h-10 pb-1 pt-4 text-xs font-bold {{ $signal !== '' ? 'border-teal-500/45 bg-teal-500/10 text-teal-800' : '' }}"
            >
                <option value="">{{ __('Alle Signale') }}</option>
                @foreach ($signals as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid grid-cols-2 gap-2">
            <input wire:model.live.debounce.350ms="minScore" type="number" min="0" max="10" step="0.1" placeholder="{{ __('Score min.') }}" class="ak-input h-10 text-xs">
            <input wire:model.live.debounce.350ms="maxScore" type="number" min="0" max="10" step="0.1" placeholder="{{ __('Score max.') }}" class="ak-input h-10 text-xs">
        </div>

        <button wire:click="clearFilters" type="button" @disabled(!$hasFilters) class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] px-3 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)] disabled:cursor-not-allowed disabled:opacity-35">
            <x-heroicon-o-x-mark class="h-4 w-4" />{{ __('Zurücksetzen') }}
        </button>
    </div>

    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] bg-violet-500/[.035] px-4 py-2.5">
        <div class="flex items-center gap-3 text-xs text-[var(--ak-muted)]">
            @if ($exchange !== '')
                <span class="inline-flex h-7 items-center rounded-lg border border-teal-500/35 bg-teal-500/12 px-2.5 font-black text-teal-700">
                    {{ __('Exchange') }}: {{ $exchange }}
                </span>
            @endif
            @if ($signal !== '')
                <span class="inline-flex h-7 items-center rounded-lg border px-2.5 font-black text-white
                    {{ $signal === 'BUY' ? 'border-emerald-400/60 bg-emerald-600/75' : '' }}
                    {{ $signal === 'WATCH' ? 'border-lime-400/60 bg-lime-600/70' : '' }}
                    {{ $signal === 'HOLD' ? 'border-amber-400/60 bg-amber-600/70' : '' }}
                    {{ $signal === 'SELL' ? 'border-rose-400/60 bg-rose-600/75' : '' }}">
                    {{ __('Signal') }}: {{ __($signal) }}
                </span>
            @endif
            <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg border border-teal-500/25 bg-teal-500/10 px-2 font-black text-teal-700">{{ count($comparisonSelection) }}/5</span>
            <span>{{ __('Aktien für den Vergleich auswählen') }}</span>
            @if ($comparisonLimitReached)
                <span class="font-bold text-amber-300">{{ __('Maximal fünf Aktien sind möglich.') }}</span>
            @endif
        </div>
        <button
            type="button"
            wire:click="compareSelected"
            @disabled(count($comparisonSelection) < 2)
            class="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 text-xs font-black text-white shadow-lg shadow-violet-950/25 transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-35"
        >
            <x-heroicon-o-arrows-right-left class="h-4 w-4" />{{ __('Aktien vergleichen') }}
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-auto">
        <table id="stock-screener-table" class="ak-stocks-table w-full text-left">
            <colgroup>
                <col style="width:4%"><col style="width:4%"><col style="width:7%"><col style="width:12%">
                <col style="width:5%"><col style="width:8%"><col style="width:7%"><col style="width:7%">
                <col style="width:7%"><col style="width:12%"><col style="width:6%"><col style="width:6%">
                <col style="width:7%"><col style="width:8%">
            </colgroup>
            <thead>
                <tr>
                    <th class="w-12 border-b border-[var(--ak-border)] px-3 py-3 text-center" title="{{ __('Vergleichen') }}">
                        <x-heroicon-o-arrows-right-left class="mx-auto h-4 w-4 text-[var(--ak-muted)]" />
                    </th>
                    <th class="w-12 border-b border-[var(--ak-border)] px-3 py-3 text-center" title="{{ __('Watchlist') }}">
                        <x-heroicon-o-star class="mx-auto h-4 w-4 text-[var(--ak-muted)]" />
                    </th>
                    @foreach ([
                        ['symbol', __('Symbol')], ['name', __('Unternehmen')], ['country', __('Land')], ['sector', __('Sektor')],
                        ['current_price', __('Kurs')], ['predicted_price_5d', __('Ziel 5 Tage')], ['expected_return_5d', __('Rendite 5 Tage')],
                        ['prediction_score', __('KI-Score')], ['confidence', __('Konfidenz')], ['risk_score', __('Risiko')],
                        ['signal', __('Signal')], ['prediction_time', __('Analyse')]
                    ] as [$field, $label])
                        <th class="border-b border-[var(--ak-border)] px-2 py-3 text-[9px] font-black uppercase tracking-[.06em] text-[var(--ak-muted)]">
                            <button wire:click="sortBy('{{ $field }}')" type="button" class="flex w-full items-center gap-1.5 whitespace-nowrap text-left transition hover:text-teal-700">
                                {{ $label }}
                                @if ($field === 'prediction_score')
                                    <x-heroicon-o-information-circle
                                        class="h-4 w-4 shrink-0 text-teal-700/80"
                                        title="{{ __('Der KI-Score bündelt die Modellbewertung einer Aktie auf einer Skala von 0 bis 10. Höhere Werte stehen für eine positivere Bewertung.') }}"
                                        aria-label="{{ __('Bedeutung des KI-Scores') }}"
                                    />
                                @elseif ($field === 'confidence')
                                    <x-heroicon-o-information-circle
                                        class="h-4 w-4 shrink-0 text-teal-700/80"
                                        title="{{ __('Die Konfidenz zeigt in Prozent, wie sicher das KI-Modell bei seiner Bewertung ist.') }}"
                                        aria-label="{{ __('Bedeutung der Konfidenz') }}"
                                    />
                                @elseif ($field === 'signal')
                                    <x-heroicon-o-information-circle
                                        class="h-4 w-4 shrink-0 text-teal-700/80"
                                        title="{{ __('Der Status wird aus der KI-Bewertung und deinem gewählten Risikoprofil abgeleitet.') }}"
                                        aria-label="{{ __('Bedeutung des Status') }}"
                                    />
                                @endif
                                <span class="inline-flex h-4 w-4 items-center justify-center {{ $sortField === $field ? 'text-teal-700' : 'text-slate-600' }}">
                                    @if ($sortField === $field && $sortDirection === 'asc') ↑
                                    @elseif ($sortField === $field) ↓
                                    @else ↕
                                    @endif
                                </span>
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $score = \App\Support\AiScore::toTen($row->prediction_score);
                        $scorePercent = \App\Support\AiScore::toPercent($row->prediction_score);
                        $confidencePercent = is_numeric($row->confidence) ? max(0, min(100, (float) $row->confidence <= 1 ? (float) $row->confidence * 100 : (float) $row->confidence)) : null;
                        $riskPercent = is_numeric($row->risk_score) ? max(0, min(100, (float) $row->risk_score <= 1 ? (float) $row->risk_score * 100 : (float) $row->risk_score)) : null;
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
                        $signalName = strtoupper((string) ($row->signal ?: 'N/A'));
                        $signalClass = str_contains($signalName, 'BUY')
                            ? 'border-emerald-300/80 bg-emerald-400/30 text-emerald-50 shadow-[0_0_18px_rgba(52,211,153,.38)] ring-1 ring-emerald-300/25'
                            : (str_contains($signalName, 'SELL')
                                ? 'border-rose-400/25 bg-rose-400/10 text-rose-400'
                                : (str_contains($signalName, 'WATCH')
                                    ? 'border-lime-300/25 bg-lime-300/10 text-lime-300'
                                    : 'border-amber-400/25 bg-amber-400/10 text-amber-300'));
                    @endphp
                    <tr
                        wire:key="stock-row-{{ $row->id }}"
                        data-href="{{ route('stocks.show', $row->symbol) }}"
                        role="link"
                        tabindex="0"
                        onclick="if (!event.target.closest('a,button,input,select,label,form')) window.location.assign(this.dataset.href)"
                        onkeydown="if (event.target === this && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); window.location.assign(this.dataset.href); }"
                        class="cursor-pointer transition hover:bg-violet-500/[.075] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-violet-400/70"
                    >
                        <td class="px-3 py-3 text-center">
                            @php
                                $isSelectedForComparison = in_array((int) $row->id, array_map('intval', $comparisonSelection), true);
                                $comparisonDisabled = ! $isSelectedForComparison && count($comparisonSelection) >= 5;
                            @endphp
                            <input
                                type="checkbox"
                                wire:click="toggleComparison({{ $row->id }})"
                                @checked($isSelectedForComparison)
                                @disabled($comparisonDisabled)
                                aria-label="{{ __('Für Vergleich auswählen') }}: {{ $row->symbol }}"
                                class="h-4 w-4 rounded border-slate-500 bg-white text-teal-600 accent-teal-600 focus:ring-2 focus:ring-teal-500/40 disabled:cursor-not-allowed disabled:opacity-30"
                            >
                        </td>
                        <td class="px-3 py-3 text-center">
                            @php
                                $rowWatchlistIds = $watchlistMemberships->get((int) $row->id, []);
                                $isWatched = count($rowWatchlistIds) > 0;
                            @endphp
                            @if ($userWatchlists->count() === 1)
                                @php $singleWatchlistId = (int) $userWatchlists->first()->id; @endphp
                                <button
                                    type="button"
                                    wire:click="toggleWatchlist({{ $row->id }}, {{ $singleWatchlistId }})"
                                    wire:loading.attr="disabled"
                                    aria-pressed="{{ $isWatched ? 'true' : 'false' }}"
                                    title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 disabled:opacity-50 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}"
                                >
                                    @if ($isWatched)
                                        <x-heroicon-s-star class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-star class="h-5 w-5" />
                                    @endif
                                </button>
                            @elseif ($userWatchlists->count() > 1)
                                <button
                                    type="button"
                                    wire:click="openWatchlistPicker({{ $row->id }})"
                                    wire:loading.attr="disabled"
                                    aria-haspopup="dialog"
                                    aria-pressed="{{ $isWatched ? 'true' : 'false' }}"
                                    title="{{ __('Watchlist auswählen') }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-amber-300/10 disabled:opacity-50 {{ $isWatched ? 'text-amber-300' : 'text-slate-600 hover:text-amber-300' }}"
                                >
                                    @if ($isWatched)
                                        <x-heroicon-s-star class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-star class="h-5 w-5" />
                                    @endif
                                </button>
                            @else
                                <a href="{{ route('watchlists.index') }}" title="{{ __('Zuerst Watchlist erstellen') }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition hover:bg-teal-500/10 hover:text-teal-700">
                                    <x-heroicon-o-star class="h-5 w-5" />
                                </a>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <a href="{{ route('stocks.show', $row->symbol) }}" class="group inline-flex items-center gap-2.5">
                                <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-400/20 bg-white/[.06]">
                                    <span class="flex h-full w-full items-center justify-center bg-teal-500/10 text-[10px] font-black leading-none text-teal-700">
                                        {{ strtoupper(substr($row->symbol, 0, 2)) }}
                                    </span>
                                    <span class="absolute inset-0 z-10 flex items-center justify-center p-1.5" aria-hidden="true">
                                        <img
                                            src="{{ route('stocks.icon', $row->id) }}"
                                            alt=""
                                            class="h-full w-full object-contain opacity-0"
                                            loading="eager"
                                            onload="this.classList.remove('opacity-0'); this.parentElement.classList.add('bg-slate-50')"
                                            onerror="this.parentElement.classList.add('hidden')"
                                        >
                                    </span>
                                </span>
                                <span class="text-sm font-black text-teal-700 transition group-hover:text-teal-800">{{ $row->symbol }}</span>
                            </a>
                        </td>
                        <td class="px-3 py-3"><div class="min-w-52"><a href="{{ route('stocks.show', $row->symbol) }}" class="block text-sm font-bold text-[var(--ak-text)] transition hover:text-teal-700">{{ $row->name }}</a><span class="mt-0.5 block text-[10px] text-[var(--ak-muted)]">{{ $row->industry ?: __('Keine Branche') }}</span></div></td>
                        <td class="px-3 py-3"><span class="inline-flex items-center gap-1.5 text-xs text-[var(--ak-text)]">{{ $flags[$row->country] ?? '🌐' }} {{ $row->country ?: '—' }}</span></td>
                        <td class="px-3 py-3 text-xs text-[var(--ak-muted)]"><span class="inline-flex items-center gap-1.5"><x-sector-icon :sector="$row->sector" class="h-3.5 w-3.5 shrink-0 text-teal-600" /><span class="truncate">{{ $row->sector ?: '—' }}</span></span></td>
                        <td class="px-3 py-3 text-xs font-bold text-[var(--ak-text)]">{{ is_numeric($row->current_price) ? number_format($row->current_price, 2, ',', '.').' '.$row->currency : '—' }}</td>
                        <td class="px-3 py-3 text-xs font-bold text-[var(--ak-text)]">{{ is_numeric($row->predicted_price_5d) ? number_format($row->predicted_price_5d, 2, ',', '.').' '.$row->currency : '—' }}</td>
                        <td class="px-3 py-3 text-xs font-black {{ ($row->expected_return_5d ?? 0) > 0 ? 'text-emerald-400' : (($row->expected_return_5d ?? 0) < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ is_numeric($row->expected_return_5d) ? (($row->expected_return_5d > 0 ? '+' : '').number_format($row->expected_return_5d, 2, ',', '.').' %') : '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex h-full flex-col justify-center">
                                @if ($score !== null)
                                    <div class="mb-1.5 flex items-baseline justify-between">
                                        <strong class="text-sm font-black">{{ number_format($score, 1, ',', '.') }}</strong>
                                        <small class="text-[8px] text-[var(--ak-muted)]">/ 10</small>
                                    </div>
                                    <x-dashboard.score-stripes :percent="$scorePercent" />
                                @else<span class="text-center text-[var(--ak-muted)]">—</span>@endif
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex h-full items-center justify-center">
                                @if ($confidencePercent !== null)
                                    <div class="ak-screener-donut" style="--value:{{ $confidencePercent }}%;--color:{{ $confidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuenow="{{ round($confidencePercent) }}">
                                        <span>{{ number_format($confidencePercent, 0, ',', '.') }}<small>%</small></span>
                                    </div>
                                @else<span class="text-[var(--ak-muted)]">—</span>@endif
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex h-full items-center justify-center">
                                @if ($riskPercent !== null)
                                    <div class="ak-screener-donut" style="--value:{{ $riskPercent }}%;--color:{{ $riskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuenow="{{ round($riskPercent) }}">
                                        <span>{{ number_format($riskPercent, 0, ',', '.') }}<small>%</small></span>
                                    </div>
                                @else<span class="text-[var(--ak-muted)]">—</span>@endif
                            </div>
                        </td>
                        <td class="px-3 py-3"><span class="inline-flex h-7 w-20 items-center justify-center rounded-lg border px-2 text-center text-[10px] font-black {{ $signalClass }}">{{ str_replace('_', ' ', $signalName) }}</span></td>
                        <td class="px-3 py-3 text-[10px] text-[var(--ak-muted)]">{{ $row->prediction_time ? \Carbon\Carbon::parse($row->prediction_time)->format('d.m.Y H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="px-6 py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Aktien für diese Filter gefunden.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        #stock-screener-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        #stock-screener-table thead,
        #stock-screener-table thead th {
            background: var(--ak-surface) !important;
        }

        #stock-screener-table thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 0 !important;
            box-shadow: 0 1px 0 var(--ak-border), 0 8px 16px rgba(3, 7, 18, .15);
        }

        #stock-screener-table tbody tr[data-href] {
            height: 72px;
        }

        #stock-screener-table tbody tr[data-href] > td {
            height: 72px;
            min-width: 0 !important;
            overflow: hidden;
            border-top: 1px solid var(--ak-border) !important;
            border-right: 0 !important;
            border-bottom: 1px solid var(--ak-border) !important;
            border-left: 0 !important;
            background: var(--ak-card) !important;
        }

        #stock-screener-table tbody tr[data-href] > td:first-child {
            border-left: 1px solid var(--ak-border) !important;
            border-radius: 16px 0 0 16px;
        }

        #stock-screener-table tbody tr[data-href] > td:last-child {
            border-right: 1px solid var(--ak-border) !important;
            border-radius: 0 16px 16px 0;
        }

        #stock-screener-table tbody tr[data-href]:nth-child(even) > td {
            background: var(--ak-card-hover) !important;
        }

        #stock-screener-table tbody tr[data-href]:hover > td {
            background: color-mix(in srgb, var(--ak-card-hover) 90%, rgb(45 212 191) 10%) !important;
        }

        #stock-screener-table thead button,
        #stock-screener-table tbody td {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #stock-screener-table thead button {
            min-width: 0;
        }

        .ak-screener-donut {
            position: relative;
            display: grid;
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            place-items: center;
            border-radius: 999px;
            background: conic-gradient(var(--color) 0 var(--value), rgba(148, 163, 184, .16) var(--value) 100%);
            box-shadow: 0 0 14px color-mix(in srgb, var(--color) 18%, transparent);
        }

        .ak-screener-donut::after {
            position: absolute;
            inset: 5px;
            border-radius: inherit;
            background: var(--ak-card);
            content: '';
        }

        #stock-screener-table tbody tr[data-href]:nth-child(even) .ak-screener-donut::after {
            background: var(--ak-card-hover);
        }

        .ak-screener-donut span {
            position: relative;
            z-index: 1;
            color: var(--ak-text);
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
        }

        .ak-screener-donut small {
            margin-left: 1px;
            color: var(--ak-muted);
            font-size: 7px;
        }
    </style>

    @if ($watchlistPickerInstrument)
        <div
            class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm"
            wire:click.self="closeWatchlistPicker"
            role="dialog"
            aria-modal="true"
            aria-labelledby="watchlist-picker-title"
        >
            <section class="w-full max-w-sm overflow-hidden rounded-2xl border border-violet-400/25 bg-[#171325]/90 shadow-2xl shadow-black/60">
                <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-700">{{ $watchlistPickerInstrument->symbol }}</p>
                        <h2 id="watchlist-picker-title" class="mt-1 text-lg font-black text-white">{{ __('Watchlist auswählen') }}</h2>
                        <p class="mt-1 text-xs text-slate-400">{{ __('In welche Watchlist soll die Aktie übernommen werden?') }}</p>
                    </div>
                    <button type="button" wire:click="closeWatchlistPicker" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-white" aria-label="{{ __('Schließen') }}">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-80 space-y-2 overflow-y-auto p-3">
                    @foreach ($userWatchlists as $watchlist)
                        @php $isInWatchlist = in_array((int) $watchlist->id, $watchlistMemberships->get((int) $watchlistPickerInstrument->id, []), true); @endphp
                        <button
                            type="button"
                            wire:key="picker-watchlist-{{ $watchlist->id }}"
                            wire:click="toggleWatchlist({{ $watchlistPickerInstrument->id }}, {{ $watchlist->id }})"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-3 text-left transition {{ $isInWatchlist ? 'border-amber-300/30 bg-amber-300/10' : 'border-white/10 bg-white/[.025] hover:border-violet-400/30 hover:bg-violet-500/10' }}"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                @if ($isInWatchlist)
                                    <x-heroicon-s-star class="h-5 w-5 shrink-0 text-amber-300" />
                                @else
                                    <x-heroicon-o-star class="h-5 w-5 shrink-0 text-slate-500" />
                                @endif
                                <span class="min-w-0">
                                    <strong class="block truncate text-sm text-white">{{ $watchlist->name }}</strong>
                                    @if ($watchlist->is_default)<small class="text-[10px] font-bold text-teal-700">{{ __('Standard') }}</small>@endif
                                </span>
                            </span>
                            <span class="shrink-0 text-[10px] font-black {{ $isInWatchlist ? 'text-rose-300' : 'text-emerald-300' }}">{{ $isInWatchlist ? __('Entfernen') : __('Hinzufügen') }}</span>
                        </button>
                    @endforeach
                </div>
            </section>
        </div>
    @endif
</div>
