<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Exception;
class Address_Helper
{
    public const R1C1_COORDINATE_REGEX = '/(R((?:\[-?\d*\])|(?:\d*))?)(C((?:\[-?\d*\])|(?:\d*))?)/i';
    /** @return string[] */
    public static function get_row_and_column_chars(): array
    {
        $row_char = 'R';
        $col_char = 'C';
        if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_EXCEL) {
            $row_col_chars = Calculation::locale_func('*RC');
            if (mb_strlen($row_col_chars) === 2) {
                $row_char = mb_substr($row_col_chars, 0, 1);
                $col_char = mb_substr($row_col_chars, 1, 1);
            }
        }
        return [$row_char, $col_char];
    }
    /**
     * Converts an R1C1 format cell address to an A1 format cell address.
     */
    public static function convert_to_a1(string $address, int $current_row_number = 1, int $current_column_number = 1, bool $use_locale = true): string
    {
        [$row_char, $col_char] = $use_locale ? self::get_row_and_column_chars() : ['R', 'C'];
        $regex = '/^(' . $row_char . '(\[?[-+]?\d*\]?))(' . $col_char . '(\[?[-+]?\d*\]?))$/i';
        $validity_check = preg_match($regex, $address, $cell_reference);
        if (empty($validity_check)) {
            throw new Exception('Invalid R1C1-format Cell Reference');
        }
        $row_reference = $cell_reference[2];
        //    Empty R reference is the current row
        if ($row_reference === '') {
            $row_reference = (string) $current_row_number;
        }
        //    Bracketed R references are relative to the current row
        if ($row_reference[0] === '[') {
            $row_reference = $current_row_number + (int) trim($row_reference, '[]');
        }
        $column_reference = $cell_reference[4];
        //    Empty C reference is the current column
        if ($column_reference === '') {
            $column_reference = (string) $current_column_number;
        }
        //    Bracketed C references are relative to the current column
        if ($column_reference[0] === '[') {
            // @phpstan-ignore-line
            $column_reference = $current_column_number + (int) trim($column_reference, '[]');
        }
        $column_reference = (int) $column_reference;
        if ($column_reference <= 0 || $row_reference <= 0) {
            throw new Exception('Invalid R1C1-format Cell Reference, Value out of range');
        }
        return Coordinate::string_from_column_index($column_reference) . $row_reference;
    }
    protected static function convert_spreadsheet_ml_formula(string $formula): string
    {
        $formula = substr($formula, 3);
        $temp = explode('"', $formula);
        $key = false;
        foreach ($temp as &$value) {
            //    Only replace in alternate array entries (i.e. non-quoted blocks)
            $key = $key === false;
            if ($key) {
                $value = str_replace(['[.', ':.', ']'], ['', ':', ''], $value);
            }
        }
        unset($value);
        return implode('"', $temp);
    }
    /**
     * Converts a formula that uses R1C1/SpreadsheetXML format cell address to an A1 format cell address.
     */
    public static function convert_formula_to_a1(string $formula, int $current_row_number = 1, int $current_column_number = 1): string
    {
        if (str_starts_with($formula, 'of:')) {
            // We have an old-style SpreadsheetML Formula
            return self::convert_spreadsheet_ml_formula($formula);
        }
        //    Convert R1C1 style references to A1 style references (but only when not quoted)
        $temp = explode('"', $formula);
        $key = false;
        foreach ($temp as &$value) {
            //    Only replace in alternate array entries (i.e. non-quoted blocks)
            $key = $key === false;
            if ($key) {
                preg_match_all(self::R1C1_COORDINATE_REGEX, $value, $cell_references, PREG_SET_ORDER + PREG_OFFSET_CAPTURE);
                //    Reverse the matches array, otherwise all our offsets will become incorrect if we modify our way
                //        through the formula from left to right. Reversing means that we work right to left.through
                //        the formula
                $cell_references = array_reverse($cell_references);
                //    Loop through each R1C1 style reference in turn, converting it to its A1 style equivalent,
                //        then modify the formula to use that new reference
                foreach ($cell_references as $cell_reference) {
                    $a1cell_reference = self::convert_to_a1($cell_reference[0][0], $current_row_number, $current_column_number, false);
                    $value = substr_replace($value, $a1cell_reference, $cell_reference[0][1], strlen($cell_reference[0][0]));
                }
            }
        }
        unset($value);
        //    Then rebuild the formula string
        return implode('"', $temp);
    }
    /**
     * Converts an A1 format cell address to an R1C1 format cell address.
     * If $currentRowNumber or $currentColumnNumber are provided, then the R1C1 address will be formatted as a relative address.
     */
    public static function convert_to_r1c1(string $address, ?int $current_row_number = null, ?int $current_column_number = null): string
    {
        if (1 !== preg_match(Coordinate::A1_COORDINATE_REGEX, $address, $cell_reference)) {
            throw new Exception('Invalid A1-format Cell Reference');
        }
        if ($cell_reference['col'][0] === '$') {
            // Column must be absolute address
            $current_column_number = null;
        }
        $column_id = Coordinate::column_index_from_string(ltrim($cell_reference['col'], '$'));
        if ($cell_reference['row'][0] === '$') {
            // Row must be absolute address
            $current_row_number = null;
        }
        $row_id = (int) ltrim($cell_reference['row'], '$');
        if ($current_row_number !== null) {
            if ($row_id === $current_row_number) {
                $row_id = '';
            } else {
                $row_id = '[' . ($row_id - $current_row_number) . ']';
            }
        }
        if ($current_column_number !== null) {
            if ($column_id === $current_column_number) {
                $column_id = '';
            } else {
                $column_id = '[' . ($column_id - $current_column_number) . ']';
            }
        }
        return "R{$row_id}C{$column_id}";
    }
}