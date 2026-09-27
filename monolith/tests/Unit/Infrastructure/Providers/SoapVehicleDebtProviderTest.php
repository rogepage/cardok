<?php

namespace Tests\Unit\Infrastructure\Providers;

use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoapVehicleDebtProviderTest extends TestCase
{
    public function test_converts_valid_xml_response_with_multiple_debts(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
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
            'http://mock-soap/soap' => Http::response($xml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $provider = new SoapVehicleDebtProvider('http://mock-soap');
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

    public function test_converts_self_closing_debts_tag_to_empty_collection(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<response>
    <plate>DEF5678</plate>
    <debts/>
</response>';

        Http::fake([
            'http://mock-soap/soap' => Http::response($xml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $provider = new SoapVehicleDebtProvider('http://mock-soap');
        $response = $provider->getDebts('DEF5678');

        $this->assertSame('DEF5678', $response->plate);
        $this->assertEmpty($response->debts);
    }

    public function test_throws_provider_unavailable_exception_on_http_500(): void
    {
        Http::fake([
            'http://mock-soap/soap' => Http::response('<error>Server error</error>', 500),
        ]);

        $provider = new SoapVehicleDebtProvider('http://mock-soap');

        $this->expectException(ProviderUnavailableException::class);
        $provider->getDebts('ABC1234');
    }

    public function test_throws_provider_unavailable_exception_on_connection_timeout(): void
    {
        Http::fake([
            'http://mock-soap/soap' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $provider = new SoapVehicleDebtProvider('http://mock-soap');

        $this->expectException(ProviderUnavailableException::class);
        $provider->getDebts('ABC1234');
    }

    public function test_throws_invalid_provider_response_exception_on_malformed_xml(): void
    {
        Http::fake([
            'http://mock-soap/soap' => Http::response('<invalid><broken>', 200),
        ]);

        $provider = new SoapVehicleDebtProvider('http://mock-soap');

        $this->expectException(InvalidProviderResponseException::class);
        $provider->getDebts('ABC1234');
    }
}
