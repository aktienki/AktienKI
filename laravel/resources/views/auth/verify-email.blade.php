<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('E-Mail bestätigen – AktienKI') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#090d22] text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg rounded-2xl border border-amber-300/30 bg-slate-800/70 p-7 shadow-2xl shadow-black/30 backdrop-blur sm:p-9">
            <a href="{{ route('welcome') }}" class="mb-7 inline-flex"><x-brand-wordmark /></a>
            <div class="mb-5 inline-flex rounded-lg border border-amber-300/45 bg-amber-300/15 px-3 py-1.5 text-xs font-black uppercase tracking-[.16em] text-amber-200">{{ __('Beta-Zugang') }}</div>
            <h1 class="text-2xl font-bold">{{ __('E-Mail-Adresse bestätigen') }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('Wir haben dir einen Bestätigungslink an deine E-Mail-Adresse gesendet. Bitte bestätige sie, bevor du AktienKI nutzt.') }}</p>
            @if (session('status') === 'verification-link-sent')
                <p class="mt-4 rounded-lg border border-emerald-300/30 bg-emerald-300/10 px-3 py-2 text-xs font-semibold text-emerald-200">{{ __('Ein neuer Bestätigungslink wurde gesendet.') }}</p>
            @endif
            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <button class="h-11 w-full rounded-lg border border-amber-300/45 bg-amber-300/20 font-bold text-amber-100 transition hover:bg-amber-300/30">{{ __('Bestätigungslink erneut senden') }}</button>
            </form>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="w-full py-2 text-sm font-semibold text-slate-400 transition hover:text-white">{{ __('Abmelden') }}</button>
            </form>
        </section>
    </main>
</body>
</html>
