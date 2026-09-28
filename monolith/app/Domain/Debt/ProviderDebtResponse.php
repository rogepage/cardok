<?php

namespace App\Domain\Debt;

readonly class ProviderDebtResponse
{
    /**
     * @param array<int, Debt> $debts
     */
    public function __construct(
        public string $plate,
        public array $debts,
        public ?string $provider = null,
    ) {}
}
