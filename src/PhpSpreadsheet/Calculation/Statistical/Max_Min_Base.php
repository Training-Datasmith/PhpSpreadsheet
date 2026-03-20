<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

abstract class Max_Min_Base
{
    protected static function datatype_adjustment_allow_strings(int|float|string|bool $value): int|float
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