<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Beta-Einladungen – AktienKI') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--ak-bg)] text-[var(--ak-text)] antialiased">
    <main class="mx-auto max-w-5xl space-y-6 px-5 py-8 sm:px-8">
        <header class="ak-card rounded-2xl border border-amber-400/35 bg-[var(--ak-surface)] p-6 shadow-xl">
            <p class="text-xs font-black uppercase tracking-[.22em] text-amber-400">{{ __('Administration') }}</p>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">{{ __('Beta-Einladungslinks') }}</h1>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Erstelle zeitlich begrenzte Links für ausgewählte Tester.') }}</p>
                </div>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-[var(--ak-border)] px-4 py-2 text-sm font-bold">{{ __('Zum Dashboard') }}</a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-300">{{ session('status') }}</div>
        @endif

        @if ($generatedUrl)
            <section class="rounded-2xl border border-amber-400/50 bg-amber-400/10 p-5">
                <p class="text-xs font-black uppercase tracking-[.16em] text-amber-300">{{ __('Link jetzt kopieren') }}</p>
                <p class="mt-2 break-all rounded-lg bg-black/20 px-3 py-2 font-mono text-sm text-white">{{ $generatedUrl }}</p>
                <p class="mt-2 text-xs text-amber-100/80">{{ __('Aus Sicherheitsgründen wird der Token nur in diesem Browser-Schritt angezeigt.') }}</p>
            </section>
        @endif

        <section class="ak-card rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface)] p-6">
            <h2 class="text-lg font-black">{{ __('Neuen Link erstellen') }}</h2>
            <form method="POST" action="{{ route('beta.invitations.store') }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                @csrf
                <label class="text-sm font-semibold sm:col-span-1">{{ __('Bezeichnung') }}
                    <input name="label" value="{{ old('label') }}" placeholder="z. B. Tester August" class="mt-1 h-11 w-full rounded-lg border border-[var(--ak-border)] bg-black/10 px-3" />
                </label>
                <label class="text-sm font-semibold">{{ __('Max. Nutzungen') }}
                    <input name="max_uses" type="number" min="1" max="1000" value="{{ old('max_uses', 1) }}" required class="mt-1 h-11 w-full rounded-lg border border-[var(--ak-border)] bg-black/10 px-3" />
                </label>
                <label class="text-sm font-semibold">{{ __('Gültig bis (optional)') }}
                    <input name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="mt-1 h-11 w-full rounded-lg border border-[var(--ak-border)] bg-black/10 px-3" />
                </label>
                <button class="rounded-lg bg-amber-400 px-5 py-3 font-black text-slate-950 transition hover:bg-amber-300 sm:col-span-3 sm:justify-self-start">{{ __('Einladungslink generieren') }}</button>
            </form>
        </section>

        <section class="ak-card overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-surface)]">
            <div class="border-b border-[var(--ak-border)] px-6 py-4"><h2 class="font-black">{{ __('Bestehende Links') }}</h2></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-xs uppercase tracking-wider text-[var(--ak-muted)]"><tr><th class="px-6 py-3">{{ __('Bezeichnung') }}</th><th class="px-6 py-3">{{ __('Nutzung') }}</th><th class="px-6 py-3">{{ __('Ablauf') }}</th><th class="px-6 py-3">{{ __('Status') }}</th></tr></thead><tbody class="divide-y divide-[var(--ak-border)]">@forelse ($links as $link)<tr><td class="px-6 py-3 font-semibold">{{ $link->label ?: __('Ohne Bezeichnung') }}</td><td class="px-6 py-3">{{ $link->uses_count }} / {{ $link->max_uses }}</td><td class="px-6 py-3">{{ $link->expires_at?->format('d.m.Y H:i') ?: '—' }}</td><td class="px-6 py-3"><span class="rounded-md border px-2 py-1 text-xs font-bold {{ $link->isUsable() ? 'border-emerald-400/40 text-emerald-300' : 'border-rose-400/40 text-rose-300' }}">{{ $link->isUsable() ? __('Aktiv') : __('Abgelaufen / voll') }}</span></td></tr>@empty<tr><td colspan="4" class="px-6 py-8 text-center text-[var(--ak-muted)]">{{ __('Noch keine Links erstellt.') }}</td></tr>@endforelse</tbody></table></div>
            @if ($links->hasPages()) <div class="border-t border-[var(--ak-border)] px-6 py-4">{{ $links->links() }}</div> @endif
        </section>
    </main>
</body>
</html>
