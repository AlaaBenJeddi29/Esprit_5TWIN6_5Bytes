import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();

if (document.querySelector('[data-zone-map]')) {
    import('./zone-map');
}

if (document.querySelector('[data-incident-map]')) {
    import('./incident-map');
}

if (document.querySelector('[data-dashboard-charts]')) {
    import('./dashboard-charts').then(({ initDashboardCharts }) => initDashboardCharts());
}
