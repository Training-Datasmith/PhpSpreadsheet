<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Statistical;
class Factorial
{
    use Array_Enabled;
    /**
     * FACT.
     *
     * Returns the factorial of a number.
     * The factorial of a number is equal to 1*2*3*...* number.
     *
     * Excel Function:
     *        FACT(factVal)
     *
     * @param array<mixed>|float $factVal Factorial Value, or can be an array of numbers
     *
     * @return array<mixed>|float|int|string Factorial, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function fact($fact_val): array|string|float|int
    {
        if (is_array($fact_val)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $fact_val);
        }
        try {
            $fact_val = Helpers::validate_numeric_null_bool($fact_val);
            Helpers::validate_not_negative($fact_val);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $fact_loop = floor($fact_val);
        if ($fact_val > $fact_loop) {
            if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_GNUMERIC) {
                return Statistical\Distributions\Gamma::gamma_value($fact_val + 1);
            }
        }
        $factorial = 1;
        while ($fact_loop > 1) {
            $factorial *= $fact_loop--;
        }
        return $factorial;
    }
    /**
     * FACTDOUBLE.
     *
     * Returns the double factorial of a number.
     *
     * Excel Function:
     *        FACTDOUBLE(factVal)
     *
     * @param array<mixed>|float $factVal Factorial Value, or can be an array of numbers
     *
     * @return array<mixed>|float|int|string Double Factorial, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function fact_double($fact_val): array|string|float|int
    {
        if (is_array($fact_val)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $fact_val);
        }
        try {
            $fact_val = Helpers::validate_numeric_null_substitution($fact_val, 0);
            Helpers::validate_not_negative($fact_val);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $fact_loop = floor($fact_val);
        $factorial = 1;
        while ($fact_loop > 1) {
            $factorial *= $fact_loop;
            $fact_loop -= 2;
        }
        return $factorial;
    }
    /**
     * MULTINOMIAL.
     *
     * Returns the ratio of the factorial of a sum of values to the product of factorials.
     *
     * @param mixed[] $args An array of mixed values for the Data Series
     *
     * @return float|int|string The result, or a string containing an error
     */
    public static function multinomial(...$args): string|int|float
    {
        $summer = 0;
        $divisor = 1;
        try {
            // Loop through arguments
            foreach (Functions::flatten_array($args) as $argx) {
                $arg = Helpers::validate_numeric_null_substitution($argx, null);
                Helpers::validate_not_negative($arg);
                $arg = (int) $arg;
                $summer += $arg;
                /** @var float|int */
                $temp = self::fact($arg);
                $divisor *= $temp;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        $summer = self::fact($summer);
        return is_numeric($summer) ? $summer / $divisor : Excel_Error::VALUE();
    }
}