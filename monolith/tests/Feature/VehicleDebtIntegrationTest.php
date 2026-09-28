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
                'pagamentos' => [
                    'opcoes' => [
                        [
                            'tipo' => 'TOTAL',
                            'valor_base' => '2355.93',
                            'pix' => [
                                'total_com_desconto' => '2238.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '2355.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '427.72',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '229.67',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_IPVA',
                            'valor_base' => '1800.00',
                            'pix' => [
                                'total_com_desconto' => '1710.00',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '1800.00',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '326.79',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '175.48',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_MULTA',
                            'valor_base' => '555.93',
                            'pix' => [
                                'total_com_desconto' => '528.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '555.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '100.93',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '54.20',
                                    ],
                                ],
                            ],
                        ],
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
                'pagamentos' => [
                    'opcoes' => [
                        [
                            'tipo' => 'TOTAL',
                            'valor_base' => '2355.93',
                            'pix' => [
                                'total_com_desconto' => '2238.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '2355.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '427.72',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '229.67',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_IPVA',
                            'valor_base' => '1800.00',
                            'pix' => [
                                'total_com_desconto' => '1710.00',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '1800.00',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '326.79',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '175.48',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_MULTA',
                            'valor_base' => '555.93',
                            'pix' => [
                                'total_com_desconto' => '528.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '555.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '100.93',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '54.20',
                                    ],
                                ],
                            ],
                        ],
                    ],
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
                'pagamentos' => [
                    'opcoes' => [
                        [
                            'tipo' => 'TOTAL',
                            'valor_base' => '2355.93',
                            'pix' => [
                                'total_com_desconto' => '2238.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '2355.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '427.72',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '229.67',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_IPVA',
                            'valor_base' => '1800.00',
                            'pix' => [
                                'total_com_desconto' => '1710.00',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '1800.00',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '326.79',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '175.48',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'tipo' => 'SOMENTE_MULTA',
                            'valor_base' => '555.93',
                            'pix' => [
                                'total_com_desconto' => '528.13',
                            ],
                            'cartao_credito' => [
                                'parcelas' => [
                                    [
                                        'quantidade' => 1,
                                        'valor_parcela' => '555.93',
                                    ],
                                    [
                                        'quantidade' => 6,
                                        'valor_parcela' => '100.93',
                                    ],
                                    [
                                        'quantidade' => 12,
                                        'valor_parcela' => '54.20',
                                    ],
                                ],
                            ],
                        ],
                    ],
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
                'pagamentos' => [
                    'opcoes' => [],
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

    public function test_endpoint_returns_bad_request_when_plate_format_is_invalid(): void
    {
        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'INVALID_PLATE',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'A placa do veiculo informada e invalida.',
            ]);
    }

    public function test_endpoint_accepts_mercosul_plate_format(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/BRA2E19/debts' => Http::response([
                'vehicle' => 'BRA2E19',
                'debts' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'bra2e19',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'placa' => 'BRA2E19',
                'debitos' => [],
            ]);
    }

    public function test_endpoint_normalizes_whitespace_and_casing(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response([
                'vehicle' => 'ABC1234',
                'debts' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => "  abc1234 \n ",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'placa' => 'ABC1234',
            ]);
    }

    public function test_endpoint_returns_bad_request_when_provider_is_invalid(): void
    {
        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
            'provider' => 'unsupported_provider',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'O provedor informado e invalido. Provedores permitidos: rest, soap.',
            ]);
    }

    public function test_responses_contain_security_headers(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-XSS-Protection', '1; mode=block')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
