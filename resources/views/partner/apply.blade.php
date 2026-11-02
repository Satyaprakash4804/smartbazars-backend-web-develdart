@extends('layouts.admin.guest')
@section('title', 'Apply now')
@section('content')
    <div class="page page-center">
        <div class="container container-tight py-4" style="max-width:720px">
            <div class="text-center mb-4">
                <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo.png') }}" alt="SmartBazars" style="max-height:90px">
            </div>
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-1">Apply to join SmartBazars</h2>
                    <p class="text-center text-secondary mb-3">BSO or BJO</p>
                    @include('layouts.partials._alerts')
                    @include('smartbazars._errors')
                    @if($retry)
                        <div class="alert alert-warning">Your earlier application was not approved: <b>{{ $retry->rejection_reason }}</b>. Please correct it and apply again.@if(\App\Services\SmartBazars\ProgramSettings::get('joining_fee_reuse_on_reapply') && $retry->payment_status === 'paid') The joining fee you already paid will be used for this application.@endif</div>
                    @endif
                    <form method="POST" action="{{ route('partner.apply.submit') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="retry" value="{{ $retry?->uuid }}">
                        @php $v = fn($k, $d = '') => old($k, $retry ? ($retry->meta[$k] ?? $retry->{$k} ?? $d) : $d); @endphp
                        <div class="mb-3">
                            <label class="form-label required">I want to join as</label>
                            <div class="form-selectgroup w-100">
                                @foreach(['agent' => 'BSO', 'worker' => 'BJO'] as $k => $l)
                                    <label class="form-selectgroup-item flex-fill"><input type="radio" name="type" value="{{ $k }}" class="form-selectgroup-input" @checked(old('type', $retry?->type ?? 'agent') === $k) onchange="sbApplyType(this.value)"><span class="form-selectgroup-label">{{ $l }}@if(($fees[$k] ?? 0) > 0) <span class="text-secondary small">(₹{{ number_format($fees[$k], 2) }})</span>@endif</span></label>
                                @endforeach
                            </div>
                            @if(($fees['agent'] ?? 0) > 0 || ($fees['worker'] ?? 0) > 0)
                                <div class="form-hint mt-1">A one-time joining fee is paid online after you submit this form.</div>
                            @endif
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label required">Full name</label><input name="name" class="form-control" value="{{ $v('name') }}" required></div>
                            <div class="col-md-6"><label class="form-label required">Mobile</label><input name="mobile" class="form-control" value="{{ $v('mobile') }}" required></div>
                            <div class="col-12"><label class="form-label required">Email</label><input type="email" name="email" class="form-control" value="{{ $v('email') }}" required><div class="form-hint">Your login details are sent here after approval.</div></div>
                            <div class="col-12"><label class="form-label required">Address</label><input name="address" class="form-control" value="{{ $v('address') }}" required></div>
                            <div class="col-md-4"><label class="form-label required">City</label><input name="city" class="form-control" value="{{ $v('city') }}" required></div>
                            <div class="col-md-4"><label class="form-label required">State</label><input name="state" class="form-control" value="{{ $v('state') }}" required></div>
                            <div class="col-md-4"><label class="form-label required">PIN code</label><input name="zipcode" class="form-control" value="{{ $v('zipcode') }}" required></div>
                        </div>
                        <div class="mt-3">
            <label class="form-label">Referral code <span class="text-secondary">(optional)</span></label>
            <input name="referral_code" class="form-control text-uppercase" value="{{ old('referral_code', $refCode) }}" maxlength="20" placeholder="Code of the agent who invited you">
            @if($referrer)
                <div class="form-hint text-success">You will join the team of <b>{{ $referrer->user->name ?? 'your referrer' }}</b>.</div>
            @else
                <div class="form-hint"><span data-for="agent">Enter an agent's code to join that agent's downline.</span><span data-for="worker" class="d-none">Enter your vendor's code to work for them (or the agent who invited you). If you have none, SmartBazars will assign a vendor.</span></div>
            @endif
        </div>

        <h4 class="mt-4">Identity & education</h4>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label required">Aadhaar number</label><input name="aadhar_number" class="form-control" value="{{ old('aadhar_number') }}" inputmode="numeric" maxlength="14" required></div>
                            <div class="col-md-6"><label class="form-label required">Aadhaar card (JPG/PNG/PDF)</label><input type="file" name="aadhar_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                            <div class="col-md-6"><label class="form-label required">PAN number</label><input name="pan_number" class="form-control text-uppercase" value="{{ old('pan_number') }}" maxlength="10" required></div>
                            <div class="col-md-6"><label class="form-label required">PAN card (JPG/PNG/PDF)</label><input type="file" name="pan_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                            <div class="col-md-6"><label class="form-label required">Highest qualification</label><input name="qualification" class="form-control" value="{{ $v('qualification') }}" placeholder="e.g. B.Com, 12th pass" required></div>
                            <div class="col-md-6"><label class="form-label required">Education certificate (JPG/PNG/PDF)</label><input type="file" name="education_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                            <div class="col-12"><label class="form-label">Education details <span class="text-secondary">(school / college, year, % )</span></label><textarea name="education_details" rows="2" class="form-control">{{ $v('education_details') }}</textarea></div>
                        </div>
                        <div class="form-hint mt-1">Each file up to 4 MB. Documents are stored privately and seen only by SmartBazars admin.</div>
                        <button class="btn btn-primary w-100 mt-4">Submit &amp; continue</button>
                    </form>
                    <div class="text-center mt-3"><a href="{{ route('partner.login') }}">Already approved? Sign in</a></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function sbApplyType(t) { document.querySelectorAll('[data-for]').forEach(function (e) { e.classList.toggle('d-none', e.dataset.for !== t); }); }
        sbApplyType(document.querySelector('input[name=type]:checked').value);
    </script>
@endsection
