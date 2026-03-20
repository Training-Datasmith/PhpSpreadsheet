<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Database;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Internal\Wildcard_Match;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
abstract class Database_Abstract
{
    /**
     * @param mixed[] $database The range of cells that makes up the list or database.
     *                                        A database is a list of related data in which rows of related
     *                                        information are records, and columns of data are fields. The
     *                                        first row of the list contains labels for each column.
     * @param null|array<mixed>|int|string $field Indicates which column is used in the function. Enter the
     *                                        column label enclosed between double quotation marks, such as
     *                                        "Age" or "Yield," or a number (without quotation marks) that
     *                                        represents the position of the column within the list: 1 for
     *                                        the first column, 2 for the second column, and so on.
     * @param mixed[] $criteria The range of cells that contains the conditions you specify.
     *                                        You can use any range for the criteria argument, as long as it
     *                                        includes at least one column label and at least one cell below
     *                                        the column label in which you specify a condition for the
     *                                        column.
     */
    abstract public static function evaluate(array $database, array|null|int|string $field, array $criteria): null|float|int|string;
    /**
     * fieldExtract.
     *
     * Extracts the column ID to use for the data field.
     *
     * @param mixed[] $database The range of cells that makes up the list or database.
     *                                        A database is a list of related data in which rows of related
     *                                        information are records, and columns of data are fields. The
     *                                        first row of the list contains labels for each column.
     * @param mixed $field Indicates which column is used in the function. Enter the
     *                                        column label enclosed between double quotation marks, such as
     *                                        "Age" or "Yield," or a number (without quotation marks) that
     *                                        represents the position of the column within the list: 1 for
     *                                        the first column, 2 for the second column, and so on.
     */
    protected static function field_extract(array $database, mixed $field): ?int
    {
        /** @var ?string */
        $single = Functions::flatten_single_value($field);
        $field = strtoupper($single ?? '');
        if ($field === '') {
            return null;
        }
        /** @var callable */
        $callable = 'strtoupper';
        $field_names = array_map($callable, array_shift($database));
        //* @phpstan-ignore-line
        if (is_numeric($field)) {
            $field = (int) $field - 1;
            if ($field < 0 || $field >= count($field_names)) {
                return null;
            }
            return $field;
        }
        $key = array_search($field, array_values($field_names), true);
        return $key !== false ? (int) $key : null;
    }
    /**
     * filter.
     *
     * Parses the selection criteria, extracts the database rows that match those criteria, and
     * returns that subset of rows.
     *
     * @param mixed[] $database The range of cells that makes up the list or database.
     *                                        A database is a list of related data in which rows of related
     *                                        information are records, and columns of data are fields. The
     *                                        first row of the list contains labels for each column.
     * @param mixed[][] $criteria The range of cells that contains the conditions you specify.
     *                                        You can use any range for the criteria argument, as long as it
     *                                        includes at least one column label and at least one cell below
     *                                        the column label in which you specify a condition for the
     *                                        column.
     *
     * @return mixed[]
     */
    protected static function filter(array $database, array $criteria): array
    {
        /** @var mixed[] */
        $field_names = array_shift($database);
        $criteria_names = array_shift($criteria);
        //    Convert the criteria into a set of AND/OR conditions with [:placeholders]
        /** @var string[] $criteriaNames */
        $query = self::build_query($criteria_names, $criteria);
        //    Loop through each row of the database
        /** @var mixed[][] $criteriaNames */
        return self::execute_query($database, $query, $criteria_names, $field_names);
    }
    /**
     * @param mixed[] $database The range of cells that makes up the list or database
     * @param mixed[][] $criteria
     *
     * @return mixed[]
     */
    protected static function get_filtered_column(array $database, ?int $field, array $criteria): array
    {
        //    reduce the database to a set of rows that match all the criteria
        $database = self::filter($database, $criteria);
        $default_return_column_value = $field === null ? 1 : null;
        //    extract an array of values for the requested column
        $column_data = [];
        /** @var mixed[] $row */
        foreach ($database as $row_key => $row) {
            $keys = array_keys($row);
            $key = $field === null ? null : $keys[$field] ?? null;
            $column_key = $key ?? 'A';
            $column_data[$row_key][$column_key] = $key === null ? $default_return_column_value : $row[$key] ?? $default_return_column_value;
        }
        return $column_data;
    }
    /**
     * @param string[] $criteriaNames
     * @param mixed[][] $criteria
     */
    private static function build_query(array $criteria_names, array $criteria): string
    {
        $base_query = [];
        foreach ($criteria as $key => $criterion) {
            foreach ($criterion as $field => $value) {
                $criterion_name = $criteria_names[$field];
                if ($value !== null) {
                    $condition = self::build_condition($value, $criterion_name);
                    $base_query[$key][] = $condition;
                }
            }
        }
        $row_query = array_map(
            fn(array $row_value): string => count($row_value) > 1 ? 'AND(' . implode(',', $row_value) . ')' : $row_value[0] ?? '',
            // @phpstan-ignore-line
            $base_query
        );
        return count($row_query) > 1 ? 'OR(' . implode(',', $row_query) . ')' : $row_query[0] ?? '';
    }
    private static function build_condition(mixed $criterion, string $criterion_name): string
    {
        $if_condition = Functions::if_condition($criterion);
        // Check for wildcard characters used in the condition
        $result = preg_match('/(?<operator>[^"]*)(?<operand>".*[*?].*")/ui', $if_condition, $matches);
        if ($result !== 1) {
            return "[:{$criterion_name}]{$if_condition}";
        }
        $true_false = $matches['operator'] !== '<>';
        $wildcard = Wildcard_Match::wildcard($matches['operand']);
        $condition = "WILDCARDMATCH([:{$criterion_name}],{$wildcard})";
        if ($true_false === false) {
            return "NOT({$condition})";
        }
        return $condition;
    }
    /**
     * @param mixed[] $database
     * @param mixed[][] $criteria
     * @param array<mixed> $fields
     *
     * @return mixed[]
     */
    private static function execute_query(array $database, string $query, array $criteria, array $fields): array
    {
        foreach ($database as $data_row => $data_values) {
            //    Substitute actual values from the database row for our [:placeholders]
            $conditions = $query;
            foreach ($criteria as $criterion) {
                /** @var string $criterion */
                /** @var mixed[] $dataValues */
                $conditions = self::process_condition($criterion, $fields, $data_values, $conditions);
            }
            //    evaluate the criteria against the row data
            $result = Calculation::get_instance()->_calculate_formula_value('=' . $conditions);
            //    If the row failed to meet the criteria, remove it from the database
            if ($result !== true) {
                unset($database[$data_row]);
            }
        }
        return $database;
    }
    /**
     * @param array<mixed> $fields
     * @param array<mixed> $dataValues
     */
    private static function process_condition(string $criterion, array $fields, array $data_values, string $conditions): string
    {
        $key = array_search($criterion, $fields, true);
        $data_value = 'NULL';
        if (is_bool($data_values[$key])) {
            $data_value = $data_values[$key] ? 'TRUE' : 'FALSE';
        } elseif ($data_values[$key] !== null) {
            $data_value = $data_values[$key];
            // escape quotes if we have a string containing quotes
            if (is_string($data_value) && str_contains($data_value, '"')) {
                $data_value = str_replace('"', '""', $data_value);
            }
            if (is_string($data_value)) {
                $data_value = Calculation::wrap_result(strtoupper($data_value));
            }
            $data_value = String_Helper::convert_to_string($data_value);
        }
        return str_replace('[:' . $criterion . ']', $data_value, $conditions);
    }
}