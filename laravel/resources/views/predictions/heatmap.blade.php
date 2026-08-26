<x-app-layout>
    @php
        $setupMode = $setupMode ?? false;
        $shortMode = $shortMode ?? false;
        $qualitySetupMode = $qualitySetupMode ?? false;
        $heatmapFilterRoute = $qualitySetupMode ? 'setup.quality' : ($shortMode ? 'setup.short' : ($setupMode ? 'setup.filter' : 'predictions.heatmap'));
        $rangeMaxima = array_merge([
            'score' => 10, 'confidence' => 100, 'drawdown' => 50, 'profit_factor' => 3, 'profit_per_trade' => 5,
            'volatility' => 100, 'predicted_return' => 10, 'pe' => 100,
            'dividend_yield' => 5, 'market_cap' => 3000, 'revenue_growth' => 100,
            'hit_rate' => 100, 'trades' => 100,
            'sector_score' => 10,
        ], $rangeMaxima ?? []);
        $rangeMaxima['volatility'] = min(100, (float) ($rangeMaxima['volatility'] ?? 100));
        $rangeMaxima['predicted_return'] = min(10, (float) ($rangeMaxima['predicted_return'] ?? 10));
        $rangeMaxima['dividend_yield'] = min(5, (float) ($rangeMaxima['dividend_yield'] ?? 5));
        $rangeValue = static fn (string $name, float $default, float $minimum, string $maximumKey): float =>
            max($minimum, min((float) $rangeMaxima[$maximumKey], (float) request($name, $default)));
        $changedFilterCount = static function (array $defaults): int {
            return collect($defaults)->filter(function ($default, string $key): bool {
                $value = request($key, $default);
                if (is_array($default)) return array_values((array) $value) !== array_values($default);
                return (string) $value !== (string) $default;
            })->count();
        };
        $filterGroupResetUrl = static function (string $route, array $keys): string {
            return route($route, request()->except($keys));
        };
        $universeFilterDefaults = ['country' => '', 'exchange' => '', 'sector' => '', 'model' => [], 'quality_tier' => '', 'position_factor' => 1, 'gate_mode' => 'system'];
        $performanceFilterDefaults = ['score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => $rangeMaxima['drawdown'], 'profit_per_trade_min' => 0, 'volatility_max' => $rangeMaxima['volatility'], 'minimum_trades' => 0, 'sector_score_min' => -1, 'hit_rate_min' => 0, 'predicted_return_min' => 0.5];
        $fundamentalFilterDefaults = ['pe_max' => $rangeMaxima['pe'], 'dividend_yield_min' => 0, 'dividend_yield_operator' => 'gte', 'market_cap_group' => 'all', 'revenue_growth_min' => -50];
    @endphp
    <div class="ak-strategy-page flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]"
         x-data="{
             akiChatOpen: false,
             individualStatsOpen: false,
             strategyImportOpen: false,
             planNoticeOpen: false,
             planNoticeTitle: '',
             planNoticeMessage: '',
             planNoticePlan: '',
             selectedStrategyUrl: '',
             akiQuestion: '',
             akiMessages: [{ role: 'assistant', text: '{{ __('Ich helfe dir bei der Auswahl sinnvoller Filter. Frage mich zum Beispiel nach Score, Konfidenz oder Profitfaktor.') }}' }],
             showPlanNotice(title, message, plan) {
                 this.planNoticeTitle = title;
                 this.planNoticeMessage = message;
                 this.planNoticePlan = plan;
                 this.planNoticeOpen = true;
             },
             askAki() {
                 const question = this.akiQuestion.trim();
                 if (!question) return;
                 this.akiMessages.push({ role: 'user', text: question });
                 const q = question.toLowerCase();
                 let answer = '{{ __('Starte mit moderaten Werten: KI-Score ab 6, Konfidenz ab 60 %, Profitfaktor ab 1,3 und Volatilität bis 40 %. Danach kannst du die Grenzen schrittweise verschärfen.') }}';
                 if (q.includes('profit') || q.includes('profitfaktor')) answer = '{{ __('Ein Profitfaktor über 1,3 ist ein guter Start. Ab etwa 1,5 wird der Filter robuster, allerdings sinkt meist die Zahl der verfügbaren Trades.') }}';
                 else if (q.includes('konfidenz') || q.includes('confidence')) answer = '{{ __('Setze die Konfidenz zunächst auf 60 %. Für eine strengere Auswahl kannst du 70 % oder 75 % testen und anschließend die Trade-Anzahl prüfen.') }}';
                 else if (q.includes('volatil') || q.includes('risiko')) answer = '{{ __('Für ein ausgewogenes Portfolio sind 30–40 % Volatilität ein sinnvoller Rahmen. Niedrigere Werte reduzieren Risiko, können aber Chancen ausschließen.') }}';
                 else if (q.includes('score') || q.includes('ki')) answer = '{{ __('Ein KI-Score ab 6 ist ein guter Ausgangspunkt. Für wenige, stärkere Kandidaten kannst du auf 7 oder 8 erhöhen.') }}';
                 else if (q.includes('reset') || q.includes('zurück')) answer = '{{ __('Mit Reset setzt du alle Regler auf die vollständige Backtest-Auswahl zurück. Danach kannst du einzelne Kriterien nacheinander verändern.') }}';
                 this.akiMessages.push({ role: 'assistant', text: answer });
                 this.akiQuestion = '';
             }
         }">
        <header class="ak-strategy-header mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="ak-strategy-heading flex min-w-0 items-center gap-3">
                <div class="ak-strategy-heading-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-400/[.09] text-cyan-300">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6" />
                </div>
                <div class="ak-strategy-heading-copy min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] {{ $shortMode ? 'text-rose-400' : ($qualitySetupMode ? 'text-amber-300' : 'text-teal-400') }}">{{ $qualitySetupMode ? __('Premium-Auswahlstatus') : ($shortMode ? __('Short-Strategietester') : ($setupMode ? __('Setup') : __('Historische Validierung'))) }}</p>
                    <h1 class="truncate text-2xl font-black">{{ $qualitySetupMode ? __('Smart Selection') : ($shortMode ? __('SELL-Prognosen testen') : ($setupMode ? __('Filter') : __('Historische Qualität nach Modellscore und Konfidenz'))) }}</h1>
                    <p class="ak-strategy-heading-description mt-1 text-xs text-[var(--ak-muted)]">{{ $shortMode ? __('Ausschließlich echte SELL-Prognosen werden als Short-Einstieg berücksichtigt.') : __('Trefferquote, Profitfaktor, Drawdown und Volatilität; alle aktuellen Filter werden berücksichtigt.') }}</p>
                </div>
            </div>
            <div class="ak-strategy-header-actions flex shrink-0 items-center gap-2">
                @if ($setupMode && ! $shortMode)
                    <button type="button" @if($canImportStrategy ?? false) @click="strategyImportOpen = true" @else @click="showPlanNotice(@js(__('Strategie importieren')), @js(__('Importiere gespeicherte Strategien und übernimm alle Filter, Rotationen und Positionsregeln direkt in den Strategietester.')), 'PRO')" @endif class="inline-flex items-center gap-2 rounded-xl border border-teal-300/30 bg-teal-400/[.09] px-3 py-2 text-xs font-black text-teal-200 transition hover:border-teal-300/55 hover:bg-teal-400/[.16]">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                        {{ __('Strategie importieren') }}
                    </button>
                @endif
                @unless ($setupMode)
                    <a href="{{ route('predictions.index', request()->query()) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-400">
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                        {{ __('Zurück zu Prognosen') }}
                    </a>
                @endunless
            </div>
        </header>

        @if ($setupMode && ! $shortMode && ($canImportStrategy ?? false))
        <template x-teleport="body">
            <div x-show="strategyImportOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="strategyImportOpen = false">
                <section class="w-full max-w-xl rounded-2xl border border-teal-300/25 bg-[#15243a] p-5 shadow-2xl" @click.outside="strategyImportOpen = false">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[.14em] text-teal-300">{{ __('Gespeicherte Strategie') }}</p>
                            <h2 class="mt-1 text-xl font-black text-white">{{ __('Strategie in den Tester importieren') }}</h2>
                            <p class="mt-2 text-xs leading-5 text-slate-300">{{ __('Alle gespeicherten Filter, Rotationen, Positionsregeln und die Exitstrategie werden in den Strategietester übernommen.') }}</p>
                        </div>
                        <button type="button" @click="strategyImportOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-400 hover:text-white" aria-label="{{ __('Dialog schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>

                    @if ($savedFilters->isNotEmpty())
                        <label class="mt-5 block">
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Strategie auswählen') }}</span>
                            <select x-model="selectedStrategyUrl" class="ak-input h-12 w-full rounded-xl px-3 text-sm text-white">
                                <option value="">{{ __('Bitte auswählen …') }}</option>
                                @foreach ($savedFilters as $savedFilter)
                                    <option value="{{ route('setup.filter', array_merge($savedFilter->filters ?? [], ['saved_filter' => $savedFilter->id])) }}">{{ $savedFilter->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="strategyImportOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300 hover:text-white">{{ __('Abbrechen') }}</button>
                            <button type="button" :disabled="!selectedStrategyUrl" @click="if (selectedStrategyUrl) window.location.assign(selectedStrategyUrl)" class="inline-flex h-10 items-center gap-2 rounded-lg bg-teal-400 px-4 text-xs font-black text-slate-950 transition hover:bg-teal-300 disabled:cursor-not-allowed disabled:opacity-40">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />{{ __('Auswahl übernehmen') }}
                            </button>
                        </div>
                    @else
                        <div class="mt-5 rounded-xl border border-amber-300/20 bg-amber-300/[.07] px-4 py-3 text-xs leading-5 text-amber-100">
                            {{ __('Du hast noch keine Strategie gespeichert. Führe zuerst einen Backtest aus und speichere dessen Einstellungen als Strategie.') }}
                        </div>
                    @endif
                </section>
            </div>
        </template>
        @endif

        <template x-teleport="body">
            <div x-show="planNoticeOpen" x-cloak class="fixed inset-0 z-[10050] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md" @keydown.escape.window="planNoticeOpen = false">
                <section class="relative w-full max-w-md overflow-hidden rounded-2xl border border-cyan-300/25 bg-[#102033] text-slate-100 shadow-[0_28px_90px_rgba(0,0,0,.55),0_0_35px_rgba(34,211,238,.10)]" role="dialog" aria-modal="true" @click.outside="planNoticeOpen = false">
                    <div class="h-1 bg-gradient-to-r from-cyan-400 via-teal-300 to-amber-300"></div>
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-cyan-300/30 bg-cyan-400/[.10] text-cyan-300">
                                <x-heroicon-o-lock-closed class="h-6 w-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Tarif-Funktion') }}</span>
                                    <span class="ak-plan-badge" :class="planNoticePlan === 'PLUS' ? 'ak-plan-badge--plus' : 'ak-plan-badge--pro'" x-text="planNoticePlan"></span>
                                </div>
                                <h2 class="mt-2 text-xl font-black text-white" x-text="planNoticeTitle"></h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300" x-text="planNoticeMessage"></p>
                            </div>
                            <button type="button" @click="planNoticeOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-400 transition hover:bg-white/[.06] hover:text-white" aria-label="{{ __('Dialog schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" @click="planNoticeOpen = false" class="h-11 rounded-xl border border-white/10 px-5 text-xs font-black text-slate-300 transition hover:bg-white/[.05] hover:text-white">{{ __('Später') }}</button>
                            <a href="{{ route('pricing', ['standalone' => 1]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-300 to-teal-300 px-5 text-xs font-black text-slate-950 shadow-[0_8px_24px_rgba(45,212,191,.18)] transition hover:brightness-110">
                                {{ __('Tarife ansehen') }}<x-heroicon-o-arrow-right class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </section>
            </div>
        </template>

        <div id="aki-chat-modal" class="fixed inset-0 z-[200] place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" style="display:none" data-aki-chat-modal>
            <section class="relative z-[201] w-full max-w-lg overflow-hidden rounded-2xl border border-violet-400/40 bg-slate-900 text-slate-100 shadow-2xl" style="background-color: var(--ak-card, #0f1b2d); color: var(--ak-text, #f8fafc);">
                <header class="flex items-center justify-between border-b border-[var(--ak-border)] bg-violet-400/[.08] px-4 py-3">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-500">{{ __('Assistent') }}</p><h2 class="text-base font-black">{{ __('AKI fragen') }}</h2></div>
                    <button type="button" data-aki-chat-close class="rounded-lg p-2 text-[var(--ak-muted)] hover:bg-[var(--ak-surface-muted)]" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div id="aki-chat-messages" class="max-h-80 space-y-2 overflow-y-auto p-4">
                    <div class="flex justify-start"><p class="max-w-[88%] rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2 text-xs leading-5 text-[var(--ak-text)]">{{ __('Ich helfe dir bei der Auswahl sinnvoller Filter. Frage mich zum Beispiel nach Score, Konfidenz oder Profitfaktor.') }}</p></div>
                </div>
                <form onsubmit="return window.akiAsk(event)" class="flex gap-2 border-t border-[var(--ak-border)] p-3">
                    <input id="aki-chat-input" type="text" class="ak-input min-w-0 flex-1 rounded-lg px-3 py-2 text-xs" placeholder="{{ __('Wie setze ich den Profitfaktor?') }}" autocomplete="off">
                    <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-black text-white hover:bg-violet-500">{{ __('Senden') }}</button>
                </form>
            </section>
        </div>
        @if ($qualitySetupMode)
        <div x-show="individualStatsOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" style="z-index: 9999;" @keydown.escape.window="individualStatsOpen = false">
            <section class="relative z-[10000] w-full max-w-6xl overflow-hidden rounded-2xl border border-cyan-300/25 bg-[var(--ak-card)] shadow-2xl" style="z-index: 10000;" @click.outside="individualStatsOpen = false">
                <header class="flex items-center justify-between border-b border-[var(--ak-border)] bg-cyan-400/[.08] px-4 py-3">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Backtest-Auswertung') }}</p><h2 class="text-base font-black text-[var(--ak-text)]">{{ __('Individuelle Aktienstatistik') }}</h2><p class="mt-0.5 text-[10px] text-[var(--ak-muted)]">{{ __('Kennzahlen je Aktie für das aktuell gefilterte Backtest-Portfolio.') }}</p></div>
                    <button type="button" @click="individualStatsOpen = false" class="rounded-lg p-2 text-[var(--ak-muted)] hover:bg-[var(--ak-surface-muted)]" aria-label="{{ __('Statistik schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div class="max-h-[70vh] overflow-auto p-3">
                    <table class="w-full min-w-[760px] text-left text-[10px]">
                        <thead class="sticky top-0 z-10 bg-[var(--ak-card)] text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><tr><th class="px-2 py-2">{{ __('Aktie') }}</th><th class="px-2 py-2">{{ __('Signal') }}</th><th class="px-2 py-2 text-right">{{ __('Trades') }}</th><th class="px-2 py-2 text-right">{{ __('Trefferquote') }}</th><th class="px-2 py-2 text-right">{{ __('Profitfaktor') }}</th><th class="px-2 py-2 text-right">{{ __('Drawdown') }}</th><th class="px-2 py-2 text-right">{{ __('Ø Rendite') }}</th><th class="px-2 py-2 text-right">{{ __('KI / Konf.') }}</th></tr></thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse (($individualStats ?? collect()) as $stat)
                            @php $signal = strtoupper($stat['signal'] ?? ''); $signalTone = match ($signal) { 'BUY' => 'text-emerald-400 border-emerald-400/30 bg-emerald-400/10', 'SELL' => 'text-rose-400 border-rose-400/30 bg-rose-400/10', default => 'text-amber-300 border-amber-300/30 bg-amber-300/10' }; @endphp
                            <tr class="hover:bg-[var(--ak-surface-muted)]"><td class="px-2 py-2"><strong class="block text-xs font-black text-[var(--ak-text)]">{{ $stat['symbol'] }}</strong><span class="block max-w-[220px] truncate text-[9px] text-[var(--ak-muted)]">{{ $stat['name'] }}</span></td><td class="px-2 py-2"><span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black {{ $signalTone }}">{{ $signal ?: '—' }}</span></td><td class="px-2 py-2 text-right font-bold tabular-nums text-[var(--ak-text)]">{{ number_format((int) $stat['trades'], 0, ',', '.') }}</td><td class="px-2 py-2 text-right font-bold tabular-nums text-cyan-300">{{ number_format((float) $stat['hit_rate'], 1, ',', '.') }} %</td><td class="px-2 py-2 text-right font-bold tabular-nums text-amber-300">{{ number_format(\App\Support\ProfitFactor::cap($stat['profit_factor']) ?? 3, 2, ',', '.') }}</td><td class="px-2 py-2 text-right font-bold tabular-nums text-rose-300">{{ number_format((float) $stat['drawdown'], 1, ',', '.') }} %</td><td class="px-2 py-2 text-right font-bold tabular-nums {{ (float) $stat['average_return'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ (float) $stat['average_return'] >= 0 ? '+' : '' }}{{ number_format((float) $stat['average_return'], 2, ',', '.') }} %</td><td class="px-2 py-2 text-right font-bold tabular-nums text-[var(--ak-text)]">{{ number_format((float) $stat['score'], 1, ',', '.') }} / {{ number_format((float) $stat['confidence'], 0, ',', '.') }} %</td></tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-10 text-center text-[var(--ak-muted)]">{{ __('Für die aktuelle Filterauswahl liegen keine Backtest-Trades vor.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        @endif
        <script>
            window.akiChatHistory = [];
            window.akiAsk = async function (event) {
                event.preventDefault();
                const input = document.getElementById('aki-chat-input');
                const messages = document.getElementById('aki-chat-messages');
                const question = (input?.value || '').trim();
                if (!question || !messages) return false;
                const add = (text, user) => {
                    const row = document.createElement('div'); row.className = `flex ${user ? 'justify-end' : 'justify-start'}`;
                    const bubble = document.createElement('p'); bubble.className = `max-w-[88%] rounded-xl px-3 py-2 text-xs leading-5 ${user ? 'bg-teal-500 text-white' : 'bg-[var(--ak-surface-muted)] text-[var(--ak-text)]'}`; bubble.textContent = text;
                    row.appendChild(bubble); messages.appendChild(row); messages.scrollTop = messages.scrollHeight;
                };
                add(question, true); input.value = '';
                const pending = document.createElement('div'); pending.id = 'aki-chat-pending'; pending.className = 'flex justify-start'; pending.innerHTML = '<p class="max-w-[88%] rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2 text-xs text-[var(--ak-muted)]">AKI denkt …</p>'; messages.appendChild(pending);
                try {
                    const response = await fetch('{{ route('aki.chat') }}', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                        body: JSON.stringify({
                            question,
                            messages: window.akiChatHistory,
                            filters: Object.fromEntries(new URLSearchParams(window.location.search)),
                            mode: 'standard'
                        })
                    });
                    const payload = await response.json();
                    pending.remove();
                    const answer = response.ok ? (payload.answer || 'Keine Antwort erhalten.') : (payload.message || 'Die KI ist gerade nicht erreichbar.');
                    add(answer, false);
                    window.akiChatHistory = [...window.akiChatHistory, { role: 'user', content: question }, { role: 'assistant', content: answer }].slice(-8);
                } catch (error) {
                    pending.remove(); add('Die Verbindung zur KI konnte nicht hergestellt werden.', false);
                }
                return false;
            };
        </script>

        @if ($qualitySetupMode)
        <details class="ak-card mb-2 min-h-0 shrink-0 overflow-hidden rounded-lg border-orange-400/35">
            <summary class="flex h-10 cursor-pointer list-none items-center justify-between gap-3 px-3 text-[10px] font-black text-[var(--ak-text)]">
                <span class="flex items-center gap-2"><x-heroicon-o-question-mark-circle class="h-4 w-4 text-amber-300" />{{ __('Wie funktioniert Smart Selection?') }}</span>
                <span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Kurzinfo öffnen') }}</span>
            </summary>
            <div class="border-t border-orange-400/15 px-4 py-3 text-[11px] leading-5 text-[var(--ak-muted)]">
                {{ __('Setze links die Mindestanforderungen für Score, Konfidenz und Performance. Die beiden Karten zeigen anschließend nur die Backtest-Auswertung des gewählten Portfolios. Je enger der Filter, desto weniger Trades stehen für die Bewertung zur Verfügung.') }}
            </div>
        </details>
        @endif

        <form
            id="prediction-heatmap-filters"
            method="GET"
            action="{{ route($heatmapFilterRoute) }}"
            x-data="{
                score: Number({{ $rangeValue('score_min', 0, 0, 'score') }}),
                confidence: Number({{ $rangeValue('confidence_min', 0, 0, 'confidence') }}),
                drawdown: Number({{ $rangeValue('drawdown_max', $rangeMaxima['drawdown'], 0, 'drawdown') }}),
                profitPerTrade: Number({{ $rangeValue('profit_per_trade_min', 0, 0, 'profit_factor') }}),
                volatility: Number({{ $rangeValue('volatility_max', $rangeMaxima['volatility'], 0, 'volatility') }}),
                predictedReturn: Number({{ $rangeValue('predicted_return_min', 0.5, 0.5, 'predicted_return') }}),
                pe: Number({{ $rangeValue('pe_max', $rangeMaxima['pe'], 0, 'pe') }}),
                dividend: Number({{ $rangeValue('dividend_yield_min', 0, 0, 'dividend_yield') }}),
                marketCap: Number({{ $rangeValue('market_cap_min', 0, 0, 'market_cap') }}),
                revenueGrowth: Number({{ $rangeValue('revenue_growth_min', -50, -50, 'revenue_growth') }}),
                hitRate: Number({{ $rangeValue('hit_rate_min', 0, 0, 'hit_rate') }}),
                minimumTrades: Number({{ $rangeValue('minimum_trades', 0, 0, 'trades') }}),
                sectorScore: Number({{ max(-1, min(10, (float) request('sector_score_min', -1))) }}),
                scoreGrade(value) {
                    const grades = ['5−', '5+', '4−', '4+', '3−', '3+', '2−', '2+', '1−', '1+'];
                    return grades[Math.max(0, Math.min(9, Math.floor(Number(value) || 0)))];
                },
                loading: false,
                searchTimer: null,
                submitSearch() {
                    window.clearTimeout(this.searchTimer);
                    const form = this.$el.closest('form');
                    this.searchTimer = window.setTimeout(() => form?.requestSubmit(), 450);
                },
                beginFilterUpdate() {
                    this.loading = true;
                    sessionStorage.setItem('aktienki-strategy-filter-scroll', String(window.scrollY));
                }
            }"
            @submit="beginFilterUpdate()"
            class="ak-prediction-filterboard relative z-50 mb-3 flex shrink-0 flex-col gap-1 {{ $qualitySetupMode ? 'bg-transparent p-0 shadow-none' : 'rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]' }}"
        >
            <div x-show="loading" x-cloak class="ak-filter-loading absolute inset-0 z-[120] flex items-center justify-center rounded-xl">
                <div class="inline-flex items-center gap-2 rounded-lg border border-cyan-300/20 bg-slate-950/85 px-3 py-2 shadow-xl">
                    <span class="ak-backtest-spinner" aria-hidden="true"></span>
                    <span class="text-[10px] font-black uppercase tracking-[.1em] text-cyan-100">{{ $qualitySetupMode ? __('Smart Selection wird berechnet') : __('Filter werden angewendet') }}</span>
                </div>
            </div>
            @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
            @if (request()->filled('backtest_run'))<input type="hidden" name="backtest_run" value="{{ request('backtest_run') }}">@endif
            @if ($shortMode)<input type="hidden" name="signal" value="SELL">@endif
            @if ($qualitySetupMode)
            <div class="ak-card ak-dashboard-card grid shrink-0 gap-2 rounded-xl border-orange-400/35 p-2 shadow-[var(--ak-shadow)]" style="min-height:0 !important;grid-template-columns:repeat(5,minmax(0,1fr));background:var(--smart-card-bg) !important;">
                <select name="index" class="ak-input h-10 rounded-lg px-2 text-[11px]"><option value="">{{ __('Alle Indizes') }}</option>@foreach (($indices ?? []) as $index)<option value="{{ $index->symbol }}" @selected(request('index') === $index->symbol)>{{ $index->name ?: $index->symbol }}</option>@endforeach</select>
                <select name="country" class="ak-input h-10 rounded-lg px-2 text-[11px]"><option value="">{{ __('Alle Länder') }}</option>@foreach ($countries as $country)<option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>@endforeach</select>
                <select name="exchange" class="ak-input h-10 rounded-lg px-2 text-[11px]"><option value="">{{ __('Alle Börsen') }}</option>@foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(request('exchange') === $exchange->code)>{{ $exchange->name ?: $exchange->code }}</option>@endforeach</select>
                <select name="sector" class="ak-input h-10 rounded-lg px-2 text-[11px]"><option value="">{{ __('Alle Sektoren') }}</option>@foreach ($sectors as $sector)<option value="{{ $sector }}" @selected(request('sector') === $sector)>{{ __($sector) }}</option>@endforeach</select>
                <select name="quality_tier" class="ak-input h-10 rounded-lg px-2 text-[11px]" title="{{ __('Modellqualität / Quality Gate') }}">
                    <option value="">{{ __('Modellqualität') }}</option>
                    @foreach (($qualityTiers ?? []) as $qualityTier)
                        <option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __('Quality Gate') }} · {{ __($qualityTier->name) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if (! $qualitySetupMode)
            @if ($setupMode)
            @php $universeChanged = $changedFilterCount($universeFilterDefaults); @endphp
            <div class="ak-filter-group-grid grid min-w-0 gap-1">
            <details class="ak-filter-group rounded-lg border border-[var(--ak-border)] bg-white/[.02]" @if($universeChanged > 0) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2">
                    <span><strong class="block text-[11px] font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Portfolio & Modell') }}</strong><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Markt, Modelle, Quality Gate und Positionslogik') }}</small></span>
                    <span class="flex items-center gap-2"><span class="rounded-full border border-cyan-300/25 bg-cyan-400/[.10] px-2 py-1 text-[9px] font-black text-cyan-300">{{ $universeChanged }} {{ __('geändert') }}</span><a href="{{ $filterGroupResetUrl($heatmapFilterRoute, array_keys($universeFilterDefaults)) }}" onclick="event.stopPropagation()" class="inline-flex h-7 items-center gap-1 rounded-md border border-[var(--ak-border)] px-2 text-[8px] font-black uppercase text-[var(--ak-muted)]"><x-heroicon-o-arrow-path class="h-3 w-3" />{{ __('Reset') }}</a><x-heroicon-o-chevron-down class="h-4 w-4 text-cyan-300" /></span>
                </summary>
                <div class="border-t border-[var(--ak-border)] p-2">
            @endif
            <div class="flex min-w-0 items-center gap-1">
            <div
                class="grid min-w-0 flex-1 gap-1"
                style="grid-template-columns: {{ $setupMode
                    ? 'repeat(6,minmax(82px,1fr))'
                    : 'repeat(5,minmax(82px,1fr))' }};"
            >
            <select name="country" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Land') }}</option>
                @foreach ($countries as $country)<option value="{{ $country }}" @selected(strtoupper((string) request('country')) === strtoupper((string) $country))>{{ $country }}</option>@endforeach
            </select>
            <select name="exchange" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Börse') }}</option>
                @foreach ($exchanges as $exchange)<option value="{{ $exchange->code }}" @selected(strtoupper((string) request('exchange')) === strtoupper((string) $exchange->code))>{{ $exchange->name ?: $exchange->code }}</option>@endforeach
            </select>
            <select name="sector" onchange="this.form.requestSubmit()" class="ak-input h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Sektor') }}</option>
                @foreach ($sectors as $sector)<option value="{{ $sector }}" @selected((string) request('sector') === (string) $sector)>{{ __($sector) }}</option>@endforeach
            </select>
            @php
                $selectedModelIds = collect((array) request('model'))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();
            @endphp
            <div x-data="{ open: false }" class="relative min-w-0">
                <button type="button" @click="open = !open" class="ak-input flex h-10 w-full min-w-0 items-center justify-between rounded-[5px] px-2 text-[11px]" title="{{ __('Modelle') }}">
                    <span class="truncate">{{ $selectedModelIds->isEmpty() ? __('Modelle') : __(':count Modelle', ['count' => $selectedModelIds->count()]) }}</span>
                    <span class="ak-filter-count-value ml-1 rounded bg-teal-400/15 px-1 text-[8px] font-black text-teal-300">{{ $selectedModelIds->count() }}</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="ak-filter-dropdown-menu absolute left-0 top-9 z-[80] max-h-64 w-64 overflow-y-auto rounded-lg border border-[var(--ak-border)] bg-[#102b35] p-2 shadow-2xl">
                    @foreach ($models as $model)
                        <label class="ak-filter-dropdown-option flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-white/[.05]">
                            <input type="checkbox" name="model[]" value="{{ $model->id }}" @checked($selectedModelIds->contains((int) $model->id)) class="ak-filter-checkbox h-4 w-4">
                            <span class="truncate text-[10px] font-bold text-slate-200">{{ $model->public_alias }}</span>
                        </label>
                    @endforeach
                    <button type="submit" class="mt-2 w-full rounded-md bg-teal-500 px-2 py-1.5 text-[9px] font-black uppercase tracking-[.08em] text-slate-950 hover:bg-teal-400">
                        {{ __('Übernehmen') }}
                    </button>
                </div>
            </div>
            <select name="quality_tier" onchange="this.form.requestSubmit()" class="ak-input ak-quality-tier-select h-10 min-w-0 rounded-[5px] px-1.5 text-[11px]">
                <option value="">{{ __('Modellstufe mindestens') }}</option>
                @foreach ($qualityTiers as $qualityTier)<option value="{{ $qualityTier->code }}" @selected(request('quality_tier') === $qualityTier->code)>{{ __($qualityTier->name) }}</option>@endforeach
            </select>
            @if ($setupMode)
                <div class="ak-input flex h-10 min-w-0 items-center overflow-hidden rounded-[5px] p-0.5 {{ request('exit_strategy') === 'buy_and_hold' ? 'cursor-not-allowed opacity-45' : '' }}" title="{{ request('exit_strategy') === 'buy_and_hold' ? __('Money Manager ist bei Buy and Hold deaktiviert') : __('Maximaler Kapitalanteil je Aktie bei freiem Kapital') }}" role="radiogroup" aria-label="{{ __('Positionsanteil') }}">
                    <span class="shrink-0 px-1 text-[8px] font-bold text-slate-500">{{ __('Anteil') }}</span>
                    @for ($factor = 1; $factor <= 5; $factor++)
                        <label class="ak-position-factor flex h-full min-w-0 flex-1 cursor-pointer text-[9px] font-black">
                            <input type="radio" name="position_factor" value="{{ $factor }}" @checked((request('exit_strategy') === 'buy_and_hold' ? 1 : (int) request('position_factor', 1)) === $factor) @disabled(request('exit_strategy') === 'buy_and_hold') onchange="this.form.requestSubmit()" class="sr-only">
                            <span class="flex h-full w-full items-center justify-center rounded-[3px] text-slate-400 transition hover:bg-white/[.05] hover:text-slate-200">{{ $factor }}×</span>
                        </label>
                    @endfor
                </div>
            @endif
            </div>
            @if ($setupMode)
                <select name="gate_mode" onchange="this.form.requestSubmit()" class="ak-input h-10 w-32 shrink-0 rounded-[5px] px-1.5 text-[11px] {{ empty($hasPersonalQualityGate) ? 'cursor-not-allowed opacity-55' : '' }}" title="{{ __('Quality Gate für diesen Backtest') }}">
                    <option value="system" @selected(request('gate_mode', 'system') === 'system')>{{ __('System-Gate') }}</option>
                    <option value="personal" @selected(request('gate_mode') === 'personal') @disabled(empty($hasPersonalQualityGate))>{{ __('Mein Quality Gate') }}</option>
                </select>
                <div class="ak-filter-result-count inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-[5px] border border-teal-300/25 bg-teal-400/[.10] px-2.5 text-[10px] font-black text-teal-100" title="{{ __('Treffer im gesamten geprüften Portfolio') }}">
                    <x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 text-teal-300" />
                    <span class="tabular-nums">{{ number_format((int) ($heatmapSummary->instruments ?? 0), 0, ',', '.') }}</span>
                    <span class="text-teal-300/80">/ {{ number_format((int) ($heatmapUniverseInstruments ?? 0), 0, ',', '.') }} {{ __('Aktien') }}</span>
                </div>
            @endif
            </div>
            @if ($setupMode)</div></details>@endif
            @endif
            @if ($qualitySetupMode)
            <div class="grid min-h-0 items-start gap-3 p-0 lg:grid-cols-3">
                <div class="ak-card ak-dashboard-card min-h-[480px] self-start rounded-xl border-orange-400/35 p-3 shadow-[var(--ak-shadow)]" style="background: var(--smart-card-bg) !important;">
                    <button type="submit" class="mb-2 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg border border-cyan-300/35 bg-cyan-400/[.14] px-4 text-[10px] font-black uppercase tracking-[.1em] text-cyan-100 transition hover:bg-cyan-400/[.24]">
                        <x-heroicon-o-calculator class="h-4 w-4" />{{ __('Berechnen') }}
                    </button>
                    <div class="mb-2 flex items-center gap-2"><span class="text-[10px] font-black uppercase tracking-[.15em] text-amber-300">{{ __('Label-Faktoren') }}</span><span class="h-px flex-1 bg-amber-300/15"></span><button type="button" @if($canSaveSmartLabel ?? false) @click="$dispatch('open-save-filter')" @else @click="showPlanNotice(@js(__('Label speichern')), @js(__('Speichere deine aktuelle Smart Selection als persönliches Label und verwende sie jederzeit erneut.')), 'PLUS')" @endif class="inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1 text-[8px] font-black uppercase tracking-[.08em] transition {{ ($canSaveSmartLabel ?? false) ? 'border-teal-300/30 bg-teal-400/[.10] text-teal-200 hover:bg-teal-400/[.18]' : 'border-amber-300/25 bg-amber-300/[.07] text-amber-200 hover:bg-amber-300/[.14]' }}"><x-heroicon-o-bookmark class="h-3 w-3" />{{ __('Label speichern') }}</button><a href="{{ route('setup.quality', ['reset' => 1]) }}" class="inline-flex shrink-0 items-center gap-1 rounded-md border border-amber-300/25 bg-amber-300/[.08] px-2 py-1 text-[8px] font-black uppercase tracking-[.08em] text-amber-200 transition hover:border-amber-200/50 hover:bg-amber-300/[.16]" title="{{ __('Alle Filter auf Standardwerte zurücksetzen') }}"><x-heroicon-o-arrow-path class="h-3 w-3" />{{ __('Reset') }}</a></div>
                    <div class="grid grid-cols-1 gap-3">
            @else
            @if ($setupMode)
            @php $performanceChanged = $changedFilterCount($performanceFilterDefaults); @endphp
            <details class="ak-filter-group rounded-lg border border-[var(--ak-border)] bg-white/[.02]" @if($performanceChanged > 0) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2">
                    <span><strong class="block text-[11px] font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Performance & Risiko') }}</strong><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Historische Robustheit, Profit Factor und Mindestanzahl an Trades') }}</small></span>
                    <span class="flex items-center gap-2"><span class="rounded-full border border-amber-300/25 bg-amber-400/[.10] px-2 py-1 text-[9px] font-black text-amber-300">{{ $performanceChanged }} {{ __('geändert') }}</span><a href="{{ $filterGroupResetUrl($heatmapFilterRoute, array_keys($performanceFilterDefaults)) }}" onclick="event.stopPropagation()" class="inline-flex h-7 items-center gap-1 rounded-md border border-[var(--ak-border)] px-2 text-[8px] font-black uppercase text-[var(--ak-muted)]"><x-heroicon-o-arrow-path class="h-3 w-3" />{{ __('Reset') }}</a><x-heroicon-o-chevron-down class="h-4 w-4 text-amber-300" /></span>
                </summary>
                <div class="border-t border-[var(--ak-border)] p-2">
            @endif
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
            @endif
                <section class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.035] p-2">
                    <h3 class="mb-2 text-[9px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Modellqualität') }}</h3>
                    <div class="grid grid-cols-2 gap-1">
                        <label class="ak-heatmap-range" title="{{ __('Historische Walk-Forward-Richtungsgenauigkeit des Modells; der finale KI-Score wird hier nicht verwendet.') }}"><span>{{ __('Modellscore mindestens') }} <b x-text="scoreGrade(score)">{{ \App\Support\QualityGrade::fromPercent($rangeValue('score_min', 0, 0, 'score') * 10) }}</b></span><input name="score_min" type="range" min="0" max="9" step="1" value="{{ max(0, min(9, floor($rangeValue('score_min', 0, 0, 'score')))) }}" x-model.number="score" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                        <label class="ak-heatmap-range"><span>{{ __('Konfidenz') }} ≥ <b x-text="`${confidence}%`">{{ number_format((float) request('confidence_min', 0), 0, ',', '.') }}%</b></span><input name="confidence_min" type="range" min="0" max="{{ $rangeMaxima['confidence'] }}" step="5" value="{{ $rangeValue('confidence_min', 0, 0, 'confidence') }}" x-model.number="confidence" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                        @if ($setupMode && ! $shortMode)<label class="ak-heatmap-range"><span>{{ __('Hitrate') }} ≥ <b x-text="hitRate <= 0 ? '{{ __('Alle') }}' : `${hitRate.toFixed(0)} %`">{{ (float) request('hit_rate_min', 0) <= 0 ? __('Alle') : number_format((float) request('hit_rate_min'), 0, ',', '.').' %' }}</b></span><input name="hit_rate_min" type="range" min="0" max="{{ $rangeMaxima['hit_rate'] }}" step="5" value="{{ $rangeValue('hit_rate_min', 0, 0, 'hit_rate') }}" x-model.number="hitRate" onchange="this.form.requestSubmit()"></label>@endif
                        <label class="ak-heatmap-range"><span>{{ __('Historische Trades') }} ≥ <b x-text="minimumTrades <= 0 ? '{{ __('Alle') }}' : minimumTrades">{{ (int) request('minimum_trades', 0) <= 0 ? __('Alle') : number_format((int) request('minimum_trades'), 0, ',', '.') }}</b></span><input name="minimum_trades" type="range" min="0" max="{{ (int) $rangeMaxima['trades'] }}" step="5" value="{{ $rangeValue('minimum_trades', 0, 0, 'trades') }}" x-model.number="minimumTrades" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                    </div>
                </section>
                <section class="rounded-lg border border-emerald-300/15 bg-emerald-400/[.035] p-2">
                    <h3 class="mb-2 text-[9px] font-black uppercase tracking-[.12em] text-emerald-300">{{ __('Performance') }}</h3>
                    <div class="grid grid-cols-2 gap-1">
                        <label class="ak-heatmap-range"><span>{{ __('Profit je Trade') }} ≥ <b x-text="profitPerTrade <= 0 ? '{{ __('Alle') }}' : profitPerTrade.toFixed(1).replace('.', ',')">{{ (float) request('profit_per_trade_min', 0) <= 0 ? __('Alle') : number_format((float) request('profit_per_trade_min'), 1, ',', '.') }}</b></span><input name="profit_per_trade_min" type="range" min="0" max="{{ $rangeMaxima['profit_factor'] }}" step="0.1" value="{{ $rangeValue('profit_per_trade_min', 0, 0, 'profit_factor') }}" x-model.number="profitPerTrade" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                        @if ($qualitySetupMode || ($setupMode && ! $shortMode))<label class="ak-heatmap-range"><span>{{ __('Renditeerwartung · 20 Tage') }} ≥ <b x-text="predictedReturn >= 10 ? '10 % +' : `${predictedReturn.toFixed(1).replace('.', ',')} %`">{{ (float) request('predicted_return_min', 0.5) >= 10 ? '10 % +' : number_format(max(0.5, (float) request('predicted_return_min', 0.5)), 1, ',', '.').' %' }}</b></span><input name="predicted_return_min" type="range" min="0.5" max="10" step="0.5" value="{{ $rangeValue('predicted_return_min', 0.5, 0.5, 'predicted_return') }}" x-model.number="predictedReturn" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>@endif
                        @if ($setupMode && ! $shortMode)<label class="ak-heatmap-range"><span>{{ __('Sektor-Score') }} <b x-text="sectorScore < 0 ? '{{ __('Alle') }}' : `> ${sectorScore.toFixed(0)}`">{{ (float) request('sector_score_min', -1) < 0 ? __('Alle') : '> '.number_format((float) request('sector_score_min'), 0, ',', '.') }}</b></span><input name="sector_score_min" type="range" min="-1" max="10" step="1" value="{{ max(-1, min(10, (float) request('sector_score_min', -1))) }}" x-model.number="sectorScore" onchange="this.form.requestSubmit()"></label>@endif
                    </div>
                </section>
                <section class="rounded-lg border border-rose-300/15 bg-rose-400/[.03] p-2">
                    <h3 class="mb-2 text-[9px] font-black uppercase tracking-[.12em] text-rose-300">{{ __('Risiko') }}</h3>
                    <div class="grid grid-cols-2 gap-1">
                        <label class="ak-heatmap-range"><span>{{ __('Drawdown') }} ≤ <b x-text="drawdown >= {{ $rangeMaxima['drawdown'] }} ? '{{ __('Alle') }}' : `${drawdown}%`">{{ $rangeValue('drawdown_max', $rangeMaxima['drawdown'], 0, 'drawdown') >= $rangeMaxima['drawdown'] ? __('Alle') : number_format($rangeValue('drawdown_max', $rangeMaxima['drawdown'], 0, 'drawdown'), 0, ',', '.').'%' }}</b></span><input name="drawdown_max" type="range" min="0" max="{{ $rangeMaxima['drawdown'] }}" step="5" value="{{ $rangeValue('drawdown_max', $rangeMaxima['drawdown'], 0, 'drawdown') }}" x-model.number="drawdown" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                        <label class="ak-heatmap-range"><span>{{ __('Volatilität') }} ≤ <b x-text="volatility >= {{ $rangeMaxima['volatility'] }} ? '{{ __('Alle') }}' : `${volatility}%`">{{ $rangeValue('volatility_max', $rangeMaxima['volatility'], 0, 'volatility') >= $rangeMaxima['volatility'] ? __('Alle') : number_format($rangeValue('volatility_max', $rangeMaxima['volatility'], 0, 'volatility'), 0, ',', '.').'%' }}</b></span><input name="volatility_max" type="range" min="0" max="{{ $rangeMaxima['volatility'] }}" step="5" value="{{ $rangeValue('volatility_max', $rangeMaxima['volatility'], 0, 'volatility') }}" x-model.number="volatility" onchange="if (!@js($qualitySetupMode)) this.form.requestSubmit()"></label>
                    </div>
                </section>
            @if ($qualitySetupMode)
                    </div>
                </div>
                <aside class="ak-card ak-dashboard-card min-h-[480px] self-start rounded-xl border-orange-400/35 p-3 shadow-[var(--ak-shadow)]" style="background: var(--smart-card-bg) !important;" aria-label="{{ __('Portfolio-Statistik') }}">
                    @php $contextMax = max(1, ...array_map('intval', array_values($marketContextCounts ?? []))); @endphp
                    <div class="mb-2 flex items-center justify-between gap-2"><span class="text-[10px] font-black uppercase tracking-[.15em] text-cyan-300">{{ __('Portfolio') }}</span><x-heroicon-o-globe-alt class="h-4 w-4 text-cyan-300" /></div>
                    <p class="mb-3 text-[9px] leading-4 text-slate-400">{{ __('Welche Märkte der Filter aktuell umfasst.') }}</p>
                    <div class="space-y-2.5">
                        @foreach ([['Börsen','exchanges','text-cyan-300'],['Sektoren','sectors','text-violet-300'],['Länder','countries','text-emerald-300']] as [$label,$key,$tone])
                            @php $count = (int) ($marketContextCounts[$key] ?? 0); $bar = $count > 0 ? max(8, min(100, ($count / $contextMax) * 100)) : 0; @endphp
                            <div><div class="mb-1 flex items-center justify-between gap-2"><span class="text-[9px] font-bold uppercase tracking-wide text-slate-300">{{ __($label) }}</span><strong class="text-sm font-black tabular-nums {{ $tone }}">{{ number_format($count, 0, ',', '.') }}</strong></div><div class="h-1.5 overflow-hidden rounded-full bg-white/[.08]"><div class="h-full rounded-full bg-current {{ $tone }} transition-[width] duration-500 ease-out" style="width: {{ $bar }}%"></div></div></div>
                        @endforeach
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2"><div class="rounded-lg border border-white/[.07] bg-white/[.035] p-2 text-center"><strong class="block text-lg font-black tabular-nums text-white">{{ number_format((int) ($heatmapSummary?->candidates ?? $heatmapSummary?->trades ?? 0), 0, ',', '.') }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ __('Kandidaten') }}</span></div><div class="rounded-lg border border-white/[.07] bg-white/[.035] p-2 text-center"><strong class="block text-lg font-black tabular-nums text-cyan-300">{{ number_format((int) ($heatmapSummary?->qualified ?? 0), 0, ',', '.') }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ __('qualifiziert') }}</span></div></div>
                </aside>
                <aside class="ak-card ak-dashboard-card min-h-[480px] self-start rounded-xl border-orange-400/35 p-3 shadow-[var(--ak-shadow)]" style="background: var(--smart-card-bg) !important;" aria-label="{{ __('Performance-Statistik aller Symbole') }}">
                    <div class="mb-2 flex items-center justify-between gap-2"><span class="text-[10px] font-black uppercase tracking-[.15em] text-amber-300">{{ __('Performance') }}</span><div class="flex items-center gap-2"><button type="button" @click="individualStatsOpen = true" class="inline-flex items-center gap-1 rounded-md border border-cyan-300/25 bg-cyan-300/[.08] px-2 py-1 text-[8px] font-black uppercase tracking-[.06em] text-cyan-200 transition hover:bg-cyan-300/[.16]"><x-heroicon-o-list-bullet class="h-3 w-3" />{{ __('Aktienstatistik') }}</button><x-heroicon-o-chart-bar class="h-4 w-4 text-amber-300" /></div></div>
                    <p class="mb-3 text-[9px] leading-4 text-slate-400">{{ __('Backtest-Kennzahlen des aktuellen Filterportfolios.') }}</p>
                    @php
                        $hitRateValue = max(0, min(100, (float) ($heatmapSummary?->hit_rate ?? 0)));
                        $drawdownValue = max(0, min(100, (float) ($heatmapSummary?->drawdown ?? 0)));
                        $profitPerTrade = (float) ($heatmapSummary?->normalized_profit_per_trade ?? 0);
                        $profitPerTradeValue = max(0, min(100, 50 + ($profitPerTrade * 25)));
                        $profitPerTradeColor = $profitPerTrade > 0 ? '#34d399' : ($profitPerTrade < 0 ? '#fb7185' : '#fbbf24');
                    @endphp
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ([['Gewonnene Trades', (int) ($heatmapSummary?->winning_trades ?? 0), 'text-emerald-300'],['Trades gesamt',(int) ($heatmapSummary?->trades ?? 0),'text-white'],['Trades/Monat',number_format((float) ($heatmapSummary?->trades_per_month ?? 0), 1, ',', '.'),'text-teal-300'],['Ø Profit je Trade · 3J WF',($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade, 2, ',', '.').' ATR',$profitPerTrade >= 0 ? 'text-emerald-300' : 'text-rose-300'],['Volatilität',number_format((float) ($heatmapSummary?->volatility ?? 0), 1, ',', '.').' %','text-orange-300']] as [$label,$value,$tone])
                            <div class="rounded-lg border border-white/[.07] bg-white/[.035] p-2"><strong class="block text-base font-black tabular-nums {{ $tone }}">{{ $value }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ __($label) }}</span></div>
                        @endforeach
                    </div>
                    @php $profitBar = max(5, $profitPerTradeValue); $hit = min(100, max(5, (float) ($heatmapSummary?->hit_rate ?? 0))); @endphp
                    <div class="mt-3 space-y-3">
                        <div><div class="mb-1 flex justify-between text-[9px] font-bold uppercase text-slate-400"><span>{{ __('Volatilitätsnorm. Profit je Trade') }}</span><span>{{ ($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade, 2, ',', '.') }} ATR</span></div><div class="h-2.5 overflow-hidden rounded-full bg-white/[.08]"><div class="h-full rounded-full transition-[width] duration-500 ease-out" style="width: {{ $profitBar }}%;background:{{ $profitPerTradeColor }}"></div></div></div>
                        <div><div class="mb-1 flex justify-between text-[9px] font-bold uppercase text-slate-400"><span>{{ __('Trefferquote') }}</span><span>{{ number_format($hitRateValue, 1, ',', '.') }} %</span></div><div class="h-2.5 overflow-hidden rounded-full bg-white/[.08]"><div class="h-full rounded-full bg-cyan-300 transition-[width] duration-500 ease-out" style="width: {{ $hit }}%"></div></div></div>
                        <div><div class="mb-1 flex justify-between text-[9px] font-bold uppercase text-slate-400"><span>{{ __('Max. Drawdown') }}</span><span>{{ number_format($drawdownValue, 1, ',', '.') }} %</span></div><div class="h-2.5 overflow-hidden rounded-full bg-white/[.08]"><div class="h-full rounded-full bg-rose-400 transition-[width] duration-500 ease-out" style="width: {{ max(3, $drawdownValue) }}%"></div></div></div>
                    </div>
                    <p class="mt-3 text-[9px] leading-4 text-slate-400">{{ __('Die Statistik aktualisiert sich mit den Reglern und hilft, einen robusten Filter zu finden.') }}</p>
                </aside>
            </div>
            @endif
            @if (! $qualitySetupMode)</div>@endif
            @if ($setupMode && ! $qualitySetupMode)</div></details>@endif

        @if ($setupMode && ! $shortMode && ! $qualitySetupMode)
            @php $fundamentalChanged = $changedFilterCount($fundamentalFilterDefaults); @endphp
            <details class="ak-filter-group rounded-lg border border-[var(--ak-border)] bg-white/[.02]" @if($fundamentalChanged > 0) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2">
                    <span><strong class="block text-[11px] font-black uppercase tracking-[.12em] text-[var(--ak-text)]">{{ __('Fundamentaldaten') }}</strong><small class="text-[9px] text-[var(--ak-muted)]">{{ __('Bewertung, Ausschüttung, Unternehmensgröße und Wachstum') }}</small></span>
                    <span class="flex items-center gap-2"><span class="rounded-full border border-emerald-300/25 bg-emerald-400/[.10] px-2 py-1 text-[9px] font-black text-emerald-300">{{ $fundamentalChanged }} {{ __('geändert') }}</span><a href="{{ $filterGroupResetUrl($heatmapFilterRoute, array_keys($fundamentalFilterDefaults)) }}" onclick="event.stopPropagation()" class="inline-flex h-7 items-center gap-1 rounded-md border border-[var(--ak-border)] px-2 text-[8px] font-black uppercase text-[var(--ak-muted)]"><x-heroicon-o-arrow-path class="h-3 w-3" />{{ __('Reset') }}</a><x-heroicon-o-chevron-down class="h-4 w-4 text-emerald-300" /></span>
                </summary>
            <div
                id="fundamental-heatmap-filters"
                class="min-w-0 overflow-x-auto border-t border-[var(--ak-border)] p-2"
            >
                <label class="ak-fundamental-range">
                    <span>{{ __('KGV') }} ≤ <b x-text="pe >= {{ $rangeMaxima['pe'] }} ? '{{ __('Alle') }}' : pe.toFixed(0)">{{ $rangeValue('pe_max', $rangeMaxima['pe'], 0, 'pe') >= $rangeMaxima['pe'] ? __('Alle') : number_format($rangeValue('pe_max', $rangeMaxima['pe'], 0, 'pe'), 0, ',', '.') }}</b></span>
                    <input name="pe_max" type="range" min="0" max="{{ $rangeMaxima['pe'] }}" step="1" value="{{ $rangeValue('pe_max', $rangeMaxima['pe'], 0, 'pe') }}" x-model.number="pe" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span class="flex items-center gap-2">{{ __('Dividendenrendite') }}
                        <select name="dividend_yield_operator" onchange="this.form.requestSubmit()" class="ak-input h-7 rounded-md px-2 text-[10px] font-black">
                            <option value="gte" @selected(request('dividend_yield_operator', 'gte') === 'gte')>≥</option>
                            <option value="lte" @selected(request('dividend_yield_operator') === 'lte')>≤</option>
                        </select>
                        <b x-text="`${dividend.toFixed(1).replace('.', ',')} %`">{{ number_format((float) request('dividend_yield_min', 0), 1, ',', '.') }} %</b>
                    </span>
                    <input name="dividend_yield_min" type="range" min="0" max="{{ $rangeMaxima['dividend_yield'] }}" step="0.1" value="{{ $rangeValue('dividend_yield_min', 0, 0, 'dividend_yield') }}" x-model.number="dividend" onchange="this.form.requestSubmit()">
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Marktkapitalisierung') }}</span>
                    <select name="market_cap_group" onchange="this.form.requestSubmit()" class="ak-input mt-2 h-9 w-full rounded-md px-2 text-[10px] font-bold">
                        <option value="all" @selected(request('market_cap_group', 'all') === 'all')>{{ __('Alle Größen') }}</option>
                        <option value="small" @selected(request('market_cap_group') === 'small')>{{ __('Klein · unter 2 Mrd.') }}</option>
                        <option value="mid" @selected(request('market_cap_group') === 'mid')>{{ __('Mittel · 2 bis unter 10 Mrd.') }}</option>
                        <option value="large" @selected(request('market_cap_group') === 'large')>{{ __('Groß · ab 10 Mrd.') }}</option>
                    </select>
                </label>
                <label class="ak-fundamental-range">
                    <span>{{ __('Umsatzwachstum') }} ≥ <b x-text="revenueGrowth <= -50 ? '{{ __('Alle') }}' : `${revenueGrowth.toFixed(0)} %`">{{ (float) request('revenue_growth_min', -50) <= -50 ? __('Alle') : number_format((float) request('revenue_growth_min'), 0, ',', '.').' %' }}</b></span>
                    <input name="revenue_growth_min" type="range" min="-50" max="{{ $rangeMaxima['revenue_growth'] }}" step="1" value="{{ $rangeValue('revenue_growth_min', -50, -50, 'revenue_growth') }}" x-model.number="revenueGrowth" onchange="this.form.requestSubmit()">
                </label>
            </div>
            </details>
            </div>
        @endif
        </form>

        @if ($shortMode)
            <section class="mb-3 flex shrink-0 items-center justify-between gap-4 rounded-xl border border-rose-300/20 bg-rose-400/[.055] px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-400/10 text-rose-300"><x-heroicon-o-arrow-trending-down class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-rose-300">{{ __('Short-Datenbasis') }}</p>
                        <p class="truncate text-[10px] text-[var(--ak-muted)]">{{ __('Der Tester verwendet nur historisch berechnete SELL-Prognosen mit negativer 20-Tage-Prognose. Long-Trades werden ausgeschlossen.') }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-lg border border-amber-300/20 bg-amber-300/[.08] px-3 py-2 text-[9px] font-black text-amber-200">{{ __('Historischer Short-Lauf erforderlich') }}</span>
            </section>
        @endif

        @if ($setupMode && ! $shortMode)
            @php
                $selectedExitStrategy = (string) request('exit_strategy', $editingSavedFilter?->filters['exit_strategy'] ?? 'fixed_20d');
            @endphp
            <section x-data="{ saveOpen: @js($qualitySetupMode && ($canSaveSmartLabel ?? false) && request()->boolean('open_label_modal')), exitStrategy: @js($selectedExitStrategy), visibility: @js(old('visibility', $editingSavedFilter?->visibility ?? 'private')), automationEnabled: @js(request()->boolean('automatic_optimization')) }" @open-save-filter.window="if (@js($qualitySetupMode ? ($canSaveSmartLabel ?? false) : ($canSaveStrategy ?? false))) { saveOpen = true } else { showPlanNotice(@js($qualitySetupMode ? __('Label speichern') : __('Strategie speichern')), @js($qualitySetupMode ? __('Speichere diese Smart Selection als persönliches Label.') : __('Das Speichern persönlicher Strategien ist im Pro-Tarif verfügbar.')), @js($qualitySetupMode ? 'PLUS' : 'PRO')) }" class="contents">
                <div class="hidden">
                    <x-heroicon-o-bookmark class="h-4 w-4" />
                    {{ __('Gespeicherte Filter') }}
                    <span class="rounded-md bg-white/[.06] px-1.5 py-0.5 text-[9px] text-[var(--ak-muted)]">{{ $savedFilters->count() }} / {{ $savedFilterLimit }}</span>
                    @if ($editingSavedFilter)<span class="rounded-md border border-amber-300/20 bg-amber-300/[.08] px-2 py-1 normal-case tracking-normal text-amber-200">{{ __('Bearbeitung: :name', ['name' => $editingSavedFilter->name]) }}</span>@endif
                </div>
                <div class="hidden">
                    @forelse ($savedFilters as $savedFilter)
                        <div class="flex shrink-0 items-center overflow-hidden rounded-md border {{ (int) request('saved_filter') === (int) $savedFilter->id ? 'border-teal-300/40 bg-teal-400/[.12]' : 'border-white/[.08] bg-white/[.035]' }}">
                            <a href="{{ route('setup.filter', $savedFilter->filters ?? []) }}" class="px-2.5 py-1.5 text-[10px] font-bold text-slate-200 hover:bg-teal-400/10 hover:text-teal-300">{{ $savedFilter->name }}</a>
                            <form method="POST" action="{{ route('setup.filter.saved.destroy', $savedFilter) }}" class="border-l border-white/[.08]">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex h-7 w-7 items-center justify-center text-slate-500 hover:bg-rose-400/10 hover:text-rose-300" title="{{ __('Filter löschen') }}"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                            </form>
                        </div>
                    @empty
                        <span class="text-[10px] text-[var(--ak-muted)]">{{ __('Noch kein Filter gespeichert.') }}</span>
                    @endforelse
                </div>
                <button type="button" @click="saveOpen = true" class="hidden">
                    @if ($editingSavedFilter)<x-heroicon-o-check class="h-3.5 w-3.5" />{{ __('Änderungen speichern') }}@else<x-heroicon-o-plus class="h-3.5 w-3.5" />{{ __('Filter speichern') }}@endif
                </button>

                <template x-teleport="body">
                <div x-show="saveOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background: rgb(7, 17, 31) !important; opacity: 1 !important;" @keydown.escape.window="saveOpen = false">
                    <form method="POST" action="{{ $qualitySetupMode ? route('setup.quality.labels.store') : route('setup.filter.saved.store') }}" class="relative isolate max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-teal-300/20 p-5 shadow-2xl" style="background: rgba(21, 36, 58, 0.90) !important; background-image: none !important; backdrop-filter: none !important;" @click.outside="saveOpen = false">
                        @csrf
                        @if (request('backtest_run'))<input type="hidden" name="backtest_run" value="{{ request('backtest_run') }}">@endif
                        @if ($editingSavedFilter)<input type="hidden" name="saved_filter" value="{{ $editingSavedFilter->id }}">@endif
                        @foreach (\App\Http\Controllers\SavedPredictionFilterController::FILTER_KEYS as $filterKey)
                            @continue($filterKey === 'exit_strategy')
                            @continue($qualitySetupMode && in_array($filterKey, ['score_min', 'confidence_min', 'drawdown_max', 'profit_per_trade_min', 'volatility_max', 'predicted_return_min', 'hit_rate_min', 'minimum_trades', 'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min'], true))
                            @php
                                $filterValue = request(
                                    $filterKey,
                                    \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS[$filterKey] ?? ''
                                );
                            @endphp
                            @if (is_array($filterValue))
                                @foreach ($filterValue as $item)<input type="hidden" name="{{ $filterKey }}[]" value="{{ $item }}">@endforeach
                            @else
                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach
                        <div class="flex items-start justify-between gap-4">
                            <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-teal-400">{{ $qualitySetupMode ? __('Als Label speichern') : __('Filter speichern') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ $qualitySetupMode ? __('Label konfigurieren') : __('Exitstrategie und Filtername') }}</h2></div>
                            <button type="button" @click="saveOpen = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-slate-400 hover:text-white"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                        </div>
                        @if ($qualitySetupMode)
                            <div class="mt-4 rounded-xl border border-cyan-300/20 bg-cyan-300/[.07] px-3 py-2 text-[10px] leading-5 text-slate-300">
                                <strong class="text-cyan-200">{{ __('Dynamische Konfiguration') }}</strong><br>
                                {{ __('Gespeichert werden nur deine Filterregeln. Nach dem monatlichen Test und dem anschließenden Retest werden die enthaltenen Aktien automatisch neu bestimmt. So bleibt die Auswahl stets auf dem gewünschten Qualitätsniveau.') }}
                            </div>
                            @php
                                $labelCriteriaFields = [
                                    ['score_min', __('Modellscore mindestens'), 0, 10, .1],
                                    ['confidence_min', __('Konfidenz mindestens (%)'), 0, 100, 1],
                                    ['profit_per_trade_min', __('Profit-Faktor pro Aktie'), 0, 10, .1],
                                    ['hit_rate_min', __('Trefferquote mindestens (%)'), 0, 100, 1],
                                    ['drawdown_max', __('Drawdown höchstens (%)'), 0, 100, 1],
                                    ['volatility_max', __('Volatilität höchstens'), 0, 1000000, .1],
                                    ['minimum_trades', __('Historische Trades mindestens'), 0, 10000, 1],
                                    ['predicted_return_min', __('Prognose mindestens (%)'), .5, 10, .1],
                                    ['pe_max', __('KGV höchstens'), 0, 10000, .1],
                                    ['dividend_yield_min', __('Dividendenrendite (%)'), 0, 5, .1],
                                    ['market_cap_min', __('Marktkapitalisierung mindestens'), 0, null, 1],
                                    ['revenue_growth_min', __('Umsatzwachstum mindestens (%)'), -100, 10000, .1],
                                ];
                            @endphp
                            <fieldset class="mt-4 rounded-xl border border-white/10 bg-slate-950/20 p-3">
                                <legend class="px-1 text-[10px] font-black uppercase tracking-[.12em] text-cyan-200">{{ __('Filterregeln des Labels') }}</legend>
                                <p class="mb-3 text-[10px] leading-4 text-slate-400">{{ __('Die Werte wurden aus dem Strategietester übernommen und können vor dem Speichern angepasst werden.') }}</p>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($labelCriteriaFields as [$field, $label, $min, $max, $step])
                                        <label class="text-[9px] font-black uppercase tracking-wide text-slate-400">{{ $label }}
                                            <input name="{{ $field }}" type="number" min="{{ $min }}" @if($max !== null) max="{{ $max }}" @endif step="{{ $step }}" value="{{ request($field, \App\Http\Controllers\SavedPredictionFilterController::FILTER_DEFAULTS[$field] ?? 0) }}" class="ak-input mt-1.5 h-10 w-full rounded-lg px-3 text-xs font-bold normal-case text-white">
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <label class="mt-3 flex cursor-pointer items-start gap-2 rounded-xl border border-amber-300/20 bg-amber-300/[.06] px-3 py-2.5 text-[10px] leading-5 text-slate-300">
                                <input type="hidden" name="email_notification_enabled" value="0">
                                <input type="checkbox" name="email_notification_enabled" value="1" class="mt-1 h-4 w-4 rounded border-amber-300/40 bg-slate-900 text-amber-400 focus:ring-amber-400">
                                <span><strong class="block text-amber-200">{{ __('E-Mail bei Kaufsignal senden') }}</strong>{{ __('Soll ich dir eine E-Mail senden, sobald eine Aktie dieses Labels ein neues Kaufsignal erhält?') }}</span>
                            </label>
                        @endif
                        @if (! $qualitySetupMode)
                        <div class="mt-5">
                            <div class="flex items-end justify-between gap-3">
                                <div><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Exitstrategie') }}</p><p class="mt-1 text-[10px] text-slate-500">{{ __('Wähle genau eine Ausstiegslogik für diesen Filter.') }}</p></div>
                                <span class="rounded-md bg-teal-400/10 px-2 py-1 text-[9px] font-black text-teal-300">{{ __('Eine Auswahl') }}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach ([
                                    ['fixed_20d', __('20 Tage'), __('Verkauf nach 20 Handelstagen'), 'save-exit-fixed'],
                                    ['signal_change', __('Signal- oder Marktphasenwechsel'), __('Verkauf beim ersten Wechsel des BUY-Signals oder der MACD-/Stochastik-Marktphase'), 'save-exit-signal'],
                                    ['forecast_below_price', __('Prognose unter Kurs'), __('Hält die Position, bis die neue 20-Tage-Prognose unter dem dann aktuellen Kurs liegt'), 'save-exit-forecast-price'],
                                    ['buy_and_hold', __('Buy and Hold'), __('Gekaufte Aktien werden dauerhaft gehalten'), 'save-exit-buy-hold'],
                                ] as [$exitValue, $exitLabel, $exitDescription, $exitMetricId])
                                    <label class="cursor-pointer rounded-lg border p-2.5 transition" :class="exitStrategy === '{{ $exitValue }}' ? 'border-teal-300/35 bg-teal-400/[.10]' : 'border-white/[.08] bg-white/[.025]'">
                                        <div class="flex items-start gap-2">
                                            <input type="radio" name="exit_strategy" value="{{ $exitValue }}" x-model="exitStrategy" @checked($selectedExitStrategy === $exitValue) required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30">
                                            <span><b class="block text-[11px] text-white">{{ $exitLabel }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ $exitDescription }}</small></span>
                                        </div>
                                        <div id="{{ $exitMetricId }}" class="mt-2 border-t border-white/[.07] pt-2 text-[9px] font-bold text-slate-500">{{ __('Noch kein passendes Backtestergebnis') }}</div>
                                        <p x-show="exitStrategy === 'buy_and_hold' && '{{ $exitValue }}' === 'buy_and_hold'" class="mt-2 rounded bg-amber-300/[.08] px-2 py-1 text-[8px] font-bold text-amber-200">{{ __('Money Manager deaktiviert · Positionsfaktor 1×') }}</p>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <div class="mt-5 grid {{ $qualitySetupMode ? 'grid-cols-[1fr_140px]' : 'grid-cols-1' }} gap-3">
                            <label class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Name') }}
                                <input name="name" value="{{ $editingSavedFilter?->name }}" maxlength="80" required autofocus class="ak-input mt-2 h-11 w-full rounded-lg px-3 text-sm font-bold text-white" placeholder="{{ $qualitySetupMode ? __('z. B. Momentum Favorit') : __('z. B. Quality Europa') }}">
                            </label>
                            @if ($qualitySetupMode)
                            <label x-data="{ color: '#06b6d4' }" class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Labelfarbe') }}
                                <span class="mt-2 flex h-11 items-center gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-2">
                                    <input name="color" type="color" value="#06b6d4" x-model="color" required class="h-7 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                                    <span class="min-w-0 truncate font-mono text-[10px] normal-case tracking-normal text-slate-300" x-text="color.toUpperCase()"></span>
                                </span>
                            </label>
                            @endif
                        </div>
                        @if (! $qualitySetupMode)
                            <div x-data="{ icon: @js(data_get($editingSavedFilter?->filters, 'display_icon', 'chart-bar')), color: @js(data_get($editingSavedFilter?->filters, 'display_color', '#22d3ee')) }" class="mt-3 rounded-xl border border-white/[.08] bg-white/[.025] p-3">
                                <input type="hidden" name="display_icon" x-model="icon">
                                <input type="hidden" name="display_color" x-model="color">
                                <div class="grid gap-4 md:grid-cols-[1fr_260px]">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Symbol') }}</p>
                                        <div class="mt-2 grid grid-cols-6 gap-2">
                                            @foreach ([
                                                ['chart-bar', 'heroicon-o-chart-bar', __('Analyse')],
                                                ['bolt', 'heroicon-o-bolt', __('Momentum')],
                                                ['shield-check', 'heroicon-o-shield-check', __('Qualität')],
                                                ['arrow-path', 'heroicon-o-arrow-path', __('Rotation')],
                                                ['trophy', 'heroicon-o-trophy', __('Favorit')],
                                                ['rocket-launch', 'heroicon-o-rocket-launch', __('Chance')],
                                            ] as [$iconValue, $iconComponent, $iconLabel])
                                                <button type="button" @click="icon = @js($iconValue)" :style="icon === @js($iconValue) ? `border-color:${color}; color:${color}; background-color:${color}18` : ''" class="flex h-11 items-center justify-center rounded-lg border border-white/10 bg-slate-950/30 text-slate-400 transition hover:border-white/25 hover:text-white" title="{{ $iconLabel }}">
                                                    <x-dynamic-component :component="$iconComponent" class="h-5 w-5" />
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Farbe') }}</p>
                                        <div class="mt-2 grid grid-cols-6 gap-2">
                                            @foreach (['#22d3ee', '#2dd4bf', '#84cc16', '#fbbf24', '#fb7185', '#a78bfa'] as $colorValue)
                                                <button type="button" @click="color = @js($colorValue)" class="flex h-11 items-center justify-center rounded-lg border transition" :class="color === @js($colorValue) ? 'border-white/70 ring-2 ring-white/20' : 'border-white/10'" title="{{ $colorValue }}">
                                                    <span class="h-5 w-5 rounded-full" style="background-color:{{ $colorValue }}"></span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 grid gap-3 md:grid-cols-[1fr_290px]">
                                <label class="block text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Kommentar') }} <span class="normal-case text-slate-600">({{ __('optional') }})</span>
                                    <textarea name="description" maxlength="1000" rows="3" class="ak-input mt-2 w-full rounded-lg px-3 py-2 text-xs font-normal normal-case leading-5 text-white" placeholder="{{ __('Beschreibe kurz Ziel, Auswahlregeln und Besonderheiten der Strategie.') }}">{{ old('description', $editingSavedFilter?->description) }}</textarea>
                                </label>
                                <fieldset><legend class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Sichtbarkeit') }}</legend><div class="mt-2 grid gap-2">
                                    <label class="cursor-pointer rounded-lg border p-3 transition" :class="visibility === 'private' ? 'border-teal-300/40 bg-teal-400/[.10]' : 'border-white/10 bg-slate-950/25'"><div class="flex items-start gap-2"><input type="radio" name="visibility" value="private" x-model="visibility" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-teal-500"><span><b class="block text-xs text-white">{{ __('Privat') }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ __('Nur du kannst diese Strategie sehen und verwenden.') }}</small></span></div></label>
                                    <label class="cursor-pointer rounded-lg border p-3 transition" :class="visibility === 'pro_public' ? 'border-amber-300/45 bg-amber-300/[.10]' : 'border-white/10 bg-slate-950/25'"><div class="flex items-start gap-2"><input type="radio" name="visibility" value="pro_public" x-model="visibility" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-amber-500"><span><b class="block text-xs text-white">{{ __('Öffentlich für Pro') }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-400">{{ __('Pro-Nutzer können eine eigene Kopie importieren. Nur du bearbeitest das Original.') }}</small></span></div></label>
                                </div></fieldset>
                            </div>
                            <div class="mt-4 rounded-xl border border-cyan-300/25 bg-cyan-400/[.07] p-4">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="checkbox" name="automation_enabled" value="1" x-model="automationEnabled" class="mt-1 h-4 w-4 rounded border-cyan-300/40 bg-slate-900 text-cyan-400 focus:ring-cyan-400">
                                    <span><strong class="block text-xs text-cyan-200">{{ __('Strategie automatisch im Depot ausführen') }}</strong><small class="mt-1 block text-[10px] leading-4 text-slate-300">{{ __('Nach neuen Predictions wird geprüft, ob sich das Strategiedepot ändern muss.') }}</small></span>
                                </label>
                                <div x-show="automationEnabled" x-cloak class="mt-4 border-t border-cyan-300/15 pt-4">
                                    <div class="flex items-start gap-3">
                                        <x-heroicon-o-arrow-path-rounded-square class="mt-0.5 h-5 w-5 shrink-0 text-cyan-300" />
                                        <div><p class="text-xs font-black text-cyan-200">{{ __('Automatisches Strategiedepot') }}</p><p class="mt-1 text-[10px] leading-4 text-slate-300">{{ __('Die optimierte Strategie wird nach jeder neuen Prediction auf das gewählte Depot angewendet. Käufe und spätere Depotänderungen werden protokolliert.') }}</p></div>
                                    </div>
                                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                                        <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Depot') }}
                                            <select name="portfolio_id" :disabled="!automationEnabled" class="ak-input mt-2 h-11 w-full rounded-lg px-3 text-xs font-bold text-white">
                                                <option value="">{{ __('Neues Depot automatisch erstellen') }}</option>
                                                @foreach (($automationPortfolios ?? collect()) as $automationPortfolio)
                                                    <option value="{{ $automationPortfolio->id }}">{{ $automationPortfolio->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Verfügbares Kapital') }}
                                            <div class="relative mt-2"><input name="automation_initial_capital" type="number" min="1000" max="1000000" step="100" value="{{ request('initial_capital', 10000) }}" :disabled="!automationEnabled" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                        </label>
                                        <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Kosten je Trade') }}
                                            <div class="relative mt-2"><input name="automation_trade_cost" type="number" min="0" max="1000" step="0.01" value="{{ request('trade_cost', 10) }}" :disabled="!automationEnabled" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                        </label>
                                    </div>
                                    <label class="mt-3 flex cursor-pointer items-start gap-2 rounded-lg border border-amber-300/20 bg-amber-300/[.06] px-3 py-2.5">
                                        <input type="hidden" name="transaction_email_enabled" value="0">
                                        <input type="checkbox" name="transaction_email_enabled" value="1" checked :disabled="!automationEnabled" class="mt-0.5 h-4 w-4 rounded border-amber-300/40 bg-slate-900 text-amber-400 focus:ring-amber-400">
                                        <span class="text-[10px] leading-4 text-slate-300"><strong class="block text-amber-200">{{ __('E-Mail bei Depotänderungen') }}</strong>{{ __('Du erhältst nur dann eine Nachricht, wenn die Strategie tatsächlich einen Kauf, eine Aufstockung oder einen Verkauf im Depot ausführt.') }}</span>
                                    </label>
                                </div>
                            </div>
                        @endif
                        @if ($qualitySetupMode)
                        <div x-data="{ icon: 'sparkles' }" class="mt-3">
                            <input type="hidden" name="icon" x-model="icon">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Symbol') }}</p>
                            <div class="mt-2 grid grid-cols-6 gap-2">
                                @foreach ([
                                    ['sparkles', 'heroicon-o-sparkles', __('Favorit')],
                                    ['bolt', 'heroicon-o-bolt', __('Momentum')],
                                    ['trophy', 'heroicon-o-trophy', __('Top-Auswahl')],
                                    ['shield-check', 'heroicon-o-shield-check', __('Qualität')],
                                    ['chart-bar', 'heroicon-o-chart-bar', __('Analyse')],
                                    ['rocket-launch', 'heroicon-o-rocket-launch', __('Chance')],
                                ] as [$iconValue, $iconComponent, $iconLabel])
                                    <button type="button" @click="icon = '{{ $iconValue }}'" class="flex h-12 items-center justify-center gap-2 rounded-lg border transition" :class="icon === '{{ $iconValue }}' ? 'border-teal-300/50 bg-teal-400/15 text-teal-200' : 'border-white/10 bg-slate-950/30 text-slate-400 hover:border-white/20 hover:text-white'" title="{{ $iconLabel }}">
                                        <x-dynamic-component :component="$iconComponent" class="h-5 w-5" />
                                        <span class="hidden text-[9px] font-bold xl:inline">{{ $iconLabel }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @error('exit_strategy')<p class="mt-2 text-[10px] font-bold text-rose-300">{{ $message }}</p>@enderror
                        <p class="mt-3 text-[10px] text-slate-400">{{ $qualitySetupMode ? __('Die Kriterien werden als eigene Smart-Selection-Kategorie gespeichert. Strategien und das systemweite Quality Gate bleiben unverändert.') : __('Dein Tarif erlaubt :count gespeicherte Filter.', ['count' => $savedFilterLimit]) }}</p>
                        <div class="mt-5 flex justify-end gap-2"><button type="button" @click="saveOpen = false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</button><button type="submit" class="h-10 rounded-lg border border-teal-300/30 bg-teal-400/15 px-5 text-xs font-black text-teal-200 hover:bg-teal-400/20">{{ $qualitySetupMode ? __('Als Label speichern') : __('Speichern') }}</button></div>
                    </form>
                </div>
                </template>
            </section>
            @error('saved_filter')<p class="-mt-2 mb-2 shrink-0 text-xs font-bold text-rose-300">{{ $message }}</p>@enderror
            @php
                $backtestIsActive = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['queued', 'running'], true);
                $backtestIsComplete = isset($activeBacktestRun) && in_array($activeBacktestRun?->status, ['completed', 'completed_with_errors'], true);
                $backtestFilters = [
                    'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
                    'score_min', 'confidence_min', 'drawdown_max', 'profit_per_trade_min', 'volatility_max',
                    'pe_max', 'dividend_yield_min', 'dividend_yield_operator', 'market_cap_min', 'market_cap_group', 'revenue_growth_min', 'hit_rate_min',
                    'risk_max', 'predicted_return_min', 'minimum_trades', 'sector_score_min', 'positive_prediction_required', 'ensemble_veto_required', 'quality_gate_profile',
                    'gate_mode', 'quality_setup',
                    'sector_score_rotation', 'index_score_rotation', 'entry_strategy', 'entry_risk_style', 'automatic_strategy_comparison', 'forecast_score_rotation_5d_enabled', 'strategy_priority', 'position_factor', 'exit_strategy',
                    'fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled',
                    'resistance_trailing_stop_enabled', 'entry_wait_5d_enabled', 'signal_change_exit_enabled', 'forecast_below_price_exit_enabled',
                    'indicator_matrix_usage', 'indicator_matrix_preset', 'indicator_matrix_macd_min', 'indicator_matrix_macd_max',
                    'indicator_matrix_stoch_min', 'indicator_matrix_stoch_max', 'indicator_matrix_macd_direction',
                    'indicator_probability_min',
                ];
            @endphp
            @if (! $qualitySetupMode)
            <section x-data="{ capitalOpen: @js(request()->boolean('new_backtest')), wizardOpen: false, optimizeOpen: false, capital: 10000, positions: Number({{ max(1, min(50, (int) request('max_positions', 5))) }}), positionFactor: Number({{ request('exit_strategy') === 'buy_and_hold' ? 1 : max(1, (int) request('position_factor', 1)) }}), tradeCost: 10, moneyManagerEnabled: @js(request('exit_strategy') !== 'buy_and_hold') }" @close-capital-modal.window="capitalOpen = false" class="ak-backtest-strip relative mb-3 flex shrink-0 items-center justify-between gap-3 overflow-hidden rounded-xl border border-amber-300/20 bg-amber-300/[.055] px-3 py-2 {{ $backtestIsActive ? 'pb-4' : '' }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        @if ($backtestIsActive)
                            <span class="ak-backtest-spinner" aria-hidden="true"></span>
                        @endif
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-amber-300">{{ __('Persönlicher 3-Jahres-Backtest') }}</p>
                        @if ($backtestIsActive)
                            <span class="ak-backtest-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        @endif
                    </div>
                    <p class="ak-backtest-description truncate text-[10px] text-[var(--ak-muted)]">
                        @if ($backtestIsActive)
                            <span id="ak-backtest-status-text">{{ __('Der Backtest wird im Hintergrund berechnet …') }}</span>
                            <span id="ak-backtest-status-count" class="ml-1 font-bold text-amber-100"></span>
                        @elseif ($backtestIsComplete)
                            {{ __('Abgeschlossen: :trades Trades aus :instruments Aktien', ['trades' => number_format((int) $activeBacktestRun->trades_count, 0, ',', '.'), 'instruments' => number_format((int) $activeBacktestRun->instruments_completed, 0, ',', '.')]) }}
                        @elseif (isset($activeBacktestRun) && $activeBacktestRun?->status === 'failed')
                            {{ __('Der Backtest konnte nicht abgeschlossen werden.') }}
                        @elseif (isset($activeBacktestRun) && $activeBacktestRun?->status === 'cancelled')
                            {{ __('Der Backtest wurde abgebrochen.') }}
                        @else
                            {{ __('Nur Aktien, die alle gewählten Kriterien erfüllen, werden berücksichtigt. Ausstieg nach 20 Handelstagen.') }}
                        @endif
                    </p>
                </div>
                @if ($backtestIsActive)
                    <form method="POST" action="{{ route('setup.filter.backtest.cancel', $activeBacktestRun->public_id) }}" x-data="{ cancelling: false }" @submit="cancelling = true" class="shrink-0">
                        @csrf
                        @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
                        @foreach ($backtestFilters as $filter)
                            @if (request()->filled($filter) || $filter === 'position_factor')
                                @if (is_array(request($filter)))
                                    @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                @else
                                    <input type="hidden" name="{{ $filter }}" value="{{ request($filter, 1) }}" @if ($filter === 'position_factor') x-model.number="positionFactor" @endif>
                                @endif
                            @endif
                        @endforeach
                        <button type="submit" :disabled="cancelling" class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-300/25 bg-rose-400/[.07] px-4 text-[10px] font-black uppercase tracking-[.08em] text-rose-200 transition hover:bg-rose-400/15 disabled:cursor-wait disabled:opacity-60">
                            <span x-show="cancelling" class="ak-backtest-spinner h-4 w-4" aria-hidden="true"></span>
                            <x-heroicon-o-x-mark x-show="!cancelling" class="h-4 w-4" />
                            <span x-text="cancelling ? @js(__('Wird abgebrochen …')) : @js(__('Abbrechen'))"></span>
                        </button>
                    </form>
                @elseif ($backtestIsComplete)
                    <div class="ak-backtest-actions flex shrink-0 items-center gap-2">
                        @php $canSaveCurrentSelection = $qualitySetupMode ? ($canSaveSmartLabel ?? false) : ($canSaveStrategy ?? false); @endphp
                        <button type="button" @if($canSaveCurrentSelection) @click="$dispatch('open-save-filter')" @else @click="showPlanNotice(@js($qualitySetupMode ? __('Label speichern') : __('Strategie speichern')), @js($qualitySetupMode ? __('Speichere diese Smart Selection als persönliches Label und verwende sie in deinen Auswertungen.') : __('Speichere alle Filter, Rotationen, Positionsregeln und die Exitstrategie für die spätere Wiederverwendung.')), @js($qualitySetupMode ? 'PLUS' : 'PRO'))" @endif class="ak-backtest-save-action inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-[10px] font-black uppercase tracking-[.06em] transition {{ !$canSaveCurrentSelection ? 'border-amber-300/25 bg-amber-300/[.07] text-amber-200 hover:bg-amber-300/[.14]' : 'border-teal-300/25 bg-teal-400/[.09] text-teal-200 hover:bg-teal-400/[.16]' }}">
                            <x-heroicon-o-bookmark class="h-4 w-4" />{{ $qualitySetupMode ? __('Als Label speichern') : ($editingSavedFilter ? __('Änderungen speichern') : __('Strategie speichern')) }}
                        </button>
                        @if (app(\App\Services\PlanAccessService::class)->allows(auth()->user(), \App\Enums\PlanLevel::Premium))
                            <a href="{{ route('setup.quality', array_merge(request()->query(), ['quality_setup' => 1, 'open_label_modal' => 1])) }}" class="ak-backtest-smart-action inline-flex h-9 items-center gap-2 rounded-lg border border-amber-300/25 bg-amber-300/[.09] px-3 text-[10px] font-black uppercase tracking-[.06em] text-amber-200 transition hover:bg-amber-300/[.16]">
                                <x-heroicon-o-shield-check class="h-4 w-4" />{{ __('Als Label speichern') }}
                            </a>
                        @elseif (app(\App\Services\PlanAccessService::class)->allows(auth()->user(), \App\Enums\PlanLevel::Pro))
                            <button type="button" disabled title="{{ __('Verfügbar ab Premium') }}" class="ak-backtest-smart-disabled-action inline-flex h-9 cursor-not-allowed items-center gap-2 rounded-lg border border-slate-500/15 bg-slate-500/[.05] px-3 text-[10px] font-black uppercase tracking-[.06em] text-slate-500 opacity-70">
                                <x-heroicon-o-lock-closed class="h-4 w-4" />{{ __('Smart Selection') }}
                                <span class="rounded bg-slate-400/10 px-1.5 py-0.5 text-[8px]">{{ __('Premium') }}</span>
                            </button>
                        @endif
                        <a href="{{ route($heatmapFilterRoute, array_merge(request()->except('backtest_run'), ['new_backtest' => 1])) }}" data-open-backtest-capital class="ak-backtest-recalculate-action inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 px-3 text-[10px] font-black uppercase tracking-[.06em] text-slate-300 hover:text-white">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Neu berechnen') }}
                        </a>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-backtest-result'))" class="ak-backtest-primary-action inline-flex h-9 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/12 px-4 text-[10px] font-black uppercase tracking-[.08em] text-amber-200 transition hover:bg-amber-300/20">
                            <x-heroicon-o-chart-bar class="h-4 w-4" />{{ __('Ergebnis anzeigen') }}
                        </button>
                    </div>
                @else
                    <div class="ak-backtest-actions flex shrink-0 items-center gap-2">
                        <button type="button" @click="wizardOpen = true" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-lg border border-cyan-300/30 bg-cyan-400/10 px-4 text-[10px] font-black uppercase tracking-[.08em] text-cyan-200 transition hover:bg-cyan-400/20">
                            <x-heroicon-o-map class="h-4 w-4" />{{ __('Strategie-Assistent') }}
                        </button>
                        <a href="{{ route($heatmapFilterRoute, array_merge(request()->except('backtest_run'), ['new_backtest' => 1])) }}" data-open-backtest-capital class="ak-backtest-primary-action inline-flex h-9 shrink-0 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/12 px-4 text-[10px] font-black uppercase tracking-[.08em] text-amber-200 transition hover:bg-amber-300/20">
                            <x-heroicon-o-play class="h-4 w-4" />
                            {{ __('Manuellen Backtest starten') }}
                        </a>
                    </div>
                @endif
                    <div x-show="wizardOpen" x-cloak class="fixed inset-0 z-[10010] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-md" @keydown.escape.window="wizardOpen = false">
                        <form method="POST" action="{{ route('setup.filter.backtest') }}" x-data="{ step: 1, submitting: false, score: 0, confidence: 0, trades: 1, profit: 0, drawdown: 100, expectedReturn: .5, allocation: Number({{ max(1, min(5, (int) request('position_factor', 1))) }}), scoreGrade(value) { const grades = ['5−', '5+', '4−', '4+', '3−', '3+', '2−', '2+', '1−', '1+']; return grades[Math.max(0, Math.min(9, Math.floor(Number(value) || 0)))]; } }" @submit="submitting = true" class="w-full max-w-2xl rounded-2xl border border-cyan-300/25 bg-[#15243a] p-5 shadow-2xl" @click.outside="wizardOpen = false">
                            @csrf
                            @foreach ($backtestFilters as $filter)
                                @continue(! in_array($filter, ['q','country','exchange','sector','ai_type','model','quality_tier','signal','gate_mode'], true))
                                @if (request()->filled($filter))
                                    @if (is_array(request($filter)))
                                        @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                    @else
                                        <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">
                                    @endif
                                @endif
                            @endforeach
                            <input type="hidden" name="initial_capital" value="10000"><input type="hidden" name="max_positions" value="5"><input type="hidden" name="trade_cost" value="10">
                            <input type="hidden" name="score_min" :value="score"><input type="hidden" name="confidence_min" :value="confidence"><input type="hidden" name="minimum_trades" :value="trades"><input type="hidden" name="profit_per_trade_min" :value="profit"><input type="hidden" name="drawdown_max" :value="drawdown"><input type="hidden" name="predicted_return_min" :value="expectedReturn"><input type="hidden" name="position_factor" :value="allocation">
                            <div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-cyan-300">{{ __('Strategie-Assistent') }} · <span x-text="step"></span>/4</p><h2 class="mt-1 text-xl font-black text-white">{{ __('Vom Filter zur getesteten Strategie') }}</h2></div><button type="button" @click="wizardOpen=false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-slate-300"><x-heroicon-o-x-mark class="h-5 w-5" /></button></div>
                            <div class="mt-4 grid grid-cols-4 gap-1">@foreach ([1,2,3,4] as $wizardStep)<span class="h-1.5 rounded-full" :class="step >= {{ $wizardStep }} ? 'bg-cyan-400' : 'bg-white/10'"></span>@endforeach</div>
                            <div x-show="step===1" class="mt-5"><h3 class="text-base font-black text-white">{{ __('1. Ausgangsportfolio prüfen') }}</h3><p class="mt-2 text-xs leading-5 text-slate-300">{{ __('Der Assistent übernimmt nur Länder-, Börsen-, Sektor-, Modell- und Signalauswahl. Alte Performance-, Risiko- und Fundamentallimits werden bewusst zurückgesetzt und in den nächsten Schritten transparent neu gewählt.') }}</p><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl border border-cyan-300/20 bg-cyan-400/[.07] p-4"><strong class="text-2xl font-black text-cyan-200">{{ number_format((int) ($heatmapUniverseInstruments ?? 0), 0, ',', '.') }}</strong><small class="block text-[9px] uppercase text-slate-400">{{ __('Aktien im verfügbaren Testportfolio') }}</small></div><div class="rounded-xl border border-white/10 p-4"><strong class="text-2xl font-black text-white">{{ number_format((int) ($heatmapSummary->instruments ?? 0), 0, ',', '.') }}</strong><small class="block text-[9px] uppercase text-slate-400">{{ __('aktuell eng gefiltert') }}</small></div></div><p class="mt-3 rounded-lg border border-emerald-300/20 bg-emerald-400/[.06] p-3 text-[10px] text-emerald-100">{{ __('Empfehlung: Beginne breit und verschärfe nur einen Grenzwert pro Test. Ziel sind zunächst mindestens 25 Aktien.') }}</p></div>
                            <div x-show="step===2" x-cloak class="mt-5"><h3 class="text-base font-black text-white">{{ __('2. KI-Qualität und Evidenz') }}</h3><div class="mt-4 grid grid-cols-2 gap-4"><label class="text-[10px] font-black uppercase text-slate-400">{{ __('KI-Score mindestens') }} <b class="text-cyan-200" x-text="scoreGrade(score)"></b><input type="range" min="0" max="9" step="1" x-model.number="score" class="mt-3 w-full"><small class="mt-1 block normal-case text-slate-500">{{ __('1+ ist die beste Stufe') }}</small></label><label class="text-[10px] font-black uppercase text-slate-400">{{ __('Konfidenz mindestens') }} <b class="text-cyan-200" x-text="confidence+' %'"></b><input type="range" min="0" max="100" step="5" x-model.number="confidence" class="mt-3 w-full"></label><label class="text-[10px] font-black uppercase text-slate-400">{{ __('Trades mindestens') }}<input type="number" min="0" max="10000" x-model.number="trades" class="ak-input mt-2 h-10 w-full"></label><label class="text-[10px] font-black uppercase text-slate-400">{{ __('Profit je Trade mindestens') }}<input type="number" min="0" max="10" step="0.1" x-model.number="profit" class="ak-input mt-2 h-10 w-full"></label></div></div>
                            <div x-show="step===3" x-cloak class="mt-5"><h3 class="text-base font-black text-white">{{ __('3. Risiko und Chance') }}</h3><div class="mt-4 grid grid-cols-2 gap-4"><label class="text-[10px] font-black uppercase text-slate-400">{{ __('Drawdown maximal') }} <b class="text-rose-300" x-text="drawdown+' %'"></b><input type="range" min="0" max="100" step="5" x-model.number="drawdown" class="mt-3 w-full"></label><label class="text-[10px] font-black uppercase text-slate-400">{{ __('Erwartete Rendite mindestens') }} <b class="text-emerald-300" x-text="expectedReturn.toFixed(1)+' %'"></b><input type="range" min="0.5" max="10" step="0.5" x-model.number="expectedReturn" class="mt-3 w-full"></label></div><p class="mt-4 rounded-lg border border-amber-300/20 bg-amber-300/[.06] p-3 text-[10px] leading-4 text-slate-300">{{ __('Nutze den Korrelationscheck oberhalb der Heatmaps als Hinweis, ob höhere KI-Scores in dieser Auswahl tatsächlich mit besseren Profitfaktoren einhergehen.') }}</p></div>
                            <div x-show="step===4" x-cloak class="mt-5"><h3 class="text-base font-black text-white">{{ __('4. Allocation und Teststart') }}</h3><p class="mt-2 text-xs text-slate-300">{{ __('Maximaler Anteil einer Aktie relativ zur Grundposition.') }}</p><div class="mt-4 grid grid-cols-5 gap-2">@for($factor=1;$factor<=5;$factor++)<label><input type="radio" value="{{ $factor }}" x-model.number="allocation" class="peer sr-only"><span class="flex h-12 cursor-pointer items-center justify-center rounded-lg border border-white/10 text-sm font-black text-slate-400 peer-checked:border-amber-300/50 peer-checked:bg-amber-300/15 peer-checked:text-amber-200">{{ $factor }}×</span></label>@endfor</div><div class="mt-4 rounded-xl border border-white/10 bg-white/[.03] p-3 text-[10px] text-slate-300"><b class="text-white">{{ __('Zusammenfassung') }}:</b> KI ≥ <span x-text="scoreGrade(score)"></span> · {{ __('Konfidenz') }} ≥ <span x-text="confidence"></span>% · {{ __('Trades') }} ≥ <span x-text="trades"></span> · {{ __('Drawdown') }} ≤ <span x-text="drawdown"></span>% · {{ __('Rendite') }} ≥ <span x-text="expectedReturn"></span>% · {{ __('Allocation') }} <span x-text="allocation"></span>×</div></div>
                            <div class="mt-6 flex justify-between border-t border-white/10 pt-4"><button type="button" @click="step=Math.max(1,step-1)" :disabled="step===1" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300 disabled:opacity-30">{{ __('Zurück') }}</button><button x-show="step<4" type="button" @click="step++" class="h-10 rounded-lg bg-cyan-500 px-5 text-xs font-black text-slate-950">{{ __('Weiter') }}</button><button x-show="step===4" type="submit" :disabled="submitting" class="h-10 rounded-lg border border-amber-300/30 bg-amber-300/15 px-5 text-xs font-black text-amber-200 disabled:opacity-50"><span x-text="submitting ? @js(__('Wird gestartet …')) : @js(__('Strategie testen'))"></span></button></div>
                        </form>
                    </div>
                    <div x-show="optimizeOpen" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @keydown.escape.window="optimizeOpen = false">
                        <form method="POST" action="{{ route('setup.filter.optimize') }}" x-data="{ tradeCapacity: 10, optimizationGoal: 'reduce_drawdown', capital: 10000, tradeCost: 10, submitting: false }" @submit="submitting = true" class="max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl border border-cyan-300/25 bg-[#15243a]/95 p-5 shadow-2xl" @click.outside="optimizeOpen = false">
                            @csrf
                            @foreach ($backtestFilters as $filter)
                                @if (request()->filled($filter))
                                    @if (is_array(request($filter)))
                                        @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                    @else
                                        <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">
                                    @endif
                                @endif
                            @endforeach
                            <div class="flex items-start justify-between gap-4">
                                <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Automatische Optimierung') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ __('Was soll verbessert werden?') }}</h2><p class="mt-1 text-xs leading-5 text-slate-300">{{ __('Zuerst werden Modelle mit negativer Netto-Performance ausgeschlossen. Danach kombiniert die Automatik die aktuellen dreijährigen Walk-Forward-Ergebnisse für 5, 10, 15 und 20 Tage. Gleichmäßig gute Ergebnisse über mehrere Horizonte erhalten einen Konsistenzbonus.') }}</p></div>
                                <button type="button" @click="optimizeOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            @php
                                $optimizationRiskProfile = app(\App\Services\PersonalizedSignalService::class)
                                    ->profileLabel(auth()->user());
                            @endphp
                            <div class="mt-4 flex items-start gap-3 rounded-xl border border-amber-300/25 bg-amber-300/[.07] p-3">
                                <x-heroicon-o-user-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
                                <p class="text-xs leading-5 text-slate-200">{{ __('Die Optimierung richtet sich nach deinem Risikoprofil „:profile“. Dadurch werden die vier Zeithorizonte sowie Risiko, Drawdown und Performance passend zu deinem Profil gewichtet.', ['profile' => $optimizationRiskProfile]) }}</p>
                            </div>
                            <fieldset class="mt-5">
                                <legend class="text-xs font-black text-white">{{ __('Wie viele Positionen möchtest du gleichzeitig verwalten?') }}</legend>
                                <p class="mt-1 text-[10px] leading-4 text-slate-400">{{ __('Die Automatik bevorzugt eine entsprechend überschaubare Aktienauswahl und übernimmt den Wert als Positionslimit für den Backtest.') }}</p>
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    @foreach ([5 => __('Wenige'), 10 => __('Mittel'), 20 => __('Viele') ] as $capacity => $label)
                                        <label class="cursor-pointer rounded-xl border p-3 text-center transition" :class="tradeCapacity === {{ $capacity }} ? 'border-cyan-300/60 bg-cyan-400/[.14] ring-1 ring-cyan-300/25' : 'border-white/10 bg-white/[.035] hover:border-white/25'">
                                            <input type="radio" name="trade_capacity" value="{{ $capacity }}" x-model.number="tradeCapacity" required class="sr-only">
                                            <b class="block text-sm text-white">{{ $capacity }}</b>
                                            <small class="mt-0.5 block text-[10px] text-slate-400">{{ $label }}</small>
                                            <span x-show="tradeCapacity === {{ $capacity }}" class="mt-1 inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wide text-cyan-300"><x-heroicon-o-check-circle class="h-3.5 w-3.5" />{{ __('Ausgewählt') }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <fieldset class="mt-5">
                                <legend class="text-xs font-black text-white">{{ __('Kapital und Handelskosten') }}</legend>
                                <p class="mt-1 text-[10px] leading-4 text-slate-400">{{ __('Die Automatik berücksichtigt die Kosten für Kauf und Verkauf bereits in der historischen Netto-Rendite.') }}</p>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Verfügbares Kapital') }}<div class="relative mt-2"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" x-model.number="capital" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div></label>
                                    <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Kosten je Trade') }}<div class="relative mt-2"><input name="trade_cost" type="number" min="0" max="1000" step="0.01" x-model.number="tradeCost" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div></label>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-400">{{ __('Kapital je Position') }}: <b class="text-cyan-200" x-text="`${Math.max(0, capital / Math.max(1, tradeCapacity)).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}`"></b></p>
                            </fieldset>
                            <div class="mt-5 grid gap-2">
                                @foreach ([
                                    ['reduce_drawdown', 'heroicon-o-shield-check', __('Drawdown verringern'), __('Bevorzugt stabilere Aktien und begrenzt historische Verlustphasen.')],
                                    ['fewer_trades', 'heroicon-o-funnel', __('Anzahl Trades reduzieren'), __('Sucht eine selektivere Strategie mit mindestens fünf Aktien.')],
                                    ['maximize_performance', 'heroicon-o-arrow-trending-up', __('Performance optimieren'), __('Gewichtet Rendite, Profitfaktor und Trefferquote bei begrenztem Drawdown.')],
                                ] as [$goal, $icon, $title, $description])
                                    <label class="group cursor-pointer rounded-xl border p-3 transition" :class="optimizationGoal === @js($goal) ? 'border-cyan-300/60 bg-cyan-400/[.10]' : 'border-white/10 bg-white/[.035] hover:border-white/20'">
                                        <span class="flex items-start gap-3"><input type="radio" name="optimization_goal" value="{{ $goal }}" x-model="optimizationGoal" required class="mt-1 h-4 w-4 border-slate-500 bg-slate-900 text-cyan-500 focus:ring-cyan-500/30"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-cyan-400/10 text-cyan-300"><x-dynamic-component :component="$icon" class="h-5 w-5" /></span><span><b class="block text-sm text-white">{{ $title }}</b><small class="mt-1 block text-[10px] leading-4 text-slate-400">{{ $description }}</small></span></span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="sticky -bottom-5 -mx-5 mt-5 flex justify-end gap-2 border-t border-white/10 bg-[#15243a] px-5 py-4"><button type="button" @click="optimizeOpen = false" :disabled="submitting" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300 disabled:opacity-50">{{ __('Abbrechen') }}</button><button type="submit" :disabled="submitting" class="inline-flex h-10 items-center gap-2 rounded-lg border border-cyan-300/30 bg-cyan-400/15 px-5 text-xs font-black text-cyan-100 hover:bg-cyan-400/20 disabled:cursor-wait disabled:opacity-60"><span x-show="submitting" class="ak-backtest-spinner h-4 w-4" aria-hidden="true"></span><x-heroicon-o-sparkles x-show="!submitting" class="h-4 w-4" /><span x-text="submitting ? @js(__('Wird optimiert …')) : @js(__('Optimierung berechnen'))"></span></button></div>
                        </form>
                    </div>
                @if (! $backtestIsActive)
                    @php
                        $modalEntryStrategy = (string) request('entry_strategy', request()->boolean('forecast_score_rotation_5d_enabled') ? 'forecast_score_rotation_5d' : (request()->boolean('entry_wait_5d_enabled') ? 'wait_5d' : 'direct_buy'));
                        $modalExitStrategy = (string) request('exit_strategy', 'fixed_20d');
                    @endphp
                    <div data-backtest-capital-modal x-show="capitalOpen" @unless(request()->boolean('new_backtest')) x-cloak @endunless class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-md" @keydown.escape.window="$dispatch('close-capital-modal')">
                        <form method="POST" action="{{ route('setup.filter.backtest') }}" x-data="{ submitting: false, entryStrategy: @js($modalEntryStrategy), exitStrategy: @js($modalExitStrategy), automaticComparison: @js(request()->boolean('automatic_strategy_comparison')), matrixUsage: @js(request('indicator_matrix_usage', 'off')), matrixPreset: @js(request('indicator_matrix_preset', 'manual')) }" @submit="submitting = true" class="ak-backtest-config-dialog max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl border border-teal-300/20 bg-[#15243a] p-5 shadow-2xl" style="background-color:#15243a !important; opacity:1 !important;" @click.outside="$dispatch('close-capital-modal')">
                            @csrf
                            @if ($errors->any())
                                <div class="mb-4 rounded-xl border border-rose-300/25 bg-rose-400/[.08] px-4 py-3 text-xs font-bold text-rose-200">{{ $errors->first() }}</div>
                            @endif
                            @if ($qualitySetupMode)<input type="hidden" name="quality_setup" value="1">@endif
                            @foreach ($backtestFilters as $filter)
                                @continue($filter === 'position_factor')
                                @if (request()->filled($filter))
                                    @if (is_array(request($filter)))
                                        @foreach (request($filter) as $item)<input type="hidden" name="{{ $filter }}[]" value="{{ $item }}">@endforeach
                                    @else
                                        <input type="hidden" name="{{ $filter }}" value="{{ request($filter, 1) }}" @if ($filter === 'position_factor') x-model.number="positionFactor" @endif>
                                    @endif
                                @endif
                            @endforeach
                            <div class="mb-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Backtest starten') }}</p>
                                    <h2 class="mt-1 text-xl font-black text-white">{{ $qualitySetupMode ? __('Testbetrag') : __('Allocation je Aktie') }}</h2>
                                    <p class="mt-1 text-xs text-slate-300">{{ $qualitySetupMode ? __('Lege den Betrag für den Vergleich deiner Smart Selection mit dem S&P 500 fest.') : __('Alle gesetzten Strategietester-Filter bleiben unverändert. Passe hier nur die maximale Allocation je Aktie an.') }}</p>
                                    @if (! $qualitySetupMode)
                                        <div class="mt-3 inline-flex items-center gap-2 rounded-lg border border-teal-300/25 bg-teal-400/[.09] px-3 py-2 text-xs font-bold text-teal-100">
                                            <x-heroicon-o-funnel class="h-4 w-4 text-teal-300" />
                                            <strong class="text-base font-black tabular-nums">{{ number_format((int) ($heatmapSummary->instruments ?? 0), 0, ',', '.') }}</strong>
                                            <span>{{ __('Aktien erfüllen die gewählten Kriterien') }}</span>
                                        </div>
                                    @endif
                                </div>
                                <a href="{{ route($heatmapFilterRoute, request()->except(['new_backtest', 'backtest_run'])) }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white" aria-label="{{ __('Schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></a>
                            </div>
                            @if ($qualitySetupMode)
                                <input type="hidden" name="max_positions" value="5">
                                <input type="hidden" name="trade_cost" value="10">
                                <input type="hidden" name="position_factor" value="1">
                                <input type="hidden" name="initial_capital" value="10000">
                            @else
                                <input type="hidden" name="initial_capital" value="10000">
                                <input type="hidden" name="max_positions" value="5">
                                <input type="hidden" name="trade_cost" value="10">
                            @endif
                            <fieldset disabled class="hidden" aria-hidden="true">
                            @if (! $qualitySetupMode)
                            <div class="mb-4 grid gap-4 md:grid-cols-2">
                                <fieldset class="rounded-xl border border-cyan-300/20 bg-cyan-400/[.055] p-3 transition" :class="automaticComparison ? 'pointer-events-none opacity-35 grayscale' : ''">
                                    <legend class="px-1 text-[10px] font-black uppercase tracking-wide text-cyan-200">{{ __('Bevorzugte Entrystrategie') }}</legend>
                                    <div class="mt-1 grid gap-1.5">
                                        @foreach ([
                                            ['direct_buy', __('Direktes BUY'), __('Kauf beim gültigen BUY-Signal.')],
                                            ['wait_5d', __('WAIT bis 5 Tage'), __('Wartet höchstens fünf Handelstage auf BUY.')],
                                            ['forecast_score_rotation_5d', __('Forecast-Score 5T'), __('Prüft neue Einstiege alle fünf Handelstage.')],
                                        ] as [$value, $label, $description])
                                            <label class="cursor-pointer rounded-lg border px-2.5 py-2 transition" :class="entryStrategy === @js($value) ? 'border-cyan-300/45 bg-cyan-400/[.10]' : 'border-white/[.08] bg-white/[.025]'">
                                                <span class="flex items-start gap-2"><input type="radio" name="entry_strategy" value="{{ $value }}" x-model="entryStrategy" :disabled="automaticComparison" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-cyan-500"><span><b class="block text-[10px] text-white">{{ $label }}</b><small class="block text-[8px] leading-3 text-slate-400">{{ $description }}</small></span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 border-t border-cyan-300/15 pt-3">
                                        <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-cyan-200">{{ __('Zusätzliche Bereichspriorität') }}</p>
                                        @foreach ([
                                            ['sector_score_rotation', __('Sektorrotation')],
                                            ['index_score_rotation', __('Indexrotation')],
                                        ] as [$key, $label])
                                            <label class="mt-1.5 flex cursor-pointer items-start gap-2 rounded-lg border border-white/[.08] bg-white/[.025] px-2.5 py-2">
                                                <input type="checkbox" name="{{ $key }}" value="1" :disabled="automaticComparison" @checked(request()->boolean($key)) class="mt-0.5 h-4 w-4 rounded border-slate-500 bg-slate-900 text-cyan-500">
                                                <span><b class="block text-[10px] text-white">{{ $label }}</b><small class="block text-[8px] leading-3 text-slate-400">{{ __('Bevorzugt passende Aktien, solange die 20T-Prognose höchstens 2 Prozentpunkte unter der besten Aktie liegt.') }}</small></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <fieldset class="rounded-xl border border-amber-300/20 bg-amber-300/[.055] p-3 transition" :class="automaticComparison ? 'pointer-events-none opacity-35 grayscale' : ''">
                                    <legend class="px-1 text-[10px] font-black uppercase tracking-wide text-amber-200">{{ __('Bevorzugte Exitstrategie') }}</legend>
                                    <div class="mt-1 grid gap-1.5">
                                        @foreach ([
                                            ['fixed_20d', __('20 Tage'), __('Standard: Verkauf nach 20 Handelstagen.')],
                                            ['signal_change', __('Signal- oder Marktphasenwechsel'), __('Verkauft beim ersten Wechsel des BUY-Signals oder der MACD-/Stochastik-Marktphase.')],
                                            ['forecast_below_price', __('Prognose unter Kurs'), __('Hält die Position, bis die neue 20-Tage-Prognose unter dem dann aktuellen Kurs liegt.')],
                                            ['buy_and_hold', __('Buy and Hold'), __('Hält Aktien ohne zeitbasierten Exit.')],
                                        ] as [$value, $label, $description])
                                            <label class="cursor-pointer rounded-lg border px-2.5 py-2 transition" :class="exitStrategy === @js($value) ? 'border-amber-300/45 bg-amber-300/[.10]' : 'border-white/[.08] bg-white/[.025]'">
                                                <span class="flex items-start gap-2"><input type="radio" name="exit_strategy" value="{{ $value }}" x-model="exitStrategy" :disabled="automaticComparison" required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-amber-500"><span><b class="block text-[10px] text-white">{{ $label }}</b><small class="block text-[8px] leading-3 text-slate-400">{{ $description }}</small></span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>
                            <fieldset class="mb-4 rounded-xl border border-violet-300/20 bg-violet-400/[.055] p-3">
                                <legend class="px-1 text-[10px] font-black uppercase tracking-wide text-violet-200">{{ __('Auswahlprofil') }}</legend>
                                <div class="mt-1 grid gap-2 sm:grid-cols-3">
                                    @foreach ([
                                        ['conservative', __('Konservativ'), __('Drawdown niedrig · Hit-Rate hoch · Profitfaktor hoch')],
                                        ['balanced', __('Ausgewogen'), __('Hit-Rate hoch · Profitfaktor hoch · Drawdown niedrig')],
                                        ['chance', __('Chance'), __('Profitfaktor hoch · Hit-Rate hoch · Drawdown niedrig')],
                                    ] as [$value, $label, $description])
                                        <label class="cursor-pointer rounded-lg border border-white/[.08] bg-white/[.025] px-2.5 py-2 has-[:checked]:border-violet-300/50 has-[:checked]:bg-violet-300/10">
                                            <span class="flex items-start gap-2"><input type="radio" name="entry_risk_style" value="{{ $value }}" @checked(request('entry_risk_style', 'balanced') === $value) required class="mt-0.5 h-4 w-4 border-slate-500 bg-slate-900 text-violet-500"><span><b class="block text-[10px] text-white">{{ $label }}</b><small class="block text-[8px] leading-3 text-slate-400">{{ $description }}</small></span></span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-[8px] leading-3 text-slate-400">{{ __('Die Pfeile zeigen die Sortierung: niedriger Drawdown sowie höhere Hit-Rate und höherer Profitfaktor werden bevorzugt.') }}</p>
                            </fieldset>
                            <label class="mb-4 flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-300/25 bg-emerald-400/[.07] px-4 py-3">
                                <input type="checkbox" name="automatic_strategy_comparison" value="1" x-model="automaticComparison" @checked(request()->boolean('automatic_strategy_comparison')) class="mt-0.5 h-4 w-4 rounded border-slate-500 bg-slate-900 text-emerald-500">
                                <span><b class="block text-[11px] font-black uppercase tracking-wide text-emerald-200">{{ __('Automatik') }}</b><small class="mt-1 block text-[9px] leading-4 text-slate-300">{{ __('Berechnet alle verfügbaren Entry- und Exitstrategien und stellt sie im Bericht gegenüber. Das fest gewählte Auswahlprofil bleibt für alle Varianten unverändert.') }}</small></span>
                            </label>
                            <fieldset class="mb-4 rounded-xl border border-fuchsia-300/20 bg-fuchsia-400/[.055] p-3 transition" :class="automaticComparison ? 'pointer-events-none opacity-35 grayscale' : ''">
                                <legend class="px-1 text-[10px] font-black uppercase tracking-wide text-fuchsia-200">{{ __('Indikatormatrix · MACD × Stochastik') }}</legend>
                                <p class="mb-3 text-[9px] leading-4 text-slate-400">{{ __('Prüft die Kombination am historischen Handelstag. MACD wird kursnormalisiert; steigende/fallende Werte vergleichen immer mit dem vorherigen Handelstag.') }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="text-[9px] font-black uppercase tracking-wide text-slate-400">{{ __('Verwendung') }}
                                        <select name="indicator_matrix_usage" x-model="matrixUsage" :disabled="automaticComparison" class="ak-input mt-1 h-10 w-full rounded-lg px-3 text-xs font-bold text-white">
                                            <option value="off">{{ __('Deaktiviert') }}</option>
                                            <option value="entry">{{ __('Entry-Filter') }}</option>
                                            <option value="exit">{{ __('Exit-Signal') }}</option>
                                        </select>
                                    </label>
                                    <label class="text-[9px] font-black uppercase tracking-wide text-slate-400">{{ __('Marktphase / Preset') }}
                                        <select name="indicator_matrix_preset" x-model="matrixPreset" :disabled="automaticComparison || matrixUsage === 'off'" class="ak-input mt-1 h-10 w-full rounded-lg px-3 text-xs font-bold text-white disabled:opacity-40">
                                            <option value="manual">{{ __('Manuelle Grenzwerte') }}</option>
                                            <option value="oversold_recovery">{{ __('Überverkauft · Erholung') }}</option>
                                            <option value="early_recovery">{{ __('Frühe Erholung') }}</option>
                                            <option value="bullish_impulse">{{ __('Bullischer Impuls') }}</option>
                                            <option value="overheated_fading">{{ __('Überkauft · nachlassend') }}</option>
                                            <option value="bearish_impulse">{{ __('Bärischer Impuls') }}</option>
                                        </select>
                                    </label>
                                </div>
                                <div x-show="matrixUsage !== 'off' && matrixPreset === 'manual'" x-cloak class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <label class="text-[8px] font-black uppercase text-slate-400">MACD min. (%)<input name="indicator_matrix_macd_min" type="number" step="0.01" min="-100" max="100" value="{{ request('indicator_matrix_macd_min', -1) }}" class="ak-input mt-1 h-9 w-full rounded-lg px-2 text-xs text-white"></label>
                                    <label class="text-[8px] font-black uppercase text-slate-400">MACD max. (%)<input name="indicator_matrix_macd_max" type="number" step="0.01" min="-100" max="100" value="{{ request('indicator_matrix_macd_max', 1) }}" class="ak-input mt-1 h-9 w-full rounded-lg px-2 text-xs text-white"></label>
                                    <label class="text-[8px] font-black uppercase text-slate-400">Stoch. min.<input name="indicator_matrix_stoch_min" type="number" step="1" min="0" max="100" value="{{ request('indicator_matrix_stoch_min', 0) }}" class="ak-input mt-1 h-9 w-full rounded-lg px-2 text-xs text-white"></label>
                                    <label class="text-[8px] font-black uppercase text-slate-400">Stoch. max.<input name="indicator_matrix_stoch_max" type="number" step="1" min="0" max="100" value="{{ request('indicator_matrix_stoch_max', 100) }}" class="ak-input mt-1 h-9 w-full rounded-lg px-2 text-xs text-white"></label>
                                    <label class="col-span-2 text-[8px] font-black uppercase text-slate-400 sm:col-span-1">{{ __('MACD-Richtung') }}<select name="indicator_matrix_macd_direction" class="ak-input mt-1 h-9 w-full rounded-lg px-2 text-xs text-white"><option value="any">{{ __('beliebig') }}</option><option value="rising">{{ __('steigend') }}</option><option value="falling">{{ __('fallend') }}</option></select></label>
                                </div>
                            </fieldset>
                            @endif
                            <div class="grid {{ $qualitySetupMode ? 'grid-cols-1' : 'grid-cols-3' }} gap-3">
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ $qualitySetupMode ? __('Testbetrag') : __('Startkapital') }}
                                    <div class="relative mt-2"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" x-model.number="capital" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                                @if (! $qualitySetupMode)
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Max. Depotpositionen') }}
                                    <input name="max_positions" type="number" min="1" max="50" step="1" x-model.number="positions" @input="positionFactor = Math.min(positionFactor, Math.max(1, positions))" required class="ak-input mt-2 h-11 w-full rounded-lg text-sm font-bold text-white">
                                </label>
                                <label class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                                    {{ __('Kosten je Trade') }}
                                    <div class="relative mt-2"><input name="trade_cost" type="number" min="0" max="1000" step="0.01" x-model.number="tradeCost" required class="ak-input h-11 w-full rounded-lg pr-8 text-sm font-bold text-white"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">€</span></div>
                                </label>
                                @endif
                            </div>
                            </fieldset>
                            @if (! $qualitySetupMode)
                            <fieldset disabled class="hidden" aria-hidden="true">
                            <fieldset x-show="entryStrategy === 'forecast_score_rotation_5d' && exitStrategy !== 'buy_and_hold'" x-cloak class="mt-4 rounded-xl border border-amber-300/25 bg-amber-300/[.07] px-4 py-3">
                                <legend class="px-1 text-[10px] font-black uppercase tracking-wide text-amber-200">{{ __('Welche Strategie hat Priorität?') }}</legend>
                                <p class="mb-3 text-[10px] leading-4 text-slate-300">{{ __('Rotation und Exit können am selben Prüftag auslösen. Lege fest, welche Regel zuerst angewendet wird.') }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="cursor-pointer rounded-lg border border-white/10 bg-white/[.035] p-3 text-xs text-slate-200 has-[:checked]:border-amber-300/50 has-[:checked]:bg-amber-300/10"><input type="radio" name="strategy_priority" value="rotation_first" @checked(request('strategy_priority', 'rotation_first') === 'rotation_first') class="mr-2 text-amber-400" required><b>{{ __('Rotation zuerst') }}</b><small class="mt-1 block pl-6 text-[9px] text-slate-400">{{ __('Zuerst nach Sektor/Index und Forecast rotieren, anschließend die Exitregel prüfen.') }}</small></label>
                                    <label class="cursor-pointer rounded-lg border border-white/10 bg-white/[.035] p-3 text-xs text-slate-200 has-[:checked]:border-amber-300/50 has-[:checked]:bg-amber-300/10"><input type="radio" name="strategy_priority" value="exit_first" @checked(request('strategy_priority') === 'exit_first') class="mr-2 text-amber-400" required><b>{{ __('Exit zuerst') }}</b><small class="mt-1 block pl-6 text-[9px] text-slate-400">{{ __('Zuerst die Exitregel ausführen, danach freie Positionen neu besetzen.') }}</small></label>
                                </div>
                            </fieldset>
                            </fieldset>
                            <div class="mt-4 rounded-xl border border-white/[.08] bg-white/[.035] px-4 py-3">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ __('Allocation je Aktie') }}</span>
                                    <strong class="text-xs font-black text-amber-200" x-text="`${positionFactor}×`"></strong>
                                </div>
                                <div class="grid grid-cols-5 gap-1" role="radiogroup" aria-label="{{ __('Allocation je Aktie') }}">
                                    @for ($factor = 1; $factor <= 5; $factor++)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="position_factor" value="{{ $factor }}" x-model.number="positionFactor" class="peer sr-only">
                                            <span class="flex h-9 items-center justify-center rounded-md border border-white/10 text-xs font-black text-slate-400 transition peer-checked:border-amber-300/45 peer-checked:bg-amber-300/15 peer-checked:text-amber-200">{{ $factor }}×</span>
                                        </label>
                                    @endfor
                                </div>
                            </div>
                            <label class="mt-4 block rounded-xl border border-cyan-300/20 bg-cyan-400/[.055] px-4 py-3">
                                <span class="flex items-center justify-between gap-3 text-[10px] font-black uppercase tracking-wide text-slate-300">
                                    <span>{{ __('Indikator-Steigwahrscheinlichkeit') }}</span>
                                    <output class="text-cyan-200">{{ (float) request('indicator_probability_min', 0) > 0 ? '≥ '.number_format((float) request('indicator_probability_min'), 0, ',', '.').' %' : __('Alle') }}</output>
                                </span>
                                <input type="range" name="indicator_probability_min" min="0" max="100" step="5" value="{{ max(0, min(100, (float) request('indicator_probability_min', 0))) }}" oninput="this.previousElementSibling.querySelector('output').textContent=Number(this.value)>0?'≥ '+this.value+' %':@js(__('Alle'))" class="mt-3 h-2 w-full accent-cyan-400">
                                <small class="mt-2 block text-[9px] leading-4 text-slate-400">{{ __('Kombinierte 20-Tage-Steigwahrscheinlichkeit aus RSI, ADX, Stochastik, Volatilität, ATR, Bollinger-Bandbreite, MACD und Momentum.') }}</small>
                            </label>
                            <label class="mt-4 block rounded-xl border border-violet-300/20 bg-violet-400/[.055] px-4 py-3">
                                <span class="flex items-center justify-between gap-3 text-[10px] font-black uppercase tracking-wide text-slate-300">
                                    <span>{{ __('Noise-Score · Point-in-Time') }}</span>
                                    <output class="text-violet-200">{{ (float) request('noise_score_min', 0) > 0 ? '≥ '.number_format((float) request('noise_score_min'), 0, ',', '.') : __('Alle') }}</output>
                                </span>
                                <input type="range" name="noise_score_min" min="0" max="100" step="5" value="{{ max(0, min(100, (float) request('noise_score_min', 0))) }}" oninput="this.previousElementSibling.querySelector('output').textContent=Number(this.value)>0?'≥ '+this.value:@js(__('Alle'))" class="mt-3 h-2 w-full accent-violet-400">
                                <small class="mt-2 block text-[9px] leading-4 text-slate-400">{{ __('50 entspricht einer neutralen signierten Prognosefläche. Höhere Werte verlangen eine konsistentere positive 5T/10T/15T/20T-Prognose. Historische Tage ohne vollständige vier Horizonte werden ausgeschlossen.') }}</small>
                            </label>
                            <div class="mt-4 rounded-xl border border-white/[.08] bg-white/[.035] px-4 py-3 text-xs text-slate-300">
                                {{ __('Grundanteil je Aktie') }}: <strong class="text-white" x-text="`${Math.max(0, capital / Math.max(1, positions)).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}`"></strong>
                                <span class="mx-2 text-slate-600">·</span>
                                {{ __('Bei freiem Kapital maximal') }}:
                                <strong class="text-amber-200" x-text="`${Math.max(0, capital / Math.max(1, positions) * positionFactor).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} (${positionFactor}/${Math.max(1, positions)})`"></strong>
                            </div>
                            @endif
                            <div class="mt-5 flex justify-end gap-2">
                                <a href="{{ route($heatmapFilterRoute, request()->except(['new_backtest', 'backtest_run'])) }}" class="inline-flex h-10 items-center rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300">{{ __('Abbrechen') }}</a>
                                <button type="submit" formnovalidate :disabled="submitting" class="ak-backtest-submit-action inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/15 px-5 text-xs font-black text-amber-200 hover:bg-amber-300/20 disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="submitting" class="ak-backtest-spinner h-4 w-4" aria-hidden="true"></span>
                                    <x-heroicon-o-play x-show="!submitting" class="h-4 w-4" />
                                    <span x-text="submitting ? @js(__('Wird gestartet …')) : @js(__('Backtest ausführen'))"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
                @if ($backtestIsActive)
                    <div class="ak-backtest-progress" role="progressbar" aria-label="{{ __('Backtest läuft') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span id="ak-backtest-progress-bar"></span>
                    </div>
                @endif
            </section>
            <script>
                (() => {
                    const modal = document.querySelector('[data-backtest-capital-modal]');
                    if (!modal || modal.dataset.nativeCloseReady === '1') return;
                    modal.dataset.nativeCloseReady = '1';
                    const close = () => {
                        modal.hidden = true;
                        modal.style.setProperty('display', 'none', 'important');
                        document.documentElement.classList.remove('overflow-hidden');
                    };
                    const open = () => {
                        modal.hidden = false;
                        modal.removeAttribute('x-cloak');
                        modal.style.setProperty('display', 'flex', 'important');
                        document.documentElement.classList.add('overflow-hidden');
                    };
                    window.openBacktestCapitalModal = open;
                    if (@js(request()->boolean('new_backtest'))) open();
                    document.addEventListener('click', event => {
                        if (event.target.closest('[data-close-backtest-capital]')) close();
                        if (event.target.closest('[data-open-backtest-capital]')) open();
                        if (event.target === modal) close();
                    });
                    document.addEventListener('keydown', event => {
                        if (event.key === 'Escape' && !modal.hidden) close();
                    });
                })();
            </script>
            @if ($backtestIsActive)
                <script>
                    (() => {
                        const statusUrl = @json(route('setup.filter.backtest.status', $activeBacktestRun->public_id));
                        const statusCount = document.getElementById('ak-backtest-status-count');
                        const progressBar = document.getElementById('ak-backtest-progress-bar');
                        const progressTrack = progressBar?.parentElement;
                        const poll = async () => {
                            try {
                                const response = await fetch(statusUrl, {
                                    headers: { Accept: 'application/json' },
                                    cache: 'no-store',
                                });
                                if (response.ok) {
                                    const result = await response.json();
                                    if (result.finished) {
                                        window.location.reload();
                                        return;
                                    }
                                    if (result.instruments_total > 0) {
                                        const completed = Math.min(result.instruments_completed, result.instruments_total);
                                        const percent = Math.round((completed / result.instruments_total) * 100);
                                        statusCount.textContent = `${completed} / ${result.instruments_total} Aktien · ${percent} %`;
                                        progressBar.classList.add('is-determinate');
                                        progressBar.style.width = `${percent}%`;
                                        progressTrack?.setAttribute('aria-valuenow', String(percent));
                                    }
                                }
                            } catch (_) {
                                // A temporary connection interruption must not reload the complete page.
                            }
                            window.setTimeout(poll, 2500);
                        };
                        window.setTimeout(poll, 1200);
                    })();
                </script>
            @endif
            @endif
        @endif

        @php
            $heatmapScoreFilter = max(0, min(10, (float) request('score_min', 0)));
            $heatmapConfidenceFilter = max(0, min(100, (float) request('confidence_min', 0)));
            $heatmapMetrics = [
                ['key' => 'hit_rate', 'axis_key' => 'confidence', 'label' => __('Modellscore vs. Konfidenz'), 'axis_label' => __('Konfidenz'), 'axis_step' => 10.0, 'axis_suffix' => '%', 'suffix' => '%'],
                ['key' => 'profit_factor', 'axis_key' => 'profit_factor', 'label' => __('Modellscore vs. Profitfaktor'), 'axis_label' => __('Profitfaktor'), 'axis_step' => .3, 'axis_suffix' => '', 'suffix' => ''],
                ['key' => 'drawdown', 'axis_key' => 'hit_rate', 'label' => __('Modellscore vs. Hitrate'), 'axis_label' => __('Hitrate'), 'axis_step' => 10.0, 'axis_suffix' => '%', 'suffix' => '%'],
                ['key' => 'volatility', 'axis_key' => 'volatility', 'label' => __('Modellscore vs. Volatilität'), 'axis_label' => __('Volatilität'), 'axis_step' => 10.0, 'axis_suffix' => '%', 'suffix' => '%'],
            ];
            $averageBars = [
                'hit_rate' => [
                    'label' => __('Ø Hitrate'),
                    'display' => number_format((float) ($heatmapSummary?->hit_rate ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, (float) ($heatmapSummary?->hit_rate ?? 0))),
                    'color' => 'bg-emerald-400',
                    'colors' => ['bg-rose-500', 'bg-rose-400', 'bg-orange-400', 'bg-amber-400', 'bg-yellow-300', 'bg-lime-300', 'bg-lime-400', 'bg-green-400', 'bg-emerald-400', 'bg-emerald-500'],
                    'palette' => ['#f43f5e', '#fb7185', '#fb923c', '#fbbf24', '#fde047', '#bef264', '#a3e635', '#4ade80', '#34d399', '#10b981'],
                ],
                'profit_factor' => [
                    'label' => __('Ø Profitfaktor'),
                    'display' => number_format(\App\Support\ProfitFactor::cap($heatmapSummary?->profit_factor) ?? 0, 2, ',', '.'),
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->profit_factor ?? 0) / 2.5) * 100)),
                    'color' => 'bg-teal-400',
                    'colors' => ['bg-rose-500', 'bg-rose-500', 'bg-rose-400', 'bg-rose-400', 'bg-yellow-300', 'bg-yellow-300', 'bg-lime-300', 'bg-lime-400', 'bg-green-400', 'bg-emerald-500'],
                    'palette' => ['#f43f5e', '#f43f5e', '#fb7185', '#fb7185', '#fde047', '#fde047', '#bef264', '#a3e635', '#4ade80', '#10b981'],
                ],
                'drawdown' => [
                    'label' => __('Max. Drawdown'),
                    'display' => number_format((float) ($heatmapSummary?->drawdown ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->drawdown ?? 0) / 50) * 100)),
                    'color' => 'bg-rose-400',
                    'colors' => ['bg-emerald-400', 'bg-lime-400', 'bg-yellow-300', 'bg-yellow-300', 'bg-amber-300', 'bg-amber-300', 'bg-orange-400', 'bg-orange-400', 'bg-rose-400', 'bg-rose-500'],
                    'palette' => ['#34d399', '#a3e635', '#fde047', '#fde047', '#fcd34d', '#fcd34d', '#fb923c', '#fb923c', '#fb7185', '#f43f5e'],
                ],
                'volatility' => [
                    'label' => __('Ø Volatilität'),
                    'display' => number_format((float) ($heatmapSummary?->volatility ?? 0), 1, ',', '.').' %',
                    'width' => max(0, min(100, ((float) ($heatmapSummary?->volatility ?? 0) / 50) * 100)),
                    'color' => 'bg-orange-400',
                    'palette' => ['#34d399', '#a3e635', '#fde047', '#fde047', '#fcd34d', '#fcd34d', '#fb923c', '#fb923c', '#fb7185', '#f43f5e'],
                ],
            ];
        @endphp

        @if (! $qualitySetupMode)
        @php
            $scoreProfitCorrelation = (array) ($heatmapSummary?->score_profit_factor_correlation ?? []);
            $correlationSamples = (int) ($scoreProfitCorrelation['samples'] ?? 0);
            $spearmanCorrelation = $scoreProfitCorrelation['spearman'] ?? null;
            $pearsonCorrelation = $scoreProfitCorrelation['pearson'] ?? null;
            $correlationDirection = (string) ($scoreProfitCorrelation['direction'] ?? 'neutraler');
            $correlationStrength = (string) ($scoreProfitCorrelation['strength'] ?? 'nicht berechenbar');
            $correlationScope = (string) ($scoreProfitCorrelation['scope'] ?? 'filtered');
            $correlationTone = ! is_numeric($spearmanCorrelation) || abs((float) $spearmanCorrelation) < .20
                ? 'border-slate-400/20 text-slate-300'
                : ((float) $spearmanCorrelation > 0
                    ? 'border-emerald-400/30 text-emerald-300'
                    : 'border-rose-400/30 text-rose-300');
            $qualityProfitFactor = (float) ($heatmapSummary?->profit_factor ?? 0);
            $qualityHitRate = (float) ($heatmapSummary?->hit_rate ?? 0);
            $qualityDrawdown = (float) ($heatmapSummary?->drawdown ?? 100);
            $qualityInstruments = (int) ($heatmapSummary?->instruments ?? 0);
            $filterQualityScore = (int) round(
                max(0, min(1, ($qualityProfitFactor - .8) / .7)) * 30
                + max(0, min(1, ($qualityHitRate - 45) / 15)) * 25
                + max(0, min(1, 1 - ($qualityDrawdown / 50))) * 20
                + max(0, min(1, $qualityInstruments / 25)) * 15
                + max(0, min(1, (float) ($spearmanCorrelation ?? 0))) * 10
            );
            [$filterQualityLabel, $filterQualityTone] = match (true) {
                $filterQualityScore >= 80 => [__('Sehr gut'), 'border-emerald-300/40 bg-emerald-400/12 text-emerald-200'],
                $filterQualityScore >= 65 => [__('Gut'), 'border-teal-300/40 bg-teal-400/12 text-teal-200'],
                $filterQualityScore >= 50 => [__('Solide'), 'border-amber-300/40 bg-amber-400/10 text-amber-200'],
                $filterQualityScore >= 35 => [__('Schwach'), 'border-orange-300/40 bg-orange-400/10 text-orange-200'],
                default => [__('Kritisch'), 'border-rose-300/40 bg-rose-400/10 text-rose-200'],
            };
        @endphp
        <section class="ak-correlation-grid mb-3 grid shrink-0 gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]" style="grid-template-columns:minmax(240px,1fr) 120px 120px minmax(170px,220px) minmax(150px,190px);align-items:center;">
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Korrelationstest') }}</p>
                <h2 class="mt-1 text-sm font-black">{{ __('Profitfaktor vs. Modellscore') }}</h2>
                <p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ $correlationScope === 'filtered' ? __('Aktuelle Filterauswahl') : __('Gesamtes aktuelles Testportfolio · Filterauswahl zu klein') }} · {{ __('mindestens 10 Trades je Aktie · Profitfaktor bei 3,0 gekappt') }}</p>
            </div>
            <div class="rounded-lg border px-3 py-2 text-center {{ $correlationTone }}">
                <small class="block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">Spearman ρ</small>
                <strong class="mt-0.5 block text-lg font-black tabular-nums">{{ is_numeric($spearmanCorrelation) ? number_format((float) $spearmanCorrelation, 2, ',', '.') : '—' }}</strong>
            </div>
            <div class="rounded-lg border border-slate-400/20 px-3 py-2 text-center">
                <small class="block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">Pearson r</small>
                <strong class="mt-0.5 block text-lg font-black tabular-nums">{{ is_numeric($pearsonCorrelation) ? number_format((float) $pearsonCorrelation, 2, ',', '.') : '—' }}</strong>
            </div>
            <div class="min-w-[150px] rounded-lg border border-slate-400/20 px-3 py-2">
                <small class="block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Einordnung') }}</small>
                <strong class="mt-0.5 block text-xs font-black {{ $correlationTone }}">{{ $correlationSamples >= 3 ? __(':strength :direction Zusammenhang', ['strength' => ucfirst($correlationStrength), 'direction' => $correlationDirection]) : __('Zu wenige Aktien') }}</strong>
                <span class="mt-0.5 block text-[8px] text-[var(--ak-muted)]">n = {{ number_format($correlationSamples, 0, ',', '.') }} {{ __('Aktien') }}</span>
            </div>
            <div class="rounded-lg border px-3 py-2 {{ $filterQualityTone }}" title="{{ __('Gewichtung: Profitfaktor 30 %, Hitrate 25 %, Drawdown 20 %, Aktienanzahl 15 % und KI-Korrelation 10 %.') }}">
                <small class="block text-[8px] font-black uppercase tracking-wide opacity-75">{{ __('Filterqualität') }}</small>
                <div class="mt-0.5 flex items-baseline justify-between gap-2"><strong class="text-lg font-black tabular-nums">{{ $filterQualityScore }}/100</strong><span class="text-[9px] font-black uppercase">{{ $filterQualityLabel }}</span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-950/25"><span class="block h-full rounded-full bg-current" style="width: {{ $filterQualityScore }}%"></span></div>
            </div>
        </section>
        <section class="ak-heatmap-metric-grid grid min-h-0 w-full flex-none grid-cols-1 items-start gap-3 overflow-visible pb-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($heatmapMetrics as $metric)
                @php
                    $comparisonMap = (array) data_get($comparisonHeatmaps ?? [], $metric['axis_key'], []);
                    $comparisonStocks = (int) ($comparisonMap['stocks'] ?? 0);
                    $bar = $averageBars[$metric['key']];
                    $minimumProfitFactor = is_numeric($comparisonMap['min_profit_factor'] ?? null)
                        ? (float) $comparisonMap['min_profit_factor']
                        : null;
                    $bar['label'] = __('Niedrigster Profitfaktor je Aktie');
                    $bar['display'] = $minimumProfitFactor === null
                        ? '—'
                        : number_format($minimumProfitFactor, 2, ',', '.');
                    $bar['width'] = $minimumProfitFactor === null
                        ? 0
                        : min(100, max(0, $minimumProfitFactor / 3 * 100));
                    $bar['palette'] = ['#164e63', '#155e75', '#0e7490', '#0891b2', '#06b6d4', '#22d3ee', '#2dd4bf', '#34d399', '#6ee7b7', '#a7f3d0'];
                    $axisFilterValue = match ($metric['axis_key']) {
                        'confidence' => max(0, min(100, (float) request('confidence_min', 0))),
                        'profit_factor' => max(0, min(3, (float) request('profit_per_trade_min', 0))) / 3 * 100,
                        'hit_rate' => max(0, min(100, (float) request('hit_rate_min', 0))),
                        'volatility' => max(0, min(100, (float) request('volatility_max', 100))),
                    };
                    $axisFilterDisplay = match ($metric['axis_key']) {
                        'profit_factor' => number_format($axisFilterValue / 100 * 3, 1, ',', '.'),
                        default => number_format($axisFilterValue, 0, ',', '.').$metric['axis_suffix'],
                    };
                @endphp
                <details open data-mobile-heatmap-details class="flex h-auto min-h-0 min-w-0 flex-col overflow-visible rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 pb-5 shadow-[var(--ak-shadow)]">
                    <summary class="ak-mobile-heatmap-summary mb-2 flex shrink-0 cursor-pointer list-none items-center justify-between gap-2">
                        <h2 class="text-sm font-black">{{ $metric['label'] }}</h2>
                        <div class="flex items-center gap-2 text-[7px] font-bold uppercase tracking-wide text-amber-300/60">
                            <span>{{ __('KI') }} ≥ <b data-heatmap-score-label>{{ number_format($heatmapScoreFilter, 1, ',', '.') }}</b></span>
                            <span>{{ $metric['axis_label'] }} {{ $metric['axis_key'] === 'volatility' ? '≤' : '≥' }} <b data-heatmap-axis-label="{{ $metric['axis_key'] }}">{{ $axisFilterDisplay }}</b></span>
                            <strong class="ak-mobile-heatmap-current hidden text-xs text-[var(--ak-text)]">{{ $bar['display'] }}</strong>
                            <x-heroicon-o-chevron-down class="ak-mobile-heatmap-chevron hidden h-5 w-5 text-cyan-300" />
                        </div>
                    </summary>
                    <div class="mb-2 shrink-0 rounded-md border border-white/[.06] bg-white/[.025] px-2 py-1.5">
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <span class="truncate text-[8px] font-black uppercase tracking-[.08em] text-[var(--ak-muted)]">{{ $bar['label'] }}</span>
                            <strong class="shrink-0 text-xs font-black tabular-nums">{{ $bar['display'] }}</strong>
                        </div>
                        @php $reachedSegments = (int) ceil($bar['width'] / 10); @endphp
                        <div class="flex h-2 items-stretch gap-1">
                            @for ($segment = 1; $segment <= 10; $segment++)
                                @php
                                    $segmentHex = $bar['palette'][$segment - 1];
                                    $segmentOpacity = $segment < $reachedSegments
                                        ? .4
                                        : ($segment === $reachedSegments && $reachedSegments > 0 ? 1 : 0);
                                @endphp
                                <span class="min-w-0 flex-1 rounded-[2px] {{ $segmentOpacity <= 0 ? 'bg-slate-400/10' : '' }}" @if ($segmentOpacity > 0) style="background-color: {{ $segmentHex }}; opacity: {{ $segmentOpacity }};" @endif></span>
                            @endfor
                        </div>
                    </div>
                    <div class="grid aspect-square h-auto min-h-0 w-full flex-none grid-cols-[34px_repeat(10,minmax(0,1fr))] grid-rows-[repeat(10,minmax(0,1fr))_14px] gap-1" style="position: relative;">
                        <div
                            aria-hidden="true"
                            style="position: absolute; inset: 0 0 18px 38px; z-index: 20; overflow: hidden; pointer-events: none;"
                        >
                            <span
                                data-heatmap-score-line
                                data-heatmap-drag="score"
                                style="position: absolute; top: 0; bottom: 0; left: {{ max(1, min(99, $heatmapScoreFilter * 10)) }}%; display: block; width: 2px; transform: translateX(-1px); background: repeating-linear-gradient(to bottom, rgba(34, 211, 238, .72) 0 4px, transparent 4px 8px);"
                            ></span>
                            <span
                                data-heatmap-axis-line="{{ $metric['axis_key'] }}"
                                data-heatmap-drag="{{ $metric['axis_key'] }}"
                                style="position: absolute; right: 0; bottom: {{ max(1, min(99, $axisFilterValue)) }}%; left: 0; display: block; height: 2px; transform: translateY(1px); background: repeating-linear-gradient(to right, rgba(34, 211, 238, .72) 0 4px, transparent 4px 8px);"
                            ></span>
                        </div>
                        @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                            <div class="flex items-center justify-end pr-0.5 text-[7px] font-bold tabular-nums text-[var(--ak-muted)]">
                                @php
                                    $axisFrom = $confidenceBucket * $metric['axis_step'];
                                    $axisTo = ($confidenceBucket + 1) * $metric['axis_step'];
                                    $axisDecimals = $metric['axis_step'] < 1 ? 1 : 0;
                                @endphp
                                {{ number_format($axisFrom, $axisDecimals, ',', '.') }}–{{ number_format($axisTo, $axisDecimals, ',', '.') }}{{ $metric['axis_suffix'] }}
                            </div>
                            @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                @php
                                    $cellKey = $scoreBucket.'-'.$confidenceBucket;
                                    $cell = $heatmap->get($cellKey);
                                    $samples = (int) ($cell->samples ?? 0);
                                    $rawValue = is_numeric(data_get($cell, $metric['key'])) ? (float) data_get($cell, $metric['key']) : null;
                                    $hasValue = $metric['key'] === 'drawdown'
                                        ? $samples > 0
                                        : $samples >= 5 && $rawValue !== null;
                                    $good = match ($metric['key']) {
                                        'hit_rate' => $rawValue > 50,
                                        'profit_factor' => $rawValue >= 1.05,
                                        'drawdown' => $rawValue <= 25,
                                        'volatility' => $rawValue <= 25,
                                    };
                                    $weak = match ($metric['key']) {
                                        'hit_rate' => $rawValue < 50,
                                        'profit_factor' => $rawValue <= 1,
                                        'drawdown' => $rawValue > 45,
                                        'volatility' => $rawValue > 40,
                                    };
                                    $drawdownLimit = $rangeValue('drawdown_max', $rangeMaxima['drawdown'], 0, 'drawdown');
                                    $drawdownRatio = $drawdownLimit > 0 ? $rawValue / $drawdownLimit : ($rawValue <= 0 ? 0 : INF);
                                    $drawdownExcessRatio = $rawValue > $drawdownLimit
                                        ? ($rawValue - $drawdownLimit) / max(1.0, 50.0 - $drawdownLimit)
                                        : 0.0;
                                    $drawdownClass = $rawValue <= $drawdownLimit
                                        ? match (true) {
                                            $drawdownRatio <= .20 => 'border-emerald-300/25 bg-emerald-400/38 text-emerald-50',
                                            $drawdownRatio <= .40 => 'border-emerald-300/25 bg-emerald-400/30 text-emerald-50',
                                            $drawdownRatio <= .60 => 'border-emerald-300/25 bg-emerald-400/23 text-emerald-100',
                                            $drawdownRatio <= .80 => 'border-emerald-300/25 bg-green-400/17 text-green-100',
                                            default => 'border-emerald-300/25 bg-lime-400/12 text-lime-100',
                                        }
                                        : match (true) {
                                            $drawdownExcessRatio <= .25 => 'border-amber-300/20 bg-yellow-300/18 text-yellow-50',
                                            $drawdownExcessRatio <= .50 => 'border-amber-300/20 bg-amber-400/23 text-amber-50',
                                            $drawdownExcessRatio <= .75 => 'border-amber-300/20 bg-orange-400/27 text-orange-50',
                                            default => 'border-rose-400/20 bg-rose-500/30 text-rose-50',
                                        };
                                    $profitFactorClass = match (true) {
                                        $rawValue < 1.01 => 'border-rose-300/45 bg-rose-500/30 text-rose-50',
                                        $rawValue <= 1.10 => 'border-yellow-300/40 bg-yellow-300/22 text-yellow-50',
                                        default => 'border-emerald-300/30 bg-emerald-400/20 text-emerald-100',
                                    };
                                    $volatilityLimit = $rangeValue('volatility_max', $rangeMaxima['volatility'], 0, 'volatility');
                                    $volatilityRatio = $volatilityLimit > 0 ? $rawValue / $volatilityLimit : ($rawValue <= 0 ? 0 : INF);
                                    $volatilityExcessRatio = $rawValue > $volatilityLimit
                                        ? ($rawValue - $volatilityLimit) / max(1.0, 100.0 - $volatilityLimit)
                                        : 0.0;
                                    $volatilityClass = $rawValue <= $volatilityLimit
                                        ? match (true) {
                                            $volatilityRatio <= .20 => 'border-emerald-300/25 bg-emerald-400/38 text-emerald-50',
                                            $volatilityRatio <= .40 => 'border-emerald-300/25 bg-emerald-400/30 text-emerald-50',
                                            $volatilityRatio <= .60 => 'border-emerald-300/25 bg-emerald-400/23 text-emerald-100',
                                            $volatilityRatio <= .80 => 'border-emerald-300/25 bg-green-400/17 text-green-100',
                                            default => 'border-emerald-300/25 bg-lime-400/12 text-lime-100',
                                        }
                                        : match (true) {
                                            $volatilityExcessRatio <= .25 => 'border-amber-300/20 bg-yellow-300/18 text-yellow-50',
                                            $volatilityExcessRatio <= .50 => 'border-amber-300/20 bg-amber-400/23 text-amber-50',
                                            $volatilityExcessRatio <= .75 => 'border-amber-300/20 bg-orange-400/27 text-orange-50',
                                            default => 'border-rose-400/20 bg-rose-500/30 text-rose-50',
                                        };
                                    $hitRateCellPalette = [
                                        'bg-rose-500/30 text-rose-50', 'bg-rose-400/28 text-rose-50',
                                        'bg-orange-400/25 text-orange-50', 'bg-amber-400/23 text-amber-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-lime-300/18 text-lime-50',
                                        'bg-lime-400/20 text-lime-50', 'bg-green-400/22 text-green-50',
                                        'bg-emerald-400/25 text-emerald-50', 'bg-emerald-500/30 text-emerald-50',
                                    ];
                                    $profitFactorCellPalette = [
                                        'bg-rose-500/30 text-rose-50', 'bg-rose-500/30 text-rose-50',
                                        'bg-rose-400/27 text-rose-50', 'bg-rose-400/27 text-rose-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-yellow-300/20 text-yellow-50',
                                        'bg-lime-300/18 text-lime-50', 'bg-lime-400/20 text-lime-50',
                                        'bg-green-400/24 text-green-50', 'bg-emerald-500/30 text-emerald-50',
                                    ];
                                    $drawdownCellPalette = [
                                        'bg-emerald-400/30 text-emerald-50', 'bg-lime-400/26 text-lime-50',
                                        'bg-yellow-300/20 text-yellow-50', 'bg-yellow-300/20 text-yellow-50',
                                        'bg-amber-300/22 text-amber-50', 'bg-amber-300/22 text-amber-50',
                                        'bg-orange-400/25 text-orange-50', 'bg-orange-400/25 text-orange-50',
                                        'bg-rose-400/27 text-rose-50', 'bg-rose-500/30 text-rose-50',
                                    ];
                                    $volatilityCellPalette = [
                                        'bg-orange-400/[.08] text-orange-400', 'bg-orange-400/[.10] text-orange-400',
                                        'bg-orange-400/[.12] text-orange-400', 'bg-orange-400/[.14] text-orange-400',
                                        'bg-orange-400/[.16] text-orange-400', 'bg-orange-400/[.18] text-orange-400',
                                        'bg-orange-400/[.20] text-orange-400', 'bg-orange-400/[.23] text-orange-400',
                                        'bg-orange-400/[.26] text-orange-400', 'bg-orange-400/[.30] text-orange-400',
                                    ];
                                    $heatmapPaletteIndex = match ($metric['key']) {
                                        'hit_rate' => (int) max(0, min(9, floor((float) $rawValue / 10))),
                                        'profit_factor' => (int) max(0, min(9, floor((float) $rawValue / .25))),
                                        'drawdown' => (int) max(0, min(9, floor((float) $rawValue / 5))),
                                        'volatility' => (int) max(0, min(9, floor((float) $rawValue / 5))),
                                    };
                                    $metricCellClass = match ($metric['key']) {
                                        'hit_rate' => $hitRateCellPalette[$heatmapPaletteIndex],
                                        'profit_factor' => $profitFactorCellPalette[$heatmapPaletteIndex],
                                        'drawdown' => $drawdownCellPalette[$heatmapPaletteIndex],
                                        'volatility' => $volatilityCellPalette[$heatmapPaletteIndex],
                                    };
                                    $metricCellHex = $bar['palette'][$heatmapPaletteIndex];
                                    $outsideSelectedArea = ! $qualifiedHeatmapCells->has($cellKey);
                                    $cellClass = ! $hasValue
                                        ? 'border-white/[.05] bg-slate-500/[.07] text-slate-500'
                                        : 'border-teal-400/10 '.$metricCellClass;
                                    $displayValue = ! $hasValue
                                        ? ($samples ?: '—')
                                        : ($metric['key'] === 'profit_factor'
                                            ? number_format($rawValue, 2, ',', '.')
                                            : number_format($rawValue, 0, ',', '.').$metric['suffix']);

                                    // Conservative stock-level comparison: the
                                    // visible value is the weakest PF in a cell.
                                    $comparisonCell = (array) data_get($comparisonMap, 'cells.'.$cellKey, []);
                                    $samples = (int) ($comparisonCell['stocks'] ?? 0);
                                    $minimumCellProfitFactor = is_numeric($comparisonCell['min_profit_factor'] ?? null)
                                        ? (float) $comparisonCell['min_profit_factor']
                                        : null;
                                    $hasValue = $samples > 0 && $minimumCellProfitFactor !== null;
                                    $densityIndex = $hasValue
                                        ? (int) max(0, min(9, floor($minimumCellProfitFactor / .3)))
                                        : 0;
                                    $metricCellHex = $bar['palette'][$densityIndex];
                                    $scoreCellSelected = (($scoreBucket + 1) * 10) > ($heatmapScoreFilter * 10);
                                    $axisCellFromPercent = $confidenceBucket * 10;
                                    $axisCellToPercent = ($confidenceBucket + 1) * 10;
                                    $axisCellSelected = $metric['axis_key'] === 'volatility'
                                        ? $axisCellFromPercent < $axisFilterValue
                                        : $axisCellToPercent > $axisFilterValue;
                                    $outsideSelectedArea = ! ($scoreCellSelected && $axisCellSelected);
                                    $cellClass = $hasValue
                                        ? 'border-teal-400/15 text-white'
                                        : 'border-white/[.05] bg-slate-500/[.035] text-slate-600';
                                    $displayValue = $hasValue ? number_format($minimumCellProfitFactor, 2, ',', '.') : '—';
                                @endphp
                                <div class="ak-heatmap-cell relative flex aspect-square min-h-0 min-w-0 cursor-default items-center justify-center self-center rounded-[4px] border {{ $outsideSelectedArea ? 'border-white/[.08]' : $cellClass.($hasValue && $metric['key'] !== 'drawdown' ? ' !border-teal-400/10' : '') }}"
                                     style="{{ $outsideSelectedArea
                                         ? 'background-color: transparent !important; color: #f8fafc !important; opacity: 1 !important;'
                                         : ($hasValue ? 'background-color: color-mix(in srgb, '.$metricCellHex.' 24%, transparent); color: color-mix(in srgb, '.$metricCellHex.' 42%, white); border-color: rgba(34, 211, 238, .10);' : '') }}"
                                     title="{{ __('Modellscore :scoreFrom–:scoreTo · :axis :axisFrom–:axisTo · Mindest-PF :profitFactor · :samples Aktien', [
                                         'scoreFrom' => $scoreBucket,
                                         'scoreTo' => $scoreBucket + 1,
                                         'axis' => $metric['axis_label'],
                                         'axisFrom' => number_format($axisFrom, $axisDecimals, ',', '.').$metric['axis_suffix'],
                                         'axisTo' => number_format($axisTo, $axisDecimals, ',', '.').$metric['axis_suffix'],
                                         'profitFactor' => $minimumCellProfitFactor === null ? '—' : number_format($minimumCellProfitFactor, 2, ',', '.'),
                                         'samples' => $samples,
                                    ]) }}">
                                    <span class="absolute inset-0 z-10 grid place-items-center text-[7px] font-black tabular-nums sm:text-[8px]"
                                          style="visibility:visible !important; opacity:1 !important; color:{{ $outsideSelectedArea ? '#f8fafc' : 'inherit' }} !important; background:transparent !important;">
                                        {{ $displayValue }}
                                    </span>
                                </div>
                            @endfor
                        @endfor
                        <div></div>
                        @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                            <div class="text-center text-[7px] font-bold tabular-nums text-[var(--ak-muted)]">{{ $scoreBucket }}–{{ $scoreBucket + 1 }}</div>
                        @endfor
                    </div>
                </details>
            @endforeach
        </section>
        @endif
        <script>
            (() => {
                const filterForm = document.getElementById('prediction-heatmap-filters');
                if (window.matchMedia('(max-width: 767px)').matches) {
                    document.querySelectorAll('[data-mobile-heatmap-details]').forEach(card => {
                        card.removeAttribute('open');
                    });
                }
                const storedScroll = sessionStorage.getItem('aktienki-strategy-filter-scroll');
                if (storedScroll !== null) {
                    sessionStorage.removeItem('aktienki-strategy-filter-scroll');
                    requestAnimationFrame(() => window.scrollTo({ top: Number(storedScroll) || 0, behavior: 'instant' }));
                }
                const scoreInput = filterForm?.querySelector('input[name="score_min"]');
                const confidenceInput = filterForm?.querySelector('input[name="confidence_min"]');
                const profitFactorInput = filterForm?.querySelector('input[name="profit_per_trade_min"]');
                const hitRateInput = filterForm?.querySelector('input[name="hit_rate_min"]');
                const volatilityInput = filterForm?.querySelector('input[name="volatility_max"]');

                const updateHeatmapFilterLines = () => {
                    const score = Math.max(0, Math.min(10, Number(scoreInput?.value ?? 0)));
                    const confidence = Math.max(0, Math.min(100, Number(confidenceInput?.value ?? 0)));
                    const scorePosition = Math.max(1, Math.min(99, score * 10));
                    const confidencePosition = Math.max(1, Math.min(99, confidence));
                    const axisPositions = {
                        confidence: confidence,
                        profit_factor: Math.max(0, Math.min(3, Number(profitFactorInput?.value ?? 0))) / 3 * 100,
                        hit_rate: Math.max(0, Math.min(100, Number(hitRateInput?.value ?? 0))),
                        volatility: Math.max(0, Math.min(100, Number(volatilityInput?.value ?? 100))),
                    };

                    document.querySelectorAll('[data-heatmap-score-line]').forEach(line => {
                        line.style.left = `${scorePosition}%`;
                    });
                    document.querySelectorAll('[data-heatmap-axis-line]').forEach(line => {
                        const position = axisPositions[line.dataset.heatmapAxisLine] ?? confidencePosition;
                        line.style.bottom = `${Math.max(1, Math.min(99, position))}%`;
                    });
                    document.querySelectorAll('[data-heatmap-score-label]').forEach(label => {
                        label.textContent = score.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                    });
                    document.querySelectorAll('[data-heatmap-axis-label]').forEach(label => {
                        const axis = label.dataset.heatmapAxisLabel;
                        const values = {
                            confidence: `${Math.round(confidence).toLocaleString('de-DE')}%`,
                            profit_factor: Math.max(0, Math.min(3, Number(profitFactorInput?.value ?? 0))).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
                            hit_rate: `${Math.round(Math.max(0, Math.min(100, Number(hitRateInput?.value ?? 0)))).toLocaleString('de-DE')}%`,
                            volatility: `${Math.round(Math.max(0, Math.min(100, Number(volatilityInput?.value ?? 100)))).toLocaleString('de-DE')}%`,
                        };
                        label.textContent = values[axis] ?? '—';
                    });
                };

                scoreInput?.addEventListener('input', updateHeatmapFilterLines);
                confidenceInput?.addEventListener('input', updateHeatmapFilterLines);
                profitFactorInput?.addEventListener('input', updateHeatmapFilterLines);
                hitRateInput?.addEventListener('input', updateHeatmapFilterLines);
                volatilityInput?.addEventListener('input', updateHeatmapFilterLines);

                let activeHeatmapDrag = null;
                let heatmapDragChanged = false;

                const setDraggedHeatmapFilter = (event) => {
                    if (!activeHeatmapDrag) return;
                    const surface = activeHeatmapDrag.parentElement;
                    const bounds = surface?.getBoundingClientRect();
                    if (!bounds?.width || !bounds?.height) return;

                    if (activeHeatmapDrag.dataset.heatmapDrag === 'score' && scoreInput) {
                        const rawScore = ((event.clientX - bounds.left) / bounds.width) * 10;
                        const score = Math.max(0, Math.min(10, Math.round(rawScore * 2) / 2));
                        scoreInput.value = String(score);
                        scoreInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    if (activeHeatmapDrag.dataset.heatmapDrag === 'confidence' && confidenceInput) {
                        const rawConfidence = ((bounds.bottom - event.clientY) / bounds.height) * 100;
                        const confidence = Math.max(0, Math.min(100, Math.round(rawConfidence / 5) * 5));
                        confidenceInput.value = String(confidence);
                        confidenceInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    const rawAxisPercent = Math.max(0, Math.min(100, ((bounds.bottom - event.clientY) / bounds.height) * 100));
                    if (activeHeatmapDrag.dataset.heatmapDrag === 'profit_factor' && profitFactorInput) {
                        const profitFactor = Math.round((rawAxisPercent / 100 * 3) * 10) / 10;
                        profitFactorInput.value = String(profitFactor);
                        profitFactorInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (activeHeatmapDrag.dataset.heatmapDrag === 'hit_rate' && hitRateInput) {
                        const hitRate = Math.round(rawAxisPercent / 5) * 5;
                        hitRateInput.value = String(hitRate);
                        hitRateInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (activeHeatmapDrag.dataset.heatmapDrag === 'volatility' && volatilityInput) {
                        const volatility = Math.round(rawAxisPercent / 5) * 5;
                        volatilityInput.value = String(volatility);
                        volatilityInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    heatmapDragChanged = true;
                    updateHeatmapFilterLines();
                };

                document.querySelectorAll('[data-heatmap-drag]').forEach(line => {
                    line.addEventListener('pointerdown', event => {
                        if (event.pointerType === 'mouse' && event.button !== 0) return;
                        activeHeatmapDrag = line;
                        heatmapDragChanged = false;
                        line.setPointerCapture?.(event.pointerId);
                        event.preventDefault();
                        setDraggedHeatmapFilter(event);
                    });
                    line.addEventListener('pointermove', event => {
                        if (activeHeatmapDrag !== line) return;
                        event.preventDefault();
                        setDraggedHeatmapFilter(event);
                    });
                    const finishHeatmapDrag = event => {
                        if (activeHeatmapDrag !== line) return;
                        line.releasePointerCapture?.(event.pointerId);
                        activeHeatmapDrag = null;
                        if (heatmapDragChanged && !@js($qualitySetupMode)) {
                            sessionStorage.setItem('aktienki-strategy-filter-scroll', String(window.scrollY));
                            window.setTimeout(() => filterForm?.requestSubmit(), 0);
                        }
                    };
                    line.addEventListener('pointerup', finishHeatmapDrag);
                    line.addEventListener('pointercancel', finishHeatmapDrag);
                });
                updateHeatmapFilterLines();
            })();
        </script>

        <p class="mt-2 shrink-0 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Graue Felder enthalten zu wenige validierte Prognosen für eine belastbare Bewertung.') }}</p>
    </div>

    @if (($setupMode ?? false) && !($qualitySetupMode ?? false) && ($backtestIsComplete ?? false))
        @php
            $runSummary = json_decode((string) ($activeBacktestRun->summary ?? '{}'), true) ?: [];
            $dynamicExitComparison = collect(data_get($runSummary, 'dynamic_exit_summary.comparison', []));
            $activeRunSettings = json_decode((string) ($activeBacktestRun->settings ?? '{}'), true) ?: [];
            $automaticComparisonActive = (bool) data_get($activeRunSettings, 'selection_filters.automatic_strategy_comparison', false);
        @endphp
        <div x-data="{ open: @js(request()->boolean('show_result')) }" x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @open-backtest-result.window="open = true" @automatic-strategy-selected.window="open = false" @keydown.escape.window="if (!@js($automaticComparisonActive)) open = false">
            <section class="ak-backtest-result-dialog w-full max-w-5xl rounded-2xl border border-teal-300/20 bg-[#15243a]/90 p-5 shadow-2xl" @click.outside="if (!@js($automaticComparisonActive)) open = false">
                <div id="filtered-backtest-result-loading" class="mb-4 flex items-center gap-3 rounded-xl border border-cyan-300/20 bg-cyan-400/[.06] px-4 py-3" role="status" aria-live="polite">
                    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center">
                        <span class="absolute h-8 w-8 animate-ping rounded-full bg-cyan-300/15"></span>
                        <span class="h-6 w-6 animate-spin rounded-full border-2 border-cyan-300/20 border-t-cyan-300"></span>
                    </span>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-200">{{ __('Auswertung läuft') }}</p>
                        <p id="filtered-backtest-result-loading-text" class="mt-0.5 text-[10px] text-slate-300">{{ $automaticComparisonActive ? __('Strategievarianten werden berechnet und miteinander verglichen …') : __('Ergebnisse und Diagramm werden aufbereitet …') }}</p>
                    </div>
                </div>
                <header class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Persönlicher 3-Jahres-Backtest') }}</p>
                        <h2 class="mt-1 text-xl font-black text-white">{{ __('Backtest-Ergebnis') }}</h2>
                        <p class="mt-1 text-xs text-slate-300">{{ __('Strategie und S&P 500 starten mit demselben gewählten Kapital.') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! $qualitySetupMode)
                        <a href="{{ route('setup.filter.backtest.report', $activeBacktestRun->public_id) }}" class="ak-backtest-report-link inline-flex h-9 items-center gap-2 rounded-lg border border-teal-300/20 bg-teal-400/10 px-3 text-[10px] font-black uppercase tracking-wide text-teal-200 hover:bg-teal-400/15">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />{{ __('PDF-Bericht') }}
                        </a>
                        @endif
                        <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('cancel-backtest-result'))" class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-300/20 bg-rose-400/[.07] px-3 text-[10px] font-black uppercase tracking-wide text-rose-200 transition hover:bg-rose-400/15">
                            <x-heroicon-o-x-mark class="h-4 w-4" />{{ __('Abbrechen') }}
                        </button>
                        @unless ($automaticComparisonActive)<button type="button" @click="open = false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:text-white" aria-label="{{ __('Schließen') }}">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>@endunless
                    </div>
                </header>

                @if (! $qualitySetupMode)
                <div class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                    @foreach ([
                        [__('Startkapital'), '…', 'filtered-backtest-initial-capital'],
                        [__('Endkapital'), '…', 'filtered-backtest-final-capital'],
                        [__('Ausgeführte Trades'), '…', 'filtered-backtest-executed-trades'],
                        [__('Übersprungen'), '…', 'filtered-backtest-skipped-trades'],
                        [__('Gesamtkosten'), '…', 'filtered-backtest-total-costs'],
                        [__('Hitrate'), '…', 'filtered-backtest-hit-rate'],
                        [__('Ø Profit je Trade (ATR · 3J WF)'), '…', 'filtered-backtest-profit-per-trade'],
                        [__('Max. Portfolio-Drawdown'), '…', 'filtered-backtest-drawdown'],
                    ] as $index => $metric)
                        @php [$label, $value, $metricId] = array_pad($metric, 3, ''); @endphp
                        <div class="flex min-h-14 items-center justify-between gap-3 rounded-xl border border-white/[.08] bg-white/[.035] px-3 py-2">
                            <span class="max-w-[58%] text-[8px] font-black uppercase leading-4 tracking-wide text-slate-400">{{ $label }}</span>
                            <strong @if ($metricId) id="{{ $metricId }}" @endif class="shrink-0 text-right text-sm font-black tabular-nums text-white">{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
                @else
                <div class="mb-4 grid grid-cols-5 gap-2">
                    @foreach ([
                        [__('Gewinner'), 'filtered-backtest-winner-trades', 'text-emerald-300'],
                        [__('Verlierer'), 'filtered-backtest-loser-trades', 'text-rose-300'],
                        [__('Ø Gewinnfaktor'), 'filtered-backtest-average-gain-factor', 'text-teal-200'],
                        [__('Min. Drawdown'), 'filtered-backtest-minimum-drawdown', 'text-emerald-200'],
                        [__('Max. Drawdown'), 'filtered-backtest-quality-maximum-drawdown', 'text-rose-200'],
                        [__('Ø Profit/Trade (ATR)'), 'filtered-backtest-average-return', 'text-orange-400'],
                        [__('Gesamtkapital'), 'filtered-backtest-quality-total-capital', 'text-white'],
                        [__('Gewinn/Verlust-Ratio'), 'filtered-backtest-win-loss-ratio', 'text-violet-200'],
                        [__('Gesamt-Investmentdauer'), 'filtered-backtest-investment-days', 'text-amber-200'],
                    ] as [$label, $metricId, $color])
                        <div class="rounded-xl border border-white/[.08] bg-white/[.035] px-3 py-2">
                            <span class="block text-[9px] font-black uppercase tracking-wide text-slate-400">{{ $label }}</span>
                            <strong id="{{ $metricId }}" class="mt-1 block text-lg font-black tabular-nums {{ $color }}">…</strong>
                        </div>
                    @endforeach
                </div>
                @endif
                @if (! $qualitySetupMode)
                <div class="rounded-xl border border-white/[.06] bg-slate-950/15 px-3 pt-1">
                    <div id="filtered-backtest-result-chart" class="h-[285px] w-full"></div>
                </div>
                @if ($automaticComparisonActive)
                    <div class="mt-4 rounded-xl border border-emerald-300/20 bg-emerald-400/[.045] p-3">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div><p class="text-[9px] font-black uppercase tracking-wide text-emerald-300">{{ __('Automatischer Strategievergleich') }}</p><p class="mt-1 text-[9px] text-slate-400">{{ __('Wähle eine Variante aus, bevor du das Ergebnis übernimmst.') }}</p></div>
                            <span class="rounded-md border border-violet-300/20 bg-violet-400/[.08] px-2 py-1 text-[8px] font-black text-violet-200">{{ match(data_get($activeRunSettings, 'selection_filters.entry_risk_style', 'balanced')) { 'conservative' => __('Konservativ'), 'chance' => __('Chance'), default => __('Ausgewogen') } }}</span>
                        </div>
                        <div class="max-h-48 overflow-auto rounded-lg border border-white/[.07]">
                            <table class="w-full min-w-[680px] text-left text-[9px] text-slate-300">
                                <thead class="sticky top-0 bg-[#15243a] uppercase tracking-wide text-emerald-300"><tr><th class="px-2 py-2"></th><th class="px-2 py-2">{{ __('Strategie') }}</th><th class="px-2 py-2">{{ __('Endkapital') }}</th><th class="px-2 py-2">{{ __('Performance') }}</th><th class="px-2 py-2">{{ __('Drawdown') }}</th><th class="px-2 py-2">{{ __('Trades') }}</th></tr></thead>
                                <tbody id="automatic-strategy-result-rows"></tbody>
                            </table>
                        </div>
                        <div class="mt-3 flex justify-end"><button id="automatic-strategy-confirm" type="button" disabled class="h-9 rounded-lg border border-emerald-300/25 bg-emerald-400/10 px-4 text-[10px] font-black text-emerald-200 disabled:cursor-not-allowed disabled:opacity-35">{{ __('Auswahl übernehmen') }}</button></div>
                    </div>
                @endif
                @if ($dynamicExitComparison->isNotEmpty())
                    <div class="mt-4 max-h-52 overflow-auto rounded-xl border border-cyan-300/15">
                        <table class="w-full min-w-[760px] text-left text-[10px] text-slate-300">
                            <thead class="sticky top-0 bg-[#15243a] text-[9px] uppercase tracking-wide text-cyan-300">
                                <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">{{ __('Regeln') }}</th><th class="px-3 py-2">{{ __('Validierung') }}</th><th class="px-3 py-2">{{ __('Profitfaktor') }}</th><th class="px-3 py-2">{{ __('Hitrate') }}</th><th class="px-3 py-2">{{ __('Drawdown') }}</th><th class="px-3 py-2">{{ __('Trades') }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($dynamicExitComparison as $variant)
                                    @php
                                        $activeRules = collect((array) data_get($variant, 'rules', []))->filter()->keys()->map(fn ($rule) => match ($rule) {
                                            'fixed_20d' => '20T', 'dynamic_horizon' => __('Horizont'), 'support_stop' => __('Support'),
                                            'resistance_trailing_stop' => __('Widerstand'), 'entry_wait_5d' => 'WAIT 5T', default => $rule,
                                        })->implode(' · ');
                                    @endphp
                                    <tr class="border-t border-white/[.06] {{ $loop->first ? 'bg-emerald-400/[.07]' : '' }}">
                                        <td class="px-3 py-2 font-black {{ $loop->first ? 'text-emerald-300' : '' }}">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2 font-bold">{{ $activeRules ?: __('Basis') }}</td>
                                        <td class="px-3 py-2">{{ number_format((float) data_get($variant, 'validation.average_return', 0), 2, ',', '.') }} %/Trade</td>
                                        <td class="px-3 py-2">{{ number_format(\App\Support\ProfitFactor::cap(data_get($variant, 'validation.profit_factor', 0)) ?? 0, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2">{{ number_format((float) data_get($variant, 'validation.hit_rate', 0), 1, ',', '.') }} %</td>
                                        <td class="px-3 py-2">{{ number_format((float) data_get($variant, 'validation.max_drawdown', 0), 1, ',', '.') }} %</td>
                                        <td class="px-3 py-2">{{ (int) data_get($variant, 'validation.trades', 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[9px] text-slate-400">{{ __('Platz 1 ist die gewählte robuste Variante. Die Kennzahlen stammen aus dem chronologisch getrennten 30-%-Validierungsabschnitt und enthalten die hinterlegten Transaktionskosten.') }}</p>
                @endif
                <div class="mt-3 flex items-center justify-between gap-4 text-[10px] text-slate-400">
                    <span>{{ __('Portfolio-Verlauf auf Basis gleich gewichteter, am jeweiligen Ausstiegstag zusammengefasster Trades.') }}</span>
                    <span id="filtered-backtest-benchmark-performance" class="shrink-0 font-bold text-slate-300"></span>
                </div>
                @endif
            </section>
        </div>
        @if ($automaticComparisonActive)
        <div id="automatic-strategy-save-prompt" class="fixed inset-0 z-[110] hidden items-center justify-center bg-slate-950/85 p-4 backdrop-blur-md">
            <section class="w-full max-w-md rounded-2xl border border-emerald-300/25 bg-[#15243a] p-5 shadow-2xl">
                <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-300/25 bg-emerald-400/10 text-emerald-300"><x-heroicon-o-bookmark class="h-5 w-5" /></span><div><p class="text-[10px] font-black uppercase tracking-wide text-emerald-300">{{ __('Strategie ausgewählt') }}</p><h3 class="mt-1 text-lg font-black text-white">{{ __('Als Strategie speichern?') }}</h3><p class="mt-2 text-xs leading-5 text-slate-300">{{ __('Möchtest du die ausgewählte Variante mit dem festgelegten Auswahlprofil für spätere Backtests und Depots speichern?') }}</p></div></div>
                <div class="mt-5 flex justify-end gap-2"><button id="automatic-strategy-save-no" type="button" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-bold text-slate-300 hover:text-white">{{ __('Nur Ergebnis übernehmen') }}</button><button id="automatic-strategy-save-yes" type="button" class="h-10 rounded-lg border border-emerald-300/30 bg-emerald-400/15 px-4 text-xs font-black text-emerald-200 hover:bg-emerald-400/20">{{ __('Jetzt speichern') }}</button></div>
            </section>
        </div>
        @endif
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const target = document.querySelector('#filtered-backtest-result-chart');
                @if (! $qualitySetupMode)
                if (!target || !window.ApexCharts) return;
                @endif
                const loading = document.querySelector('#filtered-backtest-result-loading');
                const loadingText = document.querySelector('#filtered-backtest-result-loading-text');
                const resultRequestController = new AbortController();
                window.addEventListener('cancel-backtest-result', () => resultRequestController.abort(), { once: true });
                let result;
                try {
                    const response = await fetch(@json(route('setup.filter.backtest.result', $activeBacktestRun->public_id)), {
                        cache: 'no-store',
                        headers: { Accept: 'application/json' },
                        signal: resultRequestController.signal,
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    result = await response.json();
                    loading?.classList.add('hidden');
                } catch (error) {
                    if (error?.name === 'AbortError') return;
                    if (loading) {
                        loading.classList.remove('border-cyan-300/20', 'bg-cyan-400/[.06]');
                        loading.classList.add('border-rose-300/25', 'bg-rose-400/[.07]');
                        const spinner = loading.querySelector('.animate-spin');
                        spinner?.classList.remove('animate-spin', 'border-cyan-300/20', 'border-t-cyan-300');
                        spinner?.classList.add('border-rose-300/40');
                    }
                    if (loadingText) loadingText.textContent = '{{ __('Die Auswertung konnte nicht geladen werden. Bitte versuche es erneut oder öffne das Ergebnis später noch einmal.') }}';
                    console.error('Backtest result could not be loaded', error);
                    return;
                }
                const performance = document.querySelector('#filtered-backtest-performance');
                const finalCapital = document.querySelector('#filtered-backtest-final-capital');
                const initialCapital = document.querySelector('#filtered-backtest-initial-capital');
                const executedTrades = document.querySelector('#filtered-backtest-executed-trades');
                const skippedTrades = document.querySelector('#filtered-backtest-skipped-trades');
                const totalCosts = document.querySelector('#filtered-backtest-total-costs');
                const hitRate = document.querySelector('#filtered-backtest-hit-rate');
                const profitPerTrade = document.querySelector('#filtered-backtest-profit-per-trade');
                const drawdown = document.querySelector('#filtered-backtest-drawdown');
                const winnerTrades = document.querySelector('#filtered-backtest-winner-trades');
                const loserTrades = document.querySelector('#filtered-backtest-loser-trades');
                const averageGainFactor = document.querySelector('#filtered-backtest-average-gain-factor');
                const minimumDrawdown = document.querySelector('#filtered-backtest-minimum-drawdown');
                const qualityMaximumDrawdown = document.querySelector('#filtered-backtest-quality-maximum-drawdown');
                const averageReturn = document.querySelector('#filtered-backtest-average-return');
                const qualityTotalCapital = document.querySelector('#filtered-backtest-quality-total-capital');
                const winLossRatio = document.querySelector('#filtered-backtest-win-loss-ratio');
                const investmentDays = document.querySelector('#filtered-backtest-investment-days');
                const benchmarkPerformance = document.querySelector('#filtered-backtest-benchmark-performance');
                const updateExitMetric = (id, strategyPerformance, tradesPerMonth, strategyDrawdown) => {
                    const element = document.querySelector(`#${id}`);
                    if (!element) return;
                    element.classList.remove('text-slate-500');
                    element.classList.add('text-slate-300');
                    element.textContent = `${Number(strategyPerformance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} % · ${Number(tradesPerMonth).toLocaleString('de-DE', { maximumFractionDigits: 2 })} Trades/Monat · DD ${Number(strategyDrawdown).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                };
                if (initialCapital) initialCapital.textContent = Number(result.initial_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (finalCapital) finalCapital.textContent = Number(result.final_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (executedTrades) executedTrades.textContent = Number(result.executed_trades).toLocaleString('de-DE');
                if (skippedTrades) skippedTrades.textContent = Number(result.skipped_trades).toLocaleString('de-DE');
                if (totalCosts) totalCosts.textContent = Number(result.total_costs).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (hitRate) hitRate.textContent = `${Number(result.hit_rate).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (profitPerTrade) profitPerTrade.textContent = `${Number(result.average_trade_return || 0) >= 0 ? '+' : ''}${Number(result.average_trade_return || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} ATR`;
                if (drawdown) drawdown.textContent = `${Number(result.portfolio_max_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`;
                if (winnerTrades) winnerTrades.textContent = Number(result.winner_trades || 0).toLocaleString('de-DE');
                if (loserTrades) loserTrades.textContent = Number(result.loser_trades || 0).toLocaleString('de-DE');
                if (averageGainFactor) averageGainFactor.textContent = result.average_gain_factor === null ? '∞' : Number(result.average_gain_factor).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (minimumDrawdown) minimumDrawdown.textContent = `${Number(result.minimum_trade_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                if (qualityMaximumDrawdown) qualityMaximumDrawdown.textContent = `${Number(result.max_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                if (averageReturn) averageReturn.textContent = `${Number(result.average_trade_return || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} ATR`;
                if (qualityTotalCapital) qualityTotalCapital.textContent = Number(result.final_capital || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
                if (winLossRatio) winLossRatio.textContent = result.win_loss_ratio === null ? '∞' : Number(result.win_loss_ratio).toLocaleString('de-DE', { maximumFractionDigits: 2 });
                if (investmentDays) investmentDays.textContent = `${Number(result.total_investment_days || 0).toLocaleString('de-DE')} {{ __('Tage') }}`;
                const automaticRows = document.querySelector('#automatic-strategy-result-rows');
                if (automaticRows) {
                    const variants = {
                        selected_strategy: { label: '{{ __('Gewählte Strategie') }}', final_capital: result.final_capital, performance: result.strategy_performance, max_drawdown: result.portfolio_max_drawdown, executed_trades: result.executed_trades },
                        forecast_entry: { label: '{{ __('Forecast-Score-Einstieg 5T') }}', final_capital: result.forecast_score_rotation_final_capital, performance: result.forecast_score_rotation_performance, max_drawdown: result.forecast_score_rotation_max_drawdown, executed_trades: result.forecast_score_rotation_executed_trades },
                        sector_entry: { label: '{{ __('Sektorrotation') }}', final_capital: result.sector_entry_rotation_final_capital, performance: result.sector_entry_rotation_performance, max_drawdown: result.sector_entry_rotation_max_drawdown, executed_trades: result.sector_entry_rotation_executed_trades },
                        index_entry: { label: '{{ __('Indexrotation') }}', final_capital: result.index_entry_rotation_final_capital, performance: result.index_entry_rotation_performance, max_drawdown: result.index_entry_rotation_max_drawdown, executed_trades: result.index_entry_rotation_executed_trades },
                        buy_and_hold: { label: '{{ __('Buy and Hold') }}', final_capital: result.buy_and_hold_final_capital, performance: result.buy_and_hold_performance, max_drawdown: result.buy_and_hold_max_drawdown, executed_trades: result.buy_and_hold_executed_trades },
                    };
                    const automaticLabels = { auto_exit_fixed_20d: 'Exit 20T', auto_exit_dynamic_horizon: '{{ __('Dynamischer Horizont') }}', auto_exit_support_stop: 'Support-Stop', auto_exit_resistance_trailing: 'Resistance-Trailing', auto_exit_signal_change: '{{ __('Signalwechsel') }}', auto_exit_forecast_below_price: '{{ __('Prognose unter Kurs') }}', auto_entry_wait_5d: 'WAIT 5T' };
                    Object.entries(result.automatic_exit_variants || {}).forEach(([key, value]) => variants[key] = { label: automaticLabels[key] || key, ...value });
                    automaticRows.innerHTML = Object.entries(variants).filter(([, value]) => Number(value.executed_trades || 0) > 0).map(([key, value]) => `<tr class="border-t border-white/[.06]"><td class="px-2 py-2"><input type="radio" name="automatic_result_strategy" value="${key}" class="h-4 w-4 border-slate-500 bg-slate-900 text-emerald-500"></td><td class="px-2 py-2 font-bold text-white">${value.label}</td><td class="px-2 py-2">${Number(value.final_capital || 0).toLocaleString('de-DE', { style:'currency', currency:'EUR' })}</td><td class="px-2 py-2">${Number(value.performance || 0).toLocaleString('de-DE', { maximumFractionDigits:2 })} %</td><td class="px-2 py-2">${Number(value.max_drawdown || 0).toLocaleString('de-DE', { maximumFractionDigits:2 })} %</td><td class="px-2 py-2">${Number(value.executed_trades || 0).toLocaleString('de-DE')}</td></tr>`).join('');
                    const confirm = document.querySelector('#automatic-strategy-confirm');
                    automaticRows.addEventListener('change', event => { if (event.target.matches('input[type="radio"]')) confirm.disabled = false; });
                    confirm?.addEventListener('click', () => {
                        const selected = automaticRows.querySelector('input[type="radio"]:checked'); if (!selected) return;
                        sessionStorage.setItem('aktienki-selected-backtest-strategy', selected.value);
                        window.dispatchEvent(new CustomEvent('automatic-strategy-selected'));
                        const prompt = document.querySelector('#automatic-strategy-save-prompt'); prompt?.classList.remove('hidden'); prompt?.classList.add('flex');
                    });
                    document.querySelector('#automatic-strategy-save-no')?.addEventListener('click', event => { const prompt = event.currentTarget.closest('#automatic-strategy-save-prompt'); prompt?.classList.add('hidden'); prompt?.classList.remove('flex'); });
                    document.querySelector('#automatic-strategy-save-yes')?.addEventListener('click', event => {
                        const prompt = event.currentTarget.closest('#automatic-strategy-save-prompt'); prompt?.classList.add('hidden'); prompt?.classList.remove('flex');
                        const saveForm = document.querySelector('form[action*="/setup/filter/saved"]');
                        if (saveForm) { let input = saveForm.querySelector('input[name="automatic_selected_strategy"]'); if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'automatic_selected_strategy'; saveForm.appendChild(input); } input.value = sessionStorage.getItem('aktienki-selected-backtest-strategy') || ''; }
                        window.dispatchEvent(new CustomEvent('open-save-filter'));
                    });
                }
                @if (! $qualitySetupMode)
                updateExitMetric('save-exit-fixed', result.strategy_performance, result.trades_per_month, result.portfolio_max_drawdown);
                if (benchmarkPerformance && result.benchmark_performance !== null) {
                    const benchmarkProfit = Number(result.benchmark_profit || 0);
                    benchmarkPerformance.textContent = `S&P 500: ${Number(result.benchmark_final_capital).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} · ${benchmarkProfit >= 0 ? '+' : ''}${benchmarkProfit.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })} · ${result.benchmark_performance >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %`;
                }
                if (target._akChart) await target._akChart.destroy();
                const isLightTheme = document.documentElement.dataset.theme === 'light';
                const chartLabelColor = isLightTheme ? '#475569' : '#94a3b8';
                const chartGridColor = isLightTheme ? 'rgba(14, 116, 144, .14)' : 'rgba(148,163,184,.10)';
                const benchmarkColor = isLightTheme ? '#b45309' : '#f59e0b';
                const selectedOptions = result.selected_backtest_options || {};
                const comparisonSeries = [{ name: 'Strategie', data: result.strategy_chart }];
                const comparisonColors = ['#22d3ee'];
                const comparisonWidths = [3.5];
                const comparisonDashes = [0];
                if (selectedOptions.automatic || selectedOptions.entry_strategy === 'forecast_score_rotation_5d') {
                    comparisonSeries.push({ name: 'Forecast-Score-Einstieg (5T)', data: result.forecast_score_rotation_chart || [] });
                    comparisonColors.push('#fbbf24'); comparisonWidths.push(3); comparisonDashes.push(2);
                }
                if (selectedOptions.automatic || selectedOptions.sector_rotation) {
                    comparisonSeries.push({ name: 'Sektorrotation', data: result.sector_entry_rotation_chart || [] });
                    comparisonColors.push('#a78bfa'); comparisonWidths.push(2.5); comparisonDashes.push(4);
                }
                if (selectedOptions.automatic || selectedOptions.index_rotation) {
                    comparisonSeries.push({ name: 'Indexrotation', data: result.index_entry_rotation_chart || [] });
                    comparisonColors.push('#f472b6'); comparisonWidths.push(2.5); comparisonDashes.push(6);
                }
                if (selectedOptions.automatic || selectedOptions.exit_strategy === 'buy_and_hold') {
                    comparisonSeries.push({ name: 'Buy and Hold', data: result.buy_and_hold_chart || [] });
                    comparisonColors.push('#818cf8'); comparisonWidths.push(2.25); comparisonDashes.push(5);
                }
                if (selectedOptions.automatic) {
                    const automaticLabels = {
                        auto_exit_fixed_20d: 'Exit 20T', auto_exit_dynamic_horizon: 'Dynamischer Horizont',
                        auto_exit_support_stop: 'Support-Stop', auto_exit_resistance_trailing: 'Resistance-Trailing',
                        auto_exit_signal_change: 'Signalwechsel', auto_exit_forecast_below_price: 'Prognose unter Kurs', auto_entry_wait_5d: 'WAIT-Einstieg 5T',
                    };
                    const automaticColors = ['#2dd4bf', '#60a5fa', '#fb923c', '#e879f9', '#f87171', '#84cc16'];
                    Object.entries(result.automatic_exit_variants || {}).forEach(([key, variant], index) => {
                        comparisonSeries.push({ name: automaticLabels[key] || key, data: variant.chart || [] });
                        comparisonColors.push(automaticColors[index % automaticColors.length]);
                        comparisonWidths.push(2); comparisonDashes.push(4 + index);
                    });
                }
                comparisonSeries.push(
                    { name: `S&P 500 Buy & Hold (${Number(result.benchmark_performance) >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %)`, data: result.benchmark_chart },
                    { name: `DAX (${Number(result.dax_performance) >= 0 ? '+' : ''}${Number(result.dax_performance || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %)`, data: result.dax_chart || [] },
                );
                comparisonColors.push(benchmarkColor, '#fb7185');
                comparisonWidths.push(2.5, 2.5);
                comparisonDashes.push(8, 3);
                const chart = new window.ApexCharts(target, {
                    chart: { type: 'line', height: 285, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, fontFamily: 'inherit', foreColor: chartLabelColor },
                    @if ($qualitySetupMode)
                    series: [
                        { name: '{{ __('Smart Selection') }}', data: result.strategy_chart },
                        { name: `S&P 500 (${Number(result.benchmark_performance) >= 0 ? '+' : ''}${Number(result.benchmark_performance).toLocaleString('de-DE', { maximumFractionDigits: 2 })} %)`, data: result.benchmark_chart },
                    ],
                    colors: ['#0891b2', benchmarkColor],
                    stroke: { width: [3, 3], curve: 'straight', dashArray: [0, 4] },
                    @else
                    series: comparisonSeries,
                    colors: comparisonColors,
                    stroke: { width: comparisonWidths, curve: 'straight', dashArray: comparisonDashes, lineCap: 'round' },
                    @endif
                    xaxis: { type: 'datetime', min: result.period_start, max: result.period_end, tickAmount: 6, labels: { datetimeUTC: false, format: 'MMM yyyy', hideOverlappingLabels: true, style: { colors: chartLabelColor, fontSize: '10px', fontWeight: 600 } }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                    yaxis: { tickAmount: 5, forceNiceScale: true, labels: { minWidth: 54, formatter: value => `${value >= 0 ? '+' : ''}${value.toLocaleString('de-DE', { maximumFractionDigits: 0 })} %`, style: { colors: chartLabelColor, fontSize: '10px', fontWeight: 600 } } },
                    grid: { borderColor: chartGridColor, strokeDashArray: 3, padding: { top: 4, right: 12, bottom: 0, left: 4 }, xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
                    annotations: { yaxis: [{ y: 0, borderColor: isLightTheme ? 'rgba(15,118,110,.35)' : 'rgba(94,234,212,.28)', strokeDashArray: 0 }] },
                    legend: { position: 'top', horizontalAlign: 'left', offsetY: -2, fontSize: '11px', fontWeight: 700, itemMargin: { horizontal: 12, vertical: 4 }, labels: { colors: isLightTheme ? '#334155' : '#cbd5e1' }, markers: { size: 5, strokeWidth: 0 }, onItemHover: { highlightDataSeries: true } },
                    markers: { size: 0, hover: { size: 5, sizeOffset: 2 } },
                    tooltip: { theme: isLightTheme ? 'light' : 'dark', x: { format: 'dd.MM.yyyy' }, y: { formatter: value => `${value >= 0 ? '+' : ''}${value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %` } },
                    dataLabels: { enabled: false },
                    noData: { text: '{{ __('Keine Vergleichsdaten verfügbar') }}', style: { color: chartLabelColor } },
                });
                target._akChart = chart;
                await chart.render();
                @endif
            });
        </script>
    @endif

    <style>
        @media (max-width: 767px) {
            .ak-correlation-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .ak-correlation-grid > :first-child { grid-column: 1 / -1; }
        }
        /* A short cross-fade keeps the existing tester visible while the
           server-rendered filter result replaces it. */
        @view-transition {
            navigation: auto;
        }

        ::view-transition-old(root),
        ::view-transition-new(root) {
            mix-blend-mode: normal;
        }

        ::view-transition-old(root) {
            animation: ak-filter-fade-out 180ms ease-out both;
        }

        ::view-transition-new(root) {
            animation: ak-filter-fade-in 220ms ease-in both;
        }

        @keyframes ak-filter-fade-out { from { opacity: 1; } to { opacity: .82; } }
        @keyframes ak-filter-fade-in { from { opacity: .82; } to { opacity: 1; } }

        .ak-filter-loading {
            background: rgb(2 12 23 / 22%);
            backdrop-filter: blur(1px);
        }

        .ak-filter-section-heading {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 8px;
            padding: 3px 2px 1px;
        }

        .ak-filter-section-heading strong {
            flex: 0 0 auto;
            color: #67e8f9;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .12em;
            line-height: 1;
            text-transform: uppercase;
        }

        .ak-filter-section-heading span {
            overflow: hidden;
            color: var(--ak-muted);
            font-size: 8px;
            font-weight: 650;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #prediction-heatmap-filters .ak-position-factor input:checked + span {
            background: rgba(34, 211, 238, .22) !important;
            color: #67e8f9 !important;
            -webkit-text-fill-color: #67e8f9 !important;
            box-shadow: inset 0 0 0 1px rgba(34, 211, 238, .32), 0 1px 3px rgba(14, 116, 144, .18);
        }

        #prediction-heatmap-filters .ak-input {
            border-radius: 5px !important;
            color: #ecfeff !important;
            -webkit-text-fill-color: #ecfeff !important;
        }

        #fundamental-heatmap-filters .ak-input {
            border-radius: 5px !important;
            color: #ecfeff !important;
            -webkit-text-fill-color: #ecfeff !important;
            background-color: #102b35 !important;
        }

        .ak-backtest-spinner {
            width: 13px;
            height: 13px;
            flex: 0 0 auto;
            border: 2px solid rgba(34, 211, 238, .18);
            border-top-color: rgba(34, 211, 238, .95);
            border-right-color: rgba(34, 211, 238, .92);
            border-radius: 999px;
            animation: ak-backtest-spin .9s linear infinite;
        }
        .ak-backtest-dots { display: inline-flex; align-items: center; gap: 3px; height: 12px; }
        .ak-backtest-dots i {
            width: 3px;
            height: 3px;
            border-radius: 999px;
            background: rgba(34, 211, 238, .92);
            animation: ak-backtest-dot 1.2s ease-in-out infinite;
        }
        .ak-backtest-dots i:nth-child(2) { animation-delay: .16s; }
        .ak-backtest-dots i:nth-child(3) { animation-delay: .32s; }
        .ak-backtest-progress {
            position: absolute;
            right: 12px;
            bottom: 5px;
            left: 12px;
            height: 6px;
            overflow: hidden;
            border: 1px solid rgba(34, 211, 238, .18);
            border-radius: 999px;
            background: rgba(15, 23, 42, .62);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, .35);
        }
        .ak-backtest-progress span {
            display: block;
            width: 34%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(34, 211, 238, .95), rgba(34, 211, 238, 1), transparent);
            box-shadow: 0 0 8px rgba(34, 211, 238, .32);
            animation: ak-backtest-progress 1.8s ease-in-out infinite;
        }
        .ak-backtest-progress span.is-determinate {
            transform: none;
            animation: none;
            background: linear-gradient(90deg, rgba(34, 211, 238, .82), rgba(34, 211, 238, .9));
            transition: width .35s ease;
        }
        @keyframes ak-backtest-spin { to { transform: rotate(360deg); } }
        .ak-smart-calc-orbit { position: relative; width: 48px; height: 48px; border: 2px solid rgb(245 158 11 / 25%); border-radius: 999px; animation: ak-backtest-spin 1.8s linear infinite; }
        .ak-smart-calc-orbit::before, .ak-smart-calc-orbit::after { content: ''; position: absolute; inset: 7px; border: 2px solid transparent; border-top-color: #fbbf24; border-right-color: #22d3ee; border-radius: 999px; }
        .ak-smart-calc-orbit::after { inset: 15px; border-top-color: #22d3ee; border-right-color: transparent; animation: ak-backtest-spin .9s linear reverse infinite; }
        .ak-smart-calc-orbit span, .ak-smart-calc-orbit i, .ak-smart-calc-orbit b { position: absolute; width: 5px; height: 5px; border-radius: 999px; background: #fbbf24; box-shadow: 0 0 12px rgb(251 191 36 / 80%); }
        .ak-smart-calc-orbit span { top: -3px; left: 20px; }
        .ak-smart-calc-orbit i { right: -3px; top: 20px; background: #22d3ee; box-shadow: 0 0 12px rgb(34 211 238 / 80%); }
        .ak-smart-calc-orbit b { bottom: -3px; left: 20px; background: #22d3ee; box-shadow: 0 0 12px rgb(45 212 191 / 80%); }
        @keyframes ak-backtest-dot {
            0%, 65%, 100% { opacity: .25; transform: translateY(0); }
            32% { opacity: 1; transform: translateY(-2px); }
        }
        @keyframes ak-backtest-progress {
            from { transform: translateX(-110%); }
            to { transform: translateX(310%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ak-backtest-spinner, .ak-backtest-dots i, .ak-backtest-progress span { animation-duration: 3.5s; }
        }

        #fundamental-heatmap-filters {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(175px, 1fr)) !important;
            gap: 4px;
        }

        #fundamental-heatmap-filters > input[type="hidden"] {
            display: none;
        }

        #fundamental-heatmap-filters .ak-input::placeholder {
            color: #a5f3fc !important;
            opacity: 1;
        }

        #fundamental-heatmap-filters .ak-fundamental-range {
            display: grid;
            min-width: 0;
            height: 44px;
            grid-template-rows: 17px 1fr;
            align-items: center;
            border: 1px solid var(--ak-border);
            border-radius: 5px;
            padding: 5px 9px 6px;
            background: #102b35;
        }

        #fundamental-heatmap-filters .ak-fundamental-range span {
            overflow: hidden;
            color: #a5f3fc;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #fundamental-heatmap-filters .ak-fundamental-range b {
            color: #67e8f9;
            font-weight: 900;
        }

        #fundamental-heatmap-filters .ak-fundamental-range input {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            height: 16px;
            margin: 0;
            cursor: pointer;
            background: transparent;
        }


        #prediction-heatmap-filters select.ak-input {
            appearance: none;
            -webkit-appearance: none;
            padding: 0 13px 0 5px !important;
            background-color: #102b35 !important;
            background-image:
                linear-gradient(45deg, transparent 50%, #67e8f9 50%),
                linear-gradient(135deg, #67e8f9 50%, transparent 50%) !important;
            background-position: calc(100% - 7px) 50%, calc(100% - 4px) 50% !important;
            background-size: 3px 3px, 3px 3px !important;
            background-repeat: no-repeat !important;
        }

        :root:not([data-theme="light"]) #prediction-heatmap-filters select.ak-input {
            color-scheme: dark;
        }

        #prediction-heatmap-filters .ak-input::placeholder {
            color: #ecfeff !important;
            opacity: 1;
        }

        #prediction-heatmap-filters .ak-filter-checkbox {
            appearance: auto !important;
            -webkit-appearance: checkbox !important;
            flex: 0 0 auto;
            cursor: pointer;
            opacity: 1 !important;
            pointer-events: auto !important;
            accent-color: #06b6d4;
        }

        #prediction-heatmap-filters input.ak-input:not([type="range"]):not([type="hidden"]) {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 30px !important;
        }

        #prediction-heatmap-filters .ak-heatmap-range {
            display: grid;
            min-width: 0;
            height: 32px;
            grid-template-rows: 13px 1fr;
            align-items: center;
            border: 1px solid var(--ak-border);
            border-radius: 5px;
            padding: 2px 7px 3px;
            background: #102b35;
        }

        #prediction-heatmap-filters .ak-heatmap-range span {
            overflow: hidden;
            color: #a5f3fc;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #prediction-heatmap-filters .ak-heatmap-range b {
            color: #67e8f9;
            font-weight: 900;
        }

        #prediction-heatmap-filters .ak-heatmap-range input {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            height: 16px;
            margin: 0;
            cursor: pointer;
            background: transparent;
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-runnable-track,
        #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-runnable-track {
            height: 6px;
            border-radius: 999px;
            background: #164e63;
            box-shadow: inset 0 0 0 1px rgb(103 232 249 / 18%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-thumb,
        #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-thumb {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            margin-top: -5px;
            border: 2px solid #cffafe;
            border-radius: 999px;
            background: #06b6d4;
            box-shadow: 0 0 0 2px rgb(6 182 212 / 18%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-track,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-track {
            height: 6px;
            border-radius: 999px;
            background: #164e63;
            box-shadow: inset 0 0 0 1px rgb(103 232 249 / 18%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-progress,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-progress {
            height: 4px;
            border-radius: 999px;
            background: #06b6d4;
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-thumb,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-thumb {
            width: 10px;
            height: 10px;
            border: 2px solid #cffafe;
            border-radius: 999px;
            background: #06b6d4;
            box-shadow: 0 0 0 2px rgb(6 182 212 / 18%);
        }

        #prediction-heatmap-filters .ak-heatmap-range:has(input[name="volatility_max"]) b {
            color: #67e8f9;
        }

        #prediction-heatmap-filters .ak-heatmap-range input[name="volatility_max"] {
            accent-color: #06b6d4;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            background-color: #ecfeff !important;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range {
            background: #ecfeff;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range span {
            color: #155e75;
        }

        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range b,
        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range b,
        :root[data-theme="light"] .ak-strategy-page .ak-strategy-stat-value,
        :root[data-theme="light"] .ak-strategy-page .ak-filter-count-value,
        :root[data-theme="light"] .ak-strategy-page .ak-filter-result-count {
            color: #0e7490 !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters select.ak-input {
            appearance: auto !important;
            -webkit-appearance: auto !important;
            padding-right: 6px !important;
            background-color: #ecfeff !important;
            background-image: none !important;
            color: #0f172a !important;
            -webkit-text-fill-color: unset !important;
            color-scheme: light;
            opacity: 1;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-quality-tier-select {
            color: #0f172a !important;
            -webkit-text-fill-color: unset !important;
            color-scheme: light;
            font-weight: 700;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-menu {
            border-color: rgb(15 118 110 / 24%) !important;
            background: rgb(255 255 255 / 99%) !important;
            box-shadow: 0 16px 38px rgb(15 23 42 / 16%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-option {
            color: #1e293b;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-menu :is(span, b, small) {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            opacity: 1 !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-menu small {
            color: #64748b !important;
            -webkit-text-fill-color: #64748b !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-option:hover {
            background: #ecfeff !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-option :is(.text-slate-100, .text-slate-200) {
            color: #1e293b !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-option .text-slate-400 {
            color: #64748b !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-dropdown-option input[type="checkbox"] {
            border-color: #94a3b8;
            background-color: #ffffff;
            color: #0891b2;
            accent-color: #0891b2;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-reset {
            border-color: rgb(8 145 178 / 32%) !important;
            background: #ffffff;
            color: #0e7490 !important;
            box-shadow: 0 3px 10px rgb(15 23 42 / 7%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-filter-reset:hover {
            border-color: #0891b2 !important;
            background: #ecfeff;
            color: #155e75 !important;
            box-shadow: 0 5px 14px rgb(8 145 178 / 14%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range {
            background: #ecfeff;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range span {
            color: #155e75;
        }

        :root[data-theme="light"] .ak-strategy-page .ak-heatmap-cell {
            color: #374151 !important;
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-runnable-track,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-runnable-track,
        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-track,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-track {
            background: #bae6e8;
            box-shadow: inset 0 0 0 1px rgb(14 116 144 / 14%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-thumb,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-thumb {
            width: 6px;
            height: 18px;
            margin-top: -7px;
            border: 1px solid #ffffff;
            border-radius: 2px;
            background: #d97706;
            box-shadow: 0 0 0 2px rgb(217 119 6 / 25%), 0 2px 5px rgb(15 23 42 / 28%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-thumb,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-thumb {
            width: 6px;
            height: 18px;
            border: 1px solid #ffffff;
            border-radius: 2px;
            background: #d97706;
            box-shadow: 0 0 0 2px rgb(217 119 6 / 25%), 0 2px 5px rgb(15 23 42 / 28%);
        }

        /* Semantische Ampelskala: schwach -> neutral -> stark. */
        #prediction-heatmap-filters input[type="range"],
        #fundamental-heatmap-filters input[type="range"] {
            --ak-range-start: #ef4444;
            --ak-range-middle: #facc15;
            --ak-range-end: #22c55e;
        }

        /* Bei Obergrenzen ist ein kleiner Wert besser: Grün -> Gelb -> Rot. */
        #prediction-heatmap-filters input[name="drawdown_max"],
        #prediction-heatmap-filters input[name="volatility_max"],
        #fundamental-heatmap-filters input[name="pe_max"],
        #fundamental-heatmap-filters .ak-fundamental-range:has(select[name="dividend_yield_operator"] option[value="lte"]:checked) input[name="dividend_yield_min"] {
            --ak-range-start: #22c55e;
            --ak-range-middle: #facc15;
            --ak-range-end: #ef4444;
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-runnable-track,
        #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-runnable-track {
            background: linear-gradient(90deg, var(--ak-range-start) 0%, var(--ak-range-middle) 50%, var(--ak-range-end) 100%);
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 24%), 0 1px 4px rgb(15 23 42 / 22%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-track,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-track,
        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-progress,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-progress {
            background: linear-gradient(90deg, var(--ak-range-start) 0%, var(--ak-range-middle) 50%, var(--ak-range-end) 100%);
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 24%), 0 1px 4px rgb(15 23 42 / 22%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-thumb,
        #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-thumb {
            width: 6px;
            height: 18px;
            margin-top: -7px;
            border-radius: 2px;
            border-color: #ffffff;
            background: #0f172a;
            box-shadow: 0 0 0 2px rgb(255 255 255 / 72%), 0 2px 6px rgb(15 23 42 / 38%);
        }

        #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-thumb,
        #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-thumb {
            width: 6px;
            height: 18px;
            border-radius: 2px;
            border-color: #ffffff;
            background: #0f172a;
            box-shadow: 0 0 0 2px rgb(255 255 255 / 72%), 0 2px 6px rgb(15 23 42 / 38%);
        }

        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-webkit-slider-runnable-track,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-webkit-slider-runnable-track,
        :root[data-theme="light"] #prediction-heatmap-filters .ak-heatmap-range input::-moz-range-track,
        :root[data-theme="light"] #fundamental-heatmap-filters .ak-fundamental-range input::-moz-range-track {
            background: linear-gradient(90deg, var(--ak-range-start) 0%, var(--ak-range-middle) 50%, var(--ak-range-end) 100%);
            box-shadow: inset 0 0 0 1px rgb(15 23 42 / 12%), 0 1px 3px rgb(15 23 42 / 18%);
        }

        /* Backtest dialogs follow the active theme without losing chart contrast. */
        :root[data-theme="light"] .ak-backtest-config-dialog,
        :root[data-theme="light"] .ak-backtest-result-dialog {
            color: #1e293b;
            border-color: rgb(8 145 178 / 28%) !important;
            background: linear-gradient(145deg, rgb(255 255 255 / 98%), rgb(240 253 250 / 98%)) !important;
            box-shadow: 0 24px 70px rgb(15 23 42 / 20%), inset 0 1px 0 #ffffff;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .text-white,
        :root[data-theme="light"] .ak-backtest-result-dialog .text-white {
            color: #0f172a !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .text-slate-300,
        :root[data-theme="light"] .ak-backtest-result-dialog .text-slate-300 {
            color: #475569 !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .text-slate-400,
        :root[data-theme="light"] .ak-backtest-result-dialog .text-slate-400 {
            color: #64748b !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .text-amber-200,
        :root[data-theme="light"] .ak-backtest-config-dialog .text-amber-300,
        :root[data-theme="light"] .ak-backtest-result-dialog .text-amber-200,
        :root[data-theme="light"] .ak-backtest-result-dialog .text-amber-300 {
            color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .ak-input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            border-color: rgb(15 118 110 / 22%) !important;
            background-color: #ffffff !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .ak-backtest-submit-action {
            border-color: #0e7490 !important;
            background-color: #0891b2 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: 0 6px 16px rgb(8 145 178 / 20%);
        }

        :root[data-theme="light"] .ak-backtest-config-dialog .ak-backtest-submit-action:hover {
            background-color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-config-dialog > div.rounded-xl,
        :root[data-theme="light"] .ak-backtest-result-dialog > .grid > div {
            border-color: rgb(15 118 110 / 14%) !important;
            background-color: rgb(255 255 255 / 72%) !important;
        }

        :root[data-theme="light"] .ak-backtest-result-dialog .ak-backtest-report-link {
            border-color: rgb(8 145 178 / 32%) !important;
            background-color: rgb(8 145 178 / 10%) !important;
            color: #0e7490 !important;
            -webkit-text-fill-color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-result-dialog #filtered-backtest-result-chart {
            border-radius: 12px;
            background: rgb(255 255 255 / 58%);
        }

        :root[data-theme="light"] .ak-backtest-strip {
            border-color: rgb(8 145 178 / 30%) !important;
            background: linear-gradient(90deg, rgb(236 254 255 / 96%), rgb(240 253 250 / 96%)) !important;
            box-shadow: inset 3px 0 0 #0891b2, 0 6px 18px rgb(15 118 110 / 7%);
        }

        :root[data-theme="light"] .ak-backtest-strip > div:first-child > div:first-child > p {
            color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-description {
            color: #334155 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip #ak-backtest-status-count {
            color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-primary-action {
            border-color: #0e7490 !important;
            background-color: #0891b2 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: 0 5px 14px rgb(8 145 178 / 20%);
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-primary-action:hover {
            border-color: #155e75 !important;
            background-color: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-save-action {
            border-color: #0e7490 !important;
            background: #0891b2 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: 0 5px 14px rgb(8 145 178 / 20%);
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-save-action:hover {
            background: #0e7490 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-smart-action {
            border-color: #0e7490 !important;
            background: #0e7490 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: 0 5px 14px rgb(15 118 110 / 18%);
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-smart-action:hover {
            background: #155e75 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-smart-disabled-action {
            border-color: rgb(71 85 105 / 30%) !important;
            background: #e2e8f0 !important;
            color: #475569 !important;
            -webkit-text-fill-color: #475569 !important;
            opacity: 1 !important;
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-recalculate-action {
            border-color: rgb(71 85 105 / 32%) !important;
            background: #ffffff !important;
            color: #334155 !important;
            -webkit-text-fill-color: #334155 !important;
            box-shadow: 0 3px 10px rgb(15 23 42 / 8%);
        }

        :root[data-theme="light"] .ak-backtest-strip .ak-backtest-recalculate-action:hover {
            border-color: #0891b2 !important;
            background: #ecfeff !important;
            color: #0e7490 !important;
            -webkit-text-fill-color: #0e7490 !important;
        }

        /* Smart Selection uses the same translucent card treatment as the dashboard. */
        .ak-strategy-page .ak-dashboard-card {
            background: linear-gradient(145deg, rgba(15, 32, 51, .96), rgba(10, 24, 41, .94)) !important;
            border-color: color-mix(in srgb, #fb923c 32%, transparent) !important;
            box-shadow: 0 12px 30px rgba(194, 65, 12, .10), inset 0 1px 0 rgba(251, 146, 60, .045) !important;
            backdrop-filter: blur(8px);
        }

        :root[data-theme="light"] .ak-strategy-page .ak-dashboard-card {
            background: rgba(255, 255, 255, .62) !important;
            border-color: #d9e7e4 !important;
            box-shadow: 0 8px 22px rgba(35, 72, 67, .065), inset 3px 0 0 rgba(6, 182, 212, .48) !important;
        }

        :root:not([data-theme="light"]) body:not(.welcome-background) .ak-strategy-page .ak-dashboard-card {
            --smart-card-bg: linear-gradient(145deg, rgba(15, 32, 51, .96), rgba(10, 24, 41, .94));
            background: linear-gradient(145deg, rgba(15, 32, 51, .96), rgba(10, 24, 41, .94)) !important;
        }

        :root[data-theme="light"] body:not(.welcome-background) .ak-strategy-page .ak-dashboard-card {
            --smart-card-bg: rgba(255, 255, 255, .62);
            background: rgba(255, 255, 255, .62) !important;
        }

        /* Light mode: crisp dashboard cards with readable contrast. */
        :root[data-theme="light"] .ak-strategy-page {
            --ak-text: #1e293b;
            --ak-muted: #64748b;
            --ak-card: #ffffff;
            --ak-card-strong: #ffffff;
            --ak-surface-muted: #f1f5f9;
            --ak-border: #cbd5e1;
            color: #1e293b;
        }
        :root[data-theme="light"] .ak-strategy-page .ak-dashboard-card {
            background: linear-gradient(145deg, rgba(255,255,255,.98), rgba(240,253,250,.92)) !important;
            border-color: #b8d8d5 !important;
            box-shadow: 0 10px 26px rgba(14, 116, 144, .10), inset 3px 0 0 rgba(6, 182, 212,.55) !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-white,
        :root[data-theme="light"] .ak-strategy-page .text-slate-100,
        :root[data-theme="light"] .ak-strategy-page .text-slate-200,
        :root[data-theme="light"] .ak-strategy-page .text-slate-300 {
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-slate-400,
        :root[data-theme="light"] .ak-strategy-page .text-slate-500 {
            color: #64748b !important;
            -webkit-text-fill-color: #64748b !important;
        }
        :root[data-theme="light"] .ak-strategy-page [class*="border-white"] {
            border-color: #d7e3e5 !important;
        }
        :root[data-theme="light"] .ak-strategy-page [class*="bg-white"] {
            background-color: #f8fafc !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-amber-200,
        :root[data-theme="light"] .ak-strategy-page .text-amber-300 {
            color: #b45309 !important;
            -webkit-text-fill-color: #b45309 !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-emerald-300 {
            color: #047857 !important;
            -webkit-text-fill-color: #047857 !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-cyan-300,
        :root[data-theme="light"] .ak-strategy-page .text-teal-300 {
            color: #0e7490 !important;
            -webkit-text-fill-color: #0e7490 !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-orange-300 {
            color: #c2410c !important;
            -webkit-text-fill-color: #c2410c !important;
        }
        :root[data-theme="light"] .ak-strategy-page .text-rose-300 {
            color: #e11d48 !important;
            -webkit-text-fill-color: #e11d48 !important;
        }
        :root[data-theme="light"] .ak-strategy-page .ak-prediction-filterboard {
            background: rgba(255,255,255,.94) !important;
            border-color: #b8d8d5 !important;
            box-shadow: 0 8px 22px rgba(14, 116, 144,.08) !important;
        }

        /* Compact strategy-filter accordion: closed groups share one header row. */
        .ak-filter-group-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .ak-filter-group-grid > .ak-filter-group {
            min-width: 0;
        }

        .ak-filter-group-grid > .ak-filter-group:not([open]) > summary small {
            display: none;
        }

        .ak-filter-group-grid > .ak-filter-group:not([open]) > summary {
            min-height: 44px;
            padding-top: .45rem;
            padding-bottom: .45rem;
        }

        @media (min-width: 1024px) {
            .ak-filter-group-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .ak-filter-group-grid:has(> .ak-filter-group[open]) {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ak-filter-group-grid > .ak-filter-group:not([open]) {
                order: 1;
            }

            .ak-filter-group-grid > .ak-filter-group[open] {
                order: 2;
                grid-column: 1 / -1;
            }
        }

        /* Keep the strategy tester on the shared dashboard canvas.  These
           declarations intentionally come last because older theme rules
           above assigned opaque card fills again. */
        .ak-strategy-page :is(
            .ak-prediction-filterboard,
            .ak-dashboard-card,
            .ak-backtest-strip,
            .ak-correlation-grid,
            .ak-heatmap-metric-grid > :is(article, details),
            .ak-filter-group
        ) {
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            backdrop-filter: none !important;
        }

        :root[data-theme="light"] .ak-strategy-page :is(
            .ak-prediction-filterboard,
            .ak-dashboard-card,
            .ak-backtest-strip,
            .ak-correlation-grid,
            .ak-heatmap-metric-grid > :is(article, details),
            .ak-filter-group
        ) {
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
        }

        @media (max-width: 768px) {
            .ak-strategy-header {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: .85rem !important;
            }

            .ak-strategy-heading {
                display: grid !important;
                grid-template-columns: 3.5rem minmax(0, 1fr);
                align-items: center !important;
                gap: .75rem !important;
            }

            .ak-strategy-heading-icon {
                grid-column: 1;
                grid-row: 1 / 3;
            }

            .ak-strategy-heading-copy {
                display: contents;
            }

            .ak-strategy-heading-copy > :is(p:first-child, h1) {
                grid-column: 2;
            }

            .ak-strategy-heading-copy > p:first-child {
                align-self: end;
            }

            .ak-strategy-heading-copy > h1 {
                align-self: start;
            }

            .ak-strategy-heading-description {
                grid-column: 1 / -1;
                width: 100%;
                margin-top: .2rem !important;
                line-height: 1.55;
            }

            .ak-strategy-header-actions,
            .ak-strategy-header-actions > button {
                width: 100%;
            }

            .ak-strategy-header-actions > button {
                min-height: 3rem;
                justify-content: center;
            }
        }
    </style>
</x-app-layout>
