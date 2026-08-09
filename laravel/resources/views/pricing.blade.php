<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AktienKI Tarife und Funktionsumfang im Vergleich.">
    <title>{{ __('Preise') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .pricing-bg { --ak-accent:#fb923c;--ak-accent-soft:rgba(251,146,60,.10);--ak-card-strong:rgba(52,65,95,.60);--ak-border:rgba(251,146,60,.24);background-color:#090d22;background-image:radial-gradient(circle at 73% 34%,rgba(124,58,237,.16),transparent 34%),radial-gradient(circle at 28% 92%,rgba(251,146,60,.13),transparent 34%),radial-gradient(circle at 8% 16%,rgba(251,191,36,.04),transparent 22%),linear-gradient(135deg,#090d22 0%,#10162f 48%,#171033 100%); }
        .pricing-topbar { background:rgba(11,20,36,.96);border-bottom:1px solid rgba(251,146,60,.14);box-shadow:0 10px 30px rgba(2,6,23,.24),inset 0 -1px 0 rgba(251,146,60,.035);backdrop-filter:blur(18px) saturate(115%); }
        .pricing-dashboard-card { background-color:rgba(52,65,95,.60);border-color:rgba(251,146,60,.30);box-shadow:0 12px 30px rgba(2,132,199,.10),inset 0 1px 0 rgba(251,146,60,.035);backdrop-filter:blur(8px); }
        .pricing-featured { border-width:2px;border-color:rgba(251,191,36,.75);background:linear-gradient(145deg,rgba(251,191,36,.30),rgba(245,158,11,.11));box-shadow:0 26px 64px rgba(180,83,9,.32),0 0 38px rgba(251,191,36,.16),inset 0 1px 0 rgba(254,243,199,.22);transform:translateY(-.35rem); }
        .pricing-featured:hover { border-color:rgba(253,230,138,.95); }
        .pricing-popular-badge { border:1px solid rgba(251,191,36,.48);background:rgba(251,191,36,.21);color:#fef3c7; }
        .pricing-featured-button { background:linear-gradient(90deg,#f59e0b,#fbbf24);color:#1c1917;box-shadow:0 10px 28px rgba(180,83,9,.3),inset 0 1px 0 rgba(255,255,255,.3); }
        .pricing-featured-button:hover { filter:brightness(1.08);box-shadow:0 12px 34px rgba(245,158,11,.4); }
        .pricing-planned { border-style:dashed;border-color:rgba(248,113,113,.45);background:linear-gradient(145deg,rgba(153,27,27,.25),rgba(190,24,93,.10) 55%,rgba(15,23,42,.22));box-shadow:0 20px 48px rgba(127,29,29,.18),inset 0 1px 0 rgba(254,202,202,.07); }
        .pricing-planned-badge { border:1px solid rgba(252,165,165,.82);background:linear-gradient(90deg,#991b1b,#dc2626 52%,#f87171);color:#fff;box-shadow:0 0 24px rgba(239,68,68,.38),0 6px 18px rgba(127,29,29,.3); }
        .pricing-planned-button { border:1px dashed rgba(148,163,184,.28);background:rgba(148,163,184,.06);color:#94a3b8;cursor:not-allowed; }
        [data-theme="light"] .pricing-bg { background-color:#f8fafc; background-image:radial-gradient(circle at 75% 15%,rgba(139,92,246,.13),transparent 30%),radial-gradient(circle at 15% 80%,rgba(251,146,60,.1),transparent 28%),linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px); }
        [data-theme="light"] .pricing-topbar { background:rgba(255,255,255,.82);border-color:rgba(124,58,237,.16); }
        [data-theme="light"] .pricing-featured { border-color:rgba(217,119,6,.48);background:linear-gradient(145deg,rgba(251,191,36,.28),rgba(255,255,255,.78)); }
        [data-theme="light"] .pricing-popular-badge { color:#92400e; }
        [data-theme="light"] .pricing-planned { border-color:rgba(220,38,38,.34);background:linear-gradient(145deg,rgba(254,226,226,.9),rgba(255,241,242,.82) 58%,rgba(255,255,255,.9)); }
        [data-theme="light"] .pricing-planned-badge { border-color:#b91c1c;background:linear-gradient(90deg,#991b1b,#dc2626 52%,#f87171);color:#fff; }
    </style>
</head>
<body class="pricing-bg min-h-screen text-[var(--ak-text)] antialiased">
    <header class="ak-public-topbar pricing-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl bg-[var(--ak-accent-soft)] px-3 py-2 text-sm font-bold text-[var(--ak-accent)] sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-bold text-orange-400 sm:inline-flex">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center px-3 py-2.5 text-sm font-semibold text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-lg border border-orange-400/30 bg-orange-400/20 px-3 py-2.5 text-sm font-bold text-orange-400 shadow-lg shadow-orange-400/20 transition hover:bg-orange-400/30 lg:inline-flex">{{ __('Als Tester registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col px-5 py-5 sm:px-8 lg:px-10 lg:py-3">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[.22em] text-orange-400">{{ __('Tarife') }}</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">{{ __('Der passende Zugang zu AktienKI.') }}</h1>
            <p class="mx-auto mt-1 max-w-2xl text-xs leading-5 text-[var(--ak-muted)]">{{ __('Beginne kostenlos und erweitere deinen Analyseumfang, wenn deine Anforderungen wachsen.') }}</p>
        </div>

        @php
            $planFeatures = [
                'market' => __('Marktübersicht und Basisanalysen'),
                'predictions' => __('KI-Scores und Kursprognosen'),
                'stock_access' => __('Aktienzugriff'),
                'watchlists' => __('Persönliche Watchlists'),
                'depot' => __('Musterdepot'),
                'mail' => __('Mail-Benachrichtigung bei Signalübergang'),
                'fundamentals' => __('Fundamentaldaten'),
                'smart' => __('Labeling'),
                'research' => __('Analysen'),
                'ai_questions' => __('AKI-Abfragen pro Monat'),
                'tester' => __('Strategietester'),
                'whatsapp' => __('WhatsApp-Messaging'),
                'manager' => __('Strategie Manager'),
            ];
            $plans = [
                [
                    'name' => __('Free'), 'price' => '0 €', 'suffix' => __('dauerhaft'), 'featured' => false, 'planned' => false,
                    'description' => __('Für den Einstieg in datenbasierte Aktienanalysen.'),
                    'enabled' => ['market', 'predictions', 'stock_access', 'watchlists', 'depot'],
                ],
                [
                    'name' => __('Plus'), 'price' => '9,90 €', 'suffix' => __('pro Monat'), 'featured' => false, 'planned' => false,
                    'intro_old_price' => '14,90 €',
                    'description' => __('Für aktive Anleger und eigene Strategietests.'),
                    'enabled' => ['market', 'predictions', 'stock_access', 'mail', 'watchlists', 'depot', 'fundamentals', 'smart', 'ai_questions', 'research'],
                ],
                [
                    'name' => __('Pro'), 'price' => '19,90 €', 'suffix' => __('pro Monat'), 'featured' => true, 'planned' => false,
                    'intro_old_price' => '29,90 €',
                    'description' => __('Für Anleger mit regelmäßigem Analysebedarf.'),
                    'enabled' => ['market', 'predictions', 'stock_access', 'watchlists', 'depot', 'fundamentals', 'tester', 'smart', 'ai_questions', 'manager', 'mail', 'whatsapp', 'research'],
                ],
            ];
        @endphp

        <div class="mt-7 items-stretch">
            <div class="grid items-stretch gap-5 md:grid-cols-3 md:gap-4">
                @foreach ($plans as $plan)
                    <article class="relative flex h-full flex-col rounded-2xl border p-5 lg:min-h-0 {{ $plan['featured'] ? 'pricing-featured' : ($plan['planned'] ? 'pricing-planned' : 'pricing-dashboard-card') }}">
                        @if ($plan['featured'])
                            <span class="pricing-popular-badge absolute right-5 top-5 rounded-md px-3 py-1 text-[10px] font-black uppercase tracking-wider">{{ __('Empfehlung') }}</span>
                        @elseif ($plan['planned'])
                            <span class="pricing-planned-badge absolute right-5 top-5 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider" style="background: linear-gradient(90deg, #7f1d1d 0%, #dc2626 52%, #fb7185 100%); color: #fff; border-color: rgba(254, 202, 202, .9);">{{ __('In Planung') }}</span>
                        @endif
                        <h2 class="text-xl font-black">{{ $plan['name'] }}</h2>
                        <p class="mt-2 min-h-10 text-xs leading-5 text-[var(--ak-muted)]">{{ $plan['description'] }}</p>
                        <div class="mt-3 flex items-end gap-2"><strong class="text-3xl font-black tracking-tight">{{ $plan['price'] }}</strong>@if (!empty($plan['intro_old_price']))<s class="pb-1 text-base font-bold text-[var(--ak-muted)]">{{ $plan['intro_old_price'] }}</s>@endif<span class="pb-1 text-[10px] text-[var(--ak-muted)]">{{ $plan['suffix'] }}</span></div>
                        @if (!empty($plan['intro_old_price']))
                            <span class="mt-2 inline-flex w-fit rounded-md border border-amber-300/30 bg-amber-400/10 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-amber-300">{{ __('Für die ersten 250 Nutzer') }}</span>
                        @endif
                        <div class="my-3 h-px bg-[var(--ak-border)]"></div>
                        <ul class="flex-1 space-y-1.5">
                            @foreach ($planFeatures as $featureKey => $feature)
                                @php($featureEnabled = in_array($featureKey, $plan['enabled'], true))
                                <li class="flex min-h-5 items-center gap-2 text-[10px] leading-4 {{ $featureEnabled ? 'text-[var(--ak-text)]' : 'text-slate-500/75' }}">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-md border text-[9px] font-black {{ $featureEnabled ? 'border-orange-400/30 bg-orange-400/10 text-orange-400' : 'border-slate-500/20 bg-slate-700/10 text-slate-600' }}">{{ $featureEnabled ? '✓' : '–' }}</span>
                                    <span>{{ $featureKey === 'stock_access' ? ($plan['name'] === __('Free') ? __('Zugriff auf 100 Aktien') : __('Zugriff auf alle Aktien')) : ($featureKey === 'watchlists' && $plan['name'] === __('Free') ? __('Eine Watchlist') : ($featureKey === 'research' && $plan['name'] === __('Pro') ? __('Erweiterte Analysen') : ($featureKey === 'ai_questions' ? ($plan['name'] === __('Plus') ? __('50 AKI-Fragen pro Monat') : ($plan['name'] === __('Pro') ? __('100 AKI-Abfragen pro Monat') : $feature)) : $feature))) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($plan['planned'])
                            <span class="pricing-planned-button mt-4 flex h-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold">{{ __('In Planung') }}</span>
                        @else
                            <span class="mt-4 flex h-10 shrink-0 cursor-not-allowed items-center justify-center rounded-lg border border-slate-400/25 bg-slate-400/[.08] text-sm font-bold text-[var(--ak-muted)]" aria-disabled="true">{{ __('Während der Beta deaktiviert') }}</span>
                        @endif
                    </article>
                @endforeach
            </div>

        </div>

        <p class="mx-auto mt-4 max-w-3xl shrink-0 text-center text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Alle Preise verstehen sich inklusive gesetzlicher Umsatzsteuer. AktienKI ist ein Analysewerkzeug und keine Anlageberatung. Tarife und Funktionsumfang können sich vor dem offiziellen Start noch ändern.') }}</p>
    </main>
</body>
</html>
