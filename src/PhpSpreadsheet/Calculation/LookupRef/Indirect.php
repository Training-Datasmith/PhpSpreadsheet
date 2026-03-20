<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Exception;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Indirect
{
    /**
     * Determine whether cell address is in A1 (true) or R1C1 (false) format.
     *
     * @param mixed $a1fmt Expect bool Helpers::CELLADDRESS_USE_A1 or CELLADDRESS_USE_R1C1,
     *                      but can be provided as numeric which is cast to bool
     */
    private static function a1Format(mixed $a1fmt): bool
    {
        $a1fmt = Functions::flatten_single_value($a1fmt);
        if ($a1fmt === null) {
            return Helpers::CELLADDRESS_USE_A1;
        }
        if (is_string($a1fmt)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (bool) $a1fmt;
    }
    /**
     * Convert cellAddress to string, verify not null string.
     *
     * @param null|mixed[]|string $cellAddress
     */
    private static function validate_address(array|string|null $cell_address): string
    {
        $cell_address = Functions::flatten_single_value($cell_address);
        if (!is_string($cell_address) || !$cell_address) {
            throw new Exception(Excel_Error::REF());
        }
        return $cell_address;
    }
    /**
     * INDIRECT.
     *
     * Returns the reference specified by a text string.
     * References are immediately evaluated to display their contents.
     *
     * Excel Function:
     *        =INDIRECT(cellAddress, bool) where the bool argument is optional
     *
     * @param mixed[]|string $cellAddress $cellAddress The cell address of the current cell (containing this formula)
     * @param mixed $a1fmt Expect bool Helpers::CELLADDRESS_USE_A1 or CELLADDRESS_USE_R1C1,
     *                      but can be provided as numeric which is cast to bool
     * @param Cell $cell The current cell (containing this formula)
     *
     * @return mixed[]|string An array containing a cell or range of cells, or a string on error
     */
    public static function INDIRECT($cell_address, mixed $a1fmt, Cell $cell): string|array
    {
        [$base_col, $base_row] = Coordinate::indexes_from_string($cell->get_coordinate());
        try {
            $a1 = self::a1Format($a1fmt);
            $cell_address = self::validate_address($cell_address);
        } catch (Exception $e) {
            return $e->get_message();
        }
        [$cell_address, $worksheet, $sheet_name] = Helpers::extract_worksheet($cell_address, $cell);
        if (preg_match('/^' . Calculation::CALCULATION_REGEXP_COLUMNRANGE_RELATIVE . '$/miu', $cell_address, $matches)) {
            $cell_address = self::handle_row_column_ranges($worksheet, ...explode(':', $cell_address));
        } elseif (preg_match('/^' . Calculation::CALCULATION_REGEXP_ROWRANGE_RELATIVE . '$/miu', $cell_address, $matches)) {
            $cell_address = self::handle_row_column_ranges($worksheet, ...explode(':', $cell_address));
        }
        try {
            [$cell_address1, $cell_address2, $cell_address] = Helpers::extract_cell_addresses($cell_address, $a1, $cell->get_work_sheet(), $sheet_name, $base_row, $base_col);
        } catch (Exception) {
            return Excel_Error::REF();
        }
        if (!preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/miu', $cell_address1, $matches) || $cell_address2 !== null && !preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/miu', $cell_address2, $matches)) {
            return Excel_Error::REF();
        }
        return self::extract_required_cells($worksheet, $cell_address);
    }
    /**
     * Extract range values.
     *
     * @return mixed[] Array of values in range if range contains more than one element.
     *                  Otherwise, a single value is returned.
     */
    private static function extract_required_cells(?Worksheet $worksheet, string $cell_address): array
    {
        return Calculation::get_instance($worksheet?->get_parent())->extract_cell_range($cell_address, $worksheet, false, createCell: true);
    }
    private static function handle_row_column_ranges(?Worksheet $worksheet, string $start, string $end): string
    {
        // Being lazy, we're only checking a single row/column to get the max
        if (ctype_digit($start) && $start <= Address_Range::MAX_ROW) {
            // Max 16,384 columns for Excel2007
            $end_col_ref = $worksheet !== null ? $worksheet->get_highest_data_column((int) $start) : Address_Range::MAX_COLUMN;
            return "A{$start}:{$end_col_ref}{$end}";
        }
        // Being lazy, we're only checking a single row/column to get the max
        if (ctype_alpha($start) && strlen($start) <= 3) {
            // Max 1,048,576 rows for Excel2007
            $end_row_ref = $worksheet !== null ? $worksheet->get_highest_data_row($start) : Address_Range::MAX_ROW;
            return "{$start}1:{$end}{$end_row_ref}";
        }
        return "{$start}:{$end}";
    }
}