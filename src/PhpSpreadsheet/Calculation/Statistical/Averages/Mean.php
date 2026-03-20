<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Averages;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig;
use Php_Office\Php_Spreadsheet\Calculation\Statistical\Averages;
use Php_Office\Php_Spreadsheet\Calculation\Statistical\Counts;
use Php_Office\Php_Spreadsheet\Calculation\Statistical\Minimum;
class Mean
{
    /**
     * GEOMEAN.
     *
     * Returns the geometric mean of an array or range of positive data. For example, you
     *        can use GEOMEAN to calculate average growth rate given compound interest with
     *        variable rates.
     *
     * Excel Function:
     *        GEOMEAN(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function geometric(mixed ...$args): float|int|string
    {
        $a_args = Functions::flatten_array($args);
        $a_mean = Math_Trig\Operations::product($a_args);
        if (is_numeric($a_mean) && $a_mean > 0) {
            $a_count = Counts::COUNT($a_args);
            if (Minimum::min($a_args) > 0) {
                return $a_mean ** (1 / $a_count);
            }
        }
        return Excel_Error::NAN();
    }
    /**
     * HARMEAN.
     *
     * Returns the harmonic mean of a data set. The harmonic mean is the reciprocal of the
     *        arithmetic mean of reciprocals.
     *
     * Excel Function:
     *        HARMEAN(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function harmonic(mixed ...$args): string|float|int
    {
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        if (Minimum::min($a_args) < 0) {
            return Excel_Error::NAN();
        }
        $return_value = 0;
        $a_count = 0;
        foreach ($a_args as $arg) {
            // Is it a numeric value?
            if (is_numeric($arg) && !is_string($arg)) {
                if ($arg <= 0) {
                    return Excel_Error::NAN();
                }
                $return_value += 1 / $arg;
                ++$a_count;
            }
        }
        // Return
        if ($a_count > 0) {
            return 1 / ($return_value / $a_count);
        }
        return Excel_Error::NA();
    }
    /**
     * TRIMMEAN.
     *
     * Returns the mean of the interior of a data set. TRIMMEAN calculates the mean
     *        taken by excluding a percentage of data points from the top and bottom tails
     *        of a data set.
     *
     * Excel Function:
     *        TRIMEAN(value1[,value2[, ...]], $discard)
     *
     * @param mixed $args Data values
     */
    public static function trim(mixed ...$args): float|string
    {
        $a_args = Functions::flatten_array($args);
        // Calculate
        $percent = array_pop($a_args);
        if (is_numeric($percent) && !is_string($percent)) {
            if ($percent < 0 || $percent > 1) {
                return Excel_Error::NAN();
            }
            $m_args = [];
            foreach ($a_args as $arg) {
                // Is it a numeric value?
                if (is_numeric($arg) && !is_string($arg)) {
                    $m_args[] = $arg;
                }
            }
            $discard = floor(Counts::COUNT($m_args) * $percent / 2);
            sort($m_args);
            for ($i = 0; $i < $discard; ++$i) {
                array_pop($m_args);
                array_shift($m_args);
            }
            return Averages::average($m_args);
        }
        return Excel_Error::VALUE();
    }
}