@once
<style>
    [data-cookie-consent]{--cc-bg:#071725;--cc-panel:#0c2233;--cc-line:rgba(83,226,240,.23);--cc-text:#edf9fa;--cc-muted:#9bb4ba;--cc-accent:#56e7f1;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
    .cc-backdrop{position:fixed;inset:0;z-index:9997;background:rgba(0,7,13,.58);backdrop-filter:blur(5px)}
    .cc-banner{position:fixed;z-index:9998;left:50%;bottom:18px;width:min(960px,calc(100% - 28px));transform:translateX(-50%);padding:20px;border:1px solid var(--cc-line);border-radius:20px;background:linear-gradient(135deg,rgba(13,42,56,.99),rgba(5,21,34,.99));box-shadow:0 28px 90px rgba(0,0,0,.58),inset 0 1px rgba(255,255,255,.04);color:var(--cc-text)}
    .cc-grid{display:grid;grid-template-columns:1fr auto;align-items:center;gap:24px}.cc-title{margin:0 0 6px;font-size:18px;font-weight:900}.cc-copy{margin:0;max-width:650px;color:var(--cc-muted);font-size:12px;line-height:1.65}.cc-copy a{color:#baf8fb;text-decoration:underline}.cc-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.cc-btn{min-height:42px;padding:0 14px;border:1px solid var(--cc-line);border-radius:11px;background:rgba(255,255,255,.025);color:#d6ecef;font:inherit;font-size:12px;font-weight:850;cursor:pointer}.cc-btn:hover{border-color:rgba(86,231,241,.52);background:rgba(86,231,241,.08)}.cc-primary{border-color:transparent;background:linear-gradient(135deg,#78f3fa,#31d9e6);color:#001419;box-shadow:0 10px 28px rgba(49,217,230,.16)}
    .cc-modal{position:fixed;z-index:9999;left:50%;top:50%;width:min(620px,calc(100% - 28px));max-height:calc(100vh - 30px);overflow:auto;transform:translate(-50%,-50%);padding:24px;border:1px solid var(--cc-line);border-radius:20px;background:var(--cc-bg);box-shadow:0 30px 110px rgba(0,0,0,.72);color:var(--cc-text)}.cc-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.cc-modal h2{margin:0;font-size:23px}.cc-close{width:36px;height:36px;border:1px solid var(--cc-line);border-radius:10px;background:transparent;color:#cde6e9;font-size:20px;cursor:pointer}.cc-intro{color:var(--cc-muted);font-size:12px;line-height:1.6}.cc-categories{display:grid;gap:9px;margin:18px 0}.cc-category{display:grid;grid-template-columns:1fr auto;gap:14px;padding:14px;border:1px solid rgba(86,231,241,.14);border-radius:13px;background:rgba(255,255,255,.025)}.cc-category b{font-size:13px}.cc-category p{margin:4px 0 0;color:var(--cc-muted);font-size:11px;line-height:1.5}.cc-switch{align-self:center;position:relative;width:42px;height:24px}.cc-switch input{position:absolute;opacity:0}.cc-slider{position:absolute;inset:0;border-radius:999px;background:#344b58;transition:.2s}.cc-slider:after{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:white;transition:.2s}.cc-switch input:checked+.cc-slider{background:#20cbd8}.cc-switch input:checked+.cc-slider:after{transform:translateX(18px)}.cc-switch input:disabled+.cc-slider{opacity:.72}.cc-required{align-self:center;color:#72eef6;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.cc-modal-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    .cc-settings-trigger{position:fixed;z-index:9000;left:10px;bottom:8px;padding:6px 9px;border:1px solid rgba(86,231,241,.14);border-radius:8px;background:rgba(4,18,29,.82);color:#78969d;font:inherit;font-size:9px;font-weight:800;cursor:pointer;opacity:.55}.cc-settings-trigger:hover{opacity:1;color:#c9f8fb}
    [data-cc-hidden]{display:none!important}@media(max-width:720px){.cc-banner{bottom:10px;padding:16px}.cc-grid{grid-template-columns:1fr;gap:14px}.cc-actions{justify-content:stretch}.cc-btn{flex:1}.cc-modal{padding:18px}.cc-modal-actions .cc-btn{flex:1}}
</style>
<div data-cookie-consent data-cc-hidden>
    <div class="cc-backdrop" data-cc-backdrop></div>
    <section class="cc-banner" data-cc-banner role="dialog" aria-modal="true" aria-labelledby="cc-title">
        <div class="cc-grid"><div><h2 class="cc-title" id="cc-title">{{ __('Datenschutz-Einstellungen') }}</h2><p class="cc-copy">{{ __('Wir verwenden notwendige Cookies und lokale Speicherungen für Anmeldung, Sicherheit, Sprache und Darstellung. Optionale Analyse- oder Marketingdienste werden nur mit deiner Einwilligung aktiviert.') }} <a href="{{ route('legal.show','datenschutz') }}">{{ __('Datenschutzerklärung') }}</a></p></div><div class="cc-actions"><button class="cc-btn" type="button" data-cc-necessary>{{ __('Nur notwendige') }}</button><button class="cc-btn" type="button" data-cc-customize>{{ __('Einstellungen') }}</button><button class="cc-btn cc-primary" type="button" data-cc-all>{{ __('Alle akzeptieren') }}</button></div></div>
    </section>
    <section class="cc-modal" data-cc-modal data-cc-hidden role="dialog" aria-modal="true" aria-labelledby="cc-settings-title">
        <div class="cc-modal-head"><div><div style="color:var(--cc-accent);font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase">AktienKI</div><h2 id="cc-settings-title">{{ __('Cookie-Einstellungen') }}</h2></div><button class="cc-close" type="button" data-cc-close aria-label="{{ __('Schließen') }}">×</button></div>
        <p class="cc-intro">{{ __('Du entscheidest, welche optionalen Kategorien verwendet werden dürfen. Aktuell setzt AktienKI keine externen Analyse- oder Marketing-Tags ein. Deine Auswahl gilt auch für künftig eingebundene Dienste und kann jederzeit geändert werden.') }}</p>
        <div class="cc-categories">
            <div class="cc-category"><div><b>{{ __('Notwendig') }}</b><p>{{ __('Session, CSRF-Schutz, Anmeldung, Sicherheitsfunktionen und Speicherung deiner Consent-Auswahl. Diese Funktionen können nicht deaktiviert werden.') }}</p></div><span class="cc-required">{{ __('Immer aktiv') }}</span></div>
            <div class="cc-category"><div><b>{{ __('Präferenzen') }}</b><p>{{ __('Speichert beispielsweise Sprache, Theme und von dir gewählte Darstellungsoptionen auf deinem Gerät.') }}</p></div><label class="cc-switch"><input type="checkbox" data-cc-category="preferences"><span class="cc-slider"></span></label></div>
            <div class="cc-category"><div><b>{{ __('Analyse') }}</b><p>{{ __('Hilft uns künftig, die Nutzung anonymisiert auszuwerten. Derzeit ist kein externer Analysedienst aktiv.') }}</p></div><label class="cc-switch"><input type="checkbox" data-cc-category="analytics"><span class="cc-slider"></span></label></div>
            <div class="cc-category"><div><b>{{ __('Marketing') }}</b><p>{{ __('Würde personalisierte Inhalte oder Reichweitenmessung erlauben. Derzeit sind keine Marketing-Tags aktiv.') }}</p></div><label class="cc-switch"><input type="checkbox" data-cc-category="marketing"><span class="cc-slider"></span></label></div>
        </div>
        <div class="cc-modal-actions"><button class="cc-btn" type="button" data-cc-necessary>{{ __('Nur notwendige') }}</button><button class="cc-btn cc-primary" type="button" data-cc-save>{{ __('Auswahl speichern') }}</button></div>
    </section>
</div>
<button class="cc-settings-trigger" type="button" data-cc-open>{{ __('Cookie-Einstellungen') }}</button>
<script>
(() => {
    const key='aktienki-cookie-consent-v1',version=1,root=document.querySelector('[data-cookie-consent]');
    if(!root)return;
    const banner=root.querySelector('[data-cc-banner]'),modal=root.querySelector('[data-cc-modal]'),backdrop=root.querySelector('[data-cc-backdrop]');
    const read=()=>{try{const value=JSON.parse(localStorage.getItem(key)||'null');return value?.version===version?value:null}catch(_){return null}};
    const apply=value=>{window.aktienkiCookieConsent=value;window.dispatchEvent(new CustomEvent('aktienki:cookie-consent',{detail:value}))};
    const close=()=>{root.setAttribute('data-cc-hidden','');banner.removeAttribute('data-cc-hidden');modal.setAttribute('data-cc-hidden','')};
    const openBanner=()=>{root.removeAttribute('data-cc-hidden');banner.removeAttribute('data-cc-hidden');modal.setAttribute('data-cc-hidden','')};
    const openSettings=()=>{const current=read()||{};root.removeAttribute('data-cc-hidden');banner.setAttribute('data-cc-hidden','');modal.removeAttribute('data-cc-hidden');root.querySelectorAll('[data-cc-category]').forEach(input=>input.checked=Boolean(current[input.dataset.ccCategory]));modal.querySelector('button')?.focus()};
    const save=choices=>{const value={version,necessary:true,preferences:Boolean(choices.preferences),analytics:Boolean(choices.analytics),marketing:Boolean(choices.marketing),savedAt:new Date().toISOString()};localStorage.setItem(key,JSON.stringify(value));document.cookie='aki_cookie_consent=1; Max-Age=15552000; Path=/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');apply(value);close()};
    const necessary=()=>save({preferences:false,analytics:false,marketing:false});
    root.querySelectorAll('[data-cc-necessary]').forEach(button=>button.addEventListener('click',necessary));
    root.querySelector('[data-cc-all]')?.addEventListener('click',()=>save({preferences:true,analytics:true,marketing:true}));
    root.querySelector('[data-cc-customize]')?.addEventListener('click',openSettings);
    root.querySelector('[data-cc-save]')?.addEventListener('click',()=>save(Object.fromEntries([...root.querySelectorAll('[data-cc-category]')].map(input=>[input.dataset.ccCategory,input.checked]))));
    root.querySelector('[data-cc-close]')?.addEventListener('click',()=>read()?close():openBanner());
    backdrop.addEventListener('click',()=>read()?close():openBanner());
    document.querySelector('[data-cc-open]')?.addEventListener('click',openSettings);
    window.addEventListener('aktienki:open-cookie-settings',openSettings);
    const current=read();if(current){apply(current);close()}else{openBanner()}
})();
</script>
@endonce
