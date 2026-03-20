<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Base
{
    use Array_Enabled;
    /**
     * BASE.
     *
     * Converts a number into a text representation with the given radix (base).
     *
     * Excel Function:
     *        BASE(Number, Radix [Min_length])
     *
     * @param mixed $number expect float
     *                      Or can be an array of values
     * @param mixed $radix expect float
     *                      Or can be an array of values
     * @param mixed $minLength expect int or null
     *                      Or can be an array of values
     *
     * @return array<mixed>|string the text representation with the given radix (base)
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function evaluate(mixed $number, mixed $radix, mixed $min_length = null): array|string
    {
        if (is_array($number) || is_array($radix) || is_array($min_length)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $radix, $min_length);
        }
        try {
            $number = floor(Helpers::validate_numeric_null_bool($number));
            $radix = (int) Helpers::validate_numeric_null_bool($radix);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return self::calculate($number, $radix, $min_length);
    }
    private static function calculate(float $number, int $radix, mixed $min_length): string
    {
        if ($min_length === null || is_numeric($min_length)) {
            if ($number < 0 || $number >= 2 ** 53 || $radix < 2 || $radix > 36) {
                return Excel_Error::NAN();
                // Numeric range constraints
            }
            $outcome = strtoupper(base_convert("{$number}", 10, $radix));
            if ($min_length !== null) {
                $outcome = str_pad($outcome, (int) $min_length, '0', STR_PAD_LEFT);
                // String padding
            }
            return $outcome;
        }
        return Excel_Error::VALUE();
    }
}