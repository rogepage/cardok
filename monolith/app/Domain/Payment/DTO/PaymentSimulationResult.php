<?php

namespace App\Domain\Payment\DTO;

readonly class PaymentSimulationResult
{
    /**
     * @param PaymentOption[] $options
     */
    public function __construct(
        public array $options,
    ) {}

    /**
     * @return array{opcoes: array<int, array{
     *     tipo: string,
     *     valor_base: string,
     *     pix: array{total_com_desconto: string},
     *     cartao_credito: array{parcelas: array<array{quantidade: int, valor_parcela: string}>}
     * }>}
     */
    public function toArray(): array
    {
        return [
            'opcoes' => array_map(
                fn (PaymentOption $option) => $option->toArray(),
                $this->options
            ),
        ];
    }
}
