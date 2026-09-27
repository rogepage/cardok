<?php

namespace App\Domain\Payment\DTO;

use App\Domain\Debt\Money;

readonly class PixOption
{
    public function __construct(
        public Money $totalWithDiscount,
    ) {}

    /**
     * @return array{total_com_desconto: string}
     */
    public function toArray(): array
    {
        return [
            'total_com_desconto' => $this->totalWithDiscount->toDecimal(),
        ];
    }
}
