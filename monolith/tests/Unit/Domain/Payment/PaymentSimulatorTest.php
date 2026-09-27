<?php

namespace Tests\Unit\Domain\Payment;

use App\Domain\Debt\CalculatedDebt;
use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;
use App\Domain\Payment\Services\CreditCardCalculator;
use App\Domain\Payment\Services\PaymentSimulator;
use App\Domain\Payment\Services\PixCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PaymentSimulatorTest extends TestCase
{
    private PaymentSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new PaymentSimulator(
            new PixCalculator(),
            new CreditCardCalculator()
        );
    }

    public function test_simulates_official_scenario(): void
    {
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('1500.00'),
                    updatedAmount: Money::fromDecimal('1800.00'),
                    interestAmount: Money::fromDecimal('300.00'),
                    dueDate: CarbonImmutable::parse('2024-01-10'),
                    daysOverdue: 121,
                ),
                new CalculatedDebt(
                    type: DebtType::MULTA,
                    originalAmount: Money::fromDecimal('300.50'),
                    updatedAmount: Money::fromDecimal('555.93'),
                    interestAmount: Money::fromDecimal('255.43'),
                    dueDate: CarbonImmutable::parse('2024-02-15'),
                    daysOverdue: 85,
                ),
            ],
            totalOriginal: Money::fromDecimal('1800.50'),
            totalUpdated: Money::fromDecimal('2355.93'),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        $this->assertCount(3, $result->options);

        // 1. TOTAL
        $totalOption = $result->options[0];
        $this->assertSame('TOTAL', $totalOption->type);
        $this->assertSame('2355.93', $totalOption->baseAmount->toDecimal());
        $this->assertSame('2238.13', $totalOption->pix->totalWithDiscount->toDecimal());
        $this->assertSame('2355.93', $totalOption->creditCard->installments[0]->installmentAmount->toDecimal());
        $this->assertSame('427.72', $totalOption->creditCard->installments[1]->installmentAmount->toDecimal());
        $this->assertSame('229.67', $totalOption->creditCard->installments[2]->installmentAmount->toDecimal());

        // 2. SOMENTE_IPVA
        $ipvaOption = $result->options[1];
        $this->assertSame('SOMENTE_IPVA', $ipvaOption->type);
        $this->assertSame('1800.00', $ipvaOption->baseAmount->toDecimal());
        $this->assertSame('1710.00', $ipvaOption->pix->totalWithDiscount->toDecimal());
        $this->assertSame('1800.00', $ipvaOption->creditCard->installments[0]->installmentAmount->toDecimal());
        $this->assertSame('326.79', $ipvaOption->creditCard->installments[1]->installmentAmount->toDecimal());
        $this->assertSame('175.48', $ipvaOption->creditCard->installments[2]->installmentAmount->toDecimal());

        // 3. SOMENTE_MULTA
        $multaOption = $result->options[2];
        $this->assertSame('SOMENTE_MULTA', $multaOption->type);
        $this->assertSame('555.93', $multaOption->baseAmount->toDecimal());
        $this->assertSame('528.13', $multaOption->pix->totalWithDiscount->toDecimal());
        $this->assertSame('555.93', $multaOption->creditCard->installments[0]->installmentAmount->toDecimal());
        $this->assertSame('100.93', $multaOption->creditCard->installments[1]->installmentAmount->toDecimal());
        $this->assertSame('54.20', $multaOption->creditCard->installments[2]->installmentAmount->toDecimal());
    }

    public function test_groups_multiple_debts_of_same_type(): void
    {
        // 3 IPVA debts: 100, 200, 300 -> SOMENTE_IPVA = 600
        // 1 MULTA debt: 150 -> SOMENTE_MULTA = 150
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('100.00'),
                    updatedAmount: Money::fromDecimal('100.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('200.00'),
                    updatedAmount: Money::fromDecimal('200.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
                new CalculatedDebt(
                    type: DebtType::MULTA,
                    originalAmount: Money::fromDecimal('150.00'),
                    updatedAmount: Money::fromDecimal('150.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('300.00'),
                    updatedAmount: Money::fromDecimal('300.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
            ],
            totalOriginal: Money::fromDecimal('750.00'),
            totalUpdated: Money::fromDecimal('750.00'),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        // Expect exactly 3 options: TOTAL, SOMENTE_IPVA, SOMENTE_MULTA
        $this->assertCount(3, $result->options);

        $this->assertSame('TOTAL', $result->options[0]->type);
        $this->assertSame('750.00', $result->options[0]->baseAmount->toDecimal());

        $this->assertSame('SOMENTE_IPVA', $result->options[1]->type);
        $this->assertSame('600.00', $result->options[1]->baseAmount->toDecimal());

        $this->assertSame('SOMENTE_MULTA', $result->options[2]->type);
        $this->assertSame('150.00', $result->options[2]->baseAmount->toDecimal());
    }

    public function test_preserves_order_of_first_appearance(): void
    {
        // MULTA appears first, IPVA appears second
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [
                new CalculatedDebt(
                    type: DebtType::MULTA,
                    originalAmount: Money::fromDecimal('100.00'),
                    updatedAmount: Money::fromDecimal('100.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('200.00'),
                    updatedAmount: Money::fromDecimal('200.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
            ],
            totalOriginal: Money::fromDecimal('300.00'),
            totalUpdated: Money::fromDecimal('300.00'),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        $this->assertSame('TOTAL', $result->options[0]->type);
        $this->assertSame('SOMENTE_MULTA', $result->options[1]->type);
        $this->assertSame('SOMENTE_IPVA', $result->options[2]->type);
    }

    public function test_zero_debts_returns_empty_options(): void
    {
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'DEF5678',
            debts: [],
            totalOriginal: Money::fromCents(0),
            totalUpdated: Money::fromCents(0),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        $this->assertEmpty($result->options);
        $this->assertSame(['opcoes' => []], $result->toArray());
    }
}
