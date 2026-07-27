@php
    use App\Models\Company;
    use App\Models\Prediction;
    use App\Models\News;
    use App\Models\ModelResult;

    $stocksCount = Company::count();

    $sectorsCount = Company::whereNotNull('sector')
        ->where('sector', '!=', '')
        ->distinct('sector')
        ->count('sector');

    $predictionsCount = Prediction::count();

    $newsCount = class_exists(News::class)
        ? News::count()
        : 0;

    $modelsCount = class_exists(ModelResult::class)
        ? ModelResult::count()
        : 0;

    $topPredictions = Prediction::with('company')
        ->whereNotNull('confidence')
        ->orderByDesc('confidence')
        ->limit(5)
        ->get();

    if ($topPredictions->isEmpty()) {
        $topPredictions = Prediction::with('company')
            ->latest('prediction_date')
            ->limit(5)
            ->get();
    }
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AktienKI</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-950 text-white antialiased overflow-x-hidden">

<div class="min-h-screen relative overflow-hidden">

    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(37,99,235,0.22),transparent_32%),radial-gradient(circle_at_80%_20%,rgba(124,58,237,0.18),transparent_30%),linear-gradient(180deg,#020617,#0f172a)]"></div>

    <div class="relative max-w-screen-2xl mx-auto px-6 lg:px-10 py-8">

        <nav class="flex items-center justify-between mb-20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                    <x-heroicon-o-chart-bar-square class="w-6 h-6 text-blue-400"/>
                </div>

                <span class="text-xl font-bold tracking-tight">
                    AktienKI
                </span>
            </div>

            <div class="hidden md:flex items-center gap-8 text-sm text-slate-300">
                <a href="#features" class="hover:text-white">Features</a>
                <a href="#daten" class="hover:text-white">Analysen</a>
                <a href="#daten" class="hover:text-white">Daten</a>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('login') }}"
                   class="px-5 py-2.5 rounded-xl border border-slate-700 text-sm hover:bg-slate-800 transition">
                    Anmelden
                </a>

                <a href="{{ route('register') }}"
                   class="px-5 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold hover:bg-blue-500 transition">
                    Registrieren
                </a>
            </div>
        </nav>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-16 mb-10 items-center">

            <div>
                
                <h1 class="text-4xl md:text-6xl font-extrabold leading-tight tracking-tight">
                    Intelligente Analysen.<br>
                    Bessere Entscheidungen.<br>
                    <span class="text-blue-500">Mehr Überblick.</span>
                </h1>

                <p class="mt-6 text-slate-400 text-lg leading-relaxed max-w-xl">
                    AktienKI kombiniert Machine Learning, Fundamentaldaten, Markttrends und Risikoanalyse,
                    um Aktien verständlich und datenbasiert zu bewerten.
                </p>

                <div id="features" class="mt-10 space-y-5">

                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                            <x-heroicon-o-cpu-chip class="w-6 h-6 text-blue-400"/>
                        </div>

                        <div>
                            <h3 class="font-semibold">KI-Vorhersagen</h3>
                            <p class="text-sm text-slate-400">
                                Modelle analysieren historische Marktdaten, Trends und Wahrscheinlichkeiten.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                            <x-heroicon-o-chart-bar-square class="w-6 h-6 text-emerald-400"/>
                        </div>

                        <div>
                            <h3 class="font-semibold">Nachvollziehbare Scores</h3>
                            <p class="text-sm text-slate-400">
                                AI Score, Confidence, Trend, Momentum und Risiko auf einen Blick.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
                            <x-heroicon-o-shield-check class="w-6 h-6 text-violet-400"/>
                        </div>

                        <div>
                            <h3 class="font-semibold">Risikobewusst investieren</h3>
                            <p class="text-sm text-slate-400">
                                Nicht nur Chancen, sondern auch Unsicherheit und Risiko erkennen.
                            </p>
                        </div>
                    </div>

                </div>

                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ route('register') }}"
                       class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold transition">
                        Kostenlos starten
                    </a>

                    <a href="#daten"
                       class="px-6 py-3 rounded-xl border border-slate-700 hover:bg-slate-800 font-semibold transition">
                        Reale Daten ansehen
                    </a>
                </div>
            </div>

            <div class="relative h-[750px]">

                <div class="absolute inset-0 rounded-3xl bg-slate-900/80 border border-slate-800 shadow-2xl overflow-hidden">

                    <div class="absolute inset-0 opacity-30 bg-[linear-gradient(rgba(59,130,246,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(59,130,246,0.12)_1px,transparent_1px)] bg-[size:36px_36px]"></div>

                    <div class="absolute top-6 left-1/2 -translate-x-1/2 flex gap-2 z-20">
                        @for($i = 0; $i < max($topPredictions->count(), 1); $i++)
                            <span class="w-2 h-2 rounded-full {{ $i === 0 ? 'bg-blue-500' : 'bg-slate-600' }}"></span>
                        @endfor
                    </div>

                    <div
                        x-data="{
                            active: 0,
                            total: {{ max($topPredictions->count(), 1) }},
                            visible: true,
                            next() {
                                this.visible = false;

                                setTimeout(() => {
                                    this.active = (this.active + 1) % this.total;
                                    this.visible = true;
                                }, 180);
                            }
                        }"
                        x-init="setInterval(() => next(), 5200)"
                        class="relative h-full"
                    >
                        @forelse($topPredictions as $index => $prediction)
                            @php
                                $company = $prediction->company;
                                $score = $prediction->ai_score ?? round(($prediction->buy_probability ?? $prediction->probability ?? 0) * 100);
                                $confidence = $prediction->confidence ?? $score;
                                $direction = strtoupper($prediction->direction ?? 'HOLD');
                                $expectedReturn = $prediction->expected_return ?? null;
                            @endphp

                            <div
                                x-show="active === {{ $index }}"
                                x-transition:enter="transition ease-out duration-500"
                                x-transition:enter-start="opacity-0 translate-x-24 scale-[0.98]"
                                x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                                x-transition:leave="transition ease-in duration-180"
                                x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-x-24 scale-[0.98]"
                                class="absolute inset-[76px_56px_56px_56px]"
                            >
                                <div class="flex items-start justify-between gap-6">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-widest mb-2">
                                            Top Empfehlung
                                        </p>

                                        <h2 class="text-3xl font-bold">
                                            {{ $company?->symbol ?? 'Aktie' }}
                                        </h2>

                                        <p class="text-sm text-slate-400 mt-1">
                                            {{ $company?->name ?? 'AktienKI Analyse' }}
                                        </p>
                                    </div>

                                    <div class="px-4 py-2 rounded-full border text-xs font-bold
                                        @if($direction === 'BUY')
                                            bg-emerald-500/10 text-emerald-400 border-emerald-500/20
                                        @elseif($direction === 'SELL')
                                            bg-red-500/10 text-red-400 border-red-500/20
                                        @else
                                            bg-yellow-500/10 text-yellow-400 border-yellow-500/20
                                        @endif">
                                        {{ $direction }}
                                    </div>
                                </div>

                                <div class="mt-8 grid grid-cols-2 gap-6 items-center">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-widest">
                                            AI Score
                                        </p>

                                        <div class="text-7xl font-black mt-2
                                            {{ $score >= 75 ? 'text-emerald-400' : ($score >= 50 ? 'text-yellow-400' : 'text-red-400') }}">
                                            {{ $score }} 
                                            
                                        </div>
                                      

                                        <p class="text-sm text-slate-400 mt-2">
                                            @if($score >= 90)
                                                Sehr starke Bewertung
                                            @elseif($score >= 75)
                                                Positive Bewertung
                                            @elseif($score >= 50)
                                                Beobachten
                                            @else
                                                Schwache Bewertung
                                            @endif
                                        </p>
                                    </div>

                                  
                                </div>

                                <div class="mt-8 rounded-2xl bg-slate-950/80 border border-slate-800 p-5">
                                    <div class="grid grid-cols-2 gap-4">

                                        <div>
                                            <p class="text-xs text-slate-500">Aktueller Kurs</p>
                                            <p class="font-bold mt-1">
                                                {{ $prediction->current_price !== null ? number_format($prediction->current_price, 2, ',', '.') : '-' }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">Zielkurs</p>
                                            <p class="font-bold mt-1">
                                                {{ $prediction->target_price !== null ? number_format($prediction->target_price, 2, ',', '.') : '-' }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">Erwartete Rendite</p>
                                            <p class="font-bold mt-1 {{ ($expectedReturn ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                                {{ $expectedReturn !== null ? number_format($expectedReturn, 2, ',', '.') . ' %' : '-' }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">Confidence</p>
                                            <p class="font-bold mt-1 text-blue-400">
                                                {{ number_format($confidence, 0, ',', '.') }} %
                                            </p>
                                        </div>

                                    </div>
                                </div>

                                <div class="mt-6 space-y-4">

                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="flex items-center gap-2 text-slate-300">
                                                <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-emerald-400"/>
                                                Trend
                                            </span>
                                            <span>{{ $prediction->trend_score ?? $score }}</span>
                                        </div>

                                        <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-2 bg-emerald-500 rounded-full"
                                                style="width: {{ min($prediction->trend_score ?? $score, 100) }}%">
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="flex items-center gap-2 text-slate-300">
                                                <x-heroicon-o-bolt class="w-4 h-4 text-blue-400"/>
                                                Momentum
                                            </span>
                                            <span>{{ $prediction->momentum_score ?? $confidence }}</span>
                                        </div>

                                        <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-2 bg-blue-500 rounded-full"
                                                style="width: {{ min($prediction->momentum_score ?? $confidence, 100) }}%">
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="flex items-center gap-2 text-slate-300">
                                                <x-heroicon-o-shield-check class="w-4 h-4 text-violet-400"/>
                                                Risiko
                                            </span>
                                            <span>{{ $prediction->risk_score ?? 50 }}</span>
                                        </div>

                                        <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-2 bg-violet-500 rounded-full"
                                                style="width: {{ min($prediction->risk_score ?? 50, 100) }}%">
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="absolute bottom-0 left-0 right-0 flex justify-center gap-2">
                                    @foreach($topPredictions as $dotIndex => $dotPrediction)
                                        <button
                                            type="button"
                                            @click="
                                                visible = false;
                                                setTimeout(() => {
                                                    active = {{ $dotIndex }};
                                                    visible = true;
                                                }, 180);
                                            "
                                            class="w-2 h-2 rounded-full transition"
                                            :class="active === {{ $dotIndex }} ? 'bg-blue-500' : 'bg-slate-600'"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="absolute inset-[76px_56px_56px_56px] flex flex-col items-center justify-center text-center">
                                <x-heroicon-o-circle-stack class="w-16 h-16 text-blue-400 mb-6"/>

                                <h2 class="text-3xl font-bold">
                                    Noch keine Predictions vorhanden
                                </h2>

                                <p class="text-slate-400 mt-4 max-w-md">
                                    Sobald deine Python-Engine Vorhersagen in die Datenbank schreibt,
                                    erscheinen hier automatisch deine Top-Empfehlungen.
                                </p>
                            </div>
                        @endforelse
                    </div>

                </div>
                </div>

        </section>

        <section id="daten" class="">
            <div class="text-center mb-5">
                <h2 class="text-3xl font-bold">
                    Täglich aktuelle Analysen
                </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">

                    <div class="stat-card">
                        <div class="stat-icon bg-blue-500/10 border-blue-500/20">
                            <x-heroicon-o-building-office-2 class="w-7 h-7 text-blue-400"/>
                        </div>

                        <p class="stat-value">{{ number_format($stocksCount, 0, ',', '.') }}</p>
                        <p class="stat-label">Aktien analysiert</p>
                        <p class="stat-note">aus deiner Datenbank</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-emerald-500/10 border-emerald-500/20">
                            <x-heroicon-o-globe-europe-africa class="w-7 h-7 text-emerald-400"/>
                        </div>

                        <p class="stat-value">{{ number_format($sectorsCount, 0, ',', '.') }}</p>
                        <p class="stat-label">Sektoren</p>
                        <p class="stat-note">branchenübergreifend</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-violet-500/10 border-violet-500/20">
                            <x-heroicon-o-cpu-chip class="w-7 h-7 text-violet-400"/>
                        </div>

                        <p class="stat-value">{{ number_format($predictionsCount, 0, ',', '.') }}</p>
                        <p class="stat-label">KI-Vorhersagen</p>
                        <p class="stat-note">historisch gespeichert</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-500/10 border-yellow-500/20">
                            <x-heroicon-o-newspaper class="w-7 h-7 text-yellow-400"/>
                        </div>

                        <p class="stat-value">{{ number_format($newsCount, 0, ',', '.') }}</p>
                        <p class="stat-label">News analysiert</p>
                        <p class="stat-note">Sentiment vorbereitet</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-sky-500/10 border-sky-500/20">
                            <x-heroicon-o-circle-stack class="w-7 h-7 text-sky-400"/>
                        </div>

                        <p class="stat-value">{{ number_format($modelsCount, 0, ',', '.') }}</p>
                        <p class="stat-label">Modelle</p>
                        <p class="stat-note">Modellresultate</p>
                    </div>

                </div>
        </section>

    </div>
</div>

<style>
    .prediction-slide {
        position: absolute;
        inset: 76px 56px 56px 56px;
        opacity: 0;
        transform: translateY(18px) scale(.98);
        animation-name: predictionFade;
        animation-duration: calc(var(--slides) * 5s);
        animation-iteration-count: infinite;
        animation-timing-function: ease-in-out;
    }

    @keyframes predictionFade {
        0% {
            opacity: 0;
            transform: translateY(18px) scale(.98);
        }

        5% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        18% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        23% {
            opacity: 0;
            transform: translateY(-18px) scale(.98);
        }

        100% {
            opacity: 0;
            transform: translateY(-18px) scale(.98);
        }
    }

    .stat-card {
        border: 1px solid rgba(51,65,85,.9);
        background: rgba(15,23,42,.86);
        border-radius: 1rem;
        padding: 1.35rem;
        box-shadow: 0 20px 45px rgba(0,0,0,.25);
        transition: all .2s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        border-color: rgba(96,165,250,.45);
    }

    .stat-icon {
        width: 3rem;
        height: 3rem;
        border-radius: .9rem;
        border-width: 1px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.2rem;
    }

    .stat-value {
        font-size: 1.9rem;
        font-weight: 800;
        color: white;
        line-height: 1;
    }

    .stat-label {
        margin-top: .5rem;
        color: rgb(203 213 225);
        font-size: .875rem;
    }

    .stat-note {
        margin-top: .75rem;
        color: rgb(52 211 153);
        font-size: .78rem;
    }
</style>

</body>
</html>