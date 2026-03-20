<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Composer\Pcre\Preg;
use Exception;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet as ActualWorksheet;
class Defined_Names
{
    public function __construct(private readonly Xml_Writer $obj_writer, private readonly Spreadsheet $spreadsheet)
    {
    }
    public function write(): void
    {
        // Write defined names
        $this->obj_writer->start_element('definedNames');
        // Named ranges
        if (count($this->spreadsheet->get_defined_names()) > 0) {
            // Named ranges
            $this->write_named_ranges_and_formulae();
        }
        // Other defined names
        $sheet_count = $this->spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            // NamedRange for autoFilter
            $this->write_named_range_for_autofilter($this->spreadsheet->get_sheet($i), $i);
            // NamedRange for Print_Titles
            $this->write_named_range_for_print_titles($this->spreadsheet->get_sheet($i), $i);
            // NamedRange for Print_Area
            $this->write_named_range_for_print_area($this->spreadsheet->get_sheet($i), $i);
        }
        $this->obj_writer->end_element();
    }
    /**
     * Write defined names.
     */
    private function write_named_ranges_and_formulae(): void
    {
        // Loop named ranges
        $defined_names = $this->spreadsheet->get_defined_names();
        foreach ($defined_names as $defined_name) {
            $this->write_defined_name($defined_name);
        }
    }
    /**
     * Write Defined Name for named range.
     */
    private function write_defined_name(Defined_Name $defined_name): void
    {
        // definedName for named range
        $local = -1;
        if ($defined_name->get_local_only() && $defined_name->get_scope() !== null) {
            try {
                $local = $defined_name->get_scope()->get_parent_or_throw()->get_index($defined_name->get_scope());
            } catch (Exception) {
                // See issue 2266 - deleting sheet which contains
                //     defined names will cause Exception above.
                return;
            }
        }
        $this->obj_writer->start_element('definedName');
        $this->obj_writer->write_attribute('name', $defined_name->get_name());
        if ($local >= 0) {
            $this->obj_writer->write_attribute('localSheetId', "{$local}");
        }
        $defined_range = $this->get_defined_range($defined_name);
        $this->obj_writer->write_raw_data($defined_range);
        $this->obj_writer->end_element();
    }
    /**
     * Write Defined Name for autoFilter.
     */
    private function write_named_range_for_autofilter(Actual_Worksheet $worksheet, int $worksheet_id = 0): void
    {
        // NamedRange for autoFilter
        $auto_filter_range = $worksheet->get_auto_filter()->get_range();
        if (!empty($auto_filter_range)) {
            $this->obj_writer->start_element('definedName');
            $this->obj_writer->write_attribute('name', '_xlnm._FilterDatabase');
            $this->obj_writer->write_attribute('localSheetId', "{$worksheet_id}");
            $this->obj_writer->write_attribute('hidden', '1');
            // Create absolute coordinate and write as raw text
            $range = Coordinate::split_range($auto_filter_range);
            $range = $range[0];
            //    Strip any worksheet ref so we can make the cell ref absolute
            [, $range[0]] = Actual_Worksheet::extract_sheet_title($range[0], true);
            $range[0] = Coordinate::absolute_coordinate($range[0] ?? '');
            if (count($range) > 1) {
                $range[1] = Coordinate::absolute_coordinate($range[1] ?? '');
            }
            $range = implode(':', $range);
            $this->obj_writer->write_raw_data('\'' . str_replace("'", "''", $worksheet->get_title()) . '\'!' . $range);
            $this->obj_writer->end_element();
        }
    }
    /**
     * Write Defined Name for PrintTitles.
     */
    private function write_named_range_for_print_titles(Actual_Worksheet $worksheet, int $worksheet_id = 0): void
    {
        // NamedRange for PrintTitles
        if ($worksheet->get_page_setup()->is_columns_to_repeat_at_left_set() || $worksheet->get_page_setup()->is_rows_to_repeat_at_top_set()) {
            $this->obj_writer->start_element('definedName');
            $this->obj_writer->write_attribute('name', '_xlnm.Print_Titles');
            $this->obj_writer->write_attribute('localSheetId', "{$worksheet_id}");
            // Setting string
            $setting_string = '';
            // Columns to repeat
            if ($worksheet->get_page_setup()->is_columns_to_repeat_at_left_set()) {
                $repeat = $worksheet->get_page_setup()->get_columns_to_repeat_at_left();
                $setting_string .= '\'' . str_replace("'", "''", $worksheet->get_title()) . '\'!$' . $repeat[0] . ':$' . $repeat[1];
            }
            // Rows to repeat
            if ($worksheet->get_page_setup()->is_rows_to_repeat_at_top_set()) {
                if ($worksheet->get_page_setup()->is_columns_to_repeat_at_left_set()) {
                    $setting_string .= ',';
                }
                $repeat = $worksheet->get_page_setup()->get_rows_to_repeat_at_top();
                $setting_string .= '\'' . str_replace("'", "''", $worksheet->get_title()) . '\'!$' . $repeat[0] . ':$' . $repeat[1];
            }
            $this->obj_writer->write_raw_data($setting_string);
            $this->obj_writer->end_element();
        }
    }
    /**
     * Write Defined Name for PrintTitles.
     */
    private function write_named_range_for_print_area(Actual_Worksheet $worksheet, int $worksheet_id = 0): void
    {
        // NamedRange for PrintArea
        if ($worksheet->get_page_setup()->is_print_area_set()) {
            $this->obj_writer->start_element('definedName');
            $this->obj_writer->write_attribute('name', '_xlnm.Print_Area');
            $this->obj_writer->write_attribute('localSheetId', "{$worksheet_id}");
            // Print area
            $print_area = Coordinate::split_range($worksheet->get_page_setup()->get_print_area());
            $chunks = [];
            foreach ($print_area as $print_area_rect) {
                $print_area_rect[0] = Coordinate::absolute_reference($print_area_rect[0]);
                $print_area_rect[1] = Coordinate::absolute_reference($print_area_rect[1]);
                $chunks[] = '\'' . str_replace("'", "''", $worksheet->get_title()) . '\'!' . implode(':', $print_area_rect);
            }
            $this->obj_writer->write_raw_data(implode(',', $chunks));
            $this->obj_writer->end_element();
        }
    }
    private function get_defined_range(Defined_Name $defined_name): string
    {
        $defined_range = $defined_name->get_value();
        $split_count = Preg::match_all_with_offsets('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/mui', $defined_range, $split_ranges);
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
                if ($offset === 0 || $defined_range[$offset - 1] !== ':') {
                    // We should have a worksheet
                    $ws = $defined_name->get_worksheet();
                    $worksheet = $ws === null ? null : $ws->get_title();
                }
            } else {
                $worksheet = str_replace("''", "'", trim($worksheet, "'"));
            }
            if (!empty($worksheet)) {
                $new_range = "'" . str_replace("'", "''", $worksheet) . "'!";
            }
            $new_range = "{$new_range}{$column}{$row}";
            $defined_range = substr($defined_range, 0, $offset) . $new_range . substr($defined_range, $offset + $length);
        }
        if (str_starts_with($defined_range, '=')) {
            return substr($defined_range, 1);
        }
        return $defined_range;
    }
}