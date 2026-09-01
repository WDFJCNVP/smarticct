import './bootstrap';
import Chart from 'chart.js/auto';

/**
 * ============================================================
 * THEME-AWARE COLOR ENGINE
 * ============================================================
 * Chart.js draws to a <canvas>, which can't read CSS var()
 * directly — it needs a resolved color string. So instead of
 * hardcoding hex values here (which would go stale the moment
 * someone reworks app.css, and wouldn't react to dark mode),
 * every chart resolves its colors from the SAME custom
 * properties defined in your @theme block, at the moment it
 * draws. When <html> gains/loses the `dark` class, every chart
 * on the page re-reads those variables and repaints.
 */

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function hexToRgba(hex, alpha) {
    const h = hex.replace('#', '');
    const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
    const int = parseInt(full, 16);
    const r = (int >> 16) & 255, g = (int >> 8) & 255, b = int & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Resolves the current palette from your actual app.css tokens.
 * `secondary` (gold) needs no dark: branching — you already
 * re-assign --color-secondary inside `.dark { }`, so the browser
 * resolves it correctly on its own. info/warning/success/danger
 * use separate --color-dark-* variables, so those DO need the
 * isDark() branch to pick the right one.
 *
 * txtBody (vs txtMuted) is used for chart tick/legend text —
 * axis labels need to actually be readable, and the muted token
 * is intentionally the lower-contrast one reserved for secondary,
 * non-essential text like timestamps.
 */
function palette() {
    const dark = isDark();
    return {
        primary: cssVar('--color-primary'),
        secondary: cssVar('--color-secondary'),
        info: cssVar(dark ? '--color-dark-info' : '--color-info'),
        warning: cssVar(dark ? '--color-dark-warning' : '--color-warning'),
        success: cssVar(dark ? '--color-dark-success' : '--color-success'),
        danger: cssVar(dark ? '--color-dark-danger' : '--color-danger'),
        txtBody: cssVar(dark ? '--color-dark-txt-body' : '--color-light-txt-body'),
        txtMuted: cssVar(dark ? '--color-dark-txt-muted' : '--color-light-txt-muted'),
        border: cssVar(dark ? '--color-dark-bd-default' : '--color-light-bd-default'),
        surface: cssVar(dark ? '--color-dark-surface' : '--color-light-secondary'),
    };
}

// Chart.js can't read CSS vars for font-family either, so it's resolved the
// same way as colors: fresh, from the theme, every time a chart (re)draws.
function fontFamily() {
    return cssVar('--font-secondary') || 'sans-serif';
}

// Chart.js default axis/legend font is small (~12px) and Chart.js can't read
// rem-based CSS vars, so size and weight are set directly here — bumped up
// for readability since the dashboard's audience skews older. Family is
// resolved live so chart text always matches the rest of the UI.
function tickFont() {
    return { size: 13, weight: 500, family: fontFamily() };
}

function legendFont() {
    return { size: 13, weight: 500, family: fontFamily() };
}

function axisOptions(pal) {
    return {
        ticks: { color: pal.txtBody, precision: 0, font: tickFont() },
        // Modern dashboards read cleaner without gridlines cluttering the plot area.
        grid: { display: false },
        border: { color: pal.border },
    };
}

// Legend on the right, circular swatches instead of the default rectangles —
// matches the rounded, modern look used across the rest of the dashboard.
function legendOptions(pal) {
    return {
        position: 'right',
        labels: {
            color: pal.txtBody,
            font: legendFont(),
            usePointStyle: true,
            pointStyle: 'circle',
            boxWidth: 8,
            boxHeight: 8,
            padding: 16,
        },
    };
}

// Shared tooltip styling so tooltips look like part of the app (rounded,
// theme-colored) instead of Chart.js's stark default white box, which is
// especially jarring in dark mode.
function tooltipOptions(pal) {
    return {
        backgroundColor: pal.surface,
        titleColor: pal.txtBody,
        bodyColor: pal.txtBody,
        borderColor: pal.border,
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        titleFont: tickFont(),
        bodyFont: tickFont(),
        usePointStyle: true,
        boxPadding: 4,
    };
}

// A chart counts as "empty" if it has no data points, or every value is
// zero/falsy — used to swap the canvas for a "No data yet" placeholder so
// the card isn't just a blank white box.
function isChartEmpty(data) {
    if (!data || !Array.isArray(data.data) || data.data.length === 0) return true;
    return data.data.every((v) => !v);
}

// Kept for roleChart only, which isn't used on this dashboard — remove this
// (and the lastUpdated line below) once you confirm nothing else renders it.
function stamp() {
    return 'Updated ' + new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

/**
 * Registry of "recolor" callbacks, one per live chart on the page.
 * A MutationObserver watches <html class="..."> for the `dark`
 * class flipping (however your theme toggle does it — Flux,
 * Livewire, or a plain button) and re-runs every callback.
 */
window.__dashboardCharts = window.__dashboardCharts || [];

if (!window.__themeObserverAttached) {
    window.__themeObserverAttached = true;
    new MutationObserver(() => {
        window.__dashboardCharts.forEach((recolor) => recolor());
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}

// ============================================================
// LINE CHART — time-series trends (e.g. registrations over time)
// ============================================================
window.lineChart = function (initial) {
    let chart = null;
    let recolor = () => {};

    return {
        empty: isChartEmpty(initial),
        init() {
            const pal = palette();
            chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        label: 'Registrations',
                        data: initial.data,
                        borderColor: pal.info,
                        backgroundColor: hexToRgba(pal.info, 0.12),
                        pointBackgroundColor: pal.surface,
                        pointBorderColor: pal.info,
                        pointBorderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: tooltipOptions(pal) },
                    scales: {
                        y: { beginAtZero: true, ...axisOptions(pal) },
                        x: { ...axisOptions(pal) },
                    },
                },
            });

            recolor = () => {
                const p = palette();
                chart.data.datasets[0].borderColor = p.info;
                chart.data.datasets[0].backgroundColor = hexToRgba(p.info, 0.12);
                chart.data.datasets[0].pointBackgroundColor = p.surface;
                chart.data.datasets[0].pointBorderColor = p.info;
                chart.options.plugins.tooltip = tooltipOptions(p);
                chart.options.scales.x.ticks.color = p.txtBody;
                chart.options.scales.x.border.color = p.border;
                chart.options.scales.y.ticks.color = p.txtBody;
                chart.options.scales.y.border.color = p.border;
                chart.update();
            };
            window.__dashboardCharts.push(recolor);
        },
        update(chartData) {
            if (!chart) return;
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update();
            this.empty = isChartEmpty(chartData);
        },
        destroy() {
            window.__dashboardCharts = window.__dashboardCharts.filter((fn) => fn !== recolor);
        },
    };
};

