<?php

namespace App\DTO\Contracts;

interface BaseDTOInterface
{
    public static function fromArray(array $data): BaseDTOInterface;
    public function toArray(): array;
    public static function fromJson(string $json): BaseDTOInterface;
    public function toJson(): string;
}
