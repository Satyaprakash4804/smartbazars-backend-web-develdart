@extends('smartbazars.public._layout')
@section('title', 'Join with ' . $code)
@section('content')
    <h1>You're invited to SmartBazars</h1>
    <p>
        @if($type === 'vendor') <b>{{ $vendorName }}</b> invites you to shop smarter with SmartBazars.
        @else <b>{{ $workerName }}</b> invites you to join SmartBazars.@endif
    </p>
    <div class="code" id="code">{{ $code }}</div>
    <p class="muted">Use this referral code when you sign up or buy the ₹{{ number_format($kitPrice, 0) }} Welcome Kit to get your SmartBazars User Card.</p>
    <p><img class="coin" src="{{ asset('assets/images/smartbazars/coin.png') }}" alt=""> Earn cashback coins on every order · 1 coin = ₹1</p>
    <button class="btn alt" onclick="navigator.clipboard.writeText('{{ $code }}');this.textContent='Code copied ✓'">Copy code</button>
    <a class="btn" href="{{ $siteUrl }}/?ref={{ $code }}">Start shopping on SmartBazars</a>
    <p class="muted" style="margin-top:14px">Have the SmartBazars app? Open it → Account → SmartBazars User Card → enter <b>{{ $code }}</b>.</p>
@endsection
