<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Helpers;
class Cosecant
{
    use Array_Enabled;
    /**
     * CSC.
     *
     * Returns the cosecant of an angle.
     *
     * @param array<mixed>|float $angle Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The cosecant of the angle
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function csc($angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::very_small_denominator(1.0, sin($angle));
    }
    /**
     * CSCH.
     *
     * Returns the hyperbolic cosecant of an angle.
     *
     * @param array<mixed>|float $angle Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The hyperbolic cosecant of the angle
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function csch($angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::very_small_denominator(1.0, sinh($angle));
    }
}