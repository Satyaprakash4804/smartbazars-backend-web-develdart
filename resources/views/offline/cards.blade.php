@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_cards'])
@section('title', 'User Cards')
@section('header_data')
    @php $page_title = 'Offline User Cards'; $page_pretitle = 'SmartBazars'; @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    <div class="card">
        <div class="card-header flex-wrap gap-2">
            <form method="get" class="d-flex flex-wrap gap-2 flex-grow-1">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px" placeholder="Name, mobile, email or card no.">
                <input type="text" name="district" value="{{ request('district') }}" class="form-control" style="max-width:180px" placeholder="District">
                <button class="btn btn-primary">Search</button>
            </form>
            @if($canCreate)<a href="{{ route($rp . 'cards.create') }}" class="btn btn-success"><i class="ti ti-plus me-1"></i>New User Card</a>@endif
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Card</th><th>Customer</th><th>Location</th>@if($who === 'admin')<th>Vendor</th>@endif<th>Created</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $p)
                    <tr>
                        <td class="font-monospace fw-bold">{{ $p->card?->formatted_number }}</td>
                        <td>{{ $p->user?->name }}<div class="text-secondary small">{{ $p->user?->mobile }} · {{ $p->user?->email }}</div></td>
                        <td class="small">{{ $p->ward_panchayat }}, {{ $p->block }}<br>{{ $p->district }}, {{ $p->state }}</td>
                        @if($who === 'admin')<td class="small">{{ $p->seller?->displayName() }}@if($p->member)<div class="text-secondary">BJO: {{ $p->member->user?->name }}</div>@endif</td>@endif
                        <td class="text-nowrap small">{{ $p->created_at->format('d M Y') }}<br><span class="text-secondary">{{ $p->card_emailed_at ? 'emailed' : 'not emailed' }}</span></td>
                        <td class="text-end"><a href="{{ route($rp . 'cards.show', $p) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No cards found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
