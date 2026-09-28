<?php

namespace App\Application\Support;

class PlateMasker
{
    /**
     * Masks the vehicle plate for LGPD compliance, preserving the first 3 characters
     * and masking the remainder with asterisks.
     *
     * Examples:
     * - "ABC1234" -> "ABC****"
     * - "BRA2E19" -> "BRA****"
     * - "AB"      -> "**"
     */
    public static function mask(?string $plate): string
    {
        if ($plate === null) {
            return '***';
        }

        $clean = strtoupper(trim($plate));
        $len = strlen($clean);

        if ($clean === '') {
            return '***';
        }

        if ($len <= 3) {
            return str_repeat('*', $len);
        }

        return substr($clean, 0, 3) . str_repeat('*', $len - 3);
    }
}
