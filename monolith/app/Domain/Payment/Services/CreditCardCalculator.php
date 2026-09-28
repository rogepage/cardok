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
     *
     * Precisão e Controle Financeiro:
     * 1. Onde o float é utilizado: Exclusivamente no cálculo analítico da taxa composta ($compounded = pow(1 + i, n))
     *    e na determinação do coeficiente adimensional do Sistema Francês de Amortização (Tabela Price).
     * 2. Por que: A fórmula PMT requer exponenciação de taxa composta fracionária ((1 + 0.025)^n).
     * 3. Como a precisão é controlada: O montante monetário NUNCA é manipulado como float solto. Opera-se
     *    estritamente sobre centavos inteiros ($baseAmount->getAmountInCents()). A multiplicação de centavos
     *    inteiros pelo coeficiente em ponto flutuante de dupla precisão (IEEE 754 de 64 bits, ~15-17 dígitos)
     *    elimina desvios de representação decimal intermediária.
     * 4. Ponto de arredondamento HALF_UP: Ocorre uma única vez no fechamento da parcela, convertendo
     *    o resultado diretamente para centavos inteiros via round(..., 0, PHP_ROUND_HALF_UP).
     */
    public function calculateInstallment(Money $baseAmount, int $quantity): Money
    {
        if ($quantity === 1) {
            return $baseAmount;
        }

        if (! in_array($quantity, self::ALLOWED_INSTALLMENTS, true)) {
            throw new InvalidArgumentException("Installment quantity {$quantity} is not supported.");
        }

        $cents = $baseAmount->getAmountInCents();
        $i = self::MONTHLY_RATE;
        $compounded = pow(1.0 + $i, $quantity);

        // Fator Price adimensional: k_n = (i * (1+i)^n) / ((1+i)^n - 1)
        $factor = ($i * $compounded) / ($compounded - 1.0);

        // Aplica o fator sobre os centavos inteiros e arredonda uma única vez com HALF_UP para centavos inteiros
        $installmentCents = (int) round($cents * $factor, 0, PHP_ROUND_HALF_UP);

        return Money::fromCents($installmentCents);
    }
}
