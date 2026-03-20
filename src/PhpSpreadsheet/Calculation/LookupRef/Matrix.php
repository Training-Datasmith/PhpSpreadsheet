<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Matrix
{
    use Array_Enabled;
    /**
     * Helper function; NOT an implementation of any Excel Function.
     *
     * @param mixed[] $values
     */
    public static function is_column_vector(array $values): bool
    {
        return count($values, COUNT_RECURSIVE) === count($values, COUNT_NORMAL) * 2;
    }
    /**
     * Helper function; NOT an implementation of any Excel Function.
     *
     * @param mixed[] $values
     */
    public static function is_row_vector(array $values): bool
    {
        return count($values, COUNT_RECURSIVE) > 1 && (count($values, COUNT_NORMAL) === 1 || count($values, COUNT_RECURSIVE) === count($values, COUNT_NORMAL));
    }
    /**
     * TRANSPOSE.
     *
     * @param mixed $matrixData A matrix of values
     *
     * @return mixed[]
     */
    public static function transpose($matrix_data): array
    {
        $return_matrix = [];
        if (!is_array($matrix_data)) {
            $matrix_data = [[$matrix_data]];
        }
        if (!is_array(end($matrix_data))) {
            $matrix_data = [$matrix_data];
        }
        $column = 0;
        /** @var mixed[][] $matrixData */
        foreach ($matrix_data as $matrix_row) {
            $row = 0;
            foreach ($matrix_row as $matrix_cell) {
                $return_matrix[$row][$column] = $matrix_cell;
                ++$row;
            }
            ++$column;
        }
        return $return_matrix;
    }
    /**
     * INDEX.
     *
     * Uses an index to choose a value from a reference or array
     *
     * Excel Function:
     *        =INDEX(range_array, row_num, [column_num], [area_num])
     *
     * @param mixed $matrix A range of cells or an array constant
     * @param mixed $rowNum The row in the array or range from which to return a value.
     *                          If row_num is omitted, column_num is required.
     *                      Or can be an array of values
     * @param mixed $columnNum The column in the array or range from which to return a value.
     *                          If column_num is omitted, row_num is required.
     *                      Or can be an array of values
     *
     * TODO Provide support for area_num, currently not supported
     *
     * @return mixed the value of a specified cell or array of cells
     *         If an array of values is passed as the $rowNum and/or $columnNum arguments, then the returned result
     *            will also be an array with the same dimensions
     */
    public static function index(mixed $matrix, mixed $row_num = 0, mixed $column_num = null): mixed
    {
        if (is_array($row_num) || is_array($column_num)) {
            return self::evaluate_array_arguments_subset_from([self::class, __FUNCTION__], 1, $matrix, $row_num, $column_num);
        }
        $row_num ??= 0;
        $column_num ??= 0;
        if (is_scalar($matrix)) {
            if ($row_num === 0 || $row_num === 1) {
                if ($column_num === 0 || $column_num === 1) {
                    if ($column_num === 1 || $row_num === 1) {
                        return $matrix;
                    }
                }
            }
        }
        try {
            $row_num = Lookup_Ref_Validations::validate_positive_int($row_num);
            $column_num = Lookup_Ref_Validations::validate_positive_int($column_num);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (is_array($matrix) && count($matrix) === 1 && $row_num > 1) {
            $matrix_key = array_keys($matrix)[0];
            if (is_array($matrix[$matrix_key])) {
                $temp_matrix = [];
                foreach ($matrix[$matrix_key] as $key => $value) {
                    $temp_matrix[$key] = [$value];
                }
                $matrix = $temp_matrix;
            }
        }
        if (!is_array($matrix) || $row_num > count($matrix)) {
            return Excel_Error::REF();
        }
        $row_keys = array_keys($matrix);
        $column_keys = @array_keys($matrix[$row_keys[0]]);
        //* @phpstan-ignore-line
        if ($column_num > count($column_keys)) {
            return Excel_Error::REF();
        }
        if ($column_num === 0) {
            return self::extract_row_value($matrix, $row_keys, $row_num);
        }
        $column_num = $column_keys[--$column_num];
        //* @phpstan-ignore-line
        if ($row_num === 0) {
            return array_map(fn($value): array => [$value], array_column($matrix, $column_num));
        }
        $row_num = $row_keys[--$row_num];
        //* @phpstan-ignore-line
        /** @var mixed[][] $matrix */
        return $matrix[$row_num][$column_num];
    }
    /**
     * @param mixed[] $matrix
     * @param array<int, int> $rowKeys
     */
    private static function extract_row_value(array $matrix, array $row_keys, int $row_num): mixed
    {
        if ($row_num === 0) {
            return $matrix;
        }
        $row_num = $row_keys[--$row_num];
        $row = $matrix[$row_num];
        if (is_array($row)) {
            return [$row_num => $row];
        }
        return $row;
    }
}