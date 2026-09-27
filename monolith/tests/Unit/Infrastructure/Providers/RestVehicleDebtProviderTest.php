<?php

namespace Tests\Unit\Infrastructure\Providers;

use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestVehicleDebtProviderTest extends TestCase
{
    public function test_converts_valid_json_response_with_multiple_debts(): void
    {
        Http::fake([
            'http://mock-rest/api/v1/vehicles/ABC1234/debts' => Http::response([
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

        $provider = new RestVehicleDebtProvider('http://mock-rest');
        $response = $provider->getDebts('ABC1234');

        $this->assertSame('ABC1234', $response->plate);
        $this->assertCount(2, $response->debts);

        $this->assertSame('IPVA', $response->debts[0]->type);
        $this->assertSame('1500.00', $response->debts[0]->amount->toDecimal());
        $this->assertSame('2024-01-10', $response->debts[0]->dueDate->toDateString());

        $this->assertSame('MULTA', $response->debts[1]->type);
        $this->assertSame('300.50', $response->debts[1]->amount->toDecimal());
        $this->assertSame('2024-02-15', $response->debts[1]->dueDate->toDateString());
    }

    public function test_converts_valid_json_response_with_zero_debts(): void
    {
        Http::fake([
            'http://mock-rest/api/v1/vehicles/DEF5678/debts' => Http::response([
                'vehicle' => 'DEF5678',
                'debts' => [],
            ], 200),
        ]);

        $provider = new RestVehicleDebtProvider('http://mock-rest');
        $response = $provider->getDebts('DEF5678');

        $this->assertSame('DEF5678', $response->plate);
        $this->assertEmpty($response->debts);
    }

    public function test_throws_provider_unavailable_exception_on_http_500(): void
    {
        Http::fake([
            'http://mock-rest/api/v1/vehicles/ABC1234/debts' => Http::response([
                'error' => 'Internal server error',
            ], 500),
        ]);

        $provider = new RestVehicleDebtProvider('http://mock-rest');

        $this->expectException(ProviderUnavailableException::class);
        $provider->getDebts('ABC1234');
    }

    public function test_throws_provider_unavailable_exception_on_connection_timeout(): void
    {
        Http::fake([
            'http://mock-rest/api/v1/vehicles/ABC1234/debts' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new RestVehicleDebtProvider('http://mock-rest');

        $this->expectException(ProviderUnavailableException::class);
        $provider->getDebts('ABC1234');
    }

    public function test_throws_invalid_provider_response_exception_on_malformed_payload(): void
    {
        Http::fake([
            'http://mock-rest/api/v1/vehicles/ABC1234/debts' => Http::response([
                'unexpected' => true,
            ], 200),
        ]);

        $provider = new RestVehicleDebtProvider('http://mock-rest');

        $this->expectException(InvalidProviderResponseException::class);
        $provider->getDebts('ABC1234');
    }
}
