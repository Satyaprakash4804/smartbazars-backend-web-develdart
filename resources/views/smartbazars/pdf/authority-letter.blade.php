<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 10.5px; margin: 0; }
    .page { padding: 26px 44px 20px; position: relative; }
    .band { height: 10px; background: #1E8E3E; }
    .band2 { height: 4px; background: #F57C00; }
    .head td { vertical-align: middle; }
    .brand { font-size: 22px; font-weight: bold; color: #145A32; letter-spacing: .3px; }
    .tag { color: #6b7280; font-size: 10px; }
    .meta { text-align: right; font-size: 10.5px; color: #374151; line-height: 1.6; }
    h1 { text-align: center; font-size: 16px; letter-spacing: 2px; color: #0A2E73; margin: 14px 0 2px; }
    .sub { text-align: center; color: #6b7280; font-size: 9.5px; margin-bottom: 10px; }
    .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 14px; background: #f9fafb; }
    .box td { padding: 3px 6px; vertical-align: top; }
    .k { color: #6b7280; width: 120px; }
    p { line-height: 1.5; text-align: justify; margin: 7px 0; }
    ul { margin: 4px 0 4px 18px; padding: 0; line-height: 1.45; }
    .qr { text-align: center; border: 1px dashed #1E8E3E; border-radius: 6px; padding: 8px; }
    .code { font-size: 15px; font-weight: bold; color: #0A2E73; letter-spacing: 1.5px; }
    .sign td { vertical-align: bottom; }
    .seal { width: 96px; height: 96px; border: 3px double #1E8E3E; border-radius: 48px; text-align: center; color: #1E8E3E; font-weight: bold; font-size: 10px; padding-top: 28px; box-sizing: border-box; }
    .foot { position: fixed; bottom: 0; left: 0; right: 0; }
    .foot .txt { text-align: center; font-size: 9px; color: #6b7280; padding: 6px 0 8px; }
</style>
</head>
<body>
<div class="band"></div><div class="band2"></div>
<div class="page">
    <table width="100%" class="head">
        <tr>
            <td width="60%">
                @if($logo)<img src="{{ $logo }}" style="height:54px">@else<span class="brand">SmartBazars</span>@endif
                <div class="tag">Your Everyday Shopping, Made Smarter.</div>
            </td>
            <td class="meta">
                <b>Letter No:</b> {{ $letterNo }}<br>
                <b>Date:</b> {{ $issued }}<br>
                <b>Valid till:</b> {{ $validTill }}
            </td>
        </tr>
    </table>

    <h1>LETTER OF AUTHORISATION</h1>
    <div class="sub">Authorised Vendor / Partner Store of SmartBazars</div>

    <p>To,<br><b>{{ $owner }}</b><br>{{ $name }}<br>{{ $address }}</p>

    <p><b>Subject:</b> Authorisation to operate as a SmartBazars Vendor</p>

    <p>Dear {{ $owner }},</p>
    <p>
        We are pleased to inform you that, after verification of your business details and documents,
        <b>{{ $name }}</b> has been approved and is hereby <b>authorised to operate as an official Vendor (Partner Store)
        of SmartBazars</b> on the SmartBazars app and website for the period mentioned above.
    </p>

    <table width="100%" class="box" cellspacing="0">
        <tr><td class="k">Vendor / Store</td><td><b>{{ $name }}</b></td><td rowspan="6" width="150" class="qr">
            <img src="{{ $qr }}" width="128" height="128"><br>
            <span class="code">{{ $code }}</span><br><span style="font-size:8.5px;color:#6b7280">Scan to join SmartBazars</span>
        </td></tr>
        <tr><td class="k">Proprietor</td><td>{{ $owner }}</td></tr>
        <tr><td class="k">Vendor Code</td><td><b>{{ $code }}</b></td></tr>
        <tr><td class="k">GSTIN / Tax No.</td><td>{{ $store?->tax_number ?: '—' }}</td></tr>
        <tr><td class="k">Contact</td><td>{{ $seller->user->mobile ?? '' }} · {{ $seller->user->email ?? '' }}</td></tr>
        <tr><td class="k">Onboarded by</td><td>{{ $agentName ?: 'SmartBazars Head Office' }}</td></tr>
    </table>

    <p>Under this authorisation the vendor may:</p>
    <ul>
        <li>list and sell products to SmartBazars customers within the assigned delivery zone;</li>
        <li>display the SmartBazars name, logo and this QR code at the store and in local promotion;</li>
        <li>refer customers using the vendor code <b>{{ $code }}</b> and earn commission as per the SmartBazars programme;</li>
        <li>appoint workers through the Vendor Panel, subject to SmartBazars approval.</li>
    </ul>
    <p>
        The vendor shall follow SmartBazars policies on pricing, product quality, hygiene, delivery and customer service.
        SmartBazars may suspend or withdraw this authorisation in case of misuse or policy violation.
        This letter can be verified online at <b>{{ $verifyLink }}</b>.
    </p>
    <p>We welcome you to the SmartBazars family and wish you great success.</p>

    <table width="100%" class="sign" style="margin-top:8px">
        <tr>
            <td width="60%">
                <div style="height:26px"></div>
                <b>Authorised Signatory</b><br>SmartBazars<br><span style="color:#6b7280;font-size:10px">This is a system-generated document and is valid without a physical signature.</span>
            </td>
            <td align="right"><table cellspacing="0" style="border:3px double #1E8E3E;border-radius:10px"><tr><td style="padding:10px 14px;text-align:center;color:#1E8E3E;font-weight:bold;font-size:10px;line-height:1.5">SMARTBAZARS<br>VERIFIED VENDOR<br><span style="font-size:8px;color:#374151">{{ $issued }}</span></td></tr></table></td>
        </tr>
    </table>
</div>
<div class="foot"><div class="txt">SmartBazars · Authorised Vendor Letter {{ $letterNo }} · Verify: {{ $verifyLink }}</div><div class="band2"></div><div class="band"></div></div>
</body>
</html>
