@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_kits'])
@section('title', 'Welcome Kit')
@section('header_data')
    @php $page_title = 'Welcome Kit'; $page_pretitle = 'SmartBazars Program'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards mb-3">
        @foreach(['sold' => 'Kits sold', 'revenue' => 'Kit revenue', 'pending_dispatch' => 'To dispatch', 'cards' => 'Active user cards'] as $k => $l)
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body">
                <div class="text-secondary">{{ $l }}</div>
                <div class="h2 mb-0">{{ $k === 'revenue' ? '₹'.number_format($stats[$k], 0) : $stats[$k] }}</div>
            </div></div></div>
        @endforeach
    </div>
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">New kit</h3></div>
                <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.store') }}" enctype="multipart/form-data" class="card-body">
                    @csrf
                    <div class="mb-2"><label class="form-label required">Name</label><input name="name" class="form-control" value="{{ old('name', 'SmartBazars Welcome Kit') }}" required></div>
                    <div class="mb-2"><label class="form-label required">Price (₹)</label><input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', 250) }}" required></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                    <button class="btn btn-primary w-100">Create & add products</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Kits</h3><div class="card-actions small text-secondary">Only one kit can be live at a time.</div></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Kit</th><th>Price</th><th>Items</th><th>Sold</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse($kits as $k)
                            <tr>
                                <td><div class="d-flex align-items-center gap-2">@if($k->image_url)<span class="avatar" style="background-image:url('{{ $k->image_url }}')"></span>@endif<b>{{ $k->name }}</b></div></td>
                                <td>₹{{ number_format($k->price, 2) }}</td>
                                <td>{{ $k->items_count }}</td>
                                <td>{{ $k->purchases_count }}</td>
                                <td>{!! $k->is_active ? '<span class="badge bg-success">Live</span>' : '<span class="badge bg-secondary-lt">Inactive</span>' !!}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('admin.smartbazars.welcome-kits.edit', $k) }}" class="btn btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.activate', $k) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm {{ $k->is_active ? 'btn-outline-warning' : 'btn-success' }}">{{ $k->is_active ? 'Deactivate' : 'Make live' }}</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">Create your first welcome kit.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
