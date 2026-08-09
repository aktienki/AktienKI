<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Easy Access – Top-3-Signale per E-Mail erhalten.') }}">
    <title>{{ __('Easy Access – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#071426] text-white antialiased">
    <div class="pointer-events-none fixed inset-0 opacity-70" aria-hidden="true"><div class="absolute left-1/2 top-0 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-cyan-400/[.10] blur-3xl"></div><div class="absolute bottom-0 right-0 h-[420px] w-[520px] rounded-full bg-amber-400/[.08] blur-3xl"></div></div>
    <header class="relative z-10 border-b border-cyan-300/15 bg-[#09182b]/85 backdrop-blur-xl"><div class="mx-auto flex h-[74px] max-w-3xl items-center justify-between px-5 sm:px-8"><a href="{{ route('welcome') }}" class="flex items-center"><x-brand-wordmark /></a><a href="{{ route('login') }}" class="rounded-lg border border-white/15 px-4 py-2 text-sm font-bold text-slate-300 hover:bg-white/[.06] hover:text-white">{{ __('Anmelden') }}</a></div></header>

    <main class="relative z-10 mx-auto flex min-h-[calc(100svh-74px)] max-w-3xl items-center px-5 py-10 sm:px-8">
        <section class="w-full rounded-3xl border border-cyan-300/25 bg-[#0c2035]/90 p-7 shadow-2xl shadow-black/25 backdrop-blur-xl sm:p-11">
            <p class="text-xs font-black uppercase tracking-[.25em] text-cyan-300">{{ __('Easy Access') }}</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-5xl">{{ __('Top-3-Signale direkt erhalten') }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">{{ __('Trage deine E-Mail-Adresse ein, bestätige dein Anlageprofil und wähle eine freigegebene Strategie. Du erhältst ausschließlich eine Nachricht, wenn ein BUY- oder SELL-Signal für eine Aktie der offiziellen Top 3 entsteht.') }}</p>

            @if (session('easy_access_subscribed'))<div class="mt-6 rounded-xl border border-emerald-300/40 bg-emerald-300/10 px-4 py-3 text-sm font-semibold text-emerald-100">{{ session('easy_access_subscribed') }}</div>@endif
            @if ($errors->any())<div class="mt-6 rounded-xl border border-rose-300/40 bg-rose-300/10 px-4 py-3 text-sm text-rose-100"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if ($invite !== '')<div class="mt-6 rounded-xl border {{ $invitationValid ? 'border-emerald-300/40 bg-emerald-300/10 text-emerald-100' : 'border-amber-300/40 bg-amber-300/10 text-amber-100' }} px-4 py-3 text-sm">{{ $invitationValid ? __('Einladungslink erkannt – dein Beta-Zugang wird berücksichtigt.') : __('Der Einladungslink ist nicht mehr gültig. Du kannst dich trotzdem anmelden.') }}</div>@endif

            @if ($strategies->isEmpty())
                <div class="mt-8 rounded-2xl border border-amber-300/35 bg-amber-300/10 p-5 text-sm leading-6 text-amber-100">{{ __('Aktuell ist noch keine öffentliche Strategie freigegeben. Bitte versuche es später erneut.') }}</div>
            @else
                <form method="POST" action="{{ route('easy-access.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <input type="hidden" name="invite" value="{{ $invite }}">
                    <label class="block text-sm font-bold text-slate-200">{{ __('E-Mail-Adresse') }}<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="du@example.com" class="mt-2 h-12 w-full rounded-xl border border-cyan-300/25 bg-[#08182b] px-4 text-white outline-none focus:border-cyan-300/70 focus:ring-4 focus:ring-cyan-300/10"></label>
                    <label class="block text-sm font-bold text-slate-200">{{ __('Strategie') }}<select name="strategy_id" required class="mt-2 h-12 w-full rounded-xl border border-cyan-300/25 bg-[#08182b] px-4 text-white outline-none focus:border-cyan-300/70"><option value="">{{ __('Strategie auswählen') }}</option>@foreach ($strategies as $strategy)<option value="{{ $strategy->id }}" @selected((string) old('strategy_id') === (string) $strategy->id)>{{ $strategy->name }}</option>@endforeach</select></label>
                    <fieldset><legend class="text-sm font-bold text-slate-200">{{ __('Dein Anlageprofil') }}</legend><div class="mt-2 grid gap-2 sm:grid-cols-3">@foreach ([['cautious', 'Vorsichtig'], ['balanced', 'Ausgewogen'], ['opportunity', 'Chancenorientiert']] as [$value, $label])<label class="cursor-pointer rounded-xl border border-white/10 bg-white/[.04] p-3 text-sm transition has-[:checked]:border-cyan-300/60 has-[:checked]:bg-cyan-300/10"><input type="radio" name="investment_profile" value="{{ $value }}" @checked(old('investment_profile', 'balanced') === $value) class="mr-2 accent-cyan-300">{{ __($label) }}</label>@endforeach</div></fieldset>
                    <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[.04] p-4 text-sm leading-5 text-slate-300"><input type="checkbox" name="accept_terms" value="1" required class="mt-1 h-4 w-4 accent-cyan-300"><span>{{ __('Ich akzeptiere die Nutzungsbedingungen und bestätige den Risikohinweis. Die Informationen sind keine Anlageberatung.') }}</span></label>
                    <p class="rounded-xl border border-amber-300/20 bg-amber-300/[.06] px-4 py-3 text-xs leading-5 text-amber-100">{{ __('Datenschutz: Wir verwenden deine E-Mail-Adresse ausschließlich für die gewählten Top-3-Signalbenachrichtigungen. Es wird keine Werbung und kein Newsletter versendet.') }}</p>
                    <button type="submit" class="w-full rounded-xl bg-cyan-300 px-5 py-3.5 font-black text-slate-950 shadow-lg shadow-cyan-300/20 transition hover:bg-cyan-200">{{ __('Top-3-Signale abonnieren') }}</button>
                </form>
            @endif
            <p class="mt-7 text-center text-xs text-slate-500">{{ __('Du kannst die Benachrichtigungen jederzeit über den Abmeldelink in der E-Mail beenden.') }}</p>
        </section>
    </main>
</body>
</html>
