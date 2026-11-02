<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>SmartBazars User Card — {{ $card['cardNo'] }}</title>
    <style>
        body { margin: 0; min-height: 100vh; background: linear-gradient(160deg, #eaf2ff, #ffffff); font-family: 'Segoe UI', Roboto, Arial, sans-serif; color: #0A2E73; }
        main { max-width: 820px; margin: 0 auto; padding: 24px 14px 40px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 22px; }
        .btn { display: inline-block; padding: 12px 22px; border-radius: 10px; background: #0A2E73; color: #fff; text-decoration: none; font-weight: 700; border: 0; font-size: 15px; cursor: pointer; }
        .btn.alt { background: #F57C00; }
        p.note { text-align: center; color: #475569; font-size: 14px; margin-top: 20px; }
    </style>
</head>
<body>
<main>
    @include('offline.partials.card', ['card' => $card, 'cardId' => 'sb-public-card'])
    <div class="actions">
        <a class="btn" href="{{ route('offline.card.public.pdf', $token) }}">Download PDF</a>
        <button class="btn alt" onclick="window.print()">Print</button>
    </div>
    <p class="note">Show this card or scan its QR at any SmartBazars vendor store. Earn cashback coins on every purchase — no delivery charge on store purchases.</p>
</main>
<style>@media print { .actions, .note { display: none; } body { background: #fff; } }</style>
</body>
</html>
