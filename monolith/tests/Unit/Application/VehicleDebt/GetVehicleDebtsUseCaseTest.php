<?php

namespace Tests\Unit\Application\VehicleDebt;

use App\Application\VehicleDebt\GetVehicleDebtsUseCase;
use App\Application\VehicleDebt\VehicleDebtService;
use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Money;
use App\Domain\Debt\ProviderDebtResponse;
use App\Domain\Debt\Services\DebtCalculationService;
use App\Domain\Payment\DTO\PaymentSimulationResult;
use App\Domain\Payment\Services\PaymentSimulator;
use PHPUnit\Framework\TestCase;

class GetVehicleDebtsUseCaseTest extends TestCase
{
    private VehicleDebtService $vehicleDebtService;
    private DebtCalculationService $calculationService;
    private PaymentSimulator $paymentSimulator;
    private GetVehicleDebtsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicleDebtService = $this->createMock(VehicleDebtService::class);
        $this->calculationService = $this->createMock(DebtCalculationService::class);
        $this->paymentSimulator = $this->createMock(PaymentSimulator::class);

        $this->useCase = new GetVehicleDebtsUseCase(
            $this->vehicleDebtService,
            $this->calculationService,
            $this->paymentSimulator,
        );
    }

    public function test_successfully_orchestrates_consultation_flow(): void
    {
        $providerResponse = new ProviderDebtResponse('ABC1234', []);
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [],
            totalOriginal: Money::fromCents(0),
            totalUpdated: Money::fromCents(0),
        );
        $paymentSimulation = new PaymentSimulationResult([]);

        $this->vehicleDebtService->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234', ['rest'])
            ->willReturn($providerResponse);

        $this->calculationService->expects($this->once())
            ->method('calculate')
            ->with($providerResponse)
            ->willReturn($calculatedDebts);

        $this->paymentSimulator->expects($this->once())
            ->method('simulate')
            ->with($calculatedDebts)
            ->willReturn($paymentSimulation);

        $result = $this->useCase->execute('ABC1234', ['rest']);

        $this->assertSame($calculatedDebts, $result->calculatedDebts);
        $this->assertSame($paymentSimulation, $result->payments);
        $this->assertSame('ABC1234', $result->toArray()['placa']);
    }

    public function test_propagates_unknown_debt_type_exception(): void
    {
        $providerResponse = new ProviderDebtResponse('ABC1234', []);

        $this->vehicleDebtService->method('getDebts')->willReturn($providerResponse);
        $this->calculationService->method('calculate')
            ->willThrowException(new UnknownDebtTypeException('OUTROS'));

        $this->expectException(UnknownDebtTypeException::class);
        $this->useCase->execute('ABC1234');
    }

    public function test_propagates_all_providers_unavailable_exception(): void
    {
        $this->vehicleDebtService->method('getDebts')
            ->willThrowException(new AllProvidersUnavailableException('All providers down'));

        $this->expectException(AllProvidersUnavailableException::class);
        $this->useCase->execute('ABC1234');
    }
}
