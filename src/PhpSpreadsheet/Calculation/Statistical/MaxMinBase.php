<?php

namespace PhpOffice\PhpSpreadsheet\Calculation\Statistical;

abstract class MaxMinBase
{
    protected static function datatypeAdjustmentAllowStrings(int|float|string|bool $value): int|float
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            return 0;
        }

        return $value;
    }
}
