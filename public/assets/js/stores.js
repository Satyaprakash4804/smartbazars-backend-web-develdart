/* SmartBazars: Store location picker on Leaflet + OpenStreetMap (was Google Maps). */
let map, marker;

function initMap() {
    const el = document.getElementById('map');
    if (!el || typeof L === 'undefined' || map) return;
    el.style.height = el.style.height || '420px';
    const exLat = document.getElementById('latitude')?.value;
    const exLng = document.getElementById('longitude')?.value;
    const has = exLat && exLng;
    const center = has ? {lat: parseFloat(exLat), lng: parseFloat(exLng)} : {lat: 25.5941, lng: 85.1376};
    map = SBGeo.createMap(el, center, has ? 16 : 11);
    const existing = document.getElementById('autocomplete-container');
    if (existing) existing.style.display = 'none';

    marker = L.marker([center.lat, center.lng], {draggable: true}).addTo(map);
    marker.on('dragend', () => handleLocationSelection(marker.getLatLng()));
    map.on('click', e => handleLocationSelection(e.latlng));
    SBGeo.addSearch(map, r => handleLocationSelection({lat: r.lat, lng: r.lng}, r),
        {placeholder: 'Search your shop area, market or PIN code…'});
    SBGeo.drawZones(map).catch(e => console.warn('Unable to render delivery zones on map:', e));
}

async function handleLocationSelection(latlng, found = null) {
    const lat = +(+latlng.lat).toFixed(7), lng = +(+latlng.lng).toFixed(7);
    marker.setLatLng([lat, lng]);
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    const a = found || await SBGeo.reverse(lat, lng);

    if (a.country) {
        const sel = document.getElementById('select-countries');
        if (sel && sel.tomselect && typeof loadCountryAndSetValue === 'function') {
            loadCountryAndSetValue(sel.tomselect, a.country);
        }
    }
    const set = (id, v) => { const f = document.getElementById(id); if (f) f.value = v || ''; };
    set('city', a.city);
    set('state', a.state);
    set('zipcode', a.postal_code);
    set('landmark', a.street);
    set('address', a.display);
    marker.bindPopup(`<b>${a.name || 'Selected location'}</b><br>${a.display || (lat + ', ' + lng)}<br><small style="color:#666">Drag the marker to adjust</small>`).openPopup();
}

document.addEventListener('DOMContentLoaded', function () {
    try { initMap(); } catch (error) { console.error('Error initializing map:', error); }
});

$(document).ready(function () {

    const storesTable = $('#stores-table').DataTable();
// Reload table when filters change
    $('#verificationStatus, #visibilityStatus').on('change', function () {
        storesTable.ajax.reload(null, false);
    });

// Add filter params to AJAX request
    $('#stores-table').on('preXhr.dt', function (e, settings, data) {
        data.verification_status = $('#verificationStatus').val();
        data.visibility_status = $('#visibilityStatus').val();
    });
});
