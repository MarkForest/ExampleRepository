<?php

declare(strict_types=1);

namespace App\DTO\Api\V1;

/**
 * Query parameters for GET /api/v1/accounts/{account}/payments (Урок 9.1).
 */
final readonly class AccountPaymentsQueryDTO
{
    public function __construct(
        private int $perPage
    ) {}

    /**
     * @param array{page?: int, per_page?: int} $validated
     */
    public static function fromValidated(array $validated): self
    {
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 20;

        return new self($perPage);
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }
}
