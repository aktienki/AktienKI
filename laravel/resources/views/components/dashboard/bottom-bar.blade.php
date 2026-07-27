<nav aria-label="{{ __('Dashboard Navigation') }}" class="ak-dashboard-bottom-bar fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-[#120d22]/94 pb-[env(safe-area-inset-bottom)] shadow-[0_-18px_55px_rgba(0,0,0,.32)] backdrop-blur-2xl">
    <div class="grid h-[4.5rem] w-full grid-cols-[3.5rem_minmax(0,1fr)_3.5rem] items-center px-2 sm:grid-cols-[10rem_minmax(0,1fr)_10rem] sm:px-5">
        <div class="ak-bottom-status flex min-w-0 items-center gap-2 text-slate-400 sm:pl-2">
            <span class="ak-bottom-status-icon relative flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-emerald-400/15 bg-emerald-400/[.06] text-emerald-300">
                <x-heroicon-o-cpu-chip class="h-4 w-4" />
                <i class="absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399]"></i>
            </span>
            <span class="hidden min-w-0 sm:block">
                <strong class="block truncate text-[10px] font-bold text-slate-300">{{ __('KI-Status') }}</strong>
                <small class="block text-[9px] text-emerald-400">{{ __('Bereit') }}</small>
            </span>
        </div>

        <div aria-hidden="true"></div>

        <div class="flex justify-end sm:pr-2">
            <button type="button" disabled aria-label="{{ __('AKI Chat – demnächst verfügbar') }}" title="{{ __('AKI Chat – demnächst verfügbar') }}" class="ak-bottom-chat relative flex h-10 w-10 items-center justify-center rounded-xl border border-violet-400/25 bg-violet-500/10 text-violet-300 shadow-lg shadow-violet-950/20 transition disabled:cursor-default sm:h-11 sm:w-11">
                <x-heroicon-o-chat-bubble-left-right class="h-5 w-5" />
                <span class="ak-bottom-chat-badge absolute -right-1 -top-1 rounded-full bg-violet-500 px-1.5 py-0.5 text-[7px] font-black uppercase tracking-wide text-white">AKI</span>
            </button>
        </div>
    </div>
</nav>
