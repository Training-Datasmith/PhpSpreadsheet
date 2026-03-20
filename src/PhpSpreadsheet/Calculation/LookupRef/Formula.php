<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Formula
{
    /**
     * FORMULATEXT.
     *
     * @param mixed $cellReference The cell to check
     * @param ?Cell $cell The current cell (containing this formula)
     */
    public static function text(mixed $cell_reference = '', ?Cell $cell = null): string
    {
        if ($cell === null) {
            return Excel_Error::REF();
        }
        $worksheet = null;
        $cell_reference = String_Helper::convert_to_string($cell_reference);
        if (1 === preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/i', $cell_reference, $matches)) {
            $cell_reference = $matches[6] . $matches[7];
            $worksheet_name = trim($matches[3], "'");
            $worksheet = !empty($worksheet_name) ? $cell->get_worksheet()->get_parent_or_throw()->get_sheet_by_name($worksheet_name) : $cell->get_worksheet();
        }
        if ($worksheet === null || !$worksheet->cell_exists($cell_reference) || !$worksheet->get_cell($cell_reference)->is_formula()) {
            return Excel_Error::NA();
        }
        return $worksheet->get_cell($cell_reference)->get_value_string();
    }
}