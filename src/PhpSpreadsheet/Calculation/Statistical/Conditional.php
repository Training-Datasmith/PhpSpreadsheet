<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Database\D_Average;
use Php_Office\Php_Spreadsheet\Calculation\Database\D_Count;
use Php_Office\Php_Spreadsheet\Calculation\Database\D_Max;
use Php_Office\Php_Spreadsheet\Calculation\Database\D_Min;
use Php_Office\Php_Spreadsheet\Calculation\Database\D_Sum;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcException;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Conditional
{
    private const CONDITION_COLUMN_NAME = 'CONDITION';
    private const VALUE_COLUMN_NAME = 'VALUE';
    private const CONDITIONAL_COLUMN_NAME = 'CONDITIONAL %d';
    /**
     * AVERAGEIF.
     *
     * Returns the average value from a range of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        AVERAGEIF(range,condition[, average_range])
     *
     * @param mixed $range Data values, expect array
     * @param null|mixed[]|string $condition the criteria that defines which cells will be checked
     * @param mixed $averageRange Data values
     */
    public static function AVERAGEIF(mixed $range, null|array|string $condition, mixed $average_range = []): null|int|float|string
    {
        if (!is_array($range) || !is_array($average_range) || array_key_exists(0, $range) || array_key_exists(0, $average_range)) {
            $ref_error = Excel_Error::REF();
            if (in_array($ref_error, [$range, $average_range], true)) {
                return $ref_error;
            }
            throw new Calc_Exception('Must specify range of cells, not any kind of literal');
        }
        $database = self::database_from_range_and_value($range, $average_range);
        $condition = Functions::flatten_single_value($condition);
        $condition = [[self::CONDITION_COLUMN_NAME, self::VALUE_COLUMN_NAME], [$condition, null]];
        return D_Average::evaluate($database, self::VALUE_COLUMN_NAME, $condition);
    }
    /**
     * AVERAGEIFS.
     *
     * Counts the number of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        AVERAGEIFS(average_range, criteria_range1, criteria1, [criteria_range2, criteria2]…)
     *
     * @param mixed $args Pairs of Ranges and Criteria
     */
    public static function AVERAGEIFS(mixed ...$args): null|int|float|string
    {
        if (empty($args)) {
            return 0.0;
        }
        if (count($args) === 3) {
            return self::AVERAGEIF($args[1], $args[2], $args[0]);
            //* @phpstan-ignore-line
        }
        foreach ($args as $arg) {
            if (is_array($arg) && array_key_exists(0, $arg)) {
                throw new Calc_Exception('Must specify range of cells, not any kind of literal');
            }
        }
        $conditions = self::build_condition_set_for_value_range(...$args);
        $database = self::build_database_with_value_range(...$args);
        return D_Average::evaluate($database, self::VALUE_COLUMN_NAME, $conditions);
    }
    /**
     * COUNTIF.
     *
     * Counts the number of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        COUNTIF(range,condition)
     *
     * @param mixed $range Data values, expect array
     * @param null|mixed[]|string $condition the criteria that defines which cells will be counted
     */
    public static function COUNTIF(mixed $range, null|array|string $condition): string|int
    {
        if (!is_array($range) || array_key_exists(0, $range)) {
            if ($range === Excel_Error::REF()) {
                return $range;
            }
            throw new Calc_Exception('Must specify range of cells, not any kind of literal');
        }
        // Filter out any empty values that shouldn't be included in a COUNT
        $range = array_filter(Functions::flatten_array($range), fn($value): bool => $value !== null && $value !== '');
        $range = array_merge([[self::CONDITION_COLUMN_NAME]], array_chunk($range, 1));
        $condition = Functions::flatten_single_value($condition);
        $condition = array_merge([[self::CONDITION_COLUMN_NAME]], [[$condition]]);
        return D_Count::evaluate($range, null, $condition, false);
    }
    /**
     * COUNTIFS.
     *
     * Counts the number of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        COUNTIFS(criteria_range1, criteria1, [criteria_range2, criteria2]…)
     *
     * @param mixed $args Pairs of Ranges and Criteria
     */
    public static function COUNTIFS(mixed ...$args): int|string
    {
        if (empty($args)) {
            return 0;
        }
        if (count($args) === 2) {
            return self::COUNTIF(...$args);
        }
        $database = self::build_database(...$args);
        $conditions = self::build_condition_set(...$args);
        return D_Count::evaluate($database, null, $conditions, false);
    }
    /**
     * MAXIFS.
     *
     * Returns the maximum value within a range of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        MAXIFS(max_range, criteria_range1, criteria1, [criteria_range2, criteria2]…)
     *
     * @param mixed $args Pairs of Ranges and Criteria
     */
    public static function MAXIFS(mixed ...$args): null|float|string
    {
        if (empty($args)) {
            return 0.0;
        }
        $conditions = self::build_condition_set_for_value_range(...$args);
        $database = self::build_database_with_value_range(...$args);
        return D_Max::evaluate($database, self::VALUE_COLUMN_NAME, $conditions, false);
    }
    /**
     * MINIFS.
     *
     * Returns the minimum value within a range of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        MINIFS(min_range, criteria_range1, criteria1, [criteria_range2, criteria2]…)
     *
     * @param mixed $args Pairs of Ranges and Criteria
     */
    public static function MINIFS(mixed ...$args): null|float|string
    {
        if (empty($args)) {
            return 0.0;
        }
        $conditions = self::build_condition_set_for_value_range(...$args);
        $database = self::build_database_with_value_range(...$args);
        return D_Min::evaluate($database, self::VALUE_COLUMN_NAME, $conditions, false);
    }
    /**
     * SUMIF.
     *
     * Totals the values of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        SUMIF(range, criteria, [sum_range])
     *
     * @param mixed $range Data values, expecting array
     * @param mixed $sumRange Data values, expecting array
     */
    public static function SUMIF(mixed $range, mixed $condition, mixed $sum_range = []): null|float|string
    {
        if (!is_array($range) || array_key_exists(0, $range) || !is_array($sum_range) || array_key_exists(0, $sum_range)) {
            $ref_error = Excel_Error::REF();
            if (in_array($ref_error, [$range, $sum_range], true)) {
                return $ref_error;
            }
            throw new Calc_Exception('Must specify range of cells, not any kind of literal');
        }
        $database = self::database_from_range_and_value($range, $sum_range);
        $condition = Functions::flatten_single_value($condition);
        $condition = [[self::CONDITION_COLUMN_NAME, self::VALUE_COLUMN_NAME], [$condition, null]];
        return D_Sum::evaluate($database, self::VALUE_COLUMN_NAME, $condition);
    }
    /**
     * SUMIFS.
     *
     * Counts the number of cells that contain numbers within the list of arguments
     *
     * Excel Function:
     *        SUMIFS(average_range, criteria_range1, criteria1, [criteria_range2, criteria2]…)
     *
     * @param mixed $args Pairs of Ranges and Criteria
     */
    public static function SUMIFS(mixed ...$args): null|float|string
    {
        if (empty($args)) {
            return 0.0;
        }
        if (count($args) === 3) {
            return self::SUMIF($args[1], $args[2], $args[0]);
        }
        $conditions = self::build_condition_set_for_value_range(...$args);
        $database = self::build_database_with_value_range(...$args);
        return D_Sum::evaluate($database, self::VALUE_COLUMN_NAME, $conditions);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[][]
     */
    private static function build_condition_set(...$args): array
    {
        $conditions = self::build_conditions(1, ...$args);
        return array_map(null, ...$conditions);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[][]
     */
    private static function build_condition_set_for_value_range(...$args): array
    {
        $conditions = self::build_conditions(2, ...$args);
        if (count($conditions) === 1) {
            return array_map(fn($value): array => [$value], $conditions[0]);
        }
        return array_map(null, ...$conditions);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[][]
     */
    private static function build_conditions(int $start_offset, ...$args): array
    {
        $conditions = [];
        $pair_count = 1;
        $argument_count = count($args);
        for ($argument = $start_offset; $argument < $argument_count; $argument += 2) {
            $conditions[] = array_merge([sprintf(self::CONDITIONAL_COLUMN_NAME, $pair_count)], [$args[$argument]]);
            ++$pair_count;
        }
        return $conditions;
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    private static function build_database(...$args): array
    {
        $database = [];
        return self::build_data_set(0, $database, ...$args);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    private static function build_database_with_value_range(...$args): array
    {
        $database = [];
        $database[] = array_merge([self::VALUE_COLUMN_NAME], Functions::flatten_array($args[0]));
        return self::build_data_set(1, $database, ...$args);
    }
    /**
     * @param mixed[][] $database
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    private static function build_data_set(int $start_offset, array $database, ...$args): array
    {
        $pair_count = 1;
        $argument_count = count($args);
        for ($argument = $start_offset; $argument < $argument_count; $argument += 2) {
            $database[] = array_merge([sprintf(self::CONDITIONAL_COLUMN_NAME, $pair_count)], Functions::flatten_array($args[$argument]));
            ++$pair_count;
        }
        return array_map(null, ...$database);
    }
    /**
     * @param mixed[] $range
     * @param mixed[] $valueRange
     *
     * @return mixed[]
     */
    private static function database_from_range_and_value(array $range, array $value_range = []): array
    {
        $range = Functions::flatten_array($range);
        $value_range = Functions::flatten_array($value_range);
        if (empty($value_range)) {
            $value_range = $range;
        }
        return array_map(null, array_merge([self::CONDITION_COLUMN_NAME], $range), array_merge([self::VALUE_COLUMN_NAME], $value_range));
    }
}