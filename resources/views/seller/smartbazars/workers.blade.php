@extends('layouts.seller.app', ['page' => 'sb_workers'])
@section('title', 'My BJOs')
@section('header_data')
    @php $page_title = 'My BJOs'; $page_pretitle = 'SmartBazars Welcome Kit network'; @endphp
@endsection
@section('seller-content')
    @include('smartbazars._errors')
    <div class="row row-cards mb-3">
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Active workers</div><div class="h2 mb-0">{{ $workers->where('status','active')->count() }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Kits sold by workers</div><div class="h2 mb-0">{{ $kitsSold }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Your kit earnings</div><div class="h2 mb-0">₹{{ number_format($kitEarnings, 2) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Your agent</div><div class="h3 mb-0">{{ $agent->user->name ?? 'SmartBazars' }}</div></div></div></div>
    </div>

    @if($awaitingMe->count())
        <div class="card mb-3 border-warning">
            <div class="card-header"><h3 class="card-title">BJO requests waiting for your approval</h3></div>
            <div class="table-responsive"><table class="table card-table table-vcenter">
                <thead><tr><th>Name</th><th>Contact</th><th>Requested by agent</th><th></th></tr></thead>
                <tbody>
                @foreach($awaitingMe as $r)
                    <tr>
                        <td class="fw-bold">{{ $r->name }}</td>
                        <td class="small">{{ $r->email }}<br>{{ $r->mobile }}</td>
                        <td class="small">{{ $r->requestedByMember->user->name ?? '' }} ({{ $r->requestedByMember->referral_code ?? '' }})</td>
                        <td class="text-nowrap">
                            <form method="POST" action="{{ route('seller.smartbazars.workers.decide', $r) }}" class="d-inline">@csrf
                                <button name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Add a worker</h3></div>
                <form method="POST" action="{{ route('seller.smartbazars.workers.store') }}" class="card-body">
                    @csrf
                    <div class="mb-2"><label class="form-label required">Name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" required></div>
                    @php $wf = app(\App\Services\SmartBazars\NetworkFeeService::class)->feeFor('worker'); @endphp
                    @if($wf > 0)<div class="small text-secondary mb-2">Joining fee ₹{{ number_format($wf, 2) }} is paid online after you submit.</div>@endif
                    <button class="btn btn-primary w-100">Send for admin approval</button>
                    <p class="small text-secondary mt-2 mb-0">After SmartBazars approves, the worker gets login details by email and a referral code. You earn on every Welcome Kit sold with their code.</p>
                </form>
            </div>
            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Request history</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($history as $h)
                        <div class="list-group-item d-flex justify-content-between"><span>{{ $h->name }}</span><span>@include('smartbazars._status', ['s' => $h->status])@if($h->status === 'pending_payment' && $h->feePayment) <a href="{{ $h->feePayment->payUrl() }}" class="btn btn-sm btn-primary ms-1">Pay ₹{{ number_format((float)$h->fee_amount, 2) }}</a>@endif</span></div>
                    @empty <div class="list-group-item text-secondary">None yet.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">BJOs</h3></div>
                <div class="table-responsive"><table class="table card-table table-vcenter">
                    <thead><tr><th>BJO</th><th>Referral code</th><th>Customers</th><th>Earned</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($workers as $w)
                        <tr>
                            <td>{{ $w->user->name ?? '' }}<div class="small text-secondary">{{ $w->user->mobile ?? '' }}</div></td>
                            <td><code>{{ $w->referral_code }}</code></td>
                            <td>{{ $w->customers_count }}</td>
                            <td>₹{{ number_format($w->total_earned, 2) }}</td>
                            <td>@include('smartbazars._status', ['s' => $w->status])</td>
                            <td><form method="POST" action="{{ route('seller.smartbazars.workers.toggle', $w) }}">@csrf
                                <button class="btn btn-sm {{ $w->status==='active' ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $w->status==='active' ? 'Suspend' : 'Activate' }}</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">No workers yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
@endsection
