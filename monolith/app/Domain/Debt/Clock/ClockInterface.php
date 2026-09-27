<?php

namespace App\Domain\Debt\Clock;

use Carbon\CarbonImmutable;

interface ClockInterface
{
    public function now(): CarbonImmutable;
}
