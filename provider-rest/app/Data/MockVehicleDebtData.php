<?php

namespace App\Data;

class MockVehicleDebtData
{
    /**
     * @var array<string, array<int, array{type: string, amount: float, due_date: string}>>
     */
    private static array $debts = [
        'ABC1234' => [
            [
                'type' => 'IPVA',
                'amount' => 1500.00,
                'due_date' => '2024-01-10',
            ],
            [
                'type' => 'MULTA',
                'amount' => 300.50,
                'due_date' => '2024-02-15',
            ],
        ],
    ];

    /**
     * @return array<int, array{type: string, amount: float, due_date: string}>
     */
    public static function findByPlate(string $plate): array
    {
        $normalizedPlate = strtoupper(trim($plate));

        return self::$debts[$normalizedPlate] ?? [];
    }
}
