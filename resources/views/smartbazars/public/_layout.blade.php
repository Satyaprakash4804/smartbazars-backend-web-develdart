<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · SmartBazars</title>
    <link rel="icon" href="{{ asset('logos/smartbazars/favicon.png') }}">
    <style>
        :root { --g: #1E8E3E; --g2: #145A32; --b: #0A2E73; --o: #F57C00; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans', sans-serif; background: linear-gradient(160deg, #E8F5E9, #FFF8E1); min-height: 100vh; color: #1f2937; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; max-width: 440px; width: 100%; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,.12); overflow: hidden; }
        .bar { height: 8px; background: linear-gradient(90deg, var(--g), var(--o)); }
        .in { padding: 26px 24px 22px; text-align: center; }
        .logo { max-width: 210px; height: auto; }
        h1 { font-size: 22px; margin: 14px 0 6px; color: var(--b); }
        p { color: #4b5563; line-height: 1.55; margin: 8px 0; }
        .code { display: inline-block; font-size: 22px; letter-spacing: 3px; font-weight: 800; color: var(--b); background: #EEF2FF; border: 2px dashed #6366F1; border-radius: 12px; padding: 8px 18px; margin: 10px 0; }
        .btn { display: block; text-decoration: none; background: var(--g); color: #fff; padding: 13px; border-radius: 12px; font-weight: 700; margin-top: 10px; border: 0; width: 100%; font-size: 15px; cursor: pointer; }
        .btn.alt { background: #fff; color: var(--g2); border: 2px solid var(--g); }
        .ok { color: var(--g); } .bad { color: #b91c1c; }
        .muted { font-size: 12px; color: #6b7280; }
        .row { display: flex; justify-content: space-between; border-bottom: 1px solid #f1f1f1; padding: 8px 0; text-align: left; gap: 10px; }
        .row span:first-child { color: #6b7280; }
        .coin { width: 22px; height: 22px; vertical-align: -5px; }
    </style>
</head>
<body>
<div class="card"><div class="bar"></div><div class="in">
    @if(!empty($logo))<img class="logo" src="{{ $logo }}" alt="SmartBazars">@endif
    @yield('content')
</div></div>
</body>
</html>