// ============================================================
// ROLE CHART — operator vs commuter split (2-category doughnut)
// ============================================================
window.roleChart = function (initial) {
    let chart = null;
    let recolor = () => {};

    return {
        lastUpdated: stamp(),
        init() {
            const pal = palette();
            chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        data: initial.data,
                        backgroundColor: [pal.info, pal.warning],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: legendOptions(pal),
                        tooltip: tooltipOptions(pal),
                    },
                },
            });

            recolor = () => {
                const p = palette();
                chart.data.datasets[0].backgroundColor = [p.info, p.warning];
                chart.options.plugins.legend.labels.color = p.txtBody;
                chart.options.plugins.tooltip = tooltipOptions(p);
                chart.update();
            };
            window.__dashboardCharts.push(recolor);
        },
        update(chartData) {
            if (!chart) return;
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update();
            this.lastUpdated = stamp();
        },
        destroy() {
            window.__dashboardCharts = window.__dashboardCharts.filter((fn) => fn !== recolor);
        },
    };
};

// ============================================================
// BAR CHART — generic single-series breakdown
// Pass options.colorKey as a PALETTE KEY ('primary', 'success',
// 'info', 'warning', 'danger', 'secondary') — not a raw hex —
// so it re-resolves correctly whenever the theme flips.
// ============================================================
window.barChart = function (initial, options = {}) {
    let chart = null;
    let recolor = () => {};
    const key = options.colorKey ?? 'primary';

    return {
        empty: isChartEmpty(initial),
        init() {
            const pal = palette();
            chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        label: options.label ?? '',
                        data: initial.data,
                        backgroundColor: pal[key] ?? pal.primary,
                        borderRadius: 6,
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: tooltipOptions(pal) },
                    scales: {
                        y: { beginAtZero: true, ...axisOptions(pal) },
                        x: { ...axisOptions(pal) },
                    },
                },
            });

            recolor = () => {
                const p = palette();
                chart.data.datasets[0].backgroundColor = p[key] ?? p.primary;
                chart.options.plugins.tooltip = tooltipOptions(p);
                chart.options.scales.x.ticks.color = p.txtBody;
                chart.options.scales.x.border.color = p.border;
                chart.options.scales.y.ticks.color = p.txtBody;
                chart.options.scales.y.border.color = p.border;
                chart.update();
            };
            window.__dashboardCharts.push(recolor);
        },
        update(chartData) {
            if (!chart) return;
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update();
            this.empty = isChartEmpty(chartData);
        },
        destroy() {
            window.__dashboardCharts = window.__dashboardCharts.filter((fn) => fn !== recolor);
        },
    };
};

