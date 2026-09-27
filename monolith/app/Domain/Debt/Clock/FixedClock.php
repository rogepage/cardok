<?php

namespace App\Domain\Debt\Clock;

use Carbon\CarbonImmutable;

class FixedClock implements ClockInterface
{
    public function __construct(
        private readonly CarbonImmutable $now
    ) {}

    public static function fromIsoString(string $isoString = '2024-05-10T00:00:00Z'): self
    {
        return new self(CarbonImmutable::parse($isoString, 'UTC'));
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }
}
