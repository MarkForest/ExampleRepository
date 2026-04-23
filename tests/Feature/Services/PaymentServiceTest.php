<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\DTO\Api\V1\CreatePaymentDTO;
use App\Models\Account;
use App\Models\Payment;
use App\Services\Api\V1\PaymentService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_process_payment_creates_payment_when_balance_is_sufficient(): void
    {
        Event::fake();

        // Arrange
        $paymentRepository
            = Mockery::mock(PaymentRepositoryInterface::class);
        $accountRepository
            = Mockery::mock(AccountRepositoryInterface::class);

        $accountId = 1;
        $amount = 1500.0;

        $account = new Account();
        $account->id = $accountId;
        $account->balance = 2000.0;

        $accountRepository
            ->shouldReceive('findById')
            ->once()
            ->with($accountId)
            ->andReturn($account);

        $accountRepository
            ->shouldReceive('decrementBalance')
            ->once()
            ->with($accountId, Mockery::type('string'))
            ->andReturnNull();

        $expectedData = [
            'account_id' => $accountId,
            // В PaymentService комиссионные прибавляются к amount через MoneyObject.
            // При amount=1500 и commission rate=0.01 итоговый amount становится 1515.
            'amount' => '1515',
            'currency' => 'UAH',
            // В PaymentService сохраняется именно rate (0.01), а не сумма комиссии.
            'commission' => 0.01,
            'description' => null,
            'status' => 'completed',
        ];

        $paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $data) use ($expectedData): bool {
                foreach ($expectedData as $key => $value) {
                    if (!array_key_exists($key, $data)) {
                        return false;
                    }
                    if ($data[$key] !== $value) {
                        return false;
                    }
                }

                return true;
            }))
            ->andReturn(new Payment(array_merge($expectedData, ['id' => $accountId])));


        $service = new PaymentService(
            $paymentRepository,
            $accountRepository
        );

        $createPaymentDTO = CreatePaymentDTO::fromArray([
            'account_id' => $accountId,
            'amount' => (string) $amount,
            'currency' => 'UAH',
        ]);

        // Act
        $payment = $service->processPayment($createPaymentDTO);

        // Assert
        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(1515.0, (float) $payment->amount);
    }

    public function test_process_payment_throws_exception_when_balance_is_insufficient(): void
    {
        $paymentRepository
            = Mockery::mock(PaymentRepositoryInterface::class);
        $accountRepository
            = Mockery::mock(AccountRepositoryInterface::class);

        $accountId = 1;
        $amount = 1500.0;

        $account = new \App\Models\Account();
        $account->id = $accountId;
        $account->balance = 100.0;

        $accountRepository
            ->shouldReceive('findById')
            ->once()
            ->with($accountId)
            ->andReturn($account);

        $paymentRepository
            ->shouldReceive('create')
            ->never();

        $accountRepository
            ->shouldReceive('decrementBalance')
            ->never();

        $service = new PaymentService(
            $paymentRepository,
            $accountRepository
        );

        $createPaymentDTO = CreatePaymentDTO::fromArray([
            'account_id' => $accountId,
            'amount' => (string) $amount,
            'currency' => 'UAH',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds.');

        $service->processPayment($createPaymentDTO);
    }
}
