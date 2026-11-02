@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_lookup'])
@section('title', 'Find Customer')
@section('header_data')
    @php $page_title = 'Find Customer by User Card'; $page_pretitle = 'Scan QR or enter card number'; @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    <div class="card mb-3"><div class="card-body">
        <form method="get" id="lookupForm" class="row g-2 align-items-center">
            <div class="col-md"><input type="text" name="q" id="lookupQ" value="{{ request('q') }}" class="form-control form-control-lg font-monospace"
                                       placeholder="User Card no. (SB2026000001) or mobile number" autocomplete="off" autofocus></div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary btn-lg"><i class="ti ti-search me-1"></i>Find</button>
                <button type="button" class="btn btn-outline-primary btn-lg" onclick="sbOpenScanner()"><i class="ti ti-scan me-1"></i>Scan QR</button>
            </div>
        </form>
        @if($notFound)<div class="alert alert-warning mt-3 mb-0">No User Card found for “{{ request('q') }}”. Check the number and try again.</div>@endif
    </div></div>

    @if($summary)
        @include('offline.partials.customer-summary')
        @if($canSell)
            <div class="mt-3"><a class="btn btn-success" href="{{ route($rp . 'pos') }}?card={{ $summary['card']->card_number }}"><i class="ti ti-shopping-cart me-1"></i>{{ $who === 'worker' ? 'Order for this customer' : 'Sell to this customer' }}</a></div>
        @endif
    @endif
    @include('offline.partials.scanner', ['onScan' => 'sbLookupScanned'])
@endsection
@push('scripts')
<script>
function sbLookupScanned(card) { document.getElementById('lookupQ').value = card; document.getElementById('lookupForm').submit(); }
</script>
@endpush
