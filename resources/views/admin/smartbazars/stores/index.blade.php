@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_store_review'])
@section('title', 'Store Review')
@section('header_data')
    @php $page_title = 'Store Review'; $page_pretitle = 'SmartBazars'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach(['pending' => 'Pending review', 'rejected' => 'Rejected', 'approved' => 'Approved', 'all' => 'All'] as $k => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $state === $k ? 'active' : '' }}" href="{{ route('admin.smartbazars.store-review', ['state' => $k]) }}">
                            {{ $label }} <span class="badge bg-secondary-lt ms-1">{{ $counts[$k] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr><th>#</th><th>Store</th><th>Vendor</th><th>Location</th><th>Status</th><th>Created</th><th class="w-1"></th></tr>
                </thead>
                <tbody>
                @forelse($stores as $store)
                    <tr>
                        <td>{{ $store->id }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($store->store_logo)
                                    <span class="avatar avatar-sm" style="background-image: url('{{ $store->store_logo }}')"></span>
                                @endif
                                <div>
                                    <div class="fw-bold">{{ $store->name }}</div>
                                    <div class="small text-secondary">{{ $store->contact_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            {{ $store->seller?->user?->name ?? '—' }}
                            <div class="text-secondary">{{ $store->seller?->user?->email }}</div>
                        </td>
                        <td class="small">{{ collect([$store->city, $store->state])->filter()->implode(', ') ?: '—' }}</td>
                        <td>@include('smartbazars._store_state', ['store' => $store, 'showReason' => true])</td>
                        <td class="small text-secondary">{{ $store->created_at?->format('d M Y') }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.smartbazars.store-review.show', $store) }}" class="btn btn-sm btn-primary">
                                {{ $store->reviewState() === 'pending' ? 'Review' : 'View' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No stores in this list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stores->links() }}</div>
    </div>
@endsection
