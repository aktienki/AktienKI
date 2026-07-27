const storageKey = 'aktienki-theme';
const versionKey = 'aktienki-theme-version';
const lightVersion = 'light-default-v1';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function selectedTheme() {
    if (localStorage.getItem(versionKey) !== lightVersion) {
        localStorage.setItem(storageKey, 'light');
        localStorage.setItem(versionKey, lightVersion);
    }

    return localStorage.getItem(storageKey) || 'light';
}

function resolvedTheme(theme = selectedTheme()) {
    if (document.documentElement.dataset.themeLocked === 'dark') return 'dark';

    return theme === 'system' ? (media.matches ? 'dark' : 'light') : theme;
}

function applyTheme(theme = selectedTheme()) {
    const resolved = resolvedTheme(theme);
    document.documentElement.dataset.theme = resolved;
    document.documentElement.style.colorScheme = resolved;
    document.querySelectorAll('[data-theme-icon]').forEach((icon) => {
        icon.classList.toggle('hidden', icon.dataset.themeIcon !== resolved);
    });
    window.dispatchEvent(new CustomEvent('aktienki:theme-changed', { detail: { theme: resolved } }));
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme();
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = resolvedTheme() === 'dark' ? 'light' : 'dark';
            localStorage.setItem(storageKey, next);
            applyTheme(next);
        });
    });
});

media.addEventListener('change', () => {
    if (selectedTheme() === 'system') applyTheme('system');
});

window.addEventListener('storage', (event) => {
    if (event.key === storageKey) applyTheme();
});
