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
            $amountStr = number_format($amount, 2, '.', '');
        } else {
            $amountStr = trim((string) $amount);
        }

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amountStr)) {
            throw new InvalidArgumentException("Invalid monetary amount string: {$amountStr}");
        }

        $isNegative = str_starts_with($amountStr, '-');
        $clean = ltrim($amountStr, '-');

        $parts = explode('.', $clean);
        $units = (int) $parts[0];
        $decimals = isset($parts[1]) ? str_pad(substr($parts[1], 0, 2), 2, '0') : '00';
        $cents = ($units * 100) + (int) $decimals;

        return new self($isNegative ? -$cents : $cents);
    }

    public function toDecimal(): string
    {
        $isNegative = $this->amountInCents < 0;
        $abs = abs($this->amountInCents);
        $units = intdiv($abs, 100);
        $cents = $abs % 100;

        return sprintf('%s%d.%02d', $isNegative ? '-' : '', $units, $cents);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function add(Money $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function subtract(Money $other): self
    {
        return new self($this->amountInCents - $other->amountInCents);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents;
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }
}
