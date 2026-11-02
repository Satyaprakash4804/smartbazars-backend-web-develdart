@extends('layouts.admin.guest')
@section('title', 'Partner Login')
@section('content')
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo.png') }}" alt="SmartBazars" style="max-height:90px">
            </div>
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-1">Partner Login</h2>
                    <p class="text-center text-secondary mb-4">For SmartBazars BSOs & BJOs</p>
                    @include('layouts.partials._alerts')
                    @include('smartbazars._errors')
                    <form method="POST" action="{{ route('partner.login.post') }}" autocomplete="off">
                        @csrf
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
                        <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                        <label class="form-check mb-3"><input type="checkbox" name="remember" class="form-check-input"><span class="form-check-label">Remember me</span></label>
                        <button class="btn btn-primary w-100">Sign in</button>
                    </form>
                    <div class="hr-text">New here?</div>
                    <a href="{{ route('partner.apply') }}" class="btn btn-outline-primary w-100">Apply now</a>
                </div>
            </div>
            <div class="text-center text-secondary mt-3 small">Your login details are emailed after SmartBazars approves your ID.</div>
        </div>
    </div>
@endsection
