@extends('layouts.admin.app', ['page' => 'sb_wallet'])
@section('title', 'My Wallet')
@section('header_data')
    @php $page_title = 'My Wallet'; $page_pretitle = 'Recharge, move earnings, history'; @endphp
@endsection
@section('admin-content')
    @php $money = fn($v) => '₹' . number_format((float)$v, 2); $badge = ['pending' => 'bg-yellow-lt', 'approved' => 'bg-green-lt', 'rejected' => 'bg-red-lt']; @endphp
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <div id="rzpAlert"></div>
    @if($need > 0 && $need > $orderBalance)
        <div class="alert alert-warning"><strong>Order wallet is low.</strong> You need about {{ $money($need - $orderBalance) }} more to place the customer's order. Add money below, then continue.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            {{-- balances --}}
            <div class="sbw-balances mb-3">
                <div class="sbw-tile sbw-tile--order">
                    <div class="sbw-tile__label"><i class="ti ti-shopping-bag"></i> Order wallet</div>
                    <div class="sbw-tile__value" id="orderBal">{{ $money($orderBalance) }}</div>
                    <div class="sbw-tile__hint">pays customer orders</div>
                </div>
                <div class="sbw-tile">
                    <div class="sbw-tile__label"><i class="ti ti-cash"></i> Earnings</div>
                    <div class="sbw-tile__value text-green">{{ $money($earnings) }}</div>
                    <div class="sbw-tile__hint">commissions</div>
                </div>
                <div class="sbw-tile sbw-tile--coins">
                    <div class="sbw-tile__label"><i class="ti ti-coins"></i> Cashback coins</div>
                    <div class="sbw-tile__value">{{ number_format($coin['coins']) }}</div>
                    <div class="sbw-tile__hint">worth {{ $money($coin['value']) }}</div>
                </div>
            </div>
            <a href="{{ route('partner.offline.pos') }}" class="btn btn-warning w-100 mb-3"><i class="ti ti-shopping-cart me-1"></i>Place a customer order</a>

            {{-- Razorpay --}}
            <div class="card mb-3"><div class="card-header"><h4 class="card-title"><i class="ti ti-credit-card me-1"></i>Add money online (Razorpay)</h4></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach([500, 1000, 2000, 5000] as $q)<button type="button" class="btn btn-sm btn-outline-primary btn-pill rzp-chip" data-amt="{{ $q }}">₹{{ number_format($q) }}</button>@endforeach
                    </div>
                    <div class="input-group mb-2"><span class="input-group-text">₹</span>
                        <input type="number" id="rzpAmount" min="{{ $min }}" step="1" class="form-control" placeholder="Amount (min {{ $money($min) }})" value="{{ $need > $orderBalance ? ceil($need - $orderBalance) : '' }}"></div>
                    <button class="btn btn-primary w-100" id="rzpPay"><i class="ti ti-brand-razorpay me-1"></i>Pay with Razorpay</button>
                    <div class="text-secondary small mt-2">UPI, cards, net banking and wallets. The amount reaches your order wallet as soon as the payment succeeds.</div>
                </div>
            </div>

            {{-- cashback coins --}}
            <div class="card mb-3 sbw-coins-card"><div class="card-header"><h4 class="card-title"><i class="ti ti-coins me-1"></i>Move cashback coins to order wallet</h4></div>
                <form method="post" action="{{ route('partner.wallet.coins') }}" class="card-body" id="coinForm">@csrf
                    @if(!$coin['enabled'])
                        <div class="alert alert-warning mb-0">Cashback coins are switched off right now.</div>
                    @else
                        <div class="d-flex align-items-center justify-content-between mb-2 small">
                            <span class="text-secondary">You have <strong class="text-body">{{ number_format($coin['coins']) }}</strong> coins</span>
                            <span class="badge bg-yellow-lt">1 coin = {{ $money($coin['rate']) }}</span>
                        </div>
                        <div class="input-group mb-2"><span class="input-group-text"><i class="ti ti-coins"></i></span>
                            <input type="number" step="1" min="1" max="{{ $coin['coins'] }}" name="coins" id="coinAmount" class="form-control" placeholder="Coins (max {{ number_format($coin['coins']) }})" required>
                            <button type="button" class="btn" onclick="document.getElementById('coinAmount').value = {{ (int)$coin['coins'] }}; document.getElementById('coinAmount').dispatchEvent(new Event('input'))">Max</button></div>
                        <div class="small text-secondary mb-2">You get <strong class="text-body" id="coinValue">₹0.00</strong> in your order wallet.</div>
                        <button class="btn btn-warning w-100" @disabled($coin['coins'] < 1)><i class="ti ti-arrow-right me-1"></i>Move coins to order wallet</button>
                    @endif
                </form>
            </div>

            {{-- earnings transfer --}}
            <div class="card mb-3"><div class="card-header"><h4 class="card-title"><i class="ti ti-arrows-exchange me-1"></i>Move earnings to order wallet</h4></div>
                <form method="post" action="{{ route('partner.wallet.transfer') }}" class="card-body">@csrf
                    <div class="input-group mb-2"><span class="input-group-text">₹</span>
                        <input type="number" step="0.01" min="1" max="{{ $earnings }}" name="amount" id="trfAmount" class="form-control" placeholder="Amount (max {{ $money($earnings) }})" required>
                        <button type="button" class="btn" onclick="document.getElementById('trfAmount').value = {{ floor($earnings * 100) / 100 }}">Max</button></div>
                    <button class="btn btn-success w-100" @disabled($earnings < 1)>Move to order wallet</button>
                </form>
            </div>

            {{-- manual --}}
            <details class="card mb-3"><summary class="card-header"><h4 class="card-title">Paid by bank / UPI directly? Send a request</h4></summary>
                <form method="post" action="{{ route('partner.wallet.recharge') }}" enctype="multipart/form-data" class="card-body">@csrf
                    <div class="alert alert-info small">{{ $instructions }}</div>
                    <input type="number" step="1" min="{{ $min }}" name="amount" class="form-control mb-2" placeholder="Amount" required>
                    <select name="method" class="form-select mb-2"><option value="upi">UPI</option><option value="bank">Bank transfer</option><option value="cash">Cash at office</option><option value="other">Other</option></select>
                    <input name="reference" class="form-control mb-2" placeholder="UTR / reference number" maxlength="80" required>
                    <input type="file" name="proof" accept="image/*" class="form-control mb-2">
                    <input name="note" class="form-control mb-3" placeholder="Note (optional)" maxlength="255">
                    <button class="btn btn-outline-primary w-100">Send request</button>
                </form>
            </details>
        </div>

        <div class="col-lg-7">
            @if($requests->isNotEmpty())
            <div class="card mb-3"><div class="card-header"><h4 class="card-title">Recent recharges</h4></div>
                <div class="table-responsive"><table class="table card-table table-vcenter">
                    <thead><tr><th>Date</th><th>Reference</th><th>Mode</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>@foreach($requests as $r)<tr><td class="text-nowrap small">{{ $r->created_at->format('d M Y, h:i A') }}</td><td class="font-monospace small">{{ $r->gateway_payment_id ?: $r->reference }}</td>
                        <td>{{ $r->gateway ? 'Razorpay' : strtoupper($r->method) }}</td><td class="text-end">{{ $money($r->amount) }}</td>
                        <td><span class="badge {{ $badge[$r->status] }}">{{ ucfirst($r->status) }}</span>@if($r->admin_note && !$r->gateway)<div class="small text-secondary">{{ $r->admin_note }}</div>@endif</td></tr>@endforeach</tbody></table></div></div>
            @endif
            <div class="card"><div class="card-header"><h4 class="card-title me-auto">Wallet history</h4>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('partner.wallet') }}" class="btn {{ request('wallet') ? 'btn-outline-secondary' : 'btn-primary' }}">All</a>
                    <a href="{{ route('partner.wallet', ['wallet' => 'order']) }}" class="btn {{ request('wallet') === 'order' ? 'btn-primary' : 'btn-outline-secondary' }}">Order</a>
                    <a href="{{ route('partner.wallet', ['wallet' => 'earnings']) }}" class="btn {{ request('wallet') === 'earnings' ? 'btn-primary' : 'btn-outline-secondary' }}">Earnings</a>
                </div></div>
                <div class="table-responsive"><table class="table card-table table-vcenter">
                    <thead><tr><th>Date</th><th>Details</th><th>Wallet</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>@forelse($ledger as $l)<tr><td class="text-nowrap small">{{ $l->created_at->format('d M Y, h:i A') }}</td><td class="small">{{ $l->description }}</td>
                        <td><span class="badge {{ $l->wallet === 'order' ? 'bg-blue-lt' : 'bg-green-lt' }}">{{ $l->wallet === 'order' ? 'Order' : 'Earnings' }}</span>@if($l->reference_type === 'coins')<span class="badge bg-yellow-lt ms-1">Coins</span>@endif</td>
                        <td class="text-end {{ $l->type === 'credit' ? 'text-green' : 'text-red' }}">{{ $l->type === 'credit' ? '+' : '−' }}{{ $money($l->amount) }}</td><td class="text-end">{{ $money($l->balance_after) }}</td></tr>
                    @empty<tr><td colspan="5" class="text-center text-secondary py-3">No wallet activity yet.</td></tr>@endforelse</tbody></table></div>
                <div class="card-footer">{{ $ledger->links() }}</div></div>
        </div>
    </div>
