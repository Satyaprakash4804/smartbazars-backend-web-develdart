@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => $member->isAgent() ? 'sb_agents' : 'sb_workers'])
@section('title', 'Edit ' . $member->type)
@section('header_data')
    @php $page_title = 'Edit ' . ucfirst($member->type); $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row justify-content-center"><div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ $member->user->name ?? '' }} <code class="ms-2">{{ $member->referral_code }}</code></h3></div>
            <form method="POST" action="{{ route('admin.smartbazars.network.member.update', $member) }}" class="card-body">
                @csrf @method('PUT')
                <div class="mb-2"><label class="form-label required">Full name</label><input name="name" value="{{ old('name', $member->user->name) }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label required">Email (login)</label><input type="email" name="email" value="{{ old('email', $member->user->email) }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile', $member->user->mobile) }}" class="form-control" required></div>
                @if($member->isWorker())
                    <div class="mb-2"><label class="form-label required">Works for vendor</label>
                        <select name="seller_id" class="form-select" required>@foreach($sellers as $s)<option value="{{ $s->id }}" @selected(old('seller_id', $member->seller_id) == $s->id)>{{ $s->user->name ?? '#'.$s->id }}</option>@endforeach</select></div>
                @else
                    <div class="mb-2"><label class="form-label">Upline agent</label>
                        <select name="parent_id" class="form-select">
                            <option value="" @selected(!old('parent_id', $member->parent_id))>SmartBazars (top level)</option>
                            @foreach($uplines as $u)<option value="{{ $u->id }}" @selected(old('parent_id', $member->parent_id) == $u->id)>{{ str_repeat('— ', max(0, $u->level - 1)) }}{{ $u->user->name ?? '#'.$u->id }} ({{ $u->referral_code }})</option>@endforeach
                        </select>
                        <div class="form-hint">Moves this agent <b>and their whole downline</b>. Future commissions follow the new upline; credited ones are unchanged. Prefer drag &amp; drop? Use <a href="{{ route('admin.smartbazars.network.mapping') }}">Network Mapping</a>.</div></div>
                @endif
                <div class="mb-2"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="active" @selected(old('status', $member->status)==='active')>Active</option><option value="suspended" @selected(old('status', $member->status)==='suspended')>Suspended</option></select></div>
                <label class="form-check mt-2"><input type="checkbox" name="send_credentials" value="1" class="form-check-input"><span class="form-check-label">If the email changed, send new login details (new password) to the new address</span></label>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary">Save changes</button>
                    <a href="{{ route('admin.smartbazars.network.' . ($member->isAgent() ? 'agents' : 'workers')) }}" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">KYC details</h3>
                <div class="ms-auto">@if($kycMissing)<span class="badge bg-warning-lt">Incomplete</span>@else<span class="badge bg-success-lt">Complete</span>@endif</div></div>
            <div class="card-body">
                @if($kycMissing)<div class="alert alert-warning small mb-3">Still to be provided by the member at their next login: {{ implode(', ', array_map(fn($k) => str_replace('_', ' ', $k), $kycMissing)) }}.</div>@endif
                @if($kyc)
                    <dl class="row mb-0 small">
                        <dt class="col-4">Address</dt><dd class="col-8">{{ $kyc->address ?: '—' }}, {{ $kyc->city }}, {{ $kyc->state }} {{ $kyc->zipcode }}</dd>
                        <dt class="col-4">Aadhaar no.</dt><dd class="col-8">{{ $kyc->sensitive('aadhar_number') ?: '—' }}</dd>
                        <dt class="col-4">PAN no.</dt><dd class="col-8">{{ $kyc->sensitive('pan_number') ?: '—' }}</dd>
                        <dt class="col-4">Qualification</dt><dd class="col-8">{{ $kyc->qualification ?: '—' }}</dd>
                        <dt class="col-4">Education</dt><dd class="col-8">{{ $kyc->education_details ?: '—' }}</dd>
                    </dl>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        @foreach(\App\Services\SmartBazars\KycService::MEMBER_DOCS as $k => $l)
                            @if(!empty($kyc->documents[$k]))<a target="_blank" class="btn btn-sm" href="{{ route('admin.smartbazars.network.member.kyc-doc', [$member, $k]) }}">{{ $l }}</a>@endif
                        @endforeach
                    </div>
                @else
                    <div class="text-secondary">Nothing submitted yet.</div>
                @endif
            </div>
        </div>
    </div></div>
@endsection
