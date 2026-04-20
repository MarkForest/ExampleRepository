<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CommissionCalculatorService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CommissionCalculatorServiceTest extends TestCase
{
    private CommissionCalculatorService $commissionCalculatorService;
    protected function setUp(): void
    {
        parent::setUp();
        var_dump('create new instance');
        Log::info('create new instance');
        $this->commissionCalculatorService = App::make(CommissionCalculatorService::class);
    }

    public function test_commission_is_zero_when_amount_below_threshold(): void
    {
        $this->assertSame(
            0.0,
            $this->commissionCalculatorService->calculate(500.0)
        );
    }

    public function test_commission_is_one_percent_when_amount_at_or_above_threshold(): void
    {
        $this->assertSame(10.0, $this->commissionCalculatorService->calculate(1000.0));
        $this->assertSame(15.5, $this->commissionCalculatorService->calculate(1550.0));
    }

    public function test_negative_amounts_also_have_zero_commission(): void
    {
        $this->assertSame(0.0, $this->commissionCalculatorService->calculate(-1.0));
        $this->assertSame(0.0, $this->commissionCalculatorService->calculate(-500.0));
    }
}
