<?php

declare(strict_types=1);

namespace App\DTO\Api\V1;

use App\Contracts\DTO\BaseDTOInterface;

final class CreateAccountDTO implements BaseDTOInterface
{
    public function __construct(
        private readonly string $balance
    ) {}

    public static function fromArray(array $data): BaseDTOInterface
    {
        $balance = $data['balance'] ?? '0';

        return new self((string) $balance);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'balance' => $this->balance,
        ];
    }

    public static function fromJson(string $json): BaseDTOInterface
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function getBalance(): string
    {
        return $this->balance;
    }
}
