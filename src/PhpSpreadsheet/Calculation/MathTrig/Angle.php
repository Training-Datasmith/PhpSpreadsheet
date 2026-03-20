<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Angle
{
    use Array_Enabled;
    /**
     * DEGREES.
     *
     * Returns the result of builtin function rad2deg after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string Rounded number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_degrees(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return rad2deg($number);
    }
    /**
     * RADIANS.
     *
     * Returns the result of builtin function deg2rad after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string Rounded number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_radians(mixed $number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return deg2rad($number);
    }
}