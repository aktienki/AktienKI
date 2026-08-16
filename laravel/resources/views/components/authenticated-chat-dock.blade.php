<div id="aki-global-chat-dock" class="group fixed inset-x-0 bottom-0 z-[190] flex justify-center px-3" data-aki-global-chat-dock>
    <div class="relative w-full translate-y-[48px] transition-transform duration-300 ease-out group-hover:translate-y-0 group-focus-within:translate-y-0" style="max-width: 660px">
        <button type="button" data-aki-global-chat-open class="absolute -top-7 left-1/2 flex h-7 -translate-x-1/2 items-center gap-2 rounded-t-xl border border-b-0 border-cyan-300/35 bg-[#0a2133]/95 px-5 text-[9px] font-black uppercase tracking-[.14em] text-cyan-200 shadow-[0_-8px_28px_rgba(34,211,238,.13)] backdrop-blur-xl transition-opacity duration-150 group-hover:pointer-events-none group-hover:opacity-0 group-focus-within:pointer-events-none group-focus-within:opacity-0" aria-label="{{ __('AKI Chat öffnen') }}">
            <x-heroicon-o-chat-bubble-left-right class="h-4 w-4" />
            <span>{{ __('AKI fragen') }}</span>
            <x-heroicon-o-chevron-up class="h-3 w-3 transition-transform group-hover:rotate-180" />
        </button>
        <div class="flex h-[48px] items-center justify-between gap-4 rounded-t-2xl border border-cyan-300/25 bg-[#0a2133]/95 px-3 shadow-[0_-14px_40px_rgba(2,12,23,.38)] backdrop-blur-xl sm:px-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="relative grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-300/30 bg-cyan-400/10 text-cyan-200">
                    <x-heroicon-o-sparkles class="h-4 w-4" />
                    <i class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_7px_#34d399]"></i>
                </span>
                <span class="min-w-0"><strong class="block truncate text-xs text-slate-100">{{ __('AKI Assistent') }}</strong><small class="hidden truncate text-[9px] text-slate-400 sm:block">{{ __('Fragen zu Aktien, Prognosen und Filtern stellen') }}</small></span>
            </div>
            <button type="button" data-aki-global-chat-open class="inline-flex h-8 shrink-0 items-center gap-2 rounded-lg border border-cyan-300/35 bg-cyan-400/15 px-3 text-[10px] font-black text-cyan-100 transition hover:bg-cyan-400/25">
                {{ __('Chat starten') }} <x-heroicon-o-arrow-up-right class="h-4 w-4" />
            </button>
        </div>
    </div>
</div>

@php
    $akiBudgetService = app(\App\Services\AkiChatBudgetService::class);
    $akiChatPlan = auth()->check() ? $akiBudgetService->planCode(auth()->user()) : 'free';
    $akiChatBudget = auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('aki_chat_usages') ? $akiBudgetService->summary(auth()->user()) : null;
@endphp
<div id="aki-global-chat-modal" class="fixed inset-0 z-[250] hidden place-items-center bg-slate-950/85 p-4 backdrop-blur-md" data-aki-global-chat-modal>
    <section class="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-cyan-300/45 text-slate-100 shadow-[0_28px_90px_rgba(0,0,0,.65)]" style="background:#091b2c !important">
        <header class="flex items-center gap-3 border-b border-cyan-300/25 px-4 py-3" style="background:#102b40 !important">
            <span class="grid h-9 w-9 place-items-center rounded-xl border border-cyan-300/25 bg-cyan-400/10 text-cyan-200"><x-heroicon-o-sparkles class="h-5 w-5" /></span>
            <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Assistent') }}</p><h2 class="text-base font-black">{{ __('AKI fragen') }}</h2></div>
            <button type="button" data-aki-global-chat-clear class="ml-auto rounded-lg border border-amber-300/30 bg-amber-300/10 px-2.5 py-1.5 text-[9px] font-black text-amber-200 hover:bg-amber-300/20">{{ __('Verlauf löschen') }}</button>
            <button type="button" data-aki-global-chat-close class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/5 hover:text-white" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
        </header>
        <div class="flex flex-wrap items-center gap-2 border-b border-cyan-300/15 px-4 py-2.5" style="background:#0b2235 !important">
            <span class="text-[9px] font-black uppercase tracking-[.12em] text-slate-400">{{ __('Antwortqualität') }}</span>
            <button type="button" data-aki-chat-mode="standard" class="rounded-lg border border-cyan-300/45 bg-cyan-400/15 px-3 py-1.5 text-[10px] font-black text-cyan-100">{{ __('Standard') }}</button>
            <button type="button" data-aki-chat-mode="deep" class="rounded-lg border border-slate-600/60 bg-slate-800/70 px-3 py-1.5 text-[10px] font-black text-slate-300">{{ __('Tiefenanalyse') }} <span class="ml-1 text-amber-300">PRO</span></button>
            <span data-aki-budget class="ml-auto text-[9px] font-bold {{ ($akiChatBudget['warning'] ?? false) ? 'text-amber-300' : 'text-slate-400' }}">
                @if($akiChatBudget){{ number_format($akiChatBudget['remaining_eur'], 2, ',', '.') }} € {{ __('verfügbar') }}@endif
            </span>
        </div>
        <div data-aki-global-chat-messages class="min-h-56 flex-1 space-y-2 overflow-y-auto p-5" style="background:#0a2032 !important">
            <p class="max-w-[88%] rounded-xl border border-cyan-300/15 bg-slate-700/60 px-3 py-2 text-xs leading-5">{{ __('Hallo! Wie kann ich dir auf dieser Seite helfen?') }}</p>
        </div>
        <form data-aki-global-chat-form class="flex gap-2 border-t border-cyan-300/20 p-3" style="background:#091827 !important">
            <input data-aki-global-chat-input type="text" class="min-w-0 flex-1 rounded-xl border border-cyan-300/25 bg-slate-950/55 px-3 py-2.5 text-xs text-white placeholder:text-slate-500 focus:border-cyan-300/55 focus:outline-none" placeholder="{{ __('Stelle AKI eine Frage …') }}" autocomplete="off">
            <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2.5 text-xs font-black text-slate-950 hover:bg-cyan-400">{{ __('Senden') }}</button>
        </form>
    </section>
