<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AntiPattern\Controller;
use Illuminate\Http\JsonResponse;

final class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data'    => [],
            'message' => 'TODO: вернуть валюты + Cache-Control: public, max-age=3600',
        ]);
    }
}
