/**
 * rxn-geo-tracking-dashboard.js
 *
 * Inicializa Google Maps en el dashboard de /mi-empresa/geo-tracking y popula
 * los markers desde /mi-empresa/geo-tracking/map-points.
 *
 * El script del Google Maps API (cargado en la vista con &callback=rxnGeoInitMap)
 * llama a window.rxnGeoInitMap() cuando el SDK está listo.
 *
 * Config esperada en window.RxnGeoDashboardConfig:
 *   - mapPointsEndpoint: string
 *   - filters: { date_from, date_to, user_id, event_type, entidad_tipo }
 *   - eventLabels: { [event_type]: label }
 */
(function () {
    'use strict';

    const MARKER_COLORS = {
        login: '#6c757d',                   // gris
        'presupuesto.created': '#0d6efd',   // azul
        'tratativa.created': '#198754',     // verde
        'pds.created': '#fd7e14',           // naranja
    };

    const ACCURACY_ORDER = ['gps', 'wifi', 'ip', 'denied', 'error'];

    let map = null;
    let infoWindow = null;
    const markerRefs = [];

    function setStatus(text) {
        const el = document.getElementById('rxn-geo-map-status');
        if (el) el.textContent = text || '';
    }

    function colorForEvent(eventType) {
        return MARKER_COLORS[eventType] || '#6610f2';
    }

    function buildPopupHtml(point, labels) {
        const label = (labels && labels[point.event_type]) || point.event_type;
        const accuracyBadge = point.accuracy_meters
            ? point.accuracy_source.toUpperCase() + ' ±' + point.accuracy_meters + 'm'
            : point.accuracy_source.toUpperCase();

        let entidadLine = '';
        if (point.entidad_tipo && point.entidad_id) {
            entidadLine = '<div><small>' +
                escapeHtml(point.entidad_tipo) + ' #' + point.entidad_id +
                '</small></div>';
        }

        const ciudad = [point.city, point.country].filter(Boolean).join(', ');
        const ciudadLine = ciudad
            ? '<div><small>📍 ' + escapeHtml(ciudad) + '</small></div>'
            : '';

        return '' +
            '<div style="font-size: 0.85rem; min-width: 200px;">' +
                '<div style="font-weight: 700; margin-bottom: 4px;">' + escapeHtml(label) + '</div>' +
                '<div><strong>' + escapeHtml(point.user_nombre || '—') + '</strong></div>' +
                entidadLine +
                ciudadLine +
                '<div><small style="color: #888;">IP: ' + escapeHtml(point.ip || '—') + '</small></div>' +
                '<div><small style="color: #888;">' + escapeHtml(point.created_at) + '</small></div>' +
                '<div style="margin-top: 6px;">' +
                    '<span style="display: inline-block; padding: 2px 6px; border-radius: 4px; ' +
                        'background: #e9ecef; font-size: 0.7rem; font-weight: 600;">' +
                        escapeHtml(accuracyBadge) +
                    '</span>' +
                '</div>' +
            '</div>';
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function clearMarkers() {
        markerRefs.forEach(function (m) {
            if (m && m.setMap) m.setMap(null);
        });
        markerRefs.length = 0;
    }

    function loadPoints() {
        const config = window.RxnGeoDashboardConfig || {};
        const endpoint = config.mapPointsEndpoint || '/mi-empresa/geo-tracking/map-points';
        const filters = config.filters || {};
        const labels = config.eventLabels || {};

        const qs = new URLSearchParams();
        Object.keys(filters).forEach(function (k) {
            if (filters[k] !== null && filters[k] !== undefined && filters[k] !== '') {
                qs.append(k, filters[k]);
            }
        });

        setStatus('Cargando puntos...');

        fetch(endpoint + (qs.toString() ? '?' + qs.toString() : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    setStatus('Error al cargar puntos.');
                    return;
                }

                clearMarkers();

                const points = data.points || [];
                if (points.length === 0) {
                    setStatus('Sin puntos para mostrar con los filtros aplicados.');
                    return;
                }

                const bounds = new google.maps.LatLngBounds();

                points.forEach(function (point) {
                    const position = { lat: point.lat, lng: point.lng };
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: (labels[point.event_type] || point.event_type) + ' — ' + (point.user_nombre || ''),
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 7,
                            fillColor: colorForEvent(point.event_type),
                            fillOpacity: 0.85,
                            strokeColor: '#ffffff',
                            strokeWeight: 2,
                        },
                    });

                    marker.addListener('click', function () {
                        if (!infoWindow) {
                            infoWindow = new google.maps.InfoWindow();
                        }
                        infoWindow.setContent(buildPopupHtml(point, labels));
                        infoWindow.open(map, marker);
                    });

                    markerRefs.push(marker);
                    bounds.extend(position);
                });

                // Auto-zoom al bounds de los puntos.
                map.fitBounds(bounds);
                // Evitar zoom excesivo si hay un solo punto.
                const listener = google.maps.event.addListenerOnce(map, 'idle', function () {
                    if (map.getZoom() > 15) map.setZoom(15);
                });

                let status = 'Mostrando ' + points.length + ' punto' + (points.length === 1 ? '' : 's');
                if (data.limited) {
                    status += ' (límite ' + data.max_points + ' — acotá filtros para ver menos)';
                }
                setStatus(status);
            })
            .catch(function (err) {
                console.error('[RxnGeoDashboard] Error:', err);
                setStatus('Error de red al cargar puntos.');
            });
    }

    // Entry point invocado por el callback del Google Maps SDK.
    window.rxnGeoInitMap = function () {
        const mapEl = document.getElementById('rxn-geo-map');
        if (!mapEl) return;

        map = new google.maps.Map(mapEl, {
            center: { lat: -34.6037, lng: -58.3816 }, // Buenos Aires por default.
            zoom: 4,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true,
        });

        loadPoints();
    };
})();
