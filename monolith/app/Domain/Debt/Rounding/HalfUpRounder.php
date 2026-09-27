<?php

namespace App\Domain\Debt\Rounding;

use InvalidArgumentException;

class HalfUpRounder
{
    /**
     * Rounds a rational fraction (numerator / denominator) to the nearest integer
     * using the HALF_UP rounding mode, preserving exact integer arithmetic without float.
     *
     * In HALF_UP:
     * - Remainder < half denominator => rounds down (towards zero)
     * - Remainder >= half denominator => rounds up (away from zero)
     *
     * Example:
     * - 255425 / 10 (255.425) -> remainder 5 >= 5 -> 25543 (255.43)
     * - 255424 / 10 (255.424) -> remainder 4 < 5  -> 25542 (255.42)
     */
    public static function round(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException("Denominator must be positive, got: {$denominator}");
        }

        if ($numerator >= 0) {
            return intdiv($numerator + intdiv($denominator, 2), $denominator);
        }

        return -intdiv(-$numerator + intdiv($denominator, 2), $denominator);
    }
}
