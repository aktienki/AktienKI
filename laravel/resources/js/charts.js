import ApexCharts from "apexcharts";

window.ApexCharts = ApexCharts;

window.sparkline = (series = []) => ({

    chart: null,

    init() {
        const lightTheme = document.documentElement.dataset.theme === 'light';

        if (!Array.isArray(series) || series.length === 0) {
            series = [1, 2, 1.5, 2.4, 2.1, 2.9, 3.2];
        }

        this.chart = new ApexCharts(this.$refs.chart, {

            chart: {
                type: "line",
                height: 52,
                sparkline: {
                    enabled: true
                },
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: false
                }
            },

            series: [{
                data: series
            }],

            stroke: {
                curve: "smooth",
                width: 2.4
            },

            colors: [lightTheme ? "#06B6D4" : "#22D3EE"],

            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0,
                    stops: [0,100]
                }
            },

            tooltip: {
                enabled: false
            },

            markers: {
                size: 0
            },

            grid: {
                show: false
            },

            xaxis: {
                labels: {
                    show: false
                }
            },

            yaxis: {
                labels: {
                    show: false
                }
            }

        });

        this.chart.render();

    }

});

window.candlestick = (series = []) => ({
    chart: null,

    init() {
        const element = this.$refs.chart;
        if (!element) return;

        element.__aktienkiChart?.destroy?.();
        element.replaceChildren();

        if (!Array.isArray(series) || series.length === 0) {
            series = [
                { x: 1, y: [99, 102, 98, 101] },
                { x: 2, y: [101, 103, 100, 100.5] },
                { x: 3, y: [100.5, 104, 100, 103] },
                { x: 4, y: [103, 104, 101.5, 102] },
                { x: 5, y: [102, 106, 101.5, 105] },
                { x: 6, y: [105, 107, 104, 106] },
            ];
        }

        // Use an ordinal axis so overnight and weekend market closures do not
        // create visual gaps between otherwise consecutive hourly candles.
        series = series.map((point, index) => ({ ...point, x: index }));
        const closes = series
            .map((point) => Number(point.y?.[3]))
            .filter((value) => Number.isFinite(value));
        const averageClose = closes.length
            ? closes.reduce((sum, value) => sum + value, 0) / closes.length
            : null;

        this.chart = new ApexCharts(element, {
            chart: {
                type: 'candlestick',
                height: '100%',
                sparkline: { enabled: true },
                toolbar: { show: false },
                animations: { enabled: false },
                background: 'transparent',
            },
            series: [{ data: series }],
            stroke: {
                width: 1,
            },
            plotOptions: {
                bar: {
                    columnWidth: '62%',
                },
                candlestick: {
                    colors: {
                        upward: '#34d399',
                        downward: '#e66b78',
                    },
                    wick: { useFillColor: false },
                },
            },
            grid: { show: false, padding: { top: 2, right: 1, bottom: 2, left: 1 } },
            annotations: averageClose === null ? {} : {
                yaxis: [{
                    y: averageClose,
                    borderColor: 'rgba(255, 255, 255, 0.78)',
                    strokeDashArray: 5,
                    borderWidth: 1,
                }],
            },
            tooltip: { enabled: false },
            xaxis: { type: 'numeric', labels: { show: false }, tooltip: { enabled: false } },
            yaxis: { labels: { show: false }, tooltip: { enabled: false } },
        });

        element.__aktienkiChart = this.chart;
        this.chart.render();
    },

    destroy() {
        this.chart?.destroy();
        if (this.$refs.chart?.__aktienkiChart === this.chart) {
            delete this.$refs.chart.__aktienkiChart;
        }
    },
});

