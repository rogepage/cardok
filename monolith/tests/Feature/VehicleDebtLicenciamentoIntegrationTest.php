<?php

namespace Tests\Feature;

use App\Application\VehicleDebt\ProviderExecutor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleDebtLicenciamentoIntegrationTest extends TestCase
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

    public function test_api_returns_calculated_licenciamento_and_payment_options(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response([
                'vehicle' => 'ABC1234',
                'debts' => [
                    [
                        'type' => 'LICENCIAMENTO',
                        'amount' => 100.00,
                        'due_date' => '2024-04-30',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
            'provider' => 'rest',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('placa', 'ABC1234')
            ->assertJsonPath('debitos.0.tipo', 'LICENCIAMENTO')
            ->assertJsonPath('debitos.0.valor_original', '100.00')
            ->assertJsonPath('debitos.0.valor_atualizado', '103.30')
            ->assertJsonPath('debitos.0.dias_atraso', 10)
            ->assertJsonPath('resumo.total_original', '100.00')
            ->assertJsonPath('resumo.total_atualizado', '103.30');

        $options = $response->json('pagamentos.opcoes');
        $this->assertCount(2, $options);

        // TOTAL option
        $this->assertSame('TOTAL', $options[0]['tipo']);
        $this->assertSame('103.30', $options[0]['valor_base']);
        $this->assertSame('98.14', $options[0]['pix']['total_com_desconto']);

        // SOMENTE_LICENCIAMENTO option
        $this->assertSame('SOMENTE_LICENCIAMENTO', $options[1]['tipo']);
        $this->assertSame('103.30', $options[1]['valor_base']);
        $this->assertSame('98.14', $options[1]['pix']['total_com_desconto']);
        $this->assertCount(3, $options[1]['cartao_credito']['parcelas']);
    }
}
