<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Percentiles
{
    public const RANK_SORT_DESCENDING = 0;
    public const RANK_SORT_ASCENDING = 1;
    /**
     * PERCENTILE.
     *
     * Returns the nth percentile of values in a range..
     *
     * Excel Function:
     *        PERCENTILE(value1[,value2[, ...]],entry)
     *
     * @param mixed $args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function PERCENTILE(mixed ...$args): string|float
    {
        $a_args = Functions::flatten_array($args);
        // Calculate
        $entry = array_pop($a_args);
        try {
            $entry = Statistical_Validations::validate_float($entry);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($entry < 0 || $entry > 1) {
            return Excel_Error::NAN();
        }
        $m_args = self::percentile_filter_values($a_args);
        $m_value_count = count($m_args);
        if ($m_value_count > 0) {
            sort($m_args);
            /** @var float[] $mArgs */
            $count = Counts::COUNT($m_args);
            $index = $entry * ($count - 1);
            $index_floor = floor($index);
            $i_base = (int) $index_floor;
            if ($index == $index_floor) {
                return $m_args[$i_base];
            }
            $i_next = $i_base + 1;
            $i_proportion = $index - $i_base;
            return $m_args[$i_base] + ($m_args[$i_next] - $m_args[$i_base]) * $i_proportion;
        }
        return Excel_Error::NAN();
    }
    /**
     * PERCENTRANK.
     *
     * Returns the rank of a value in a data set as a percentage of the data set.
     * Note that the returned rank is simply rounded to the appropriate significant digits,
     *      rather than floored (as MS Excel), so value 3 for a value set of  1, 2, 3, 4 will return
     *      0.667 rather than 0.666
     *
     * @param mixed $valueSet An array of (float) values, or a reference to, a list of numbers
     * @param mixed $value The number whose rank you want to find
     * @param mixed $significance The (integer) number of significant digits for the returned percentage value
     *
     * @return float|string (string if result is an error)
     */
    public static function PERCENTRANK(mixed $value_set, mixed $value, mixed $significance = 3): string|float
    {
        $value_set = Functions::flatten_array($value_set);
        $value = Functions::flatten_single_value($value);
        $significance = $significance === null ? 3 : Functions::flatten_single_value($significance);
        try {
            $value = Statistical_Validations::validate_float($value);
            $significance = Statistical_Validations::validate_int($significance);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $value_set = self::rank_filter_values($value_set);
        $value_count = count($value_set);
        if ($value_count == 0) {
            return Excel_Error::NA();
        }
        sort($value_set, SORT_NUMERIC);
        $value_adjustor = $value_count - 1;
        if ($value < $value_set[0] || $value > $value_set[$value_adjustor]) {
            return Excel_Error::NA();
        }
        $pos = array_search($value, $value_set);
        if ($pos === false) {
            /** @var float[] $valueSet */
            $pos = 0;
            $test_value = $value_set[0];
            while ($test_value < $value) {
                $test_value = $value_set[++$pos];
            }
            --$pos;
            $pos += ($value - $value_set[$pos]) / ($test_value - $value_set[$pos]);
        }
        return round((float) $pos / $value_adjustor, $significance);
    }
    /**
     * QUARTILE.
     *
     * Returns the quartile of a data set.
     *
     * Excel Function:
     *        QUARTILE(value1[,value2[, ...]],entry)
     *
     * @param mixed $args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function QUARTILE(mixed ...$args)
    {
        $a_args = Functions::flatten_array($args);
        $entry = array_pop($a_args);
        try {
            $entry = Statistical_Validations::validate_float($entry);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $entry = floor($entry);
        $entry /= 4;
        if ($entry < 0 || $entry > 1) {
            return Excel_Error::NAN();
        }
        return self::PERCENTILE($a_args, $entry);
    }
    /**
     * RANK.
     *
     * Returns the rank of a number in a list of numbers.
     *
     * @param mixed $value The number whose rank you want to find
     * @param mixed $valueSet An array of float values, or a reference to, a list of numbers
     * @param mixed $order Order to sort the values in the value set
     *
     * @return float|string The result, or a string containing an error (0 = Descending, 1 = Ascending)
     */
    public static function RANK(mixed $value, mixed $value_set, mixed $order = self::RANK_SORT_DESCENDING)
    {
        $value = Functions::flatten_single_value($value);
        $value_set = Functions::flatten_array($value_set);
        $order = $order === null ? self::RANK_SORT_DESCENDING : Functions::flatten_single_value($order);
        try {
            $value = Statistical_Validations::validate_float($value);
            $order = Statistical_Validations::validate_int($order);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $value_set = self::rank_filter_values($value_set);
        if ($order === self::RANK_SORT_DESCENDING) {
            rsort($value_set, SORT_NUMERIC);
        } else {
            sort($value_set, SORT_NUMERIC);
        }
        $pos = array_search($value, $value_set);
        if ($pos === false) {
            return Excel_Error::NA();
        }
        return ++$pos;
    }
    /**
     * @param mixed[] $dataSet
     *
     * @return mixed[]
     */
    protected static function percentile_filter_values(array $data_set): array
    {
        return array_filter($data_set, fn($value): bool => is_numeric($value) && !is_string($value));
    }
    /**
     * @param mixed[] $dataSet
     *
     * @return mixed[]
     */
    protected static function rank_filter_values(array $data_set): array
    {
        return array_filter($data_set, is_numeric(...));
    }
}