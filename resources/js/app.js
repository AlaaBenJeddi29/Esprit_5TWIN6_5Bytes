import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

if (document.querySelector('[data-zone-map]')) {
    import('./zone-map');
}

if (document.querySelector('[data-incident-map]')) {
    import('./incident-map');
}
