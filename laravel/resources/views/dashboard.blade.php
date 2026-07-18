<x-layouts::app :title="__('Dashboard')">
    <div class="relative min-h-full overflow-hidden bg-[#0b0716] text-white">
        <div class="pointer-events-none absolute inset-0 opacity-40 [background-image:linear-gradient(rgba(167,139,250,.06)_1px,transparent_1px),linear-gradient(90deg,rgba(167,139,250,.06)_1px,transparent_1px)] [background-size:42px_42px]"></div>
        <div class="pointer-events-none absolute -left-40 top-0 h-96 w-96 rounded-full bg-violet-600/15 blur-3xl"></div>
        <div class="pointer-events-none absolute right-0 top-24 h-80 w-80 rounded-full bg-fuchsia-500/10 blur-3xl"></div>

        <div class="relative mx-auto max-w-[1600px] space-y-7 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.28em] text-violet-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.9)]"></span>
                        AktienKI Intelligence
                    </div>
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Markt- & KI-Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-400 sm:text-base">
                        Marktlage, Top-Signale und Modellstatus auf einen Blick.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 backdrop-blur-xl">
                        <div class="text-[11px] uppercase tracking-wider text-zinc-500">Letztes Update</div>
                        <div class="mt-1 text-sm font-medium text-zinc-200">
                            {{ $snapshot?->snapshot_time?->format('d.m.Y H:i') ?? $latestRun?->started_at?->format('d.m.Y H:i') ?? 'Noch keine Daten' }}
                        </div>
                    </div>
                    <a href="{{ route('market-overview') }}" class="rounded-2xl border border-violet-400/30 bg-violet-500/10 px-4 py-3 text-sm font-semibold text-violet-200 transition hover:border-violet-300/60 hover:bg-violet-500/20">
                        Marktübersicht öffnen
                    </a>
                </div>
            </header>

            @include('dashboard.partials.market-overview')

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,.8fr)]">
                @include('dashboard.partials.top-signals')
                @include('dashboard.partials.ai-engine-status')
            </div>

            @include('dashboard.partials.market-snapshot')
        </div>
    </div>
</x-layouts::app>
