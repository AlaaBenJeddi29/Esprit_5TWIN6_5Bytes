import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();

if (document.querySelector('[data-zone-map]')) {
    import('./zone-map');
}
