<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Gcd
{
    /**
     * Recursively determine GCD.
     *
     * Returns the greatest common divisor of a series of numbers.
     * The greatest common divisor is the largest integer that divides both
     *        number1 and number2 without a remainder.
     *
     * Excel Function:
     *        GCD(number1[,number2[, ...]])
     */
    private static function evaluate_gcd(float|int $a, float|int $b): float|int
    {
        return $b ? self::evaluate_gcd($b, $a % $b) : $a;
    }
    /**
     * GCD.
     *
     * Returns the greatest common divisor of a series of numbers.
     * The greatest common divisor is the largest integer that divides both
     *        number1 and number2 without a remainder.
     *
     * Excel Function:
     *        GCD(number1[,number2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|int|string Greatest Common Divisor, or a string containing an error
     */
    public static function evaluate(mixed ...$args): string|float|int
    {
        try {
            $array_args = [];
            foreach (Functions::flatten_array($args) as $value1) {
                if ($value1 !== null) {
                    $value = Helpers::validate_numeric_null_substitution($value1, 1);
                    Helpers::validate_not_negative($value);
                    $array_args[] = (int) $value;
                }
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (count($array_args) <= 0) {
            return Excel_Error::VALUE();
        }
        $gcd = array_pop($array_args);
        do {
            $gcd = self::evaluate_gcd($gcd, (int) array_pop($array_args));
        } while (!empty($array_args));
        return $gcd;
    }
}