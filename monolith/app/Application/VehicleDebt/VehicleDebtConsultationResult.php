<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\CalculatedDebt;
use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Payment\DTO\PaymentSimulationResult;

readonly class VehicleDebtConsultationResult
{
    public function __construct(
        public CalculatedVehicleDebts $calculatedDebts,
        public PaymentSimulationResult $payments,
    ) {}

    /**
     * Serializes the consultation result into the API response schema.
     *
     * @return array{
     *     placa: string,
     *     debitos: array<int, array{tipo: string, valor_original: string, valor_atualizado: string, vencimento: string, dias_atraso: int}>,
     *     resumo: array{total_original: string, total_atualizado: string},
     *     pagamentos: array{opcoes: array<int, mixed>}
     * }
     */
    public function toArray(): array
    {
        return [
            'placa' => $this->calculatedDebts->plate,
            'debitos' => array_map(fn (CalculatedDebt $debt) => [
                'tipo' => $debt->type->value,
                'valor_original' => $debt->originalAmount->toDecimal(),
                'valor_atualizado' => $debt->updatedAmount->toDecimal(),
                'vencimento' => $debt->dueDate->toDateString(),
                'dias_atraso' => $debt->daysOverdue,
            ], $this->calculatedDebts->debts),
            'resumo' => [
                'total_original' => $this->calculatedDebts->totalOriginal->toDecimal(),
                'total_atualizado' => $this->calculatedDebts->totalUpdated->toDecimal(),
            ],
            'pagamentos' => $this->payments->toArray(),
        ];
    }
}
