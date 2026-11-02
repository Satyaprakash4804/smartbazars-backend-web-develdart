@extends('layouts.admin.app', ['page' => 'id_card'])
@section('title', 'My ID Card')
@section('header_data')
    @php $page_title = 'My ID Card'; $page_pretitle = 'SmartBazars ' . $role; @endphp
@endsection
@section('admin-content')
<style>
    /* Every size inside the card is in em and the card's font-size is a fixed share of its own width, so the
       card looks identical on a phone, a desktop and on paper (85.6 mm). Long text auto-shrinks (see script). */
    .sb-idwrap { display: flex; flex-wrap: wrap; gap: 24px; justify-content: center; }
    .sb-box { container-type: inline-size; width: min(100%, 440px); }
    .sb-id { width: 100%; aspect-ratio: 85.6 / 54; background: #fff; border-radius: 1.3em; box-shadow: 0 14px 40px rgba(10,46,115,.18);
             display: flex; flex-direction: column; color: #0f172a; line-height: 1.25; overflow: hidden; position: relative;
             font-size: clamp(9px, 3.4vw, 14px); font-size: 3.62cqw; }
    .sb-id .top { display: flex; justify-content: space-between; align-items: center; gap: .6em; padding: .55em 1.1em; flex: 0 0 auto;
                  background: linear-gradient(100deg, #0A2E73 0%, #123f94 60%, #1b57c0 100%); color: #fff; border-bottom: .28em solid #F57C00; }
    .sb-id .top img { height: 1.9em; width: auto; background: #fff; border-radius: .4em; padding: .15em .45em; display: block; }
    .sb-id .top .ttl { font-weight: 800; letter-spacing: .12em; font-size: .95em; white-space: nowrap; }
    .sb-id .mid { flex: 1 1 auto; min-height: 0; display: grid; grid-template-columns: 5.6em minmax(0, 1fr) 6.2em; gap: .9em;
                  align-items: center; padding: .7em 1.1em; }
    .sb-id .ph { width: 5.6em; height: 7.2em; border-radius: .55em; overflow: hidden; background: #E8F5E9; border: .2em solid #fff;
                 box-shadow: 0 0 0 .16em #1E8E3E, 0 .3em .8em rgba(15,23,42,.18); display: flex; align-items: center; justify-content: center; }
    .sb-id .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sb-id .ph .ini { font-size: 2em; font-weight: 800; color: #1E8E3E; }
    .sb-id .info { min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: .15em; }
    .sb-id .n { font-size: calc(1.3em * var(--fit, 1)); font-weight: 800; color: #0A2E73; line-height: 1.15; max-height: 2.4em; overflow: hidden; overflow-wrap: anywhere; }
    .sb-id .role { align-self: flex-start; background: #1E8E3E; color: #fff; padding: .08em .85em; border-radius: 1em; font-weight: 700; font-size: .78em; letter-spacing: .04em; margin: .1em 0 .2em; line-height: 1.45; }
    .sb-id .r { font-size: calc(.82em * var(--fit, 1)); white-space: nowrap; overflow: hidden; }
    .sb-id .r.wrapr { white-space: normal; max-height: 2.5em; line-height: 1.2; overflow-wrap: anywhere; }
    .sb-id .r .k { color: #64748b; font-size: .85em; text-transform: uppercase; letter-spacing: .05em; margin-right: .35em; }
    .sb-id .r b { font-weight: 700; }
    .sb-id .qrcol { display: flex; flex-direction: column; align-items: center; gap: .25em; }
    .sb-id .qr { width: 100%; background: #fff; border: .1em solid #e2e8f0; border-radius: .45em; padding: .2em; }
    .sb-id .qr svg { display: block; width: 100%; height: auto; }
    .sb-id .qrcap { font-size: .6em; color: #64748b; text-align: center; letter-spacing: .04em; }
    .sb-id .foot { flex: 0 0 auto; background: #F1F5F9; padding: .45em 1.1em; font-size: .72em; color: #334155; display: flex; justify-content: space-between; gap: 1em; }
    .sb-id.back .mid { display: block; align-self: center; font-size: .86em; line-height: 1.6; overflow-wrap: anywhere; }
    .sb-id .sign { margin-top: 1.2em; width: 11em; border-top: .08em solid #94a3b8; padding-top: .15em; font-size: .85em; color: #475569; }
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { background: #fff !important; }
        body * { visibility: hidden !important; }
        .sb-print, .sb-print * { visibility: visible !important; }
        .sb-print { position: absolute; left: 0; top: 0; justify-content: flex-start; gap: 8mm; }
        .sb-box { width: 85.6mm; max-width: none; }
        .sb-id { font-size: 3.1mm; box-shadow: none; border: .2mm solid #cbd5e1; break-inside: avoid; }
    }
</style>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div class="text-secondary">
        Your official SmartBazars ID. Show it to vendors and customers; anyone can scan the QR to verify you.
        @if(!$photoUrl)<div class="mt-1"><a href="{{ route('partner.profile.index') }}">Add your photo</a> so it appears on the card.</div>@endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('partner.id-card.pdf') }}" class="btn btn-primary"><i class="ti ti-download me-1"></i>Download PDF</a>
        <button onclick="window.print()" class="btn"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div></div>
<div class="sb-idwrap sb-print">
    <div class="sb-box"><div class="sb-id">
        <div class="top">@if($logo)<img src="{{ $logo }}" alt="SmartBazars">@else<span class="ttl">SmartBazars</span>@endif<span class="ttl">{{ strtoupper($role) }} ID</span></div>
        <div class="mid">
            <div class="ph">@if($photoUrl)<img src="{{ $photoUrl }}" alt="{{ $name }}">@else<span class="ini">{{ $initials }}</span>@endif</div>
            <div class="info">
                <div class="n sb-fit-h">{{ $name }}</div>
                <span class="role">{{ $role }}</span>
                <div class="r sb-fit"><span class="k">ID No.</span><b>{{ $idNo }}</b></div>
                <div class="r sb-fit"><span class="k">Code</span><b>{{ $code }}</b></div>
                <div class="r sb-fit"><span class="k">Mobile</span><b>{{ $mobile }}</b></div>
                <div class="r wrapr sb-fit-h"><span class="k">{{ $role === 'BSO' ? 'Reports to' : 'Vendor' }}</span><b>{{ $reportsTo }}</b></div>
            </div>
            <div class="qrcol"><div class="qr">{!! $qrSvg !!}</div><div class="qrcap">SCAN TO VERIFY</div></div>
        </div>
        <div class="foot"><span>Issued {{ $issued }}</span><span>Valid till {{ $validTill }}</span></div>
    </div></div>
    <div class="sb-box"><div class="sb-id back">
        <div class="top"><span class="ttl">SmartBazars</span><span style="font-size:.75em;opacity:.9">Your Everyday Shopping, Made Smarter.</span></div>
        <div class="mid">
            <div>
                This card certifies that the holder is an authorised <b>{{ $role }}</b> of SmartBazars. It is the property of SmartBazars and must be returned on request.<br>
                <b>Issued:</b> {{ $issued }} &nbsp; <b>Valid till:</b> {{ $validTill }}<br>
                <b>Verify:</b> {{ $verifyLink }}
                <div class="sign">Authorised Signatory</div>
            </div>
        </div>
        <div class="foot"><span>If found, please return to the nearest SmartBazars partner store.</span></div>
    </div></div>
</div>
<script>
    // Auto-fit: shrink a line until it fits its space (name may use two lines).
    function sbFit() {
        document.querySelectorAll('.sb-fit, .sb-fit-h').forEach(function (el) {
            var s = 1; el.style.setProperty('--fit', 1);
            var over = function () { return el.classList.contains('sb-fit-h') ? el.scrollHeight > el.clientHeight + 1 : el.scrollWidth > el.clientWidth + 1; };
            while (over() && s > 0.55) { s -= 0.04; el.style.setProperty('--fit', s.toFixed(2)); }
        });
    }
    window.addEventListener('load', sbFit);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(sbFit);
    window.addEventListener('beforeprint', sbFit);
    window.addEventListener('afterprint', sbFit);
    if (window.ResizeObserver) { var ro = new ResizeObserver(sbFit); document.querySelectorAll('.sb-box').forEach(function (b) { ro.observe(b); }); }
    sbFit();
</script>
@endsection
