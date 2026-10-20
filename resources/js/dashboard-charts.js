import Chart from 'chart.js/auto';

const charts = new Map();
let payload = null;
let themeObserver = null;

const cssToken = (name, fallback) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value !== '' ? value : fallback;
};

const readPalette = () => {
    const dark = document.documentElement.classList.contains('dark');

    return {
        dark,
        brand: cssToken('--color-brand-500', '#465fff'),
        success: cssToken('--color-success-500', '#12b76a'),
        warning: cssToken('--color-warning-500', '#f79009'),
        warning400: cssToken('--color-warning-400', '#fdb022'),
        error: cssToken('--color-error-500', '#f04438'),
        gray200: cssToken('--color-gray-200', '#e4e7ec'),
        gray400: cssToken('--color-gray-400', '#98a2b3'),
        gray500: cssToken('--color-gray-500', '#667085'),
        white: cssToken('--color-white', '#ffffff'),
        surface: cssToken('--color-gray-900', '#101828'),
    };
};

const withAlpha = (color, alpha) => {
    const hex = color.trim();

    if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
        return `rgba(70, 95, 255, ${alpha})`;
    }

    const normalized = hex.length === 4
        ? `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`
        : hex;

    const value = Number.parseInt(normalized.slice(1), 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
};

const tooltipOptions = (palette) => ({
    backgroundColor: palette.dark ? 'rgba(16, 24, 40, 0.94)' : 'rgba(255, 255, 255, 0.97)',
    titleColor: palette.dark ? '#f9fafb' : '#101828',
    bodyColor: palette.dark ? '#d0d5dd' : '#475467',
    borderColor: palette.dark ? 'rgba(255, 255, 255, 0.12)' : palette.gray200,
    borderWidth: 1,
    padding: 10,
    boxPadding: 4,
    displayColors: true,
});

const destroyCharts = () => {
    charts.forEach((chart) => chart.destroy());
    charts.clear();
};

const renderStatusChart = (data, palette) => {
    const canvas = document.getElementById('incidentStatusChart');

    if (!canvas) {
        return;
    }

    const entries = Object.entries(data.counts ?? {});
    const colors = {
        nouveau: palette.warning,
        en_cours: palette.brand,
        en_attente: palette.gray400,
        traite: palette.success,
        rejete: palette.error,
    };
    const total = data.total ?? 0;

    charts.set('status', new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: entries.map(([key]) => data.labels?.[key] ?? key),
            datasets: [{
                data: entries.map(([, count]) => count),
                backgroundColor: entries.map(([key]) => colors[key] ?? palette.gray400),
                borderColor: palette.dark ? palette.surface : palette.white,
                borderWidth: 3,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipOptions(palette),
                    callbacks: {
                        label: (context) => {
                            const percent = total > 0 ? Math.round((context.parsed / total) * 100) : 0;

                            return ` ${context.label} : ${context.parsed} (${percent} %)`;
                        },
                    },
                },
            },
        },
    }));
};

const renderSeverityChart = (data, palette) => {
    const canvas = document.getElementById('incidentSeverityChart');

    if (!canvas) {
        return;
    }

    const entries = Object.entries(data.counts ?? {});
    const colors = {
        faible: palette.success,
        moyenne: palette.warning400,
        haute: palette.warning,
        critique: palette.error,
    };

    charts.set('severity', new Chart(canvas, {
        type: 'bar',
        data: {
            labels: entries.map(([key]) => data.labels?.[key] ?? key),
            datasets: [{
                label: 'Incidents ouverts',
                data: entries.map(([, count]) => count),
                backgroundColor: entries.map(([key]) => colors[key] ?? palette.gray400),
                borderRadius: 5,
                borderSkipped: false,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipOptions(palette),
                    callbacks: {
                        label: (context) => ` ${context.parsed.x} incident(s)`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: withAlpha(palette.gray200, palette.dark ? 0.16 : 0.8) },
                    ticks: { color: palette.gray500, precision: 0 },
                },
                y: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { color: palette.gray500 },
                },
            },
        },
    }));
};

const renderTrendChart = (data, palette) => {
    const canvas = document.getElementById('incidentTrendChart');

    if (!canvas) {
        return;
    }

    const context = canvas.getContext('2d');
    const height = canvas.parentElement?.clientHeight || 320;
    const gradient = context.createLinearGradient(0, 0, 0, height);

    gradient.addColorStop(0, withAlpha(palette.brand, palette.dark ? 0.28 : 0.18));
    gradient.addColorStop(1, withAlpha(palette.brand, 0));

    charts.set('trend', new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels ?? [],
            datasets: [{
                label: 'Incidents signalés',
                data: data.counts ?? [],
                borderColor: palette.brand,
                backgroundColor: gradient,
                fill: true,
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: palette.brand,
                pointBorderColor: palette.dark ? palette.surface : palette.white,
                pointBorderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipOptions(palette),
                    callbacks: {
                        label: (context) => ` ${context.parsed.y} incident(s)`,
                    },
                },
            },
            scales: {
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { color: palette.gray500, maxRotation: 0, autoSkipPadding: 16 },
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: withAlpha(palette.gray200, palette.dark ? 0.16 : 0.8) },
                    ticks: { color: palette.gray500, precision: 0 },
                },
            },
        },
    }));
};

const renderCharts = () => {
    if (!payload) {
        return;
    }

    destroyCharts();

    const palette = readPalette();

    if ((payload.status?.total ?? 0) > 0) {
        renderStatusChart(payload.status, palette);
    }

    if ((payload.severity?.total ?? 0) > 0) {
        renderSeverityChart(payload.severity, palette);
    }

    if ((payload.trend?.total ?? 0) > 0) {
        renderTrendChart(payload.trend, palette);
    }
};

const observeTheme = () => {
    if (themeObserver !== null) {
        return;
    }

    let lastDark = document.documentElement.classList.contains('dark');

    themeObserver = new MutationObserver(() => {
        const isDark = document.documentElement.classList.contains('dark');

        if (isDark === lastDark) {
            return;
        }

        lastDark = isDark;
        renderCharts();
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
};

export function initDashboardCharts() {
    const dataElement = document.getElementById('dashboard-chart-data');

    if (!dataElement) {
        return;
    }

    try {
        payload = JSON.parse(dataElement.textContent ?? '');
    } catch (error) {
        console.error('Dashboard charts: invalid chart data.', error);

        return;
    }

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'Outfit, sans-serif';

    renderCharts();
    observeTheme();
}
