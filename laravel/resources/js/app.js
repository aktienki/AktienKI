// resources/js/app.js

import './bootstrap';
import './preferences';
import './market-map';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Remember the last distinct in-app location so page-level back links retain
// the real origin, including filters and query parameters. A reload does not
// overwrite that origin.
const navigationCurrentKey = 'aktienki.navigation.current';
const navigationPreviousKey = 'aktienki.navigation.previous';
const currentLocation = `${window.location.pathname}${window.location.search}${window.location.hash}`;

try {
    const rememberedCurrent = window.sessionStorage.getItem(navigationCurrentKey);
    if (rememberedCurrent && rememberedCurrent !== currentLocation) {
        window.sessionStorage.setItem(navigationPreviousKey, rememberedCurrent);
    }
    window.sessionStorage.setItem(navigationCurrentKey, currentLocation);
} catch (_) {
    // The server-rendered fallback URL remains usable without session storage.
}

document.addEventListener('click', (event) => {
    const backLink = event.target.closest('a[data-back-link]');
    if (!backLink || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    try {
        const rememberedPrevious = window.sessionStorage.getItem(navigationPreviousKey);
        if (!rememberedPrevious || rememberedPrevious === currentLocation) return;

        const destination = new URL(rememberedPrevious, window.location.origin);
        if (destination.origin !== window.location.origin) return;

        event.preventDefault();
        window.location.assign(`${destination.pathname}${destination.search}${destination.hash}`);
    } catch (_) {
        // Fall through to the link's explicit fallback URL.
    }
});

const enhanceScreenerFilterSelects = () => {
    document.querySelectorAll('.screener-filter-bar select:not([data-custom-select])').forEach((select) => {
        select.dataset.customSelect = 'true';
        const shell = document.createElement('div');
        shell.className = 'screener-custom-select';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'screener-custom-select-trigger';
        const value = document.createElement('span');
        const arrow = document.createElement('i');
        arrow.textContent = '⌄';
        trigger.append(value, arrow);
        const menu = document.createElement('div');
        menu.className = 'screener-custom-select-menu';

        [...select.options].forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'screener-custom-select-option';
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            item.addEventListener('click', () => {
                select.value = option.value;
                value.textContent = option.textContent;
                menu.classList.remove('is-open');
                shell.classList.remove('is-open');
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            menu.append(item);
        });

        value.textContent = select.selectedOptions[0]?.textContent || '';
        trigger.addEventListener('click', () => {
            document.querySelectorAll('.screener-custom-select-menu.is-open').forEach((openMenu) => {
                if (openMenu !== menu) openMenu.classList.remove('is-open');
            });
            menu.classList.toggle('is-open');
            shell.classList.toggle('is-open', menu.classList.contains('is-open'));
        });
        select.parentNode.insertBefore(shell, select);
        shell.append(select, trigger, menu);
    });
};

document.addEventListener('DOMContentLoaded', enhanceScreenerFilterSelects);
document.addEventListener('click', (event) => {
    if (!event.target.closest?.('.screener-custom-select')) {
        document.querySelectorAll('.screener-custom-select-menu.is-open').forEach((menu) => menu.classList.remove('is-open'));
        document.querySelectorAll('.screener-custom-select.is-open').forEach((shell) => shell.classList.remove('is-open'));
    }
});

// ApexCharts and its chart factories are by far the largest frontend module.
// Load them only on pages that actually contain a chart target. Likewise, do
// not initialise the realtime stack on pages without live-price elements.
const chartTargetSelector = [
    '#personal-dashboard',
    '[x-data^="sparkline("]',
    '[x-data^="candlestick("]',
    '[x-data^="dailyAiScoreChart("]',
    '#stock-detail-page',
    '#strategy-depot-page',
    '#filtered-backtest-result-chart',
    '.ak-top3-chart',
    '.aki-indicator-card__chart',
    '[data-screener-chart]',
    '.ak-portfolio-line-chart',
].join(',');

let chartModulePromise;
window.loadAktienKiCharts = () => {
    chartModulePromise ??= import('./charts').then(() => {
        window.dispatchEvent(new CustomEvent('aktienki:charts-ready'));
    });

    return chartModulePromise;
};

if (document.querySelector(chartTargetSelector)) {
    await window.loadAktienKiCharts();
}

if (document.querySelector('meta[name="authenticated-user"]') && document.querySelector('[data-live-symbol]')) {
    await import('./live-prices');
}

Alpine.start();

// Give native and custom dialogs one shared surface hook. This keeps modal
// contrast consistent even when individual views still use legacy utility
// colors intended for the dark theme.
const registerModalSurfaces = (scope = document) => {
    const candidates = scope.matches?.('dialog,[role="dialog"][aria-modal="true"]')
        ? [scope]
        : scope.querySelectorAll?.('dialog,[role="dialog"][aria-modal="true"]') ?? [];

    candidates.forEach((candidate) => {
        if (candidate.matches('.fixed.inset-0')) {
            const panel = [...candidate.children].find((child) =>
                child.matches?.('section,form,[class*="max-w-"]') && !child.matches('button'),
            );
            panel?.classList.add('ak-modal-panel');
            candidate.classList.add('ak-modal-overlay');
            return;
        }
        candidate.classList.add('ak-modal-panel');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    registerModalSurfaces();
    new MutationObserver((mutations) => mutations.forEach((mutation) =>
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) registerModalSurfaces(node);
        }),
    )).observe(document.body, { childList: true, subtree: true });
});

