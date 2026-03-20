<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Logical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Operations
{
    use Array_Enabled;
    /**
     * LOGICAL_AND.
     *
     * Returns boolean TRUE if all its arguments are TRUE; returns FALSE if one or more argument is FALSE.
     *
     * Excel Function:
     *        =AND(logical1[,logical2[, ...]])
     *
     *        The arguments must evaluate to logical values such as TRUE or FALSE, or the arguments must be arrays
     *            or references that contain logical values.
     *
     *        Boolean arguments are treated as True or False as appropriate
     *        Integer or floating point arguments are treated as True, except for 0 or 0.0 which are False
     *        If any argument value is a string, or a Null, the function returns a #VALUE! error, unless the string
     *            holds the value TRUE or FALSE, in which case it is evaluated as the corresponding boolean value
     *
     * @param mixed ...$args Data values
     *
     * @return bool|string the logical AND of the arguments
     */
    public static function logical_and(mixed ...$args): bool|string
    {
        return self::count_true_values($args, fn(int $true_value_count, int $count): bool => $true_value_count === $count);
    }
    /**
     * LOGICAL_OR.
     *
     * Returns boolean TRUE if any argument is TRUE; returns FALSE if all arguments are FALSE.
     *
     * Excel Function:
     *        =OR(logical1[,logical2[, ...]])
     *
     *        The arguments must evaluate to logical values such as TRUE or FALSE, or the arguments must be arrays
     *            or references that contain logical values.
     *
     *        Boolean arguments are treated as True or False as appropriate
     *        Integer or floating point arguments are treated as True, except for 0 or 0.0 which are False
     *        If any argument value is a string, or a Null, the function returns a #VALUE! error, unless the string
     *            holds the value TRUE or FALSE, in which case it is evaluated as the corresponding boolean value
     *
     * @param mixed $args Data values
     *
     * @return bool|string the logical OR of the arguments
     */
    public static function logical_or(mixed ...$args): bool|string
    {
        return self::count_true_values($args, fn(int $true_value_count): bool => $true_value_count > 0);
    }
    /**
     * LOGICAL_XOR.
     *
     * Returns the Exclusive Or logical operation for one or more supplied conditions.
     * i.e. the Xor function returns TRUE if an odd number of the supplied conditions evaluate to TRUE,
     *      and FALSE otherwise.
     *
     * Excel Function:
     *        =XOR(logical1[,logical2[, ...]])
     *
     *        The arguments must evaluate to logical values such as TRUE or FALSE, or the arguments must be arrays
     *            or references that contain logical values.
     *
     *        Boolean arguments are treated as True or False as appropriate
     *        Integer or floating point arguments are treated as True, except for 0 or 0.0 which are False
     *        If any argument value is a string, or a Null, the function returns a #VALUE! error, unless the string
     *            holds the value TRUE or FALSE, in which case it is evaluated as the corresponding boolean value
     *
     * @param mixed $args Data values
     *
     * @return bool|string the logical XOR of the arguments
     */
    public static function logical_xor(mixed ...$args): bool|string
    {
        return self::count_true_values($args, fn(int $true_value_count): bool => $true_value_count % 2 === 1);
    }
    /**
     * NOT.
     *
     * Returns the boolean inverse of the argument.
     *
     * Excel Function:
     *        =NOT(logical)
     *
     *        The argument must evaluate to a logical value such as TRUE or FALSE
     *
     *        Boolean arguments are treated as True or False as appropriate
     *        Integer or floating point arguments are treated as True, except for 0 or 0.0 which are False
     *        If any argument value is a string, or a Null, the function returns a #VALUE! error, unless the string
     *            holds the value TRUE or FALSE, in which case it is evaluated as the corresponding boolean value
     *
     * @param mixed $logical A value or expression that can be evaluated to TRUE or FALSE
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool|string the boolean inverse of the argument
     *         If an array of values is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function NOT(mixed $logical = false): array|bool|string
    {
        if (is_array($logical)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $logical);
        }
        if (is_string($logical)) {
            $logical = mb_strtoupper($logical, 'UTF-8');
            if ($logical == 'TRUE' || $logical == Calculation::get_true()) {
                return false;
            }
            if ($logical == 'FALSE' || $logical == Calculation::get_false()) {
                return true;
            }
            return Excel_Error::VALUE();
        }
        return !$logical;
    }
    /**
     * @param mixed[] $args
     * @param callable(int, int): bool $func
     */
    private static function count_true_values(array $args, callable $func): bool|string
    {
        $true_value_count = 0;
        $count = 0;
        $a_args = Functions::flatten_array_indexed($args);
        foreach ($a_args as $k => $arg) {
            ++$count;
            // Is it a boolean value?
            if (is_bool($arg)) {
                $true_value_count += $arg;
            } elseif (is_string($arg)) {
                $is_literal = !Functions::is_cell_value($k);
                $arg = mb_strtoupper($arg, 'UTF-8');
                if ($is_literal && ($arg == 'TRUE' || $arg == Calculation::get_true())) {
                    ++$true_value_count;
                } elseif ($is_literal && ($arg == 'FALSE' || $arg == Calculation::get_false())) {
                    //$trueValueCount += 0;
                } else {
                    --$count;
                }
            } elseif (is_int($arg) || is_float($arg)) {
                $true_value_count += (int) ($arg != 0);
            } else {
                --$count;
            }
        }
        return $count === 0 ? Excel_Error::VALUE() : $func($true_value_count, $count);
    }
}