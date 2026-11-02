@php
    $map = [
        'active' => 'success', 'approved' => 'success', 'credited' => 'success', 'paid' => 'success', 'delivered' => 'success',
        'pending_admin' => 'warning', 'pending_seller' => 'orange', 'pending' => 'warning', 'processing' => 'info', 'dispatched' => 'azure',
        'suspended' => 'danger', 'rejected' => 'danger', 'reversed' => 'danger', 'cancelled' => 'secondary', 'blocked' => 'danger', 'waived' => 'info', 'pending_payment' => 'warning',
    ];
    $labels = ['pending_admin' => 'Awaiting admin', 'pending_seller' => 'Awaiting vendor', 'pending_payment' => 'Awaiting payment'];
@endphp
<span class="badge bg-{{ $map[$s] ?? 'secondary' }}-lt">{{ $labels[$s] ?? ucfirst(str_replace('_', ' ', $s)) }}</span>