// Keep every application donut on the same expressive light-theme scale.
// Quality metrics run red -> orange -> green; risk runs in reverse.
const lightDonutSelectors = [
    '.screener-metric-donut',
    '.screener-mobile-donut',
    '.ak-prediction-donut',
    '.ak-market-score-donut',
    '.ak-screener-donut',
    '.ak-confidence-donut',
    '.ak-risk-donut',
    '.signal-history-donut',
].join(',');

const mixRgb = (from, to, amount) => from.map((channel, index) =>
    Math.round(channel + ((to[index] - channel) * amount)),
);

const lightDonutColor = (percent, isRisk) => {
    const red = [220, 38, 38];
    const orange = [249, 115, 22];
    const yellow = [234, 179, 8];
    const green = [5, 150, 105];
    const normalized = Math.max(0, Math.min(100, isRisk ? 100 - percent : percent));
    const rgb = normalized <= 35
        ? mixRgb(red, orange, normalized / 35)
        : normalized <= 65
            ? mixRgb(orange, yellow, (normalized - 35) / 30)
            : mixRgb(yellow, green, (normalized - 65) / 35);

    return `rgb(${rgb.join(' ')})`;
};

// Dark surfaces need a brighter midpoint than amber. Keep the semantic
// red/green endpoints, but move average quality values to a clear yellow.
const darkDonutColor = (percent, isRisk) => {
    const red = [251, 113, 133];
    const orange = [251, 146, 60];
    const yellow = [250, 204, 21];
    const green = [52, 211, 153];
    const normalized = Math.max(0, Math.min(100, isRisk ? 100 - percent : percent));
    const rgb = normalized <= 35
        ? mixRgb(red, orange, normalized / 35)
        : normalized <= 65
            ? mixRgb(orange, yellow, (normalized - 35) / 30)
            : mixRgb(yellow, green, (normalized - 65) / 35);

    return `rgb(${rgb.join(' ')})`;
};

