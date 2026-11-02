/* SmartBazars: Delivery zone editor on Leaflet + OpenStreetMap (was Google Maps). */
let map, drawnItems, polygon = null, originalPath = null;

function initMap() {
    const el = document.getElementById('map');
    if (!el || typeof L === 'undefined' || map) return; // list page loads this file too; init once
    const latIn = document.getElementById('center-latitude');
    const lngIn = document.getElementById('center-longitude');
    const center = (latIn && latIn.value && lngIn.value) ? {lat: +latIn.value, lng: +lngIn.value} : {lat: 28.6139, lng: 77.2090};
    map = SBGeo.createMap(el, center, latIn && latIn.value ? 10 : 6);

    document.getElementById('place-autocomplete-card')?.remove();
    SBGeo.addSearch(map, r => L.popup().setLatLng([r.lat, r.lng]).setContent(`<b>${r.name}</b><br>${r.display}`).openOn(map));

    drawnItems = new L.FeatureGroup().addTo(map);
    const drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {allowIntersection: false, showArea: true, shapeOptions: {color: '#d32f2f', fillColor: '#FF0000', fillOpacity: 0.2, weight: 2}},
            polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false
        },
        edit: {featureGroup: drawnItems, remove: true}
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, e => {
        drawnItems.clearLayers();
        polygon = e.layer;
        drawnItems.addLayer(polygon);
        updateBoundaryInput();
    });
    map.on(L.Draw.Event.EDITED, updateBoundaryInput);
    map.on(L.Draw.Event.DELETED, () => { polygon = null; document.getElementById('boundary-json').value = ''; });

    // restore existing polygon
    const boundaryInput = document.getElementById('boundary-json');
    if (boundaryInput && boundaryInput.value) {
        try {
            const arr = JSON.parse(boundaryInput.value);
            if (Array.isArray(arr) && arr.length > 2) {
                originalPath = arr.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
                setPolygon(originalPath);
            }
        } catch (e) { /* ignore */ }
    }

    const currentId = parseInt(document.getElementById('current-zone-id')?.value || '0') || null;
    SBGeo.drawZones(map, currentId).catch(() => {});

    document.getElementById('clear-last')?.addEventListener('click', () => {
        drawnItems.clearLayers();
        polygon = null;
        document.getElementById('boundary-json').value = '';
    });
    document.getElementById('reset-zone')?.addEventListener('click', () => {
        if (originalPath) setPolygon(originalPath);
    });
}

function setPolygon(path) {
    drawnItems.clearLayers();
    polygon = L.polygon(path, {color: '#d32f2f', fillColor: '#FF0000', fillOpacity: 0.2, weight: 2});
    drawnItems.addLayer(polygon);
    map.fitBounds(polygon.getBounds());
    updateBoundaryInput();
}

function updateBoundaryInput() {
    if (!polygon) return;
    const path = polygon.getLatLngs()[0].map(ll => ({lat: +ll.lat.toFixed(6), lng: +ll.lng.toFixed(6)}));
    document.getElementById('boundary-json').value = JSON.stringify(path);
    const c = {lat: path.reduce((s, p) => s + p.lat, 0) / path.length, lng: path.reduce((s, p) => s + p.lng, 0) / path.length};
    document.getElementById('center-latitude').value = c.lat;
    document.getElementById('center-longitude').value = c.lng;
    let max = 0;
    path.forEach(p => { max = Math.max(max, haversineDistance(c, p)); });
    document.getElementById('radius-km').value = max.toFixed(3);
}

function haversineDistance(a, b) {
    const R = 6371, r = d => d * Math.PI / 180;
    const dLat = r(b.lat - a.lat), dLng = r(b.lng - a.lng);
    const x = Math.sin(dLat / 2) ** 2 + Math.sin(dLng / 2) ** 2 * Math.cos(r(a.lat)) * Math.cos(r(b.lat));
    return 2 * R * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}

document.addEventListener('DOMContentLoaded', function () {
    try { initMap(); } catch (e) { console.error('Error initializing map:', e); }
    document.addEventListener('click', function (event) {
        handleDelete(event, '.delete-delivery-zone', `/${panel}/delivery-zones/`, 'You are about to delete this Zone.');
    });
});
