<x-app-layout>
<style>
    :root[data-theme="light"] .securities-page .ak-stocks-table thead th {
        color: #164e63 !important;
        border-bottom-color: rgba(14, 116, 144, .32) !important;
        text-shadow: none;
    }
    .certificate-sector-donut {
        --sector-track: color-mix(in srgb, var(--ak-muted) 18%, transparent);
        position: relative;
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        place-items: center;
        border-radius: 999px;
        background:
            repeating-conic-gradient(from -90deg, transparent 0 68deg, var(--ak-card) 68deg 72deg),
            conic-gradient(from -90deg,
                var(--s1, var(--sector-track)) 0 20%,
                var(--s2, var(--sector-track)) 20% 40%,
                var(--s3, var(--sector-track)) 40% 60%,
                var(--s4, var(--sector-track)) 60% 80%,
                var(--s5, var(--sector-track)) 80% 100%);
    }
    .certificate-sector-donut::after {
        position: absolute;
        inset: 6px;
        border-radius: inherit;
        background: var(--ak-card);
        content: '';
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--ak-border) 80%, transparent);
    }
    .certificate-sector-donut > b { position: relative; z-index: 1; font-size: 12px; font-weight: 900; }
    .certificate-sector-donut[data-kind="chance"] { --c1:#fb7185; --c2:#fb923c; --c3:#facc15; --c4:#a3e635; --c5:#34d399; }
    .certificate-sector-donut[data-kind="risk"] { --c1:#34d399; --c2:#a3e635; --c3:#facc15; --c4:#fb923c; --c5:#fb7185; }
    .certificate-sector-donut[data-score="1"] { --s1:var(--c1); }
    .certificate-sector-donut[data-score="2"] { --s1:var(--c1); --s2:var(--c2); }
    .certificate-sector-donut[data-score="3"] { --s1:var(--c1); --s2:var(--c2); --s3:var(--c3); }
    .certificate-sector-donut[data-score="4"] { --s1:var(--c1); --s2:var(--c2); --s3:var(--c3); --s4:var(--c4); }
    .certificate-sector-donut[data-score="5"] { --s1:var(--c1); --s2:var(--c2); --s3:var(--c3); --s4:var(--c4); --s5:var(--c5); }
</style>
<main class="securities-page mx-auto w-full max-w-[1900px] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
    <header class="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Produkte') }}</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight sm:text-4xl">{{ $tab === 'certificates' ? __('Zertifikate') : __('ETFs & Zertifikate') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ak-muted)]">{{ $tab === 'certificates' ? __('Aktuelle Discount- und Bonuszertifikate zu den Aktien im AktienKI-Portfolio.') : __('In Deutschland handelbare ETFs sowie Zertifikate und Anleihen mit verifiziertem Handelsplatz.') }}</p>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach([__('ETFs')=>$stats['etfs'], __('Positionen')=>$stats['holdings'], __('Produkte')=>$stats['certificates'], __('Basiswerte')=>$stats['underlyings']] as $label=>$value)
                <div class="rounded-xl border border-cyan-400/20 bg-[var(--ak-card)] px-4 py-3 text-right shadow-[var(--ak-shadow)]"><b class="block text-xl tabular-nums text-cyan-300">{{ number_format($value, 0, ',', '.') }}</b><small class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small></div>
            @endforeach
        </div>
    </header>

    <nav class="mb-4 grid grid-cols-2 gap-2 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2">
        <a href="{{ route('securities.index', ['tab'=>'etfs']) }}" class="rounded-xl px-4 py-3 text-center text-sm font-black transition {{ $tab === 'etfs' ? 'bg-cyan-400/15 text-cyan-300 ring-1 ring-cyan-400/30' : 'text-[var(--ak-muted)] hover:bg-white/5' }}">{{ __('ETFs') }}</a>
        <a href="{{ route('securities.index', ['tab'=>'certificates']) }}" class="rounded-xl px-4 py-3 text-center text-sm font-black transition {{ $tab === 'certificates' ? 'bg-amber-400/15 text-amber-300 ring-1 ring-amber-400/30' : 'text-[var(--ak-muted)] hover:bg-white/5' }}">{{ __('Zertifikate & Anleihen') }}</a>
    </nav>

    @php $certificateFiltersActive = $discountMin !== null || $returnMin !== null || $returnPaMin !== null || filled($maturityFrom) || filled($maturityTo); @endphp
    <section x-data="{ open: @js($search !== '' || filled($type) || filled($underlyingId) || $certificateFiltersActive) }" class="mb-5 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-white/5" :aria-expanded="open">
            <span class="flex items-center gap-2 text-sm font-black"><x-heroicon-o-funnel class="h-5 w-5 text-amber-400" />{{ __('Filter') }}</span>
            <span class="flex items-center gap-2 text-[10px] font-bold text-[var(--ak-muted)]">
                @if($search !== '' || filled($type) || filled($underlyingId) || $certificateFiltersActive)<span class="rounded-full bg-amber-400/15 px-2 py-1 text-amber-300">{{ __('aktiv') }}</span>@endif
                <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="{'rotate-180': open}" />
            </span>
        </button>
        <form x-cloak x-show="open" x-transition.origin.top method="GET" class="grid gap-2 border-t border-[var(--ak-border)] p-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            @if($underlyingId)<input type="hidden" name="underlying" value="{{ $underlyingId }}">@endif
            <input name="q" value="{{ $search }}" class="ak-input h-11 min-w-0 lg:col-span-2" placeholder="{{ $tab === 'etfs' ? __('ETF, Anbieter, Symbol oder ISIN') : __('Produkt, Emittent, Basiswert, WKN oder ISIN') }}">
            @if($tab === 'certificates')
                @if($canViewLeveragedProducts)
                    <select name="product_group" class="ak-input h-11"><option value="investment" @selected($productGroup==='investment')>{{ __('Anlagezertifikate') }}</option><option value="leverage" @selected($productGroup==='leverage')>{{ __('Hebelprodukte · Profil Risk') }}</option></select>
                @endif
                <select name="type" class="ak-input h-11"><option value="">{{ __('Alle Produkttypen') }}</option><option value="discount_certificate" @selected($type==='discount_certificate')>{{ __('Discountzertifikate') }}</option><option value="bonus_certificate" @selected($type==='bonus_certificate')>{{ __('Bonuszertifikate') }}</option><option value="bond" @selected($type==='bond')>{{ __('Anleihen') }}</option></select>
                <input type="number" step="0.01" name="discount_min" value="{{ $discountMin }}" class="ak-input h-11" placeholder="{{ __('Discount ab %') }}">
                <label class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span class="mb-1 block">{{ __('Fälligkeit von') }}</span><input type="date" name="maturity_from" value="{{ $maturityFrom }}" class="ak-input h-11 w-full"></label>
                <label class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span class="mb-1 block">{{ __('Fälligkeit bis') }}</span><input type="date" name="maturity_to" value="{{ $maturityTo }}" class="ak-input h-11 w-full"></label>
                <input type="number" step="0.01" name="return_min" value="{{ $returnMin }}" class="ak-input h-11 self-end" placeholder="{{ __('Rendite absolut ab %') }}">
                <input type="number" step="0.01" name="return_pa_min" value="{{ $returnPaMin }}" class="ak-input h-11 self-end" placeholder="{{ __('Rendite p. a. ab %') }}">
            @endif
            <button class="h-11 rounded-xl bg-cyan-400 px-5 text-sm font-black text-slate-950 hover:bg-cyan-300">{{ __('Anwenden') }}</button>
            <a href="{{ $tab === 'certificates' ? route('certificates.index') : route('securities.index', ['tab'=>$tab]) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-amber-400/30 px-4 text-xs font-black text-amber-300">{{ __('Zurücksetzen') }}</a>
        </form>
    </section>

    @if($tab === 'etfs')
        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($etfs as $etf)
                <article class="rounded-2xl border border-cyan-400/20 bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-400">{{ $etf->provider }}</p><h2 class="mt-1 font-black leading-5">{{ $etf->name }}</h2></div><span class="rounded-lg bg-cyan-400/10 px-2 py-1 text-[10px] font-black text-cyan-300">ETF</span></div>
                    <dl class="mt-5 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-[var(--ak-muted)]">{{ __('Symbol') }}</dt><dd class="mt-1 font-black">{{ $etf->german_listing_symbol ?: ($etf->symbol ?: '—') }}</dd></div><div><dt class="text-[var(--ak-muted)]">ISIN</dt><dd class="mt-1 font-mono text-[11px]">{{ $etf->isin ?: '—' }}</dd></div><div><dt class="text-[var(--ak-muted)]">{{ __('Handelsplatz') }}</dt><dd class="mt-1 font-bold">{{ $etf->exchange ?: '—' }}{{ $etf->mic_code ? ' · '.$etf->mic_code : '' }}</dd></div><div><dt class="text-[var(--ak-muted)]">{{ __('Positionen') }}</dt><dd class="mt-1 font-black tabular-nums text-violet-300">{{ number_format($etf->current_holding_count, 0, ',', '.') }}</dd></div></dl>
                    <p class="mt-4 border-t border-white/10 pt-3 text-[10px] text-[var(--ak-muted)]">{{ __('Datenstand') }}: {{ $etf->last_synced_at ? \Illuminate\Support\Carbon::parse($etf->last_synced_at)->format('d.m.Y H:i') : __('noch nicht synchronisiert') }}</p>
                </article>
            @empty <div class="col-span-full rounded-2xl border border-dashed border-cyan-400/25 p-10 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine passenden, verifiziert handelbaren ETFs gefunden.') }}</div> @endforelse
        </section>
        @if(method_exists($etfs, 'links'))<div class="mt-6">{{ $etfs->links() }}</div>@endif
    @else
        @if(method_exists($officialCertificates, 'links'))
            <section class="mb-5 overflow-hidden rounded-2xl border border-cyan-400/25 bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                <header class="flex flex-col justify-between gap-2 border-b border-[var(--ak-border)] px-4 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Offizieller Börsenkatalog') }}</p>
                        <h2 class="mt-1 text-lg font-black">{{ __('Frankfurt: handelbare Zertifikate und Warrants') }}</h2>
                    </div>
                    <div class="text-[10px] font-bold text-[var(--ak-muted)]">
                        {{ number_format($stats['certificates'], 0, ',', '.') }} {{ __('aktive Produkte') }}
                        @if($officialCatalogDate) · {{ __('Stand') }} {{ \Illuminate\Support\Carbon::parse($officialCatalogDate)->format('d.m.Y') }}@endif
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="ak-stocks-table w-full min-w-[900px] text-left text-[11px]">
                        <thead class="bg-cyan-950/20 text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            <tr><th class="px-4 py-3">{{ __('Produkt') }}</th><th class="px-3 py-3">{{ __('Kategorie') }}</th><th class="px-3 py-3 text-center">{{ __('Chance') }}</th><th class="px-3 py-3 text-center">{{ __('Risiko') }}</th><th class="px-3 py-3">{{ __('Basiswert-Code') }}</th><th class="px-3 py-3">{{ __('Fälligkeit') }}</th><th class="px-3 py-3">{{ __('Spezialist') }}</th><th class="px-3 py-3">{{ __('Handel') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach($officialCertificates as $security)
                                <tr class="transition hover:bg-cyan-400/[.05]">
                                    <td class="border-t border-[var(--ak-border)] px-4 py-3"><b class="block text-[var(--ak-text)]">{{ $security->instrument_name }}</b><span class="mt-1 block font-mono text-[9px] text-[var(--ak-muted)]">{{ $security->wkn ?: '—' }} · {{ $security->isin }}</span></td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-3"><span class="rounded-md border border-amber-400/25 bg-amber-400/[.08] px-2 py-1 font-black text-amber-300">{{ $security->warrant_type ?: ($security->instrument_type ?: '—') }}</span></td>
                                    @php
                                        $chanceScore = isset($security->chance_score) && is_numeric($security->chance_score) ? max(1, min(5, (int) round($security->chance_score))) : null;
                                        $riskScore = isset($security->risk_score) && is_numeric($security->risk_score) ? max(1, min(5, (int) round($security->risk_score))) : null;
                                    @endphp
                                    <td class="border-t border-[var(--ak-border)] px-3 py-2"><div class="mx-auto certificate-sector-donut" data-kind="chance" data-score="{{ $chanceScore ?: 0 }}" title="{{ $chanceScore ? __('Chance-Score :score von 5', ['score'=>$chanceScore]) : __('Noch nicht berechenbar') }}"><b>{{ $chanceScore ?: '—' }}</b></div></td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-2"><div class="mx-auto certificate-sector-donut" data-kind="risk" data-score="{{ $riskScore ?: 0 }}" title="{{ $riskScore ? __('Risiko-Score :score von 5', ['score'=>$riskScore]) : __('Noch nicht berechenbar') }}"><b>{{ $riskScore ?: '—' }}</b></div></td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-3 font-mono font-bold text-cyan-300">{{ $security->underlying_code ?: '—' }}</td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-3 font-bold tabular-nums">{{ $security->maturity_date ? \Illuminate\Support\Carbon::parse($security->maturity_date)->format('d.m.Y') : __('Open End') }}</td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-3">{{ $security->specialist ?: '—' }}</td>
                                    <td class="border-t border-[var(--ak-border)] px-3 py-3"><b>{{ $security->mic_code }}</b><small class="mt-1 block text-[var(--ak-muted)]">{{ $security->currency }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mb-7">{{ $officialCertificates->links() }}</div>
        @endif
        @php $labels=['discount_certificate'=>__('Discountzertifikat'),'bonus_certificate'=>__('Bonuszertifikat'),'bond'=>__('Anleihe')]; @endphp
        <section class="overflow-x-auto rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <table class="ak-stocks-table w-full min-w-[1120px] table-fixed border-separate border-spacing-0 text-left text-[10px] 2xl:text-[11px]">
                <colgroup>
                    <col style="width:11%"><col style="width:13%"><col style="width:8%"><col style="width:7%"><col style="width:13%">
                    <col style="width:7%"><col style="width:6%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%">
                </colgroup>
                <thead class="sticky top-0 z-20 bg-[#12343b] text-[10px] font-black uppercase tracking-[.1em] text-slate-300 shadow-[0_1px_0_rgba(34,211,238,.20),0_8px_18px_rgba(0,0,0,.22)]">
                    <tr class="h-11">
                        @foreach([
                            [__('Basiswert'),'text-left','underlying'],[__('Produkt'),'text-left','product'],[__('Typ'),'text-left','type'],[__('Fälligkeit'),'text-center','maturity'],
                            [__('Emittent'),'text-left','issuer'],[__('Kurs'),'text-right','price'],[__('Discount'),'text-right','discount'],['Cap / '.__('Barriere'),'text-right','cap'],
                            [__('Widerstände'),'text-right',null],[__('Rendite absolut'),'text-right','return'],[__('Rendite p. a.').' *','text-right','return_pa'],[__('Aktualisierung'),'text-left','updated']
                        ] as [$heading,$alignment,$sortKey])
                            <th class="border-b border-[var(--ak-border)] px-2 py-3 {{ $alignment }}">
                                @if($sortKey)
                                    @php $nextDirection = $sort === $sortKey && $direction === 'asc' ? 'desc' : 'asc'; @endphp
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $sortKey, 'direction' => $nextDirection, 'page' => 1]) }}" class="inline-flex w-full items-center gap-1 transition hover:text-cyan-300 {{ $alignment === 'text-right' ? 'justify-end' : ($alignment === 'text-center' ? 'justify-center' : '') }}" title="{{ __('Nach :column sortieren', ['column' => $heading]) }}">
                                        <span>{{ $heading }}</span>
                                        <span aria-hidden="true" class="text-[9px] {{ $sort === $sortKey ? 'opacity-100' : 'opacity-35' }}">{{ $sort === $sortKey ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                    </a>
                                @else
                                    {{ $heading }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificates as $security)
                        <tr class="group transition hover:bg-cyan-400/[.06]">
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 align-middle">
                                <a href="{{ route('stocks.show', $security->underlying_symbol) }}" class="font-black text-[var(--ak-text)] transition hover:text-cyan-300">{{ $security->underlying_name }}</a>
                                <span class="mt-0.5 block text-[9px] font-bold text-cyan-400">{{ $security->underlying_symbol }}</span>
                            </td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 align-middle">
                                <div class="truncate font-black text-[var(--ak-text)]" title="{{ $security->name }}">{{ $security->name }}</div>
                                <div class="mt-1 font-mono text-[9px] text-[var(--ak-muted)]">{{ $security->wkn ?: '—' }} · {{ $security->isin }}</div>
                            </td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 align-middle"><span class="inline-flex rounded-md bg-amber-400/10 px-2 py-1 text-[9px] font-black text-amber-300">{{ $labels[$security->type] ?? $security->type }}</span></td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-center align-middle font-bold tabular-nums">{{ $security->maturity_date ? \Illuminate\Support\Carbon::parse($security->maturity_date)->format('d.m.Y') : '—' }}</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 align-middle"><span class="line-clamp-2 font-bold">{{ $security->issuer ?: __('Unbekannt') }}</span><small class="mt-0.5 block text-[9px] text-[var(--ak-muted)]">{{ $security->exchange }}</small></td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle font-black tabular-nums text-cyan-300">{{ is_numeric($security->price) ? number_format($security->price, 2, ',', '.').' '.$security->currency : '—' }}</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle font-black tabular-nums {{ is_numeric($security->discount_percent) && $security->discount_percent > 0 ? 'text-emerald-400' : 'text-[var(--ak-muted)]' }}">{{ is_numeric($security->discount_percent) ? number_format($security->discount_percent, 2, ',', '.').' %' : '—' }}</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle tabular-nums"><b class="text-violet-300">{{ is_numeric($security->cap) ? number_format($security->cap, 2, ',', '.') : '—' }}</b>@if(is_numeric($security->barrier))<small class="mt-0.5 block text-rose-400">{{ number_format($security->barrier, 2, ',', '.') }}</small>@endif</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle tabular-nums">
                                @if($security->type === 'discount_certificate')
                                    <b class="text-amber-300">{{ is_numeric($security->resistance) ? number_format($security->resistance, 2, ',', '.') : '—' }}</b>
                                    @if(is_numeric($security->broken_resistance))<small class="mt-0.5 block text-[var(--ak-muted)]" title="{{ __('Zuletzt überwundener Widerstand') }}">{{ __('überwunden') }}: {{ number_format($security->broken_resistance, 2, ',', '.') }}</small>@endif
                                @else
                                    <span class="text-[var(--ak-muted)]">—</span>
                                @endif
                            </td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle font-black tabular-nums {{ is_numeric($security->absolute_return_percent) && $security->absolute_return_percent >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ is_numeric($security->absolute_return_percent) ? number_format($security->absolute_return_percent, 2, ',', '.').' %' : '—' }}</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 text-right align-middle font-black tabular-nums {{ is_numeric($security->annual_return_percent) && $security->annual_return_percent >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ is_numeric($security->annual_return_percent) ? number_format($security->annual_return_percent, 2, ',', '.').' %' : '—' }}</td>
                            <td class="border-b border-[var(--ak-border)] px-3 py-3 align-middle text-[9px] text-[var(--ak-muted)]">
                                <span class="block">{{ $security->quote_at ? \Illuminate\Support\Carbon::parse($security->quote_at)->timezone('Europe/Berlin')->format('d.m.Y H:i') : '—' }}</span>
                                @php
                                    $productSourceUrl = $security->source_provider === 'sg_zertifikate' && filled($security->wkn)
                                        ? 'https://www.sg-zertifikate.de/product-details/'.mb_strtolower($security->wkn)
                                        : $security->source_url;
                                @endphp
                                @if($productSourceUrl)<a class="mt-1 inline-flex font-black text-amber-300 hover:text-amber-200" href="{{ $productSourceUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Quelle') }}{{ $security->wkn ? ' · '.$security->wkn : '' }} ↗</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-6 py-12 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine passenden, verifiziert handelbaren Produkte gefunden.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @if(method_exists($certificates, 'links'))<div class="mt-6">{{ $certificates->links() }}</div>@endif
        <p class="mt-3 text-right text-[9px] text-[var(--ak-muted)]">* {{ __('Linear auf ein Jahr hochgerechnet anhand der verbleibenden Kalendertage.') }}</p>
    @endif
    <p class="mt-7 text-center text-[10px] leading-5 text-[var(--ak-muted)]">{{ __('Produktdaten dienen ausschließlich der Information. Konditionen, Kurse und Handelbarkeit müssen vor einer Entscheidung beim Emittenten oder Handelsplatz geprüft werden.') }}</p>
</main>
</x-app-layout>
