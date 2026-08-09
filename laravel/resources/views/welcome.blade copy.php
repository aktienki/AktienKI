@php
    use App\Models\Company;
    use App\Models\Prediction;
    use App\Models\News;

    $stocksCount = Company::count();
    $sectorsCount = Company::whereNotNull('sector')->distinct('sector')->count('sector');
    $predictionsCount = Prediction::count();
    $newsCount = class_exists(News::class) ? News::count() : 0;
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>AktienKI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-950 text-white antialiased overflow-x-hidden">

<div class="min-h-screen relative">

    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(59,130,246,0.18),transparent_35%),radial-gradient(circle_at_top_right,rgba(139,92,246,0.16),transparent_35%)]"></div>

    <div class="relative max-w-7xl mx-auto px-6 py-8">

        <nav class="flex items-center justify-between mb-20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-bold">
                    AI
                </div>
                <span class="text-xl font-bold">AktienKI</span>
            </div>

            <div class="flex gap-4">
                <a href="{{ route('login') }}" class="px-5 py-2 rounded-xl border border-slate-700 text-sm hover:bg-slate-800">
                    Anmelden
                </a>
                <a href="{{ route('register') }}" class="px-5 py-2 rounded-xl bg-blue-600 text-sm font-semibold hover:bg-blue-500">
                    Registrieren
                </a>
            </div>
        </nav>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
        

            <div>
                <div class="inline-flex px-4 py-2 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs font-semibold mb-6">
                    KI-gestützte Aktienanalyse
                </div>

                <h1 class="text-4xl md:text-6xl font-extrabold leading-tight">
                    Intelligente Analysen.<br>
                    Bessere Entscheidungen.<br>
                    <span class="text-blue-500">Mehr Überblick.</span>
                </h1>

                <p class="mt-6 text-slate-400 text-lg leading-relaxed max-w-xl">
                    AktienKI kombiniert Machine Learning, Fundamentaldaten, Markttrends und Risikoanalyse,
                    um Aktien verständlich und datenbasiert zu bewerten.
                </p>

                <div class="mt-10 space-y-5">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center"><x-heroicon-o-cpu-chip class="w-6 h-6 text-blue-400"/></div>
                        <div>
                            <h3 class="font-semibold">KI-Vorhersagen</h3>
                            <p class="text-sm text-slate-400">Analysen auf Basis historischer Marktdaten und Modellbewertungen.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center"><x-heroicon-o-arrow-trending-up class="w-6 h-6 text-emerald-400"/></div>
                        <div>
                            <h3 class="font-semibold">Nachvollziehbare Scores</h3>
                            <p class="text-sm text-slate-400">AI Score, Confidence, Trend, Momentum und Risiko auf einen Blick.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center"><x-heroicon-o-shield-check class="w-6 h-6 text-violet-400"/></div>
                        <div>
                            <h3 class="font-semibold">Risikobewusst investieren</h3>
                            <p class="text-sm text-slate-400">Nicht nur Chancen, sondern auch Unsicherheit und Risiko erkennen.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex gap-4">
                    <a href="{{ route('register') }}" class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold">
                        Als Tester registrieren
                    </a>
                    <a href="#daten" class="px-6 py-3 rounded-xl border border-slate-700 hover:bg-slate-800 font-semibold">
                        Mehr erfahren
                    </a>
                </div>
            </div>

            <div class="relative h-[560px]">

                <div class="absolute inset-0 rounded-3xl bg-slate-900/80 border border-slate-800 shadow-2xl p-8 overflow-hidden">

                    <div class="absolute top-6 left-1/2 -translate-x-1/2 flex gap-2 z-20">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        <span class="w-2 h-2 rounded-full bg-slate-600"></span>
                        <span class="w-2 h-2 rounded-full bg-slate-600"></span>
                    </div>

                    <div class="welcome-card welcome-card-1">
                        <p class="text-sm text-slate-400 mb-2">AI SCORE</p>
                        <div class="text-7xl font-black text-emerald-400">94</div>
                        <p class="text-emerald-400 mt-2 font-semibold">Sehr stark</p>

                        <div class="mt-8 h-40 rounded-xl bg-slate-950 border border-slate-800 p-4">
                            <div class="h-full flex items-end gap-2">
                                @foreach([20,28,35,42,39,46,52,61,58,66,70,73,80,78,86,94] as $bar)
                                    <div class="flex-1 rounded-t bg-emerald-500/80" style="height: {{ $bar }}%"></div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-8 space-y-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Trend</span><span>96</span>
                                </div>
                                <div class="h-2 bg-slate-800 rounded-full">
                                    <div class="h-2 bg-emerald-500 rounded-full w-[96%]"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Momentum</span><span>91</span>
                                </div>
                                <div class="h-2 bg-slate-800 rounded-full">
                                    <div class="h-2 bg-blue-500 rounded-full w-[91%]"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="welcome-card welcome-card-2">
                        <p class="text-sm text-slate-400 mb-4">MARKTANALYSE</p>
                        <h2 class="text-4xl font-bold mb-6">Gesamtmarkt bullish</h2>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5">
                                <p class="text-xs text-slate-500">Buy-Signale</p>
                                <p class="text-3xl font-bold text-emerald-400 mt-2">73%</p>
                            </div>

                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5">
                                <p class="text-xs text-slate-500">Risiko</p>
                                <p class="text-3xl font-bold text-yellow-400 mt-2">Mittel</p>
                            </div>

                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5">
                                <p class="text-xs text-slate-500">Volatilität</p>
                                <p class="text-3xl font-bold text-blue-400 mt-2">2.1</p>
                            </div>

                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5">
                                <p class="text-xs text-slate-500">Confidence</p>
                                <p class="text-3xl font-bold text-violet-400 mt-2">87%</p>
                            </div>
                        </div>

                        <div class="mt-8 rounded-2xl bg-slate-950 border border-slate-800 p-5">
                            <p class="text-sm text-slate-400 mb-3">Top Sektor</p>
                            <div class="text-2xl font-bold">Technologie</div>
                            <p class="text-emerald-400 text-sm mt-2">+18% stärkere Signale</p>
                        </div>
                    </div>

                    <div class="welcome-card welcome-card-3">
                        <p class="text-sm text-slate-400 mb-4">PREDICTION ENGINE</p>
                        <h2 class="text-4xl font-bold mb-8">Tägliche KI-Auswertung</h2>

                        <div class="space-y-4">
                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5 flex justify-between">
                                <div>
                                    <p class="font-bold">SAP</p>
                                    <p class="text-xs text-slate-500">DAX · Software</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-emerald-400 font-bold">BUY</p>
                                    <p class="text-sm">AI 92</p>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5 flex justify-between">
                                <div>
                                    <p class="font-bold">NVIDIA</p>
                                    <p class="text-xs text-slate-500">NASDAQ · Chips</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-emerald-400 font-bold">BUY</p>
                                    <p class="text-sm">AI 89</p>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-5 flex justify-between">
                                <div>
                                    <p class="font-bold">Tesla</p>
                                    <p class="text-xs text-slate-500">NASDAQ · Auto</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-red-400 font-bold">SELL</p>
                                    <p class="text-sm">AI 38</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </section>

        <section id="daten" class="mt-24">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold">Aktuelle Analysen in Echtzeit</h2>
                <p class="text-slate-400 mt-3">Reale Werte direkt aus deiner PostgreSQL-Datenbank.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

                <div class="stat-card">
                    <p class="stat-value">{{ number_format($stocksCount, 0, ',', '.') }}</p>
                    <p class="stat-label">Aktien analysiert</p>
                    <p class="stat-note">aus deiner Datenbank</p>
                </div>

                <div class="stat-card">
                    <p class="stat-value">{{ number_format($sectorsCount, 0, ',', '.') }}</p>
                    <p class="stat-label">Sektoren abgedeckt</p>
                    <p class="stat-note">branchenübergreifend</p>
                </div>

                <div class="stat-card">
                    <p class="stat-value">{{ number_format($predictionsCount, 0, ',', '.') }}</p>
                    <p class="stat-label">KI-Vorhersagen</p>
                    <p class="stat-note">historisch gespeichert</p>
                </div>

                <div class="stat-card">
                    <p class="stat-value">{{ number_format($newsCount, 0, ',', '.') }}</p>
                    <p class="stat-label">News analysiert</p>
                    <p class="stat-note">Sentiment vorbereitet</p>
                </div>

            </div>
        </section>

    </div>
</div>

<style>
    .welcome-card {
        position: absolute;
        inset: 76px 56px 56px 56px;
        opacity: 0;
        transform: translateY(16px) scale(.98);
        animation: fadeCards 15s infinite;
    }

    .welcome-card-1 { animation-delay: 0s; }
    .welcome-card-2 { animation-delay: 5s; }
    .welcome-card-3 { animation-delay: 10s; }

    @keyframes fadeCards {
        0% { opacity: 0; transform: translateY(16px) scale(.98); }
        8% { opacity: 1; transform: translateY(0) scale(1); }
        28% { opacity: 1; transform: translateY(0) scale(1); }
        36% { opacity: 0; transform: translateY(-16px) scale(.98); }
        100% { opacity: 0; transform: translateY(-16px) scale(.98); }
    }

    .stat-card {
        border: 1px solid rgba(51,65,85,.9);
        background: rgba(15,23,42,.85);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 20px 45px rgba(0,0,0,.25);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: white;
    }

    .stat-label {
        margin-top: .35rem;
        color: rgb(203 213 225);
        font-size: .9rem;
    }

    .stat-note {
        margin-top: .75rem;
        color: rgb(52 211 153);
        font-size: .8rem;
    }
</style>

</body>
</html>
