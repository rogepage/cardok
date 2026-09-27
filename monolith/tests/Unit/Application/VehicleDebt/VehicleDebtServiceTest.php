<?php

namespace Tests\Unit\Application\VehicleDebt;

use App\Application\VehicleDebt\ProviderExecutor;
use App\Application\VehicleDebt\ProviderResolver;
use App\Application\VehicleDebt\VehicleDebtService;
use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\ProviderDebtResponse;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Tests\TestCase;

class VehicleDebtServiceTest extends TestCase
{
    private VehicleDebtProvider $mockRest;
    private VehicleDebtProvider $mockSoap;
    private ProviderResolver $resolver;
    private ProviderExecutor $executor;
    private VehicleDebtService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRest = $this->createMock(RestVehicleDebtProvider::class);
        $this->mockSoap = $this->createMock(SoapVehicleDebtProvider::class);

        $this->resolver = new ProviderResolver(
            $this->mockRest,
            $this->mockSoap,
        );

        $this->executor = new ProviderExecutor(
            maxRetries: 2,
            initialBackoffMs: 0,
            sleeper: fn () => null, // instant sleeper for tests
        );

        $this->service = new VehicleDebtService(
            $this->resolver,
            $this->executor,
        );

        config(['services.providers.order' => ['rest', 'soap']]);
    }

    public function test_scenario_1_rest_succeeds_soap_is_never_called(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);

        $this->mockRest->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturn($expectedResponse);

        $this->mockSoap->expects($this->never())
            ->method('getDebts');

        $response = $this->service->getDebts('ABC1234');

        $this->assertSame($expectedResponse, $response);
    }

    public function test_scenario_2_rest_fails_with_500_retries_and_falls_back_to_soap(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);

        // REST fails 3 times (1 initial + 2 retries)
        $this->mockRest->expects($this->exactly(3))
            ->method('getDebts')
            ->with('ABC1234')
            ->willThrowException(new ProviderUnavailableException('REST 500 error'));

        // SOAP succeeds on first attempt
        $this->mockSoap->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturn($expectedResponse);

        $response = $this->service->getDebts('ABC1234');

        $this->assertSame($expectedResponse, $response);
    }

    public function test_scenario_3_rest_times_out_and_falls_back_to_soap(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);

        // REST times out 3 times
        $this->mockRest->expects($this->exactly(3))
            ->method('getDebts')
            ->with('ABC1234')
            ->willThrowException(new ProviderUnavailableException('REST timeout after 2s'));

        // SOAP succeeds on first attempt
        $this->mockSoap->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturn($expectedResponse);

        $response = $this->service->getDebts('ABC1234');

        $this->assertSame($expectedResponse, $response);
    }

    public function test_scenario_4_both_rest_and_soap_fail_throws_all_providers_unavailable(): void
    {
        // REST fails 3 times
        $this->mockRest->expects($this->exactly(3))
            ->method('getDebts')
            ->with('ABC1234')
            ->willThrowException(new ProviderUnavailableException('REST 500'));

        // SOAP fails 3 times
        $this->mockSoap->expects($this->exactly(3))
            ->method('getDebts')
            ->with('ABC1234')
            ->willThrowException(new ProviderUnavailableException('SOAP 500'));

        $this->expectException(AllProvidersUnavailableException::class);

        $this->service->getDebts('ABC1234');
    }

    public function test_respects_custom_configured_order_soap_first(): void
    {
        config(['services.providers.order' => ['soap', 'rest']]);

        $expectedResponse = new ProviderDebtResponse('ABC1234', []);

        // SOAP is called first and succeeds
        $this->mockSoap->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturn($expectedResponse);

        // REST should never be called
        $this->mockRest->expects($this->never())
            ->method('getDebts');

        $response = $this->service->getDebts('ABC1234');

        $this->assertSame($expectedResponse, $response);
    }

    public function test_rest_invalid_response_triggers_immediate_fallback_without_retry(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);

        // REST returns invalid payload: exactly 1 attempt (no retries)
        $this->mockRest->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willThrowException(new InvalidProviderResponseException('Corrupted response'));

        // Falls back immediately to SOAP
        $this->mockSoap->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturn($expectedResponse);

        $response = $this->service->getDebts('ABC1234');

        $this->assertSame($expectedResponse, $response);
    }
}
