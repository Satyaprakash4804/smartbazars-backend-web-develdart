@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => $member->isAgent() ? 'sb_agents' : 'sb_workers'])
@section('title', $member->user->name ?? 'Member')
@section('header_data')
    @php $page_title = ($member->user->name ?? 'Member') . ' — ' . ucfirst($member->type); $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    <div class="row row-cards">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="mb-2">@include('smartbazars._status', ['s' => $member->status])</div>
                <div><b>Email:</b> {{ $member->user->email ?? '' }}</div>
                <div><b>Mobile:</b> {{ $member->user->mobile ?? '' }}</div>
                <div><b>Referral code:</b> <code>{{ $member->referral_code }}</code></div>
                @if($member->isAgent())
                    <div><b>Upline:</b> {{ $member->parent?->user?->name ?? 'SmartBazars (direct)' }}</div>
                    <div><b>Level:</b> {{ $member->level }}</div>
                @else
                    <div><b>Vendor:</b> {{ $member->seller?->user?->name ?? '—' }}</div>
                @endif
                <div><b>Created by:</b> {{ ucfirst($member->created_by_type) }} · {{ $member->created_at->format('d M Y') }}</div>
                <hr>
                <div class="d-flex justify-content-between"><span>Balance</span><b>₹{{ number_format($member->wallet_balance, 2) }}</b></div>
                <div class="d-flex justify-content-between"><span>Total earned</span><b>₹{{ number_format($member->total_earned, 2) }}</b></div>
            </div></div>
        </div>
        <div class="col-md-8">
            @if($member->isAgent())
                <div class="card mb-3"><div class="card-header"><h3 class="card-title">Downline</h3></div>
                    <div class="card-body">@include('smartbazars._tree', ['nodes' => $tree, 'adminLinks' => true])</div></div>
                <div class="card mb-3"><div class="card-header"><h3 class="card-title">Vendors onboarded ({{ $vendors->count() }})</h3></div>
                    <div class="list-group list-group-flush">
                        @forelse($vendors as $v)
                            <div class="list-group-item">{{ $v->user->name ?? '#'.$v->id }} <span class="text-secondary small">{{ $v->user->email ?? '' }} · {{ $v->verification_status }}</span></div>
                        @empty <div class="list-group-item text-secondary">No vendors yet.</div> @endforelse
                    </div></div>
            @else
                <div class="card mb-3"><div class="card-header"><h3 class="card-title">Customers ({{ $customers->count() }})</h3></div>
                    <div class="table-responsive"><table class="table card-table">
                        <thead><tr><th>Name</th><th>Mobile</th><th>Welcome kit</th><th>Card</th></tr></thead>
                        <tbody>@forelse($customers as $c)
                            <tr><td>{{ $c->name }}</td><td>{{ $c->mobile }}</td>
                                <td>{!! $c->welcome_kit_purchased_at ? '<span class="badge bg-success-lt">Purchased</span>' : '<span class="badge bg-secondary-lt">Not yet</span>' !!}</td>
                                <td>{{ $c->userCard->card_number ?? '—' }}</td></tr>
                        @empty <tr><td colspan="4" class="text-secondary">No customers yet.</td></tr> @endforelse</tbody>
                    </table></div></div>
            @endif
            <div class="card"><div class="card-header"><h3 class="card-title">Commissions</h3></div>
                @include('admin.smartbazars.network._commission_table', ['rows' => $commissions, 'showMember' => false])
                <div class="card-footer">{{ $commissions->links() }}</div>
            </div>
        </div>
    </div>
@endsection
