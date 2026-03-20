<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Worksheet\Table as WorksheetTable;
class Table extends Writer_Part
{
    /**
     * Write Table to XML format.
     *
     * @param int $tableRef Table ID
     *
     * @return string XML Output
     */
    public function write_table(Worksheet_Table $table, int $table_ref): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Table
        $name = 'Table' . $table_ref;
        $range = $table->get_range();
        $obj_writer->start_element('table');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        $obj_writer->write_attribute('id', (string) $table_ref);
        $obj_writer->write_attribute('name', $name);
        $obj_writer->write_attribute('displayName', $table->get_name() ?: $name);
        $obj_writer->write_attribute('ref', $range);
        $obj_writer->write_attribute('headerRowCount', $table->get_show_header_row() ? '1' : '0');
        $obj_writer->write_attribute('totalsRowCount', $table->get_show_totals_row() ? '1' : '0');
        // Table Boundaries
        [$range_start, $range_end] = Coordinate::range_boundaries($table->get_range());
        // Table Auto Filter
        if ($table->get_show_header_row() && $table->get_allow_filter() === true) {
            $obj_writer->start_element('autoFilter');
            $obj_writer->write_attribute('ref', $range);
            foreach (range($range_start[0], $range_end[0]) as $offset => $column_index) {
                $column = $table->get_column_by_offset($offset);
                if (!$column->get_show_filter_button()) {
                    $obj_writer->start_element('filterColumn');
                    $obj_writer->write_attribute('colId', (string) $offset);
                    $obj_writer->write_attribute('hiddenButton', '1');
                    $obj_writer->end_element();
                } else {
                    $column = $table->get_auto_filter()->get_column_by_offset($offset);
                    Auto_Filter::write_auto_filter_column($obj_writer, $column, $offset);
                }
            }
            $obj_writer->end_element();
            // autoFilter
        }
        // Table Columns
        $obj_writer->start_element('tableColumns');
        $obj_writer->write_attribute('count', (string) ($range_end[0] - $range_start[0] + 1));
        foreach (range($range_start[0], $range_end[0]) as $offset => $column_index) {
            $worksheet = $table->get_worksheet();
            if (!$worksheet) {
                continue;
            }
            $column = $table->get_column_by_offset($offset);
            $cell = $worksheet->get_cell([$column_index, $range_start[1]]);
            $obj_writer->start_element('tableColumn');
            $obj_writer->write_attribute('id', (string) ($offset + 1));
            $obj_writer->write_attribute('name', $table->get_show_header_row() ? $cell->get_value_string() : 'Column' . ($offset + 1));
            if ($table->get_show_totals_row()) {
                if ($column->get_totals_row_label()) {
                    $obj_writer->write_attribute('totalsRowLabel', $column->get_totals_row_label());
                }
                if ($column->get_totals_row_function()) {
                    $obj_writer->write_attribute('totalsRowFunction', $column->get_totals_row_function());
                }
            }
            if ($column->get_column_formula()) {
                $obj_writer->write_element('calculatedColumnFormula', $column->get_column_formula());
            }
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        // Table Styles
        $obj_writer->start_element('tableStyleInfo');
        $obj_writer->write_attribute('name', $table->get_style()->get_theme());
        $obj_writer->write_attribute('showFirstColumn', $table->get_style()->get_show_first_column() ? '1' : '0');
        $obj_writer->write_attribute('showLastColumn', $table->get_style()->get_show_last_column() ? '1' : '0');
        $obj_writer->write_attribute('showRowStripes', $table->get_style()->get_show_row_stripes() ? '1' : '0');
        $obj_writer->write_attribute('showColumnStripes', $table->get_style()->get_show_column_stripes() ? '1' : '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
}