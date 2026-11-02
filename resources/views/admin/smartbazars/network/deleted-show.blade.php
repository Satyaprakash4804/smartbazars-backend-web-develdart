@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_deleted'])
@section('title', 'Deleted ' . ucfirst($member->type))
@section('header_data')
    @php $page_title = 'Deleted ' . ucfirst($member->type); $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @php
        $sn = $member->deleted_snapshot ?? [];
        $money = fn($v) => '₹' . number_format((float)$v, 2);
    @endphp
    <div class="mb-3"><a href="{{ route('admin.smartbazars.network.deleted', ['type' => $member->type]) }}" class="btn btn-sm">← Back to Deleted Records</a></div>
    <div class="row row-cards">
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-1">{{ $sn['name'] ?? $member->user->name ?? '#' . $member->id }}</h3>
                    <div class="text-secondary small text-break">{{ $sn['email'] ?? '' }}<br>{{ $sn['mobile'] ?? '' }}</div>
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-5">Type</dt><dd class="col-7">{{ ucfirst($member->type) }}</dd>
                        <dt class="col-5">Code</dt><dd class="col-7"><code>{{ $member->referral_code }}</code></dd>
                        <dt class="col-5">{{ $member->isAgent() ? 'Upline' : 'Vendor' }}</dt><dd class="col-7">{{ $sn['upline'] ?? '—' }}</dd>
                        <dt class="col-5">Downline moved</dt><dd class="col-7">{{ $sn['downline_moved'] ?? 0 }}</dd>
                        <dt class="col-5">Deleted</dt><dd class="col-7">{{ $member->deleted_at?->format('d M Y H:i') }}</dd>
                        <dt class="col-5">Deleted by</dt><dd class="col-7">{{ $admin->name ?? '—' }}</dd>
                        @if(!empty($sn['note']))<dt class="col-5">Note</dt><dd class="col-7">{{ $sn['note'] }}</dd>@endif
                    </dl>
                </div>
            </div>
            <div class="row row-cards mt-0">
                <div class="col-6"><div class="card card-sm"><div class="card-body"><div class="subheader">Wallet balance</div><div class="h3 mb-0 {{ (float)$member->wallet_balance > 0 ? 'text-warning' : '' }}">{{ $money($member->wallet_balance) }}</div></div></div></div>
                <div class="col-6"><div class="card card-sm"><div class="card-body"><div class="subheader">Total earned</div><div class="h3 mb-0">{{ $money($member->total_earned) }}</div></div></div></div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Withdrawal requests</h3></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter text-nowrap">
                        <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Status</th><th>Reference</th></tr></thead>
                        <tbody>
                        @forelse($withdrawals as $w)
                            <tr><td class="small">{{ $w->created_at?->format('d M Y H:i') }}</td><td class="text-end">{{ $money($w->amount) }}</td><td>@include('smartbazars._status', ['s' => $w->status])</td><td class="small">{{ $w->transaction_reference ?: '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-3">No withdrawal requests.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($withdrawals->hasPages())<div class="card-footer">{{ $withdrawals->links() }}</div>@endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Commission history</h3></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter text-nowrap">
                        <thead><tr><th>Date</th><th>Source</th><th>Role</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($commissions as $c)
                            <tr>
                                <td class="small">{{ $c->created_at?->format('d M Y H:i') }}</td>
                                <td class="small">{{ ucfirst(str_replace('_', ' ', $c->source)) }}<div class="text-secondary text-wrap">{{ $c->description }}</div></td>
                                <td class="small">{{ ucfirst(str_replace('_', ' ', $c->role)) }}</td>
                                <td class="text-end">{{ $money($c->amount) }}</td>
                                <td>@include('smartbazars._status', ['s' => $c->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">No commissions.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($commissions->hasPages())<div class="card-footer">{{ $commissions->links() }}</div>@endif
            </div>
        </div>
    </div>
@endsection
