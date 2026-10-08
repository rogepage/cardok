<?php

namespace Tests\Feature;

use App\Application\VehicleDebt\ProviderExecutor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderFallbackIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.providers.order' => ['ai', 'rest'],
            'services.providers.retries' => 1,
            'services.providers.backoff_ms' => 0,
        ]);

        $this->app->singleton(ProviderExecutor::class, function () {
            return new ProviderExecutor(
                maxRetries: 1,
                initialBackoffMs: 0,
                sleeper: fn () => null,
            );
        });
    }

    public function test_api_returns_debts_from_ai_provider_when_configured_as_primary(): void
    {
        $csv = <<<CSV
tipo,valor,vencimento
IPVA,1500.00,2024-01-10
MULTA,300.50,2024-02-15
CSV;

        Http::fake([
            'http://provider-csv:8000/api/v1/debts/ABC1234' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('placa', 'ABC1234')
            ->assertJsonCount(2, 'debitos');

        $this->assertNotEmpty($response->json('resumo'));
        $this->assertNotEmpty($response->json('pagamentos.opcoes'));
    }

    public function test_api_falls_back_to_rest_when_ai_provider_fails(): void
    {
        Http::fake([
            'http://provider-csv:8000/api/v1/debts/ABC1234' => Http::response('AI Service Unavailable', 500),
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response([
                'vehicle' => 'ABC1234',
                'debts' => [
                    [
                        'type' => 'IPVA',
                        'amount' => 1500.00,
                        'due_date' => '2024-01-10',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('placa', 'ABC1234')
            ->assertJsonCount(1, 'debitos')
            ->assertJsonPath('debitos.0.tipo', 'IPVA');
    }

    public function test_api_does_not_fallback_when_ai_returns_valid_zero_debts(): void
    {
        Http::fake([
            'http://provider-csv:8000/api/v1/debts/DEF5678' => Http::response("tipo,valor,vencimento\n", 200, ['Content-Type' => 'text/plain']),
            'http://provider-rest:8000/api/v1/vehicles/DEF5678/debts' => Http::response([
                'vehicle' => 'DEF5678',
                'debts' => [
                    [
                        'type' => 'IPVA',
                        'amount' => 999.00,
                        'due_date' => '2024-01-10',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'DEF5678',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('placa', 'DEF5678')
            ->assertJsonCount(0, 'debitos');
    }
}
