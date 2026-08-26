<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300"><x-heroicon-o-shield-check class="h-6 w-6" /></div>
                <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-400">{{ __('Strategie · Pro') }}</p><h1 class="text-2xl font-black">{{ __('Eigenes Quality Gate') }}</h1><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Definiere, welche Mindestqualität eine Aktie für deine Signale erfüllen muss.') }}</p></div>
            </div>
            <a href="{{ route('setup.filter') }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400"><x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück zur Strategie') }}</a>
        </header>

        @if(session('status'))<div class="mb-3 shrink-0 rounded-xl border border-teal-400/25 bg-teal-400/10 px-4 py-2 text-xs font-bold text-teal-300">{{ session('status') }}</div>@endif

        @if(!$canConfigure)
            <section class="ak-card flex min-h-0 flex-1 items-center justify-center p-8 text-center"><div class="max-w-xl"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/10 text-amber-300"><x-heroicon-o-lock-closed class="h-8 w-8" /></div><h2 class="mt-5 text-2xl font-black">{{ __('Eigenes Quality Gate ist eine Pro-Funktion') }}</h2><p class="mt-3 text-sm leading-6 text-[var(--ak-muted)]">{{ __('Mit Pro kannst du Modellqualität, Score, Konfidenz, Risiko und Backtest-Kennzahlen zu deinem persönlichen Freigabeverfahren kombinieren.') }}</p><a href="{{ route('pricing') }}" class="mt-6 inline-flex h-11 items-center rounded-lg bg-gradient-to-r from-teal-600 to-orange-400 px-5 text-xs font-black text-white">{{ __('Pro ansehen') }}</a></div></section>
        @else
            <form method="POST" action="{{ route('setup.quality-gate.update') }}" class="ak-card flex min-h-0 flex-1 flex-col p-4">@csrf @method('PUT')
                <div class="grid shrink-0 gap-3 lg:grid-cols-[1.4fr_.6fr]">
                    <label class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3"><span class="text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Name') }}</span><input name="name" value="{{ old('name', $profile?->name ?? __('Mein Quality Gate')) }}" class="ak-input mt-2 h-9 w-full rounded-md px-3 text-sm" required></label>
                    <label class="flex items-center justify-between rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3"><span><b class="block text-sm">{{ __('Quality Gate aktiv') }}</b><small class="text-[10px] text-[var(--ak-muted)]">{{ __('Auf persönliche Signale anwenden') }}</small></span><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $profile?->is_active ?? true)) class="h-5 w-5 rounded border-slate-600 bg-transparent text-teal-500 focus:ring-teal-500"></label>
                </div>

                <div class="mt-3 grid min-h-0 flex-1 grid-cols-2 gap-3 lg:grid-cols-3">
                    @php($fields = [
                        ['score_min', __('KI-Score mindestens'), 0, 10, .1, '/10'], ['confidence_min', __('Model Qualität mindestens'), 0, 100, 1, '%'], ['risk_max', __('Risiko maximal'), 0, 100, 1, '%'],
                        ['predicted_return_min', __('Prognose 20 Tage mindestens'), .5, 10, .5, '%'], ['drawdown_max', __('Drawdown maximal'), 0, 100, 1, '%'], ['profit_factor_min', __('Profitfaktor mindestens'), 0, 10, .05, ''],
                        ['hit_rate_min', __('Hitrate mindestens'), 0, 100, 1, '%'], ['minimum_trades', __('Backtest-Trades mindestens'), 1, 10000, 1, ''],
                    ])
                    @foreach($fields as [$key,$label,$min,$max,$step,$unit])
                        <label class="flex min-h-0 flex-col justify-between rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3"><span class="text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</span><div class="mt-2 flex items-center gap-2"><input type="range" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ old($key, $rules[$key]) }}" class="min-w-0 flex-1 accent-orange-4000" oninput="this.nextElementSibling.firstElementChild.textContent=this.value"><span class="w-16 text-right text-sm font-black text-orange-4000"><b>{{ old($key, $rules[$key]) }}</b>{{ $unit }}</span></div></label>
                    @endforeach
                    <label class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3"><span class="text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modellstufe mindestens') }}</span><select name="minimum_tier" class="ak-input mt-2 h-9 w-full rounded-md px-2 text-xs">@foreach(['test'=>__('Start'),'solid'=>__('Basis'),'strong'=>__('Stark'),'top'=>__('Quality Gate')] as $value=>$label)<option value="{{ $value }}" @selected(old('minimum_tier',$rules['minimum_tier'])===$value)>{{ $label }}</option>@endforeach</select></label>
                    @foreach([['positive_prediction_required',__('Positive Prognose erforderlich')],['ensemble_veto_required',__('Ensemble-Veto muss OK sein')]] as [$key,$label])<label class="flex items-center justify-between rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3"><span class="text-xs font-bold">{{ $label }}</span><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$rules[$key])) class="h-5 w-5 rounded border-slate-600 bg-transparent text-teal-500 focus:ring-teal-500"></label>@endforeach
                </div>
                @if($errors->any())<div class="mt-3 text-xs font-bold text-rose-400">{{ $errors->first() }}</div>@endif
                <div class="mt-3 flex shrink-0 items-center justify-between gap-4 border-t border-[var(--ak-border)] pt-3"><p class="max-w-2xl text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Das persönliche Gate verändert keine Modellberechnung. Es entscheidet, welche vorhandenen Prognosen für dich freigegeben, angezeigt oder per E-Mail gemeldet werden.') }}</p><div class="flex shrink-0 gap-2"><button type="submit" formaction="{{ route('setup.quality-gate.backtest') }}" formmethod="POST" class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300/25 bg-amber-300/10 px-4 text-xs font-black text-amber-300 hover:bg-amber-300/15"><x-heroicon-o-beaker class="h-4 w-4" />{{ __('Mit diesen Regeln backtesten') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-orange-400 px-5 text-xs font-black text-white shadow-lg shadow-teal-950/20">{{ __('Quality Gate speichern') }}</button></div></div>
            </form>
        @endif
    </div>
</x-app-layout>
