<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Page = 754 x 488 px (1508 x 976 artwork at 50%). Everything is absolutely positioned in px. */
    @page { margin: 0; size: 565.5pt 366pt; }
    html, body { margin: 0; padding: 0; width: 754px; height: 488px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0A2E73; position: relative; background: #f4f9ff; }
    .a { position: absolute; }
    .k { font-size: 15px; font-weight: bold; color: #0A2E73; white-space: nowrap; }
    .v { font-size: 15px; font-weight: bold; color: #111c3a; border-bottom: 1.2px dashed #6b86b8; white-space: nowrap; overflow: hidden; }
</style>
</head>
<body>
@php
    $fsz = function (string $v) { $n = mb_strlen($v); return $n <= 20 ? 15 : ($n <= 26 ? 12.5 : 10.5); };
    $rows = [
        ['Name', \Illuminate\Support\Str::limit($name, 40)],
        ['User Card No.', $cardNo],
        ['Mobile No.', $mobile],
        ['Ward / Panchayat', \Illuminate\Support\Str::limit($ward, 40)],
        ['Block / District', \Illuminate\Support\Str::limit($block . ', ' . $district, 44)],
        ['State', \Illuminate\Support\Str::limit($state, 40)],
    ];
@endphp

@if($hdrUri)<img class="a" src="{{ $hdrUri }}" style="left:0;top:0;width:754px;height:122px">@endif
@if($ftrUri)<img class="a" src="{{ $ftrUri }}" style="left:0;top:348px;width:754px;height:140px">@endif

{{-- USER CARD pill --}}
<div class="a" style="left:212px;top:127px;width:331px;height:48px;background:#0b57c4;border-radius:13px;color:#fff">
    <div style="padding-left:30px;font-size:30px;font-weight:bold;letter-spacing:1.5px;line-height:44px">USER CARD</div>
</div>

{{-- Valid upto --}}
<div class="a" style="right:26px;top:132px;width:170px;text-align:right;font-size:11px;color:#475569;line-height:1.3">
    Valid upto<br><b style="font-size:13px;color:#0A2E73">{{ $validUpto }}</b>
</div>

{{-- Photo --}}
<div class="a" style="left:24px;top:132px;width:163px;height:192px;border:3px solid #0b57c4;border-radius:13px;background:#fff;overflow:hidden">
    @if($photo)
        <img class="a" src="{{ $photo['uri'] }}" style="left:{{ $photo['left'] }}px;top:{{ $photo['top'] }}px;width:{{ $photo['iw'] }}px;height:{{ $photo['ih'] }}px">
    @else
        <div style="margin-top:46px;text-align:center;font-size:70px;color:#d1d5db;line-height:1">&#9679;</div>
        <div style="margin:4px auto 0;width:110px;height:60px;background:#e5e7eb;border-radius:55px 55px 0 0"></div>
    @endif
</div>

{{-- Detail rows (absolute cells: dompdf ignores table column widths) --}}
@foreach($rows as $i => [$k, $v])
    @php $ty = round(197 + $i * 24, 1); @endphp
    <div class="a k" style="left:212px;top:{{ $ty }}px;width:136px;line-height:20px;font-size:13px">{{ $k }}</div>
    <div class="a k" style="left:348px;top:{{ $ty }}px;width:10px;line-height:20px">:</div>
    <div class="a v" style="left:360px;top:{{ $ty }}px;width:228px;height:20px;line-height:20px;font-size:{{ $fsz($v) }}px">{{ $v }}</div>
@endforeach

{{-- QR --}}
<div class="a" style="left:601px;top:188px;width:127px;height:124px;background:#fff;border:3px solid #0b57c4;border-radius:12px">
    <img src="{{ $qrUri }}" style="margin:6px 0 0 7px;width:104px;height:104px">
</div>
<div class="a" style="left:601px;top:317px;width:133px;height:26px;background:#0A2E73;border-radius:8px;color:#fff;text-align:center;font-size:12px;font-weight:bold;line-height:26px">Scan &amp; Connect</div>
</body>
</html>
