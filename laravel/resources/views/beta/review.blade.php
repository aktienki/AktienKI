<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Beta-Anfrage prüfen') }} – AktienKI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--ak-bg)] text-[var(--ak-text)] antialiased">
    <main class="mx-auto max-w-3xl space-y-6 px-5 py-10 sm:px-8">
        <header class="ak-card rounded-2xl border border-amber-400/35 bg-[var(--ak-surface)] p-6 shadow-xl">
            <p class="text-xs font-black uppercase tracking-[.22em] text-amber-400">{{ __('Administration · Betatest') }}</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ __('Beta-Anfrage prüfen') }}</h1>
            <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Prüfe die Angaben, bevor du einen persönlichen und einmalig nutzbaren Registrierungscode versendest.') }}</p>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-300">{{ session('status') }}</div>
        @endif

        <section class="ak-card rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface)] p-6">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Name') }}</dt><dd class="mt-1 font-bold">{{ $contactMessage->name }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('E-Mail-Adresse') }}</dt><dd class="mt-1 font-bold">{{ $contactMessage->email }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Motivation') }}</dt><dd class="mt-2 whitespace-pre-wrap rounded-xl border border-[var(--ak-border)] bg-black/10 p-4 text-sm leading-6">{{ $contactMessage->message }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Angefragt am') }}</dt><dd class="mt-1">{{ $contactMessage->created_at?->format('d.m.Y H:i') }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Status') }}</dt><dd class="mt-1 font-bold">{{ data_get($contactMessage->meta, 'beta_invitation.sent_at') ? __('Code bereits versendet') : __('Wartet auf Prüfung') }}</dd></div>
            </dl>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ak-border)] pt-5">
                <a href="{{ route('beta.invitations') }}" class="rounded-lg border border-[var(--ak-border)] px-4 py-3 text-sm font-bold">{{ __('Zur Einladungsliste') }}</a>
                @if (! data_get($contactMessage->meta, 'beta_invitation.sent_at'))
                    <form method="POST" action="{{ route('beta.requests.approve', $contactMessage) }}" onsubmit="return confirm('{{ __('Einladungscode jetzt an diese E-Mail-Adresse senden?') }}')">
                        @csrf
                        <button class="rounded-lg bg-amber-400 px-5 py-3 font-black text-slate-950 transition hover:bg-amber-300">{{ __('Anfrage freigeben & Code senden') }} →</button>
                    </form>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
