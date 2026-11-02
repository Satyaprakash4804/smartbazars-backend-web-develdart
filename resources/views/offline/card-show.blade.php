@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_cards'])
@section('title', 'User Card')
@section('header_data')
    @php $page_title = 'User Card — ' . $card['cardNo']; $page_pretitle = 'Offline customer'; @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    <div class="card mb-3"><div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">@include('offline.partials.card', ['card' => $card])</div>
            <div class="col-lg-5">
                <h3 class="mb-3">Share this card</h3>
                <div class="d-grid gap-2">
                    <a href="{{ route($rp . 'cards.pdf', $profile) }}" class="btn btn-primary"><i class="ti ti-download me-1"></i>Download PDF</a>
                    <button type="button" class="btn btn-success" id="btnWa"><i class="ti ti-brand-whatsapp me-1"></i>Send on WhatsApp</button>
                    <form method="post" action="{{ route($rp . 'cards.email', $profile) }}">@csrf
                        <button class="btn btn-outline-primary w-100"><i class="ti ti-mail me-1"></i>{{ $profile->card_emailed_at ? 'Resend email' : 'Email card' }} to {{ $profile->user->email }}</button></form>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
                    @if(in_array($who, ['seller', 'admin']) && $profile->aadhaar_file)
                        <a href="{{ route($rp . 'cards.file', [$profile, 'aadhaar']) }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-file-certificate me-1"></i>View Aadhaar upload</a>
                    @endif
                    @if($canSell)<a href="{{ route($rp . 'pos') }}?card={{ $card['rawCardNo'] }}" class="btn btn-outline-success"><i class="ti ti-shopping-cart me-1"></i>{{ $who === 'worker' ? 'Order for this customer' : 'Sell to this customer' }}</a>@endif
                </div>
                <div class="text-secondary small mt-2">Last emailed: {{ $profile->card_emailed_at?->format('d M Y, h:i A') ?? 'not yet' }}</div>
            </div>
        </div>
    </div></div>
    @if($canSell && $summary['kit']['recommend'])
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><strong>★ Recommend the Welcome Kit</strong> — {{ $summary['user']->name }} has not bought it yet ({{ '₹' . number_format((float)$summary['kit']['price'], 2) }}). Offer it with the first purchase.</div>
            <a class="btn btn-warning" href="{{ route($rp . 'pos') }}?card={{ $card['rawCardNo'] }}&kit=1">{{ $who === 'worker' ? 'Order Welcome Kit' : 'Sell Welcome Kit now' }}</a>
        </div>
    @endif
    @include('offline.partials.customer-summary')
@endsection
@push('scripts')
<script>
document.getElementById('btnWa').addEventListener('click', async function () {
    const waUrl = @json($wa);
    // On phones that support it, share the PDF itself; otherwise open WhatsApp with the card link.
    try {
        const res = await fetch(@json(route($rp . 'cards.pdf', $profile)), { credentials: 'same-origin' });
        const blob = await res.blob();
        const file = new File([blob], 'SmartBazars-User-Card-{{ $card['rawCardNo'] }}.pdf', { type: 'application/pdf' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({ files: [file], title: 'SmartBazars User Card', text: 'Your SmartBazars User Card {{ $card['cardNo'] }}' });
            return;
        }
    } catch (e) { /* fall through to link */ }
    window.open(waUrl, '_blank');
});
</script>
@endpush
