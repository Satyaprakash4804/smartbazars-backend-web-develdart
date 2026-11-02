@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_home'])
@section('title', 'Offline Sales (POS)')
@section('header_data')
    @php $page_title = 'Offline Sales & User Cards'; $page_pretitle = $who === 'admin' ? 'SmartBazars Admin' : ($who === 'worker' ? 'BJO' : 'Vendor'); @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    @php $money = fn($v) => '₹' . number_format((float)$v, 2); @endphp
    <div class="row row-deck row-cards mb-3">
        @foreach([
            ['Today\'s sales', $stats['sales_today'], $money($stats['amount_today']), 'ti-shopping-cart'],
            ['This month', $money($stats['amount_month']), 'offline sales value', 'ti-calendar-stats'],
            ['User Cards', $stats['cards_total'], '+' . $stats['cards_today'] . ' today', 'ti-id-badge-2'],
            ['Awaiting pickup', $stats['pending_pickups'], 'BJO orders at the vendor', 'ti-package'],
            ['Coins given', number_format($stats['coins_issued']), 'cashback on offline sales', 'ti-coins'],
            ['Coins used', number_format($stats['coins_redeemed']), 'redeemed by card holders', 'ti-coin'],
        ] as [$t, $v, $s, $i])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-secondary small"><i class="ti {{ $i }}"></i>{{ $t }}</div>
                    <div class="h2 mt-1 mb-0">{{ $v }}</div><div class="text-secondary small">{{ $s }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    @if($who === 'worker')
        <div class="alert alert-info">Your order wallet: <strong>{{ $money($wallet) }}</strong> (<a href="{{ route('partner.wallet') }}">recharge or move earnings</a>). Orders you place for a card holder are paid from this wallet and/or from the coins on the customer's card. The customer collects the goods from your vendor — no delivery charge.</div>
    @elseif($who === 'seller')
        <div class="alert alert-info">Record every offline sale with the customer's User Card number. Offline purchases carry <strong>no delivery charge</strong>; the customer earns cashback coins on the card.</div>
    @else
        <div class="alert alert-info">Admin view — every card, counter sale and BJO order from all vendors, with filters in each tab.</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Latest offline orders</h3><div class="card-actions"><a href="{{ route($rp . 'orders') }}">View all</a></div></div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Order</th><th>Customer</th>@if($who === 'admin')<th>Vendor</th>@endif<th>Channel</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($recent as $o)
                    <tr>
                        <td><a href="{{ route($rp . 'orders.show', $o) }}" class="font-monospace">{{ $o->order_no }}</a><div class="text-secondary small">{{ $o->created_at->diffForHumans() }}</div></td>
                        <td>{{ $o->customer?->name }}<div class="text-secondary small font-monospace">{{ $o->card?->formatted_number }}</div></td>
                        @if($who === 'admin')<td>{{ $o->seller?->displayName() }}</td>@endif
                        <td>{{ $o->channel === 'worker' ? 'BJO order' : 'Counter sale' }}</td>
                        <td class="text-end">{{ $money($o->total) }}</td>
                        <td><span class="badge {{ ['pending_pickup' => 'bg-yellow-lt', 'completed' => 'bg-green-lt', 'cancelled' => 'bg-red-lt'][$o->status] }}">{{ $o->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No offline orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
