<?php

namespace App\Domain\Payment\DTO;

use App\Domain\Debt\Money;

readonly class CreditCardInstallment
{
    public function __construct(
        public int $quantity,
        public Money $installmentAmount,
    ) {}

    /**
     * @return array{quantidade: int, valor_parcela: string}
     */
    public function toArray(): array
    {
        return [
            'quantidade' => $this->quantity,
            'valor_parcela' => $this->installmentAmount->toDecimal(),
        ];
    }
}
