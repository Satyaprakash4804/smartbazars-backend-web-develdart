@extends('layouts.admin.app', ['page' => 'sb_business', 'sub_page' => 'sb_wholesale'])
@section('title', 'Bulk Products for Vendors')
@section('header_data')
    @php $page_title = 'Bulk Products for Vendors'; $page_pretitle = 'SmartBazars wholesale'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ $edit ? 'Edit' : 'Add' }} bulk product</h3>@if($edit)<div class="card-actions"><a href="{{ route('admin.smartbazars.wholesale.index') }}">New</a></div>@endif</div>
                <form method="POST" enctype="multipart/form-data" action="{{ $edit ? route('admin.smartbazars.wholesale.update', $edit) : route('admin.smartbazars.wholesale.store') }}" class="card-body">
                    @csrf
                    <div class="mb-2"><label class="form-label">Link to catalogue product (optional)</label>
                        <select name="product_id" class="form-select"><option value="">— none —</option>
                            @foreach($catalog as $p)<option value="{{ $p->id }}" @selected(old('product_id', $edit?->product_id)==$p->id)>{{ $p->title }}</option>@endforeach
                        </select></div>
                    <div class="mb-2"><label class="form-label">Title</label><input name="title" value="{{ old('title', $edit?->title) }}" class="form-control" placeholder="Auto from product"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Pack / unit</label><input name="unit" value="{{ old('unit', $edit?->unit) }}" class="form-control" placeholder="Carton of 24"></div>
                        <div class="col-6"><label class="form-label">MRP (₹)</label><input type="number" step="0.01" name="mrp" value="{{ old('mrp', $edit?->mrp) }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label required">Vendor price (₹)</label><input type="number" step="0.01" name="vendor_price" value="{{ old('vendor_price', $edit?->vendor_price) }}" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Stock</label>
                            @if($edit?->is_smartbazars)<input class="form-control" value="{{ $edit->available_stock }} (own-store stock)" disabled>
                            @else<input type="number" name="stock" value="{{ old('stock', $edit?->stock) }}" class="form-control" placeholder="unlimited">@endif</div>
                        <div class="col-6"><label class="form-label required">Min qty / order</label><input type="number" name="min_qty" value="{{ old('min_qty', $edit?->min_qty ?? 1) }}" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Max qty / order</label><input type="number" name="max_qty" value="{{ old('max_qty', $edit?->max_qty) }}" class="form-control" placeholder="no limit"></div>
                        <div class="col-12"><label class="form-label">Coins can pay up to (% of line)</label><div class="input-group"><input type="number" name="max_coin_percent" value="{{ old('max_coin_percent', $edit?->max_coin_percent ?? 100) }}" class="form-control" min="0" max="100"><span class="input-group-text">%</span></div></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control">{{ old('description', $edit?->description) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                    <label class="form-check form-switch mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $edit?->is_active ?? true))><span class="form-check-label">Visible to vendors</span></label>
                    <button class="btn btn-primary w-100">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <form method="POST" action="{{ route('admin.smartbazars.wholesale.sync-own') }}">@csrf
                    <button class="btn btn-success"><i class="ti ti-building-store me-1"></i>Add all SmartBazars own-store products</button></form>
                <span class="small text-secondary">New own-store products are added automatically; stock is shared with the own store.</span>
                <form class="ms-auto d-flex gap-2">
                    <select name="src" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All</option><option value="own" @selected(request('src')==='own')>SmartBazars own products</option><option value="manual" @selected(request('src')==='manual')>Added manually</option></select>
                    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search">
                </form>
            </div></div>
            <div class="card">
                <div class="table-responsive"><table class="table card-table table-vcenter">
                    <thead><tr><th>Product</th><th>Vendor price</th><th>Qty limits</th><th>Stock</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($products as $w)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2">@if($w->image_url)<span class="avatar" style="background-image:url('{{ $w->image_url }}')"></span>@endif<div><b>{{ $w->title }}</b> @if($w->is_smartbazars)<span class="badge bg-green-lt">SmartBazars</span>@endif<div class="small text-secondary">{{ $w->unit }} @if($w->mrp > 0)· MRP ₹{{ number_format($w->mrp, 2) }}@endif</div></div></div></td>
                            <td class="fw-bold">₹{{ number_format($w->vendor_price, 2) }}</td>
                            <td class="small">min {{ $w->min_qty }}{{ $w->max_qty ? ' · max ' . $w->max_qty : '' }}</td>
                            <td>{{ $w->available_stock ?? '∞' }}@if($w->is_smartbazars)<div class="small text-secondary">own store</div>@endif</td>
                            <td>{!! $w->is_active ? '<span class="badge bg-success-lt">Visible</span>' : '<span class="badge bg-secondary-lt">Hidden</span>' !!}</td>
                            <td class="text-nowrap"><a class="btn btn-sm" href="?edit={{ $w->id }}">Edit</a>
                                <form method="POST" action="{{ route('admin.smartbazars.wholesale.toggle', $w) }}" class="d-inline">@csrf<button class="btn btn-sm btn-ghost-secondary">{{ $w->is_active ? 'Hide' : 'Show' }}</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Add products that vendors can buy from SmartBazars in bulk.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="card-footer">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
@endsection
