<dialog id="ak-confirmation-modal" class="m-auto w-[min(92vw,30rem)] overflow-hidden rounded-2xl border border-amber-400/30 bg-[#111c31] p-0 text-left text-slate-100 shadow-[0_28px_90px_rgba(0,0,0,.58)] backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <div class="border-b border-amber-400/20 bg-gradient-to-r from-amber-400/[.10] to-transparent px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-amber-400/30 bg-amber-400/10 text-amber-300"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></span>
            <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-amber-400">{{ __('Bestätigung') }}</p><h2 data-confirm-title class="mt-0.5 text-base font-black text-white">{{ __('Aktion bestätigen') }}</h2></div>
        </div>
    </div>
    <div class="px-5 py-5">
        <p data-confirm-message class="text-sm leading-6 text-slate-300"></p>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-confirm-cancel class="h-10 rounded-xl border border-white/10 bg-white/[.04] px-4 text-xs font-black text-slate-300 transition hover:border-white/20 hover:bg-white/[.08]">{{ __('Abbrechen') }}</button>
            <button type="button" data-confirm-accept class="h-10 rounded-xl border border-rose-400/40 bg-rose-400/12 px-5 text-xs font-black text-rose-300 transition hover:border-rose-300/65 hover:bg-rose-400/20">{{ __('Bestätigen') }}</button>
        </div>
    </div>
</dialog>

<style>
    :root[data-theme="light"] #ak-confirmation-modal {
        border-color: rgba(180, 83, 9, .28);
        background: #fff;
        color: #0f172a;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .22);
    }
    :root[data-theme="light"] #ak-confirmation-modal [data-confirm-title] { color: #0f172a; }
    :root[data-theme="light"] #ak-confirmation-modal [data-confirm-message] { color: #475569; }
    :root[data-theme="light"] #ak-confirmation-modal [data-confirm-cancel] { border-color: rgba(71,85,105,.18); background: #f8fafc; color: #334155; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.querySelector('#ak-confirmation-modal');
        if (!modal?.showModal) return;
        const title = modal.querySelector('[data-confirm-title]');
        const message = modal.querySelector('[data-confirm-message]');
        const accept = modal.querySelector('[data-confirm-accept]');
        const cancel = modal.querySelector('[data-confirm-cancel]');
        let pendingForm = null;
        let pendingSubmitter = null;

        const messageFromInlineConfirm = source => {
            const match = String(source || '').match(/confirm\((.+)\)/s);
            if (!match) return null;
            try { return JSON.parse(match[1]); } catch (_) {
                return match[1].replace(/^['"]|['"]$/g, '');
            }
        };
        document.querySelectorAll('form[onsubmit*="confirm("]').forEach(form => {
            form.dataset.confirm = messageFromInlineConfirm(form.getAttribute('onsubmit'))
                || @json(__('Möchtest du diese Aktion wirklich ausführen?'));
            form.removeAttribute('onsubmit');
            form.onsubmit = null;
        });
        document.querySelectorAll('button[onclick*="confirm("], input[onclick*="confirm("]').forEach(button => {
            const form = button.form || button.closest('form');
            if (!form) return;
            form.dataset.confirm = messageFromInlineConfirm(button.getAttribute('onclick'))
                || @json(__('Möchtest du diese Aktion wirklich ausführen?'));
            button.removeAttribute('onclick');
            button.onclick = null;
        });
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            const destructive = form.querySelector('input[name="_method"][value="DELETE"]');
            if (destructive) {
                form.dataset.confirmTitle ||= @json(__('Dauerhaft löschen?'));
                form.dataset.confirmAction ||= @json(__('Löschen'));
            }
        });

        document.addEventListener('submit', event => {
            const form = event.target.closest?.('form[data-confirm]');
            if (!form || form.dataset.confirmApproved === '1') return;
            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter || null;
            title.textContent = form.dataset.confirmTitle || @json(__('Aktion bestätigen'));
            message.textContent = form.dataset.confirm || @json(__('Möchtest du diese Aktion wirklich ausführen?'));
            accept.textContent = form.dataset.confirmAction || @json(__('Bestätigen'));
            modal.showModal();
        }, true);

        const close = () => { pendingForm = null; pendingSubmitter = null; modal.close(); };
        cancel.addEventListener('click', close);
        modal.addEventListener('cancel', event => { event.preventDefault(); close(); });
        modal.addEventListener('click', event => {
            if (event.target === modal) close();
        });
        accept.addEventListener('click', () => {
            if (!pendingForm) return close();
            const form = pendingForm;
            const submitter = pendingSubmitter;
            form.dataset.confirmApproved = '1';
            modal.close();
            submitter ? form.requestSubmit(submitter) : form.requestSubmit();
            delete form.dataset.confirmApproved;
            pendingForm = null;
            pendingSubmitter = null;
        });
    });
</script>
