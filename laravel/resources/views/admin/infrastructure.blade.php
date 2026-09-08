<x-app-layout>
    @php
        $databaseActive = (bool) data_get($status, 'database.connected', false);
        $connectionHealthy = $databaseActive;
        $serverHostname = 'root';
        $serverAddress = request()->getHost();
        $databaseName = data_get($status, 'database.database')
            ?? data_get($status, 'database.name')
            ?? config('database.connections.'.config('database.default').'.database')
            ?? '—';
        $databaseHost = data_get($status, 'database.host')
            ?? config('database.connections.'.config('database.default').'.host')
            ?? '—';
        $userCount = data_get($status, 'database_stats.users');
        $activeUserCount = data_get($status, 'database_stats.active_users');
        $oldestModelSymbol = data_get($status, 'database_stats.oldest_model_symbol');
        $oldestModelTimestamp = data_get($status, 'database_stats.oldest_model_trained_at');
        $subscriptionCounts = data_get($status, 'database_stats.subscription_counts', []);
        $currentStocks = data_get($status, 'database_stats.current_stocks', []);
        $currentStockCount = data_get($currentStocks, 'total');
        $classifiedStockCount = data_get($currentStocks, 'classified');
        $stockQualityCounts = data_get($currentStocks, 'quality_counts', []);
        $stockQualityRows = [
            ['quality', 'Quality', 'border-emerald-400/25 bg-emerald-400/[.07] text-emerald-300'],
            ['solid', 'Solid', 'border-cyan-400/25 bg-cyan-400/[.07] text-cyan-300'],
            ['basic', 'Basic', 'border-amber-400/25 bg-amber-400/[.07] text-amber-300'],
            ['unqualified', __('Nicht qual.'), 'border-rose-400/25 bg-rose-400/[.07] text-rose-300'],
            ['unclassified', __('Offen'), 'border-white/[.08] bg-white/[.025] text-[var(--ak-muted)]'],
        ];
        $latestPrediction = data_get($status, 'database_stats.latest_prediction', []);
        $latestPredictionAvailable = (bool) data_get($latestPrediction, 'available', false);
        $latestPredictionSuccessful = data_get($latestPrediction, 'successful');
        $latestPredictionCoverage = data_get($latestPrediction, 'coverage_percent');
        $latestPredictionCreatedAt = data_get($latestPrediction, 'created_at');
        $tone = fn (bool $active): string => $active
            ? 'border-emerald-400/30 bg-emerald-400/[.07] text-emerald-300'
            : 'border-rose-400/30 bg-rose-400/[.07] text-rose-300';
    @endphp

    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)]">
        <div class="ak-container py-3 lg:py-4" x-data="{ confirmAction: null }">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl border border-orange-400/30 bg-orange-400/10 text-orange-300 shadow-[0_0_25px_rgba(251,146,60,.10)]"><x-heroicon-o-command-line class="h-6 w-6" /></span>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Administration · Betrieb') }}</p>
                        <h1 class="mt-0.5 text-2xl font-black text-[var(--ak-text)]">{{ __('Systemzentrale') }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-9 items-center gap-2 rounded-xl border px-3 text-[10px] font-black {{ $tone($connectionHealthy) }}">
                        <i class="h-2 w-2 rounded-full {{ $connectionHealthy ? 'bg-emerald-400 shadow-[0_0_8px_#34d399]' : 'bg-rose-400 shadow-[0_0_8px_#fb7185]' }}"></i>
                        {{ $connectionHealthy ? __('Alle Verbindungen aktiv') : __('Verbindung prüfen') }}
                    </span>
                    <a href="{{ route('admin.infrastructure') }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-cyan-400/25 bg-cyan-400/[.08] px-3 text-[10px] font-black text-cyan-300 transition hover:bg-cyan-400/[.15]"><x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Aktualisieren') }}</a>
                </div>
            </header>

            @if(session('status'))<div class="mt-3 rounded-xl border border-emerald-400/25 bg-emerald-400/[.08] px-4 py-2.5 text-xs font-bold text-emerald-300">{{ session('status') }}</div>@endif
            @if(session('error'))<div class="mt-3 rounded-xl border border-rose-400/25 bg-rose-400/[.08] px-4 py-2.5 text-xs font-bold text-rose-300">{{ session('error') }}</div>@endif
            @if(!empty($status['error']))
                <div class="mt-3 flex w-full items-center gap-3 rounded-xl border border-rose-400/25 bg-rose-400/[.06] px-4 py-2.5 text-xs text-rose-200"><x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0" /><span class="min-w-0 flex-1 truncate">{{ $status['error'] }}</span></div>
            @endif

            <section class="infra-overview-grid mt-4 gap-3">
                {{-- Remote server --}}
                <article class="ak-card ak-dashboard-card infra-system-card relative overflow-hidden p-4">
                    <span class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-cyan-400/10 blur-3xl"></span>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-server-stack class="h-5 w-5" /></span><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Remote-Server') }}</p><h2 class="mt-1 text-lg font-black text-[var(--ak-text)]">{{ $serverHostname }}</h2></div></div>
                        <i class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_9px_#34d399]"></i>
                    </div>
                    <div class="relative mt-3 grid grid-cols-2 gap-1.5">
                        <div class="rounded-lg border border-cyan-400/10 bg-cyan-400/[.025] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Adresse') }}</small><b class="mt-0.5 block truncate text-[9px] text-[var(--ak-text)]">{{ $serverAddress }}</b></div>
                        <div class="rounded-lg border border-cyan-400/10 bg-cyan-400/[.025] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Web-Anwendung') }}</small><b class="mt-0.5 block text-[9px] text-emerald-300">{{ __('Aktiv') }}</b></div>
                    </div>
                    <div class="relative mt-3 border-t border-[var(--ak-border)] pt-3">
                        <p class="text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Verbindungen') }}</p>
                        <div class="mt-2 space-y-2">
                            @foreach ([[__('Web-Anwendung'),true,'globe-alt'],[__('Service Datenbank'),$databaseActive,'circle-stack']] as [$label,$active,$icon])
                                <div class="flex items-center gap-3 rounded-lg border px-3 py-2 {{ $tone($active) }}"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 shrink-0" /><b class="flex-1 text-[9px]">{{ $label }}</b><span class="text-[8px] font-black uppercase">{{ $active ? __('Verbunden') : __('Getrennt') }}</span></div>
                            @endforeach
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" @click="confirmAction='restart_application_services'" class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.05] px-2 py-2 text-[8px] font-black text-cyan-300 transition hover:bg-cyan-400/[.12]">{{ __('Dienste neu starten') }}</button>
                            <button type="button" @click="confirmAction='restart_remote_server'" class="rounded-lg border border-rose-400/25 bg-rose-400/[.06] px-2 py-2 text-[8px] font-black text-rose-300 transition hover:bg-rose-400/[.13]">{{ __('Server neu starten') }}</button>
                        </div>
                        <div class="mt-3 border-t border-[var(--ak-border)] pt-3">
                            <div class="flex items-center justify-between gap-2"><p class="text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Letzte Testläufe') }}</p><span class="text-[7px] font-black text-cyan-300">{{ __('WALK-FORWARD') }}</span></div>
                            <table class="mt-1 w-full table-fixed text-left text-[8px]">
                                <thead class="text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><tr><th class="w-[12%] py-1">ID</th><th class="w-[15%] py-1">{{ __('Tage') }}</th><th class="w-[27%] py-1">{{ __('Status') }}</th><th class="w-[24%] py-1 text-right">{{ __('Aktien') }}</th><th class="w-[22%] py-1 text-right">{{ __('Uhrzeit') }}</th></tr></thead>
                                <tbody class="divide-y divide-[var(--ak-border)]">
                                    @forelse(array_slice($status['runs'] ?? [], 0, 4) as $run)
                                        @php $runStatus=$run['status'] ?? 'unavailable'; $runTone=in_array($runStatus,['completed','completed_with_errors'])?'text-emerald-300':($runStatus==='running'?'text-amber-300':'text-rose-300'); @endphp
                                        <tr><td class="py-1 font-black">#{{ $run['id'] ?? '—' }}</td><td class="py-1">{{ $run['horizon_days'] ?? '—' }}</td><td class="truncate py-1 font-black {{ $runTone }}">{{ strtoupper($runStatus) }}</td><td class="py-1 text-right tabular-nums">{{ number_format((int)($run['stocks'] ?? 0),0,',','.') }}</td><td class="py-1 text-right tabular-nums text-[var(--ak-muted)]">{{ !empty($run['started_at']) ? \Illuminate\Support\Carbon::parse($run['started_at'])->format('H:i') : '—' }}</td></tr>
                                    @empty
                                        <tr><td colspan="5" class="py-1.5 text-[var(--ak-muted)]">{{ __('Keine Testläufe verfügbar.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>

                {{-- Remote database --}}
                <article class="ak-card ak-dashboard-card infra-system-card relative flex flex-col overflow-hidden p-4">
                    <span class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-violet-400/10 blur-3xl"></span>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl border border-violet-400/25 bg-violet-400/10 text-violet-300"><x-heroicon-o-circle-stack class="h-5 w-5" /></span><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Service Datenbank') }}</p><h2 class="mt-1 text-lg font-black text-[var(--ak-text)]">{{ $databaseActive ? __('Verbunden') : __('Nicht verbunden') }}</h2></div></div>
                        <i class="mt-1 h-2.5 w-2.5 rounded-full {{ $databaseActive ? 'bg-emerald-400 shadow-[0_0_9px_#34d399]' : 'bg-rose-400 shadow-[0_0_9px_#fb7185]' }}"></i>
                    </div>
                    <div class="relative mt-3 grid grid-cols-2 gap-1.5">
                        <div class="rounded-lg border border-violet-400/10 bg-violet-400/[.025] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Datenbank') }}</small><b class="mt-0.5 block truncate text-[9px] text-[var(--ak-text)]">{{ $databaseName }}</b></div>
                        <div class="rounded-lg border border-violet-400/10 bg-violet-400/[.025] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Host') }}</small><b class="mt-0.5 block truncate text-[9px] text-[var(--ak-text)]">{{ $databaseHost }}</b></div>
                    </div>
                    <div class="relative mt-3 border-t border-[var(--ak-border)] pt-3">
                        <div class="mb-3 space-y-1.5">
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-violet-400/10 bg-violet-400/[.025] px-3 py-2"><span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Anzahl Benutzer') }}<small class="mt-0.5 flex items-center gap-1 text-[8px] font-semibold text-emerald-300"><i class="h-1.5 w-1.5 rounded-full bg-emerald-400"></i>{{ __('Aktuell aktiv') }}</small></span><span class="text-right"><b class="block text-xs tabular-nums text-[var(--ak-text)]">{{ $userCount === null ? '—' : number_format((int) $userCount, 0, ',', '.') }}</b><small class="block text-[8px] font-black tabular-nums text-emerald-300">{{ $activeUserCount === null ? '—' : number_format((int) $activeUserCount, 0, ',', '.') }}</small></span></div>
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-violet-400/10 bg-violet-400/[.025] px-3 py-2"><span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Ältestes aktives Modell') }}</span><span class="text-right"><b class="block text-xs text-amber-300">{{ $oldestModelSymbol ?: '—' }}</b><small class="block text-[8px] tabular-nums text-[var(--ak-muted)]">{{ $oldestModelTimestamp ? \Illuminate\Support\Carbon::parse($oldestModelTimestamp)->format('d.m.Y H:i') : '—' }}</small></span></div>
                        </div>
                        <div data-testid="remote-stock-quality" class="rounded-xl border border-violet-400/15 bg-violet-400/[.035] p-3">
                            <div class="flex items-start justify-between gap-3">
                                <span><small class="block text-[8px] font-black uppercase tracking-[.14em] text-violet-300">{{ __('Aktuelle Aktien') }}</small><small class="mt-0.5 block text-[7px] font-semibold text-[var(--ak-muted)]">{{ __('Service Datenbank') }}</small></span>
                                <b class="text-xl leading-5 tabular-nums text-[var(--ak-text)]">{{ $currentStockCount === null ? '—' : number_format((int) $currentStockCount, 0, ',', '.') }}</b>
                            </div>
                            <div class="mt-2 grid grid-cols-5 gap-1">
                                @foreach($stockQualityRows as [$qualityCode, $qualityLabel, $qualityTone])
                                    <div class="min-w-0 rounded-lg border px-1 py-1.5 text-center {{ $qualityTone }}" title="{{ $qualityLabel }}">
                                        <b class="block text-xs leading-4 tabular-nums">{{ number_format((int) ($stockQualityCounts[$qualityCode] ?? 0), 0, ',', '.') }}</b>
                                        <small class="block truncate text-[6px] font-black uppercase leading-3">{{ $qualityLabel }}</small>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2 text-[7px] font-bold text-[var(--ak-muted)]">
                                <span>{{ $classifiedStockCount === null || $currentStockCount === null ? '—' : number_format((int) $classifiedStockCount, 0, ',', '.').'/'.number_format((int) $currentStockCount, 0, ',', '.') }} {{ __('klassifiziert') }}</span>
                                <span class="text-violet-300">20T · REMOTE</span>
                            </div>
                        </div>
                        <div data-testid="latest-prediction" class="mt-2 rounded-xl border px-3 py-2.5 {{ $latestPredictionSuccessful === true ? 'border-emerald-400/25 bg-emerald-400/[.055]' : ($latestPredictionAvailable ? 'border-rose-400/25 bg-rose-400/[.055]' : 'border-white/[.08] bg-white/[.025]') }}">
                            <div class="flex items-start justify-between gap-3">
                                <span><small class="block text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Letzte Prediction') }}</small><small class="mt-0.5 block text-[7px] tabular-nums text-[var(--ak-muted)]">{{ $latestPredictionCreatedAt ? \Illuminate\Support\Carbon::parse($latestPredictionCreatedAt)->format('d.m.Y H:i') : '—' }}</small></span>
                                <span class="rounded-md px-2 py-1 text-[7px] font-black uppercase {{ $latestPredictionSuccessful === true ? 'bg-emerald-400/[.12] text-emerald-300' : ($latestPredictionAvailable ? 'bg-rose-400/[.12] text-rose-300' : 'bg-white/[.05] text-[var(--ak-muted)]') }}">{{ $latestPredictionSuccessful === true ? __('Erfolgreich') : ($latestPredictionAvailable ? __('Unvollständig') : __('Keine Daten')) }}</span>
                            </div>
                            <div class="mt-2 flex items-end justify-between gap-3">
                                <span><b class="text-lg leading-5 tabular-nums text-[var(--ak-text)]">{{ $latestPredictionAvailable ? number_format((int) data_get($latestPrediction, 'complete', 0), 0, ',', '.').' / '.number_format((int) data_get($latestPrediction, 'eligible', 0), 0, ',', '.') : '—' }}</b><small class="ml-1 text-[8px] font-bold text-[var(--ak-muted)]">{{ __('modellberechtigte Aktien vollständig') }}</small></span>
                                <b class="text-xs tabular-nums {{ $latestPredictionSuccessful === true ? 'text-emerald-300' : 'text-rose-300' }}">{{ $latestPredictionCoverage === null ? '—' : number_format((float) $latestPredictionCoverage, 1, ',', '.').' %' }}</b>
                            </div>
                            <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/[.07]"><i class="block h-full rounded-full {{ $latestPredictionSuccessful === true ? 'bg-emerald-400' : 'bg-rose-400' }}" style="width: {{ max(0, min(100, (float) ($latestPredictionCoverage ?? 0))) }}%"></i></div>
                            <p class="mt-1.5 text-[7px] font-semibold text-[var(--ak-muted)]">{{ number_format((int) data_get($latestPrediction, 'stocks', 0), 0, ',', '.') }} {{ __('Aktien im Lauf') }} · {{ number_format((int) data_get($latestPrediction, 'rows', 0), 0, ',', '.') }} {{ __('Prognosen') }} · {{ number_format((int) data_get($latestPrediction, 'horizons', 0), 0, ',', '.') }} {{ __('Horizonte') }}</p>
                        </div>
                        <p class="mt-3 text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Datenfluss') }}</p>
                        <div class="mt-2 rounded-xl border px-3 py-3 {{ $tone($databaseActive) }}">
                            <div class="flex items-center gap-3"><x-heroicon-o-cloud-arrow-up class="h-5 w-5" /><div class="min-w-0 flex-1"><b class="block text-[10px]">{{ __('Ergebnisse speichern') }}</b><small class="block truncate text-[8px] opacity-75">{{ __('Training · Prognosen · Walk-Forward') }}</small></div></div>
                        </div>
                        <p class="mt-2 text-[9px] text-[var(--ak-muted)]">{{ __('Laravel-Verbindung') }}: <b class="text-[var(--ak-text)]">{{ data_get($status, 'database.server', '—') }}:{{ data_get($status, 'database.port', '—') }}</b></p>
                    </div>
                    <div class="relative mt-auto border-t border-[var(--ak-border)] pt-3">
                        <p class="text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ __('Benutzer nach Abonnement') }}</p>
                        <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                            @forelse($subscriptionCounts as $plan)
                                @php $planCode = strtolower((string) ($plan['code'] ?? '')); $planTone = $planCode === 'pro' ? 'border-amber-400/25 bg-amber-400/[.07] text-amber-300' : ($planCode === 'plus' ? 'border-cyan-400/20 bg-cyan-400/[.05] text-cyan-300' : 'border-white/[.07] bg-white/[.025] text-[var(--ak-text)]'); @endphp
                                <div class="rounded-lg border px-2 py-2 text-center {{ $planTone }}"><small class="block truncate text-[7px] font-black uppercase tracking-wide">{{ $plan['name'] ?? $plan['code'] ?? '—' }}</small><b class="mt-0.5 block text-base leading-4 tabular-nums">{{ number_format((int) ($plan['users'] ?? 0), 0, ',', '.') }}</b></div>
                            @empty
                                <div class="col-span-3 rounded-lg border border-white/[.07] px-2 py-2 text-center text-[8px] text-[var(--ak-muted)]">{{ __('Keine Tarifdaten verfügbar.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </article>
            </section>

            <div x-cloak x-show="confirmAction" class="fixed inset-0 z-[240] grid place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" @keydown.escape.window="confirmAction=null"><div class="w-full max-w-md rounded-2xl border border-orange-300/25 bg-[var(--ak-card)] p-5 shadow-2xl" @click.outside="confirmAction=null"><p class="text-[9px] font-black uppercase tracking-[.16em]" :class="confirmAction === 'restart_remote_server' ? 'text-rose-300' : 'text-orange-300'">{{ __('Bestätigung') }}</p><h2 class="mt-2 text-xl font-black" x-text="confirmAction === 'restart_remote_server' ? '{{ __('Remote-Server wirklich neu starten?') }}' : '{{ __('Wartungsaktion ausführen?') }}'"></h2><p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]" x-text="confirmAction === 'restart_remote_server' ? '{{ __('Website und Adminbereich sind während des Neustarts vorübergehend nicht erreichbar.') }}' : '{{ __('Die Aktion wird kontrolliert ausgeführt und anschließend protokolliert.') }}'"></p><form method="POST" action="{{ route('admin.infrastructure.action') }}" class="mt-5">@csrf<input type="hidden" name="action" :value="confirmAction"><div x-show="confirmAction === 'restart_remote_server'" class="mb-4"><label for="infrastructure-current-password" class="mb-1.5 block text-[9px] font-black uppercase tracking-[.14em] text-rose-300">{{ __('Aktuelles Admin-Passwort') }}</label><input id="infrastructure-current-password" name="current_password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-rose-400/25 bg-slate-950/50 px-3 py-2.5 text-sm text-white outline-none focus:border-rose-300" :required="confirmAction === 'restart_remote_server'"></div><div class="flex justify-end gap-2"><button type="button" @click="confirmAction=null" class="rounded-xl border border-[var(--ak-border)] px-4 py-2 text-xs font-black">{{ __('Abbrechen') }}</button><button type="submit" class="rounded-xl border px-4 py-2 text-xs font-black" :class="confirmAction === 'restart_remote_server' ? 'border-rose-300/35 bg-rose-300/[.12] text-rose-200' : 'border-orange-300/35 bg-orange-300/[.12] text-orange-200'" x-text="confirmAction === 'restart_remote_server' ? '{{ __('Server neu starten') }}' : '{{ __('Ausführen') }}'"></button></div></form></div></div>

            <style>
                .infra-overview-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
                .infra-system-card { min-height:0; }
                @media (max-width: 860px) {
                    .infra-overview-grid { grid-template-columns:1fr; }
                }
            </style>
        </div>
    </main>
</x-app-layout>
