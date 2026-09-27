<?php

namespace Tests\Feature\Livewire;

use App\Application\VehicleDebt\ProviderExecutor;
use App\Livewire\VehicleDebtLookup;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleDebtLookupTest extends TestCase
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

    public function test_initial_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Consulta de Débitos Veiculares')
            ->assertSee('Consultar débitos');
    }

    public function test_valid_plate_query_displays_debts_summary_and_payment_options(): void
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

        Livewire::test(VehicleDebtLookup::class)
            ->assertSet('resultado', null)
            ->assertSet('erro', null)
            ->set('placa', 'abc1234')
            ->call('consultar')
            ->assertSet('placa', 'ABC1234')
            ->assertSee('ABC1234')
            ->assertSee('IPVA')
            ->assertSee('R$ 1.500,00')
            ->assertSee('R$ 1.800,00')
            ->assertSee('10/01/2024')
            ->assertSee('121 dias em atraso')
            ->assertSee('MULTA')
            ->assertSee('R$ 300,50')
            ->assertSee('R$ 555,93')
            ->assertSee('15/02/2024')
            ->assertSee('85 dias em atraso')
            ->assertSee('R$ 1.800,50')
            ->assertSee('R$ 2.355,93')
            ->assertSee('Pagamento Total dos Débitos')
            ->assertSee('R$ 2.238,13')
            ->assertSee('R$ 427,72')
            ->assertSee('R$ 229,67')
            ->assertSee('Pagamento Parcial — IPVA')
            ->assertSee('R$ 1.710,00')
            ->assertSee('Pagamento Parcial — MULTA')
            ->assertSee('R$ 528,13')
            ->assertSet('erro', null);
    }

    public function test_invalid_plate_shows_friendly_error_message(): void
    {
        Livewire::test(VehicleDebtLookup::class)
            ->set('placa', 'INVALID')
            ->call('consultar')
            ->assertSet('resultado', null)
            ->assertSee('Placa inválida. Verifique o formato informado.');
    }

    public function test_empty_plate_shows_friendly_error_message(): void
    {
        Livewire::test(VehicleDebtLookup::class)
            ->set('placa', '')
            ->call('consultar')
            ->assertSet('resultado', null)
            ->assertSee('A placa do veículo é obrigatória.');
    }

    public function test_all_providers_unavailable_shows_friendly_error_message(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/ABC1234/debts' => Http::response(['error' => 'down'], 500),
            'http://provider-soap:8000/soap' => Http::response('<error>down</error>', 500),
        ]);

        Livewire::test(VehicleDebtLookup::class)
            ->set('placa', 'ABC1234')
            ->call('consultar')
            ->assertSet('resultado', null)
            ->assertSee('Não foi possível consultar os débitos no momento. Tente novamente em alguns instantes.');
    }

    public function test_zero_debts_shows_friendly_empty_state_and_no_payment_options(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/DEF5678/debts' => Http::response([
                'vehicle' => 'DEF5678',
                'debts' => [],
            ], 200),
        ]);

        Livewire::test(VehicleDebtLookup::class)
            ->set('placa', 'DEF5678')
            ->call('consultar')
            ->assertSee('Nenhum débito encontrado.')
            ->assertSee('Seu veículo está sem débitos disponíveis para consulta.')
            ->assertDontSee('Opções de Pagamento')
            ->assertDontSee('SOMENTE IPVA');
    }

    public function test_limpar_resets_component_state(): void
    {
        Http::fake([
            'http://provider-rest:8000/api/v1/vehicles/DEF5678/debts' => Http::response([
                'vehicle' => 'DEF5678',
                'debts' => [],
            ], 200),
        ]);

        Livewire::test(VehicleDebtLookup::class)
            ->set('placa', 'DEF5678')
            ->call('consultar')
            ->assertSee('Nenhum débito encontrado.')
            ->call('limpar')
            ->assertSet('placa', '')
            ->assertSet('resultado', null)
            ->assertSet('erro', null)
            ->assertDontSee('Nenhum débito encontrado.');
    }
}
