<?php

namespace App\Domain\Debt;

enum DebtType: string
{
    case IPVA = 'IPVA';
    case MULTA = 'MULTA';

    public static function tryFromNormalized(string $type): ?self
    {
        return self::tryFrom(strtoupper(trim($type)));
    }
}
