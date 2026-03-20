<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Formula
{
    /** @var string[] */
    private array $defined_names = [];
    /**
     * @param DefinedName[] $definedNames
     */
    public function __construct(array $defined_names)
    {
        foreach ($defined_names as $defined_name) {
            $this->defined_names[] = $defined_name->get_name();
        }
    }
    public function convert_formula(string $formula, string $worksheet_name = ''): string
    {
        $formula = $this->convert_cell_references($formula, $worksheet_name);
        $formula = $this->convert_defined_names($formula);
        $formula = $this->convert_function_names($formula);
        if (!str_starts_with($formula, '=')) {
            $formula = '=' . $formula;
        }
        return 'of:' . $formula;
    }
    private function convert_defined_names(string $formula): string
    {
        $split_count = Preg::match_all_with_offsets('/' . Calculation::CALCULATION_REGEXP_DEFINEDNAME . '/mui', $formula, $split_ranges);
        $lengths = array_map(String_Helper::strlen_allow_null(...), array_column($split_ranges[0], 0));
        $offsets = array_column($split_ranges[0], 1);
        $values = array_column($split_ranges[0], 0);
        while ($split_count > 0) {
            --$split_count;
            $length = $lengths[$split_count];
            $offset = $offsets[$split_count];
            $value = $values[$split_count];
            if (in_array($value, $this->defined_names, true)) {
                $formula = substr($formula, 0, $offset) . '$$' . $value . substr($formula, $offset + $length);
            }
        }
        return $formula;
    }
    private function convert_cell_references(string $formula, string $worksheet_name): string
    {
        $split_count = Preg::match_all_with_offsets('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/mui', $formula, $split_ranges);
        $lengths = array_map(String_Helper::strlen_allow_null(...), array_column($split_ranges[0], 0));
        $offsets = array_column($split_ranges[0], 1);
        $worksheets = $split_ranges[2];
        $columns = $split_ranges[6];
        $rows = $split_ranges[7];
        // Replace any commas in the formula with semicolons for Ods
        // If by chance there are commas in worksheet names, then they will be "fixed" again in the loop
        //    because we've already extracted worksheet names with our Preg::matchAllWithOffsets()
        $formula = str_replace(',', ';', $formula);
        while ($split_count > 0) {
            --$split_count;
            $length = $lengths[$split_count];
            $offset = $offsets[$split_count];
            $worksheet = $worksheets[$split_count][0];
            $column = $columns[$split_count][0];
            $row = $rows[$split_count][0];
            $new_range = '';
            if (empty($worksheet)) {
                if ($offset === 0 || $formula[$offset - 1] !== ':') {
                    // We need a worksheet
                    $worksheet = $worksheet_name;
                }
            } else {
                $worksheet = str_replace("''", "'", trim($worksheet, "'"));
            }
            if (!empty($worksheet)) {
                $new_range = "['" . str_replace("'", "''", $worksheet) . "'";
            } elseif (substr($formula, $offset - 1, 1) !== ':') {
                $new_range = '[';
            }
            $new_range .= '.';
            //if (!empty($column)) { // phpstan says always true
            $new_range .= $column;
            //}
            if (!empty($row)) {
                $new_range .= $row;
            }
            // close the wrapping [] unless this is the first part of a range
            $new_range .= substr($formula, $offset + $length, 1) !== ':' ? ']' : '';
            $formula = substr($formula, 0, $offset) . $new_range . substr($formula, $offset + $length);
        }
        return $formula;
    }
    private function convert_function_names(string $formula): string
    {
        return Preg::replace(['/\b((CEILING|FLOOR)' . '([.](MATH|PRECISE))?)\s*[(]/ui', '/\b(CEILING|FLOOR)[.]XCL\s*[(]/ui', '/\b(CEILING|FLOOR)[.]ODS\s*[(]/ui'], ['COM.MICROSOFT.$1(', 'COM.MICROSOFT.$1(', '$1('], $formula);
    }
}