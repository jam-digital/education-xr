<?php

use App\Http\Controllers\Admin\BuildUploadController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureStaff;
use Illuminate\Support\Facades\Route;

// Лендінг VR-освіти: бібліотека уроків, пакети, форма зв'язку, оплата через LiqPay
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('uroky/{slug}', [LandingController::class, 'lesson'])->where('slug', '[a-z0-9-]+')->name('lesson');
Route::get('kontakty', [LandingController::class, 'page'])->defaults('page', 'contacts')->name('page.contacts');
Route::get('oferta', [LandingController::class, 'page'])->defaults('page', 'offer')->name('page.offer');
Route::get('polityka-konfidentsiinosti', [LandingController::class, 'page'])->defaults('page', 'privacy')->name('page.privacy');
Route::get('sitemap.xml', [LandingController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [LandingController::class, 'robots'])->name('robots');
Route::post('contact', [LandingController::class, 'contact'])->name('contact')->middleware('throttle:10,1');
Route::post('buy', [LandingController::class, 'buy'])->name('buy')->middleware('throttle:10,1');
Route::get('pay/{payment}', [LandingController::class, 'pay'])->name('pay.show');
Route::get('pay/{payment}/result', [LandingController::class, 'result'])->name('pay.result');
Route::post('pay/liqpay/callback', [LandingController::class, 'callback'])->name('pay.callback');

// Кабінет закладу освіти (акаунт школи з адмінки)
Route::prefix('cabinet')->name('cabinet')->group(function () {
    Route::get('login', [CabinetController::class, 'loginForm'])->name('.login');
    Route::post('login', [CabinetController::class, 'login'])->middleware('throttle:10,1')->name('.login.submit');
    Route::middleware(EnsureClient::class)->group(function () {
        Route::get('/', [CabinetController::class, 'index']);
        Route::get('devices', [CabinetController::class, 'devices'])->name('.devices');
        Route::get('payments', [CabinetController::class, 'payments'])->name('.payments');
        Route::get('payments/{payment}/{kind}', [CabinetController::class, 'document'])->whereIn('kind', ['invoice', 'act'])->name('.document');
        Route::get('settings', [CabinetController::class, 'settings'])->name('.settings');
        Route::post('settings/pin', [CabinetController::class, 'updatePin'])->name('.pin');
        Route::post('settings/password', [CabinetController::class, 'updatePassword'])->middleware('throttle:10,1')->name('.password');
        Route::post('logout', [CabinetController::class, 'logout'])->name('.logout');
    });
});

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