// Kept temporarily for backwards comparison; the production map lives in
// market-map.js and must not be overwritten by the lazy ApexCharts bundle.
window.legacyWorldMarketMap = (countryScores = {}, indicesUrl = '/indices') => ({
    error: false,
    selectedCountry: null,

    async init() {
        try {
            const response = await fetch('/assets/ne_50m_admin_0_countries.geojson');
            if (!response.ok) throw new Error('Map data unavailable');

            const geojson = await response.json();
            const namespace = 'http://www.w3.org/2000/svg';
            const lightTheme = document.documentElement.dataset.theme === 'light';

            geojson.features.forEach((feature) => {
                const path = document.createElementNS(namespace, 'path');
                const iso = feature.properties.ISO_A2_EH || feature.properties.ISO_A2;
                const countryData = Object.prototype.hasOwnProperty.call(countryScores, iso)
                    ? countryScores[iso]
                    : null;
                const available = countryData !== null;
                const change = countryData === null || countryData.change === null
                    ? null
                    : Number(countryData.change);
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
                const activeStroke = color;
                const restingOpacity = lightTheme ? '0.66' : '0.48';

                path.setAttribute('d', this.geometryPath(feature.geometry));
                path.setAttribute('fill', color);
                path.setAttribute('fill-opacity', available ? restingOpacity : '0');
                path.setAttribute('stroke', available ? activeStroke : inactiveStroke);
                path.setAttribute('stroke-opacity', available ? '1' : (lightTheme ? '0.78' : '0.68'));
                path.setAttribute('stroke-width', available ? (lightTheme ? '1.8' : '1.5') : (lightTheme ? '0.9' : '0.85'));
                path.setAttribute('vector-effect', 'non-scaling-stroke');
                path.setAttribute('stroke-linejoin', 'round');

                if (available) {
                    path.style.cursor = 'pointer';
                    path.style.transition = 'fill-opacity 160ms ease, stroke-width 160ms ease';
                    path.addEventListener('mouseenter', () => {
                        path.setAttribute('fill-opacity', '0.72');
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

window.welcomeStockMap = (countryStocks = {}) => ({
    error: false,

    async init() {
        try {
            const response = await fetch('/assets/ne_50m_admin_0_countries.geojson');
            if (!response.ok) throw new Error('Map data unavailable');

            const geojson = await response.json();
            const namespace = 'http://www.w3.org/2000/svg';
            const defs = document.createElementNS(namespace, 'defs');
            const amberFill = document.createElementNS(namespace, 'linearGradient');
            amberFill.setAttribute('id', 'welcome-amber-country-fill');
            amberFill.setAttribute('x1', '0');
            amberFill.setAttribute('y1', '0');
            amberFill.setAttribute('x2', '1');
            amberFill.setAttribute('y2', '1');
            [
                ['0%', '#f4d58a', '.34'],
                ['55%', '#d6a84f', '.22'],
                ['100%', '#9f7435', '.08'],
            ].forEach(([offset, color, opacity]) => {
                const stop = document.createElementNS(namespace, 'stop');
                stop.setAttribute('offset', offset);
                stop.setAttribute('stop-color', color);
                stop.setAttribute('stop-opacity', opacity);
                amberFill.appendChild(stop);
            });

            defs.append(amberFill);
            this.$refs.map.prepend(defs);

            geojson.features.forEach((feature) => {
                const path = document.createElementNS(namespace, 'path');
                const iso = feature.properties.ISO_A2_EH || feature.properties.ISO_A2;
                const stocks = Object.prototype.hasOwnProperty.call(countryStocks, iso)
                    ? Number(countryStocks[iso])
                    : 0;

                path.setAttribute('d', this.geometryPath(feature.geometry));
                path.setAttribute('fill', stocks > 0 ? 'url(#welcome-amber-country-fill)' : 'transparent');
                path.setAttribute('fill-opacity', stocks > 0 ? '0.72' : '0');
                path.setAttribute('stroke', stocks > 0 ? '#d6a84f' : '#a8b4c8');
                path.setAttribute('stroke-opacity', stocks > 0 ? '0.90' : '0.62');
                path.setAttribute('stroke-width', stocks > 0 ? '1.15' : '0.88');
                path.setAttribute('vector-effect', 'non-scaling-stroke');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('shape-rendering', 'geometricPrecision');

                if (stocks > 0) {
                    path.style.transition = 'fill-opacity 180ms ease, stroke-opacity 180ms ease, stroke-width 180ms ease';
                    path.addEventListener('mouseenter', () => {
                        path.setAttribute('fill-opacity', '0.94');
                        path.setAttribute('stroke-opacity', '1');
                        path.setAttribute('stroke-width', '1.65');
                    });
                    path.addEventListener('mouseleave', () => {
                        path.setAttribute('fill-opacity', '0.72');
                        path.setAttribute('stroke-opacity', '0.76');
                        path.setAttribute('stroke-width', '1.15');
                    });
                }

                const title = document.createElementNS(namespace, 'title');
                const stockLabel = document.documentElement.lang.startsWith('en') ? 'stocks' : 'Aktien';
                title.textContent = stocks > 0
                    ? `${feature.properties.NAME}: ${stocks} ${stockLabel}`
                    : feature.properties.NAME;
                path.appendChild(title);
                this.$refs.map.appendChild(path);
            });
        } catch (error) {
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
});

window.monthlyBacktestAiScoreChart = (series = []) => ({
    chart: null,

    init() {
        const element = this.$refs.chart;
        if (!element) return;

        element.__aktienkiChart?.destroy?.();
        element.replaceChildren();

        const lightTheme = document.documentElement.dataset.theme === 'light';
        const values = series.map((point) => point.value === null ? null : Number(point.value));
        const categories = series.map((point) => point.label);
        const observations = series.map((point) => Number(point.observations || 0));

        this.chart = new ApexCharts(element, {
            chart: {
                type: 'bar',
                height: '100%',
                background: 'transparent',
                toolbar: { show: false },
                animations: { enabled: true, speed: 420 },
            },
            series: [{ name: 'Ø KI-Score', data: values }],
            colors: [lightTheme ? '#0891b2' : '#facc15'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    borderRadiusApplication: 'end',
                    columnWidth: '62%',
                },
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: lightTheme ? 'rgba(15, 118, 129, .12)' : 'rgba(148, 163, 184, .13)',
                strokeDashArray: 4,
                padding: { top: 4, right: 8, bottom: 0, left: 2 },
            },
            xaxis: {
                categories,
                tickAmount: 11,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    rotate: 0,
                    hideOverlappingLabels: true,
                    trim: false,
                    style: { colors: lightTheme ? '#64748b' : '#94a3b8', fontSize: '9px', fontWeight: 600 },
                },
            },
            yaxis: {
                min: 0,
                max: 10,
                tickAmount: 5,
                labels: {
                    style: { colors: lightTheme ? '#64748b' : '#94a3b8', fontSize: '9px', fontWeight: 600 },
                    formatter: (value) => value.toFixed(0),
                },
            },
            annotations: {
                yaxis: [{
                    y: 5,
                    borderColor: lightTheme ? 'rgba(217, 119, 6, .55)' : 'rgba(250, 204, 21, .55)',
                    strokeDashArray: 5,
                    label: {
                        text: 'Neutral 5,0',
                        position: 'left',
                        borderColor: 'transparent',
                        style: {
                            background: 'transparent',
                            color: lightTheme ? '#92400e' : '#fde047',
                            fontSize: '9px',
                            fontWeight: 700,
                        },
                    },
                }],
            },
            tooltip: {
                theme: lightTheme ? 'light' : 'dark',
                custom: ({ dataPointIndex }) => {
                    const point = series[dataPointIndex];
                    if (!point || point.value === null) {
                        return `<div class="px-3 py-2 text-xs"><b>${point?.label || '—'}</b><br>Keine Backtestdaten</div>`;
                    }
                    return `<div class="px-3 py-2 text-xs"><b>${point.label}</b><br>Ø KI-Score: <b>${Number(point.value).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</b><br>Beobachtungen: ${observations[dataPointIndex].toLocaleString('de-DE')}</div>`;
                },
            },
            noData: { text: 'Keine Backtestdaten verfügbar' },
        });

        element.__aktienkiChart = this.chart;
        this.chart.render();
    },

    destroy() {
        this.chart?.destroy();
        if (this.$refs.chart?.__aktienkiChart === this.chart) delete this.$refs.chart.__aktienkiChart;
    },
});

window.dailyAiScoreChart = (series = []) => ({
    chart: null,

    init() {
        const lightTheme = document.documentElement.dataset.theme === 'light';
        const accent = lightTheme ? '#0891b2' : '#fb923c';
        const accentSoft = lightTheme ? '#22d3ee' : '#fdba74';
        const gridColor = lightTheme ? 'rgba(14, 116, 144,.10)' : 'rgba(251,146,60,.10)';
        const axisColor = lightTheme ? '#64748b' : '#94a3b8';
        const labelBackground = lightTheme ? '#ecfeff' : '#172033';
        const labelText = lightTheme ? '#0e7490' : '#fed7aa';
        const lastPointIndex = Math.max(0, series.length - 1);

        this.chart = new ApexCharts(this.$refs.chart, {
            chart: {
                type: 'area',
                height: '100%',
                background: 'transparent',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 520, easing: 'easeinout' },
            },
            series: [{ name: 'KI-Score', data: series }],
            colors: [accent],
            stroke: { width: 3, curve: 'smooth', lineCap: 'round' },
            markers: {
                size: 0,
                strokeWidth: 0,
                hover: { size: 5, sizeOffset: 1 },
                discrete: [{
                    seriesIndex: 0,
                    dataPointIndex: lastPointIndex,
                    fillColor: accent,
                    strokeColor: lightTheme ? '#ffffff' : '#172033',
                    size: 5,
                    strokeWidth: 3,
                }],
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: lightTheme ? 'light' : 'dark',
                    type: 'vertical',
                    gradientToColors: [accentSoft],
                    opacityFrom: lightTheme ? 0.28 : 0.24,
                    opacityTo: 0.015,
                    stops: [0, 72, 100],
                },
            },
            dataLabels: {
                enabled: true,
                formatter: (value, options) => {
                    const points = options.w.config.series[options.seriesIndex].data;
                    const isLast = options.dataPointIndex === points.length - 1;
                    return isLast ? value.toFixed(1) : '';
                },
                offsetX: -7,
                offsetY: -12,
                style: {
                    fontSize: '10px',
                    fontWeight: 800,
                    colors: [labelText],
                },
                background: {
                    enabled: true,
                    foreColor: labelText,
                    borderRadius: 6,
                    padding: 4,
                    opacity: 1,
                    borderWidth: 1,
                    borderColor: lightTheme ? 'rgba(8,145,178,.24)' : 'rgba(251,146,60,.30)',
                    backgroundColor: labelBackground,
                },
            },
            annotations: {
                yaxis: [{
                    y: 5,
                    borderColor: lightTheme ? 'rgba(14, 116, 144,.24)' : 'rgba(148,163,184,.20)',
                    strokeDashArray: 5,
                    borderWidth: 1,
                }],
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 3,
                xaxis: { lines: { show: false } },
                yaxis: { lines: { show: true } },
                padding: { top: 10, right: 10, bottom: 0, left: 2 },
            },
            xaxis: {
                type: 'datetime',
                tickAmount: 4,
                labels: {
                    style: { colors: axisColor, fontSize: '9px', fontWeight: 600 },
                    datetimeUTC: false,
                    format: 'dd.MM.',
                    hideOverlappingLabels: true,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                min: 0,
                max: 10,
                tickAmount: 5,
                labels: {
                    minWidth: 14,
                    style: { colors: axisColor, fontSize: '9px', fontWeight: 600 },
                    formatter: (value) => value.toFixed(0),
                },
            },
            tooltip: {
                theme: lightTheme ? 'light' : 'dark',
                x: { format: 'dd.MM.yyyy' },
                y: { formatter: (value) => `${value.toFixed(1)} / 10` },
            },
        });

        this.chart.render();
    },

    destroy() {
        this.chart?.destroy();
    },
});
