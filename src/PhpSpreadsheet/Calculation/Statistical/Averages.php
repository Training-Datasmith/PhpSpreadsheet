<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Averages extends Aggregate_Base
{
    /**
     * AVEDEV.
     *
     * Returns the average of the absolute deviations of data points from their mean.
     * AVEDEV is a measure of the variability in a data set.
     *
     * Excel Function:
     *        AVEDEV(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string (string if result is an error)
     */
    public static function average_deviations(mixed ...$args): string|float
    {
        $a_args = Functions::flatten_array_indexed($args);
        // Return value
        $return_value = 0.0;
        $a_mean = self::average(...$args);
        if ($a_mean === Excel_Error::DIV0()) {
            return Excel_Error::NAN();
        }
        if ($a_mean === Excel_Error::VALUE()) {
            return Excel_Error::VALUE();
        }
        $a_count = 0;
        foreach ($a_args as $k => $arg) {
            $arg = self::test_accepted_boolean($arg, $k);
            // Is it a numeric value?
            // Strings containing numeric values are only counted if they are string literals (not cell values)
            //    and then only in MS Excel and in Open Office, not in Gnumeric
            if (is_string($arg) && !is_numeric($arg) && !Functions::is_cell_value($k)) {
                return Excel_Error::VALUE();
            }
            if (self::is_accepted_countable($arg, $k)) {
                /** @var float|int|numeric-string $arg */
                /** @var float|int|numeric-string $aMean */
                $return_value += abs($arg - $a_mean);
                ++$a_count;
            }
        }
        // Return
        if ($a_count === 0) {
            return Excel_Error::DIV0();
        }
        return $return_value / $a_count;
    }
    /**
     * AVERAGE.
     *
     * Returns the average (arithmetic mean) of the arguments
     *
     * Excel Function:
     *        AVERAGE(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|int|string (string if result is an error)
     */
    public static function average(mixed ...$args): string|int|float
    {
        $return_value = $a_count = 0;
        // Loop through arguments
        foreach (Functions::flatten_array_indexed($args) as $k => $arg) {
            $arg = self::test_accepted_boolean($arg, $k);
            // Is it a numeric value?
            // Strings containing numeric values are only counted if they are string literals (not cell values)
            //    and then only in MS Excel and in Open Office, not in Gnumeric
            if (is_string($arg) && !is_numeric($arg) && !Functions::is_cell_value($k)) {
                return Excel_Error::VALUE();
            }
            if (self::is_accepted_countable($arg, $k)) {
                /** @var float|int|numeric-string $arg */
                $return_value += $arg;
                ++$a_count;
            }
        }
        // Return
        if ($a_count > 0) {
            return $return_value / $a_count;
        }
        return Excel_Error::DIV0();
    }
    /**
     * AVERAGEA.
     *
     * Returns the average of its arguments, including numbers, text, and logical values
     *
     * Excel Function:
     *        AVERAGEA(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|int|string (string if result is an error)
     */
    public static function average_a(mixed ...$args): string|int|float
    {
        $return_value = null;
        $a_count = 0;
        // Loop through arguments
        foreach (Functions::flatten_array_indexed($args) as $k => $arg) {
            if (is_numeric($arg)) {
                // do nothing
            } elseif (is_bool($arg)) {
                $arg = (int) $arg;
            } elseif (!Functions::is_matrix_value($k)) {
                $arg = 0;
            } else {
                return Excel_Error::VALUE();
            }
            $return_value += $arg;
            ++$a_count;
        }
        if ($a_count > 0) {
            return $return_value / $a_count;
        }
        return Excel_Error::DIV0();
    }
    /**
     * MEDIAN.
     *
     * Returns the median of the given numbers. The median is the number in the middle of a set of numbers.
     *
     * Excel Function:
     *        MEDIAN(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function median(mixed ...$args): float|string
    {
        $a_args = Functions::flatten_array($args);
        $return_value = Excel_Error::NAN();
        /** @var array<float|int> */
        $a_args = self::filter_arguments($a_args);
        $value_count = count($a_args);
        if ($value_count > 0) {
            sort($a_args, SORT_NUMERIC);
            $value_count = $value_count / 2;
            if ($value_count == floor($value_count)) {
                $return_value = ($a_args[$value_count--] + $a_args[$value_count]) / 2;
                //* @phpstan-ignore-line
            } else {
                $value_count = (int) floor($value_count);
                $return_value = $a_args[$value_count];
            }
        }
        return $return_value;
    }
    /**
     * MODE.
     *
     * Returns the most frequently occurring, or repetitive, value in an array or range of data
     *
     * Excel Function:
     *        MODE(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function mode(mixed ...$args): float|string
    {
        $return_value = Excel_Error::NA();
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        $a_args = self::filter_arguments($a_args);
        if (!empty($a_args)) {
            return self::mode_calc($a_args);
        }
        return $return_value;
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    protected static function filter_arguments(array $args): array
    {
        return array_filter(
            $args,
            // Is it a numeric value?
            fn($value): bool => is_numeric($value) && !is_string($value)
        );
    }
    /**
     * Special variant of array_count_values that isn't limited to strings and integers,
     * but can work with floating point numbers as values.
     *
     * @param mixed[] $data
     */
    private static function mode_calc(array $data): float|string
    {
        $frequency_array = [];
        $index = 0;
        $maxfreq = 0;
        $maxfreqkey = '';
        $maxfreqdatum = '';
        foreach ($data as $datum) {
            /** @var float|string $datum */
            $found = false;
            ++$index;
            foreach ($frequency_array as $key => $value) {
                /** @var string[] $value */
                if ($value['value'] == (string) $datum) {
                    ++$frequency_array[$key]['frequency'];
                    $freq = $frequency_array[$key]['frequency'];
                    if ($freq > $maxfreq) {
                        $maxfreq = $freq;
                        $maxfreqkey = $key;
                        $maxfreqdatum = $datum;
                    } elseif ($freq == $maxfreq) {
                        if ($frequency_array[$key]['index'] < $frequency_array[$maxfreqkey]['index']) {
                            //* @phpstan-ignore-line
                            $maxfreqkey = $key;
                            $maxfreqdatum = $datum;
                        }
                    }
                    $found = true;
                    break;
                }
            }
            if ($found === false) {
                $frequency_array[] = ['value' => $datum, 'frequency' => 1, 'index' => $index];
            }
        }
        if ($maxfreq <= 1) {
            return Excel_Error::NA();
        }
        return $maxfreqdatum;
    }
}