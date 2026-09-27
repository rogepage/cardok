<?php

namespace App\Domain\Payment\Services;

use App\Domain\Debt\Money;
use App\Domain\Payment\Contracts\PaymentMethodCalculatorInterface;
use App\Domain\Payment\DTO\CreditCardInstallment;
use App\Domain\Payment\DTO\CreditCardOption;
use InvalidArgumentException;

class CreditCardCalculator implements PaymentMethodCalculatorInterface
{
    /**
     * Allowed installments in Phase 6: 1x, 6x, 12x.
     */
    public const array ALLOWED_INSTALLMENTS = [1, 6, 12];

    /**
     * Monthly interest rate for Price table: 2.5% (0.025).
     */
    private const float MONTHLY_RATE = 0.025;

    public function getMethodKey(): string
    {
        return 'cartao_credito';
    }

    /**
     * Calculates credit card installment options for the given base amount.
     */
    public function calculate(Money $baseAmount): CreditCardOption
    {
        $installments = [];

        foreach (self::ALLOWED_INSTALLMENTS as $quantity) {
            $installmentAmount = $this->calculateInstallment($baseAmount, $quantity);
            $installments[] = new CreditCardInstallment($quantity, $installmentAmount);
        }

        return new CreditCardOption($installments);
    }

    /**
     * Calculates the single installment value for a given quantity using the Price amortization system.
     */
    public function calculateInstallment(Money $baseAmount, int $quantity): Money
    {
        if ($quantity === 1) {
            return $baseAmount;
        }

        if (! in_array($quantity, self::ALLOWED_INSTALLMENTS, true)) {
            throw new InvalidArgumentException("Installment quantity {$quantity} is not supported.");
        }

        $base = (float) $baseAmount->toDecimal();
        $i = self::MONTHLY_RATE;
        $compounded = pow(1.0 + $i, $quantity);

        // PMT = base * (i * (1+i)^n) / ((1+i)^n - 1)
        $pmt = $base * ($i * $compounded) / ($compounded - 1.0);

        // Round final installment amount to 2 decimal places using HALF_UP
        $cents = (int) round($pmt * 100, 0, PHP_ROUND_HALF_UP);

        return Money::fromCents($cents);
    }
}
