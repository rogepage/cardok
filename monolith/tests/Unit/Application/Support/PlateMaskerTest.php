<?php

namespace Tests\Unit\Application\Support;

use App\Application\Support\PlateMasker;
use PHPUnit\Framework\TestCase;

class PlateMaskerTest extends TestCase
{
    public function test_masks_standard_7_char_plate(): void
    {
        $this->assertSame('ABC****', PlateMasker::mask('ABC1234'));
        $this->assertSame('BRA****', PlateMasker::mask('BRA2E19'));
    }

    public function test_normalizes_casing_and_whitespace(): void
    {
        $this->assertSame('ABC****', PlateMasker::mask('  abc1234 '));
        $this->assertSame('BRA****', PlateMasker::mask('bra2e19'));
    }

    public function test_masks_short_and_irregular_strings(): void
    {
        $this->assertSame('***', PlateMasker::mask(''));
        $this->assertSame('**', PlateMasker::mask('AB'));
        $this->assertSame('ABC*', PlateMasker::mask('ABCD'));
        $this->assertSame('ABC*****', PlateMasker::mask('ABC12345'));
    }

    public function test_full_plate_is_never_contained_in_masked_result(): void
    {
        $plate = 'XYZ9876';
        $masked = PlateMasker::mask($plate);

        $this->assertNotSame($plate, $masked);
        $this->assertStringNotContainsString('9876', $masked);
    }
}
