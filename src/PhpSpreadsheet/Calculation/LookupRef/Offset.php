<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Validations;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Offset
{
    /**
     * OFFSET.
     *
     * Returns a reference to a range that is a specified number of rows and columns from a cell or range of cells.
     * The reference that is returned can be a single cell or a range of cells. You can specify the number of rows and
     * the number of columns to be returned.
     *
     * Excel Function:
     *        =OFFSET(cellAddress, rows, cols, [height], [width])
     *
     * @param null|string $cellAddress The reference from which you want to base the offset.
     *                                     Reference must refer to a cell or range of adjacent cells;
     *                                     otherwise, OFFSET returns the #VALUE! error value.
     * @param int $rows The number of rows, up or down, that you want the upper-left cell to refer to.
     *                        Using 5 as the rows argument specifies that the upper-left cell in the
     *                        reference is five rows below reference. Rows can be positive (which means
     *                        below the starting reference) or negative (which means above the starting
     *                        reference).
     * @param int $columns The number of columns, to the left or right, that you want the upper-left cell
     *                           of the result to refer to. Using 5 as the cols argument specifies that the
     *                           upper-left cell in the reference is five columns to the right of reference.
     *                           Cols can be positive (which means to the right of the starting reference)
     *                           or negative (which means to the left of the starting reference).
     * @param ?int $height The height, in number of rows, that you want the returned reference to be.
     *                          Height must be a positive number.
     * @param ?int $width The width, in number of columns, that you want the returned reference to be.
     *                         Width must be a positive number.
     *
     * @return array<mixed>|string An array containing a cell or range of cells, or a string on error
     */
    public static function OFFSET(?string $cell_address = null, $rows = 0, $columns = 0, $height = null, $width = null, ?Cell $cell = null): string|array
    {
        /** @var int */
        $rows = Functions::flatten_single_value($rows);
        /** @var int */
        $columns = Functions::flatten_single_value($columns);
        /** @var int */
        $height = Functions::flatten_single_value($height);
        /** @var int */
        $width = Functions::flatten_single_value($width);
        if ($cell_address === null || $cell_address === '') {
            return Excel_Error::VALUE();
        }
        if (!is_object($cell)) {
            return Excel_Error::REF();
        }
        $sheet = $cell->get_parent()?->get_parent();
        // worksheet
        if ($sheet !== null) {
            $cell_address = Validations::defined_name_to_coordinate($cell_address, $sheet);
        }
        [$cell_address, $worksheet] = self::extract_worksheet($cell_address, $cell);
        $start_cell = $end_cell = $cell_address;
        if (strpos($cell_address, ':')) {
            [$start_cell, $end_cell] = explode(':', $cell_address);
        }
        [$start_cell_column, $start_cell_row] = Coordinate::indexes_from_string($start_cell);
        [, $end_cell_row, $end_cell_column] = Coordinate::indexes_from_string($end_cell);
        $start_cell_row += $rows;
        $start_cell_column += $columns - 1;
        if ($start_cell_row <= 0 || $start_cell_column < 0) {
            return Excel_Error::REF();
        }
        $end_cell_column = self::adjust_end_cell_column_for_width($end_cell_column, $width, $start_cell_column, $columns);
        $start_cell_column = Coordinate::string_from_column_index($start_cell_column + 1);
        $end_cell_row = self::adjust_end_cell_row_for_height($height, $start_cell_row, $rows, $end_cell_row);
        if ($end_cell_row <= 0 || $end_cell_column < 0) {
            return Excel_Error::REF();
        }
        $end_cell_column = Coordinate::string_from_column_index($end_cell_column + 1);
        $cell_address = "{$start_cell_column}{$start_cell_row}";
        if ($start_cell_column != $end_cell_column || $start_cell_row != $end_cell_row) {
            $cell_address .= ":{$end_cell_column}{$end_cell_row}";
        }
        return self::extract_required_cells($worksheet, $cell_address);
    }
    /** @return mixed[] */
    private static function extract_required_cells(?Worksheet $worksheet, string $cell_address): array
    {
        return Calculation::get_instance($worksheet?->get_parent())->extract_cell_range($cell_address, $worksheet, false);
    }
    /** @return array{string, ?Worksheet} */
    private static function extract_worksheet(?string $cell_address, Cell $cell): array
    {
        $cell_address = self::assess_cell_address($cell_address ?? '', $cell);
        $sheet_name = '';
        if (str_contains($cell_address, '!')) {
            [$sheet_name, $cell_address] = Worksheet::extract_sheet_title($cell_address, true, true);
        }
        $worksheet = $sheet_name !== '' ? $cell->get_worksheet()->get_parent_or_throw()->get_sheet_by_name($sheet_name) : $cell->get_worksheet();
        return [$cell_address, $worksheet];
    }
    private static function assess_cell_address(string $cell_address, Cell $cell): string
    {
        if (preg_match('/^' . Calculation::CALCULATION_REGEXP_DEFINEDNAME . '$/mui', $cell_address) !== false) {
            return Functions::expand_defined_name($cell_address, $cell);
        }
        return $cell_address;
    }
    /**
     * @param null|object|scalar $width
     * @param scalar $columns
     */
    private static function adjust_end_cell_column_for_width(string $end_cell_column, $width, int $start_cell_column, $columns): int
    {
        $end_cell_column = Coordinate::column_index_from_string($end_cell_column) - 1;
        if ($width !== null && !is_object($width)) {
            $end_cell_column = $start_cell_column + (int) $width - 1;
        } else {
            $end_cell_column += (int) $columns;
        }
        return $end_cell_column;
    }
    /**
     * @param null|object|scalar $height
     * @param scalar $rows
     */
    private static function adjust_end_cell_row_for_height($height, int $start_cell_row, $rows, int $end_cell_row): int
    {
        if ($height !== null && !is_object($height)) {
            $end_cell_row = $start_cell_row + (int) $height - 1;
        } else {
            $end_cell_row += (int) $rows;
        }
        return $end_cell_row;
    }
}