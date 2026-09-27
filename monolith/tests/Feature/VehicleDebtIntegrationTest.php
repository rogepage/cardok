<?php

namespace Tests\Feature;

use App\Application\VehicleDebt\ProviderExecutor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleDebtIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.providers.order' => ['rest', 'soap'],
            'services.providers.retries' => 2,
            'services.providers.backoff_ms' => 0,
        ]);

        $this->app->singleton(ProviderExecutor::class, function () {
            return new ProviderExecutor(
                maxRetries: 2,
                initialBackoffMs: 0,
                sleeper: fn () => null,
            );
        });
    }

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
                        'valor_original' => '1500.00',
                        'valor_atualizado' => '1800.00',
                        'vencimento' => '2024-01-10',
                        'dias_atraso' => 121,
                    ],
                    [
                        'tipo' => 'MULTA',
                        'valor_original' => '300.50',
                        'valor_atualizado' => '555.93',
                        'vencimento' => '2024-02-15',
                        'dias_atraso' => 85,
                    ],
                ],
                'resumo' => [
                    'total_original' => '1800.50',
                    'total_atualizado' => '2355.93',
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
                        'valor_original' => '1500.00',
                        'valor_atualizado' => '1800.00',
                        'vencimento' => '2024-01-10',
                        'dias_atraso' => 121,
                    ],
                    [
                        'tipo' => 'MULTA',
                        'valor_original' => '300.50',
                        'valor_atualizado' => '555.93',
                        'vencimento' => '2024-02-15',
                        'dias_atraso' => 85,
                    ],
                ],
                'resumo' => [
                    'total_original' => '1800.50',
                    'total_atualizado' => '2355.93',
                ],
            ]);
    }

    public function test_endpoint_automatically_uses_configured_order_and_falls_back_to_soap_when_rest_fails(): void
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
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response(['error' => 'fail'], 500),
            'http://provider-soap:8000/soap' => Http::response($soapXml, 200, ['Content-Type' => 'application/xml']),
        ]);

        // Request without explicit provider
        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'placa' => 'ABC1234',
                'debitos' => [
                    [
                        'tipo' => 'IPVA',
                        'valor_original' => '1500.00',
                        'valor_atualizado' => '1800.00',
                        'vencimento' => '2024-01-10',
                        'dias_atraso' => 121,
                    ],
                    [
                        'tipo' => 'MULTA',
                        'valor_original' => '300.50',
                        'valor_atualizado' => '555.93',
                        'vencimento' => '2024-02-15',
                        'dias_atraso' => 85,
                    ],
                ],
                'resumo' => [
                    'total_original' => '1800.50',
                    'total_atualizado' => '2355.93',
                ],
            ]);
    }

    public function test_endpoint_returns_503_when_all_providers_fail(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response(['error' => 'rest fail'], 500),
            'http://provider-soap:8000/soap' => Http::response('<error>soap fail</error>', 500),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(503)
            ->assertExactJson([
                'error' => 'all_providers_unavailable',
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
                'resumo' => [
                    'total_original' => '0.00',
                    'total_atualizado' => '0.00',
                ],
            ]);
    }

    public function test_endpoint_returns_422_when_provider_returns_unknown_debt_type(): void
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
                        'type' => 'LICENCIAMENTO',
                        'amount' => 150.00,
                        'due_date' => '2024-03-01',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(422)
            ->assertExactJson([
                'error' => 'unknown_debt_type',
                'type' => 'LICENCIAMENTO',
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
