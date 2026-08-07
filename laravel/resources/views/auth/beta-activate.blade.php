<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Beta freischalten – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#090d22] text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg rounded-2xl border border-amber-300/30 bg-slate-800/70 p-7 shadow-2xl shadow-black/30 sm:p-9">
            <a href="{{ route('welcome') }}" class="mb-7 inline-flex"><x-brand-wordmark /></a>
            <div class="mb-5 inline-flex rounded-lg border border-amber-300/45 bg-amber-300/15 px-3 py-1.5 text-xs font-black uppercase tracking-[.16em] text-amber-200">{{ __('Beta-Zugang') }}</div>
            <h1 class="text-2xl font-bold">{{ __('Fast geschafft') }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('Deine E-Mail-Adresse ist bestätigt. Gib jetzt den persönlichen Freischaltcode aus der E-Mail ein, um deinen Beta-Zugang zu aktivieren.') }}</p>
            <p class="mt-4 rounded-lg border border-amber-300/25 bg-amber-300/[.08] px-3 py-2 text-xs leading-5 text-amber-100">{{ config('aktienki.beta.phase_ended', false) ? __('Du erhältst jetzt drei Monate Pro kostenlos. Danach wird der Pro-Tarif kostenpflichtig; vor Ablauf informieren wir dich per E-Mail.') : __('Während der laufenden Beta ist Pro kostenlos. Nach dem offiziellen Beta-Ende startet deine dreimonatige kostenlose Pro-Testphase; vor Ablauf informieren wir dich per E-Mail.') }}</p>
            <form method="POST" action="{{ route('beta.activation.complete') }}" class="mt-6">
                @csrf
                <label for="beta_code" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-amber-300">{{ __('Freischaltcode') }}</label>
                <input id="beta_code" name="beta_code" type="text" required autofocus autocomplete="off" placeholder="AB12-CD34" class="h-12 w-full rounded-lg border border-amber-300/35 bg-[#0b1830] px-4 font-bold tracking-[.16em] text-amber-50 uppercase outline-none focus:border-amber-300/70 focus:ring-4 focus:ring-amber-300/10">
                @error('beta_code')<p class="mt-2 text-xs text-rose-300">{{ $message }}</p>@enderror
                <button class="mt-4 h-11 w-full rounded-lg border border-amber-300/45 bg-amber-300/20 font-bold text-amber-100 transition hover:bg-amber-300/30">{{ __('Beta-Zugang aktivieren') }}</button>
            </form>
            <p class="mt-4 text-center text-xs text-slate-500"><a href="mailto:{{ config('aktienki.beta.contact_email') }}" class="text-amber-200 hover:text-white">{{ __('Code nicht erhalten? Schreib uns.') }}</a></p>
        </section>
    </main>
</body>
</html>
