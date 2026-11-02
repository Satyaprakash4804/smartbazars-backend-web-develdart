@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_wallet'])
@section('title', 'BJO Wallet Recharges')
@section('header_data')
    @php $page_title = 'BJO Wallet Recharges'; $page_pretitle = 'SmartBazars Admin'; @endphp
@endsection
@section('admin-content')
    @php $money = fn($v) => '₹' . number_format((float)$v, 2); $badge = ['pending' => 'bg-yellow-lt', 'approved' => 'bg-green-lt', 'rejected' => 'bg-red-lt']; @endphp
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <div class="card mb-3"><div class="card-header"><h4 class="card-title">Payment instructions shown to BJOs</h4></div>
        <form method="post" action="{{ route('admin.smartbazars.wallet-recharges.settings') }}" class="card-body row g-2">@csrf
            <div class="col-md-2"><label class="form-label">Minimum ₹</label><input type="number" name="wallet_min_recharge" class="form-control" value="{{ $min }}" required></div>
            <div class="col-md-8"><label class="form-label">Instructions (UPI ID / bank details)</label><input name="wallet_recharge_instructions" class="form-control" value="{{ $instructions }}" maxlength="1000" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save</button></div>
        </form></div>
    <div class="card">
        <div class="card-header flex-wrap gap-2"><h4 class="card-title me-auto">Requests @if($pending)<span class="badge bg-yellow ms-1">{{ $pending }} pending</span>@endif</h4>
            <form method="get" class="d-flex gap-2"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="BJO or reference">
                <select name="status" class="form-select"><option value="">All</option>@foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select><button class="btn btn-primary">Go</button></form></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Date</th><th>BJO</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>@forelse($rows as $r)<tr>
                <td class="text-nowrap small">{{ $r->created_at->format('d M Y, h:i A') }}</td>
                <td>{{ $r->member?->user?->name }}<div class="text-secondary small">{{ $r->member?->user?->mobile }} · wallet {{ $money($r->member?->wallet_balance) }}</div></td>
                <td><span class="font-monospace">{{ $r->reference }}</span><div class="small text-secondary">{{ strtoupper($r->method) }}@if($r->proof_file) · <a href="{{ route('admin.smartbazars.wallet-recharges.proof', $r) }}" target="_blank">proof</a>@endif</div></td>
                <td class="text-end fw-bold">{{ $money($r->amount) }}</td>
                <td><span class="badge {{ $badge[$r->status] }}">{{ ucfirst($r->status) }}</span>@if($r->admin_note)<div class="small text-secondary">{{ $r->admin_note }}</div>@endif</td>
                <td class="text-end">@if($r->status === 'pending' && $r->gateway)<span class="small text-secondary">Awaiting online payment</span>@elseif($r->status === 'pending')
                    <form method="post" action="{{ route('admin.smartbazars.wallet-recharges.approve', $r) }}" class="d-inline" onsubmit="return confirm('Credit {{ $money($r->amount) }} to this BJO wallet?')">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                    <form method="post" action="{{ route('admin.smartbazars.wallet-recharges.reject', $r) }}" class="d-inline" onsubmit="this.note.value = prompt('Reason for rejecting?') || ''; return this.note.value !== ''">@csrf<input type="hidden" name="note"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                @endif</td></tr>
            @empty<tr><td colspan="6" class="text-center text-secondary py-4">No recharge requests.</td></tr>@endforelse</tbody></table></div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
