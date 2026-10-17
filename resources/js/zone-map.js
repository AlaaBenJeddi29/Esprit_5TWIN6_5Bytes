import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const DEFAULT_CENTER = [48.8566, 2.3522];
const DEFAULT_ZOOM = 12;
const SELECTED_ZOOM = 16;
const SEARCH_ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const MIN_QUERY_LENGTH = 3;

const toCoordinate = (value, min, max) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const parsed = Number(value.replace(',', '.'));

    return Number.isFinite(parsed) && parsed >= min && parsed <= max ? parsed : null;
};

const formatCoordinate = (value) => value.toFixed(7);

const initZoneMap = (container) => {
    const form = container.closest('form');
    const latitudeInput = document.getElementById(container.dataset.latInput || 'latitude');
    const longitudeInput = document.getElementById(container.dataset.lngInput || 'longitude');

    if (!form || !latitudeInput || !longitudeInput) {
        return;
    }

    const searchInput = form.querySelector('[data-zone-map-search]');
    const searchButton = form.querySelector('[data-zone-map-search-button]');
    const resultsList = form.querySelector('[data-zone-map-results]');
    const status = form.querySelector('[data-zone-map-status]');

    const initialLatitude = toCoordinate(latitudeInput.value, -90, 90);
    const initialLongitude = toCoordinate(longitudeInput.value, -180, 180);
    const hasInitialPosition = initialLatitude !== null && initialLongitude !== null;

    const map = L.map(container, { scrollWheelZoom: true }).setView(
        hasInitialPosition ? [initialLatitude, initialLongitude] : DEFAULT_CENTER,
        hasInitialPosition ? SELECTED_ZOOM : DEFAULT_ZOOM,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let marker = null;
    let searchController = null;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const clearResults = () => {
        if (resultsList) {
            resultsList.replaceChildren();
            resultsList.classList.add('hidden');
        }
    };

    const writeCoordinates = (latitude, longitude) => {
        latitudeInput.value = formatCoordinate(latitude);
        longitudeInput.value = formatCoordinate(longitude);
    };

    const ensureMarker = (latitude, longitude) => {
        if (marker === null) {
            marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const position = marker.getLatLng();
                writeCoordinates(position.lat, position.lng);
                setStatus('Position mise à jour depuis la carte.');
            });
        } else {
            marker.setLatLng([latitude, longitude]);
        }
    };

    const setPosition = (latitude, longitude, { zoom = null, label = null } = {}) => {
        ensureMarker(latitude, longitude);
        writeCoordinates(latitude, longitude);
        map.setView([latitude, longitude], zoom ?? Math.max(map.getZoom(), SELECTED_ZOOM));
        setStatus(label ? `Position sélectionnée : ${label}` : 'Position mise à jour depuis la carte.');
    };

    if (hasInitialPosition) {
        ensureMarker(initialLatitude, initialLongitude);
    }

    map.on('click', (event) => {
        setPosition(event.latlng.lat, event.latlng.lng);
        clearResults();
    });

    const syncFromInputs = () => {
        const latitude = toCoordinate(latitudeInput.value, -90, 90);
        const longitude = toCoordinate(longitudeInput.value, -180, 180);

        if (latitude === null || longitude === null) {
            return;
        }

        ensureMarker(latitude, longitude);
        map.setView([latitude, longitude], Math.max(map.getZoom(), SELECTED_ZOOM));
        setStatus('Position mise à jour depuis les coordonnées.');
    };

    latitudeInput.addEventListener('change', syncFromInputs);
    longitudeInput.addEventListener('change', syncFromInputs);

    const renderResults = (results) => {
        clearResults();

        if (!resultsList) {
            return;
        }

        let rendered = 0;

        results.forEach((result) => {
            const latitude = Number(result.lat);
            const longitude = Number(result.lon);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            const label = result.display_name ?? `${latitude}, ${longitude}`;
            const item = document.createElement('li');
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5';
            button.textContent = label;
            button.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = label;
                }

                setPosition(latitude, longitude, { zoom: SELECTED_ZOOM, label });
                clearResults();
            });

            item.appendChild(button);
            resultsList.appendChild(item);
            rendered += 1;
        });

        if (rendered === 0) {
            setStatus('Aucun résultat exploitable pour cette recherche.');
            return;
        }

        resultsList.classList.remove('hidden');
        setStatus(`${rendered} résultat(s). Sélectionnez une adresse.`);
    };

    const search = async () => {
        if (!searchInput) {
            return;
        }

        const query = searchInput.value.trim();

        if (query.length < MIN_QUERY_LENGTH) {
            clearResults();
            setStatus(`Saisissez au moins ${MIN_QUERY_LENGTH} caractères.`);
            return;
        }

        searchController?.abort();
        searchController = new AbortController();

        setStatus('Recherche en cours…');

        const url = new URL(SEARCH_ENDPOINT);
        url.search = new URLSearchParams({
            q: query,
            format: 'jsonv2',
            limit: '5',
            'accept-language': 'fr',
        }).toString();

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: searchController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            renderResults(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                clearResults();
                setStatus('La recherche a échoué. Vérifiez votre connexion puis réessayez.');
            }
        }
    };

    searchButton?.addEventListener('click', search);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            search();
        }
    });

    document.addEventListener('click', (event) => {
        if (resultsList
            && !resultsList.classList.contains('hidden')
            && !resultsList.contains(event.target)
            && event.target !== searchInput
            && event.target !== searchButton) {
            resultsList.classList.add('hidden');
        }
    });

    requestAnimationFrame(() => map.invalidateSize());
};

document.querySelectorAll('[data-zone-map]').forEach(initZoneMap);
