<?php

namespace Tests\Unit\Domain\Debt;

use App\Domain\Debt\Clock\FixedClock;
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtType;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Money;
use App\Domain\Debt\Policies\DebtInterestPolicyRegistry;
use App\Domain\Debt\Policies\IpvaInterestPolicy;
use App\Domain\Debt\Policies\MultaInterestPolicy;
use App\Domain\Debt\ProviderDebtResponse;
use App\Domain\Debt\Services\DebtCalculationService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DebtCalculationServiceTest extends TestCase
{
    private DebtCalculationService $service;
    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = FixedClock::fromIsoString('2024-05-10T00:00:00Z');
        $registry = new DebtInterestPolicyRegistry([
            new IpvaInterestPolicy(),
            new MultaInterestPolicy(),
        ]);

        $this->service = new DebtCalculationService($registry, $this->clock);
    }

    public function test_official_example_scenario(): void
    {
        $response = new ProviderDebtResponse('ABC1234', [
            new Debt('IPVA', Money::fromDecimal('1500.00'), CarbonImmutable::parse('2024-01-10', 'UTC')),
            new Debt('MULTA', Money::fromDecimal('300.50'), CarbonImmutable::parse('2024-02-15', 'UTC')),
        ]);

        $result = $this->service->calculate($response);

        $this->assertSame('ABC1234', $result->plate);
        $this->assertCount(2, $result->debts);

        // IPVA check
        $ipva = $result->debts[0];
        $this->assertSame(DebtType::IPVA, $ipva->type);
        $this->assertSame('1500.00', $ipva->originalAmount->toDecimal());
        $this->assertSame('1800.00', $ipva->updatedAmount->toDecimal());
        $this->assertSame('300.00', $ipva->interestAmount->toDecimal());
        $this->assertSame(121, $ipva->daysOverdue);

        // MULTA check
        $multa = $result->debts[1];
        $this->assertSame(DebtType::MULTA, $multa->type);
        $this->assertSame('300.50', $multa->originalAmount->toDecimal());
        $this->assertSame('555.93', $multa->updatedAmount->toDecimal());
        $this->assertSame('255.43', $multa->interestAmount->toDecimal());
        $this->assertSame(85, $multa->daysOverdue);

        // Summary check
        $this->assertSame('1800.50', $result->totalOriginal->toDecimal());
        $this->assertSame('2355.93', $result->totalUpdated->toDecimal());
    }

    public function test_ipva_overdue_below_cap(): void
    {
        // 1000.00 * 0.0033 * 10 = 33.00 (below cap of 200.00)
        $dueDate = CarbonImmutable::parse('2024-04-30', 'UTC'); // 10 days before 2024-05-10
        $debt = new Debt('IPVA', Money::fromDecimal('1000.00'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::IPVA, $this->clock->now());

        $this->assertSame(10, $calculated->daysOverdue);
        $this->assertSame('33.00', $calculated->interestAmount->toDecimal());
        $this->assertSame('1033.00', $calculated->updatedAmount->toDecimal());
    }

    public function test_ipva_overdue_exactly_at_cap(): void
    {
        // 10000.00 * 0.0033 * 60 = 1980.00
        // Cap of 20% on 10000.00 is 2000.00
        // If days = 60.606..., at 61 days: 1000.00 * 0.0033 * 61 = 201.30 -> capped at 200.00
        $dueDate = CarbonImmutable::parse('2024-03-10', 'UTC'); // 61 days
        $debt = new Debt('IPVA', Money::fromDecimal('1000.00'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::IPVA, $this->clock->now());

        $this->assertSame(61, $calculated->daysOverdue);
        $this->assertSame('200.00', $calculated->interestAmount->toDecimal()); // capped at 20%
        $this->assertSame('1200.00', $calculated->updatedAmount->toDecimal());
    }

    public function test_ipva_not_overdue_has_zero_interest(): void
    {
        $dueDate = CarbonImmutable::parse('2024-05-15', 'UTC');
        $debt = new Debt('IPVA', Money::fromDecimal('1500.00'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::IPVA, $this->clock->now());

        $this->assertSame(0, $calculated->daysOverdue);
        $this->assertSame('0.00', $calculated->interestAmount->toDecimal());
        $this->assertSame('1500.00', $calculated->updatedAmount->toDecimal());
    }

    public function test_ipva_half_up_rounding(): void
    {
        // 100.15 * 0.0033 * 1 = 0.330495 -> rounds to 0.33
        $dueDate = CarbonImmutable::parse('2024-05-09', 'UTC');
        $debt = new Debt('IPVA', Money::fromDecimal('100.15'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::IPVA, $this->clock->now());

        $this->assertSame(1, $calculated->daysOverdue);
        $this->assertSame('0.33', $calculated->interestAmount->toDecimal());
        $this->assertSame('100.48', $calculated->updatedAmount->toDecimal());
    }

    public function test_multa_overdue_and_half_up_rounding(): void
    {
        // 300.50 * 0.01 * 85 = 255.425 -> HALF_UP: 255.43
        $dueDate = CarbonImmutable::parse('2024-02-15', 'UTC');
        $debt = new Debt('MULTA', Money::fromDecimal('300.50'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::MULTA, $this->clock->now());

        $this->assertSame(85, $calculated->daysOverdue);
        $this->assertSame('255.43', $calculated->interestAmount->toDecimal());
        $this->assertSame('555.93', $calculated->updatedAmount->toDecimal());
    }

    public function test_multa_not_overdue_has_zero_interest(): void
    {
        $dueDate = CarbonImmutable::parse('2024-06-01', 'UTC');
        $debt = new Debt('MULTA', Money::fromDecimal('500.00'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::MULTA, $this->clock->now());

        $this->assertSame(0, $calculated->daysOverdue);
        $this->assertSame('0.00', $calculated->interestAmount->toDecimal());
        $this->assertSame('500.00', $calculated->updatedAmount->toDecimal());
    }

    public function test_date_boundary_cases(): void
    {
        // Case 1: vencimento = 2024-05-10 -> dias_atraso = 0
        $debtSameDay = new Debt('IPVA', Money::fromDecimal('100.00'), CarbonImmutable::parse('2024-05-10', 'UTC'));
        $calcSameDay = $this->service->calculateSingleDebt($debtSameDay, DebtType::IPVA, $this->clock->now());
        $this->assertSame(0, $calcSameDay->daysOverdue);

        // Case 2: vencimento = 2024-05-11 -> dias_atraso = 0
        $debtFuture = new Debt('IPVA', Money::fromDecimal('100.00'), CarbonImmutable::parse('2024-05-11', 'UTC'));
        $calcFuture = $this->service->calculateSingleDebt($debtFuture, DebtType::IPVA, $this->clock->now());
        $this->assertSame(0, $calcFuture->daysOverdue);

        // Case 3: vencimento = 2024-05-09 -> dias_atraso = 1
        $debtPast = new Debt('IPVA', Money::fromDecimal('100.00'), CarbonImmutable::parse('2024-05-09', 'UTC'));
        $calcPast = $this->service->calculateSingleDebt($debtPast, DebtType::IPVA, $this->clock->now());
        $this->assertSame(1, $calcPast->daysOverdue);
    }

    public function test_throws_unknown_debt_type_exception(): void
    {
        $response = new ProviderDebtResponse('ABC1234', [
            new Debt('IPVA', Money::fromDecimal('1500.00'), CarbonImmutable::parse('2024-01-10', 'UTC')),
            new Debt('LICENCIAMENTO', Money::fromDecimal('150.00'), CarbonImmutable::parse('2024-03-01', 'UTC')),
        ]);

        $this->expectException(UnknownDebtTypeException::class);

        try {
            $this->service->calculate($response);
        } catch (UnknownDebtTypeException $e) {
            $this->assertSame('LICENCIAMENTO', $e->getDebtType());
            throw $e;
        }
    }

    public function test_handles_zero_debts_correctly(): void
    {
        $response = new ProviderDebtResponse('DEF5678', []);

        $result = $this->service->calculate($response);

        $this->assertSame('DEF5678', $result->plate);
        $this->assertEmpty($result->debts);
        $this->assertSame('0.00', $result->totalOriginal->toDecimal());
        $this->assertSame('0.00', $result->totalUpdated->toDecimal());
    }

    public function test_ipva_cap_evaluated_before_rounding_with_fractional_cents(): void
    {
        // valor_original = 10.01 (1001 cents)
        // 20% cap = 200.2 cents (R$ 2.002)
        // at 100 days overdue:
        // juros_calculado = 10.01 * 0.0033 * 100 = 3.3033 (330.33 cents)
        // juros_teto = 200.2 cents
        // min(juros_calculado, juros_teto) = 200.2 cents
        // HALF_UP(200.2 cents) = 200 cents (R$ 2.00)
        // valor_atualizado = 10.01 + 2.00 = 12.01
        $dueDate = CarbonImmutable::parse('2024-01-31', 'UTC'); // 100 days before 2024-05-10
        $debt = new Debt('IPVA', Money::fromDecimal('10.01'), $dueDate);

        $calculated = $this->service->calculateSingleDebt($debt, DebtType::IPVA, $this->clock->now());

        $this->assertSame(100, $calculated->daysOverdue);
        $this->assertSame('2.00', $calculated->interestAmount->toDecimal());
        $this->assertSame('12.01', $calculated->updatedAmount->toDecimal());
    }

    public function test_totals_calculated_from_already_normalized_monetary_values(): void
    {
        // Test demonstrating that totals are the direct sum of normalized 2-decimal amounts,
        // without cumulative rounding drift or summing unrounded intermediate floats.
        // Debt 1: MULTA 0.50, 1 day overdue -> 0.50 * 0.01 * 1 = 0.005 -> HALF_UP: 0.01 -> updated: 0.51
        // Debt 2: MULTA 0.50, 1 day overdue -> 0.50 * 0.01 * 1 = 0.005 -> HALF_UP: 0.01 -> updated: 0.51
        // Sum of normalized updated amounts: 0.51 + 0.51 = 1.02.
        // (If intermediate values were summed unrounded: 0.505 + 0.505 = 1.010, which would yield 1.01).
        $dueDate = CarbonImmutable::parse('2024-05-09', 'UTC'); // 1 day overdue
        $response = new ProviderDebtResponse('XYZ9999', [
            new Debt('MULTA', Money::fromDecimal('0.50'), $dueDate),
            new Debt('MULTA', Money::fromDecimal('0.50'), $dueDate),
        ]);

        $result = $this->service->calculate($response);

        $this->assertSame('1.00', $result->totalOriginal->toDecimal());
        $this->assertSame('1.02', $result->totalUpdated->toDecimal());
        $this->assertSame('0.51', $result->debts[0]->updatedAmount->toDecimal());
        $this->assertSame('0.51', $result->debts[1]->updatedAmount->toDecimal());
    }
}
