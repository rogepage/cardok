<?php

namespace App\Domain\Payment\DTO;

use App\Domain\Debt\Money;

readonly class PaymentOption
{
    public function __construct(
        public string $type,
        public Money $baseAmount,
        public PixOption $pix,
        public CreditCardOption $creditCard,
    ) {}

    /**
     * @return array{
     *     tipo: string,
     *     valor_base: string,
     *     pix: array{total_com_desconto: string},
     *     cartao_credito: array{parcelas: array<array{quantidade: int, valor_parcela: string}>}
     * }
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->type,
            'valor_base' => $this->baseAmount->toDecimal(),
            'pix' => $this->pix->toArray(),
            'cartao_credito' => $this->creditCard->toArray(),
        ];
    }
}
