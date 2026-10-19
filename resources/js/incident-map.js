import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const SEVERITY_COLORS = {
    critique: '#991b1b',
    haute: '#f97316',
    moyenne: '#eab308',
    faible: '#16a34a',
};

const FALLBACK_COLOR = '#64748b';
const DEFAULT_CENTER = [46.6, 2.4];
const DEFAULT_ZOOM = 6;
const SINGLE_INCIDENT_ZOOM = 13;
const CLUSTER_ZOOM = 16;
const FIT_MAX_ZOOM = 14;

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
};

const addRow = (parent, label, value) => {
    const row = document.createElement('p');
    const strong = document.createElement('span');

    strong.className = 'font-medium text-gray-700';
    strong.textContent = `${label} : `;
    row.append(strong, document.createTextNode(value ?? '—'));
    parent.append(row);
};

const buildIncidentPopup = (incident) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'min-w-[220px] space-y-1 text-sm text-gray-600';

    const reference = document.createElement('p');
    reference.className = 'font-mono text-xs text-gray-500';
    reference.textContent = incident.reference;

    const title = document.createElement('p');
    title.className = 'font-semibold text-gray-900';
    title.textContent = incident.title;

    wrapper.append(reference, title);
    addRow(wrapper, 'Gravité', incident.severity_label);

    const statusRow = document.createElement('p');
    const statusLabel = document.createElement('span');
    statusLabel.className = 'font-medium text-gray-700';
    statusLabel.textContent = 'Statut : ';
    const statusBadge = document.createElement('span');
    statusBadge.className = 'inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700';
    statusBadge.textContent = incident.status_label ?? incident.status;
    statusRow.append(statusLabel, statusBadge);
    wrapper.append(statusRow);

    addRow(wrapper, 'Signalé le', formatDate(incident.reported_at));
    addRow(wrapper, 'Zone', incident.zone?.nom);
    addRow(wrapper, 'Infrastructure', incident.infrastructure?.nom);

    const link = document.createElement('a');
    link.className = 'mt-2 inline-block text-xs font-medium text-brand-500 hover:underline';
    link.href = incident.url;
    link.textContent = 'Ouvrir la fiche détaillée';
    wrapper.append(link);

    return wrapper;
};

const buildClusterPopup = (cluster) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'min-w-[230px] space-y-2 text-sm text-gray-600';

    const title = document.createElement('p');
    title.className = 'font-semibold text-gray-900';
    title.textContent = `${cluster.count} incidents critiques proches`;
    wrapper.append(title);

    addRow(wrapper, 'Zone', cluster.zone);
    addRow(wrapper, 'Infrastructure', cluster.infrastructure);

    const list = document.createElement('ul');
    list.className = 'space-y-1';

    cluster.incidents.forEach((incident) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.className = 'text-xs font-medium text-brand-500 hover:underline';
        link.href = incident.url;
        link.textContent = `${incident.reference} — ${incident.status_label ?? incident.status}`;
        item.append(link);
        list.append(item);
    });

    wrapper.append(list);

    return wrapper;
};

