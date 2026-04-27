<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Repositories\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
    }

    public function test_can_create_account_with_valid_balance(): void
    {
        $response = $this->postJson('/api/v1/accounts', [
            'balance' => '1500.50',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'balance',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.balance', '1500.50');

        $this->assertDatabaseHas('accounts', [
            'balance' => '1500.50',
        ]);
    }

    public function test_can_create_account_with_zero_balance(): void
    {
        $response = $this->postJson('/api/v1/accounts', [
            'balance' => '0',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.balance', '0.00');
    }
}
