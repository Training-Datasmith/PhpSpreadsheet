<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Helpers
{
    public static function convert_boolean_value(bool $value): string
    {
        if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_OPENOFFICE) {
            return $value ? '1' : '0';
        }
        return $value ? Calculation::get_true() : Calculation::get_false();
    }
    /**
     * @param mixed $value String value from which to extract characters
     */
    public static function extract_string(mixed $value, bool $throw_if_error = false): string
    {
        if (is_bool($value)) {
            return self::convert_boolean_value($value);
        }
        if ($throw_if_error && is_string($value) && Error_Value::is_error($value, true)) {
            throw new Calc_Exp($value);
        }
        return String_Helper::convert_to_string($value);
    }
    public static function extract_int(mixed $value, int $min_value, int $gnumeric_null = 0, bool $oo_bool_ok = false): int
    {
        if ($value === null) {
            // usually 0, but sometimes 1 for Gnumeric
            $value = Functions::get_compatibility_mode() === Functions::COMPATIBILITY_GNUMERIC ? $gnumeric_null : 0;
        }
        if (is_bool($value) && ($oo_bool_ok || Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_OPENOFFICE)) {
            $value = (int) $value;
        }
        if (!is_numeric($value)) {
            throw new Calc_Exp(Excel_Error::VALUE());
        }
        $value = (int) $value;
        if ($value < $min_value) {
            throw new Calc_Exp(Excel_Error::VALUE());
        }
        return $value;
    }
    public static function extract_float(mixed $value): float
    {
        if ($value === null) {
            $value = 0.0;
        }
        if (is_bool($value)) {
            $value = (float) $value;
        }
        if (!is_numeric($value)) {
            if (is_string($value) && Error_Value::is_error($value, true)) {
                throw new Calc_Exp($value);
            }
            throw new Calc_Exp(Excel_Error::VALUE());
        }
        return (float) $value;
    }
    public static function validate_int(mixed $value, bool $throw_if_error = false): int
    {
        if ($value === null) {
            $value = 0;
        } elseif (is_bool($value)) {
            $value = (int) $value;
        } elseif ($throw_if_error && is_string($value) && !is_numeric($value)) {
            if (!Error_Value::is_error($value, true)) {
                $value = Excel_Error::VALUE();
            }
            throw new Calc_Exp($value);
        }
        return (int) String_Helper::convert_to_string($value);
    }
}