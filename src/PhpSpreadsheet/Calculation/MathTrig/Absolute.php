<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Absolute
{
    use Array_Enabled;
    /**
     * ABS.
     *
     * Returns the result of builtin function abs after validating args.
     *
     * @param mixed $number Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|int|string rounded number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function evaluate(mixed $number): array|string|int|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return abs($number);
    }
}