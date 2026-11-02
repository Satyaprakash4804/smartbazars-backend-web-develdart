@extends('layouts.seller.app', ['page' => 'sb_kyc'])
@section('title', 'Complete your KYC')
@section('header_data')
    @php $page_title = 'Complete your KYC'; $page_pretitle = 'SmartBazars Vendor'; @endphp
@endsection
@section('seller-content')
    @php
        $need = fn($k) => in_array($k, $missing, true);
        $needAny = fn(array $ks) => count(array_intersect($ks, $missing)) > 0;
        $help = [
            'business_license' => 'Upload a clear copy of your business license. Accepted formats: JPEG, PNG, PDF. Max size: 2MB.',
            'articles_of_incorporation' => "Provide your company's articles of incorporation or certificate of incorporation. File must be clear and readable.",
            'national_identity_card' => "Upload a government-issued photo ID (passport, driver's license, or national ID card). Both front and back sides if applicable.",
            'authorized_signature' => 'Upload a document with authorized signature samples or signature authorization letter from your company.',
        ];
    @endphp
    <div class="row justify-content-center"><div class="col-lg-8">
        <div class="alert alert-warning">
            <b>Welcome, {{ $seller->user->name }}!</b> Please complete your KYC to start using your vendor account.
            We only ask for the details we don't have yet.
        </div>
        @include('smartbazars._errors')
        <form method="POST" action="{{ route('seller.smartbazars.kyc.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body">
                @if($needAny(['name', 'country', 'mobile', 'email']))
                    <h4 class="mb-2">Basic details</h4>
                    <div class="row g-2 mb-3">
                        @if($need('name'))<div class="col-md-6"><label class="form-label required">Vendor name</label><input name="name" value="{{ old('name', $seller->user->name) }}" class="form-control" required></div>@endif
                        @if($need('country'))<div class="col-md-6"><label class="form-label required">Country</label><input name="country" value="{{ old('country', $seller->country ?: 'India') }}" class="form-control" required></div>@endif
                        @if($need('mobile'))<div class="col-md-6"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile', $seller->user->mobile) }}" class="form-control" required></div>@endif
                        @if($need('email'))<div class="col-md-6"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email', $seller->user->email) }}" class="form-control" required></div>@endif
                    </div>
                @endif

                @if($needAny(['address', 'city', 'state', 'zipcode']))
                    <h4 class="mb-2">Location details</h4>
                    <div class="row g-2 mb-3">
                        @if($need('address'))<div class="col-12"><label class="form-label required">Address</label><input name="address" value="{{ old('address', $seller->address) }}" class="form-control" required></div>@endif
                        @if($need('city'))<div class="col-md-6"><label class="form-label required">City</label><input name="city" value="{{ old('city', $seller->city) }}" class="form-control" required></div>@endif
                        <div class="col-md-6"><label class="form-label">Landmark</label><input name="landmark" value="{{ old('landmark', $seller->landmark) }}" class="form-control"></div>
                        @if($need('state'))<div class="col-md-6"><label class="form-label required">State</label><input name="state" value="{{ old('state', $seller->state) }}" class="form-control" required></div>@endif
                        @if($need('zipcode'))<div class="col-md-6"><label class="form-label required">Zipcode</label><input name="zipcode" value="{{ old('zipcode', $seller->zipcode) }}" class="form-control" required></div>@endif
                    </div>
                @endif

                @if($needAny(array_keys($docs)))
                    <h4 class="mb-2">Business documents</h4>
                    @foreach($docs as $key => $label)
                        @if($need($key))
                            <div class="mb-3">
                                <label class="form-label required">{{ $label }}</label>
                                <input type="file" name="{{ $key }}" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                                <div class="form-hint">{{ $help[$key] }}</div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Submit KYC</button></div>
        </form>
        <div class="text-center mt-2"><a href="{{ route('seller.logout') }}" class="text-secondary small">Log out</a></div>
    </div></div>
@endsection
