<?php

namespace App\Domain\Debt;

use InvalidArgumentException;

readonly class Money
{
    private int $amountInCents;

    private function __construct(int $amountInCents)
    {
        $this->amountInCents = $amountInCents;
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimal(string|float|int $amount): self
    {
        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        $amountStr = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amountStr)) {
            throw new InvalidArgumentException("Invalid monetary amount string: {$amountStr}");
        }

        $cents = (int) round(((float) $amountStr) * 100);

        return new self($cents);
    }

    public function toDecimal(): string
    {
        return number_format($this->amountInCents / 100, 2, '.', '');
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents;
    }
}
