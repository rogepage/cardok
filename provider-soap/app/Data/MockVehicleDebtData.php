<?php

namespace App\Data;

class MockVehicleDebtData
{
    /**
     * @var array<string, array<int, array{category: string, value: string, expiration: string}>>
     */
    private static array $debts = [
        'ABC1234' => [
            [
                'category' => 'IPVA',
                'value' => '1500.00',
                'expiration' => '2024-01-10',
            ],
            [
                'category' => 'MULTA',
                'value' => '300.50',
                'expiration' => '2024-02-15',
            ],
        ],
        'LIC2024' => [
            [
                'category' => 'LICENCIAMENTO',
                'value' => '150.00',
                'expiration' => '2024-04-30',
            ],
        ],
    ];

    /**
     * @return array<int, array{category: string, value: string, expiration: string}>
     */
    public static function findByPlate(string $plate): array
    {
        $normalizedPlate = strtoupper(trim($plate));

        return self::$debts[$normalizedPlate] ?? [];
    }
}
