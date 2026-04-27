<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lesson 7.3 — Validation errors: POST /api/v1/payments returns 422
 */
final class CreatePaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_422_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/payments', []);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'account_id',
                    'amount',
                    'currency',
                ],
            ]);
    }

    public function test_returns_422_when_amount_is_negative(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'account_id' => null,
            'amount'     => '-10.00',
            'currency'   => 'BTC',
            'description' => 'Test',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'account_id',
                    'amount',
                    'currency',
                ],
            ]);
    }

    public function test_returns_422_when_account_does_not_exist(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'account_id' => 999999,
            'amount'     => '100.00',
            'currency'   => 'USD',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['account_id'],
            ]);
    }

    public function test_returns_422_when_currency_is_invalid(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'account_id' => 1,
            'amount'     => '100.00',
            'currency'   => 'BTC',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['currency'],
            ]);
    }
}
