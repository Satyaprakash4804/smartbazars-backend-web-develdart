@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_coin_ledger'])
@section('title', 'Coin Ledger & Transfers')
@section('header_data')
    @php $page_title = 'Coin Ledger & Transfers'; $page_pretitle = '1 coin = ₹' . rtrim(rtrim(number_format($summary['coin_value'], 2), '0'), '.'); @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    @php $coin = asset('assets/images/smartbazars/coin.png'); @endphp
    <div class="row row-cards mb-3">
        @foreach(['admin' => 'SmartBazars (admin) coins', 'vendors' => 'Coins held by vendors', 'customers' => 'Coins held by customers', 'pending' => 'Pending vendor transfers'] as $k => $l)
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body d-flex align-items-center gap-3">
                <img src="{{ $coin }}" width="40" height="40" alt="">
                <div><div class="text-secondary small">{{ $l }}</div><div class="h2 mb-0">{{ number_format($summary[$k]) }}</div>
                    <div class="small text-secondary">≈ ₹{{ number_format($summary[$k] * $summary['coin_value'], 2) }}</div></div>
            </div></div></div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Vendor transfers waiting for approval</h3></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>#</th><th>Vendor</th><th>Coins</th><th>Cash value</th><th>Note</th><th>Requested</th><th></th></tr></thead>
            <tbody>
            @forelse($transfers as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->seller?->displayName() }}<div class="small text-secondary">{{ $t->seller?->user?->email }}</div></td>
                    <td class="fw-bold">{{ number_format($t->coins) }}</td>
                    <td>₹{{ number_format($t->rupee_value, 2) }}</td>
                    <td class="small">{{ $t->vendor_note }}</td>
                    <td class="small text-secondary">{{ $t->created_at->format('d M Y H:i') }}</td>
                    <td class="text-nowrap">
                        <form method="POST" action="{{ route('admin.smartbazars.coin-transfers.process', $t) }}" class="d-flex gap-1">@csrf
                            <input name="admin_note" class="form-control form-control-sm" placeholder="Note (optional)">
                            <button name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Approve: credit ₹{{ $t->rupee_value }} to this vendor\'s settlement?')">Approve</button>
                            <button name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-3">No pending transfers.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="d-flex flex-wrap gap-2">
                <select name="who" class="form-select w-auto"><option value="">Admin + vendors</option><option value="admin" @selected(request('who')==='admin')>SmartBazars admin</option><option value="vendors" @selected(request('who')==='vendors')>Vendors</option></select>
                <select name="seller_id" class="form-select w-auto"><option value="">Any vendor</option>@foreach($sellers as $s)<option value="{{ $s->id }}" @selected(request('seller_id')==$s->id)>{{ $s->displayName() }}</option>@endforeach</select>
                <button class="btn">Filter</button>
                <a href="{{ route('admin.smartbazars.coins') }}" class="btn btn-ghost-secondary ms-auto">Customer coin history →</a>
            </form>
        </div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Date</th><th>Holder</th><th>Type</th><th>Coins</th><th>Balance</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="small text-secondary">{{ $r->created_at->format('d M Y H:i') }}</td>
                    <td>{!! $r->seller_id ? e($r->seller?->displayName()) : '<span class="badge bg-green-lt">SmartBazars</span>' !!}</td>
                    <td><span class="badge bg-secondary-lt">{{ ucwords(str_replace('_', ' ', $r->type)) }}</span></td>
                    <td class="fw-bold {{ $r->coins >= 0 ? 'text-success' : 'text-danger' }}">{{ $r->coins > 0 ? '+' : '' }}{{ $r->coins }}</td>
                    <td>{{ $r->balance_after }}</td>
                    <td class="small">{{ $r->description }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No coin movements yet. Coins appear here when customers pay with coins and the item is delivered.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
