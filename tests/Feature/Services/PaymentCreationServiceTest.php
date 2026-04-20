<?php

namespace Tests\Feature\Services;

use App\Services\PaymentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentCreationService $paymentCreationService;
    public function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'commission')) {
            $this->artisan('migrate');
        }

        $this->paymentCreationService = App::make(PaymentCreationService::class);
    }

    public function test_payment_is_persisted_with_expected_fields(): void
    {
        // Arrange
        $accountId = 1;
        // Тут можна попередньо створити Account, якщо міграції цього вимагають
        // Act
        $payment = $this->paymentCreationService->createPaymentWithCommission(
            $accountId,
            1500.0,
            'UAH'
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'account_id' => $accountId,
            'amount' => 1500.0,
            'currency' => 'UAH',
            'commission' => 15.0,
            'status' => 'created',
        ]);
    }
}
