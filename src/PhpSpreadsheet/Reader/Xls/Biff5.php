<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Reader\Xls;
class Biff5 extends Xls
{
    /**
     * Reads a cell range address in BIFF5 e.g. 'A2:B6' or 'A1'
     * always fixed range
     * section 2.5.14.
     */
    public static function read_biff5cell_range_address_fixed(string $sub_data): string
    {
        // offset: 0; size: 2; index to first row
        $fr = self::get_u_int2d($sub_data, 0) + 1;
        // offset: 2; size: 2; index to last row
        $lr = self::get_u_int2d($sub_data, 2) + 1;
        // offset: 4; size: 1; index to first column
        $fc = ord($sub_data[4]);
        // offset: 5; size: 1; index to last column
        $lc = ord($sub_data[5]);
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
     * Read BIFF5 cell range address list
     * section 2.5.15.
     *
     * @return array{size: int, cellRangeAddresses: string[]}
     */
    public static function read_biff5cell_range_address_list(string $sub_data): array
    {
        $cell_range_addresses = [];
        // offset: 0; size: 2; number of the following cell range addresses
        $nm = self::get_u_int2d($sub_data, 0);
        $offset = 2;
        // offset: 2; size: 6 * $nm; list of $nm (fixed) cell range addresses
        for ($i = 0; $i < $nm; ++$i) {
            $cell_range_addresses[] = self::read_biff5cell_range_address_fixed(substr($sub_data, $offset, 6));
            $offset += 6;
        }
        return ['size' => 2 + 6 * $nm, 'cellRangeAddresses' => $cell_range_addresses];
    }
}