@extends('smartbazars.public._layout')
@section('title', 'Verify ' . $code)
@section('content')
    <h1>Verification</h1>
    <div class="code">{{ $code }}</div>
    @if($result)
        @if($result['status'] === 'active')
            <p class="ok" style="font-size:18px;font-weight:800">✔ Verified SmartBazars {{ $result['kind'] }}</p>
        @else
            <p class="bad" style="font-size:18px;font-weight:800">✖ {{ $result['kind'] }} is {{ ucfirst(str_replace('_', ' ', $result['status'])) }}</p>
        @endif
        <div class="row"><span>Name</span><b>{{ $result['name'] }}</b></div>
        <div class="row"><span>Type</span><b>{{ $result['kind'] }}</b></div>
        <div class="row"><span>Since</span><b>{{ $result['since'] }}</b></div>
        @if($result['extra'])<div class="row"><span>Details</span><b>{{ $result['extra'] }}</b></div>@endif
    @else
        <p class="bad" style="font-size:18px;font-weight:800">✖ No SmartBazars record found</p>
        <p>This code does not belong to any SmartBazars agent, worker, vendor or member card. Please report misuse to SmartBazars support.</p>
    @endif
    <p class="muted" style="margin-top:14px">Checked on {{ now()->format('d M Y, h:i A') }}</p>
@endsection
