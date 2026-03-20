<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Lcm
{
    /**
     *  Private method to return an array of the factors of the input value.
     *
     * @return int[]
     */
    private static function factors(float $value): array
    {
        $start_val = floor(sqrt($value));
        $factor_array = [];
        for ($i = $start_val; $i > 1; --$i) {
            if ($value % $i == 0) {
                $factor_array = array_merge($factor_array, self::factors($value / $i));
                $factor_array = array_merge($factor_array, self::factors($i));
                if ($i <= sqrt($value)) {
                    break;
                }
            }
        }
        if (!empty($factor_array)) {
            rsort($factor_array);
            /** @var int[] $factorArray */
            return $factor_array;
        }
        return [(int) $value];
    }
    /**
     * LCM.
     *
     * Returns the lowest common multiplier of a series of numbers
     * The least common multiple is the smallest positive integer that is a multiple
     * of all integer arguments number1, number2, and so on. Use LCM to add fractions
     * with different denominators.
     *
     * Excel Function:
     *        LCM(number1[,number2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return int|string Lowest Common Multiplier, or a string containing an error
     */
    public static function evaluate(mixed ...$args): int|string
    {
        try {
            $array_args = [];
            $any_zeros = 0;
            $any_non_nulls = 0;
            foreach (Functions::flatten_array($args) as $value1) {
                $any_non_nulls += (int) ($value1 !== null);
                $value = Helpers::validate_numeric_null_substitution($value1, 1);
                Helpers::validate_not_negative($value);
                $array_args[] = (int) $value;
                $any_zeros += (int) !(bool) $value;
            }
            self::test_non_nulls($any_non_nulls);
            if ($any_zeros) {
                return 0;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        $return_value = 1;
        $all_powered_factors = [];
        // Loop through arguments
        foreach ($array_args as $value) {
            $my_factors = self::factors(floor($value));
            $my_counted_factors = array_count_values($my_factors);
            $my_powered_factors = [];
            foreach ($my_counted_factors as $my_counted_factor => $my_counted_power) {
                $my_powered_factors[$my_counted_factor] = $my_counted_factor ** $my_counted_power;
            }
            self::process_powered_factors($all_powered_factors, $my_powered_factors);
        }
        foreach ($all_powered_factors as $all_powered_factor) {
            /** @var scalar $allPoweredFactor */
            $return_value *= (int) $all_powered_factor;
        }
        return $return_value;
    }
    /**
     * @param mixed[] $allPoweredFactors
     * @param mixed[] $myPoweredFactors
     */
    private static function process_powered_factors(array &$all_powered_factors, array &$my_powered_factors): void
    {
        foreach ($my_powered_factors as $my_powered_value => $my_powered_factor) {
            if (isset($all_powered_factors[$my_powered_value])) {
                if ($all_powered_factors[$my_powered_value] < $my_powered_factor) {
                    $all_powered_factors[$my_powered_value] = $my_powered_factor;
                }
            } else {
                $all_powered_factors[$my_powered_value] = $my_powered_factor;
            }
        }
    }
    private static function test_non_nulls(int $any_non_nulls): void
    {
        if (!$any_non_nulls) {
            throw new Exception(Excel_Error::VALUE());
        }
    }
}