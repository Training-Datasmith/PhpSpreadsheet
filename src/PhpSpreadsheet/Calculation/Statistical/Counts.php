<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcException;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Counts extends Aggregate_Base
{
    /**
     * COUNT.
     *
     * Counts the number of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        COUNT(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function COUNT(mixed ...$args): int
    {
        $return_value = 0;
        // Loop through arguments
        $a_args = Functions::flatten_array_indexed($args);
        foreach ($a_args as $k => $arg) {
            $arg = self::test_accepted_boolean($arg, $k);
            // Is it a numeric value?
            // Strings containing numeric values are only counted if they are string literals (not cell values)
            //    and then only in MS Excel and in Open Office, not in Gnumeric
            if (self::is_accepted_countable($arg, $k, true)) {
                ++$return_value;
            }
        }
        return $return_value;
    }
    /**
     * COUNTA.
     *
     * Counts the number of cells that are not empty within the list of arguments
     *
     * Excel Function:
     *        COUNTA(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function COUNTA(mixed ...$args): int
    {
        $return_value = 0;
        // Loop through arguments
        $a_args = Functions::flatten_array_indexed($args);
        foreach ($a_args as $k => $arg) {
            // Nulls are counted if literals, but not if cell values
            if ($arg !== null || !Functions::is_cell_value($k)) {
                ++$return_value;
            }
        }
        return $return_value;
    }
    /**
     * COUNTBLANK.
     *
     * Counts the number of empty cells within the list of arguments
     *
     * Excel Function:
     *        COUNTBLANK(value1[,value2[, ...]])
     *
     * @param mixed $range Data values
     */
    public static function COUNTBLANK(mixed $range): int
    {
        if ($range === null) {
            return 1;
        }
        if (!is_array($range) || array_key_exists(0, $range)) {
            throw new Calc_Exception('Must specify range of cells, not any kind of literal');
        }
        $return_value = 0;
        // Loop through arguments
        $a_args = Functions::flatten_array($range);
        foreach ($a_args as $arg) {
            // Is it a blank cell?
            if ($arg === null || is_string($arg) && $arg == '') {
                ++$return_value;
            }
        }
        return $return_value;
    }
}