@extends('layouts.admin.app', ['page' => 'kyc'])
@section('title', 'Complete your KYC')
@section('header_data')
    @php $page_title = 'Complete your KYC'; $page_pretitle = 'SmartBazars ' . ucfirst($m->type); @endphp
@endsection
@section('admin-content')
    @php
        $need = fn($k) => in_array($k, $missing, true);
        $needAny = fn(array $ks) => count(array_intersect($ks, $missing)) > 0;
    @endphp
    <div class="row justify-content-center"><div class="col-lg-8">
        <div class="alert alert-warning">
            <b>Welcome, {{ $m->user->name }}!</b> Please complete your KYC to start using your {{ $m->type }} account.
            We only ask for the details we don't have yet.
        </div>
        @include('smartbazars._errors')
        <form method="POST" action="{{ route('partner.kyc.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body">
                @if($needAny(['name', 'mobile', 'email']))
                    <h4 class="mb-2">Basic details</h4>
                    <div class="row g-2 mb-3">
                        @if($need('name'))<div class="col-md-6"><label class="form-label required">Full name</label><input name="name" value="{{ old('name', $m->user->name) }}" class="form-control" required></div>@endif
                        @if($need('mobile'))<div class="col-md-6"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile', $m->user->mobile) }}" class="form-control" required></div>@endif
                        @if($need('email'))<div class="col-12"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email', $m->user->email) }}" class="form-control" required></div>@endif
                    </div>
                @endif

                @if($needAny(['address', 'city', 'state', 'zipcode']))
                    <h4 class="mb-2">Address</h4>
                    <div class="row g-2 mb-3">
                        @if($need('address'))<div class="col-12"><label class="form-label required">Address</label><input name="address" value="{{ old('address', $kyc?->address) }}" class="form-control" required></div>@endif
                        @if($need('city'))<div class="col-md-4"><label class="form-label required">City</label><input name="city" value="{{ old('city', $kyc?->city) }}" class="form-control" required></div>@endif
                        @if($need('state'))<div class="col-md-4"><label class="form-label required">State</label><input name="state" value="{{ old('state', $kyc?->state) }}" class="form-control" required></div>@endif
                        @if($need('zipcode'))<div class="col-md-4"><label class="form-label required">PIN code</label><input name="zipcode" value="{{ old('zipcode', $kyc?->zipcode) }}" class="form-control" required></div>@endif
                    </div>
                @endif

                @if($needAny(['aadhar_number', 'aadhar_doc', 'pan_number', 'pan_doc', 'qualification', 'education_doc']))
                    <h4 class="mb-2">Identity &amp; education</h4>
                    <div class="row g-2">
                        @if($need('aadhar_number'))<div class="col-md-6"><label class="form-label required">Aadhaar number</label><input name="aadhar_number" value="{{ old('aadhar_number') }}" class="form-control" inputmode="numeric" maxlength="14" required></div>@endif
                        @if($need('aadhar_doc'))<div class="col-md-6"><label class="form-label required">Aadhaar card (JPG/PNG/PDF)</label><input type="file" name="aadhar_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>@endif
                        @if($need('pan_number'))<div class="col-md-6"><label class="form-label required">PAN number</label><input name="pan_number" value="{{ old('pan_number') }}" class="form-control text-uppercase" maxlength="10" required></div>@endif
                        @if($need('pan_doc'))<div class="col-md-6"><label class="form-label required">PAN card (JPG/PNG/PDF)</label><input type="file" name="pan_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>@endif
                        @if($need('qualification'))<div class="col-md-6"><label class="form-label required">Highest qualification</label><input name="qualification" value="{{ old('qualification') }}" class="form-control" placeholder="e.g. B.Com, 12th pass" required></div>@endif
                        @if($need('education_doc'))<div class="col-md-6"><label class="form-label required">Education certificate (JPG/PNG/PDF)</label><input type="file" name="education_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>@endif
                        @if($needAny(['qualification', 'education_doc']) && blank($kyc?->education_details))
                            <div class="col-12"><label class="form-label">Education details <span class="text-secondary">(school / college, year, %)</span></label><textarea name="education_details" rows="2" class="form-control">{{ old('education_details') }}</textarea></div>
                        @endif
                    </div>
                    <div class="form-hint mt-1">Each file up to 4 MB. Documents are stored privately and seen only by SmartBazars admin.</div>
                @endif
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Submit KYC</button></div>
        </form>
        <div class="text-center mt-2"><a href="{{ route('partner.logout') }}" class="text-secondary small">Log out</a></div>
    </div></div>
@endsection
