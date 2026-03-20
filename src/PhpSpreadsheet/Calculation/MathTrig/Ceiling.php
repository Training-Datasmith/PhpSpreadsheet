<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Ceiling
{
    use Array_Enabled;
    /**
     * CEILING.
     *
     * Returns number rounded up, away from zero, to the nearest multiple of significance.
     *        For example, if you want to avoid using pennies in your prices and your product is
     *        priced at $4.42, use the formula =CEILING(4.42,0.05) to round prices up to the
     *        nearest nickel.
     *
     * Excel Function:
     *        CEILING(number[,significance])
     *
     * @param array<mixed>|float $number the number you want the ceiling
     *                      Or can be an array of values
     * @param array<mixed>|float $significance the multiple to which you want to round
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function ceiling($number, $significance = null): array|string|float
    {
        if (is_array($number) || is_array($significance)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $significance);
        }
        if ($significance === null) {
            self::floor_check1arg();
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            $significance = Helpers::validate_numeric_null_substitution($significance, $number < 0 ? -1 : 1);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return self::arguments_ok((float) $number, (float) $significance);
    }
    /**
     * CEILING.MATH.
     *
     * Round a number down to the nearest integer or to the nearest multiple of significance.
     *
     * Excel Function:
     *        CEILING.MATH(number[,significance[,mode]])
     *
     * @param mixed $number Number to round
     *                      Or can be an array of values
     * @param mixed $significance Significance
     *                      Or can be an array of values
     * @param array<mixed>|int $mode direction to round negative numbers
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function math(mixed $number, mixed $significance = null, $mode = 0, bool $check_signs = false): array|string|float
    {
        if (is_array($number) || is_array($significance) || is_array($mode)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $significance, $mode);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            $significance = Helpers::validate_numeric_null_substitution($significance, $number < 0 ? -1 : 1);
            $mode = Helpers::validate_numeric_null_substitution($mode, null);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (empty($significance * $number)) {
            return 0.0;
        }
        if ($check_signs) {
            if ($number > 0 && $significance < 0 || $number < 0 && $significance > 0) {
                return Excel_Error::NAN();
            }
        }
        if (self::ceiling_math_test((float) $significance, (float) $number, (int) $mode)) {
            return floor($number / $significance) * $significance;
        }
        return ceil($number / $significance) * $significance;
    }
    /**
     * CEILING.PRECISE.
     *
     * Rounds number up, away from zero, to the nearest multiple of significance.
     *
     * Excel Function:
     *        CEILING.PRECISE(number[,significance])
     *
     * @param mixed $number the number you want to round
     *                      Or can be an array of values
     * @param array<mixed>|float $significance the multiple to which you want to round
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function precise(mixed $number, $significance = 1): array|string|float
    {
        if (is_array($number) || is_array($significance)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $significance);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            $significance = Helpers::validate_numeric_null_substitution($significance, null);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (!$significance) {
            return 0.0;
        }
        $result = $number / abs($significance);
        return ceil($result) * $significance * ($significance < 0 ? -1 : 1);
    }
    /**
     * CEILING.ODS, pseudo-function - CEILING as implemented in ODS.
     *
     * ODS Function (theoretical):
     *        CEILING.ODS(number[,significance[,mode]])
     *
     * @param mixed $number Number to round
     * @param mixed $significance Significance
     * @param array<mixed>|int $mode direction to round negative numbers
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     */
    public static function math_ods(mixed $number, mixed $significance = null, $mode = 0): array|string|float
    {
        return self::math($number, $significance, $mode, true);
    }
    /**
     * Let CEILINGMATH complexity pass Scrutinizer.
     */
    private static function ceiling_math_test(float $significance, float $number, int $mode): bool
    {
        return $significance < 0 || $number < 0 && !empty($mode);
    }
    /**
     * Avoid Scrutinizer problems concerning complexity.
     */
    private static function arguments_ok(float $number, float $significance): float|string
    {
        if (empty($number * $significance)) {
            return 0.0;
        }
        $sign_sig = Helpers::return_sign($significance);
        $sign_num = Helpers::return_sign($number);
        if ($sign_sig === 1 && ($sign_num === 1 || Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_GNUMERIC) || $sign_sig === -1 && $sign_num === -1) {
            return ceil($number / $significance) * $significance;
        }
        return Excel_Error::NAN();
    }
    private static function floor_check1arg(): void
    {
        $compatibility = Functions::get_compatibility_mode();
        if ($compatibility === Functions::COMPATIBILITY_EXCEL) {
            throw new Exception('Excel requires 2 arguments for CEILING');
        }
    }
}