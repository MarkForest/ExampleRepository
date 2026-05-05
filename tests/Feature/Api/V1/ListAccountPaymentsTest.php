<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Account;
use App\Models\Payment;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListAccountPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
    }

    public function test_returns_paginated_payments_for_account(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create();

        Payment::factory()->count(2)->create([
            'account_id' => $account->id,
            'status'     => 'processed',
            'commission' => 0.00,
        ]);

        $response = $this->getJson("/api/v1/accounts/{$account->id}/payments?per_page=10");

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'account_id', 'amount', 'currency', 'description', 'status', 'created_at'],
                ],
                'links',
                'meta',
            ]);

        $response->assertJsonPath('meta.total', 2);
    }

    public function test_returns_404_when_account_missing(): void
    {
        $response = $this->getJson('/api/v1/accounts/999999/payments');

        $response->assertStatus(404);
    }

    public function test_returns_422_when_per_page_exceeds_max(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create();

        $response = $this->getJson("/api/v1/accounts/{$account->id}/payments?per_page=101");

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['per_page']]);
    }
}
