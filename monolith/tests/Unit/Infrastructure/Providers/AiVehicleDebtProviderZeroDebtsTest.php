<?php

namespace Tests\Unit\Infrastructure\Providers;

use App\Infrastructure\Providers\Ai\AiVehicleDebtProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiVehicleDebtProviderZeroDebtsTest extends TestCase
{
    public function test_converts_csv_with_header_only_as_zero_debts(): void
    {
        $csv = "tipo,valor,vencimento\n";

        Http::fake([
            'http://mock-ai/api/v1/debts/CLEAN1' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('CLEAN1');

        $this->assertSame('CLEAN1', $response->plate);
        $this->assertSame('ai', $response->provider);
        $this->assertEmpty($response->debts);
    }

    public function test_converts_empty_body_as_zero_debts(): void
    {
        Http::fake([
            'http://mock-ai/api/v1/debts/CLEAN2' => Http::response('', 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('CLEAN2');

        $this->assertSame('CLEAN2', $response->plate);
        $this->assertEmpty($response->debts);
    }

    public function test_converts_no_debts_informational_text_as_zero_debts(): void
    {
        $payload = "Nenhum débito encontrado para o veículo consultado.\n";

        Http::fake([
            'http://mock-ai/api/v1/debts/CLEAN3' => Http::response($payload, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('CLEAN3');

        $this->assertSame('CLEAN3', $response->plate);
        $this->assertEmpty($response->debts);
    }
}
