<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Lookup_Ref_Validations
{
    public static function validate_int(mixed $value): int
    {
        if (!is_numeric($value)) {
            if (is_string($value) && Error_Value::is_error($value, true)) {
                throw new Exception($value);
            }
            throw new Exception(Excel_Error::VALUE());
        }
        return (int) floor((float) $value);
    }
    public static function validate_positive_int(mixed $value, bool $allow_zero = true): int
    {
        $value = self::validate_int($value);
        if ($allow_zero === false && $value <= 0 || $value < 0) {
            throw new Exception(Excel_Error::VALUE());
        }
        return $value;
    }
}