// ============================================================
// DOUGHNUT CHART — generic multi-category split (3+ categories)
// Pass options.colorKeys as an array of PALETTE KEYS, one per
// category, e.g. ['success', 'warning', 'danger'].
// ============================================================
window.donutChart = function (initial, options = {}) {
    let chart = null;
    let recolor = () => {};
    const keys = options.colorKeys ?? ['info', 'warning', 'success', 'danger', 'secondary'];

    return {
        empty: isChartEmpty(initial),
        init() {
            const pal = palette();
            chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        data: initial.data,
                        backgroundColor: keys.map((k) => pal[k] ?? pal.primary),
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    layout: { padding: 24 },
                    plugins: {
                        legend: legendOptions(pal),
                        tooltip: tooltipOptions(pal),
                    },
                },
            });

            recolor = () => {
                const p = palette();
                chart.data.datasets[0].backgroundColor = keys.map((k) => p[k] ?? p.primary);
                chart.options.plugins.legend.labels.color = p.txtBody;
                chart.options.plugins.tooltip = tooltipOptions(p);
                chart.update();
            };
            window.__dashboardCharts.push(recolor);
        },
        update(chartData) {
            if (!chart) return;
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update();
            this.empty = isChartEmpty(chartData);
        },
        destroy() {
            window.__dashboardCharts = window.__dashboardCharts.filter((fn) => fn !== recolor);
        },
    };
};

// ============================================================
// SPARKLINE — small inline trend, no axes, for a KPI card
// ============================================================
window.sparkline = function (initial) {
    let chart = null;
    let recolor = () => {};

    return {
        init() {
            const pal = palette();
            chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        data: initial.data,
                        borderColor: pal.success,
                        backgroundColor: hexToRgba(pal.success, 0.1),
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { display: false }, y: { display: false } },
                },
            });

            recolor = () => {
                const p = palette();
                chart.data.datasets[0].borderColor = p.success;
                chart.data.datasets[0].backgroundColor = hexToRgba(p.success, 0.1);
                chart.update();
            };
            window.__dashboardCharts.push(recolor);
        },
        update(chartData) {
            if (!chart) return;
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update();
        },
        destroy() {
            window.__dashboardCharts = window.__dashboardCharts.filter((fn) => fn !== recolor);
        },
    };
};

import.meta.glob(['../images/**']);