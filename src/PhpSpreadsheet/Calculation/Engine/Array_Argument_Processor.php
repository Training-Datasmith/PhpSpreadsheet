<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Array_Argument_Processor
{
    private static Array_Argument_Helper $array_argument_helper;
    /** @return mixed[] */
    public static function process_arguments(Array_Argument_Helper $array_argument_helper, callable $method, mixed ...$arguments): array
    {
        self::$array_argument_helper = $array_argument_helper;
        if (self::$array_argument_helper->has_array_argument() === false) {
            return [$method(...$arguments)];
        }
        if (self::$array_argument_helper->array_arguments() === 1) {
            $nth_argument = self::$array_argument_helper->get_first_array_argument_number();
            return self::evaluate_nth_argument_as_array($method, $nth_argument, ...$arguments);
        }
        $single_row_vector_index = self::$array_argument_helper->get_single_row_vector();
        $single_column_vector_index = self::$array_argument_helper->get_single_column_vector();
        if ($single_row_vector_index !== null && $single_column_vector_index !== null) {
            // Basic logic for a single row vector and a single column vector
            return self::evaluate_vector_pair($method, $single_row_vector_index, $single_column_vector_index, ...$arguments);
        }
        $matrix_pair = self::$array_argument_helper->get_matrix_pair();
        if ($matrix_pair !== []) {
            if (self::$array_argument_helper->is_vector($matrix_pair[0]) === true && self::$array_argument_helper->is_vector($matrix_pair[1]) === false || self::$array_argument_helper->is_vector($matrix_pair[0]) === false && self::$array_argument_helper->is_vector($matrix_pair[1]) === true) {
                // Logic for a matrix and a vector (row or column)
                return self::evaluate_vector_matrix_pair($method, $matrix_pair, ...$arguments);
            }
            // Logic for matrix/matrix, column vector/column vector or row vector/row vector
            return self::evaluate_matrix_pair($method, $matrix_pair, ...$arguments);
        }
        // Still need to work out the logic for more than two array arguments,
        // For the moment, we're throwing an Exception when we initialise the ArrayArgumentHelper
        return ['#VALUE!'];
    }
    /**
     * @param int[] $matrixIndexes
     *
     * @return mixed[]
     */
    private static function evaluate_vector_matrix_pair(callable $method, array $matrix_indexes, mixed ...$arguments): array
    {
        $matrix2 = array_pop($matrix_indexes) ?? throw new Exception('empty array 2');
        /** @var mixed[][] $matrixValues2 */
        $matrix_values2 = $arguments[$matrix2];
        $matrix1 = array_pop($matrix_indexes) ?? throw new Exception('empty array 1');
        /** @var mixed[][] $matrixValues1 */
        $matrix_values1 = $arguments[$matrix1];
        /** @var non-empty-array<int> */
        $matrix12 = [$matrix1, $matrix2];
        $rows = min(array_map(self::$array_argument_helper->row_count(...), $matrix12));
        $columns = min(array_map(self::$array_argument_helper->column_count(...), $matrix12));
        if ($rows === 1) {
            $rows = max(array_map(self::$array_argument_helper->row_count(...), $matrix12));
        }
        if ($columns === 1) {
            $columns = max(array_map(self::$array_argument_helper->column_count(...), $matrix12));
        }
        $result = [];
        for ($row_index = 0; $row_index < $rows; ++$row_index) {
            for ($column_index = 0; $column_index < $columns; ++$column_index) {
                $row_index1 = self::$array_argument_helper->is_row_vector($matrix1) ? 0 : $row_index;
                $column_index1 = self::$array_argument_helper->is_column_vector($matrix1) ? 0 : $column_index;
                $value1 = $matrix_values1[$row_index1][$column_index1];
                $row_index2 = self::$array_argument_helper->is_row_vector($matrix2) ? 0 : $row_index;
                $column_index2 = self::$array_argument_helper->is_column_vector($matrix2) ? 0 : $column_index;
                $value2 = $matrix_values2[$row_index2][$column_index2];
                $arguments[$matrix1] = $value1;
                $arguments[$matrix2] = $value2;
                $result[$row_index][$column_index] = $method(...$arguments);
            }
        }
        return $result;
    }
    /**
     * @param array<int|string> $matrixIndexes
     *
     * @return mixed[]
     */
    private static function evaluate_matrix_pair(callable $method, array $matrix_indexes, mixed ...$arguments): array
    {
        $matrix2 = array_pop($matrix_indexes);
        /** @var mixed[][] $matrixValues2 */
        $matrix_values2 = $arguments[$matrix2];
        $matrix1 = array_pop($matrix_indexes);
        /** @var mixed[][] $matrixValues1 */
        $matrix_values1 = $arguments[$matrix1];
        $result = [];
        foreach ($matrix_values1 as $row_index => $row) {
            foreach ($row as $column_index => $value1) {
                if (isset($matrix_values2[$row_index][$column_index]) === false) {
                    continue;
                }
                $value2 = $matrix_values2[$row_index][$column_index];
                $arguments[$matrix1] = $value1;
                $arguments[$matrix2] = $value2;
                $result[$row_index][$column_index] = $method(...$arguments);
            }
        }
        return $result;
    }
    /** @return mixed[] */
    private static function evaluate_vector_pair(callable $method, int $row_index, int $column_index, mixed ...$arguments): array
    {
        $row_vector = Functions::flatten_array($arguments[$row_index]);
        $column_vector = Functions::flatten_array($arguments[$column_index]);
        $result = [];
        foreach ($column_vector as $column) {
            $row_results = [];
            foreach ($row_vector as $row) {
                $arguments[$row_index] = $row;
                $arguments[$column_index] = $column;
                $row_results[] = $method(...$arguments);
            }
            $result[] = $row_results;
        }
        return $result;
    }
    /**
     * Note, offset is from 1 (for the first argument) rather than from 0.
     *
     * @return mixed[]
     */
    private static function evaluate_nth_argument_as_array(callable $method, int $nth_argument, mixed ...$arguments): array
    {
        $values = array_slice($arguments, $nth_argument - 1, 1);
        /** @var mixed[] $values */
        $values = array_pop($values);
        $result = [];
        foreach ($values as $value) {
            $arguments[$nth_argument - 1] = $value;
            $result[] = $method(...$arguments);
        }
        return $result;
    }
}