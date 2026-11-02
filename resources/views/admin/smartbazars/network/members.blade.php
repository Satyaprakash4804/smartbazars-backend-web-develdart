@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => $type === 'agent' ? 'sb_agents' : 'sb_workers'])
@section('title', $type === 'agent' ? 'BSOs' : 'BJOs')
@section('header_data')
    @php $page_title = $type === 'agent' ? 'BSOs' : 'BJOs'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        @if($type === 'agent')
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Create ID</h3></div>
                    <div class="card-body">
                        <p class="text-secondary small">Create an BSO, BJO or Vendor ID. Choose whether to collect the joining payment or create it without payment.</p>
                        <a href="{{ route('admin.smartbazars.network.create') }}" class="btn btn-primary w-100">Create BSO / BJO / Vendor</a>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Link existing vendor to agent</h3></div>
                    <form method="POST" action="{{ route('admin.smartbazars.network.assign-vendor') }}" class="card-body">
                        @csrf
                        <p class="text-secondary small">For vendors created from the normal Sellers screen. The agent earns {{ \App\Services\SmartBazars\ProgramSettings::get('agent_direct_percent') }}% on their sales.</p>
                        <select name="seller_id" class="form-select mb-2" required>
                            <option value="">Vendor without agent…</option>
                            @foreach($unassignedVendors as $v)<option value="{{ $v->id }}">{{ $v->user->name ?? ('#'.$v->id) }} — {{ $v->user->email ?? '' }}</option>@endforeach
                        </select>
                        <select name="agent_member_id" class="form-select mb-3" required>
                            <option value="">BSO…</option>
                            @foreach($agents as $a)<option value="{{ $a->id }}">{{ $a->user->name ?? '' }} ({{ $a->referral_code }})</option>@endforeach
                        </select>
                        <button class="btn btn-outline-primary w-100">Link</button>
                    </form>
                </div>
            </div>
        @endif
        <div class="{{ $type === 'agent' ? 'col-lg-8' : 'col-12' }}">
            <div class="card">
                <div class="card-body border-bottom py-2">
                    <form class="d-flex gap-2">
                        <input name="q" class="form-control" placeholder="Search name, email, mobile, code" value="{{ request('q') }}">
                        <select name="status" class="form-select w-auto"><option value="">Any status</option><option value="active" @selected(request('status')==='active')>Active</option><option value="suspended" @selected(request('status')==='suspended')>Suspended</option></select>
                        <button class="btn">Search</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Name</th><th>Code</th>
                            @if($type==='agent')<th>Upline</th><th>Level</th><th>Sub-agents</th><th>Vendors</th>@else<th>Vendor</th><th>Customers</th>@endif
                            <th>Balance</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse($members as $m)
                            <tr>
                                <td><a href="{{ route('admin.smartbazars.network.show', $m) }}" class="fw-bold">{{ $m->user->name ?? '-' }}</a><div class="small text-secondary">{{ $m->user->email ?? '' }} · {{ $m->user->mobile ?? '' }}</div></td>
                                <td><code>{{ $m->referral_code }}</code></td>
                                @if($type==='agent')
                                    <td class="small">{{ $m->parent?->user?->name ?? 'SmartBazars' }}</td>
                                    <td>{{ $m->level }}</td><td>{{ $m->children_count }}</td><td>{{ $m->vendors_count }}</td>
                                @else
                                    <td class="small">{{ $m->seller?->user?->name ?? '—' }}</td><td>{{ $m->customers_count }}</td>
                                @endif
                                <td>₹{{ number_format($m->wallet_balance, 2) }}</td>
                                <td>@include('smartbazars._status', ['s' => $m->status])</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('admin.smartbazars.network.member.edit', $m) }}" class="btn btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.smartbazars.network.member.resend', $m) }}" class="d-inline" onsubmit="return confirm('This sets a NEW password and emails the login details to {{ $m->user->email ?? '' }}. Continue?')">@csrf
                                        <button class="btn btn-sm btn-outline-primary">Resend login</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.smartbazars.network.toggle', $m) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm {{ $m->status==='active' ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $m->status==='active' ? 'Suspend' : 'Activate' }}</button>
                                    </form>
                                    <a href="{{ route('admin.smartbazars.network.member.delete-form', $m) }}" class="btn btn-sm btn-outline-danger">Delete</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-secondary py-4">Nothing here yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $members->links() }}</div>
            </div>
        </div>
    </div>
@endsection
