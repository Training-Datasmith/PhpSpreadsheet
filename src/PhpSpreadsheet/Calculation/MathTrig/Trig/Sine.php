<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Helpers;
class Sine
{
    use Array_Enabled;
    /**
     * SIN.
     *
     * Returns the result of builtin function sin after validating args.
     *
     * @param mixed $angle Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string sine
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function sin(mixed $angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return sin($angle);
    }
    /**
     * SINH.
     *
     * Returns the result of builtin function sinh after validating args.
     *
     * @param mixed $angle Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string hyperbolic sine
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function sinh(mixed $angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return sinh($angle);
    }
    /**
     * ASIN.
     *
     * Returns the arcsine of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The arcsine of the number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function asin($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(asin($number));
    }
    /**
     * ASINH.
     *
     * Returns the inverse hyperbolic sine of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The inverse hyperbolic sine of the number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function asinh($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(asinh($number));
    }
}