<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Matrix\Builder;
use Matrix\Div0Exception as MatrixDiv0Exception;
use Matrix\Exception as MatrixException;
use Matrix\Matrix;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Matrix_Functions
{
    /**
     * Convert parameter to Matrix.
     *
     * @param mixed $matrixValues A matrix of values
     */
    private static function get_matrix(mixed $matrix_values): Matrix
    {
        $matrix_data = [];
        if (!is_array($matrix_values)) {
            $matrix_values = [[$matrix_values]];
        }
        $row = 0;
        foreach ($matrix_values as $matrix_row) {
            if (!is_array($matrix_row)) {
                $matrix_row = [$matrix_row];
            }
            $column = 0;
            foreach ($matrix_row as $matrix_cell) {
                if (is_string($matrix_cell) || $matrix_cell === null) {
                    throw new Exception(Excel_Error::VALUE());
                }
                $matrix_data[$row][$column] = $matrix_cell;
                ++$column;
            }
            ++$row;
        }
        return new Matrix($matrix_data);
    }
    /**
     * SEQUENCE.
     *
     * Generates a list of sequential numbers in an array.
     *
     * Excel Function:
     *      SEQUENCE(rows,[columns],[start],[step])
     *
     * @param mixed $rows the number of rows to return, defaults to 1
     * @param mixed $columns the number of columns to return, defaults to 1
     * @param mixed $start the first number in the sequence, defaults to 1
     * @param mixed $step the amount to increment each subsequent value in the array, defaults to 1
     *
     * @return array<mixed>|string The resulting array, or a string containing an error
     */
    public static function sequence(mixed $rows = 1, mixed $columns = 1, mixed $start = 1, mixed $step = 1): string|array
    {
        try {
            $rows = (int) Helpers::validate_numeric_null_substitution($rows, 1);
            Helpers::validate_positive($rows);
            $columns = (int) Helpers::validate_numeric_null_substitution($columns, 1);
            Helpers::validate_positive($columns);
            $start = Helpers::validate_numeric_null_substitution($start, 1);
            $step = Helpers::validate_numeric_null_substitution($step, 1);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($step === 0) {
            return array_chunk(array_fill(0, $rows * $columns, $start), max($columns, 1));
        }
        return array_chunk(range($start, $start + ($rows * $columns - 1) * $step, $step), max($columns, 1));
    }
    /**
     * MDETERM.
     *
     * Returns the matrix determinant of an array.
     *
     * Excel Function:
     *        MDETERM(array)
     *
     * @param mixed $matrixValues A matrix of values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function determinant(mixed $matrix_values)
    {
        try {
            $matrix = self::get_matrix($matrix_values);
            return $matrix->determinant();
        } catch (Matrix_Exception) {
            return Excel_Error::VALUE();
        } catch (Exception $e) {
            return $e->get_message();
        }
    }
    /**
     * MINVERSE.
     *
     * Returns the inverse matrix for the matrix stored in an array.
     *
     * Excel Function:
     *        MINVERSE(array)
     *
     * @param mixed $matrixValues A matrix of values
     *
     * @return array<mixed>|string The result, or a string containing an error
     */
    public static function inverse(mixed $matrix_values): array|string
    {
        try {
            $matrix = self::get_matrix($matrix_values);
            return $matrix->inverse()->to_array();
        } catch (Matrix_Div0exception) {
            return Excel_Error::NAN();
        } catch (Matrix_Exception) {
            return Excel_Error::VALUE();
        } catch (Exception $e) {
            return $e->get_message();
        }
    }
    /**
     * MMULT.
     *
     * @param mixed $matrixData1 A matrix of values
     * @param mixed $matrixData2 A matrix of values
     *
     * @return array<mixed>|string The result, or a string containing an error
     */
    public static function multiply(mixed $matrix_data1, mixed $matrix_data2): array|string
    {
        try {
            $matrix_a = self::get_matrix($matrix_data1);
            $matrix_b = self::get_matrix($matrix_data2);
            return $matrix_a->multiply($matrix_b)->to_array();
        } catch (Matrix_Exception) {
            return Excel_Error::VALUE();
        } catch (Exception $e) {
            return $e->get_message();
        }
    }
    /**
     * MUnit.
     *
     * @param mixed $dimension Number of rows and columns
     *
     * @return array<mixed>|string The result, or a string containing an error
     */
    public static function identity(mixed $dimension)
    {
        try {
            $dimension = (int) Helpers::validate_numeric_null_bool($dimension);
            Helpers::validate_positive($dimension, Excel_Error::VALUE());
            return Builder::create_identity_matrix($dimension, 0)->to_array();
        } catch (Exception $e) {
            return $e->get_message();
        }
    }
}