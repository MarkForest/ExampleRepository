<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    Route::middleware('throttle:api-without-cache')->group(function () {
        Route::get('accounts/{account}/payments', [AccountController::class, 'payments'])
            ->name('accounts.payments.index');
        Route::get('accounts/{account}/payments-fat', [AccountController::class, 'paymentsFat'])
            ->name('accounts.payments.fat');
    });

    Route::middleware('throttle:api-with-cache')->group(function () {
        Route::get('accounts/{account}/payments-cached', [AccountController::class, 'paymentsCached'])
            ->name('accounts.payments.cached');
        Route::post('payments/demo-fail', [PaymentController::class, 'demoFail'])->name('payments.demo-fail');
    });

    Route::middleware('throttle:reports')->group(function () {
        Route::post('reports/account-statement', [ReportsController::class, 'generateAccountStatement'])
            ->name('reports.account-statement');
    });


    Route::get('currencies', [CurrencyController::class, 'index'])
        ->name('currencies.index');



    Route::apiResource('payments', PaymentController::class)->except(['update']);
    Route::apiResource('accounts', AccountController::class)->except(['update']);
    Route::get('/test-sentry', static function (): void {
        throw new RuntimeException('Test Sentry integration - Wake up Neo');
    })->name('test.sentry');

    Route::get('/sentry-test', function () {
        throw new Exception('Test Sentry error - Wake up Neo');
    })->name('sentry.test');

});
