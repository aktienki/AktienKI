// Lightweight world map component. It intentionally has no chart-library
// dependency so Livewire-rendered market cards can always initialize it.
window.worldMarketMap = (countryScores = {}, indicesUrl = '/indices', geoJsonUrl = '/assets/ne_50m_admin_0_countries.geojson') => ({
    error: false,
    selectedCountry: null,

    async init() {
        try {
            const response = await fetch(geoJsonUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Map data unavailable (${response.status})`);

            const geojson = await response.json();
            const namespace = 'http://www.w3.org/2000/svg';
            const lightTheme = document.documentElement.dataset.theme === 'light';
            this.$refs.map.replaceChildren();

            geojson.features.forEach((feature) => {
                const path = document.createElementNS(namespace, 'path');
                const iso = feature.properties.ISO_A2_EH || feature.properties.ISO_A2;
                const countryData = Object.prototype.hasOwnProperty.call(countryScores, iso) ? countryScores[iso] : null;
                const available = countryData !== null;
                const change = countryData === null || countryData.change === null ? null : Number(countryData.change);
                const direction = change === null ? null : (change > 0 ? 1 : (change < -0.5 ? -1 : 0));
                const intensity = change === null ? 0 : Math.min(1, Math.abs(change) / 3);
                const color = direction === null
                    ? (lightTheme ? '#cbd5e1' : '#64748b')
                    : direction > 0
                        ? (lightTheme ? `hsl(152, 46%, ${72 - intensity * 30}%)` : `hsl(152, 66%, ${50 + intensity * 5}%)`)
                        : direction < 0
                            ? (lightTheme ? `hsl(352, 52%, ${78 - intensity * 28}%)` : `hsl(352, 68%, ${56 + intensity * 4}%)`)
                            : (lightTheme ? '#fbbf24' : '#d6a72b');
                const inactiveStroke = lightTheme ? '#4b5563' : '#8a9ab8';
                const restingOpacity = lightTheme ? '0.66' : '0.48';

                path.setAttribute('d', this.geometryPath(feature.geometry));
                path.setAttribute('fill', color);
                // Keep countries without index data faintly visible. Previously
                // they were fully transparent, which made a failed data match
                // look exactly like a missing map.
                path.setAttribute('fill-opacity', available ? restingOpacity : '0.14');
                path.setAttribute('stroke', available ? color : inactiveStroke);
                path.setAttribute('stroke-opacity', available ? '1' : (lightTheme ? '0.78' : '0.68'));
                path.setAttribute('stroke-width', available ? (lightTheme ? '1.8' : '1.5') : (lightTheme ? '0.9' : '0.85'));
                path.setAttribute('vector-effect', 'non-scaling-stroke');
                path.setAttribute('stroke-linejoin', 'round');

                if (available) {
                    path.style.cursor = 'pointer';
                    path.style.transition = 'fill-opacity 160ms ease, stroke-width 160ms ease';
                    path.addEventListener('mouseenter', () => {
                        path.setAttribute('fill-opacity', '0.78');
                        path.setAttribute('stroke-width', '2.2');
                    });
                    path.addEventListener('mouseleave', () => {
                        path.setAttribute('fill-opacity', restingOpacity);
                        path.setAttribute('stroke-width', lightTheme ? '1.8' : '1.5');
                    });
                    path.addEventListener('click', () => {
                        const german = document.documentElement.lang.startsWith('de');
                        this.selectedCountry = {
                            code: iso,
                            flag: this.countryFlag(iso),
                            name: german ? (feature.properties.NAME_DE || feature.properties.NAME) : feature.properties.NAME,
                            change,
                            indices: Number(countryData.indices),
                            indexName: countryData.index_name,
                            indexSymbol: countryData.index_symbol,
                            priceFormatted: new Intl.NumberFormat(german ? 'de-DE' : 'en-US', { maximumFractionDigits: 2 }).format(Number(countryData.price)),
                            latestAt: countryData.latest_at,
                            indexUrl: `${indicesUrl}?q=${encodeURIComponent(countryData.index_symbol)}`,
                        };
                    });
                }

                const title = document.createElementNS(namespace, 'title');
                const indexLabel = document.documentElement.lang.startsWith('en') ? 'indices' : 'Indizes';
                title.textContent = !available
                    ? feature.properties.NAME
                    : `${feature.properties.NAME}: ${change === null ? '—' : `${change >= 0 ? '+' : ''}${change.toFixed(2)} %`} · ${countryData.indices} ${indexLabel} · ${countryData.latest_at || '—'}`;
                path.appendChild(title);
                this.$refs.map.appendChild(path);
            });
        } catch (error) {
            console.error('Global Market Map could not be initialized.', error);
            this.error = true;
        }
    },

    geometryPath(geometry) {
        const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
        return polygons.map((polygon) => polygon.map((ring) => this.ringPath(ring)).join(' ')).join(' ');
    },

    ringPath(ring) {
        let previousLongitude = null;
        return ring.map(([longitude, latitude], index) => {
            const x = ((longitude + 180) / 360) * 1000;
            const y = ((90 - latitude) / 180) * 500;
            const crossesDateLine = previousLongitude !== null && Math.abs(longitude - previousLongitude) > 180;
            previousLongitude = longitude;
            return `${index === 0 || crossesDateLine ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        }).join(' ') + ' Z';
    },

    countryFlag(countryCode) {
        return /^[A-Z]{2}$/.test(countryCode)
            ? String.fromCodePoint(...countryCode.split('').map((letter) => 127397 + letter.charCodeAt(0)))
            : '🌐';
    },
});
