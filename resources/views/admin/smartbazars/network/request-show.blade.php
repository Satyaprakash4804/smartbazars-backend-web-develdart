@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_requests'])
@section('title', 'Application #' . $req->id)
@section('header_data')
    @php $page_title = ucfirst($req->type) . ' application #' . $req->id; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    @php
        $m = $req->meta ?? [];
        $mask = fn($v) => $v ? str_repeat('•', max(0, strlen($v) - 4)) . substr($v, -4) : '—';
        $pay = $req->feePayment;
    @endphp
    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Applicant</h3><div class="ms-auto">@include('smartbazars._status', ['s' => $req->status])</div></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Name</dt><dd class="col-8">{{ $req->name }}</dd>
                        <dt class="col-4">Email / Mobile</dt><dd class="col-8">{{ $req->email }} · {{ $req->mobile }}</dd>
                        <dt class="col-4">Type</dt><dd class="col-8">{{ ucfirst($req->type) }} <span class="text-secondary small">({{ $req->requesterLabel() }})</span></dd>
                        @if($req->seller)<dt class="col-4">Vendor</dt><dd class="col-8">{{ $req->seller->user->name ?? '#'.$req->seller_id }}</dd>@endif
                        @if($req->type === 'vendor')
                            <dt class="col-4">Store</dt><dd class="col-8">{{ $m['store_name'] ?? '' }}</dd>
                        @endif
                        @if(!empty($m['address']))<dt class="col-4">Address</dt><dd class="col-8">{{ $m['address'] }}, {{ $m['city'] ?? '' }}, {{ $m['state'] ?? '' }} {{ $m['zipcode'] ?? '' }}</dd>@endif
                        @if($req->source === 'public')
                            <dt class="col-4">Aadhaar no.</dt><dd class="col-8">{{ $req->sensitive('aadhar_number') ?? '—' }}</dd>
                            <dt class="col-4">PAN no.</dt><dd class="col-8">{{ $req->sensitive('pan_number') ?? '—' }}</dd>
                            <dt class="col-4">Qualification</dt><dd class="col-8">{{ $m['qualification'] ?? '—' }}</dd>
                            <dt class="col-4">Education details</dt><dd class="col-8">{{ $m['education_details'] ?? '—' }}</dd>
                        @endif
                        @if(!empty($m['referral_code']))
                            @php $refAgent = !empty($m['referred_by_member_id']) ? \App\Models\NetworkMember::with('user:id,name')->find($m['referred_by_member_id']) : null; @endphp
                            <dt class="col-4">Referral code</dt><dd class="col-8"><code>{{ $m['referral_code'] }}</code>@if($refAgent) — {{ $refAgent->user->name ?? '' }} ({{ ucfirst($refAgent->type) }})@if($req->type === 'agent') <span class="badge bg-success-lt">will join their downline</span>@endif @elseif($req->type === 'worker') <span class="text-secondary small">(vendor code)</span>@endif</dd>
                        @endif
                        <dt class="col-4">Applied on</dt><dd class="col-8">{{ $req->created_at->format('d M Y H:i') }}</dd>
                        @if($earlier)<dt class="col-4">Re-application</dt><dd class="col-8"><a href="{{ route('admin.smartbazars.network.requests.show', $earlier) }}">Earlier #{{ $earlier->id }}</a> — rejected: {{ $earlier->rejection_reason }}</dd>@endif
                        @if($req->rejection_reason)<dt class="col-4 text-danger">Rejection reason</dt><dd class="col-8 text-danger">{{ $req->rejection_reason }}</dd>@endif
                    </dl>
                </div>
            </div>
            @if($req->documents)
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Documents</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach(['aadhar' => 'Aadhaar card', 'pan' => 'PAN card', 'education' => 'Education certificate'] as $k => $l)
                            @if(!empty($req->documents[$k]))
                                <div class="list-group-item d-flex justify-content-between"><span>{{ $l }}</span><a target="_blank" href="{{ route('admin.smartbazars.network.requests.document', [$req, $k]) }}" class="btn btn-sm">View</a></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Joining fee</h3></div>
                <div class="card-body">
                    <div class="mb-1">Status: @if($req->payment_status === 'not_required')<span class="badge bg-secondary-lt">Not required</span>@else @include('smartbazars._status', ['s' => $req->payment_status])@endif</div>
                    @if($req->fee_amount)<div class="mb-1">Amount: <b>₹{{ number_format((float)$req->fee_amount, 2) }}</b></div>@endif
                    @if($pay?->razorpay_payment_id)<div class="small text-secondary">Razorpay payment: {{ $pay->razorpay_payment_id }} · {{ $pay->paid_at?->format('d M Y H:i') }}</div>@endif
                    @if($pay && ($pay->meta['reused_from_request_id'] ?? null))<div class="small text-secondary">Fee carried over from request #{{ $pay->meta['reused_from_request_id'] }}.</div>@endif
                    @if($req->payment_status === 'pending')
                        <form method="POST" action="{{ route('admin.smartbazars.network.requests.waive', $req) }}" class="mt-2" onsubmit="return confirm('Waive the joining fee for this applicant?')">@csrf<button class="btn btn-sm btn-outline-secondary">Waive fee</button></form>
                    @endif
                </div>
            </div>
            @if($req->status === 'approved')
                @php $cm = $m['credentials_mail'] ?? null; @endphp
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Login details email</h3></div>
                    <div class="card-body">
                        @if(($cm['status'] ?? null) === 'sent')
                            <div class="text-success mb-2">Sent to {{ $req->email }} @if(!empty($cm['at']))<span class="text-secondary small">({{ $cm['at'] }})</span>@endif</div>
                        @elseif($cm)
                            <div class="alert alert-danger mb-2">Not delivered: {{ $cm['error'] ?? 'unknown error' }}<div class="small mt-1">Check SmartBazars mail settings (Admin → Settings → Email).</div></div>
                        @else
                            <div class="text-secondary mb-2">No send record.</div>
                        @endif
                        <form method="POST" action="{{ route('admin.smartbazars.network.requests.resend', $req) }}" onsubmit="return confirm('This sets a NEW password and emails it. Continue?')">@csrf<button class="btn btn-outline-primary w-100">Resend login details (new password)</button></form>
                    </div>
                </div>
            @endif
            @if(in_array($req->status, ['pending_admin', 'pending_payment', 'pending_seller']))
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Decision</h3></div>
                    <div class="card-body">
                        @if($req->status === 'pending_admin')
                            <form method="POST" action="{{ route('admin.smartbazars.network.requests.approve', $req) }}" class="mb-3" onsubmit="return confirm('Approve and create the account? Login details will be emailed.')">
                                @csrf
                                @if($vendors->count())
                                    <label class="form-label required">Assign vendor</label>
                                    <select name="seller_id" class="form-select mb-2" required><option value="">Select vendor…</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->user->name ?? '#'.$v->id }}</option>@endforeach</select>
                                @endif
                                <button class="btn btn-success w-100">Approve &amp; create ID</button>
                            </form>
                        @elseif($req->status === 'pending_payment')
                            <div class="alert alert-warning">Waiting for the joining fee. Approval opens once it is paid (or waived).</div>
                        @else
                            <div class="alert alert-info">Waiting for the vendor's approval first.</div>
                        @endif
                        <form method="POST" action="{{ route('admin.smartbazars.network.requests.reject', $req) }}">
                            @csrf
                            <label class="form-label required">Reject with reason</label>
                            <textarea name="reason" rows="3" class="form-control mb-2" required minlength="3" maxlength="255" placeholder="Shown to the applicant"></textarea>
                            <button class="btn btn-outline-danger w-100">Reject</button>
                            <div class="form-hint mt-1">The applicant can apply again after a rejection.</div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
