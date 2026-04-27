<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowAccountNotFoundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
    }

    public function test_returns_404_when_account_not_found(): void
    {
        $response = $this->getJson('/api/v1/accounts/999999');

        $response
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_returns_200_when_account_exists(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create(['balance' => 750.25]);

        $response = $this->getJson("/api/v1/accounts/{$account->id}");

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'balance',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.balance', '750.25');
    }
}
