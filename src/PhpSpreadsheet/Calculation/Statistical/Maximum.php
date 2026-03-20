<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
class Maximum extends Max_Min_Base
{
    /**
     * MAX.
     *
     * MAX returns the value of the element of the values passed that has the highest value,
     *        with negative numbers considered smaller than positive numbers.
     *
     * Excel Function:
     *        MAX(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function max(mixed ...$args): float|int|string
    {
        $return_value = null;
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        foreach ($a_args as $arg) {
            if (Error_Value::is_error($arg, true)) {
                $return_value = $arg;
                break;
            }
            // Is it a numeric value?
            if (is_numeric($arg) && !is_string($arg)) {
                if ($return_value === null || $arg > $return_value) {
                    $return_value = $arg;
                }
            }
        }
        if ($return_value === null) {
            return 0;
        }
        /** @var float|int|string $returnValue */
        return $return_value;
    }
    /**
     * MAXA.
     *
     * Returns the greatest value in a list of arguments, including numbers, text, and logical values
     *
     * Excel Function:
     *        MAXA(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function max_a(mixed ...$args): float|int|string
    {
        $return_value = null;
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        foreach ($a_args as $arg) {
            if (Error_Value::is_error($arg, true)) {
                $return_value = $arg;
                break;
            }
            // Is it a numeric value?
            if (is_numeric($arg) || is_bool($arg) || is_string($arg) && $arg != '') {
                $arg = self::datatype_adjustment_allow_strings($arg);
                if ($return_value === null || $arg > $return_value) {
                    $return_value = $arg;
                }
            }
        }
        if ($return_value === null) {
            return 0;
        }
        /** @var float|int|string $returnValue */
        return $return_value;
    }
}