</div>

<script>
(() => {
    const modal = document.querySelector('[data-aki-global-chat-modal]');
    const messages = document.querySelector('[data-aki-global-chat-messages]');
    const input = document.querySelector('[data-aki-global-chat-input]');
    const storageKey = 'aktienki:global-chat';
    const plan = @js($akiChatPlan);
    let mode = 'standard';
    let history = [];
    try { history = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (_) { history = []; }

    const addMessage = (content, role) => {
        const bubble = document.createElement('p');
        bubble.className = `max-w-[88%] whitespace-pre-line rounded-xl border px-3 py-2 text-xs leading-5 ${role === 'user' ? 'ml-auto border-cyan-300/25 bg-cyan-500 text-slate-950' : 'border-cyan-300/15 bg-slate-700/60 text-slate-100'}`;
        bubble.textContent = content;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    };
    if (history.length) {
        messages.innerHTML = '';
        history.forEach(entry => addMessage(entry.content, entry.role));
    }
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('grid'); document.body.style.overflow = 'hidden'; setTimeout(() => input?.focus(), 50); };
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); document.body.style.overflow = ''; };
    document.querySelectorAll('[data-aki-global-chat-open]').forEach(button => button.addEventListener('click', open));
    document.querySelector('[data-aki-global-chat-close]')?.addEventListener('click', close);
    modal?.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    document.querySelector('[data-aki-global-chat-clear]')?.addEventListener('click', () => { history = []; localStorage.removeItem(storageKey); messages.innerHTML = ''; addMessage(@js(__('Der Chatverlauf wurde gelöscht.')), 'assistant'); });
    const modeButtons = [...document.querySelectorAll('[data-aki-chat-mode]')];
    const paintModes = () => modeButtons.forEach(button => {
        const active = button.dataset.akiChatMode === mode;
        button.classList.toggle('border-cyan-300/45', active); button.classList.toggle('bg-cyan-400/15', active); button.classList.toggle('text-cyan-100', active);
        button.classList.toggle('border-slate-600/60', !active); button.classList.toggle('bg-slate-800/70', !active); button.classList.toggle('text-slate-300', !active);
    });
    modeButtons.forEach(button => button.addEventListener('click', () => {
        if (button.dataset.akiChatMode === 'deep' && plan !== 'pro') { addMessage(@js(__('Die Tiefenanalyse ist im Pro-Tarif verfügbar.')), 'assistant'); return; }
        mode = button.dataset.akiChatMode; paintModes();
    }));
    paintModes();
    document.querySelector('[data-aki-global-chat-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const question = (input?.value || '').trim();
        if (!question) return;
        addMessage(question, 'user'); input.value = '';
        history.push({ role: 'user', content: question });
        const pending = addMessage(@js(__('AKI denkt …')), 'assistant');
        let response;
        try {
            response = await fetch(@js(route('aki.chat')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ question, messages: history.slice(-8), filters: Object.fromEntries(new URLSearchParams(window.location.search)), page: window.location.pathname, mode })
            });
        } catch (error) {
            console.error('AKI network request failed', error);
            pending.remove(); addMessage(@js(__('Die Verbindung zur KI konnte nicht hergestellt werden.')), 'assistant');
            return;
        }

        let payload = {};
        try { payload = await response.json(); }
        catch (error) { console.error('AKI response could not be parsed', error); }
        pending.remove();
        const answer = response.ok
            ? (payload.answer || @js(__('Keine Antwort erhalten.')))
            : (payload.message || `${@js(__('Die KI ist gerade nicht erreichbar.'))} (HTTP ${response.status})`);
        addMessage(answer, 'assistant');
        history.push({ role: 'assistant', content: answer });
        history = history.slice(-16);
        try { localStorage.setItem(storageKey, JSON.stringify(history)); }
        catch (error) { console.warn('AKI chat history could not be saved', error); }
        if (payload.budget) {
            try {
                const budget = document.querySelector('[data-aki-budget]');
                if (budget) { budget.textContent = `${Number(payload.budget.remaining_eur).toLocaleString('de-DE', {minimumFractionDigits:2})} € ${@js(__('verfügbar'))}`; budget.classList.toggle('text-amber-300', payload.budget.warning); }
            } catch (error) { console.warn('AKI budget display could not be updated', error); }
        }
    });
})();
</script>
