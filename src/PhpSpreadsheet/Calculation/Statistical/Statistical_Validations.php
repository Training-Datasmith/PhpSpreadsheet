<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Statistical_Validations
{
    public static function validate_float(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (float) $value;
    }
    public static function validate_int(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (int) floor((float) $value);
    }
    public static function validate_bool(mixed $value): bool
    {
        if (!is_bool($value) && !is_numeric($value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (bool) $value;
    }
}