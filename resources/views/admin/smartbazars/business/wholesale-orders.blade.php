@extends('layouts.admin.app', ['page' => 'sb_business', 'sub_page' => 'sb_wholesale_orders'])
@section('title', 'Vendor Bulk Orders')
@section('header_data')
    @php $page_title = 'Vendor Bulk Orders'; $page_pretitle = 'SmartBazars wholesale'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="card-body border-bottom py-2"><form class="d-flex gap-2"><select name="status" class="form-select w-auto" onchange="this.form.submit()"><option value="">Any status</option>@foreach(\App\Models\WholesaleOrder::STATUSES as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></form></div>
        <div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>#</th><th>Vendor</th><th>Items</th><th>Total</th><th>Coins</th><th>Payable</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
                <tr>
                    <td>{{ $o->id }}<div class="small text-secondary">{{ $o->created_at->format('d M Y') }}</div></td>
                    <td>{{ $o->seller?->displayName() }}<div class="small text-secondary">{{ $o->seller?->user?->mobile }}</div></td>
                    <td class="small">@foreach($o->items as $i){{ $i->quantity }} × {{ $i->title }}<br>@endforeach @if($o->vendor_note)<i class="text-secondary">“{{ $o->vendor_note }}”</i>@endif</td>
                    <td>₹{{ number_format($o->subtotal, 2) }}</td>
                    <td>{{ $o->coins_used }}<div class="small text-secondary">₹{{ number_format($o->coin_value, 2) }}</div></td>
                    <td class="fw-bold">₹{{ number_format($o->amount_payable, 2) }}</td>
                    <td class="small">{{ $o->payment_method === 'settlement' ? 'Deducted from earnings' : 'Pay offline' }}</td>
                    <td>
                        @if($o->status !== 'cancelled')
                            <form method="POST" action="{{ route('admin.smartbazars.wholesale.orders.update', $o) }}" class="d-flex gap-1" onsubmit="return this.status.value!=='cancelled' || confirm('Cancel: return coins, stock and amount to the vendor?')">@csrf
                                <select name="status" class="form-select form-select-sm">@foreach(\App\Models\WholesaleOrder::STATUSES as $s)<option value="{{ $s }}" @selected($o->status===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-primary">Save</button></form>
                        @else @include('smartbazars._status', ['s' => 'cancelled']) @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No bulk orders yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $orders->links() }}</div>
    </div>
@endsection
