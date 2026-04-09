<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'index'])
    ->name('site.index');

Route::get('/payment', [PaymentController::class, 'showPaymentForm'])
    ->name('payment.create');

Route::post('/payment', [PaymentController::class, 'store'])
    ->name('payment.store');

Route::get('/payments', [PaymentController::class, 'index'])
    ->name('payment.index');
