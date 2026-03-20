<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Deviations
{
    /**
     * DEVSQ.
     *
     * Returns the sum of squares of deviations of data points from their sample mean.
     *
     * Excel Function:
     *        DEVSQ(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function sum_squares(mixed ...$args): string|float
    {
        $a_args = Functions::flatten_array_indexed($args);
        $a_mean = Averages::average($a_args);
        if (!is_numeric($a_mean)) {
            return Excel_Error::NAN();
        }
        // Return value
        $return_value = 0.0;
        $a_count = -1;
        foreach ($a_args as $k => $arg) {
            // Is it a numeric value?
            if (is_bool($arg) && (!Functions::is_cell_value($k) || Functions::get_compatibility_mode() == Functions::COMPATIBILITY_OPENOFFICE)) {
                $arg = (int) $arg;
            }
            if (is_numeric($arg) && !is_string($arg)) {
                $return_value += ($arg - $a_mean) ** 2;
                ++$a_count;
            }
        }
        return $a_count === 0 ? Excel_Error::VALUE() : $return_value;
    }
    /**
     * KURT.
     *
     * Returns the kurtosis of a data set. Kurtosis characterizes the relative peakedness
     * or flatness of a distribution compared with the normal distribution. Positive
     * kurtosis indicates a relatively peaked distribution. Negative kurtosis indicates a
     * relatively flat distribution.
     *
     * @param mixed[] ...$args Data Series
     */
    public static function kurtosis(...$args): string|int|float
    {
        $a_args = Functions::flatten_array_indexed($args);
        $mean = Averages::average($a_args);
        if (!is_numeric($mean)) {
            return Excel_Error::DIV0();
        }
        $std_dev = (float) Standard_Deviations::STDEV($a_args);
        if ($std_dev > 0) {
            $count = $summer = 0;
            foreach ($a_args as $k => $arg) {
                if (is_bool($arg) && !Functions::is_matrix_value($k)) {
                    continue;
                }
                if (!is_numeric($arg)) {
                    continue;
                }
                if (is_string($arg)) {
                    continue;
                }
                $summer += (($arg - $mean) / $std_dev) ** 4;
                ++$count;
            }
            if ($count > 3) {
                return $summer * ($count * ($count + 1) / (($count - 1) * ($count - 2) * ($count - 3))) - 3 * ($count - 1) ** 2 / (($count - 2) * ($count - 3));
            }
        }
        return Excel_Error::DIV0();
    }
    /**
     * SKEW.
     *
     * Returns the skewness of a distribution. Skewness characterizes the degree of asymmetry
     * of a distribution around its mean. Positive skewness indicates a distribution with an
     * asymmetric tail extending toward more positive values. Negative skewness indicates a
     * distribution with an asymmetric tail extending toward more negative values.
     *
     * @param mixed[] ...$args Data Series
     *
     * @return float|int|string The result, or a string containing an error
     */
    public static function skew(...$args): string|int|float
    {
        $a_args = Functions::flatten_array_indexed($args);
        $mean = Averages::average($a_args);
        if (!is_numeric($mean)) {
            return Excel_Error::DIV0();
        }
        $std_dev = Standard_Deviations::STDEV($a_args);
        if ($std_dev === 0.0 || is_string($std_dev)) {
            return Excel_Error::DIV0();
        }
        $count = $summer = 0;
        // Loop through arguments
        foreach ($a_args as $k => $arg) {
            if (is_bool($arg) && !Functions::is_matrix_value($k)) {
            } elseif (!is_numeric($arg)) {
                return Excel_Error::VALUE();
            } else if (!is_string($arg)) {
                $summer += (($arg - $mean) / $std_dev) ** 3;
                ++$count;
            }
        }
        if ($count > 2) {
            return $summer * ($count / (($count - 1) * ($count - 2)));
        }
        return Excel_Error::DIV0();
    }
}