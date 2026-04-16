<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\AntiPattern\Controller;

class SiteController extends Controller
{
    public function index(): void
    {
        echo 'Hello World';
    }
}
