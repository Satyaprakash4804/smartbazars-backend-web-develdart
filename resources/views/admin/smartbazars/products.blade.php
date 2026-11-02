@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_products'])
@section('title', 'All Products & Cashback')
@section('header_data')
    @php $page_title = 'All Products & Cashback Coins'; $page_pretitle = 'SmartBazars own + vendor products'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Bulk set cashback</h3></div>
        <form method="POST" action="{{ route('admin.smartbazars.products.coins.bulk') }}" class="card-body row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label">Apply to</label>
                <select name="scope" class="form-select" onchange="document.getElementById('bulk-seller').classList.toggle('d-none', this.value!=='seller');document.getElementById('bulk-cat').classList.toggle('d-none', this.value!=='category');">
                    <option value="all">All products</option><option value="own">SmartBazars own products</option><option value="vendor">All vendor products</option>
                    <option value="seller">One vendor</option><option value="category">One category</option>
                </select></div>
            <div class="col-md-3 d-none" id="bulk-seller"><label class="form-label">Vendor</label><select name="seller_id" class="form-select"><option value="">—</option>@foreach($sellers as $sl)<option value="{{ $sl->id }}">{{ $sl->user->name ?? '#'.$sl->id }}</option>@endforeach</select></div>
            <div class="col-md-3 d-none" id="bulk-cat"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Coins per unit</label><input type="number" name="coins" min="0" class="form-control" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" onclick="return confirm('Overwrite cashback for all matching products?')">Apply</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="row g-2">
                <div class="col-md-3"><input name="q" class="form-control" placeholder="Search product" value="{{ request('q') }}"></div>
                <div class="col-md-2"><select name="owner" class="form-select"><option value="">Own + vendors</option><option value="own" @selected(request('owner')==='own')>SmartBazars own</option><option value="vendor" @selected(request('owner')==='vendor')>Vendors only</option></select></div>
                <div class="col-md-2"><select name="seller_id" class="form-select"><option value="">Any vendor</option>@foreach($sellers as $sl)<option value="{{ $sl->id }}" @selected(request('seller_id')==$sl->id)>{{ $sl->user->name ?? '#'.$sl->id }}{{ $sl->is_house_store ? ' (own)' : '' }}</option>@endforeach</select></div>
                <div class="col-md-2"><select name="category_id" class="form-select"><option value="">Any category</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->title }}</option>@endforeach</select></div>
                <div class="col-md-2"><select name="has_coins" class="form-select"><option value="">Any cashback</option><option value="1" @selected(request('has_coins')==='1')>With coins</option><option value="0" @selected(request('has_coins')==='0')>No coins</option></select></div>
                <div class="col-md-1"><button class="btn w-100">Go</button></div>
            </form>
        </div>
        <form method="POST" action="{{ route('admin.smartbazars.products.coins') }}">
            @csrf
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th>Product</th><th>Owner</th><th>Status</th><th style="width:160px">Cashback coins / unit</th></tr></thead>
                    <tbody>
                    @forelse($products as $p)
                        @php $own = $houseSellerIds->contains($p->seller_id); @endphp
                        <tr>
                            <td><div class="d-flex align-items-center gap-2">
                                @if($p->main_image)<span class="avatar avatar-sm" style="background-image:url('{{ $p->main_image }}')"></span>@endif
                                <a href="{{ route('admin.products.show', $p->id) }}">{{ $p->title }}</a></div></td>
                            <td>
                                {!! $own ? '<span class="badge bg-green-lt">SmartBazars</span>' : '<span class="badge bg-blue-lt">Vendor</span>' !!}
                                <span class="small text-secondary">{{ $p->seller?->user?->name }}</span>
                            </td>
                            <td class="small">{{ $p->status }}</td>
                            <td><div class="input-group input-group-sm"><input type="number" min="0" name="coins[{{ $p->id }}]" value="{{ $p->cashback_coins }}" class="form-control"><span class="input-group-text"><img src="{{ asset('assets/images/smartbazars/coin.png') }}" alt="coins" width="18" height="18" style="vertical-align:-4px"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">No products.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                {{ $products->links() }}
                <button class="btn btn-primary">Save coins on this page</button>
            </div>
        </form>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h3 class="card-title">SmartBazars own stores</h3></div>
        <div class="card-body">
            <p class="small text-secondary">Mark the seller account(s) that SmartBazars runs itself. Their products appear as <b>SmartBazars own</b>. Add/edit own products by opening that seller with “Login as seller” (impersonation).</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($sellers as $sl)
                    <form method="POST" action="{{ route('admin.smartbazars.stores.house-toggle', $sl) }}">@csrf
                        <button class="btn btn-sm {{ $sl->is_house_store ? 'btn-success' : 'btn-outline-secondary' }}">{{ $sl->is_house_store ? '✓ ' : '' }}{{ $sl->user->name ?? '#'.$sl->id }}</button></form>
                @endforeach
            </div>
        </div>
    </div>
@endsection
