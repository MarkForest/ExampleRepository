<?php

namespace Tests\Feature\Services;

use App\DTO\Payment\CreatePaymentDTO;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Mocks\AccountRepositoryMock;
use Tests\Mocks\PaymentRepositoryMock;
use Tests\TestCase;

class PaymentServiceWithMocksTest extends TestCase
{
    public function test_process_payment_throws_exception_when_balance_is_insufficient(): void
    {
        Event::fake();

        $accountId = 1;
        $amount = 1500.0;

        AccountRepositoryMock::seedAccount($accountId, 100.0);

        /** @var PaymentService $service */
        $service = App::make(PaymentService::class);

        $createPaymentDTO = CreatePaymentDTO::fromArray([
            'account_id' => $accountId,
            'amount' => (string)$amount,
            'currency' => 'UAH',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds.');

        try {
            $service->processPayment($createPaymentDTO);
        } finally {
            self::assertSame([], PaymentRepositoryMock::createdPayloads());
            self::assertSame([], AccountRepositoryMock::decrementCalls());
        }
    }

    public function test_process_payment_creates_payment_when_balance_is_sufficient(): void
    {
        Event::fake();

        $accountId = 1;
        $amount = 1500.0;

        AccountRepositoryMock::seedAccount($accountId, 2000.0);

        /** @var PaymentService $service */
        $service = $this->app->make(PaymentService::class);

        $createPaymentDTO = CreatePaymentDTO::fromArray([
            'account_id' => $accountId,
            'amount' => (string)$amount,
            'currency' => 'UAH',
        ]);

        $payment = $service->processPayment($createPaymentDTO);

        self::assertNotNull($payment);
        self::assertSame(1515.0, (float)$payment->amount);

        $createdPayloads = PaymentRepositoryMock::createdPayloads();
        self::assertCount(1, $createdPayloads);
        self::assertSame($accountId, $createdPayloads[0]['account_id']);
        self::assertSame('1515', $createdPayloads[0]['amount']);
        self::assertSame(0.01, $createdPayloads[0]['commission']);
        self::assertSame('completed', $createdPayloads[0]['status']);

        $decrementCalls = AccountRepositoryMock::decrementCalls();
        self::assertCount(1, $decrementCalls);
        self::assertSame($accountId, $decrementCalls[0]['id']);
        self::assertSame('1515.01', $decrementCalls[0]['amount']);
    }
}
