@extends('layouts.no-head-foot')
@section('title', 'Joining fee')
@section('content')
    <div class="container container-tight py-4">
        <div class="card card-md">
            <div class="card-body text-center">
                @include('layouts.partials._alerts')
                @if($state === 'paid')
                    <h2 class="text-success">Payment received</h2>
                    <p>Thank you! Your joining fee of ₹{{ number_format((float)$fee->amount, 2) }} is recorded.</p>
                    @if($fee->request?->auto_approve)
                        <p class="text-secondary">Your account is being created — login details will be emailed to {{ $fee->payer_email }}.</p>
                    @elseif($fee->request)
                        <p class="text-secondary">Your request is now with SmartBazars for approval. You will be notified by email.</p>
                    @else
                        <p class="text-secondary">SmartBazars will now review your vendor registration.</p>
                    @endif
                @elseif($state === 'closed')
                    <h2>This application is closed</h2>
                    <p class="text-secondary">It was rejected: {{ $fee->request->rejection_reason }}. You can apply again.</p>
                @elseif($state === 'error')
                    <h2 class="text-danger">Payment could not be started</h2>
                    <p class="text-secondary">{{ $message ?? 'Please try again later.' }}</p>
                @else
                    <h2>Payment pending</h2>
                    <p class="text-secondary">We have not received your payment yet. If money was deducted it is confirmed automatically within a few minutes.</p>
                    <a href="{{ route('network-fee.pay', $fee->uuid) }}" class="btn btn-primary">Try again</a>
                @endif
                @if($fee->return_url)<a href="{{ $fee->return_url }}" class="btn btn-outline-secondary mt-2">Continue</a>@endif
            </div>
        </div>
    </div>
@endsection
