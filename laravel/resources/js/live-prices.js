import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const authenticated = document.querySelector('meta[name="authenticated-user"]');

if (authenticated) {
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? 'aktienki',
        wsHost: window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        },
    });

    const elements = [...document.querySelectorAll('[data-live-symbol]')];
    const symbols = [...new Set(elements.map(element => element.dataset.liveSymbol).filter(Boolean))].slice(0, 8);

    if (symbols.length) {
        let providerToSource = {};
        const subscribe = () => fetch('/live-prices/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ symbols }),
            })
                .then(response => response.ok ? response.json() : Promise.reject(response))
                .then(({ symbols: mapping }) => {
                    providerToSource = Object.fromEntries(
                        Object.entries(mapping).map(([source, provider]) => [provider, source]),
                    );
                })
                .catch(() => {});

        subscribe();
        window.setInterval(subscribe, 60_000);

        echo.private('market-prices').listen('.price.updated', event => {
            const sourceSymbol = providerToSource[event.symbol] ?? event.symbol;
            document.querySelectorAll(`[data-live-symbol="${CSS.escape(sourceSymbol)}"]`).forEach(element => {
                const decimals = Number(element.dataset.liveDecimals ?? 2);
                const currency = element.dataset.liveCurrency ?? '';
                element.textContent = `${Number(event.price).toLocaleString(document.documentElement.lang, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                })}${currency ? ` ${currency}` : ''}`;
                element.dataset.liveUpdatedAt = String(event.timestamp);
            });
            document.querySelectorAll(`[data-live-time-symbol="${CSS.escape(sourceSymbol)}"]`).forEach(element => {
                const timestamp = Number(event.timestamp);
                if (!Number.isFinite(timestamp)) return;
                element.textContent = new Date(timestamp * 1000).toLocaleTimeString(
                    document.documentElement.lang,
                    {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        timeZone: 'Europe/Berlin',
                    },
                );
            });
        });
    }
}
