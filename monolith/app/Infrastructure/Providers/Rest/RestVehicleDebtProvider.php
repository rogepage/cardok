<?php

namespace App\Infrastructure\Providers\Rest;

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
use Throwable;

class RestVehicleDebtProvider implements VehicleDebtProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 2
    ) {}

    public function getDebts(string $plate): ProviderDebtResponse
    {
        $normalizedPlate = strtoupper(trim($plate));
        $encodedPlate = rawurlencode($normalizedPlate);
        $url = rtrim($this->baseUrl, '/') . "/api/v1/vehicles/{$encodedPlate}/debts";

        $headers = [];
        if (app()->bound('request_id')) {
            $headers['X-Request-ID'] = (string) app('request_id');
        }

        try {
            $response = Http::withHeaders($headers)->timeout($this->timeout)->get($url);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailableException("Connection to REST provider failed or timed out: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new ProviderUnavailableException("Unexpected error communicating with REST provider: {$e->getMessage()}", 0, $e);
        }

        if ($response->serverError()) {
            throw new ProviderUnavailableException("REST provider responded with server error status {$response->status()}");
        }

        if ($response->status() !== 200) {
            throw new ProviderException("REST provider returned unexpected status code {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['debts']) || ! is_array($data['debts'])) {
            throw new InvalidProviderResponseException('REST provider returned an invalid payload structure (missing debts array)');
        }

        $canonicalDebts = [];

        foreach ($data['debts'] as $index => $item) {
            if (! is_array($item) || ! isset($item['type'], $item['amount'], $item['due_date'])) {
                throw new InvalidProviderResponseException("REST debt item at index {$index} is missing required fields (type, amount, due_date)");
            }

            try {
                $rawAmount = $item['amount'];
                if (is_float($rawAmount)) {
                    $rawAmount = number_format($rawAmount, 2, '.', '');
                } elseif (is_int($rawAmount)) {
                    $rawAmount = (string) $rawAmount;
                } elseif (is_string($rawAmount)) {
                    $rawAmount = trim($rawAmount);
                }

                $amount = Money::fromDecimal($rawAmount);
                $dueDate = CarbonImmutable::parse((string) $item['due_date']);
            } catch (Throwable $e) {
                throw new InvalidProviderResponseException("REST debt item at index {$index} contains invalid values: {$e->getMessage()}", 0, $e);
            }

            $canonicalDebts[] = new Debt(
                type: (string) $item['type'],
                amount: $amount,
                dueDate: $dueDate,
            );
        }

        return new ProviderDebtResponse(
            plate: $normalizedPlate,
            debts: $canonicalDebts,
            provider: 'rest',
        );
    }
}
