<x-app-layout>
    @php
        $countryFlag = fn (?string $country): string => is_string($country) && strlen($country) === 2
            ? mb_chr(127397 + ord(strtoupper($country[0]))) . mb_chr(127397 + ord(strtoupper($country[1])))
            : '🌐';
    @endphp
    <div id="signal-changes-page" class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-500">aKI Signal Intelligence</p>
                <h1 class="mt-1 text-2xl font-black">{{ __('Signaländerungen') }}</h1>
                <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Wechsel zwischen Buy, Watch, Hold und Sell in aufeinanderfolgenden Prognosen.') }}</p>
            </div>
            <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-2 text-xs font-black text-[var(--ak-muted)]">
                {{ number_format($changes->count(), 0, ',', '.') }} {{ __('Änderungen') }}
            </span>
        </div>

        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <form method="GET" action="{{ route('signal-changes.index') }}" x-data="{ timer:null, submit(){clearTimeout(this.timer);this.timer=setTimeout(()=>this.$root.requestSubmit(),400)} }" class="flex shrink-0 flex-nowrap gap-2 overflow-x-auto border-b border-[var(--ak-border)] p-3">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <label class="relative min-w-[200px] flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" />
                    <input name="q" value="{{ request('q') }}" @input="submit()" placeholder="{{ __('Symbol oder Unternehmen') }}" class="ak-input h-10 w-full pl-9 text-sm">
                </label>
                <select name="ai_type" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle KI-Typen') }}</option>
                    @foreach ($aiTypes as $type)<option value="{{ $type }}" @selected(request('ai_type') === $type)>{{ ucfirst($type) }} KI</option>@endforeach
                </select>
                <select name="model" @change="$root.requestSubmit()" class="ak-input h-10 w-44 shrink-0 text-sm">
                    <option value="">{{ __('Alle Modelle') }}</option>
                    @foreach ($models as $model)<option value="{{ $model->id }}" @selected((int) request('model') === (int) $model->id)>{{ $model->public_alias }}</option>@endforeach
                </select>
                <select name="quality_tier" @change="$root.requestSubmit()" class="ak-input h-10 w-48 shrink-0 text-sm">
                    <option value="">{{ __('Alle Modellstufen') }}</option>
                    @foreach ($qualityTiers as $qualityTier)
                        <option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>
                    @endforeach
                    <option value="unqualified" @selected(request('quality_tier') === 'unqualified')>{{ __('Nicht qualifiziert') }}</option>
                </select>
                @foreach ([['from_signal', __('Von Signal')], ['to_signal', __('Zu Signal')]] as [$name, $label])
                    <select name="{{ $name }}" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                        <option value="">{{ $label }}</option>
                        @foreach (['BUY', 'WATCH', 'HOLD', 'SELL'] as $signal)<option value="{{ $signal }}" @selected(strtoupper((string) request($name)) === $signal)>{{ $signal }}</option>@endforeach
                    </select>
                @endforeach
                <select name="score_min" @change="$root.requestSubmit()" class="ak-input h-10 w-40 shrink-0 text-sm">
                    <option value="">{{ __('Alle KI-Scores') }}</option>
                    @foreach ([8 => '8,0', 7 => '7,0', 6 => '6,0', 5 => '5,0'] as $value => $label)<option value="{{ $value }}" @selected((string) request('score_min') === (string) $value)>{{ __('KI-Score ab') }} {{ $label }}</option>@endforeach
                </select>
                <select name="confidence_min" @change="$root.requestSubmit()" class="ak-input h-10 w-44 shrink-0 text-sm">
                    <option value="">{{ __('Alle Konfidenzen') }}</option>
                    @foreach ([90, 80, 70, 60, 50] as $value)<option value="{{ $value }}" @selected((string) request('confidence_min') === (string) $value)>{{ __('Konfidenz ab') }} {{ $value }} %</option>@endforeach
                </select>
                <select name="days" @change="$root.requestSubmit()" class="ak-input h-10 w-36 shrink-0 text-sm">
                    @foreach ([1, 2, 3, 7, 14, 30, 90, 180, 365] as $value)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $value }} {{ $value === 1 ? __('Tag') : __('Tage') }}</option>
                    @endforeach
                </select>
                <div class="flex w-48 shrink-0">
                    <a href="{{ route('signal-changes.index') }}" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] px-3 text-xs font-bold text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700">
                        <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Filter zurücksetzen') }}
                    </a>
                </div>
            </form>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @php
                    $sortUrl = fn (string $column): string => route('signal-changes.index', array_merge(
                        request()->query(),
                        [
                            'sort' => $column,
                            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        ],
                    ));
                    $sortIndicator = fn (string $column): string => $sort === $column
                        ? ($direction === 'asc' ? '↑' : '↓')
                        : '↕';
                @endphp
                <table class="w-full table-fixed border-separate border-spacing-x-0 border-spacing-y-2 text-left">
                    <colgroup>
                        <col style="width:11%"><col style="width:18%"><col style="width:13%"><col style="width:17%">
                        <col style="width:15%"><col style="width:9%"><col style="width:9%"><col style="width:8%">
                    </colgroup>
                    <thead class="text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
                        <tr>
                            @foreach ([
                                ['time', __('Zeitpunkt')],
                                ['stock', __('Aktie')],
                                ['model', __('Modell')],
                                ['change', __('Signalwechsel')],
                                ['score', __('KI-Score')],
                                ['confidence', __('Konfidenz')],
                                ['risk', __('Risiko')],
                                ['price', __('Kurs')],
                            ] as [$column, $heading])
                                <th class="sticky top-0 z-20 bg-[var(--ak-surface)] px-3 py-3 shadow-[0_1px_0_var(--ak-border)]">
                                    <a href="{{ $sortUrl($column) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap {{ $sort === $column ? 'text-teal-500' : '' }}">
                                        {{ $heading }}
                                        <span class="{{ $sort === $column ? 'text-teal-500' : 'text-[var(--ak-muted)]' }}">{{ $sortIndicator($column) }}</span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($changes as $change)
                            @php
                                $score = is_numeric($change->score_10) ? (float) $change->score_10 : null;
                                $confidence = is_numeric($change->confidence_percent) ? (float) $change->confidence_percent : null;
                                $risk = is_numeric($change->risk_percent) ? (float) $change->risk_percent : null;
                                $confidenceColor = $confidence < 40 ? '#ef4444' : ($confidence < 60 ? '#f97316' : ($confidence < 75 ? '#eab308' : ($confidence < 88 ? '#84cc16' : '#10b981')));
                                $riskColor = $risk < 10 ? '#10b981' : ($risk < 20 ? '#84cc16' : ($risk < 30 ? '#eab308' : ($risk < 40 ? '#f97316' : '#ef4444')));
                                $tierCode = $change->model_quality_tier_code ?: 'unqualified';
                                $tierName = $change->model_quality_tier_name ? __($change->model_quality_tier_name) : __('Nicht qualifiziert');
                                $tierClass = match ($tierCode) {
                                    'top' => 'ak-model-tier-top',
                                    'strong' => 'ak-model-tier-strong',
                                    'solid' => 'ak-model-tier-solid',
                                    'test' => 'ak-model-tier-test',
                                    default => 'ak-model-tier-unqualified',
                                };
                                $tone = fn (string $signal) => match ($signal) {
                                    'BUY' => 'buy',
                                    'WATCH' => 'watch',
                                    'SELL' => 'sell',
                                    default => 'hold',
                                };
                            @endphp
                            <tr onclick="window.location.href=@js(route('stocks.show', ['symbol' => $change->symbol, 'prediction' => $change->id, 'return_to' => request()->getRequestUri()]))" class="h-[72px] cursor-pointer">
                                <td class="rounded-l-2xl border-y border-l border-[var(--ak-border)] bg-[var(--ak-card)] px-3 text-xs text-[var(--ak-muted)]">{{ \Carbon\Carbon::parse($change->prediction_time)->format('d.m.Y H:i') }}</td>
                                <td class="border-y border-[var(--ak-border)] bg-[var(--ak-card)] px-3">
                                    <div class="flex items-center gap-2">
                                        <img src="{{ route('stocks.icon', $change->instrument_id) }}" alt="" class="h-8 w-8 shrink-0 object-contain">
                                        <div class="min-w-0">
                                            <p class="font-black text-teal-500">{{ $change->symbol }}</p>
                                            <p class="truncate text-[10px] text-[var(--ak-muted)]">{{ $change->name }}</p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-[9px] font-bold text-[var(--ak-muted)]">
                                                <span>{{ $countryFlag($change->country) }}</span>
                                                <span>{{ $change->exchange_code ?: '—' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-y border-[var(--ak-border)] bg-[var(--ak-card)] px-3">
                                    <p class="truncate font-bold">{{ $change->model_alias ?: ucfirst($change->ai_type) }}</p>
                                    <div class="mt-1 flex min-w-0 items-center gap-1">
                                        <span class="ak-model-tier {{ $tierClass }}">{{ $tierName }}</span>
                                        @if (is_numeric($change->model_quality_score))
                                            <small class="shrink-0 text-[8px] font-bold text-[var(--ak-muted)]">{{ number_format((float) $change->model_quality_score * 100, 0, ',', '.') }} %</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="border-y border-[var(--ak-border)] bg-[var(--ak-card)] px-3"><div class="flex items-center gap-1.5"><span class="ak-change-signal ak-change-signal-{{ $tone($change->previous_signal) }}">{{ $change->previous_signal }}</span><x-heroicon-o-arrow-right class="h-4 w-4 text-[var(--ak-muted)]" /><span class="ak-change-signal ak-change-signal-{{ $tone($change->current_signal) }}">{{ $change->current_signal }}</span></div></td>
                                <td class="border-y border-[var(--ak-border)] bg-[var(--ak-card)] px-3">@if($score!==null)<div class="mb-1 flex justify-between"><b>{{ number_format($score,1,',','.') }}</b><small class="text-[var(--ak-muted)]">/10</small></div><x-dashboard.score-stripes :percent="$score*10" />@else — @endif</td>
                                @foreach ([[$confidence,$confidenceColor,__('Konfidenz')],[$risk,$riskColor,__('Risiko')]] as [$value,$color,$label])
                                    <td class="border-y border-[var(--ak-border)] bg-[var(--ak-card)] px-2"><div class="flex justify-center">@if($value!==null)<div class="ak-change-donut" style="--value:{{$value}}%;--color:{{$color}}" aria-label="{{$label}}"><span>{{number_format($value,0,',','.')}}<small>%</small></span></div>@else — @endif</div></td>
                                @endforeach
                                <td class="rounded-r-2xl border-y border-r border-[var(--ak-border)] bg-[var(--ak-card)] px-3 text-right text-xs font-black">{{ is_numeric($change->current_price) ? number_format($change->current_price,2,',','.').' '.$change->currency : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-16 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine Signaländerungen für diese Filter gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <style>
        #signal-changes-page{--ak-muted:#b8c2d4}:root[data-theme="light"] #signal-changes-page{--ak-muted:#64748b}
        .ak-change-donut{position:relative;display:grid;width:46px;height:46px;place-items:center;border-radius:999px;background:conic-gradient(var(--color) 0 var(--value),rgba(148,163,184,.16) var(--value) 100%)}
        .ak-change-donut:after{position:absolute;inset:5px;border-radius:inherit;background:var(--ak-card);content:''}
        .ak-change-donut span{position:relative;z-index:1;font-size:10px;font-weight:900}.ak-change-donut small{margin-left:1px;color:var(--ak-muted);font-size:7px}
        .ak-change-signal{display:inline-flex;width:66px;height:28px;flex:0 0 66px;align-items:center;justify-content:center;border:1px solid;border-radius:8px;color:#fff;font-size:9px;font-weight:900;letter-spacing:.02em}
        .ak-change-signal-sell{border-color:rgba(251,113,133,.72);background:rgba(225,29,72,.58);box-shadow:0 0 10px rgba(244,63,94,.12)}
        .ak-change-signal-hold{border-color:rgba(252,211,77,.72);background:rgba(217,119,6,.56);box-shadow:0 0 10px rgba(245,158,11,.11)}
        .ak-change-signal-watch{border-color:rgba(190,242,100,.68);background:rgba(101,163,13,.52);box-shadow:0 0 10px rgba(132,204,22,.10)}
        .ak-change-signal-buy{border-color:rgba(110,231,183,.82);background:rgba(5,150,105,.72);box-shadow:0 0 12px rgba(16,185,129,.18)}
    </style>
</x-app-layout>
