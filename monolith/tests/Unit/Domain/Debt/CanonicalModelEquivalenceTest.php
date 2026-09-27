<?php

namespace Tests\Unit\Domain\Debt;

use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CanonicalModelEquivalenceTest extends TestCase
{
    public function test_rest_and_soap_providers_produce_equivalent_canonical_models(): void
    {
        $plate = 'ABC1234';

        $restJsonResponse = [
            'vehicle' => 'ABC1234',
            'debts' => [
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

        $soapXmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
<response>
    <plate>ABC1234</plate>
    <debts>
        <debt>
            <category>IPVA</category>
            <value>1500.00</value>
            <expiration>2024-01-10</expiration>
        </debt>
        <debt>
            <category>MULTA</category>
            <value>300.50</value>
            <expiration>2024-02-15</expiration>
        </debt>
    </debts>
</response>';

        Http::fake([
            'http://fake-rest/api/v1/vehicles/ABC1234/debts' => Http::response($restJsonResponse, 200),
            'http://fake-soap/soap' => Http::response($soapXmlResponse, 200, ['Content-Type' => 'application/xml']),
        ]);

        $restProvider = new RestVehicleDebtProvider('http://fake-rest');
        $soapProvider = new SoapVehicleDebtProvider('http://fake-soap');

        $restCanonical = $restProvider->getDebts($plate);
        $soapCanonical = $soapProvider->getDebts($plate);

        $this->assertSame($restCanonical->plate, $soapCanonical->plate);
        $this->assertCount(2, $restCanonical->debts);
        $this->assertCount(2, $soapCanonical->debts);

        for ($i = 0; $i < 2; $i++) {
            $restDebt = $restCanonical->debts[$i];
            $soapDebt = $soapCanonical->debts[$i];

            $this->assertTrue(
                $restDebt->equals($soapDebt),
                "Debt at index {$i} differs between REST and SOAP canonical outputs."
            );
        }
    }
}
