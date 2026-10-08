<?php

namespace Tests\Unit\Infrastructure\Providers;

use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Infrastructure\Providers\Ai\AiVehicleDebtProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiVehicleDebtProviderDefensiveTest extends TestCase
{
    public function test_ignores_unsupported_debt_categories_and_logs_warning(): void
    {
        $csv = <<<CSV
tipo,valor,vencimento
IPVA,1500.00,2024-01-10
DPVAT,350.00,2024-02-15
PEDAGIO,80.00,2024-03-01
MULTA,200.00,2024-04-10
CSV;

        Http::fake([
            'http://mock-ai/api/v1/debts/ABC1234' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        Log::shouldReceive('warning')
            ->atLeast()->times(2)
            ->with('Ignored unsupported debt type from AI provider', \Mockery::any());

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('ABC1234');

        $this->assertCount(2, $response->debts);
        $this->assertSame('IPVA', $response->debts[0]->type);
        $this->assertSame('MULTA', $response->debts[1]->type);
    }

    public function test_throws_invalid_provider_response_exception_when_all_lines_are_malformed(): void
    {
        $corruptPayload = <<<TEXT
Internal system trace:
Fatal error at line 42: unable to parse vehicle context.
Exception: SyntaxError
TEXT;

        Http::fake([
            'http://mock-ai/api/v1/debts/ABC1234' => Http::response($corruptPayload, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');

        $this->expectException(InvalidProviderResponseException::class);
        $provider->getDebts('ABC1234');
    }

    public function test_skips_lines_with_invalid_numbers_or_dates_preserving_valid_rows(): void
    {
        $csv = <<<CSV
tipo,valor,vencimento
IPVA,not-a-number,2024-01-10
MULTA,100.00,invalid-date
LICENCIAMENTO,150.00,2024-04-30
CSV;

        Http::fake([
            'http://mock-ai/api/v1/debts/ABC1234' => Http::response($csv, 200, ['Content-Type' => 'text/plain']),
        ]);

        $provider = new AiVehicleDebtProvider('http://mock-ai');
        $response = $provider->getDebts('ABC1234');

        $this->assertCount(1, $response->debts);
        $this->assertSame('LICENCIAMENTO', $response->debts[0]->type);
        $this->assertSame('150.00', $response->debts[0]->amount->toDecimal());
    }
}
