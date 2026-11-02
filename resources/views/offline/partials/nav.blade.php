@php
    $tabs = [['home', 'Overview', 'ti-layout-dashboard']];
    if ($canCreate) $tabs[] = ['cards.create', 'New User Card', 'ti-id-badge-2'];
    $tabs[] = ['cards', 'User Cards', 'ti-cards'];
    if ($canSell) $tabs[] = ['pos', $who === 'worker' ? 'Order for Customer' : 'Offline Sale (POS)', 'ti-shopping-cart'];
    $tabs[] = ['orders', $who === 'worker' ? 'My Offline Orders' : 'Offline Orders', 'ti-receipt'];
    $tabs[] = ['lookup', 'Find Customer', 'ti-scan'];
@endphp
<div class="card mb-3">
    <div class="card-body p-2">
        <div class="d-flex flex-wrap gap-2">
            @foreach($tabs as [$name, $label, $icon])
                <a href="{{ route($rp . $name) }}" class="btn btn-sm {{ request()->routeIs($rp . $name) ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="ti {{ $icon }} me-1"></i>{{ $label }}
                </a>
            @endforeach
        </div>
    </div>
</div>
