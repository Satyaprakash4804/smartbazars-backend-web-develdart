@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_requests'])
@section('title', 'ID Approvals')
@section('header_data')
    @php $page_title = 'ID Approvals'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach(['pending_admin' => 'Awaiting you', 'pending_payment' => 'Awaiting payment', 'pending_seller' => 'Awaiting vendor', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('admin.smartbazars.network.requests', ['status' => $k]) }}">
                            {{ $label }} @if(isset($counts[$k]))<span class="badge bg-secondary-lt ms-1">{{ $counts[$k] }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body border-bottom py-2">
            <form class="d-flex gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <select name="type" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach(['agent','vendor','worker'] as $t)
                        <option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>#</th><th>Type</th><th>Name / Contact</th><th>Requested by</th><th>For vendor</th><th>Details</th><th>Fee</th><th>Status</th><th>Date</th><th class="w-1"></th></tr></thead>
                <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td><span class="badge bg-{{ ['agent'=>'green','vendor'=>'blue','worker'=>'orange'][$r->type] }}-lt">{{ ucfirst($r->type) }}</span></td>
                        <td><div class="fw-bold">{{ $r->name }}</div><div class="small text-secondary">{{ $r->email }} · {{ $r->mobile }}</div></td>
                        <td class="small">{{ $r->requesterLabel() }}</td>
                        <td class="small">{{ $r->seller?->user?->name ?? '—' }}</td>
                        <td class="small">
                            @if($r->type === 'vendor' && $r->meta)
                                <b>{{ $r->meta['store_name'] ?? '' }}</b><br>{{ $r->meta['address'] ?? '' }}, {{ $r->meta['city'] ?? '' }}, {{ $r->meta['state'] ?? '' }} {{ $r->meta['zipcode'] ?? '' }}
                            @elseif($r->rejection_reason)
                                <span class="text-danger">{{ $r->rejection_reason }}</span>
                            @else — @endif
                        </td>
                        <td class="small">@if($r->payment_status === 'not_required')—@else @include('smartbazars._status', ['s' => $r->payment_status])@if($r->fee_amount)<div class="text-secondary">₹{{ number_format((float)$r->fee_amount, 2) }}</div>@endif @endif</td>
                        <td>@include('smartbazars._status', ['s' => $r->status])</td>
                        <td class="small text-secondary">{{ $r->created_at->format('d M Y') }}</td>
                        <td class="text-nowrap"><a href="{{ route('admin.smartbazars.network.requests.show', $r) }}" class="btn btn-sm btn-primary">View / Decide</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-secondary py-4">No requests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>
@endsection
