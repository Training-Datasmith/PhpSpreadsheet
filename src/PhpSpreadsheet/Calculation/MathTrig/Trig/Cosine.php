<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Helpers;
class Cosine
{
    use Array_Enabled;
    /**
     * COS.
     *
     * Returns the result of builtin function cos after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string cosine
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function cos(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return cos($number);
    }
    /**
     * COSH.
     *
     * Returns the result of builtin function cosh after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string hyperbolic cosine
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function cosh(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return cosh($number);
    }
    /**
     * ACOS.
     *
     * Returns the arccosine of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The arccosine of the number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function acos($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(acos($number));
    }
    /**
     * ACOSH.
     *
     * Returns the arc inverse hyperbolic cosine of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The inverse hyperbolic cosine of the number, or an error string
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function acosh($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(acosh($number));
    }
}