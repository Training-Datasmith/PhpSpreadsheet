<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Engineering_Validations
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
}