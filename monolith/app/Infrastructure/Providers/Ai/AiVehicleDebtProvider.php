<?php

namespace App\Infrastructure\Providers\Ai;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\Money;
use App\Domain\Debt\ProviderDebtResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiVehicleDebtProvider implements VehicleDebtProvider
{
    private const SUPPORTED_TYPES = ['IPVA', 'MULTA', 'LICENCIAMENTO'];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 2
    ) {}

    public function getDebts(string $plate): ProviderDebtResponse
    {
        $normalizedPlate = strtoupper(trim($plate));
        $encodedPlate = rawurlencode($normalizedPlate);
        $url = rtrim($this->baseUrl, '/') . "/api/v1/debts/{$encodedPlate}";

        $headers = [
            'Accept' => 'text/plain, text/csv',
        ];

        if (app()->bound('request_id')) {
            $headers['X-Request-ID'] = (string) app('request_id');
        }

        try {
            $response = Http::withHeaders($headers)->timeout($this->timeout)->get($url);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailableException("Connection to AI provider failed or timed out: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new ProviderUnavailableException("Unexpected error communicating with AI provider: {$e->getMessage()}", 0, $e);
        }

        if ($response->serverError()) {
            throw new ProviderUnavailableException("AI provider responded with server error status {$response->status()}");
        }

        if ($response->status() !== 200) {
            throw new ProviderException("AI provider returned unexpected status code {$response->status()}");
        }

        $rawBody = (string) $response->body();
        $canonicalDebts = $this->parseCsvBody($rawBody);

        return new ProviderDebtResponse(
            plate: $normalizedPlate,
            debts: $canonicalDebts,
            provider: 'ai',
        );
    }

    /**
     * @return array<int, Debt>
     */
    private function parseCsvBody(string $rawBody): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', trim($rawBody));
        if ($rawLines === false || empty($rawLines)) {
            return [];
        }

        $debts = [];
        $validDataLinesFound = 0;
        $corruptLinesFound = 0;

        foreach ($rawLines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            // Ignorar delimitadores de código markdown (``` ou ```csv)
            if (str_starts_with($trimmedLine, '```')) {
                continue;
            }

            // Determinar delimitador (; ou ,)
            $delimiter = (substr_count($trimmedLine, ';') > substr_count($trimmedLine, ',')) ? ';' : ',';
            $columns = str_getcsv($trimmedLine, $delimiter);

            // Verificar se é linha de cabeçalho
            if ($this->isHeaderLine($columns)) {
                continue;
            }

            // Linha com número incorreto de colunas
            if (count($columns) < 3) {
                // Se parecer comentário ou texto introdutório, apenas desconsideramos
                if ($this->isInformationalLine($trimmedLine)) {
                    continue;
                }

                $corruptLinesFound++;
                continue;
            }

            $rawType = strtoupper(trim((string) $columns[0], " \t\n\r\0\x0B\"'"));
            $rawAmount = trim((string) $columns[1], " \t\n\r\0\x0B\"'");
            $rawDate = trim((string) $columns[2], " \t\n\r\0\x0B\"'");

            // Verificar se a categoria é suportada
            if (! in_array($rawType, self::SUPPORTED_TYPES, true)) {
                Log::warning('Ignored unsupported debt type from AI provider', [
                    'raw_type' => $rawType,
                    'raw_line' => $trimmedLine,
                ]);
                continue;
            }

            // Normalizar valor monetário (substituindo vírgula decimal por ponto)
            $normalizedAmount = str_replace(',', '.', $rawAmount);

            try {
                $amount = Money::fromDecimal($normalizedAmount);
                $dueDate = CarbonImmutable::parse($rawDate);
            } catch (Throwable $e) {
                $corruptLinesFound++;
                continue;
            }

            $debts[] = new Debt(
                type: $rawType,
                amount: $amount,
                dueDate: $dueDate,
            );
            $validDataLinesFound++;
        }

        // Se houver apenas linhas corrompidas e nenhum débito válido ou informativo
        if ($corruptLinesFound > 0 && $validDataLinesFound === 0 && empty($debts)) {
            throw new InvalidProviderResponseException('AI provider returned a malformed response without valid CSV debt records');
        }

        return $debts;
    }

    /**
     * @param array<int, string|null> $columns
     */
    private function isHeaderLine(array $columns): bool
    {
        if (empty($columns)) {
            return false;
        }

        $firstCol = strtolower(trim((string) ($columns[0] ?? '')));

        return in_array($firstCol, ['tipo', 'type', 'categoria', 'category'], true);
    }

    private function isInformationalLine(string $line): bool
    {
        $lower = strtolower($line);

        return str_contains($lower, 'débito')
            || str_contains($lower, 'debito')
            || str_contains($lower, 'encontrado')
            || str_contains($lower, 'aqui')
            || str_contains($lower, 'veículo')
            || str_contains($lower, 'veiculo')
            || str_contains($lower, 'detran');
    }
}
