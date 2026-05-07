<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\AntiPattern\Controller;
use App\Models\Payment;

class SiteController extends Controller
{
    public function index(): void
    {
        $payments = Payment::query()->limit(10)->get();
        echo 'Hello World';
    }
}
