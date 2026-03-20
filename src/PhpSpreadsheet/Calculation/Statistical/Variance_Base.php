<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
abstract class Variance_Base
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
    protected static function datatype_adjustment_booleans(mixed $value): mixed
    {
        if (is_bool($value) && Functions::get_compatibility_mode() == Functions::COMPATIBILITY_OPENOFFICE) {
            return (int) $value;
        }
        return $value;
    }
}