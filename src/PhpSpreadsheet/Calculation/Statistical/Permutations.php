<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig;
use Php_Office\Php_Spreadsheet\Shared\Int_Or_Float;
class Permutations
{
    use Array_Enabled;
    /**
     * PERMUT.
     *
     * Returns the number of permutations for a given number of objects that can be
     *        selected from number objects. A permutation is any set or subset of objects or
     *        events where internal order is significant. Permutations are different from
     *        combinations, for which the internal order is not significant. Use this function
     *        for lottery-style probability calculations.
     *
     * @param mixed $numObjs Integer number of different objects
     *                      Or can be an array of values
     * @param mixed $numInSet Integer number of objects in each permutation
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string Number of permutations, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function PERMUT(mixed $num_objs, mixed $num_in_set): array|string|float|int
    {
        if (is_array($num_objs) || is_array($num_in_set)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $num_objs, $num_in_set);
        }
        try {
            $num_objs = Statistical_Validations::validate_int($num_objs);
            $num_in_set = Statistical_Validations::validate_int($num_in_set);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($num_objs < $num_in_set) {
            return Excel_Error::NAN();
        }
        /** @var float|int|string */
        $result1 = Math_Trig\Factorial::fact($num_objs);
        if (is_string($result1)) {
            return $result1;
        }
        /** @var float|int|string */
        $result2 = Math_Trig\Factorial::fact($num_objs - $num_in_set);
        if (is_string($result2)) {
            return $result2;
        }
        $result = round($result1 / $result2);
        return Int_Or_Float::evaluate($result);
    }
    /**
     * PERMUTATIONA.
     *
     * Returns the number of permutations for a given number of objects (with repetitions)
     *     that can be selected from the total objects.
     *
     * @param mixed $numObjs Integer number of different objects
     *                      Or can be an array of values
     * @param mixed $numInSet Integer number of objects in each permutation
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string Number of permutations, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function PERMUTATIONA(mixed $num_objs, mixed $num_in_set): array|string|float|int
    {
        if (is_array($num_objs) || is_array($num_in_set)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $num_objs, $num_in_set);
        }
        try {
            $num_objs = Statistical_Validations::validate_int($num_objs);
            $num_in_set = Statistical_Validations::validate_int($num_in_set);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($num_objs < 0 || $num_in_set < 0) {
            return Excel_Error::NAN();
        }
        $result = $num_objs ** $num_in_set;
        return Int_Or_Float::evaluate($result);
    }
}