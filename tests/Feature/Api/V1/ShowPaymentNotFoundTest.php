<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Account;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lesson 7.3 — 404 and show: GET /api/v1/payments/{id}
 */
final class ShowPaymentNotFoundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
    }

    public function test_returns_404_when_payment_not_found(): void
    {
        $response = $this->getJson('/api/v1/payments/999999');

        $response
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_returns_200_with_payment_data_when_found(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create();

        /** @var Payment $payment */
        $payment = Payment::factory()->create([
            'account_id'  => $account->id,
            'amount'      => '500.00',
            'currency'    => 'EUR',
            'description' => 'Тестовий платіж',
            'status'      => 'processed',
            'commission'  => 0.00,
        ]);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'account_id',
                    'amount',
                    'currency',
                    'description',
                    'status',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.currency', 'EUR');
    }
}
