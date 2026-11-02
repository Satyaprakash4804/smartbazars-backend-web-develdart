@extends('layouts.seller.app', ['page' => 'sb_vendor_business', 'sub_page' => 'sb_bulk'])
@section('title', 'Bulk Purchase')
@section('header_data')
    @php $page_title = 'Bulk Purchase from SmartBazars'; $page_pretitle = 'Vendor prices · pay partly with coins'; @endphp
@endsection
@section('seller-content')
    @include('smartbazars._errors')
    <form method="POST" action="{{ route('seller.smartbazars.wholesale.place') }}" id="bulk">
        @csrf
        <div class="row row-cards">
            <div class="col-lg-8">
                <div class="row row-cards">
                    @forelse($products as $p)
                        <div class="col-sm-6 col-xl-4">
                            <div class="card h-100">
                                <div class="ratio ratio-4x3 bg-light" style="background:center/cover no-repeat url('{{ $p->image_url }}')"></div>
                                <div class="card-body d-flex flex-column">
                                    @if($p->is_smartbazars)<span class="badge bg-green-lt mb-1 align-self-start">SmartBazars product</span>@endif
                                    <div class="fw-bold">{{ $p->title }}</div>
                                    <div class="small text-secondary mb-2">{{ $p->unit }}</div>
                                    <div class="d-flex align-items-baseline gap-2"><span class="h3 mb-0">₹{{ number_format($p->vendor_price, 2) }}</span>@if($p->mrp > $p->vendor_price)<s class="text-secondary small">₹{{ number_format($p->mrp, 2) }}</s>@endif</div>
                                    <div class="small text-secondary mb-2">Min {{ $p->min_qty }}{{ $p->max_qty ? ' · max ' . $p->max_qty : '' }}{{ $p->available_stock !== null ? ' · ' . $p->available_stock . ' in stock' : '' }}</div>
                                    <div class="mt-auto input-group">
                                        <span class="input-group-text">Qty</span>
                                        <input type="number" class="form-control sb-qty" name="qty[{{ $p->id }}]" value="0" min="0" @if($p->max_qty || $p->available_stock !== null) max="{{ min(array_filter([$p->max_qty, $p->available_stock], fn($v) => $v !== null)) }}" @endif @disabled($p->available_stock === 0)
                                               data-price="{{ $p->vendor_price }}" data-min="{{ $p->min_qty }}" data-coinpct="{{ $p->max_coin_percent }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><div class="card"><div class="card-body text-secondary">SmartBazars has not listed bulk products yet.</div></div></div>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card position-sticky" style="top:80px">
                    <div class="card-header"><h3 class="card-title">Order summary</h3></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between"><span>Subtotal</span><b id="sb-sub">₹0.00</b></div>
                        <hr class="my-2">
                        <label class="form-label d-flex align-items-center gap-2"><img src="{{ asset('assets/images/smartbazars/coin.png') }}" width="20" height="20"> Use coins (you have {{ number_format($seller->coin_balance) }})</label>
                        <input type="number" name="use_coins" id="sb-coins" class="form-control mb-1" min="0" max="{{ $seller->coin_balance }}" value="0">
                        <div class="small text-secondary mb-2" id="sb-coincap">1 coin = ₹{{ $coinValue }}</div>
                        <div class="d-flex justify-content-between"><span>To pay</span><b class="fs-2" id="sb-pay">₹0.00</b></div>
                        <hr class="my-2">
                        <label class="form-label">Payment</label>
                        <select name="payment_method" class="form-select mb-2">
                            <option value="settlement">Deduct from my earnings</option>
                            <option value="offline">Pay SmartBazars offline (cash/UPI)</option>
                        </select>
                        <input name="vendor_note" class="form-control mb-3" placeholder="Note for SmartBazars (optional)">
                        <button class="btn btn-primary w-100" id="sb-place" disabled>Place bulk order</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <script>
        (function () {
            const qs = [...document.querySelectorAll('.sb-qty')], coins = document.getElementById('sb-coins');
            const have = {{ (int)$seller->coin_balance }}, value = {{ $coinValue }};
            function calc() {
                let sub = 0, cap = 0, ok = true;
                qs.forEach(i => { const q = +i.value || 0; if (q > 0 && q < +i.dataset.min) ok = false; const line = q * +i.dataset.price; sub += line; cap += line * (+i.dataset.coinpct) / 100; });
                const maxCoins = Math.max(0, Math.min(have, Math.floor(cap / value), Math.floor(sub / value)));
                coins.max = maxCoins; if (+coins.value > maxCoins) coins.value = maxCoins;
                document.getElementById('sb-coincap').textContent = 'Up to ' + maxCoins + ' coins on this order · 1 coin = ₹' + value;
                document.getElementById('sb-sub').textContent = '₹' + sub.toFixed(2);
                document.getElementById('sb-pay').textContent = '₹' + Math.max(0, sub - (+coins.value || 0) * value).toFixed(2);
                document.getElementById('sb-place').disabled = !(sub > 0 && ok);
            }
            [...qs, coins].forEach(i => i.addEventListener('input', calc)); calc();
        })();
    </script>
@endsection
