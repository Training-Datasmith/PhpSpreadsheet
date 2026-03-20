<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Cell_Range;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
class Validations
{
    /**
     * Validate a cell address.
     *
     * @param null|array{0: int, 1: int}|CellAddress|string $cellAddress Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     */
    public static function validate_cell_address(null|Cell_Address|string|array $cell_address): string
    {
        if (is_string($cell_address)) {
            [$worksheet, $address] = Worksheet::extract_sheet_title($cell_address, true);
            //            if (!empty($worksheet) && $worksheet !== $this->getTitle()) {
            //                throw new Exception('Reference is not for this worksheet');
            //            }
            return empty($worksheet) ? strtoupper("{$address}") : $worksheet . '!' . strtoupper("{$address}");
        }
        if (is_array($cell_address)) {
            $cell_address = Cell_Address::from_column_row_array($cell_address);
        }
        return (string) $cell_address;
    }
    /**
     * Validate a cell address or cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|CellAddress|int|string $cellRange Coordinate of the cells as a string, eg: 'C5:F12';
     *               or as an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 12]),
     *               or as a CellAddress or AddressRange object.
     */
    public static function validate_cell_or_cell_range(Address_Range|Cell_Address|int|string|array $cell_range): string
    {
        if (is_string($cell_range) || is_numeric($cell_range)) {
            // Convert a single column reference like 'A' to 'A:A',
            //    a single row reference like '1' to '1:1'
            $cell_range = Preg::replace('/^([A-Z]+|\d+)$/', '${1}:${1}', (string) $cell_range);
        } elseif (is_object($cell_range) && $cell_range instanceof Cell_Address) {
            $cell_range = new Cell_Range($cell_range, $cell_range);
        }
        return self::validate_cell_range($cell_range);
    }
    private const SETMAXROW = '${1}1:${2}' . Address_Range::MAX_ROW;
    private const SETMAXCOL = 'A${1}:' . Address_Range::MAX_COLUMN . '${2}';
    /**
     * Convert Column ranges like 'A:C' to 'A1:C1048576'
     *     or Row ranges like '1:3' to 'A1:XFD3'.
     */
    public static function convert_whole_row_column(?string $address_range): string
    {
        return Preg::replace(['/^([A-Z]+):([A-Z]+)$/i', '/^(\d+):(\d+)$/'], [self::SETMAXROW, self::SETMAXCOL], $address_range ?? '');
    }
    /**
     * Validate a cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $cellRange Coordinate of the cells as a string, eg: 'C5:F12';
     *               or as an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 12]),
     *               or as an AddressRange object.
     */
    public static function validate_cell_range(Address_Range|string|array $cell_range): string
    {
        if (is_string($cell_range)) {
            [$worksheet, $address_range] = Worksheet::extract_sheet_title($cell_range, true);
            // Convert Column ranges like 'A:C' to 'A1:C1048576'
            //      or Row ranges like '1:3' to 'A1:XFD3'
            $address_range = self::convert_whole_row_column($address_range);
            return empty($worksheet) ? strtoupper($address_range) : $worksheet . '!' . strtoupper($address_range);
        }
        if (is_array($cell_range)) {
            switch (count($cell_range)) {
                case 4:
                    $from = [$cell_range[0], $cell_range[1]];
                    $to = [$cell_range[2], $cell_range[3]];
                    break;
                case 2:
                    $from = [$cell_range[0], $cell_range[1]];
                    $to = [$cell_range[0], $cell_range[1]];
                    break;
                default:
                    throw new Spreadsheet_Exception('CellRange array length must be 2 or 4');
            }
            $cell_range = new Cell_Range(Cell_Address::from_column_row_array($from), Cell_Address::from_column_row_array($to));
        }
        return (string) $cell_range;
    }
    public static function defined_name_to_coordinate(string $coordinate, Worksheet $worksheet): string
    {
        // Uppercase coordinate
        $coordinate = strtoupper($coordinate);
        // Eliminate leading equal sign
        $test_coordinate = Preg::replace('/^=/', '', $coordinate);
        $defined = $worksheet->get_parent_or_throw()->get_defined_name($test_coordinate, $worksheet);
        if ($defined !== null) {
            if ($defined->get_worksheet() === $worksheet && !$defined->is_formula()) {
                $coordinate = Preg::replace('/^=/', '', $defined->get_value());
            }
        }
        return $coordinate;
    }
}