{{-- SmartBazars User Card — responsive. Geometry is the 1508 x 976 artwork; every size scales with the card width (cqw). --}}
@php
    $fs = function (string $v) { $n = mb_strlen($v); return $n <= 24 ? 31 : ($n <= 32 ? 25 : 21); };
    $rows = [
        ['Name', \Illuminate\Support\Str::limit($card['name'], 40)],
        ['User Card No.', $card['cardNo']],
        ['Mobile No.', $card['mobile']],
        ['Ward / Panchayat', \Illuminate\Support\Str::limit($card['ward'], 40)],
        ['Block / District', \Illuminate\Support\Str::limit($card['block'] . ', ' . $card['district'], 44)],
        ['State', \Illuminate\Support\Str::limit($card['state'], 40)],
    ];
@endphp
<style>
    .sbc-wrap { container-type: inline-size; width: 100%; max-width: 760px; margin: 0 auto; }
    .sbc { --u: calc(100cqw / 1508); position: relative; width: 100%; aspect-ratio: 1508 / 976; border-radius: calc(var(--u) * 42);
           overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #eef6ff 55%, #f7fbff 100%);
           box-shadow: 0 calc(var(--u) * 24) calc(var(--u) * 60) rgba(10, 46, 115, .22); font-family: 'Segoe UI', Roboto, Arial, sans-serif; color: #0A2E73; }
    .sbc img.band { position: absolute; left: 0; width: 100%; display: block; }
    .sbc .hdr { top: 0; height: 25%; }
    .sbc .ftr { bottom: 0; height: 28.7%; }
    .sbc .abs { position: absolute; }
    .sbc .photo { left: 3.2%; top: 27%; width: 22.4%; height: 40.7%; border: calc(var(--u) * 6) solid #0b57c4; border-radius: calc(var(--u) * 26);
                  background: #fff; overflow: hidden; display: flex; align-items: flex-end; justify-content: center; box-sizing: border-box; }
    .sbc .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sbc .photo svg { width: 80%; height: 90%; }
    .sbc .pill { left: 28.1%; top: 26.1%; width: 43.9%; height: 9.9%; background: linear-gradient(90deg, #0b4fb5, #1a63cf); color: #fff; border-radius: calc(var(--u) * 26);
                 display: flex; align-items: center; padding-left: 4.5%; gap: calc(var(--u) * 28); font-weight: 800; letter-spacing: calc(var(--u) * 3); font-size: calc(var(--u) * 66); box-sizing: border-box; }
    .sbc .valid { right: 3.4%; top: 27.5%; text-align: right; font-size: calc(var(--u) * 27); color: #475569; line-height: 1.3; }
    .sbc .valid b { color: #0A2E73; display: block; font-size: calc(var(--u) * 31); }
    .sbc .row { left: 28.1%; width: 50%; display: flex; align-items: baseline; height: calc(var(--u) * 50); }
    .sbc .row .k { flex: 0 0 31%; font-weight: 600; font-size: calc(var(--u) * 31); white-space: nowrap; }
    .sbc .row .c { flex: 0 0 3%; font-weight: 700; font-size: calc(var(--u) * 31); }
    .sbc .row .v { flex: 1 1 auto; min-width: 0; border-bottom: calc(var(--u) * 2.5) dashed #6b86b8; color: #111c3a; font-weight: 700;
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-bottom: calc(var(--u) * 2); line-height: 1.15; }
    .sbc .qr { left: 79.7%; top: 38.5%; width: 16.9%; height: 25.4%; background: #fff; border: calc(var(--u) * 6) solid #0b57c4; border-radius: calc(var(--u) * 24); padding: 3%; box-sizing: border-box; }
    .sbc .qr svg, .sbc .qr img { width: 100%; height: 100%; display: block; }
    .sbc .scan { left: 79.7%; top: 64.3%; width: 16.9%; height: 5.6%; background: #0A2E73; color: #fff; border-radius: calc(var(--u) * 16);
                 display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: calc(var(--u) * 29); }
</style>
<div class="sbc-wrap">
    <div class="sbc" id="{{ $cardId ?? 'sb-user-card' }}">
        <img class="band hdr" src="{{ $card['hdrUri'] ?: asset('images/user-card/header.jpg') }}" alt="SmartBazars">
        <img class="band ftr" src="{{ $card['ftrUri'] ?: asset('images/user-card/footer.jpg') }}" alt="">

        <div class="abs pill">USER CARD
            <svg viewBox="0 0 24 24" style="height:72%" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7z" fill="#fff" stroke="none"/></svg>
        </div>
        <div class="abs valid">Valid upto<b>{{ $card['validUpto'] }}</b></div>

        <div class="abs photo">
            @if($card['photoUri'])
                <img src="{{ $card['photoUri'] }}" alt="{{ $card['name'] }}">
            @else
                <svg viewBox="0 0 100 110"><circle cx="50" cy="38" r="22" fill="#e5e7eb"/><path d="M8 110c0-30 18-44 42-44s42 14 42 44z" fill="#e5e7eb"/></svg>
            @endif
        </div>

        @foreach($rows as $i => [$k, $v])
            <div class="abs row" style="top: {{ 41.5 + $i * 5.12 }}%">
                <span class="k">{{ $k }}</span><span class="c">:</span>
                <span class="v" style="font-size: calc(var(--u) * {{ $fs($v) }})">{{ $v }}</span>
            </div>
        @endforeach

        <div class="abs qr">{!! $card['qrSvg'] !!}</div>
        <div class="abs scan">Scan &amp; Connect</div>
    </div>
</div>
