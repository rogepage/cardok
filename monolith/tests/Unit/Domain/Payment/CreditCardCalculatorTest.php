<?php

namespace Tests\Unit\Domain\Payment;

use App\Domain\Debt\Money;
use App\Domain\Payment\Services\CreditCardCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CreditCardCalculatorTest extends TestCase
{
    private CreditCardCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CreditCardCalculator();
    }

    public function test_credit_card_spec_total_example(): void
    {
        $base = Money::fromDecimal('2355.93');
        $card = $this->calculator->calculate($base);

        $this->assertCount(3, $card->installments);

        // 1x = 2355.93
        $this->assertSame(1, $card->installments[0]->quantity);
        $this->assertSame('2355.93', $card->installments[0]->installmentAmount->toDecimal());

        // 6x = 427.72
        $this->assertSame(6, $card->installments[1]->quantity);
        $this->assertSame('427.72', $card->installments[1]->installmentAmount->toDecimal());

        // 12x = 229.67
        $this->assertSame(12, $card->installments[2]->quantity);
        $this->assertSame('229.67', $card->installments[2]->installmentAmount->toDecimal());
    }

    public function test_credit_card_spec_ipva_example(): void
    {
        $base = Money::fromDecimal('1800.00');
        $card = $this->calculator->calculate($base);

        $this->assertCount(3, $card->installments);
        $this->assertSame('1800.00', $card->installments[0]->installmentAmount->toDecimal());
        $this->assertSame('326.79', $card->installments[1]->installmentAmount->toDecimal());
        $this->assertSame('175.48', $card->installments[2]->installmentAmount->toDecimal());
    }

    public function test_credit_card_spec_multa_example(): void
    {
        $base = Money::fromDecimal('555.93');
        $card = $this->calculator->calculate($base);

        $this->assertCount(3, $card->installments);
        $this->assertSame('555.93', $card->installments[0]->installmentAmount->toDecimal());
        $this->assertSame('100.93', $card->installments[1]->installmentAmount->toDecimal());
        $this->assertSame('54.20', $card->installments[2]->installmentAmount->toDecimal());
    }

    public function test_tolerance_within_two_cents(): void
    {
        // Spec Section 10: tolerance of ± R$ 0,02
        $base = Money::fromDecimal('2355.93');

        $installment6 = $this->calculator->calculateInstallment($base, 6);
        $diff6 = abs($installment6->getAmountInCents() - 42772);
        $this->assertLessThanOrEqual(2, $diff6);

        $installment12 = $this->calculator->calculateInstallment($base, 12);
        $diff12 = abs($installment12->getAmountInCents() - 22967);
        $this->assertLessThanOrEqual(2, $diff12);
    }

    public function test_rejects_unsupported_installments(): void
    {
        $base = Money::fromDecimal('1000.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Installment quantity 3 is not supported');

        $this->calculator->calculateInstallment($base, 3);
    }

    public function test_method_key(): void
    {
        $this->assertSame('cartao_credito', $this->calculator->getMethodKey());
    }
}
