<x-app-layout>
    @php
        $countryFlag = fn (?string $country): string => strlen((string) $country) === 2 && function_exists('mb_chr')
            ? mb_chr(127397 + ord(strtoupper($country[0]))).mb_chr(127397 + ord(strtoupper($country[1])))
            : '🌐';
    @endphp
    <div id="opportunities-page" class="mx-auto max-w-7xl px-3 py-5 sm:px-6">
        <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-black uppercase tracking-[.18em] text-amber-400">PRO · CHANCE</p><h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Meine Handelschancen') }}</h1><p class="mt-1 max-w-2xl text-xs text-[var(--ak-muted)]">{{ __('Aktien mit kurzfristigem Rücksetzer und positivem 20-Tage-Ausblick. Keine Anlageberatung.') }}</p></div>
            <span class="rounded-xl border border-amber-400/35 bg-amber-400/10 px-3 py-2 text-xs font-black text-amber-400">{{ $opportunities->total() }} {{ __('offen oder gespeichert') }}</span>
        </header>

        @if(session('status'))<div class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ session('status') }}</div>@endif

        <div class="grid gap-3">
            @forelse($opportunities as $opportunity)
                @php $snapshot = $opportunity->snapshot ?: []; $returns = data_get($snapshot, 'returns', []); $price = data_get($snapshot, 'price'); @endphp
                <article class="overflow-hidden rounded-2xl border border-[var(--ak-border)] border-l-4 border-l-cyan-400 bg-transparent shadow-[var(--ak-shadow)]">
                    <div class="flex items-center justify-between gap-3 border-b border-amber-400/35 bg-amber-400/10 px-4 py-2.5 text-amber-400">
                        <span class="flex min-w-0 items-center gap-2 text-[11px] font-black uppercase tracking-[.16em]"><span class="text-base leading-none">{{ $countryFlag($opportunity->instrument->country) }}</span><x-heroicon-o-arrow-trending-up class="h-4 w-4 shrink-0" />{{ __('Handelschance') }}</span>
                        <span class="shrink-0 text-right"><b class="block text-xs text-[var(--ak-text)]">{{ is_numeric($price) ? number_format((float)$price, 2, ',', '.').' '.($opportunity->instrument->currency ?: '') : '—' }}</b><small class="block text-[8px] font-black uppercase tracking-wide text-amber-400">{{ __($opportunity->status) }}</small></span>
                    </div>
                    <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><h2 class="truncate text-lg font-black text-[var(--ak-text)]">{{ $opportunity->instrument->name ?: $opportunity->instrument->symbol }}</h2><p class="text-xs font-bold text-cyan-400">{{ $opportunity->instrument->symbol }} · {{ $opportunity->instrument->sector ?: '—' }}</p></div>
                        <div class="text-right text-[10px] text-[var(--ak-muted)]"><span class="block font-black uppercase">{{ __('Gültig bis') }}</span><time class="mt-1 block text-xs font-black text-amber-400">{{ $opportunity->expires_at->format('d.m.Y') }}</time></div>
                    </div>
                    <div class="mt-4 grid grid-cols-4 gap-2">
                        @foreach([5,10,15,20] as $days)@php $value = $returns[$days] ?? $returns[(string)$days] ?? null; @endphp<div class="rounded-xl border border-[var(--ak-border)] bg-transparent px-2 py-2 text-center"><small class="block text-[9px] font-black text-[var(--ak-muted)]">{{ $days }}T</small><b class="mt-1 block text-sm {{ is_numeric($value) && $value > 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ is_numeric($value) ? (($value > 0 ? '+' : '').number_format($value, 1, ',', '.').' %') : '—' }}</b></div>@endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('opportunities.open', $opportunity) }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-xl bg-cyan-600 px-4 text-xs font-black text-white"><x-heroicon-o-eye class="h-4 w-4" />{{ __('Aktie ansehen') }}</button></form>
                        <form method="POST" action="{{ route('opportunities.update', $opportunity) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-400/35 bg-emerald-400/10 px-4 text-xs font-black text-emerald-400"><x-heroicon-o-check class="h-4 w-4" />{{ __('Erledigt') }}</button></form>
                        <form method="POST" action="{{ route('opportunities.update', $opportunity) }}">@csrf @method('PATCH')<input type="hidden" name="notify_on_buy" value="{{ $opportunity->notify_on_buy ? 0 : 1 }}"><button class="inline-flex h-10 items-center gap-2 rounded-xl border border-amber-400/35 bg-amber-400/10 px-4 text-xs font-black text-amber-400"><x-heroicon-o-bell class="h-4 w-4" />{{ $opportunity->notify_on_buy ? __('BUY-Erinnerung aktiv') : __('Bei BUY erinnern') }}</button></form>
                        <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}" class="ml-auto">@csrf @method('DELETE')<button class="inline-flex h-10 items-center gap-2 rounded-xl border border-rose-400/30 px-3 text-xs font-black text-rose-400"><x-heroicon-o-trash class="h-4 w-4" />{{ __('Entfernen') }}</button></form>
                    </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[var(--ak-border)] p-10 text-center"><x-heroicon-o-sparkles class="mx-auto h-9 w-9 text-amber-400" /><h2 class="mt-3 font-black text-[var(--ak-text)]">{{ __('Aktuell keine Handelschance') }}</h2><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Neue passende Prognosen werden nach dem täglichen Modelllauf automatisch ergänzt.') }}</p></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $opportunities->links() }}</div>
    </div>
</x-app-layout>
