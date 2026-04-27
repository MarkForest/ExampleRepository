<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'balance' => $this->faker->randomFloat(2, 100, 50000),
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }
}
