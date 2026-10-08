<?php

namespace App\Services;

use App\Data\MockVehicleDebtData;
use Illuminate\Http\Response;

class VehicleDebtService
{
    private static function getSimulationModeFilePath(): string
    {
        return storage_path('framework/simulation_mode.txt');
    }

    public function getDebtsResponse(string $plate, ?string $mode = null): Response
    {
        if ($mode === null) {
            $filePath = self::getSimulationModeFilePath();
            if (file_exists($filePath)) {
                $fileMode = trim((string) @file_get_contents($filePath));
                if ($fileMode !== '') {
                    $mode = $fileMode;
                }
            }
        }

        $mode = $mode ?? config('services.provider_mode') ?? env('PROVIDER_MODE', 'success');

        return match ($mode) {
            'error' => response("Simulated external AI provider error\n", 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]),

            'timeout' => $this->handleTimeout($plate),

            'invalid_response' => response("This is corrupted text, not a valid CSV\nInvalid format without columns\n", 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]),

            'empty' => response("tipo,valor,vencimento\n", 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]),

            default => $this->formatCsvResponse($plate),
        };
    }

    private function formatCsvResponse(string $plate): Response
    {
        $debts = MockVehicleDebtData::findByPlate($plate);

        $lines = ['tipo,valor,vencimento'];
        foreach ($debts as $item) {
            $type = strtoupper((string) ($item['type'] ?? ''));
            $amount = number_format((float) ($item['amount'] ?? 0), 2, '.', '');
            $due = (string) ($item['due_date'] ?? '');
            $lines[] = "{$type},{$amount},{$due}";
        }

        $body = implode("\n", $lines) . "\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function setSimulationMode(string $mode): void
    {
        $filePath = self::getSimulationModeFilePath();
        $normalizedMode = strtolower(trim($mode));

        if ($normalizedMode === '' || $normalizedMode === 'success' || $normalizedMode === 'default') {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        } else {
            @file_put_contents($filePath, $normalizedMode);
        }
    }

    public function getSimulationMode(): string
    {
        $filePath = self::getSimulationModeFilePath();
        if (file_exists($filePath)) {
            $fileMode = trim((string) @file_get_contents($filePath));
            if ($fileMode !== '') {
                return $fileMode;
            }
        }

        return (string) (config('services.provider_mode') ?? env('PROVIDER_MODE', 'success'));
    }

    private function handleTimeout(string $plate): Response
    {
        sleep(5);

        return $this->formatCsvResponse($plate);
    }
}
