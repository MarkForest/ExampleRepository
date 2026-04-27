<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
    }

    public function test_index_returns_paginated_accounts(): void
    {
        Account::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/accounts');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'balance', 'created_at'],
                ],
                'links',
                'meta',
            ]);
    }
}
