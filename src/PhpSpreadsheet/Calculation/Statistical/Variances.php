<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Variances extends Variance_Base
{
    /**
     * VAR.
     *
     * Estimates variance based on a sample.
     *
     * Excel Function:
     *        VAR(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string (string if result is an error)
     */
    public static function VAR(mixed ...$args): float|string
    {
        $return_value = Excel_Error::DIV0();
        $summer_a = $summer_b = 0.0;
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        $a_count = 0;
        foreach ($a_args as $arg) {
            $arg = self::datatype_adjustment_booleans($arg);
            // Is it a numeric value?
            if (is_numeric($arg) && !is_string($arg)) {
                $summer_a += $arg * $arg;
                $summer_b += $arg;
                ++$a_count;
            }
        }
        if ($a_count > 1) {
            $summer_a *= $a_count;
            $summer_b *= $summer_b;
            return ($summer_a - $summer_b) / ($a_count * ($a_count - 1));
        }
        return $return_value;
    }
    /**
     * VARA.
     *
     * Estimates variance based on a sample, including numbers, text, and logical values
     *
     * Excel Function:
     *        VARA(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string (string if result is an error)
     */
    public static function VARA(mixed ...$args): string|float
    {
        $return_value = Excel_Error::DIV0();
        $summer_a = $summer_b = 0.0;
        // Loop through arguments
        $a_args = Functions::flatten_array_indexed($args);
        $a_count = 0;
        foreach ($a_args as $k => $arg) {
            if (is_string($arg) && Functions::is_value($k)) {
                return Excel_Error::VALUE();
            }
            if (is_string($arg) && !Functions::is_matrix_value($k)) {
            } else if (is_numeric($arg) || is_bool($arg) || is_string($arg) && $arg != '') {
                $arg = self::datatype_adjustment_allow_strings($arg);
                $summer_a += $arg * $arg;
                $summer_b += $arg;
                ++$a_count;
            }
        }
        if ($a_count > 1) {
            $summer_a *= $a_count;
            $summer_b *= $summer_b;
            return ($summer_a - $summer_b) / ($a_count * ($a_count - 1));
        }
        return $return_value;
    }
    /**
     * VARP.
     *
     * Calculates variance based on the entire population
     *
     * Excel Function:
     *        VARP(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string (string if result is an error)
     */
    public static function VARP(mixed ...$args): float|string
    {
        // Return value
        $return_value = Excel_Error::DIV0();
        $summer_a = $summer_b = 0.0;
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        $a_count = 0;
        foreach ($a_args as $arg) {
            $arg = self::datatype_adjustment_booleans($arg);
            // Is it a numeric value?
            if (is_numeric($arg) && !is_string($arg)) {
                $summer_a += $arg * $arg;
                $summer_b += $arg;
                ++$a_count;
            }
        }
        if ($a_count > 0) {
            $summer_a *= $a_count;
            $summer_b *= $summer_b;
            return ($summer_a - $summer_b) / ($a_count * $a_count);
        }
        return $return_value;
    }
    /**
     * VARPA.
     *
     * Calculates variance based on the entire population, including numbers, text, and logical values
     *
     * Excel Function:
     *        VARPA(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string (string if result is an error)
     */
    public static function VARPA(mixed ...$args): string|float
    {
        $return_value = Excel_Error::DIV0();
        $summer_a = $summer_b = 0.0;
        // Loop through arguments
        $a_args = Functions::flatten_array_indexed($args);
        $a_count = 0;
        foreach ($a_args as $k => $arg) {
            if (is_string($arg) && Functions::is_value($k)) {
                return Excel_Error::VALUE();
            }
            if (is_string($arg) && !Functions::is_matrix_value($k)) {
            } else if (is_numeric($arg) || is_bool($arg) || is_string($arg) && $arg != '') {
                $arg = self::datatype_adjustment_allow_strings($arg);
                $summer_a += $arg * $arg;
                $summer_b += $arg;
                ++$a_count;
            }
        }
        if ($a_count > 0) {
            $summer_a *= $a_count;
            $summer_b *= $summer_b;
            return ($summer_a - $summer_b) / ($a_count * $a_count);
        }
        return $return_value;
    }
}