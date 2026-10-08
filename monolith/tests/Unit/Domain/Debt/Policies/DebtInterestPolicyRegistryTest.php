<?php

namespace Tests\Unit\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Policies\DebtInterestPolicyRegistry;
use App\Domain\Debt\Policies\IpvaInterestPolicy;
use App\Domain\Debt\Policies\LicenciamentoInterestPolicy;
use App\Domain\Debt\Policies\MultaInterestPolicy;
use Tests\TestCase;

class DebtInterestPolicyRegistryTest extends TestCase
{
    private DebtInterestPolicyRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DebtInterestPolicyRegistry([
            new IpvaInterestPolicy(),
            new MultaInterestPolicy(),
            new LicenciamentoInterestPolicy(),
        ]);
    }

    public function test_resolves_ipva_policy(): void
    {
        $policy = $this->registry->getPolicy(DebtType::IPVA);
        $this->assertInstanceOf(IpvaInterestPolicy::class, $policy);
    }

    public function test_resolves_multa_policy(): void
    {
        $policy = $this->registry->getPolicy(DebtType::MULTA);
        $this->assertInstanceOf(MultaInterestPolicy::class, $policy);
    }

    public function test_resolves_licenciamento_policy(): void
    {
        $policy = $this->registry->getPolicy(DebtType::LICENCIAMENTO);
        $this->assertInstanceOf(LicenciamentoInterestPolicy::class, $policy);
    }

    public function test_throws_exception_when_policy_not_registered(): void
    {
        $incompleteRegistry = new DebtInterestPolicyRegistry([
            new IpvaInterestPolicy(),
        ]);

        $this->expectException(UnknownDebtTypeException::class);
        $incompleteRegistry->getPolicy(DebtType::LICENCIAMENTO);
    }
}
