{{-- Full customer view for a User Card: details, uplines, dates, orders, coins. Needs $summary + panel context ($who,$seller,$member,$rp). --}}
@php
    $u = $summary['user']; $card = $summary['card']; $prof = $summary['profile'];
    $canSeePhoto = $prof && app(\App\Services\SmartBazars\OfflineCardService::class)->visibleTo($prof, $who, $seller ?? null, $member ?? null);
    $money = fn($v) => '₹' . number_format((float)$v, 2);
    $badge = ['pending_pickup' => 'bg-yellow-lt', 'completed' => 'bg-green-lt', 'cancelled' => 'bg-red-lt'];
@endphp
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex gap-3 align-items-center mb-3">
                @if($canSeePhoto && $prof->photo_file)
                    <img src="{{ route($rp . 'cards.file', [$prof, 'photo']) }}" class="rounded" style="width:84px;height:104px;object-fit:cover" alt="">
                @else
                    <span class="avatar avatar-xl rounded bg-blue-lt">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0">
                    <h3 class="mb-1 text-break">{{ $u->name }}</h3>
                    <div class="font-monospace fw-bold text-primary">{{ $card->formatted_number }}</div>
                    <span class="badge {{ $summary['valid'] ? 'bg-green-lt' : 'bg-red-lt' }} mt-1">{{ $summary['valid'] ? 'Active card' : 'Blocked / expired' }}</span>
                    <span class="badge {{ $summary['kit']['has'] ? 'bg-blue-lt' : 'bg-yellow-lt' }} mt-1"><i class="ti ti-gift me-1"></i>{{ $summary['kit']['has'] ? 'Welcome Kit purchased' : ($summary['kit']['pending'] ? 'Welcome Kit awaiting pickup' : 'No Welcome Kit yet') }}</span>
                </div>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-5 text-secondary">Mobile</dt><dd class="col-7"><a href="tel:{{ $u->mobile }}">{{ $u->mobile }}</a></dd>
                <dt class="col-5 text-secondary">Email</dt><dd class="col-7 text-break">{{ str_ends_with($u->email, '@customer.smartbazars.local') ? '—' : $u->email }}</dd>
                @if($prof)
                    <dt class="col-5 text-secondary">Ward / Panchayat</dt><dd class="col-7">{{ $prof->ward_panchayat }}</dd>
                    <dt class="col-5 text-secondary">Block</dt><dd class="col-7">{{ $prof->block }}</dd>
                    <dt class="col-5 text-secondary">District</dt><dd class="col-7">{{ $prof->district }}</dd>
                    <dt class="col-5 text-secondary">State</dt><dd class="col-7">{{ $prof->state }}</dd>
                    @if($prof->address_line)<dt class="col-5 text-secondary">Address</dt><dd class="col-7">{{ $prof->address_line }}{{ $prof->pincode ? ' - ' . $prof->pincode : '' }}</dd>@endif
                    @if($prof->aadhaar_last4)<dt class="col-5 text-secondary">Aadhaar</dt><dd class="col-7">XXXX XXXX {{ $prof->aadhaar_last4 }}</dd>@endif
                @endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="card"><div class="card-body p-3"><div class="text-secondary small">Coins</div><div class="h2 mb-0">{{ number_format($summary['coins']) }}</div><div class="text-secondary small">= {{ $money($summary['coins'] * $summary['coin_value']) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body p-3"><div class="text-secondary small">Offline orders</div><div class="h2 mb-0">{{ $summary['totals']['offline_orders'] }}</div><div class="text-secondary small">{{ $money($summary['totals']['offline_spent']) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body p-3"><div class="text-secondary small">Coins earned</div><div class="h2 mb-0">{{ number_format($summary['totals']['coins_earned']) }}</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body p-3"><div class="text-secondary small">Coins used</div><div class="h2 mb-0">{{ number_format($summary['totals']['coins_used']) }}</div></div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><h4 class="card-title">Upline / network</h4></div>
                <div class="list-group list-group-flush">
                    @foreach($summary['trail'] as $label => $name)
                        <div class="list-group-item d-flex justify-content-between"><span class="text-secondary">{{ $label }}</span><span class="fw-medium">{{ $name ?: '—' }}</span></div>
                    @endforeach
                </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><h4 class="card-title">Dates</h4></div>
                <div class="list-group list-group-flush">
                    @foreach($summary['dates'] as $label => $d)
                        <div class="list-group-item d-flex justify-content-between"><span class="text-secondary">{{ $label }}</span><span>{{ $d ?: '—' }}</span></div>
                    @endforeach
                </div></div></div>
        </div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-header"><h4 class="card-title">Offline orders ({{ $summary['offline_orders']->count() }})</h4></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Order</th><th>Date</th><th>Vendor</th><th>By</th><th>Items</th><th class="text-end">Total</th><th>Coins</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($summary['offline_orders'] as $o)
                        <tr>
                            <td class="font-monospace">{{ $o->order_no }}</td>
                            <td class="text-nowrap">{{ $o->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $o->seller?->displayName() }}</td>
                            <td>{{ $o->channel === 'worker' ? 'BJO ' . ($o->member?->user?->name ?? '') : 'Counter' }}</td>
                            <td class="text-secondary small">{{ $o->items->map(fn($i) => $i->title . ' × ' . $i->quantity)->implode(', ') }}</td>
                            <td class="text-end">{{ $money($o->total) }}</td>
                            <td class="text-nowrap small">@if($o->coins_used)<span class="text-red">−{{ $o->coins_used }}</span> @endif @if($o->coins_earned)<span class="text-green">+{{ $o->coins_earned }}</span>@endif</td>
                            <td><span class="badge {{ $badge[$o->status] ?? '' }}">{{ $o->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">No offline orders yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h4 class="card-title">Online orders ({{ $summary['online_orders']->count() }})</h4></div>
            <div class="table-responsive" style="max-height:320px">
                <table class="table table-vcenter card-table table-sm">
                    <thead><tr><th>#</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($summary['online_orders'] as $o)
                        <tr><td>{{ $o->id }}</td><td class="text-nowrap">{{ $o->created_at->format('d M Y') }}</td><td class="text-end">{{ $money($o->final_total) }}</td><td>{{ is_object($o->status) && property_exists($o->status, 'value') ? $o->status->value : $o->status }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-3">No online orders.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h4 class="card-title">Coin history</h4></div>
            <div class="table-responsive" style="max-height:320px">
                <table class="table table-vcenter card-table table-sm">
                    <thead><tr><th>Date</th><th>Description</th><th class="text-end">Coins</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    @forelse($summary['coin_history'] as $t)
                        <tr><td class="text-nowrap">{{ $t->created_at->format('d M Y') }}</td><td class="small">{{ $t->description }}</td>
                            <td class="text-end {{ $t->coins >= 0 ? 'text-green' : 'text-red' }}">{{ $t->coins > 0 ? '+' : '' }}{{ $t->coins }}</td><td class="text-end">{{ $t->balance_after }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-3">No coin activity yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
    </div>
</div>
