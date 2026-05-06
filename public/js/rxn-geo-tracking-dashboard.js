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
    // Indexa markers por id de evento para que el click en la tabla pueda
    // encontrar el marker correspondiente y abrir su popup. Se popula en
    // loadPoints() después de pintar.
    const markerById = new Map();
    // Cache de los `point` originales por id, para reconstruir el popup
    // cuando el click viene de la tabla (necesitamos los datos del evento).
    const pointById = new Map();
    let cachedLabels = {};

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
        markerById.clear();
        pointById.clear();
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
                    if (point.id) {
                        markerById.set(String(point.id), marker);
                        pointById.set(String(point.id), point);
                    }
                    bounds.extend(position);
                });

                cachedLabels = labels;

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

    /**
     * Centra el mapa en (lat, lng) con zoom de detalle, hace scroll suave a la
     * sección del mapa, y si encontramos un marker para este eventId abre su
     * popup. Si el evento no está pintado en el mapa (porque cae fuera del
     * límite de 500 puntos), igual se centra y se construye un popup mínimo
     * desde los data-attrs de la fila.
     */
    function focusEvent(eventId, lat, lng, fallbackPopup) {
        if (!map) return;
        const position = { lat: lat, lng: lng };
        map.panTo(position);

        // Zoom acorde a la precisión: si no hay zoom suficiente, lo subimos.
        if (map.getZoom() < 15) {
            map.setZoom(16);
        }

        // Scroll suave al mapa para que el usuario vea el centrado.
        const mapEl = document.getElementById('rxn-geo-map');
        if (mapEl && typeof mapEl.scrollIntoView === 'function') {
            mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Abrir popup: si hay marker, usar el del marker; si no, popup
        // anclado a la lat/lng directa con datos mínimos del row.
        if (!infoWindow) {
            infoWindow = new google.maps.InfoWindow();
        }
        const knownMarker = markerById.get(String(eventId));
        const knownPoint = pointById.get(String(eventId));
        if (knownMarker && knownPoint) {
            infoWindow.setContent(buildPopupHtml(knownPoint, cachedLabels));
            infoWindow.open(map, knownMarker);
        } else if (fallbackPopup) {
            infoWindow.setContent(fallbackPopup);
            infoWindow.setPosition(position);
            infoWindow.open(map);
        }
    }

    function highlightActiveRow(activeRow) {
        document.querySelectorAll('.rxn-geo-row-active').forEach(function (r) {
            r.classList.remove('rxn-geo-row-active');
        });
        if (activeRow) {
            activeRow.classList.add('rxn-geo-row-active');
        }
    }

    function wireTableRowClicks() {
        document.querySelectorAll('tr.rxn-geo-row-clickable').forEach(function (row) {
            row.addEventListener('click', function () {
                const lat = parseFloat(row.getAttribute('data-lat'));
                const lng = parseFloat(row.getAttribute('data-lng'));
                const eventId = row.getAttribute('data-event-id');
                if (!isFinite(lat) || !isFinite(lng)) return;

                // Popup fallback con lo que vemos en la tabla — mínimo pero útil
                // si los markers todavía no terminaron de cargar o el evento
                // está fuera del límite de 500 del mapa.
                const fechaCell = row.querySelector('td:nth-child(1)');
                const usuarioCell = row.querySelector('td:nth-child(2) strong');
                const eventoCell = row.querySelector('td:nth-child(3) .badge');
                const fallback = '' +
                    '<div style="font-size: 0.85rem; min-width: 200px;">' +
                        '<div style="font-weight: 700; margin-bottom: 4px;">' +
                            escapeHtml(eventoCell ? eventoCell.textContent.trim() : 'Evento') +
                        '</div>' +
                        '<div><strong>' + escapeHtml(usuarioCell ? usuarioCell.textContent.trim() : '—') + '</strong></div>' +
                        '<div><small style="color: #888;">' +
                            escapeHtml(fechaCell ? fechaCell.textContent.trim() : '') +
                        '</small></div>' +
                    '</div>';

                highlightActiveRow(row);
                focusEvent(eventId, lat, lng, fallback);
            });
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
        wireTableRowClicks();
    };
})();
