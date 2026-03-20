<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Sqrt
{
    use Array_Enabled;
    /**
     * SQRT.
     *
     * Returns the result of builtin function sqrt after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string square root
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function sqrt(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(sqrt($number));
    }
    /**
     * SQRTPI.
     *
     * Returns the square root of (number * pi).
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string Square Root of Number * Pi, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function pi($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_substitution($number, 0);
            Helpers::validate_not_negative($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return sqrt($number * M_PI);
    }
}