<?php

declare(strict_types=1);

namespace App\Services;

final class CommissionCalculatorService
{
    private const THRESHOLD = 1000.0;
    private const RATE = 0.01;
    public function calculate(float $amount): float
    {
        if ($amount < self::THRESHOLD) {
            return 0.0;
        }
        return round($amount * self::RATE, 2);
    }
}
