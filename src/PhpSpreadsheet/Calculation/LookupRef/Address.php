<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Address_Helper;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Address
{
    use Array_Enabled;
    public const ADDRESS_ABSOLUTE = 1;
    public const ADDRESS_COLUMN_RELATIVE = 2;
    public const ADDRESS_ROW_RELATIVE = 3;
    public const ADDRESS_RELATIVE = 4;
    public const REFERENCE_STYLE_A1 = true;
    public const REFERENCE_STYLE_R1C1 = false;
    /**
     * ADDRESS.
     *
     * Creates a cell address as text, given specified row and column numbers.
     *
     * Excel Function:
     *        =ADDRESS(row, column, [relativity], [referenceStyle], [sheetText])
     *
     * @param mixed $row Row number (integer) to use in the cell reference
     *                      Or can be an array of values
     * @param mixed $column Column number (integer) to use in the cell reference
     *                      Or can be an array of values
     * @param mixed $relativity Integer flag indicating the type of reference to return
     *                             1 or omitted    Absolute
     *                             2               Absolute row; relative column
     *                             3               Relative row; absolute column
     *                             4               Relative
     *                      Or can be an array of values
     * @param mixed $referenceStyle A logical (boolean) value that specifies the A1 or R1C1 reference style.
     *                                TRUE or omitted    ADDRESS returns an A1-style reference
     *                                FALSE              ADDRESS returns an R1C1-style reference
     *                      Or can be an array of values
     * @param mixed $sheetName Optional Name of worksheet to use
     *                      Or can be an array of values
     *
     * @return mixed[]|string If an array of values is passed as the $testValue argument, then the returned result will also be
     *            an array with the same dimensions
     */
    public static function cell(mixed $row, mixed $column, mixed $relativity = 1, mixed $reference_style = true, mixed $sheet_name = ''): array|string
    {
        if (is_array($row) || is_array($column) || is_array($relativity) || is_array($reference_style) || is_array($sheet_name)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $row, $column, $relativity, $reference_style, $sheet_name);
        }
        $relativity = $relativity === null ? 1 : (int) String_Helper::convert_to_string($relativity);
        $reference_style ??= true;
        $row = (int) String_Helper::convert_to_string($row);
        $column = (int) String_Helper::convert_to_string($column);
        if ($row < 1 || $column < 1) {
            return Excel_Error::VALUE();
        }
        $sheet_name = self::sheet_name(String_Helper::convert_to_string($sheet_name));
        if (is_int($reference_style)) {
            $reference_style = (bool) $reference_style;
        }
        if (!is_bool($reference_style) || $reference_style === self::REFERENCE_STYLE_A1) {
            return self::format_as_a1($row, $column, $relativity, $sheet_name);
        }
        return self::format_as_r1c1($row, $column, $relativity, $sheet_name);
    }
    private static function sheet_name(string $sheet_name): string
    {
        if ($sheet_name > '') {
            if (str_contains($sheet_name, ' ') || str_contains($sheet_name, '[')) {
                $sheet_name = "'{$sheet_name}'";
            }
            $sheet_name .= '!';
        }
        return $sheet_name;
    }
    private static function format_as_a1(int $row, int $column, int $relativity, string $sheet_name): string
    {
        $row_relative = $column_relative = '$';
        if ($relativity == self::ADDRESS_COLUMN_RELATIVE || $relativity == self::ADDRESS_RELATIVE) {
            $column_relative = '';
        }
        if ($relativity == self::ADDRESS_ROW_RELATIVE || $relativity == self::ADDRESS_RELATIVE) {
            $row_relative = '';
        }
        $column = Coordinate::string_from_column_index($column);
        return "{$sheet_name}{$column_relative}{$column}{$row_relative}{$row}";
    }
    private static function format_as_r1c1(int $row, int $column, int $relativity, string $sheet_name): string
    {
        if ($relativity == self::ADDRESS_COLUMN_RELATIVE || $relativity == self::ADDRESS_RELATIVE) {
            $column = "[{$column}]";
        }
        if ($relativity == self::ADDRESS_ROW_RELATIVE || $relativity == self::ADDRESS_RELATIVE) {
            $row = "[{$row}]";
        }
        [$row_char, $col_char] = Address_Helper::get_row_and_column_chars();
        return "{$sheet_name}{$row_char}{$row}{$col_char}{$column}";
    }
}