<?php

namespace Tests\Unit\Domain\Payment\Services;

use App\Domain\Debt\CalculatedDebt;
use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;
use App\Domain\Payment\Services\CreditCardCalculator;
use App\Domain\Payment\Services\PaymentSimulator;
use App\Domain\Payment\Services\PixCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PaymentSimulatorLicenciamentoTest extends TestCase
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

    public function test_simulates_options_including_somente_licenciamento(): void
    {
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [
                new CalculatedDebt(
                    type: DebtType::IPVA,
                    originalAmount: Money::fromDecimal('1000.00'),
                    updatedAmount: Money::fromDecimal('1200.00'),
                    interestAmount: Money::fromDecimal('200.00'),
                    dueDate: CarbonImmutable::parse('2024-01-10'),
                    daysOverdue: 60,
                ),
                new CalculatedDebt(
                    type: DebtType::LICENCIAMENTO,
                    originalAmount: Money::fromDecimal('100.00'),
                    updatedAmount: Money::fromDecimal('103.30'),
                    interestAmount: Money::fromDecimal('3.30'),
                    dueDate: CarbonImmutable::parse('2024-04-30'),
                    daysOverdue: 10,
                ),
            ],
            totalOriginal: Money::fromDecimal('1100.00'),
            totalUpdated: Money::fromDecimal('1303.30'),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        // Options: TOTAL, SOMENTE_IPVA, SOMENTE_LICENCIAMENTO
        $this->assertCount(3, $result->options);

        $types = array_map(fn ($opt) => $opt->type, $result->options);
        $this->assertContains('TOTAL', $types);
        $this->assertContains('SOMENTE_IPVA', $types);
        $this->assertContains('SOMENTE_LICENCIAMENTO', $types);

        $licenciamentoOpt = null;
        foreach ($result->options as $opt) {
            if ($opt->type === 'SOMENTE_LICENCIAMENTO') {
                $licenciamentoOpt = $opt;
                break;
            }
        }

        $this->assertNotNull($licenciamentoOpt);
        $this->assertSame('103.30', $licenciamentoOpt->baseAmount->toDecimal());
        // PIX with 5% discount: 103.30 * 0.95 = 98.135 -> 98.14
        $this->assertSame('98.14', $licenciamentoOpt->pix->totalWithDiscount->toDecimal());
        $this->assertCount(3, $licenciamentoOpt->creditCard->installments);
    }

    public function test_aggregates_multiple_licenciamento_debts(): void
    {
        $calculatedDebts = new CalculatedVehicleDebts(
            plate: 'ABC1234',
            debts: [
                new CalculatedDebt(
                    type: DebtType::LICENCIAMENTO,
                    originalAmount: Money::fromDecimal('100.00'),
                    updatedAmount: Money::fromDecimal('120.00'),
                    interestAmount: Money::fromDecimal('20.00'),
                    dueDate: CarbonImmutable::parse('2023-01-10'),
                    daysOverdue: 300,
                ),
                new CalculatedDebt(
                    type: DebtType::LICENCIAMENTO,
                    originalAmount: Money::fromDecimal('150.00'),
                    updatedAmount: Money::fromDecimal('150.00'),
                    interestAmount: Money::fromDecimal('0.00'),
                    dueDate: CarbonImmutable::parse('2024-05-10'),
                    daysOverdue: 0,
                ),
            ],
            totalOriginal: Money::fromDecimal('250.00'),
            totalUpdated: Money::fromDecimal('270.00'),
        );

        $result = $this->simulator->simulate($calculatedDebts);

        // TOTAL and SOMENTE_LICENCIAMENTO
        $this->assertCount(2, $result->options);
        $this->assertSame('TOTAL', $result->options[0]->type);
        $this->assertSame('270.00', $result->options[0]->baseAmount->toDecimal());

        $this->assertSame('SOMENTE_LICENCIAMENTO', $result->options[1]->type);
        $this->assertSame('270.00', $result->options[1]->baseAmount->toDecimal());
    }
}
