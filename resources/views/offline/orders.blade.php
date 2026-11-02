@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_orders'])
@section('title', 'Offline Orders')
@section('header_data')
    @php $page_title = 'Offline Orders'; $page_pretitle = 'Counter sales & BJO orders'; @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    @php $money = fn($v) => '₹' . number_format((float)$v, 2); $badge = ['pending_pickup' => 'bg-yellow-lt', 'completed' => 'bg-green-lt', 'cancelled' => 'bg-red-lt']; @endphp
    <div class="card">
        <div class="card-header"><form method="get" class="row g-2 w-100">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Order no., customer, mobile, card"></div>
            <div class="col-6 col-md-2"><select name="status" class="form-select"><option value="">All status</option>@foreach(['pending_pickup' => 'Ready for pickup', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><select name="channel" class="form-select"><option value="">All channels</option><option value="counter" @selected(request('channel') === 'counter')>Counter sale</option><option value="worker" @selected(request('channel') === 'worker')>BJO order</option></select></div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
        </form></div>
        <div class="card-body py-2 text-secondary small">{{ $totals['count'] }} orders match · total offline sales (all time, not cancelled): <strong>{{ $money($totals['amount']) }}</strong></div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Order</th><th>Customer / card</th>@if($who === 'admin')<th>Vendor</th>@endif<th>By</th><th class="text-end">Total</th><th>Paid with</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $o)
                    <tr>
                        <td><span class="font-monospace">{{ $o->order_no }}</span><div class="text-secondary small">{{ $o->created_at->format('d M Y, h:i A') }}</div></td>
                        <td>{{ $o->customer?->name }}<div class="text-secondary small font-monospace">{{ $o->card?->formatted_number }}</div></td>
                        @if($who === 'admin')<td class="small">{{ $o->seller?->displayName() }}</td>@endif
                        <td class="small">{{ $o->channel === 'worker' ? 'BJO ' . ($o->member?->user?->name ?? '') : 'Counter' }}</td>
                        <td class="text-end">{{ $money($o->total) }}</td>
                        <td class="small">{{ ucfirst($o->payment_mode) }}@if($o->coins_used)<div class="text-secondary">{{ $o->coins_used }} coins</div>@endif</td>
                        <td><span class="badge {{ $badge[$o->status] ?? '' }}">{{ $o->statusLabel() }}</span></td>
                        <td class="text-end"><a href="{{ route($rp . 'orders.show', $o) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">No offline orders.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
