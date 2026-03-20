<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Logarithms
{
    use Array_Enabled;
    /**
     * LOG_BASE.
     *
     * Returns the logarithm of a number to a specified base. The default base is 10.
     *
     * Excel Function:
     *        LOG(number[,base])
     *
     * @param mixed $number The positive real number for which you want the logarithm
     *                      Or can be an array of values
     * @param mixed $base The base of the logarithm. If base is omitted, it is assumed to be 10.
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function with_base(mixed $number, mixed $base = 10): array|string|float
    {
        if (is_array($number) || is_array($base)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $base);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            Helpers::validate_positive($number);
            $base = Helpers::validate_numeric_null_bool($base);
            Helpers::validate_positive($base);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return log($number, $base);
    }
    /**
     * LOG10.
     *
     * Returns the result of builtin function log after validating args.
     *
     * @param mixed $number Should be numeric
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded number
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function base10(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            Helpers::validate_positive($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return log10($number);
    }
    /**
     * LN.
     *
     * Returns the result of builtin function log after validating args.
     *
     * @param mixed $number Should be numeric
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Rounded number
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function natural(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
            Helpers::validate_positive($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return log($number);
    }
}