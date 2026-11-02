@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_pos'])
@section('title', $who === 'worker' ? 'Order for Customer' : 'Offline Sale')
@section('header_data')
    @php $page_title = $who === 'worker' ? 'Order for a Card Holder' : 'Offline Sale (POS)'; $page_pretitle = $seller->displayName(); @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    <div id="posAlert"></div>
    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Welcome Kit recommendation (shown on top once a card holder without a kit is found) --}}
            <div id="kitBox" class="mb-3 d-none"></div>
            {{-- 1. customer --}}
            <div class="card mb-3"><div class="card-header"><h4 class="card-title">1 · Customer User Card</h4></div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" id="cardInput" class="form-control form-control-lg font-monospace" placeholder="User Card no. or mobile" autocomplete="off" value="{{ request('card') }}">
                        <button class="btn btn-primary" id="btnFind" type="button">Find</button>
                        <button class="btn btn-outline-primary" type="button" onclick="sbOpenScanner()"><i class="ti ti-scan"></i></button>
                    </div>
                    <div id="custBox" class="mt-3 d-none"></div>
                </div>
            </div>
            {{-- 2. products --}}
            <div class="card"><div class="card-header"><h4 class="card-title">2 · Products</h4></div>
                <div class="card-body">
                    <input type="search" id="prodQ" class="form-control mb-3" placeholder="Search by name, SKU or barcode…">
                    <div id="prodList" class="row g-2"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card position-sticky" style="top:12px"><div class="card-header"><h4 class="card-title">3 · Bill</h4></div>
                <div class="card-body">
                    <div id="cartEmpty" class="text-secondary text-center py-3">No products added.</div>
                    <div id="kitRow"></div>
                    <div id="cartRows"></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>Products</span><strong id="tSub">₹0.00</strong></div>
                    <div class="d-flex justify-content-between" id="tKitRow" style="display:none !important"><span>Welcome Kit</span><strong id="tKit">₹0.00</strong></div>
                    <div class="d-flex justify-content-between text-secondary"><span>Delivery charge</span><span>₹0.00 (offline)</span></div>
                    <div class="d-flex justify-content-between text-red"><span>Coins discount</span><strong id="tCoin">− ₹0.00</strong></div>
                    <div class="d-flex justify-content-between fs-3 mt-1"><span>To pay</span><strong id="tPay">₹0.00</strong></div>

                    <div class="mt-3" id="coinBox" style="display:none">
                        <label class="form-label mb-1">Use card coins <span class="text-secondary" id="coinAvail"></span></label>
                        <div class="input-group"><input type="number" min="0" id="coinsIn" class="form-control" value="0"><button class="btn" type="button" id="btnMaxCoins">Use max</button></div>
                    </div>

                    @if($who === 'seller')
                        <div class="mt-3"><label class="form-label mb-1">Customer pays by</label>
                            <select id="payMode" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option></select></div>
                    @else
                        <div class="alert alert-info mt-3 mb-0 py-2">Balance after coins is paid from your order wallet (<strong>₹{{ number_format((float)$wallet, 2) }}</strong>). The customer collects the goods from {{ $seller->displayName() }}.</div>
                    @endif
                    @if($who === 'worker')
                        <div id="walletLow" class="alert alert-warning mt-3 mb-0 d-none">Your order wallet is short by <strong id="wlNeed"></strong>. <a id="wlLink" href="{{ route('partner.wallet') }}" class="fw-bold">Recharge wallet</a> first, then place the order.</div>
                    @endif
                    <input type="text" id="noteIn" class="form-control mt-3" maxlength="255" placeholder="Note (optional)">
                    <button class="btn btn-success w-100 btn-lg mt-3" id="btnSell" disabled>{{ $who === 'worker' ? 'Place order' : 'Complete sale' }}</button>
                </div>
            </div>
        </div>
    </div>
    @include('offline.partials.scanner', ['onScan' => 'sbPosScanned'])
