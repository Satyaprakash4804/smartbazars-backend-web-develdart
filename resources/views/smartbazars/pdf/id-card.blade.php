<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111827; font-size: 9px; margin: 0; }
    .card { width: 323px; height: 204px; border-radius: 12px; overflow: hidden; border: 1px solid #d1d5db; position: relative; display: inline-block; margin: 0 10px 14px 0; vertical-align: top; }
    .hd { width: 323px; background: #0A2E73; color: #fff; height: 36px; table-layout: fixed; }
    .hd td { padding: 0 10px; vertical-align: middle; }
    .t { font-size: 12px; font-weight: bold; letter-spacing: 1px; color: #fff; }
    .orange { height: 4px; background: #F57C00; }
    .bd { width: 323px; table-layout: fixed; margin-top: 8px; }
    .bd td { vertical-align: top; }
    .photo { position: relative; width: 58px; height: 74px; overflow: hidden; border: 2px solid #1E8E3E; background: #E8F5E9; }
    .photo img { position: absolute; }
    .avatar { width: 54px; height: 48px; padding-top: 22px; border: 2px solid #1E8E3E; background: #E8F5E9; color: #1E8E3E; font-size: 20px; font-weight: bold; text-align: center; line-height: 1; }
    .name { font-size: 11.5px; font-weight: bold; color: #0A2E73; word-wrap: break-word; line-height: 1.25; }
    .role { display: inline-block; background: #1E8E3E; color: #fff; padding: 1px 8px; border-radius: 8px; font-size: 8.5px; font-weight: bold; margin: 3px 0 4px; }
    .row { font-size: 8px; line-height: 1.5; word-wrap: break-word; }
    .k { color: #6b7280; }
    .qrbox { width: 76px; height: 76px; border: 1px solid #e5e7eb; background: #fff; }
    .foot { position: absolute; bottom: 0; left: 0; width: 323px; background: #F3F4F6; padding: 4px 10px; font-size: 7.5px; color: #374151; }
    .back .bodytxt { padding: 10px 12px; font-size: 8px; line-height: 1.5; }
    .hint { color: #6b7280; font-size: 9px; margin-bottom: 8px; }
</style>
</head>
<body>
<div class="hint">SmartBazars official ID card — print at 100% (actual size 85.6 × 54 mm), cut along the border and laminate.</div>

<div class="card">
    <table class="hd" cellspacing="0" cellpadding="0"><tr>
        <td width="170">@if($logo)<img src="{{ $logo }}" style="height:24px;background:#fff;padding:1px 4px">@else<span class="t">SmartBazars</span>@endif</td>
        <td align="right"><span class="t">{{ strtoupper($role) }} ID</span></td>
    </tr></table>
    <div class="orange"></div>
    <table class="bd" cellspacing="0" cellpadding="0"><tr>
        <td width="72" style="padding-left:10px">
            @if($photo)
                <div class="photo"><img src="{{ $photo['uri'] }}" style="left:{{ $photo['left'] }}px;top:{{ $photo['top'] }}px;width:{{ $photo['iw'] }}px;height:{{ $photo['ih'] }}px"></div>
            @else
                <div class="avatar">{{ $initials }}</div>
            @endif
        </td>
        <td style="padding:0 6px 0 4px">
            <div class="name" style="font-size:{{ mb_strlen($name) > 20 ? '9.5' : '11.5' }}px">{{ \Illuminate\Support\Str::limit($name, 30) }}</div>
            <span class="role">{{ $role }}</span>
            <div class="row"><span class="k">ID No.</span> <b>{{ $idNo }}</b></div>
            <div class="row"><span class="k">Code</span> <b>{{ $code }}</b></div>
            <div class="row"><span class="k">{{ $role === 'BSO' ? 'Reports to' : 'Vendor' }}</span> {{ \Illuminate\Support\Str::limit($reportsTo, 20) }}</div>
        </td>
        <td width="86" style="padding-right:10px" align="right"><div class="qrbox"><img src="{{ $qr }}" width="76" height="76"></div></td>
    </tr></table>
    <div class="foot">Valid till {{ $validTill }} · Scan QR to verify</div>
</div>

<div class="card back">
    <table class="hd" cellspacing="0" cellpadding="0"><tr>
        <td><span class="t">SmartBazars</span><br><span style="font-size:7.5px;color:#fff">Your Everyday Shopping, Made Smarter.</span></td>
    </tr></table>
    <div class="orange"></div>
    <div class="bodytxt">
        This card certifies that the holder is an authorised <b>{{ $role }}</b> of SmartBazars.
        The card is the property of SmartBazars and must be returned on request.
        If found, please return it to the nearest SmartBazars partner store.<br><br>
        <b>Mobile:</b> {{ $mobile }}<br><b>Issued:</b> {{ $issued }} &nbsp; <b>Valid till:</b> {{ $validTill }}<br>
        <b>Verify:</b> {{ $verifyLink }}
        <div style="margin-top:12px;border-top:1px solid #9ca3af;width:120px;padding-top:2px">Authorised Signatory</div>
    </div>
</div>
</body>
</html>
