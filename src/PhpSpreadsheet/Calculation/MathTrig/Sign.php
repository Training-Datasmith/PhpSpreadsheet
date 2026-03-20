<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Sign
{
    use Array_Enabled;
    /**
     * SIGN.
     *
     * Determines the sign of a number. Returns 1 if the number is positive, zero (0)
     *        if the number is 0, and -1 if the number is negative.
     *
     * @param array<mixed>|float $number Number to round, or can be an array of numbers
     *
     * @return array<mixed>|int|string sign value, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function evaluate($number): array|string|int
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::return_sign($number);
    }
}