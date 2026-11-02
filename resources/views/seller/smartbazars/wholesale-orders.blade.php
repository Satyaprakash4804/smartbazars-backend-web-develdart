@extends('layouts.seller.app', ['page' => 'sb_vendor_business', 'sub_page' => 'sb_bulk_orders'])
@section('title', 'My Bulk Orders')
@section('header_data')
    @php $page_title = 'My Bulk Orders'; $page_pretitle = 'Purchases from SmartBazars'; @endphp
@endsection
@section('seller-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>#</th><th>Items</th><th>Total</th><th>Coins</th><th>Payable</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
                <tr><td>{{ $o->id }}<div class="small text-secondary">{{ $o->created_at->format('d M Y') }}</div></td>
                    <td class="small">@foreach($o->items as $i){{ $i->quantity }} × {{ $i->title }}<br>@endforeach</td>
                    <td>₹{{ number_format($o->subtotal, 2) }}</td>
                    <td>{{ $o->coins_used }}</td>
                    <td class="fw-bold">₹{{ number_format($o->amount_payable, 2) }}<div class="small text-secondary">{{ $o->payment_method === 'settlement' ? 'from earnings' : 'offline' }}</div></td>
                    <td>@include('smartbazars._status', ['s' => ['placed' => 'pending', 'confirmed' => 'processing', 'dispatched' => 'dispatched', 'delivered' => 'delivered', 'cancelled' => 'cancelled'][$o->status]])</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No bulk orders yet. <a href="{{ route('seller.smartbazars.wholesale') }}">Start a bulk purchase</a>.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $orders->links() }}</div>
    </div>
@endsection
