<?php

namespace Tests\Unit\Infrastructure\Providers;

use App\Infrastructure\Providers\Ai\AiVehicleDebtProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiVehicleDebtProviderTest extends TestCase
{
    public function test_converts_valid_csv_with_comma_delimiter(): void
    {
        $csv = <<<CSV
tipo,valor,vencimento
IPVA,1500.00,2024-01-10
MULTA,300.50,2024-02-15
CSV;

        Http::fake([
            'http://mock-ai/api/v1/debts/ABC1234' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('ABC1234');

        $this->assertSame('ABC1234', $response->plate);
        $this->assertSame('ai', $response->provider);
        $this->assertCount(2, $response->debts);

        $this->assertSame('IPVA', $response->debts[0]->type);
        $this->assertSame('1500.00', $response->debts[0]->amount->toDecimal());
        $this->assertSame('2024-01-10', $response->debts[0]->dueDate->toDateString());

        $this->assertSame('MULTA', $response->debts[1]->type);
        $this->assertSame('300.50', $response->debts[1]->amount->toDecimal());
        $this->assertSame('2024-02-15', $response->debts[1]->dueDate->toDateString());
    }

    public function test_converts_valid_csv_with_semicolon_delimiter_and_comma_decimal(): void
    {
        $csv = <<<CSV
tipo;valor;vencimento
IPVA;1500,00;2024-01-10
LICENCIAMENTO;150,00;2024-04-30
CSV;

        Http::fake([
            'http://mock-ai/api/v1/debts/LIC2024' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('LIC2024');

        $this->assertSame('LIC2024', $response->plate);
        $this->assertCount(2, $response->debts);

        $this->assertSame('IPVA', $response->debts[0]->type);
        $this->assertSame('1500.00', $response->debts[0]->amount->toDecimal());
        $this->assertSame('2024-01-10', $response->debts[0]->dueDate->toDateString());

        $this->assertSame('LICENCIAMENTO', $response->debts[1]->type);
        $this->assertSame('150.00', $response->debts[1]->amount->toDecimal());
        $this->assertSame('2024-04-30', $response->debts[1]->dueDate->toDateString());
    }

    public function test_strips_markdown_code_fences_and_introductory_text(): void
    {
        $payload = <<<TEXT
Aqui estão os débitos encontrados para o veículo informado:

```csv
tipo,valor,vencimento
IPVA,1200.00,2024-01-10
```

Caso precise de mais informações, consulte o DETRAN.
TEXT;

        Http::fake([
            'http://mock-ai/api/v1/debts/XYZ9876' => Http::response($payload, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('XYZ9876');

        $this->assertSame('XYZ9876', $response->plate);
        $this->assertCount(1, $response->debts);
        $this->assertSame('IPVA', $response->debts[0]->type);
        $this->assertSame('1200.00', $response->debts[0]->amount->toDecimal());
        $this->assertSame('2024-01-10', $response->debts[0]->dueDate->toDateString());
    }
}
