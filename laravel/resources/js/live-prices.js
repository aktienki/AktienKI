import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const authenticated = document.querySelector('meta[name="authenticated-user"]');
const realtimeMarketData = document.querySelector('meta[name="realtime-market-data"]')?.content === '1';

if (authenticated) {
    let echo = null;
    if (realtimeMarketData) {
        window.Pusher = Pusher;

        const secureSocket = window.location.protocol === 'https:';
        echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? 'aktienki',
        wsHost: window.location.hostname,
        wsPort: secureSocket ? 80 : Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: secureSocket ? 443 : Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: secureSocket,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        },
        });
    }

    const elements = [...document.querySelectorAll('[data-live-symbol]')];
    const symbols = [...new Set(elements.map(element => element.dataset.liveSymbol).filter(Boolean))].slice(0, 100);

    if (symbols.length) {
        let providerToSource = {};
        const latestTimestamps = new Map();
        const applyPrice = (sourceSymbol, price, timestamp, currency = '', realtime = realtimeMarketData) => {
            const previousTimestamp = latestTimestamps.get(sourceSymbol) ?? 0;
            if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(timestamp) || timestamp < previousTimestamp) return;
            latestTimestamps.set(sourceSymbol, timestamp);
            document.querySelectorAll(`[data-live-symbol="${CSS.escape(sourceSymbol)}"]`).forEach(element => {
                const decimals = Number(element.dataset.liveDecimals ?? 2);
                const displayCurrency = element.dataset.liveCurrency ?? currency;
                element.textContent = `${price.toLocaleString(document.documentElement.lang, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                })}${displayCurrency ? ` ${displayCurrency}` : ''}`;
                element.dataset.liveUpdatedAt = String(timestamp);
            });
            document.querySelectorAll(`[data-live-time-symbol="${CSS.escape(sourceSymbol)}"]`).forEach(element => {
                element.textContent = new Date(timestamp * 1000).toLocaleTimeString(
                    document.documentElement.lang,
                    { hour:'2-digit', minute:'2-digit', second:'2-digit', timeZone:'Europe/Berlin' },
                );
            });
            window.dispatchEvent(new CustomEvent('aktienki:live-price', {
                detail: { symbol:sourceSymbol, price, timestamp, realtime },
            }));
        };
        const subscribe = () => !realtimeMarketData ? Promise.resolve() : fetch('/live-prices/subscribe', {
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

        const pollQuotes = async () => {
            await fetch(`/recommendations/live-quotes?cached_only=1&symbols=${encodeURIComponent(symbols.join(','))}`, {
                credentials:'same-origin', headers:{ Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' },
            }).then(response => response.ok ? response.json() : null).then(payload => {
                Object.entries(payload?.quotes ?? {}).forEach(([symbol, quote]) => {
                    if (!Number.isFinite(Number(quote?.price))) return;
                    applyPrice(symbol, Number(quote.price), Number(quote.timestamp), String(quote.currency ?? ''), quote.realtime === true);
                });
            }).catch(() => {});
        };

        subscribe();
        pollQuotes();
        if (realtimeMarketData) window.setInterval(subscribe, 60_000);
        window.setInterval(pollQuotes, realtimeMarketData ? 30_000 : 60_000);

        echo?.channel('market-prices').listen('.price.updated', event => {
            const sourceSymbol = providerToSource[event.symbol] ?? event.symbol;
            const timestamp = Number(event.timestamp);
            applyPrice(sourceSymbol, Number(event.price), timestamp, '', true);
        });
    }
}
