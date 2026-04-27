<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
    }

    public function test_delete_returns_204(): void
    {
        /** @var Account $account */
        $account = Account::factory()->create();

        $response = $this->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }
}