@endsection
@push('scripts')
<script>
(function () {
    const URLS = { lookup: @json(route($rp . 'lookup.json')), products: @json(route($rp . 'products')), sell: @json(route($rp . 'sell')) };
    const MAXPCT = {{ (float)\App\Services\SmartBazars\ProgramSettings::get('max_coin_redeem_percent', 100) }};
    const WORKER = {{ $who === 'worker' ? 'true' : 'false' }};
    const WALLET = {{ (float)($wallet ?? 0) }};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    let cust = null, cart = {}, timer = null, kitOn = false;
    const $ = id => document.getElementById(id);
    const money = v => '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function flash(type, text) { $('posAlert').innerHTML = '<div class="alert alert-' + type + ' alert-dismissible"><div>' + esc(text) + '</div><a class="btn-close" data-bs-dismiss="alert"></a></div>'; window.scrollTo({ top: 0, behavior: 'smooth' }); }

    async function findCustomer(q) {
        if (!q) return;
        const r = await fetch(URLS.lookup + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const box = $('custBox'); box.classList.remove('d-none');
        if (!r.ok) { cust = null; box.innerHTML = '<div class="alert alert-warning mb-0">No User Card found for this number.</div>'; recalc(); return; }
        cust = await r.json();
        box.innerHTML = '<div class="d-flex gap-3 align-items-center">'
            + (cust.photo ? '<img src="' + cust.photo + '" class="rounded" style="width:56px;height:70px;object-fit:cover">' : '<span class="avatar avatar-lg bg-blue-lt">' + esc(cust.name[0]) + '</span>')
            + '<div><div class="fw-bold fs-4">' + esc(cust.name) + '</div><div class="font-monospace text-primary">' + esc(cust.formatted) + '</div>'
            + '<div class="text-secondary small">' + esc(cust.mobile) + (cust.location ? ' · ' + esc(cust.location) : '') + '</div></div>'
            + '<div class="ms-auto text-end"><span class="badge ' + (cust.valid ? 'bg-green-lt' : 'bg-red-lt') + '">' + (cust.valid ? 'Active' : 'Blocked/expired') + '</span>'
            + '<div class="mt-1"><i class="ti ti-coins text-yellow"></i> <strong>' + cust.coins + '</strong> coins</div></div></div>';
        kitOn = false; renderKit();
        $('coinBox').style.display = cust.coins > 0 ? '' : 'none';
        $('coinAvail').textContent = '(' + cust.coins + ' available, 1 coin = ' + money(cust.coin_value) + ')';
        $('coinsIn').value = 0;
        recalc();
    }
    window.sbPosScanned = card => { $('cardInput').value = card; findCustomer(card); };
    $('btnFind').addEventListener('click', () => findCustomer($('cardInput').value.trim()));
    $('cardInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); findCustomer(e.target.value.trim()); } });

    function renderKit() {
        const box = $('kitBox'), k = cust && cust.kit;
        if (!k || !k.recommend) {
            box.classList.add('d-none'); box.innerHTML = '';
            if (cust && k && k.has) { box.classList.remove('d-none'); box.innerHTML = '<div class="alert alert-success mb-0 py-2"><i class="ti ti-gift me-1"></i>Welcome Kit already purchased by this customer.</div>'; }
            if (cust && k && k.pending) { box.classList.remove('d-none'); box.innerHTML = '<div class="alert alert-info mb-0 py-2"><i class="ti ti-gift me-1"></i>A Welcome Kit is already waiting for pickup for this customer.</div>'; }
            renderKitRow(); return;
        }
        box.classList.remove('d-none');
        box.innerHTML = '<div class="card border-0 text-white" style="background:linear-gradient(135deg,#0A2E73,#1a63cf)"><div class="card-body">'
            + '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
            + '<div><span class="badge bg-yellow text-dark mb-1">★ Recommended' + (k.first_purchase ? ' · first purchase' : '') + '</span>'
            + '<div class="fs-3 fw-bold">' + esc(k.name || 'SmartBazars Welcome Kit') + ' — ' + money(k.price) + '</div>'
            + '<div class="small opacity-75">' + (k.items && k.items.length ? esc(k.items.join(' · ')) : 'Starter pack for new card holders') + '</div>'
            + '<div class="small opacity-75 mt-1">Offer this first: it activates full Welcome-Kit benefits and repurchase commissions for the customer\'s network.</div></div>'
            + '<button type="button" class="btn ' + (kitOn ? 'btn-light' : 'btn-warning') + '" id="btnKit">' + (kitOn ? 'Remove Welcome Kit' : 'Add Welcome Kit ' + money(k.price)) + '</button></div></div></div>';
        $('btnKit').addEventListener('click', () => { kitOn = !kitOn; renderKit(); recalc(); });
        renderKitRow();
    }
    function renderKitRow() {
        const k = cust && cust.kit;
        $('kitRow').innerHTML = kitOn && k ? '<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded bg-yellow-lt"><div class="flex-grow-1"><div class="small fw-medium">★ ' + esc(k.name || 'SmartBazars Welcome Kit') + '</div><div class="text-secondary small">Qty 1 · no coin discount</div></div><div>' + money(k.price) + '</div></div>' : '';
    }

    async function loadProducts(q) {
        const r = await fetch(URLS.products + '?q=' + encodeURIComponent(q || ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const list = await r.json(), el = $('prodList');
        el.innerHTML = list.length ? '' : '<div class="col-12 text-secondary">No products found.</div>';
        list.forEach(p => {
            const c = document.createElement('div'); c.className = 'col-md-6';
            c.innerHTML = '<div class="border rounded p-2 h-100 d-flex flex-column"><div class="fw-medium">' + esc(p.title) + '</div>'
                + '<div class="text-secondary small">' + (p.sku ? 'SKU ' + esc(p.sku) + ' · ' : '') + 'Stock ' + p.stock + (p.cashback_coins ? ' · <span class="text-yellow">+' + p.cashback_coins + ' coins</span>' : '') + '</div>'
                + '<div class="mt-auto d-flex justify-content-between align-items-center pt-2"><strong>' + money(p.price) + '</strong>'
                + '<button class="btn btn-sm btn-primary" ' + (p.stock < 1 ? 'disabled' : '') + '>Add</button></div></div>';
            c.querySelector('button').addEventListener('click', () => addToCart(p));
            el.appendChild(c);
        });
    }
    $('prodQ').addEventListener('input', e => { clearTimeout(timer); timer = setTimeout(() => loadProducts(e.target.value.trim()), 250); });

    function addToCart(p) {
        const row = cart[p.id] || (cart[p.id] = { p, qty: 0 });
        if (row.qty + 1 > p.stock) { flash('warning', 'Only ' + p.stock + ' in stock for ' + p.title); return; }
        row.qty++; render();
    }
    function render() {
        const wrap = $('cartRows'); wrap.innerHTML = '';
        const ids = Object.keys(cart);
        $('cartEmpty').style.display = (ids.length || kitOn) ? 'none' : '';
        ids.forEach(id => {
            const { p, qty } = cart[id];
            const d = document.createElement('div'); d.className = 'd-flex align-items-center gap-2 mb-2';
            d.innerHTML = '<div class="flex-grow-1"><div class="small fw-medium">' + esc(p.title) + '</div><div class="text-secondary small">' + money(p.price) + ' each</div></div>'
                + '<input type="number" min="1" max="' + p.stock + '" value="' + qty + '" class="form-control form-control-sm" style="width:70px">'
                + '<div style="width:80px" class="text-end">' + money(p.price * qty) + '</div><button class="btn btn-sm btn-icon btn-ghost-danger" title="Remove">&times;</button>';
            d.querySelector('input').addEventListener('change', e => { const v = Math.max(1, Math.min(p.stock, parseInt(e.target.value || 1))); cart[id].qty = v; render(); });
            d.querySelector('button').addEventListener('click', () => { delete cart[id]; render(); });
            wrap.appendChild(d);
        });
        recalc();
    }
    function subtotal() { return Object.values(cart).reduce((s, r) => s + r.p.price * r.qty, 0); }
    function coinCap() {
        if (!cust) return 0;
        const bySub = Math.floor((subtotal() * MAXPCT / 100) / cust.coin_value);
        return Math.max(0, Math.min(cust.coins, bySub));
    }
    function recalc() {
        const sub = subtotal(); let coins = parseInt($('coinsIn').value || 0);
        const cap = coinCap(); if (coins > cap) { coins = cap; $('coinsIn').value = cap; }
        const kitPrice = kitOn && cust && cust.kit ? Number(cust.kit.price) : 0;
        const disc = cust ? coins * cust.coin_value : 0, pay = Math.max(0, sub - disc) + kitPrice;
        $('tSub').textContent = money(sub); $('tCoin').textContent = '− ' + money(disc); $('tPay').textContent = money(pay);
        $('tKit').textContent = money(kitPrice); $('tKitRow').style.cssText = kitPrice ? '' : 'display:none !important';
        $('btnSell').disabled = !(cust && cust.valid && (sub > 0 || kitPrice > 0) && (!WORKER || pay <= WALLET + 0.001));
        if (WORKER) {
            const short = pay - WALLET, low = cust && (sub > 0 || kitPrice > 0) && short > 0.001;
            $('walletLow').classList.toggle('d-none', !low);
            if (low) { $('wlNeed').textContent = money(short); $('wlLink').href = @json(route('partner.wallet')) + '?need=' + Math.ceil(pay); }
        }
        if (WORKER && cust && (sub > 0 || kitPrice > 0) && pay > WALLET + 0.001) $('tPay').classList.add('text-red'); else $('tPay').classList.remove('text-red');
    }
    $('coinsIn').addEventListener('input', recalc);
    $('btnMaxCoins').addEventListener('click', () => { $('coinsIn').value = coinCap(); recalc(); });

    $('btnSell').addEventListener('click', async () => {
        const btn = $('btnSell'); btn.disabled = true; const label = btn.textContent; btn.textContent = 'Saving…';
        const body = {
            card: cust.card_number, coins: parseInt($('coinsIn').value || 0), note: $('noteIn').value,
            payment_mode: $('payMode') ? $('payMode').value : null,
            items: Object.values(cart).map(r => ({ id: r.p.id, qty: r.qty })), welcome_kit: kitOn,
        };
        const r = await fetch(URLS.sell, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
        const j = await r.json().catch(() => ({}));
        if (r.ok && j.ok) {
            cart = {}; kitOn = false; render(); $('coinsIn').value = 0; $('noteIn').value = '';
            $('posAlert').innerHTML = '<div class="alert alert-success"><div class="d-flex justify-content-between flex-wrap gap-2"><span><strong>' + esc(j.message) + '</strong> Paid ' + money(j.total) + (j.coins_used ? ' · coins used ' + j.coins_used : '') + (j.coins_earned ? ' · cashback ' + j.coins_earned + ' coins' : '') + '</span><a href="' + j.url + '" class="btn btn-sm btn-success">View receipt</a></div></div>';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            findCustomer(body.card); loadProducts($('prodQ').value.trim());
        } else {
            const msg = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Could not save the sale.');
            flash('danger', msg); btn.textContent = label; recalc();
        }
        btn.textContent = label;
    });

    loadProducts('');
    if ($('cardInput').value) findCustomer($('cardInput').value.trim()).then(() => { if (new URLSearchParams(location.search).get('kit') === '1' && cust && cust.kit && cust.kit.recommend) { kitOn = true; renderKit(); recalc(); } });
})();
</script>
@endpush
