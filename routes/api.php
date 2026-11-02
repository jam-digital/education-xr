<?php

use App\Http\Controllers\Api\VrController;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\DeviceToken;
use Illuminate\Support\Facades\Route;

// API клієнта «VR-освіта» у шкільних окулярах (ТЗ розділ 7). Опис: docs/API.md
Route::prefix('v1/vr')->name('api.vr.')->middleware('throttle:120,1')->group(function () {
    Route::get('public-key', [VrController::class, 'publicKey'])->name('public-key');

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('device/auth', [VrController::class, 'auth'])->name('auth');
        Route::post('device/login', [VrController::class, 'login'])->name('login');
        Route::post('device/pair', [VrController::class, 'pair'])->name('pair');
    });

    Route::get('files', [VrController::class, 'file'])->middleware('signed')->name('file');

    Route::middleware(DeviceToken::class)->group(function () {
        Route::post('device/heartbeat', [VrController::class, 'heartbeat'])->name('heartbeat');
        Route::post('device/logout', [VrController::class, 'logout'])->name('logout');
        Route::get('library', [VrController::class, 'library'])->name('library');
        Route::get('lessons/{lesson}/download', [VrController::class, 'download'])->name('download');
        Route::post('session/start', [VrController::class, 'start'])->name('start');
        Route::post('session/end', [VrController::class, 'end'])->name('end');
        Route::post('offline/sync', [VrController::class, 'offlineSync'])->name('offline-sync');
    });
});

// Сповіщення платіжного шлюзу (ТЗ розділ 7); LiqPay також шле на /pay/liqpay/callback
Route::post('v1/webhooks/payment', [LandingController::class, 'callback'])->name('api.webhooks.payment');
