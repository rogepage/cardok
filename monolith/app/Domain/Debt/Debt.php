<?php

namespace App\Domain\Debt;

use Carbon\CarbonImmutable;

readonly class Debt
{
    public function __construct(
        public string $type,
        public Money $amount,
        public CarbonImmutable $dueDate,
    ) {}

    public function equals(Debt $other): bool
    {
        return $this->type === $other->type
            && $this->amount->equals($other->amount)
            && $this->dueDate->toDateString() === $other->dueDate->toDateString();
    }
}
