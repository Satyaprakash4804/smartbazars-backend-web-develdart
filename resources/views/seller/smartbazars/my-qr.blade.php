@extends('layouts.seller.app', ['page' => 'sb_my_qr'])
@section('title', 'My QR & Authority Letter')
@section('header_data')
    @php $page_title = 'My QR & Authority Letter'; $page_pretitle = 'Grow with SmartBazars'; @endphp
@endsection
@section('seller-content')
<style>
    .sb-poster { max-width: 380px; margin: 0 auto; border-radius: 22px; overflow: hidden; background: #fff; box-shadow: 0 16px 40px rgba(0,0,0,.12); text-align: center; }
    .sb-poster .hd { background: linear-gradient(135deg, #1E8E3E, #7CB342); color: #fff; padding: 18px 16px 14px; }
    .sb-poster .hd img { max-width: 70%; background: #fff; border-radius: 10px; padding: 4px 10px; }
    .sb-poster .qr { padding: 18px 18px 6px; } .sb-poster .qr svg { width: 100%; max-width: 260px; height: auto; }
    .sb-poster .code { font-size: 1.6rem; font-weight: 800; letter-spacing: 3px; color: #0A2E73; }
    .sb-poster .ft { background: #FFF3E0; padding: 10px; color: #E65100; font-weight: 600; }
    @media print { body * { visibility: hidden; } .sb-poster, .sb-poster * { visibility: visible; } .sb-poster { position: absolute; left: 0; top: 0; box-shadow: none; } }
</style>
<div class="row row-cards">
    <div class="col-lg-5">
        <div class="sb-poster">
            <div class="hd">
                @if($logo)<img src="{{ $logo }}" alt="SmartBazars">@endif
                <div class="mt-2 fw-bold fs-3">{{ $name }}</div>
                <div class="small opacity-75">Official SmartBazars Vendor</div>
            </div>
            <div class="qr">{!! $qrSvg !!}</div>
            <div class="code">{{ $code }}</div>
            <div class="small text-secondary mb-2 px-3">Scan to join SmartBazars · use code {{ $code }} for your Welcome Kit & member card</div>
            <div class="ft"><img src="{{ asset('assets/images/smartbazars/coin.png') }}" width="22" height="22" style="vertical-align:-5px"> Earn cashback coins on every order</div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
            <button onclick="window.print()" class="btn"><i class="ti ti-printer me-1"></i>Print poster</button>
            <a href="{{ route('seller.smartbazars.qr-download') }}" class="btn"><i class="ti ti-download me-1"></i>QR image</a>
            <a target="_blank" href="https://wa.me/?text={{ urlencode('Shop smarter with SmartBazars! Join with my code ' . $code . ': ' . $link) }}" class="btn btn-success"><i class="ti ti-brand-whatsapp me-1"></i>Share</a>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="row row-cards">
            <div class="col-sm-6"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Customers in your network</div><div class="h2 mb-0">{{ $customers }}</div></div></div></div>
            <div class="col-sm-6"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Referral earnings</div><div class="h2 mb-0">₹{{ number_format($earned, 2) }}</div></div></div></div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Authority Letter</h3></div>
            <div class="card-body">
                <p>Your official <b>Letter of Authorisation</b> ({{ $letterNo }}) confirms that <b>{{ $name }}</b> is a verified SmartBazars vendor. Display it at your store or share it with customers.</p>
                <a href="{{ route('seller.smartbazars.letter') }}" class="btn btn-primary"><i class="ti ti-file-certificate me-1"></i>Download Authority Letter (PDF)</a>
                <div class="text-secondary small mt-2">Anyone can verify you at <a href="{{ $verifyLink }}" target="_blank">{{ $verifyLink }}</a></div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">How you earn</h3></div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Customers who join with your code <b>{{ $code }}</b> (or your workers' codes) are linked to your store forever.</li>
                    <li>You earn on their <b>Welcome Kit</b> and on <b>every repeat purchase</b> they make on SmartBazars.</li>
                    <li>Rates are set by SmartBazars; your earnings appear in your vendor statement / wallet.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
