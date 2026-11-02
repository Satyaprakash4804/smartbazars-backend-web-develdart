@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => $member->isAgent() ? 'sb_agents' : 'sb_workers'])
@section('title', 'Delete ' . ($member->isAgent() ? 'BSO' : 'BJO'))
@section('header_data')
    @php $page_title = 'Delete ' . ($member->isAgent() ? 'BSO' : 'BJO'); $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @php
        $isAgent = $member->isAgent();
        $back = route('admin.smartbazars.network.' . ($isAgent ? 'agents' : 'workers'));
        $needsConfirm = $preview['wallet_balance'] > 0 || $preview['pending_withdrawals'] > 0;
        $money = fn($v) => '₹' . number_format((float)$v, 2);
    @endphp
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="avatar avatar-lg bg-red-lt">{{ strtoupper(mb_substr($member->user->name ?? '?', 0, 1)) }}</span>
                        <div class="flex-fill" style="min-width:200px">
                            <h3 class="mb-1">{{ $member->user->name ?? ('#' . $member->id) }}</h3>
                            <div class="text-secondary small text-break">{{ $member->user->email ?? '' }} · {{ $member->user->mobile ?? '' }}</div>
                            <div class="mt-1"><span class="badge bg-blue-lt">{{ ucfirst($member->type) }}</span> <code>{{ $member->referral_code }}</code> @include('smartbazars._status', ['s' => $member->status])</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mt-0">
                <div class="col-12 col-sm-4">
                    <div class="card card-sm"><div class="card-body">
                        <div class="subheader">Wallet balance</div>
                        <div class="h2 mb-0 {{ $preview['wallet_balance'] > 0 ? 'text-warning' : '' }}">{{ $money($preview['wallet_balance']) }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-sm-4">
                    <div class="card card-sm"><div class="card-body">
                        <div class="subheader">Total earned</div>
                        <div class="h2 mb-0">{{ $money($preview['total_earned']) }}</div>
                    </div></div>
                </div>
                <div class="col-6 col-sm-4">
                    <div class="card card-sm"><div class="card-body">
                        <div class="subheader">Pending payouts</div>
                        <div class="h2 mb-0 {{ $preview['pending_withdrawals'] ? 'text-danger' : '' }}">{{ $preview['pending_withdrawals'] }} <span class="small text-secondary">({{ $money($preview['pending_amount']) }})</span></div>
                    </div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">What happens to their downline</h3></div>
                <div class="card-body">
                    @if($isAgent)
                        <div class="row g-3 text-center mb-3">
                            <div class="col-4"><div class="h3 mb-0">{{ $preview['sub_agents'] }}</div><div class="text-secondary small">Direct sub-agents</div></div>
                            <div class="col-4"><div class="h3 mb-0">{{ $preview['descendants'] }}</div><div class="text-secondary small">BSOs in whole tree</div></div>
                            <div class="col-4"><div class="h3 mb-0">{{ $preview['vendors'] }}</div><div class="text-secondary small">Linked vendors</div></div>
                        </div>
                        @if($preview['sub_agents'] || $preview['vendors'])
                            <div class="alert alert-info mb-2">
                                All of them move up to <b>{{ $preview['upline'] }}</b>@if($member->parent_id) (this agent's upline)@else — this agent has no upline, so they are connected to SmartBazars directly @endif.
                            </div>
                            @if($preview['sample_agents'])<div class="small text-secondary">Sub-agents: {{ implode(', ', $preview['sample_agents']) }}{{ $preview['sub_agents'] > count($preview['sample_agents']) ? ' …' : '' }}</div>@endif
                            @if($preview['sample_vendors'])<div class="small text-secondary">Vendors: {{ implode(', ', $preview['sample_vendors']) }}{{ $preview['vendors'] > count($preview['sample_vendors']) ? ' …' : '' }}</div>@endif
                        @else
                            <div class="text-secondary">This agent has no downline.</div>
                        @endif
                    @else
                        <div class="row g-3 text-center mb-3">
                            <div class="col-6"><div class="h3 mb-0">{{ $preview['customers'] }}</div><div class="text-secondary small">Customers joined through this worker</div></div>
                            <div class="col-6"><div class="h3 mb-0 text-break">{{ $preview['upline'] }}</div><div class="text-secondary small">Vendor</div></div>
                        </div>
                        <div class="alert alert-info mb-0">Their customers stay with <b>{{ $preview['upline'] }}</b>.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-danger">
                <div class="card-header"><h3 class="card-title text-danger">Confirm delete</h3></div>
                <form method="POST" action="{{ route('admin.smartbazars.network.member.destroy', $member) }}" class="card-body">
                    @csrf @method('DELETE')
                    <ul class="small ps-3">
                        <li>Login is blocked and the email / mobile become free to reuse.</li>
                        <li>Wallet balance, {{ $preview['commissions'] }} commission record(s) and {{ $preview['withdrawals'] }} withdrawal request(s) are <b>kept</b> under <a href="{{ route('admin.smartbazars.network.deleted', ['type' => $member->type]) }}">Deleted Records</a>.</li>
                        @if($preview['pending_withdrawals'])<li class="text-danger">Pending payouts stay in Partner Payouts so you can still pay or reject them.</li>@endif
                    </ul>
                    @if($needsConfirm)
                        <label class="form-check mb-3">
                            <input type="checkbox" name="confirm_wallet" value="1" class="form-check-input" required>
                            <span class="form-check-label">I have noted the wallet balance of <b>{{ $money($preview['wallet_balance']) }}</b>@if($preview['pending_withdrawals']) and {{ $preview['pending_withdrawals'] }} pending payout(s)@endif.</span>
                        </label>
                    @endif
                    <div class="d-grid gap-2">
                        <button class="btn btn-danger">Delete {{ $member->type }}</button>
                        <a href="{{ $back }}" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
