@extends('layouts.admin.app', ['page' => 'requests'])
@section('title', 'Create IDs')
@section('header_data')
    @php $page_title = 'Create IDs'; $page_pretitle = 'BSOs, vendors & workers'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">New ID request</h3></div>
                <form method="POST" action="{{ route('partner.requests.store') }}" class="card-body" id="req-form">
                    @csrf
                    <div class="mb-3">
                        <div class="form-selectgroup w-100">
                            @foreach(['agent' => 'BSO', 'vendor' => 'Vendor', 'worker' => 'BJO'] as $k => $l)
                                <label class="form-selectgroup-item flex-fill"><input type="radio" name="type" value="{{ $k }}" class="form-selectgroup-input" @checked(old('type','agent')===$k) onchange="sbType(this.value)"><span class="form-selectgroup-label">{{ $l }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-2"><label class="form-label required">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" required></div>

                    <div data-for="vendor" class="d-none">
                        <div class="mb-2"><label class="form-label required">Store / business name</label><input name="meta[store_name]" value="{{ old('meta.store_name') }}" class="form-control"></div>
                        <div class="mb-2"><label class="form-label required">Address</label><input name="meta[address]" value="{{ old('meta.address') }}" class="form-control"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Landmark</label><input name="meta[landmark]" value="{{ old('meta.landmark') }}" class="form-control"></div>
                            <div class="col-6"><label class="form-label required">City</label><input name="meta[city]" value="{{ old('meta.city') }}" class="form-control"></div>
                            <div class="col-6"><label class="form-label required">State</label><input name="meta[state]" value="{{ old('meta.state') }}" class="form-control"></div>
                            <div class="col-6"><label class="form-label required">PIN code</label><input name="meta[zipcode]" value="{{ old('meta.zipcode') }}" class="form-control"></div>
                        </div>
                    </div>
                    <div data-for="worker" class="d-none mb-2">
                        <label class="form-label required">Works for vendor</label>
                        <select name="seller_id" class="form-select">
                            <option value="">Select one of your vendors…</option>
                            @foreach($myVendors as $v)<option value="{{ $v->id }}" @selected(old('seller_id')==$v->id)>{{ $v->user->name ?? '#'.$v->id }}</option>@endforeach
                        </select>
                        <div class="small text-secondary mt-1">The vendor approves first, then SmartBazars admin.</div>
                    </div>
                    @php $feeSvc = app(\App\Services\SmartBazars\NetworkFeeService::class); @endphp
                    @if($feeSvc->enabled())
                        <div class="small text-secondary mb-2">Joining fee: BSO ₹{{ number_format($feeSvc->feeFor('agent'), 2) }} · Vendor ₹{{ number_format($feeSvc->feeFor('vendor'), 2) }} · BJO ₹{{ number_format($feeSvc->feeFor('worker'), 2) }}. You pay online after submitting; no commission is shared from it.</div>
                    @endif
                    <button class="btn btn-primary w-100 mt-2">Send for approval</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">My requests</h3></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Type</th><th>Name</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        @forelse($rows as $r)
                            <tr><td>{{ ucfirst($r->type) }}</td><td>{{ $r->name }}<div class="small text-secondary">{{ $r->email }}</div>@if($r->seller)<div class="small">for {{ $r->seller->user->name ?? '' }}</div>@endif</td>
                                <td>@include('smartbazars._status', ['s' => $r->status])@if($r->rejection_reason)<div class="small text-danger">{{ $r->rejection_reason }}</div>@endif @if($r->status === 'pending_payment' && $r->feePayment)<a href="{{ $r->feePayment->payUrl() }}" class="btn btn-sm btn-primary mt-1">Pay ₹{{ number_format((float)$r->fee_amount, 2) }}</a>@endif</td>
                                <td class="small text-secondary">{{ $r->created_at->format('d M Y') }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No requests yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
    <script>
        function sbType(t) {
            document.querySelectorAll('#req-form [data-for]').forEach(el => el.classList.toggle('d-none', el.dataset.for !== t));
        }
        sbType(document.querySelector('#req-form input[name=type]:checked').value);
    </script>
@endsection
