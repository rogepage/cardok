<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleDebtIntegrationTest extends TestCase
{
    public function test_endpoint_returns_debts_from_rest_provider(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response([
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
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
            'provider' => 'rest',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'placa' => 'ABC1234',
                'debitos' => [
                    [
                        'tipo' => 'IPVA',
                        'valor' => '1500.00',
                        'vencimento' => '2024-01-10',
                    ],
                    [
                        'tipo' => 'MULTA',
                        'valor' => '300.50',
                        'vencimento' => '2024-02-15',
                    ],
                ],
            ]);
    }

    public function test_endpoint_returns_debts_from_soap_provider(): void
    {
        $soapXml = '<?xml version="1.0" encoding="UTF-8"?>
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
            'http://provider-soap:8000/soap' => Http::response($soapXml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
            'provider' => 'soap',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'placa' => 'ABC1234',
                'debitos' => [
                    [
                        'tipo' => 'IPVA',
                        'valor' => '1500.00',
                        'vencimento' => '2024-01-10',
                    ],
                    [
                        'tipo' => 'MULTA',
                        'valor' => '300.50',
                        'vencimento' => '2024-02-15',
                    ],
                ],
            ]);
    }

    public function test_endpoint_returns_empty_debts_for_vehicle_without_debts(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/DEF5678/debts' => Http::response([
                'vehicle' => 'DEF5678',
                'debts' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'DEF5678',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'placa' => 'DEF5678',
                'debitos' => [],
            ]);
    }

    public function test_endpoint_returns_bad_request_when_plate_is_missing(): void
    {
        $response = $this->postJson('/api/v1/vehicles/debts', []);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'A placa do veiculo e obrigatoria.',
            ]);
    }
}
