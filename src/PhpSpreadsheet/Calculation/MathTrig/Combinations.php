<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Combinations
{
    use Array_Enabled;
    /**
     * COMBIN.
     *
     * Returns the number of combinations for a given number of items. Use COMBIN to
     *        determine the total possible number of groups for a given number of items.
     *
     * Excel Function:
     *        COMBIN(numObjs,numInSet)
     *
     * @param mixed $numObjs Number of different objects, or can be an array of numbers
     * @param mixed $numInSet Number of objects in each combination, or can be an array of numbers
     *
     * @return array<mixed>|float|string Number of combinations, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function without_repetition(mixed $num_objs, mixed $num_in_set): array|string|float
    {
        if (is_array($num_objs) || is_array($num_in_set)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $num_objs, $num_in_set);
        }
        try {
            $num_objs = Helpers::validate_numeric_null_substitution($num_objs, null);
            $num_in_set = Helpers::validate_numeric_null_substitution($num_in_set, null);
            Helpers::validate_not_negative($num_in_set);
            Helpers::validate_not_negative($num_objs - $num_in_set);
        } catch (Exception $e) {
            return $e->get_message();
        }
        /** @var float */
        $quotient = Factorial::fact($num_objs);
        /** @var float */
        $divisor1 = Factorial::fact($num_objs - $num_in_set);
        /** @var float */
        $divisor2 = Factorial::fact($num_in_set);
        return round($quotient / ($divisor1 * $divisor2));
    }
    /**
     * COMBINA.
     *
     * Returns the number of combinations for a given number of items. Use COMBIN to
     *        determine the total possible number of groups for a given number of items.
     *
     * Excel Function:
     *        COMBINA(numObjs,numInSet)
     *
     * @param mixed $numObjs Number of different objects, or can be an array of numbers
     * @param mixed $numInSet Number of objects in each combination, or can be an array of numbers
     *
     * @return array<mixed>|float|int|string Number of combinations, or a string containing an error
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function with_repetition(mixed $num_objs, mixed $num_in_set): array|int|string|float
    {
        if (is_array($num_objs) || is_array($num_in_set)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $num_objs, $num_in_set);
        }
        try {
            $num_objs = Helpers::validate_numeric_null_substitution($num_objs, null);
            $num_in_set = Helpers::validate_numeric_null_substitution($num_in_set, null);
            Helpers::validate_not_negative($num_in_set);
            Helpers::validate_not_negative($num_objs);
            $num_objs = (int) $num_objs;
            $num_in_set = (int) $num_in_set;
            // Microsoft documentation says following is true, but Excel
            //  does not enforce this restriction.
            //Helpers::validateNotNegative($numObjs - $numInSet);
            if ($num_objs === 0) {
                Helpers::validate_not_negative(-$num_in_set);
                return 1;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        /** @var float */
        $quotient = Factorial::fact($num_objs + $num_in_set - 1);
        /** @var float */
        $divisor1 = Factorial::fact($num_objs - 1);
        /** @var float */
        $divisor2 = Factorial::fact($num_in_set);
        return round($quotient / ($divisor1 * $divisor2));
    }
}