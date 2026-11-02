<?php

use App\Http\Controllers\Offline\OfflineController;
use Illuminate\Support\Facades\Route;

/*
| SmartBazars POS / offline customers — User Card creation, counter sales, BJO orders, card lookup.
| Loaded from routes/web.php:  require __DIR__ . '/offline-route.php';
*/

$common = function (string $who, bool $canSell, bool $canCreate) {
    $c = OfflineController::class;

    Route::get('/', [$c, 'home'])->defaults('who', $who)->name('home');
    Route::get('cards', [$c, 'cards'])->defaults('who', $who)->name('cards');
    Route::get('cards/{profile}', [$c, 'show'])->defaults('who', $who)->whereNumber('profile')->name('cards.show');
    Route::get('cards/{profile}/pdf', [$c, 'pdf'])->defaults('who', $who)->whereNumber('profile')->name('cards.pdf');
    Route::post('cards/{profile}/email', [$c, 'resend'])->defaults('who', $who)->whereNumber('profile')->name('cards.email');
    Route::get('cards/{profile}/file/{kind}', [$c, 'file'])->defaults('who', $who)->whereNumber('profile')->name('cards.file');

    if ($canCreate) {
        Route::get('cards/create', [$c, 'create'])->defaults('who', $who)->name('cards.create');
        Route::post('cards', [$c, 'store'])->defaults('who', $who)->middleware('throttle:30,1')->name('cards.store');
    }

    Route::get('lookup', [$c, 'lookup'])->defaults('who', $who)->name('lookup');
    Route::get('lookup/json', [$c, 'lookupJson'])->defaults('who', $who)->middleware('throttle:120,1')->name('lookup.json');

    if ($canSell) {
        Route::get('pos', [$c, 'pos'])->defaults('who', $who)->name('pos');
        Route::get('products', [$c, 'products'])->defaults('who', $who)->name('products');
        Route::post('sell', [$c, 'sell'])->defaults('who', $who)->middleware('throttle:60,1')->name('sell');
    }

    Route::get('orders', [$c, 'orders'])->defaults('who', $who)->name('orders');
    Route::get('orders/{order}', [$c, 'showOrder'])->defaults('who', $who)->whereNumber('order')->name('orders.show');
    Route::post('orders/{order}/cancel', [$c, 'cancel'])->defaults('who', $who)->whereNumber('order')->name('orders.cancel');
    if ($who === 'seller') {
        Route::post('orders/{order}/handover', [$c, 'handover'])->defaults('who', $who)->whereNumber('order')->name('orders.handover');
    }
};

// ---- Vendor panel ----
Route::prefix('seller/offline')->name('seller.offline.')
    ->middleware(['seller.query.token', 'validate.seller', 'kyc.complete:seller'])
    ->group(fn() => $common('seller', true, true));

// ---- BJO (worker) partner panel ----
Route::prefix('partner/offline')->name('partner.offline.')
    ->middleware(['auth', 'validate.partner', 'kyc.complete:partner', 'validate.partner:worker'])
    ->group(fn() => $common('worker', true, true));

// ---- Admin: monitor everything ----
Route::prefix('admin/smartbazars/offline')->name('admin.smartbazars.offline.')
    ->middleware(['auth', 'validate.admin', 'ensure.system.type', 'ensure.subscription.feature'])
    ->group(fn() => $common('admin', false, false));

// ---- Public: card link shared on WhatsApp / email (unguessable token) ----
Route::get('card/{token}', [OfflineController::class, 'publicCard'])->where('token', '[A-Za-z0-9]{40}')->name('offline.card.public');
Route::get('card/{token}/pdf', [OfflineController::class, 'publicPdf'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('offline.card.public.pdf');

// ---- BJO wallet recharge (partner) + approval (admin) ----
Route::prefix('partner/wallet')->name('partner.')
    ->middleware(['auth', 'validate.partner', 'kyc.complete:partner', 'validate.partner:worker'])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Partner\WalletController::class, 'index'])->name('wallet');
        Route::post('recharge', [\App\Http\Controllers\Partner\WalletController::class, 'recharge'])->name('wallet.recharge');
        Route::post('razorpay/order', [\App\Http\Controllers\Partner\WalletController::class, 'razorpayOrder'])->middleware('throttle:30,1')->name('wallet.razorpay.order');
        Route::post('razorpay/verify', [\App\Http\Controllers\Partner\WalletController::class, 'razorpayVerify'])->middleware('throttle:30,1')->name('wallet.razorpay.verify');
        Route::post('transfer', [\App\Http\Controllers\Partner\WalletController::class, 'transfer'])->name('wallet.transfer');
        Route::post('coins', [\App\Http\Controllers\Partner\WalletController::class, 'coins'])->middleware('throttle:20,1')->name('wallet.coins');
    });

Route::prefix('admin/smartbazars/wallet-recharges')->name('admin.smartbazars.')
    ->middleware(['auth', 'validate.admin', 'ensure.system.type', 'ensure.subscription.feature'])
    ->group(function () {
        $c = \App\Http\Controllers\Admin\SmartBazars\WalletRechargeController::class;
        Route::get('/', [$c, 'index'])->name('wallet-recharges');
        Route::post('settings', [$c, 'settings'])->name('wallet-recharges.settings');
        Route::post('{recharge}/approve', [$c, 'approve'])->whereNumber('recharge')->name('wallet-recharges.approve');
        Route::post('{recharge}/reject', [$c, 'reject'])->whereNumber('recharge')->name('wallet-recharges.reject');
        Route::get('{recharge}/proof', [$c, 'proof'])->whereNumber('recharge')->name('wallet-recharges.proof');
    });
