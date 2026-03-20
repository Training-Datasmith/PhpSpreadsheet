<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Named_Expressions
{
    public function __construct(private readonly Xml_Writer $obj_writer, private readonly Spreadsheet $spreadsheet, private readonly Formula $formula_convertor)
    {
    }
    public function write(): string
    {
        $this->obj_writer->start_element('table:named-expressions');
        $this->write_expressions();
        $this->obj_writer->end_element();
        return '';
    }
    private function write_expressions(): void
    {
        $defined_names = $this->spreadsheet->get_defined_names();
        foreach ($defined_names as $defined_name) {
            if ($defined_name->is_formula()) {
                $this->obj_writer->start_element('table:named-expression');
                $this->write_named_formula($defined_name, $this->spreadsheet->get_active_sheet());
            } else {
                $this->obj_writer->start_element('table:named-range');
                $this->write_named_range($defined_name);
            }
            $this->obj_writer->end_element();
        }
    }
    private function write_named_formula(Defined_Name $defined_name, Worksheet $default_worksheet): void
    {
        $title = $defined_name->get_worksheet() !== null ? $defined_name->get_worksheet()->get_title() : $default_worksheet->get_title();
        $this->obj_writer->write_attribute('table:name', $defined_name->get_name());
        $this->obj_writer->write_attribute('table:expression', $this->formula_convertor->convert_formula($defined_name->get_value(), $title));
        $this->obj_writer->write_attribute('table:base-cell-address', $this->convert_address($defined_name, "'" . $title . "'!\$A\$1"));
    }
    private function write_named_range(Defined_Name $defined_name): void
    {
        $base_cell = '$A$1';
        $ws = $defined_name->get_worksheet();
        if ($ws !== null) {
            $base_cell = "'" . $ws->get_title() . "'!{$base_cell}";
        }
        $this->obj_writer->write_attribute('table:name', $defined_name->get_name());
        $this->obj_writer->write_attribute('table:base-cell-address', $this->convert_address($defined_name, $base_cell));
        $this->obj_writer->write_attribute('table:cell-range-address', $this->convert_address($defined_name, $defined_name->get_value()));
    }
    private function convert_address(Defined_Name $defined_name, string $address): string
    {
        $split_count = Preg::match_all_with_offsets('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/mui', $address, $split_ranges);
        $lengths = array_map(String_Helper::strlen_allow_null(...), array_column($split_ranges[0], 0));
        $offsets = array_column($split_ranges[0], 1);
        $worksheets = $split_ranges[2];
        $columns = $split_ranges[6];
        $rows = $split_ranges[7];
        while ($split_count > 0) {
            --$split_count;
            $length = $lengths[$split_count];
            $offset = $offsets[$split_count];
            $worksheet = $worksheets[$split_count][0];
            $column = $columns[$split_count][0];
            $row = $rows[$split_count][0];
            $new_range = '';
            if (empty($worksheet)) {
                if ($offset === 0 || $address[$offset - 1] !== ':') {
                    // We need a worksheet
                    $ws = $defined_name->get_worksheet();
                    if ($ws !== null) {
                        $worksheet = $ws->get_title();
                    }
                }
            } else {
                $worksheet = str_replace("''", "'", trim($worksheet, "'"));
            }
            if (!empty($worksheet)) {
                $new_range = "'" . str_replace("'", "''", $worksheet) . "'.";
            }
            //if (!empty($column)) { // phpstan says always true
            $new_range .= $column;
            //}
            if (!empty($row)) {
                $new_range .= $row;
            }
            $address = substr($address, 0, $offset) . $new_range . substr($address, $offset + $length);
        }
        if (str_starts_with($address, '=')) {
            return substr($address, 1);
        }
        return $address;
    }
}