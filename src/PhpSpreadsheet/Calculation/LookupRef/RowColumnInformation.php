<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Row_Column_Information
{
    /**
     * Test if cellAddress is null or whitespace string.
     *
     * @param null|mixed[]|string $cellAddress A reference to a range of cells
     */
    private static function cell_address_null_or_whitespace($cell_address): bool
    {
        return $cell_address === null || !is_array($cell_address) && trim($cell_address) === '';
    }
    private static function cell_column(?Cell $cell): int
    {
        return $cell !== null ? Coordinate::column_index_from_string($cell->get_column()) : 1;
    }
    /**
     * COLUMN.
     *
     * Returns the column number of the given cell reference
     *     If the cell reference is a range of cells, COLUMN returns the column numbers of each column
     *        in the reference as a horizontal array.
     *     If cell reference is omitted, and the function is being called through the calculation engine,
     *        then it is assumed to be the reference of the cell in which the COLUMN function appears;
     *        otherwise this function returns 1.
     *
     * Excel Function:
     *        =COLUMN([cellAddress])
     *
     * @param null|mixed[]|string $cellAddress A reference to a range of cells for which you want the column numbers
     *
     * @return int|int[]|string
     */
    public static function COLUMN($cell_address = null, ?Cell $cell = null): int|string|array
    {
        if (self::cell_address_null_or_whitespace($cell_address)) {
            return self::cell_column($cell);
        }
        if (is_array($cell_address)) {
            foreach ($cell_address as $column_key => $value) {
                $column_key = (string) preg_replace('/[^a-z]/i', '', (string) $column_key);
                return Coordinate::column_index_from_string($column_key);
            }
            return self::cell_column($cell);
        }
        $cell_address ??= '';
        if ($cell != null) {
            [, , $sheet_name] = Helpers::extract_worksheet($cell_address, $cell);
            [, , $cell_address] = Helpers::extract_cell_addresses($cell_address, true, $cell->get_worksheet(), $sheet_name);
        }
        [, $cell_address] = Worksheet::extract_sheet_title($cell_address, true);
        $cell_address ??= '';
        if (str_contains($cell_address, ':')) {
            [$start_address, $end_address] = explode(':', $cell_address);
            $start_address = (string) preg_replace('/[^a-z]/i', '', $start_address);
            $end_address = (string) preg_replace('/[^a-z]/i', '', $end_address);
            return range(Coordinate::column_index_from_string($start_address), Coordinate::column_index_from_string($end_address));
        }
        $cell_address = (string) preg_replace('/[^a-z]/i', '', $cell_address);
        try {
            return Coordinate::column_index_from_string($cell_address);
        } catch (Spreadsheet_Exception) {
            return Excel_Error::NAME();
        }
    }
    /**
     * COLUMNS.
     *
     * Returns the number of columns in an array or reference.
     *
     * Excel Function:
     *        =COLUMNS(cellAddress)
     *
     * @param null|mixed[]|string $cellAddress An array or array formula, or a reference to a range of cells
     *                                          for which you want the number of columns
     *
     * @return int|string The number of columns in cellAddress, or a string if arguments are invalid
     */
    public static function COLUMNS($cell_address = null)
    {
        if (self::cell_address_null_or_whitespace($cell_address)) {
            return 1;
        }
        if (is_string($cell_address) && Error_Value::is_error($cell_address, true)) {
            return $cell_address;
        }
        if (!is_array($cell_address)) {
            return Excel_Error::VALUE();
        }
        $is_matrix = is_numeric(array_key_first($cell_address));
        [$columns, $rows] = Calculation::get_matrix_dimensions($cell_address);
        if ($is_matrix) {
            return $rows;
        }
        return $columns;
    }
    private static function cell_row(?Cell $cell): int|string
    {
        return $cell !== null ? self::convert0to_name($cell->get_row()) : 1;
    }
    private static function convert0to_name(int|string $result): int|string
    {
        if (is_int($result) && ($result <= 0 || $result > Address_Range::MAX_ROW)) {
            return Excel_Error::NAME();
        }
        return $result;
    }
    /**
     * ROW.
     *
     * Returns the row number of the given cell reference
     *     If the cell reference is a range of cells, ROW returns the row numbers of each row in the reference
     *        as a vertical array.
     *     If cell reference is omitted, and the function is being called through the calculation engine,
     *        then it is assumed to be the reference of the cell in which the ROW function appears;
     *        otherwise this function returns 1.
     *
     * Excel Function:
     *        =ROW([cellAddress])
     *
     * @param null|mixed[][]|string $cellAddress A reference to a range of cells for which you want the row numbers
     *
     * @return int|mixed[]|string
     */
    public static function ROW($cell_address = null, ?Cell $cell = null): int|string|array
    {
        if (self::cell_address_null_or_whitespace($cell_address)) {
            return self::cell_row($cell);
        }
        if (is_array($cell_address)) {
            foreach ($cell_address as $row_key => $row_value) {
                foreach ($row_value as $cell_value) {
                    return (int) preg_replace('/\D/', '', (string) $row_key);
                }
            }
            return self::cell_row($cell);
        }
        $cell_address ??= '';
        if ($cell !== null) {
            [, , $sheet_name] = Helpers::extract_worksheet($cell_address, $cell);
            [, , $cell_address] = Helpers::extract_cell_addresses($cell_address, true, $cell->get_worksheet(), $sheet_name);
        }
        [, $cell_address] = Worksheet::extract_sheet_title($cell_address, true);
        $cell_address ??= '';
        if (str_contains($cell_address, ':')) {
            [$start_address, $end_address] = explode(':', $cell_address);
            $start_address = (int) (string) preg_replace('/\D/', '', $start_address);
            $end_address = (int) (string) preg_replace('/\D/', '', $end_address);
            return array_map(fn($value): array => [$value], range($start_address, $end_address));
        }
        [$cell_address] = explode(':', $cell_address);
        return self::convert0to_name((int) preg_replace('/\D/', '', $cell_address));
    }
    /**
     * ROWS.
     *
     * Returns the number of rows in an array or reference.
     *
     * Excel Function:
     *        =ROWS(cellAddress)
     *
     * @param null|mixed[]|string $cellAddress An array or array formula, or a reference to a range of cells
     *                                          for which you want the number of rows
     *
     * @return int|string The number of rows in cellAddress, or a string if arguments are invalid
     */
    public static function ROWS($cell_address = null)
    {
        if (self::cell_address_null_or_whitespace($cell_address)) {
            return 1;
        }
        if (is_string($cell_address) && Error_Value::is_error($cell_address, true)) {
            return $cell_address;
        }
        if (!is_array($cell_address)) {
            return Excel_Error::VALUE();
        }
        $is_matrix = is_numeric(array_key_first($cell_address));
        [$columns, $rows] = Calculation::get_matrix_dimensions($cell_address);
        if ($is_matrix) {
            return $columns;
        }
        return $rows;
    }
}