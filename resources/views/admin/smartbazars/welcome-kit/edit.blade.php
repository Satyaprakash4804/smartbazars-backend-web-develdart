@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_kits'])
@section('title', 'Edit Welcome Kit')
@section('header_data')
    @php $page_title = $kit->name; $page_pretitle = 'Welcome Kit'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Kit details</h3></div>
                <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.update', $kit) }}" enctype="multipart/form-data" class="card-body">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $kit->name) }}" required></div>
                    <div class="mb-2"><label class="form-label">Price (₹)</label><input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $kit->price) }}" required></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4">{{ old('description', $kit->description) }}</textarea></div>
                    @if($kit->image_url)<img src="{{ $kit->image_url }}" class="rounded mb-2" style="max-height:120px">@endif
                    <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                    <button class="btn btn-primary w-100">Save</button>
                </form>
                <div class="card-footer d-flex gap-2">
                    <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.activate', $kit) }}">@csrf
                        <button class="btn {{ $kit->is_active ? 'btn-outline-warning' : 'btn-success' }}">{{ $kit->is_active ? 'Deactivate' : 'Make live' }}</button></form>
                    <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.destroy', $kit) }}" onsubmit="return confirm('Delete this kit?')">@csrf
                        <button class="btn btn-outline-danger">Delete</button></form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">What's inside the kit</h3></div>
                <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.items.add', $kit) }}" class="card-body border-bottom row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5"><label class="form-label">Product from catalogue</label>
                        <select name="product_id" class="form-select">
                            <option value="">— custom item —</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Display name</label><input name="item_name" class="form-control" placeholder="Auto from product"></div>
                    <div class="col-md-1"><label class="form-label">Qty</label><input type="number" name="quantity" value="1" min="1" class="form-control"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Item</th><th>Linked product</th><th>Qty</th><th></th></tr></thead>
                        <tbody>
                        @forelse($kit->items as $i)
                            <tr><td class="fw-bold">{{ $i->item_name }}</td><td class="small text-secondary">{{ $i->product->title ?? '—' }}</td><td>{{ $i->quantity }}</td>
                                <td><form method="POST" action="{{ route('admin.smartbazars.welcome-kits.items.remove', [$kit, $i]) }}">@csrf<button class="btn btn-sm btn-ghost-danger">Remove</button></form></td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No items yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
