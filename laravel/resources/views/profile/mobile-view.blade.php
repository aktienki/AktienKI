<x-app-layout>
    @php
        $labels = [
            'strategy' => __('Depot'), 'personal' => __('Persönlicher Bereich'),
            'community' => __('Community'), 'market' => __('Aktives Portfolio'),
            'signal-cockpit' => __('Signal-Cockpit'), 'models' => __('Letzte Prognosen'),
            'signals' => __('Empfehlungen & Signalübergänge'), 'earnings' => __('Aktuelle Quartalszahlen'),
            'market-summary' => __('Aktuelle Marktlage'), 'schedule' => __('Termine & Erinnerungen'),
            'mobile-view' => __('Mobile Ansicht konfigurieren'),
        ];
        $descriptions = [
            'strategy' => __('Depotwert, Kapital, Performance und offene Positionen.'), 'personal' => __('Dein persönlicher, aufklappbarer Bereich.'),
            'community' => __('Aktivität seit deinem letzten Login.'), 'market' => __('Aktienanzahl, Risikoprofil, KI-Score-Verteilung, Trend und Stimmung.'),
            'signal-cockpit' => __('Signalwechsel der letzten fünf Handelstage.'), 'models' => __('Aktuelle Modellläufe nach Region.'),
            'signals' => __('Neue Empfehlungen und Übergänge.'), 'earnings' => __('Die letzten Quartalsergebnisse.'),
            'market-summary' => __('Aktueller Ausblick, Konfidenz und Marktrisiko.'), 'schedule' => __('Bevorstehende Termine und E-Mails.'),
            'mobile-view' => __('Direkter Zugriff auf diese Konfiguration.'),
        ];
    @endphp
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container max-w-5xl">
            <a href="{{ route('dashboard') }}" class="mb-4 inline-flex items-center gap-2 text-xs font-black text-cyan-300 hover:text-cyan-200"><x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück zum Dashboard') }}</a>
            <section class="ak-card overflow-hidden border-cyan-400/30">
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-cyan-400/20 p-5 sm:p-7">
                    <div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-device-phone-mobile class="h-6 w-6" /></span><div><p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-300">{{ __('Mobiles Dashboard') }}</p><h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Mobile Ansicht konfigurieren') }}</h1></div></div>
                    <span class="rounded-full border border-cyan-400/25 px-3 py-1 text-[10px] font-black text-cyan-300">{{ count($selectedCards) }} {{ __('Karten aktiv') }}</span>
                </header>
                <form method="POST" action="{{ route('profile.mobile-view.update') }}" class="p-5 sm:p-7">
                    @csrf @method('PATCH')
                    <p class="max-w-3xl text-sm leading-6 text-[var(--ak-muted)]">{{ __('Wähle aus, welche Dashboard-Karten auf dem Smartphone erscheinen. Die Desktop-Ansicht bleibt unverändert.') }}</p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($cards as $card)
                            <label class="group relative cursor-pointer">
                                <input type="checkbox" name="cards[]" value="{{ $card }}" class="peer sr-only" @checked(in_array($card, $selectedCards, true))>
                                <span class="flex min-h-28 items-start gap-3 rounded-xl border border-[var(--ak-border)] bg-white/[.025] p-4 transition peer-checked:border-cyan-300/70 peer-checked:bg-cyan-400/[.10] peer-focus-visible:ring-2 peer-focus-visible:ring-cyan-300">
                                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 text-cyan-300"><x-heroicon-o-check class="h-4 w-4 opacity-20 peer-checked:opacity-100" /></span>
                                    <span><b class="block text-sm text-[var(--ak-text)]">{{ $labels[$card] }}</b><small class="mt-1 block leading-5 text-[var(--ak-muted)]">{{ $descriptions[$card] }}</small></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('cards')<p class="mt-3 text-sm font-bold text-rose-300">{{ $message }}</p>@enderror
                    <footer class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ak-border)] pt-5">
                        @if(session('status'))<p class="text-sm font-bold text-emerald-300">✓ {{ session('status') }}</p>@else<span></span>@endif
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-cyan-400 px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-cyan-300"><x-heroicon-o-check class="h-4 w-4" />{{ __('Mobile Ansicht speichern') }}</button>
                    </footer>
                </form>
                <form method="POST" action="{{ route('profile.mobile-view.reset') }}" class="border-t border-[var(--ak-border)] px-5 py-4 sm:px-7">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-[var(--ak-border)] px-4 py-2.5 text-xs font-black text-[var(--ak-muted)] transition hover:border-cyan-400/40 hover:text-[var(--ak-text)]"><x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Mobile Ansicht zurücksetzen') }}</button>
                </form>
            </section>
        </div>
    </main>
</x-app-layout>
