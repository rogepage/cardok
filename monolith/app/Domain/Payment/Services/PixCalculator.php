<?php

namespace App\Domain\Payment\Services;

use App\Domain\Debt\Money;
use App\Domain\Debt\Rounding\HalfUpRounder;
use App\Domain\Payment\Contracts\PaymentMethodCalculatorInterface;
use App\Domain\Payment\DTO\PixOption;

class PixCalculator implements PaymentMethodCalculatorInterface
{
    /**
     * PIX discount percentage: 5%.
     */
    private const int DISCOUNT_PERCENTAGE = 5;

    public function getMethodKey(): string
    {
        return 'pix';
    }

    /**
     * Calculates PIX payment details with a 5% discount.
     * Preserves exact integer cents arithmetic and applies HALF_UP rounding.
     */
    public function calculate(Money $baseAmount): PixOption
    {
        $cents = $baseAmount->getAmountInCents();

        // total_com_desconto = valor_base * (100 - 5) / 100
        $discountedCents = HalfUpRounder::round($cents * (100 - self::DISCOUNT_PERCENTAGE), 100);

        return new PixOption(Money::fromCents($discountedCents));
    }
}