const applyLightDonutPalette = (scope = document) => {
    const donuts = scope.matches?.(lightDonutSelectors)
        ? [scope]
        : scope.querySelectorAll?.(lightDonutSelectors) ?? [];

    donuts.forEach((donut) => {
        const label = `${donut.getAttribute('aria-label') || ''} ${donut.querySelector('small')?.textContent || ''}`;
        const profitFactor = parseFloat(donut.getAttribute('data-profit-factor'));
        if (Number.isFinite(profitFactor) || /profit.?factor|profitfaktor/i.test(label)) {
            const value = Number.isFinite(profitFactor)
                ? profitFactor
                : parseFloat(donut.getAttribute('aria-valuenow'));
            if (Number.isFinite(value)) {
                const orange = [234, 88, 12];
                const yellow = [234, 179, 8];
                const green = [5, 150, 105];
                const rgb = value <= 1.2
                    ? mixRgb(orange, yellow, Math.max(0, Math.min(1, (value - 0.8) / 0.4)))
                    : mixRgb(yellow, green, Math.max(0, Math.min(1, (value - 1.2) / 0.6)));
                const color = `rgb(${rgb.join(' ')})`;
                donut.style.setProperty('--donut-value', `${Math.max(0, Math.min(100, (value / 3) * 100))}%`);
                donut.style.setProperty('--donut-color', color);
                donut.style.setProperty('--ak-light-donut-color', color);
                donut.style.setProperty('--ak-dark-donut-color', darkDonutColor((value / 3) * 100, false));
                donut.style.setProperty('--pf-overflow-value', `${Math.max(0, Math.min(100, ((value - 3) / 3) * 100))}%`);
                donut.style.setProperty('--pf-overflow-color', 'rgb(5 150 105)');
                donut.classList.toggle('ak-profit-factor-overflow', value > 3);
                return;
            }
        }
        const profitPerTrade = parseFloat(donut.getAttribute('data-profit-per-trade'));
        if (Number.isFinite(profitPerTrade)) {
            const yellow = [234, 179, 8];
            const green = [5, 150, 105];
            const transition = profitPerTrade < 1
                ? 0
                : Math.max(0, Math.min(1, (profitPerTrade - 1) / 1));
            const rgb = mixRgb(yellow, green, transition);
            donut.style.setProperty('--ak-light-donut-color', `rgb(${rgb.join(' ')})`);
            donut.style.setProperty('--ak-dark-donut-color', darkDonutColor(Math.max(0, Math.min(100, profitPerTrade * 50)), false));
            return;
        }
        const rawValue = [
            donut.getAttribute('aria-valuenow'),
            donut.style.getPropertyValue('--donut-value'),
            donut.style.getPropertyValue('--mobile-donut-value'),
            donut.style.getPropertyValue('--value'),
            donut.style.getPropertyValue('--confidence'),
            donut.style.getPropertyValue('--risk'),
        ].find((candidate) => candidate !== null && String(candidate).trim() !== '')
            ?? (parseFloat(donut.style.getPropertyValue('--ak-score-angle')) / 3.6);
        const max = parseFloat(donut.getAttribute('aria-valuemax') || '100');
        const value = parseFloat(String(rawValue).replace(',', '.'));
        if (!Number.isFinite(value) || !Number.isFinite(max) || max <= 0) return;

        const isRisk = donut.classList.contains('ak-risk-donut') || /risk|risiko/i.test(label);
        donut.style.setProperty('--ak-light-donut-color', lightDonutColor((value / max) * 100, isRisk));
        donut.style.setProperty('--ak-dark-donut-color', darkDonutColor((value / max) * 100, isRisk));
    });
};

document.addEventListener('DOMContentLoaded', () => {
    applyLightDonutPalette();
    new MutationObserver((mutations) => mutations.forEach((mutation) =>
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) applyLightDonutPalette(node);
        }),
    )).observe(document.body, { childList: true, subtree: true });
});

// Small, dependency-free assistant modal used by Smart Selection. This
// listener intentionally does not rely on Alpine so it still works if a
// page-level Alpine component has malformed state during a test.
document.addEventListener('click', (event) => {
    const openButton = event.target.closest?.('[data-aki-chat-open]');
    const modal = document.querySelector('[data-aki-chat-modal]');
    if (!modal) return;
    if (openButton) {
        modal.style.display = 'grid';
        return;
    }
    if (event.target === modal || event.target.closest?.('[data-aki-chat-close]')) {
        modal.style.display = 'none';
    }
});
