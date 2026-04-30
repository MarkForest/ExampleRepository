<?php

declare(strict_types=1);

namespace App\Data\Api\V1;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Symfony\Contracts\Service\Attribute\Required;

class CreatePaymentData extends Data
{
    /*
     *      'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,completed,failed',
            'currency' => 'required|in:USD,EUR,UAH',
     * */
    public function __construct(
        #[Required]
        public ?int $userId,
        #[Required, Numeric, Min(0)]
        public ?float $amount,
        #[Required]
        public ?string $status,
        #[Required]
        public ?string $currency
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'user_id' => 'int|exists:users,id',
            'currency' => 'in:USD,EUR,UAH',
            'status' => 'in:pending,completed,failed',
        ];
    }

    public static function messages(...$args): array
    {
        return [];
    }

    public static function attributes(...$args): array
    {
        return [];
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }
}