const initIncidentMap = (root) => {
    const canvas = root.querySelector('[data-incident-map-canvas]');
    const form = root.querySelector('[data-incident-map-filters]');
    const endpoint = root.dataset.endpoint;

    if (!canvas || !form || !endpoint) {
        return;
    }

    const map = L.map(canvas, { scrollWheelZoom: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const markers = L.layerGroup().addTo(map);
    const clustersLayer = L.layerGroup().addTo(map);

    const elements = {
        loading: root.querySelector('[data-map-loading]'),
        empty: root.querySelector('[data-map-empty]'),
        error: root.querySelector('[data-map-error]'),
        errorMessage: root.querySelector('[data-map-error-message]'),
        status: root.querySelector('[data-map-status]'),
        clusterList: root.querySelector('[data-cluster-list]'),
        reset: root.querySelector('[data-reset-filters]'),
        radius: form.querySelector('[name="radius"]'),
    };

    const counters = {
        total: root.querySelector('[data-count="total"]'),
        displayed: root.querySelector('[data-count="displayed"]'),
        critical: root.querySelector('[data-count="critical"]'),
        unresolved: root.querySelector('[data-count="unresolved"]'),
        clusters: root.querySelector('[data-count="clusters"]'),
        unplaced: root.querySelector('[data-count="unplaced"]'),
    };

    let requestController = null;
    let requestId = 0;
    let debounceTimer = null;

    const toggle = (element, hidden) => {
        if (element) {
            element.classList.toggle('hidden', hidden);
        }
    };

    const setStatus = (message) => {
        if (elements.status) {
            elements.status.textContent = message;
        }
    };

    const setCounter = (element, value) => {
        if (element) {
            element.textContent = String(value ?? 0);
        }
    };

    const updateCounters = (counts = {}) => {
        setCounter(counters.displayed, counts.displayed);
        setCounter(counters.critical, counts.critical);
        setCounter(counters.unresolved, counts.unresolved);
        setCounter(counters.clusters, counts.clusters);
        setCounter(counters.unplaced, counts.unplaced);
        setCounter(counters.total, counts.total);
    };

    const zoomToCluster = (cluster) => {
        const [[minLat, minLng], [maxLat, maxLng]] = cluster.bounds;
        const center = [cluster.center.latitude, cluster.center.longitude];

        if (minLat === maxLat && minLng === maxLng) {
            map.setView(center, CLUSTER_ZOOM);
            return;
        }

        map.fitBounds(
            [[minLat, minLng], [maxLat, maxLng]],
            { padding: [40, 40], maxZoom: CLUSTER_ZOOM },
        );
    };

    const renderIncident = (incident) => {
        const color = SEVERITY_COLORS[incident.severity] ?? FALLBACK_COLOR;

        L.circleMarker([incident.latitude, incident.longitude], {
            radius: incident.severity === 'critique' ? 9 : 7,
            color: '#ffffff',
            weight: 2,
            fillColor: color,
            fillOpacity: 0.95,
        })
            .addTo(markers)
            .bindPopup(buildIncidentPopup(incident), { maxWidth: 340 })
            .bindTooltip(`${incident.reference} — ${incident.severity_label}`, {
                direction: 'top',
                offset: [0, -6],
            });
    };

    const renderCluster = (cluster) => {
        const center = [cluster.center.latitude, cluster.center.longitude];

        L.circle(center, {
            radius: cluster.radius,
            color: '#7f1d1d',
            weight: 2,
            dashArray: '6 6',
            fillColor: '#ef4444',
            fillOpacity: 0.12,
        }).addTo(clustersLayer);

        const iconContent = document.createElement('div');
        iconContent.className = 'flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-[#991b1b] text-xs font-bold text-white shadow-lg';
        iconContent.textContent = String(cluster.count);

        L.marker(center, {
            icon: L.divIcon({
                html: iconContent,
                className: 'incident-cluster-icon',
                iconSize: [36, 36],
                iconAnchor: [18, 18],
            }),
            title: `${cluster.count} incidents critiques proches`,
            zIndexOffset: 1000,
        })
            .addTo(clustersLayer)
            .bindPopup(buildClusterPopup(cluster), { maxWidth: 340 });
    };

    const renderClusterList = (clusters) => {
        if (!elements.clusterList) {
            return;
        }

        elements.clusterList.replaceChildren();

        if (clusters.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500 dark:text-gray-400';
            empty.textContent = 'Aucune concentration critique détectée avec ces filtres.';
            elements.clusterList.append(empty);
            return;
        }

        clusters.forEach((cluster) => {
            const card = document.createElement('article');
            card.className = 'rounded-lg border border-gray-200 p-4 dark:border-gray-700';

            const header = document.createElement('div');
            header.className = 'flex items-start justify-between gap-3';

            const title = document.createElement('p');
            title.className = 'text-sm font-semibold text-gray-900 dark:text-white';
            title.textContent = `${cluster.count} incidents critiques`;

            const badge = document.createElement('span');
            badge.className = 'shrink-0 rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-600 dark:bg-error-500/15 dark:text-error-400';
            badge.textContent = 'Vigilance';

            header.append(title, badge);

            const location = document.createElement('p');
            location.className = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
            location.textContent = cluster.zone
                ? `Zone : ${cluster.zone}${cluster.infrastructure ? ` · ${cluster.infrastructure}` : ''}`
                : cluster.infrastructure
                    ? `Infrastructure : ${cluster.infrastructure}`
                    : 'Localisation non rattachée à une zone';

            const references = document.createElement('p');
            references.className = 'mt-2 break-words text-xs font-mono text-gray-500 dark:text-gray-400';
            references.textContent = cluster.incidents.map((incident) => incident.reference).join(', ');

            const zoom = document.createElement('button');
            zoom.type = 'button';
            zoom.className = 'mt-3 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
            zoom.textContent = 'Zoomer sur la concentration';
            zoom.addEventListener('click', () => zoomToCluster(cluster));

            card.append(header, location, references, zoom);
            elements.clusterList.append(card);
        });
    };

    const fitToData = (incidents) => {
        if (incidents.length === 0) {
            map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            return;
        }

        if (incidents.length === 1) {
            map.setView([incidents[0].latitude, incidents[0].longitude], SINGLE_INCIDENT_ZOOM);
            return;
        }

        const bounds = L.latLngBounds(
            incidents.map((incident) => [incident.latitude, incident.longitude]),
        );

        map.fitBounds(bounds, { padding: [40, 40], maxZoom: FIT_MAX_ZOOM });
    };

    const render = (payload) => {
        markers.clearLayers();
        clustersLayer.clearLayers();

        const incidents = Array.isArray(payload.incidents) ? payload.incidents : [];
        const clusters = Array.isArray(payload.clusters) ? payload.clusters : [];

        incidents.forEach(renderIncident);
        clusters.forEach(renderCluster);
        renderClusterList(clusters);
        updateCounters(payload.counts);

        toggle(elements.empty, incidents.length > 0);
        fitToData(incidents);

        const unplaced = payload.counts?.unplaced ?? 0;
        const truncated = payload.meta?.truncated ? ' · affichage plafonné' : '';

        setStatus(`${incidents.length} incident(s) affiché(s) · ${unplaced} sans coordonnées${truncated}.`);
    };

    const readErrorMessage = async (response) => {
        try {
            const payload = await response.json();

            if (payload?.errors) {
                const messages = Object.values(payload.errors).flat();

                if (messages.length > 0) {
                    return messages.join(' ');
                }
            }

            if (payload?.message) {
                return payload.message;
            }
        } catch {
            // Corps non JSON : message générique ci-dessous.
        }

        return `Erreur ${response.status} lors du chargement des incidents.`;
    };

    const load = async () => {
        const currentRequest = ++requestId;
        requestController?.abort();
        requestController = new AbortController();

        const params = new URLSearchParams(new FormData(form));

        params.forEach((value, key) => {
            if (String(value).trim() === '') {
                params.delete(key);
            }
        });

        toggle(elements.loading, false);
        toggle(elements.error, true);
        setStatus('Chargement des incidents…');

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error(await readErrorMessage(response));
            }

            const payload = await response.json();

            if (currentRequest !== requestId) {
                return;
            }

            render(payload);
        } catch (error) {
            if (error.name === 'AbortError' || currentRequest !== requestId) {
                return;
            }

            toggle(elements.empty, true);

            if (elements.errorMessage) {
                elements.errorMessage.textContent = error.message
                    || 'Impossible de charger les incidents. Vérifiez votre connexion puis réessayez.';
            }

            toggle(elements.error, false);
            setStatus('Échec du chargement des incidents.');
        } finally {
            if (currentRequest === requestId) {
                toggle(elements.loading, true);
            }
        }
    };

    const scheduleLoad = () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(load, 250);
    };

    form.addEventListener('change', scheduleLoad);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(debounceTimer);
        load();
    });

    elements.reset?.addEventListener('click', () => {
        form.reset();

        if (elements.radius) {
            elements.radius.value = root.dataset.defaultRadius ?? elements.radius.value;
        }

        window.clearTimeout(debounceTimer);
        load();
    });

    requestAnimationFrame(() => map.invalidateSize());
    load();
};

document.querySelectorAll('[data-incident-map]').forEach(initIncidentMap);
