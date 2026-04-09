<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ProductApiController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductApiController::class, 'index']);
