/* SmartBazars: Web settings default-location map on Leaflet + OpenStreetMap (was Google Maps). */
let defaultLocationMap, defaultLocationMarker;

function initDefaultLocationMap() {
    const el = document.getElementById('default-location-map');
    if (!el || typeof L === 'undefined' || defaultLocationMap) return;
    const latInput = document.getElementById('default-latitude');
    const lngInput = document.getElementById('default-longitude');
    const center = (latInput.value && lngInput.value) ? {lat: +latInput.value, lng: +lngInput.value} : {lat: 28.6315, lng: 77.2167};
    defaultLocationMap = SBGeo.createMap(el, center, 12);
    document.getElementById('place-autocomplete-card')?.remove();

    defaultLocationMarker = L.marker([center.lat, center.lng], {draggable: true, title: 'Default Location'}).addTo(defaultLocationMap);
    const place = async (lat, lng, label) => {
        defaultLocationMarker.setLatLng([lat, lng]);
        latInput.value = lat;
        lngInput.value = lng;
        const a = label ? {display: label} : await SBGeo.reverse(lat, lng);
        defaultLocationMarker.bindPopup(`<b>Default location</b><br>${a.display || ''}`).openPopup();
    };
    defaultLocationMarker.on('dragend', () => { const p = defaultLocationMarker.getLatLng(); place(p.lat, p.lng); });
    defaultLocationMap.on('click', e => place(e.latlng.lat, e.latlng.lng));
    SBGeo.addSearch(defaultLocationMap, r => place(r.lat, r.lng, r.display), {
        countries: () => Array.from(document.querySelectorAll('#select-countries option:checked')).map(o => o.value)
    });
    const fromInputs = () => {
        const lat = parseFloat(latInput.value), lng = parseFloat(lngInput.value);
        if (!isNaN(lat) && !isNaN(lng)) { defaultLocationMarker.setLatLng([lat, lng]); defaultLocationMap.setView([lat, lng]); }
    };
    latInput.addEventListener('change', fromInputs);
    lngInput.addEventListener('change', fromInputs);
}

document.addEventListener('DOMContentLoaded', initDefaultLocationMap);
