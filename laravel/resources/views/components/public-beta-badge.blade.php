@props(['registeredUsers' => 0])
@php($remainingBetaSlots = max(25 - (int) $registeredUsers, 0))

<div class="mb-6 w-full max-w-none shrink-0 self-start rounded-2xl border-2 border-amber-200/90 bg-gradient-to-r from-amber-400/[.34] via-orange-400/[.20] to-amber-500/[.14] px-5 py-4 text-left ring-2 ring-amber-400/20 shadow-[0_0_18px_rgba(251,191,36,.42),0_16px_48px_rgba(180,83,9,.36),inset_0_1px_0_rgba(254,243,199,.30)] sm:px-6 sm:py-5">
    @if ((int) $registeredUsers < 25)
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                <span class="inline-flex shrink-0 items-center rounded-md border border-amber-200/80 bg-amber-300/25 px-3 py-1 text-[11px] font-black uppercase tracking-[.16em] text-amber-100 shadow-[0_0_18px_rgba(251,191,36,.24)]">{{ __('Öffentliche Betaphase') }}</span>
                <span class="inline-flex items-center rounded-md border border-amber-200/35 bg-slate-950/20 px-3 py-1 text-[11px] font-black uppercase tracking-[.12em] text-amber-100">{{ __('Noch') }} {{ number_format($remainingBetaSlots, 0, ',', '.') }} {{ __('von 25 Plätzen frei') }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('contact') }}" class="inline-flex items-center rounded-md border border-amber-200/45 bg-slate-950/20 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-amber-100 transition hover:border-amber-100/70 hover:bg-amber-300/15">{{ __('Du hast Fragen? Schreib uns') }}</a>
                <a href="{{ route('register') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-amber-100/80 bg-amber-300 px-4 py-2 text-xs font-black uppercase tracking-wide text-amber-950 shadow-[0_0_18px_rgba(251,191,36,.45)] transition hover:bg-amber-200">{{ __('Registrieren') }}</a>
            </div>
        </div>
        <p class="mt-2 text-sm font-black leading-6 text-amber-50 sm:text-base">{{ __('Bald beginnt die öffentliche Betaphase. Sei von Anfang an dabei und hilf uns, AktienKI weiter zu verbessern.') }}</p>
        <p class="mt-1 text-xs font-semibold leading-5 text-amber-100/85">{{ __('Erhalte einen dauerhaft kostenlosen Zugang zum Pro-Tarif. Registriere dich schon jetzt …') }}</p>
    @else
        <span class="inline-flex items-center rounded-md border border-amber-200/80 bg-amber-300/25 px-3 py-1 text-[11px] font-black uppercase tracking-[.16em] text-amber-100">{{ __('Öffentliche Betaphase läuft') }}</span>
        <p class="mt-2 text-sm font-black leading-6 text-amber-50 sm:text-base">{{ __('Die öffentliche Betaphase läuft bereits mit 25 Nutzern. Wir sind bald für dich da.') }}</p>
    @endif
</div>
