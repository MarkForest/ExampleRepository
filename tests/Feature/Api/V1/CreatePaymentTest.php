<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Account;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lesson 7.3 — Happy path: POST /api/v1/payments returns 201
 */
final class CreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Feature tests use the real repository to actually persist to SQLite
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
    }

    public function test_can_create_payment_with_valid_data(): void
    {
        // Arrange
        /** @var Account $account */
        $account = Account::factory()->create(['balance' => 1000.00]);

        $payload = [
            'account_id'  => $account->id,
            'amount'      => '250.00',
            'currency'    => 'USD',
            'description' => 'Оплата сервісу',
        ];

        // Act
        $response = $this->postJson('/api/v1/payments', $payload);

        // Assert
        $response
            ->assertStatus(201)
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
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.account_id', $account->id)
            ->assertJsonPath('data.status', 'processed');

        $this->assertDatabaseHas('payments', [
            'account_id' => $account->id,
            'amount'     => '250.00',
            'currency'   => 'USD',
        ]);
    }

    public function test_can_create_payment_without_description(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create();

        $response = $this->postJson('/api/v1/payments', [
            'account_id' => $account->id,
            'amount'     => '100.00',
            'currency'   => 'UAH',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.description', null);
    }
}
