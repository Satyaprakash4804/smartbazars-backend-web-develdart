@extends('layouts.no-head-foot')
@section('title', 'Joining fee')
@section('content')
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo.png') }}" alt="" width="150">
        </div>
        <div class="card card-md">
            <div class="card-body">
                @include('layouts.partials._alerts')
                <h2>SmartBazars joining fee</h2>
                <div class="d-flex justify-content-between mb-2"><span>Joining as:</span><b>{{ $role }}</b></div>
                @if($fee->payer_name)<div class="d-flex justify-content-between mb-2"><span>Name:</span><b>{{ $fee->payer_name }}</b></div>@endif
                <hr class="my-3">
                <div class="d-flex justify-content-between h3"><span>Amount:</span><span>₹{{ number_format((float)$fee->amount, 2) }}</span></div>
                <button class="btn btn-primary w-100 mt-3" id="payBtn">Pay ₹{{ number_format((float)$fee->amount, 2) }} with Razorpay</button>
                <form id="rzpForm" method="POST" action="{{ route('network-fee.verify', $fee->uuid) }}" style="display:none">
                    @csrf
                    <input type="hidden" name="razorpay_order_id" value="{{ $orderId }}">
                    <input type="hidden" name="razorpay_payment_id" id="rzp_pid">
                    <input type="hidden" name="razorpay_signature" id="rzp_sig">
                </form>
                <p class="text-secondary small mt-3 mb-0">This is a one-time joining fee. The full amount goes to SmartBazars.</p>
            </div>
        </div>
    </div>
    @push('scripts')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            document.getElementById('payBtn').addEventListener('click', function () {
                var rzp = new Razorpay({
                    key: @json($keyId),
                    amount: {{ (int)$amountPaise }},
                    currency: 'INR',
                    name: 'SmartBazars',
                    description: @json('Joining fee — ' . $role),
                    order_id: @json($orderId),
                    prefill: {name: @json($fee->payer_name), email: @json($fee->payer_email)},
                    notes: {type: 'network_fee', fee_payment_id: @json((string)$fee->id)},
                    handler: function (r) {
                        document.getElementById('rzp_pid').value = r.razorpay_payment_id;
                        document.getElementById('rzp_sig').value = r.razorpay_signature;
                        document.getElementById('rzpForm').submit();
                    },
                    theme: {color: '#0ea5e9'}
                });
                rzp.open();
            });
        </script>
    @endpush
@endsection
