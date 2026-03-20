<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Reader\Xls;
class Biff8 extends Xls
{
    /**
     * read BIFF8 constant value array from array data
     * returns e.g. ['value' => '{1,2;3,4}', 'size' => 40]
     * section 2.5.8.
     *
     * @return array{value: string, size: int}
     */
    protected static function read_biff8constant_array(string $array_data): array
    {
        // offset: 0; size: 1; number of columns decreased by 1
        $nc = ord($array_data[0]);
        // offset: 1; size: 2; number of rows decreased by 1
        $nr = self::get_u_int2d($array_data, 1);
        $size = 3;
        // initialize
        $array_data = substr($array_data, 3);
        // offset: 3; size: var; list of ($nc + 1) * ($nr + 1) constant values
        $matrix_chunks = [];
        for ($r = 1; $r <= $nr + 1; ++$r) {
            $items = [];
            for ($c = 1; $c <= $nc + 1; ++$c) {
                $constant = self::read_biff8constant($array_data);
                $items[] = $constant['value'];
                $array_data = substr($array_data, $constant['size']);
                $size += $constant['size'];
            }
            $matrix_chunks[] = implode(',', $items);
            // looks like e.g. '1,"hello"'
        }
        $matrix = '{' . implode(';', $matrix_chunks) . '}';
        return ['value' => $matrix, 'size' => $size];
    }
    /**
     * read BIFF8 constant value which may be 'Empty Value', 'Number', 'String Value', 'Boolean Value', 'Error Value'
     * section 2.5.7
     * returns e.g. ['value' => '5', 'size' => 9].
     *
     * @return array{value: bool|float|int|string, size: int}
     */
    private static function read_biff8constant(string $value_data): array
    {
        // offset: 0; size: 1; identifier for type of constant
        $identifier = ord($value_data[0]);
        switch ($identifier) {
            case 0x0:
                // empty constant (what is this?)
                $value = '';
                $size = 9;
                break;
            case 0x1:
                // number
                // offset: 1; size: 8; IEEE 754 floating-point value
                $value = self::extract_number(substr($value_data, 1, 8));
                $size = 9;
                break;
            case 0x2:
                // string value
                // offset: 1; size: var; Unicode string, 16-bit string length
                $string = self::read_unicode_string_long(substr($value_data, 1));
                $value = '"' . $string['value'] . '"';
                $size = 1 + $string['size'];
                break;
            case 0x4:
                // boolean
                // offset: 1; size: 1; 0 = FALSE, 1 = TRUE
                if (ord($value_data[1])) {
                    $value = 'TRUE';
                } else {
                    $value = 'FALSE';
                }
                $size = 9;
                break;
            case 0x10:
                // error code
                // offset: 1; size: 1; error code
                $value = Error_Code::lookup(ord($value_data[1]));
                $size = 9;
                break;
            default:
                throw new Reader_Exception('Unsupported BIFF8 constant');
        }
        return ['value' => $value, 'size' => $size];
    }
    /**
     * Read BIFF8 cell range address list
     * section 2.5.15.
     *
     * @return array{size: int, cellRangeAddresses: mixed[]}
     */
    public static function read_biff8cell_range_address_list(string $sub_data): array
    {
        $cell_range_addresses = [];
        // offset: 0; size: 2; number of the following cell range addresses
        $nm = self::get_u_int2d($sub_data, 0);
        $offset = 2;
        // offset: 2; size: 8 * $nm; list of $nm (fixed) cell range addresses
        for ($i = 0; $i < $nm; ++$i) {
            $cell_range_addresses[] = self::read_biff8cell_range_address_fixed(substr($sub_data, $offset, 8));
            $offset += 8;
        }
        return ['size' => 2 + 8 * $nm, 'cellRangeAddresses' => $cell_range_addresses];
    }
    /**
     * Reads a cell address in BIFF8 e.g. 'A2' or '$A$2'
     * section 3.3.4.
     */
    protected static function read_biff8cell_address(string $cell_address_structure): string
    {
        // offset: 0; size: 2; index to row (0... 65535) (or offset (-32768... 32767))
        $row = self::get_u_int2d($cell_address_structure, 0) + 1;
        // offset: 2; size: 2; index to column or column offset + relative flags
        // bit: 7-0; mask 0x00FF; column index
        $column = Coordinate::string_from_column_index((0xff & self::get_u_int2d($cell_address_structure, 2)) + 1);
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($cell_address_structure, 2))) {
            $column = '$' . $column;
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($cell_address_structure, 2))) {
            $row = '$' . $row;
        }
        return $column . $row;
    }
    /**
     * Reads a cell address in BIFF8 for shared formulas. Uses positive and negative values for row and column
     * to indicate offsets from a base cell
     * section 3.3.4.
     *
     * @param string $baseCell Base cell, only needed when formula contains tRefN tokens, e.g. with shared formulas
     */
    protected static function read_biff8cell_address_b(string $cell_address_structure, string $base_cell = 'A1'): string
    {
        [$base_col, $base_row] = Coordinate::coordinate_from_string($base_cell);
        $base_col = Coordinate::column_index_from_string($base_col) - 1;
        $base_row = (int) $base_row;
        // offset: 0; size: 2; index to row (0... 65535) (or offset (-32768... 32767))
        $row_index = self::get_u_int2d($cell_address_structure, 0);
        $row = self::get_u_int2d($cell_address_structure, 0) + 1;
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($cell_address_structure, 2))) {
            // offset: 2; size: 2; index to column or column offset + relative flags
            // bit: 7-0; mask 0x00FF; column index
            $col_index = 0xff & self::get_u_int2d($cell_address_structure, 2);
            $column = Coordinate::string_from_column_index($col_index + 1);
            $column = '$' . $column;
        } else {
            // offset: 2; size: 2; index to column or column offset + relative flags
            // bit: 7-0; mask 0x00FF; column index
            $relative_col_index = 0xff & self::get_int2d($cell_address_structure, 2);
            $col_index = $base_col + $relative_col_index;
            $col_index = $col_index < 256 ? $col_index : $col_index - 256;
            $col_index = $col_index >= 0 ? $col_index : $col_index + 256;
            $column = Coordinate::string_from_column_index($col_index + 1);
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($cell_address_structure, 2))) {
            $row = '$' . $row;
        } else {
            $row_index = $row_index <= 32767 ? $row_index : $row_index - 65536;
            $row = $base_row + $row_index;
        }
        return $column . $row;
    }
    /**
     * Reads a cell range address in BIFF8 e.g. 'A2:B6' or 'A1'
     * always fixed range
     * section 2.5.14.
     */
    protected static function read_biff8cell_range_address_fixed(string $sub_data): string
    {
        // offset: 0; size: 2; index to first row
        $fr = self::get_u_int2d($sub_data, 0) + 1;
        // offset: 2; size: 2; index to last row
        $lr = self::get_u_int2d($sub_data, 2) + 1;
        // offset: 4; size: 2; index to first column
        $fc = self::get_u_int2d($sub_data, 4);
        // offset: 6; size: 2; index to last column
        $lc = self::get_u_int2d($sub_data, 6);
        // check values
        if ($fr > $lr || $fc > $lc) {
            throw new Reader_Exception('Not a cell range address');
        }
        // column index to letter
        $fc = Coordinate::string_from_column_index($fc + 1);
        $lc = Coordinate::string_from_column_index($lc + 1);
        if ($fr == $lr && $fc == $lc) {
            return "{$fc}{$fr}";
        }
        return "{$fc}{$fr}:{$lc}{$lr}";
    }
    /**
     * Reads a cell range address in BIFF8 e.g. 'A2:B6' or '$A$2:$B$6'
     * there are flags indicating whether column/row index is relative
     * section 3.3.4.
     */
    protected static function read_biff8cell_range_address(string $sub_data): string
    {
        // todo: if cell range is just a single cell, should this function
        // not just return e.g. 'A1' and not 'A1:A1' ?
        // offset: 0; size: 2; index to first row (0... 65535) (or offset (-32768... 32767))
        $fr = self::get_u_int2d($sub_data, 0) + 1;
        // offset: 2; size: 2; index to last row (0... 65535) (or offset (-32768... 32767))
        $lr = self::get_u_int2d($sub_data, 2) + 1;
        // offset: 4; size: 2; index to first column or column offset + relative flags
        // bit: 7-0; mask 0x00FF; column index
        $fc = Coordinate::string_from_column_index((0xff & self::get_u_int2d($sub_data, 4)) + 1);
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($sub_data, 4))) {
            $fc = '$' . $fc;
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($sub_data, 4))) {
            $fr = '$' . $fr;
        }
        // offset: 6; size: 2; index to last column or column offset + relative flags
        // bit: 7-0; mask 0x00FF; column index
        $lc = Coordinate::string_from_column_index((0xff & self::get_u_int2d($sub_data, 6)) + 1);
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($sub_data, 6))) {
            $lc = '$' . $lc;
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($sub_data, 6))) {
            $lr = '$' . $lr;
        }
        return "{$fc}{$fr}:{$lc}{$lr}";
    }
    /**
     * Reads a cell range address in BIFF8 for shared formulas. Uses positive and negative values for row and column
     * to indicate offsets from a base cell
     * section 3.3.4.
     *
     * @param string $baseCell Base cell
     *
     * @return string Cell range address
     */
    protected static function read_biff8cell_range_address_b(string $sub_data, string $base_cell = 'A1'): string
    {
        [$base_col, $base_row] = Coordinate::indexes_from_string($base_cell);
        $base_col = $base_col - 1;
        // TODO: if cell range is just a single cell, should this function
        // not just return e.g. 'A1' and not 'A1:A1' ?
        // offset: 0; size: 2; first row
        $fr_index = self::get_u_int2d($sub_data, 0);
        // adjust below
        // offset: 2; size: 2; relative index to first row (0... 65535) should be treated as offset (-32768... 32767)
        $lr_index = self::get_u_int2d($sub_data, 2);
        // adjust below
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($sub_data, 4))) {
            // absolute column index
            // offset: 4; size: 2; first column with relative/absolute flags
            // bit: 7-0; mask 0x00FF; column index
            $fc_index = 0xff & self::get_u_int2d($sub_data, 4);
            $fc = Coordinate::string_from_column_index($fc_index + 1);
            $fc = '$' . $fc;
        } else {
            // column offset
            // offset: 4; size: 2; first column with relative/absolute flags
            // bit: 7-0; mask 0x00FF; column index
            $relative_fc_index = 0xff & self::get_int2d($sub_data, 4);
            $fc_index = $base_col + $relative_fc_index;
            $fc_index = $fc_index < 256 ? $fc_index : $fc_index - 256;
            $fc_index = $fc_index >= 0 ? $fc_index : $fc_index + 256;
            $fc = Coordinate::string_from_column_index($fc_index + 1);
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($sub_data, 4))) {
            // absolute row index
            $fr = $fr_index + 1;
            $fr = '$' . $fr;
        } else {
            // row offset
            $fr_index = $fr_index <= 32767 ? $fr_index : $fr_index - 65536;
            $fr = $base_row + $fr_index;
        }
        // bit: 14; mask 0x4000; (1 = relative column index, 0 = absolute column index)
        if (!(0x4000 & self::get_u_int2d($sub_data, 6))) {
            // absolute column index
            // offset: 6; size: 2; last column with relative/absolute flags
            // bit: 7-0; mask 0x00FF; column index
            $lc_index = 0xff & self::get_u_int2d($sub_data, 6);
            $lc = Coordinate::string_from_column_index($lc_index + 1);
            $lc = '$' . $lc;
        } else {
            // column offset
            // offset: 6; size: 2; last column with relative/absolute flags
            // bit: 7-0; mask 0x00FF; column index
            $relative_lc_index = 0xff & self::get_int2d($sub_data, 6);
            $lc_index = $base_col + $relative_lc_index;
            $lc_index = $lc_index < 256 ? $lc_index : $lc_index - 256;
            $lc_index = $lc_index >= 0 ? $lc_index : $lc_index + 256;
            $lc = Coordinate::string_from_column_index($lc_index + 1);
        }
        // bit: 15; mask 0x8000; (1 = relative row index, 0 = absolute row index)
        if (!(0x8000 & self::get_u_int2d($sub_data, 6))) {
            // absolute row index
            $lr = $lr_index + 1;
            $lr = '$' . $lr;
        } else {
            // row offset
            $lr_index = $lr_index <= 32767 ? $lr_index : $lr_index - 65536;
            $lr = $base_row + $lr_index;
        }
        return "{$fc}{$fr}:{$lc}{$lr}";
    }
}