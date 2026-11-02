/*
 * SmartBazars — free maps with Leaflet + OpenStreetMap (no Google API key needed).
 * Search & reverse-geocoding use OpenStreetMap Nominatim (free, fair-use: ~1 request/second).
 */
window.SBGeo = (function () {
    const INDIA = {lat: 22.9734, lng: 78.6569};
    const NOMINATIM = 'https://nominatim.openstreetmap.org';

    function tiles(map) {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        return map;
    }

    function createMap(el, center, zoom) {
        const map = L.map(el, {scrollWheelZoom: true}).setView([center.lat, center.lng], zoom || 12);
        return tiles(map);
    }

    function parseAddress(r) {
        const a = (r && r.address) || {};
        return {
            display: r ? r.display_name : '',
            name: (r && (r.name || (r.display_name || '').split(',')[0])) || 'Selected location',
            city: a.city || a.town || a.village || a.city_district || a.county || a.state_district || '',
            state: a.state || '',
            postal_code: a.postcode || '',
            country: a.country || '',
            country_code: (a.country_code || '').toUpperCase(),
            street: [a.house_number, a.road].filter(Boolean).join(' ') || a.neighbourhood || a.suburb || ''
        };
    }

    async function reverse(lat, lng) {
        try {
            const r = await fetch(`${NOMINATIM}/reverse?format=jsonv2&addressdetails=1&lat=${lat}&lon=${lng}`, {headers: {'Accept-Language': 'en'}});
            return parseAddress(await r.json());
        } catch (e) {
            return parseAddress(null);
        }
    }

    async function search(q, countryCodes) {
        const cc = countryCodes && countryCodes.length ? `&countrycodes=${countryCodes.join(',').toLowerCase()}` : '';
        const r = await fetch(`${NOMINATIM}/search?format=jsonv2&addressdetails=1&limit=6${cc}&q=${encodeURIComponent(q)}`, {headers: {'Accept-Language': 'en'}});
        return (await r.json()).map(x => ({lat: parseFloat(x.lat), lng: parseFloat(x.lon), bbox: x.boundingbox, ...parseAddress(x)}));
    }

    /** Adds a search box on the map. onPick({lat,lng,bbox,...address}) */
    function addSearch(map, onPick, opts = {}) {
        const Ctl = L.Control.extend({
            options: {position: 'topleft'},
            onAdd: function () {
                const box = L.DomUtil.create('div', 'sb-search leaflet-bar');
                box.innerHTML = `<input type="text" placeholder="${opts.placeholder || 'Search place, area or PIN code…'}" autocomplete="off">
                                 <div class="sb-results"></div>`;
                L.DomEvent.disableClickPropagation(box);
                L.DomEvent.disableScrollPropagation(box);
                const input = box.querySelector('input'), list = box.querySelector('.sb-results');
                let t;
                input.addEventListener('input', () => {
                    clearTimeout(t);
                    const q = input.value.trim();
                    if (q.length < 3) { list.innerHTML = ''; return; }
                    t = setTimeout(async () => {
                        try {
                            const res = await search(q, typeof opts.countries === 'function' ? opts.countries() : (opts.countries || ['in']));
                            list.innerHTML = res.length ? '' : '<div class="sb-empty">No results</div>';
                            res.forEach(r => {
                                const item = document.createElement('div');
                                item.className = 'sb-item';
                                item.textContent = r.display;
                                item.onclick = () => {
                                    list.innerHTML = '';
                                    input.value = r.display;
                                    if (r.bbox) {
                                        map.fitBounds([[+r.bbox[0], +r.bbox[2]], [+r.bbox[1], +r.bbox[3]]], {maxZoom: 16});
                                    } else {
                                        map.setView([r.lat, r.lng], 16);
                                    }
                                    onPick && onPick(r);
                                };
                                list.appendChild(item);
                            });
                        } catch (e) { list.innerHTML = '<div class="sb-empty">Search failed</div>'; }
                    }, 600);
                });
                return box;
            }
        });
        map.addControl(new Ctl());
        if (!document.getElementById('sb-leaflet-css')) {
            const s = document.createElement('style');
            s.id = 'sb-leaflet-css';
            s.textContent = `.sb-search{background:#fff;padding:6px;border-radius:8px;width:320px;max-width:70vw}
                .sb-search input{width:100%;border:1px solid #ddd;border-radius:6px;padding:6px 8px;font-size:14px}
                .sb-results{max-height:240px;overflow:auto}.sb-item{padding:6px 8px;font-size:13px;cursor:pointer;border-bottom:1px solid #f1f1f1}
                .sb-item:hover{background:#e8f5e9}.sb-empty{padding:6px 8px;font-size:13px;color:#888}
                .leaflet-container{font:inherit}`;
            document.head.appendChild(s);
        }
    }

    async function loadZones() {
        const response = await fetch('/api/delivery-zone?per_page=500', {headers: {Accept: 'application/json'}});
        if (!response.ok) return [];
        const json = await response.json();
        return (json && json.data && Array.isArray(json.data.data)) ? json.data.data : (Array.isArray(json.data) ? json.data : []);
    }

    function zonePath(zone) {
        if (!zone.boundary_json || !Array.isArray(zone.boundary_json)) return [];
        return zone.boundary_json.map(p => [parseFloat(p.lat), parseFloat(p.lng)]).filter(p => !isNaN(p[0]) && !isNaN(p[1]));
    }

    /** Draw zones (blue, non-interactive). Returns array of layers. */
    async function drawZones(map, excludeId) {
        const layers = [];
        (await loadZones()).forEach(z => {
            if (excludeId && z.id === excludeId) return;
            const path = zonePath(z);
            if (path.length < 3) return;
            layers.push(L.polygon(path, {color: '#0066ff', weight: 2, fillColor: '#1a73e8', fillOpacity: 0.08, interactive: false})
                .bindTooltip(z.name || 'Zone', {sticky: true}).addTo(map));
        });
        return layers;
    }

    return {INDIA, createMap, addSearch, reverse, search, drawZones, loadZones};
})();
