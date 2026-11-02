<?php

use App\Http\Controllers\Partner\ApplyController;
use App\Http\Controllers\Partner\KycController;
use App\Http\Controllers\Partner\PartnerController;
use Illuminate\Support\Facades\Route;

/*
| SmartBazars Partner panel — Agents & Vendor workers
| URL: /partner
*/
Route::prefix('partner')->name('partner.')->group(function () {
    Route::middleware(['guest'])->group(function () {
        Route::get('/', [PartnerController::class, 'showLogin']);
        Route::get('login', [PartnerController::class, 'showLogin'])->name('login');
        Route::post('login', [PartnerController::class, 'login'])->name('login.post')->middleware('throttle:10,1');

        // Public "Apply now" (agents & workers) — documents + joining fee
        Route::get('apply', [ApplyController::class, 'form'])->name('apply');
        Route::post('apply', [ApplyController::class, 'submit'])->name('apply.submit')->middleware('throttle:10,1');
    });

    Route::middleware(['auth', 'validate.partner', 'kyc.complete:partner'])->group(function () {
        Route::get('logout', [PartnerController::class, 'logout'])->name('logout');
        Route::get('kyc', [KycController::class, 'index'])->name('kyc');
        Route::post('kyc', [KycController::class, 'store'])->name('kyc.store');
        Route::get('dashboard', [PartnerController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [PartnerController::class, 'profile'])->name('profile.index');
        Route::post('profile/password', [PartnerController::class, 'updatePassword'])->name('profile.password');
        Route::get('profile/photo', [PartnerController::class, 'photo'])->name('photo');
        Route::post('profile/photo', [PartnerController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('profile/photo', [PartnerController::class, 'removePhoto'])->name('profile.photo.remove');

        Route::get('id-card', [PartnerController::class, 'idCard'])->name('id-card');
        Route::get('id-card/pdf', [PartnerController::class, 'idCardPdf'])->name('id-card.pdf');
        Route::get('daily-report', [PartnerController::class, 'reports'])->name('reports.daily');
        Route::post('daily-report', [PartnerController::class, 'saveReport'])->name('reports.daily.save');
        Route::get('earnings', [PartnerController::class, 'earnings'])->name('earnings');
        Route::post('earnings/withdraw', [PartnerController::class, 'requestWithdrawal'])->name('earnings.withdraw');

        // Agents only
        Route::middleware('validate.partner:agent')->group(function () {
            Route::get('team', [PartnerController::class, 'team'])->name('team');
            Route::get('team/added', [PartnerController::class, 'teamAdded'])->name('team.added');
            Route::get('requests', [PartnerController::class, 'requests'])->name('requests');
            Route::post('requests', [PartnerController::class, 'storeRequest'])->name('requests.store');
        });

        // Workers only
        Route::middleware('validate.partner:worker')->group(function () {
            Route::get('customers', [PartnerController::class, 'customers'])->name('customers');
            Route::post('customers', [PartnerController::class, 'storeCustomer'])->name('customers.store');
        });
    });
});
