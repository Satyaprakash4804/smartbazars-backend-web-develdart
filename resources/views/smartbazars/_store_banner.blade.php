@php
    $sbSeller = auth()->user()?->seller();
    $sbStores = $sbSeller ? \App\Models\Store::where('seller_id', $sbSeller->id)->get()->filter(fn($s) => $s->reviewState() !== 'approved') : collect();
@endphp
@foreach($sbStores as $s)
    @if($s->reviewState() === 'rejected')
        <div class="alert alert-danger mb-3"><b>“{{ $s->name }}” needs changes.</b> Reason from SmartBazars: {{ $s->rejection_reason }}<div class="small mt-1">Edit the store and save — it goes back for review automatically.</div></div>
    @else
        <div class="alert alert-info mb-3"><b>“{{ $s->name }}” is waiting for SmartBazars verification.</b> Customers can see it once it is approved. You'll be notified by email and in the app as soon as it is decided.</div>
    @endif
@endforeach
