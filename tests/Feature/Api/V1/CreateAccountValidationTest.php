<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateAccountValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_422_when_balance_is_missing(): void
    {
        $response = $this->postJson('/api/v1/accounts', []);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'balance',
                ],
            ]);
    }

    public function test_returns_422_when_balance_is_negative(): void
    {
        $response = $this->postJson('/api/v1/accounts', [
            'balance' => '-1.00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['balance'],
            ]);
    }
}
