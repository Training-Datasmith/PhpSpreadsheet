<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Floor
{
    use Array_Enabled;
    private static function floor_check1arg(): void
    {
        $compatibility = Functions::get_compatibility_mode();
        if ($compatibility === Functions::COMPATIBILITY_EXCEL) {
            throw new Exception('Excel requires 2 arguments for FLOOR');
        }
    }
    /**
     * FLOOR.
     *
     * Rounds number down, toward zero, to the nearest multiple of significance.
     *
     * Excel Function:
     *        FLOOR(number[,significance])
     *
     * @param mixed $number Expect float. Number to round
     *                      Or can be an array of values
     * @param mixed $significance Expect float. Significance
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function floor(mixed $number, mixed $significance = null): array|string|float
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
     * FLOOR.MATH.
     *
     * Round a number down to the nearest integer or to the nearest multiple of significance.
     *
     * Excel Function:
     *        FLOOR.MATH(number[,significance[,mode]])
     *
     * @param mixed $number Number to round
     *                      Or can be an array of values
     * @param mixed $significance Significance
     *                      Or can be an array of values
     * @param mixed $mode direction to round negative numbers
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function math(mixed $number, mixed $significance = null, mixed $mode = 0, bool $check_signs = false): array|string|float
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
        return self::args_ok((float) $number, (float) $significance, (int) $mode);
    }
    /**
     * FLOOR.ODS, pseudo-function - FLOOR as implemented in ODS.
     *
     * Round a number down to the nearest integer or to the nearest multiple of significance.
     *
     * ODS Function (theoretical):
     *        FLOOR.ODS(number[,significance[,mode]])
     *
     * @param mixed $number Number to round
     * @param mixed $significance Significance
     * @param array<mixed>|int $mode direction to round negative numbers
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     */
    public static function math_ods(mixed $number, mixed $significance = null, mixed $mode = 0)
    {
        return self::math($number, $significance, $mode, true);
    }
    /**
     * FLOOR.PRECISE.
     *
     * Rounds number down, toward zero, to the nearest multiple of significance.
     *
     * Excel Function:
     *        FLOOR.PRECISE(number[,significance])
     *
     * @param array<mixed>|float $number Number to round
     *                      Or can be an array of values
     * @param array<mixed>|float $significance Significance
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded Number, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function precise($number, $significance = 1): array|string|float
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
        return self::arguments_ok_precise((float) $number, (float) $significance);
    }
    /**
     * Avoid Scrutinizer problems concerning complexity.
     */
    private static function arguments_ok_precise(float $number, float $significance): string|float
    {
        if ($significance == 0.0) {
            return Excel_Error::DIV0();
        }
        if ($number == 0.0) {
            return 0.0;
        }
        return floor($number / abs($significance)) * abs($significance);
    }
    /**
     * Avoid Scrutinizer complexity problems.
     *
     * @return float|string Rounded Number, or a string containing an error
     */
    private static function args_ok(float $number, float $significance, int $mode): string|float
    {
        if (!$significance) {
            return Excel_Error::DIV0();
        }
        if (!$number) {
            return 0.0;
        }
        if (self::floor_math_test($number, $significance, $mode)) {
            return ceil($number / $significance) * $significance;
        }
        return floor($number / $significance) * $significance;
    }
    /**
     * Let FLOORMATH complexity pass Scrutinizer.
     */
    private static function floor_math_test(float $number, float $significance, int $mode): bool
    {
        if (Helpers::return_sign($significance) == -1) {
            return true;
        }
        return Helpers::return_sign($number) == -1 && !empty($mode);
    }
    /**
     * Avoid Scrutinizer problems concerning complexity.
     */
    private static function arguments_ok(float $number, float $significance): string|float
    {
        if ($significance == 0.0) {
            return Excel_Error::DIV0();
        }
        if ($number == 0.0) {
            return 0.0;
        }
        $sign_sig = Helpers::return_sign($significance);
        $sign_num = Helpers::return_sign($number);
        if ($sign_sig === 1 && ($sign_num === 1 || Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_GNUMERIC) || $sign_num === -1 && $sign_sig === -1) {
            return floor($number / $significance) * $significance;
        }
        return Excel_Error::NAN();
    }
}