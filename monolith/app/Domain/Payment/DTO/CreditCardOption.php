<?php

namespace App\Domain\Payment\DTO;

readonly class CreditCardOption
{
    /**
     * @param CreditCardInstallment[] $installments
     */
    public function __construct(
        public array $installments,
    ) {}

    /**
     * @return array{parcelas: array<array{quantidade: int, valor_parcela: string}>}
     */
    public function toArray(): array
    {
        return [
            'parcelas' => array_map(
                fn (CreditCardInstallment $installment) => $installment->toArray(),
                $this->installments
            ),
        ];
    }
}