@endsection
@push('styles')
<style>
    .sbw-balances { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 575.98px) { .sbw-balances { grid-template-columns: 1fr 1fr; } .sbw-tile--order { grid-column: 1 / -1; } }
    .sbw-tile { border-radius: 16px; padding: 1rem; background: var(--tblr-bg-surface); border: 1px solid var(--tblr-border-color); box-shadow: 0 1px 2px rgba(16,24,40,.05); min-width: 0; }
    .sbw-tile__label { font-size: .75rem; font-weight: 650; color: var(--tblr-secondary); display: flex; gap: .35rem; align-items: center; white-space: nowrap; }
    .sbw-tile__value { font-size: clamp(1.15rem, .9rem + 1vw, 1.6rem); font-weight: 800; letter-spacing: -.02em; margin-top: .35rem; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .sbw-tile__hint { font-size: .72rem; color: var(--tblr-secondary); }
    .sbw-tile--order { background: linear-gradient(135deg, #0A2E73, #1a63cf); color: #fff; border: 0; box-shadow: 0 14px 30px -14px rgba(10,46,115,.7); }
    .sbw-tile--order .sbw-tile__label, .sbw-tile--order .sbw-tile__hint { color: rgba(255,255,255,.78); }
    .sbw-tile--coins { background: linear-gradient(135deg, #fff8e6, #ffffff); border-color: #ffe2a8; }
    [data-bs-theme=dark] .sbw-tile--coins { background: linear-gradient(135deg, rgba(245,159,0,.16), transparent); border-color: rgba(245,159,0,.3); }
    .sbw-tile--coins .sbw-tile__value { color: #b26a00; }
    .card-table td.text-end { white-space: nowrap; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const inp = document.getElementById('coinAmount'), out = document.getElementById('coinValue');
    if (!inp || !out) return;
    const rate = {{ (float)$coin['rate'] }};
    const fmt = v => '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    inp.addEventListener('input', () => { out.textContent = fmt(Math.max(0, Math.floor(+inp.value || 0)) * rate); });
})();
</script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const amt = document.getElementById('rzpAmount'), btn = document.getElementById('rzpPay'), box = document.getElementById('rzpAlert');
    const say = (type, t) => { box.innerHTML = '<div class="alert alert-' + type + '">' + String(t).replace(/[<>]/g, '') + '</div>'; window.scrollTo({ top: 0, behavior: 'smooth' }); };
    const post = (url, body) => fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) })
        .then(async r => { const j = await r.json().catch(() => ({})); if (!r.ok) throw new Error(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Request failed')); return j; });

    document.querySelectorAll('.rzp-chip').forEach(b => b.addEventListener('click', () => { amt.value = b.dataset.amt; }));

    btn.addEventListener('click', async () => {
        const v = parseFloat(amt.value || 0);
        if (!(v >= {{ $min }})) return say('warning', 'Enter at least ₹{{ number_format($min, 0) }}.');
        btn.disabled = true; btn.textContent = 'Starting payment…';
        try {
            const o = await post(@json(route('partner.wallet.razorpay.order')), { amount: v });
            const rzp = new Razorpay({
                key: o.key, amount: o.amount_paise, currency: 'INR', name: 'SmartBazars', description: 'BJO order wallet recharge', order_id: o.order_id,
                prefill: { name: o.name, email: o.email, contact: o.contact }, theme: { color: '#0A2E73' },
                handler: async function (r) {
                    try {
                        const res = await post(@json(route('partner.wallet.razorpay.verify')), r);
                        say('success', res.message);
                        setTimeout(() => location.reload(), 1200);
                    } catch (e) { say('warning', e.message); setTimeout(() => location.reload(), 4000); }
                },
                modal: { ondismiss: function () { btn.disabled = false; btn.innerHTML = '<i class="ti ti-brand-razorpay me-1"></i>Pay with Razorpay'; } },
            });
            rzp.on('payment.failed', function (r) { say('danger', 'Payment failed: ' + (r.error && r.error.description ? r.error.description : 'please try again.')); });
            rzp.open();
        } catch (e) {
            say('danger', e.message);
            btn.disabled = false; btn.innerHTML = '<i class="ti ti-brand-razorpay me-1"></i>Pay with Razorpay';
        }
    });
})();
</script>
@endpush
