<?php

namespace App\Domain\Payment\Contracts;

use App\Domain\Debt\Money;

interface PaymentMethodCalculatorInterface
{
    /**
     * Identifies the payment method key (e.g. 'pix', 'cartao_credito').
     */
    public function getMethodKey(): string;

    /**
     * Calculates the payment simulation details for the given base amount.
     */
    public function calculate(Money $baseAmount): mixed;
}
