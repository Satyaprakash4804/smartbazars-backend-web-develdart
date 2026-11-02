{{-- SmartBazars: products vendors can buy in bulk (shown on the vendor dashboard) --}}
@php
    $sbBulk = \Illuminate\Support\Facades\Schema::hasTable('wholesale_products')
        ? \App\Models\WholesaleProduct::with(['storeVariant', 'product'])->where('is_active', true)
            ->orderByRaw('store_product_variant_id IS NULL')->orderBy('sort')->limit(8)->get()
        : collect();
@endphp
@if($sbBulk->isNotEmpty())
<div class="col-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Buy from SmartBazars in bulk</h3>
            <div class="card-actions"><a href="{{ route('seller.smartbazars.wholesale') }}" class="btn btn-primary btn-sm">Open bulk purchase</a></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($sbBulk as $p)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ route('seller.smartbazars.wholesale') }}" class="text-reset text-decoration-none d-block border rounded-3 h-100 overflow-hidden">
                            <div class="ratio ratio-4x3 bg-light" style="background:center/cover no-repeat url('{{ $p->image_url }}')"></div>
                            <div class="p-2">
                                @if($p->is_smartbazars)<span class="badge bg-green-lt">SmartBazars</span>@endif
                                <div class="fw-semibold text-truncate" title="{{ $p->title }}">{{ $p->title }}</div>
                                <div><b>₹{{ number_format($p->vendor_price, 2) }}</b> @if($p->mrp > $p->vendor_price)<s class="small text-secondary">₹{{ number_format($p->mrp, 2) }}</s>@endif</div>
                                <div class="small text-secondary">Min {{ $p->min_qty }}{{ $p->available_stock !== null ? ' · ' . $p->available_stock . ' left' : '' }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
