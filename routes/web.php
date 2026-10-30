<?php

use App\Http\Controllers\Admin\BuildUploadController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\EnsureStaff;
use Illuminate\Support\Facades\Route;

// Лендінг VR-освіти: бібліотека уроків, пакети, форма зв'язку, оплата через LiqPay
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('contact', [LandingController::class, 'contact'])->name('contact')->middleware('throttle:10,1');
Route::post('buy', [LandingController::class, 'buy'])->name('buy')->middleware('throttle:10,1');
Route::get('pay/{payment}', [LandingController::class, 'pay'])->name('pay.show');
Route::get('pay/{payment}/result', [LandingController::class, 'result'])->name('pay.result');
Route::post('pay/liqpay/callback', [LandingController::class, 'callback'])->name('pay.callback');

// Службові маршрути адмінки (сесія Filament, лише персонал)
Route::middleware(['auth', EnsureStaff::class])->prefix('admin/x')->name('admin.')->group(function () {
    Route::get('payments/{payment}/{kind}', [DocumentController::class, 'payment'])->whereIn('kind', ['invoice', 'act'])->name('payment.document');
    Route::get('reports/finance', [DocumentController::class, 'report'])->name('report.finance');

    // Білди VR-уроків завантажують суперадмін і методист (ТЗ 3.1, 3.3)
    Route::middleware(EnsureStaff::class.':super_admin,methodist_manager')->prefix('uploads')->name('uploads.')->group(function () {
        Route::post('init', [BuildUploadController::class, 'init'])->name('init');
        Route::post('part-url', [BuildUploadController::class, 'partUrl'])->name('part-url');
        Route::post('chunk', [BuildUploadController::class, 'chunk'])->name('chunk');
        Route::post('complete', [BuildUploadController::class, 'complete'])->name('complete');
        Route::post('abort', [BuildUploadController::class, 'abort'])->name('abort');
    });
});
