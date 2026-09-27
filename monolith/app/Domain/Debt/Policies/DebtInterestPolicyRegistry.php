<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;

class DebtInterestPolicyRegistry
{
    /**
     * @var array<int, DebtInterestPolicyInterface>
     */
    private array $policies;

    /**
     * @param iterable<DebtInterestPolicyInterface> $policies
     */
    public function __construct(iterable $policies = [])
    {
        $this->policies = is_array($policies) ? $policies : iterator_to_array($policies);
    }

    public function getPolicy(DebtType $type): DebtInterestPolicyInterface
    {
        foreach ($this->policies as $policy) {
            if ($policy->supports($type)) {
                return $policy;
            }
        }

        throw new UnknownDebtTypeException($type->value);
    }
}
