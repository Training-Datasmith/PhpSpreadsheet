<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Helpers
{
    /**
     * Many functions accept null/false/true argument treated as 0/0/1.
     *
     * @return float|string quotient or DIV0 if denominator is too small
     */
    public static function very_small_denominator(float $numerator, float $denominator): string|float
    {
        return abs($denominator) < 1.0E-12 ? Excel_Error::DIV0() : $numerator / $denominator;
    }
    /**
     * Many functions accept null/false/true argument treated as 0/0/1.
     */
    public static function validate_numeric_null_bool(mixed $number): int|float
    {
        $number = Functions::flatten_single_value($number);
        if ($number === null) {
            return 0;
        }
        if (is_bool($number)) {
            return (int) $number;
        }
        if (is_numeric($number)) {
            return 0 + $number;
        }
        throw new Exception(Excel_Error::throw_error($number));
    }
    /**
     * Validate numeric, but allow substitute for null.
     */
    public static function validate_numeric_null_substitution(mixed $number, null|float|int $substitute): float|int
    {
        $number = Functions::flatten_single_value($number);
        if ($number === null && $substitute !== null) {
            return $substitute;
        }
        if (is_numeric($number)) {
            return 0 + $number;
        }
        throw new Exception(Excel_Error::throw_error($number));
    }
    /**
     * Confirm number >= 0.
     */
    public static function validate_not_negative(float|int $number, ?string $except = null): void
    {
        if ($number >= 0) {
            return;
        }
        throw new Exception($except ?? Excel_Error::NAN());
    }
    /**
     * Confirm number > 0.
     */
    public static function validate_positive(float|int $number, ?string $except = null): void
    {
        if ($number > 0) {
            return;
        }
        throw new Exception($except ?? Excel_Error::NAN());
    }
    /**
     * Confirm number != 0.
     */
    public static function validate_not_zero(float|int $number): void
    {
        if ($number) {
            return;
        }
        throw new Exception(Excel_Error::DIV0());
    }
    public static function return_sign(float $number): int
    {
        return $number ? $number > 0 ? 1 : -1 : 0;
    }
    public static function get_even(float $number): float
    {
        $significance = 2 * self::return_sign($number);
        return $significance ? ceil($number / $significance) * $significance : 0;
    }
    /**
     * Return NAN or value depending on argument.
     */
    public static function number_or_nan(float $result): float|string
    {
        return is_nan($result) ? Excel_Error::NAN() : $result;
    }
}