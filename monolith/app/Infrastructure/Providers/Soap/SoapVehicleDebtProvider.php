<?php

namespace App\Infrastructure\Providers\Soap;

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
use SimpleXMLElement;
use Throwable;

class SoapVehicleDebtProvider implements VehicleDebtProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 2
    ) {}

    public function getDebts(string $plate): ProviderDebtResponse
    {
        $normalizedPlate = strtoupper(trim($plate));
        $url = rtrim($this->baseUrl, '/') . '/soap';

        $escapedPlate = htmlspecialchars($normalizedPlate, ENT_XML1, 'UTF-8');
        $xmlRequest = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<request>\n"
            . "    <plate>{$escapedPlate}</plate>\n"
            . "</request>";

        $headers = ['Content-Type' => 'application/xml'];
        if (app()->bound('request_id')) {
            $headers['X-Request-ID'] = (string) app('request_id');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->withBody($xmlRequest, 'application/xml')
                ->post($url);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailableException("Connection to SOAP provider failed or timed out: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new ProviderUnavailableException("Unexpected error communicating with SOAP provider: {$e->getMessage()}", 0, $e);
        }

        if ($response->serverError()) {
            throw new ProviderUnavailableException("SOAP provider responded with server error status {$response->status()}");
        }

        if ($response->status() !== 200) {
            throw new ProviderException("SOAP provider returned unexpected status code {$response->status()}");
        }

        $body = trim($response->body());

        if (empty($body)) {
            throw new InvalidProviderResponseException('SOAP provider returned an empty response');
        }

        $xml = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if ($xml === false) {
            throw new InvalidProviderResponseException('SOAP provider returned malformed XML');
        }

        if ($xml->getName() !== 'response' || ! isset($xml->debts)) {
            throw new InvalidProviderResponseException('SOAP XML response missing required root <response> or <debts> element');
        }

        $canonicalDebts = [];

        // Check if there are debt child elements in <debts>
        if (isset($xml->debts->debt)) {
            foreach ($xml->debts->debt as $index => $debtXml) {
                if (! isset($debtXml->category) || ! isset($debtXml->value) || ! isset($debtXml->expiration)) {
                    throw new InvalidProviderResponseException("SOAP debt item at index {$index} missing category, value or expiration");
                }

                $category = trim((string) $debtXml->category);
                $value = trim((string) $debtXml->value);
                $expiration = trim((string) $debtXml->expiration);

                if ($category === '' || $value === '' || $expiration === '') {
                    throw new InvalidProviderResponseException("SOAP debt item at index {$index} has empty fields");
                }

                try {
                    $amount = Money::fromDecimal($value);
                    $dueDate = CarbonImmutable::parse($expiration);
                } catch (Throwable $e) {
                    throw new InvalidProviderResponseException("SOAP debt item at index {$index} has invalid value or date: {$e->getMessage()}", 0, $e);
                }

                $canonicalDebts[] = new Debt(
                    type: $category,
                    amount: $amount,
                    dueDate: $dueDate,
                );
            }
        }

        return new ProviderDebtResponse(
            plate: $normalizedPlate,
            debts: $canonicalDebts,
            provider: 'soap',
        );
    }
}
