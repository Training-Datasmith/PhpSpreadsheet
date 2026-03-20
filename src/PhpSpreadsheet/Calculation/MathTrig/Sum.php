<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Sum
{
    /**
     * SUM, ignoring non-numeric non-error strings. This is eventually used by SUMIF.
     *
     * SUM computes the sum of all the values and cells referenced in the argument list.
     *
     * Excel Function:
     *        SUM(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function sum_ignoring_strings(mixed ...$args): float|int|string
    {
        $return_value = 0;
        // Loop through the arguments
        foreach (Functions::flatten_array($args) as $arg) {
            // Is it a numeric value?
            if (is_numeric($arg)) {
                $return_value += $arg;
            } elseif (Error_Value::is_error($arg)) {
                /** @var string $arg */
                return $arg;
            }
        }
        return $return_value;
    }
    /**
     * SUM, returning error for non-numeric strings. This is used by Excel SUM function.
     *
     * SUM computes the sum of all the values and cells referenced in the argument list.
     *
     * Excel Function:
     *        SUM(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return array<mixed>|float|int|string
     */
    public static function sum_erroring_strings(mixed ...$args): float|int|string|array
    {
        $return_value = 0;
        // Loop through the arguments
        $a_args = Functions::flatten_array_indexed($args);
        foreach ($a_args as $k => $arg) {
            // Is it a numeric value?
            if (is_numeric($arg)) {
                $return_value += $arg;
            } elseif (is_bool($arg)) {
                $return_value += (int) $arg;
            } elseif (Error_Value::is_error($arg, true)) {
                /** @var string $arg */
                return $arg;
            } elseif ($arg !== null && !Functions::is_cell_value($k)) {
                // ignore non-numerics from cell, but fail as literals (except null)
                return Excel_Error::VALUE();
            }
        }
        return $return_value;
    }
    /**
     * SUMPRODUCT.
     *
     * Excel Function:
     *        SUMPRODUCT(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|int|string The result, or a string containing an error
     */
    public static function product(mixed ...$args): string|int|float
    {
        $array_list = $args;
        $wrk_array = Functions::flatten_array(array_shift($array_list));
        $wrk_cell_count = count($wrk_array);
        for ($i = 0; $i < $wrk_cell_count; ++$i) {
            if (!is_numeric($wrk_array[$i]) || is_string($wrk_array[$i])) {
                $wrk_array[$i] = 0;
            }
        }
        foreach ($array_list as $matrix_data) {
            $array2 = Functions::flatten_array($matrix_data);
            $count = count($array2);
            if ($wrk_cell_count != $count) {
                return Excel_Error::VALUE();
            }
            foreach ($array2 as $i => $val) {
                if (!is_numeric($val) || is_string($val)) {
                    $val = 0;
                }
                /** @var array<float|int> $wrkArray */
                $wrk_array[$i] *= $val;
            }
        }
        /** @var array<float|int> $wrkArray */
        return array_sum($wrk_array);
    }
}