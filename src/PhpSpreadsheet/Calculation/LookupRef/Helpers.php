<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Cell\Address_Helper;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Helpers
{
    public const CELLADDRESS_USE_A1 = true;
    public const CELLADDRESS_USE_R1C1 = false;
    private static function convert_r1c1(string &$cell_address1, ?string &$cell_address2, bool $a1, ?int $base_row = null, ?int $base_col = null): string
    {
        if ($a1 === self::CELLADDRESS_USE_R1C1) {
            $cell_address1 = Address_Helper::convert_to_a1($cell_address1, $base_row ?? 1, $base_col ?? 1);
            if ($cell_address2) {
                $cell_address2 = Address_Helper::convert_to_a1($cell_address2, $base_row ?? 1, $base_col ?? 1);
            }
        }
        return $cell_address1 . ($cell_address2 ? ":{$cell_address2}" : '');
    }
    private static function adjust_sheet_title(string &$sheet_title, ?string $value): void
    {
        if ($sheet_title) {
            $sheet_title .= '!';
            if (stripos($value ?? '', $sheet_title) === 0) {
                $sheet_title = '';
            }
        }
    }
    /** @return array{string, ?string, string} */
    public static function extract_cell_addresses(string $cell_address, bool $a1, Worksheet $sheet, string $sheet_name = '', ?int $base_row = null, ?int $base_col = null): array
    {
        $cell_address1 = $cell_address;
        $cell_address2 = null;
        $named_range = Defined_Name::resolve_name($cell_address1, $sheet, $sheet_name);
        if ($named_range !== null) {
            $work_sheet = $named_range->get_work_sheet();
            $sheet_title = $work_sheet === null ? '' : $work_sheet->get_title();
            $value = (string) preg_replace('/^=/', '', $named_range->get_value());
            self::adjust_sheet_title($sheet_title, $value);
            $cell_address1 = $sheet_title . $value;
            $cell_address = $cell_address1;
            $a1 = self::CELLADDRESS_USE_A1;
        }
        if (str_contains($cell_address, ':')) {
            [$cell_address1, $cell_address2] = explode(':', $cell_address);
        }
        $cell_address = self::convert_r1c1($cell_address1, $cell_address2, $a1, $base_row, $base_col);
        return [$cell_address1, $cell_address2, $cell_address];
    }
    /** @return array{string, ?Worksheet, string} */
    public static function extract_worksheet(string $cell_address, Cell $cell): array
    {
        $sheet_name = '';
        if (str_contains($cell_address, '!')) {
            [$sheet_name, $cell_address] = Worksheet::extract_sheet_title($cell_address, true, true);
        }
        $worksheet = $sheet_name !== '' ? $cell->get_worksheet()->get_parent_or_throw()->get_sheet_by_name($sheet_name) : $cell->get_worksheet();
        return [$cell_address, $worksheet, $sheet_name];
    }
}