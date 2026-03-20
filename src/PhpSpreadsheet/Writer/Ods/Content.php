<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalculationException;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Worksheet\Row_Cell_Iterator;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Php_Office\Php_Spreadsheet\Writer\Ods;
use Php_Office\Php_Spreadsheet\Writer\Ods\Cell\Comment;
use Php_Office\Php_Spreadsheet\Writer\Ods\Cell\Style;
/**
 * @author     Alexander Pervakov <frost-nzcr4@jagmort.com>
 */
class Content extends Writer_Part
{
    private readonly Formula $formula_convertor;
    /**
     * Set parent Ods writer.
     */
    public function __construct(Ods $writer)
    {
        parent::__construct($writer);
        $this->formula_convertor = new Formula($this->get_parent_writer()->get_spreadsheet()->get_defined_names());
    }
    /**
     * Write content.xml to XML format.
     *
     * @return string XML Output
     */
    public function write(): string
    {
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8');
        // Content
        $obj_writer->start_element('office:document-content');
        $obj_writer->write_attribute('xmlns:office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $obj_writer->write_attribute('xmlns:style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
        $obj_writer->write_attribute('xmlns:text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $obj_writer->write_attribute('xmlns:table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $obj_writer->write_attribute('xmlns:draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
        $obj_writer->write_attribute('xmlns:fo', 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0');
        $obj_writer->write_attribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $obj_writer->write_attribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $obj_writer->write_attribute('xmlns:meta', 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0');
        $obj_writer->write_attribute('xmlns:number', 'urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0');
        $obj_writer->write_attribute('xmlns:presentation', 'urn:oasis:names:tc:opendocument:xmlns:presentation:1.0');
        $obj_writer->write_attribute('xmlns:svg', 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0');
        $obj_writer->write_attribute('xmlns:chart', 'urn:oasis:names:tc:opendocument:xmlns:chart:1.0');
        $obj_writer->write_attribute('xmlns:dr3d', 'urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0');
        $obj_writer->write_attribute('xmlns:math', 'http://www.w3.org/1998/Math/MathML');
        $obj_writer->write_attribute('xmlns:form', 'urn:oasis:names:tc:opendocument:xmlns:form:1.0');
        $obj_writer->write_attribute('xmlns:script', 'urn:oasis:names:tc:opendocument:xmlns:script:1.0');
        $obj_writer->write_attribute('xmlns:ooo', 'http://openoffice.org/2004/office');
        $obj_writer->write_attribute('xmlns:ooow', 'http://openoffice.org/2004/writer');
        $obj_writer->write_attribute('xmlns:oooc', 'http://openoffice.org/2004/calc');
        $obj_writer->write_attribute('xmlns:dom', 'http://www.w3.org/2001/xml-events');
        $obj_writer->write_attribute('xmlns:xforms', 'http://www.w3.org/2002/xforms');
        $obj_writer->write_attribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $obj_writer->write_attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $obj_writer->write_attribute('xmlns:rpt', 'http://openoffice.org/2005/report');
        $obj_writer->write_attribute('xmlns:of', 'urn:oasis:names:tc:opendocument:xmlns:of:1.2');
        $obj_writer->write_attribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $obj_writer->write_attribute('xmlns:grddl', 'http://www.w3.org/2003/g/data-view#');
        $obj_writer->write_attribute('xmlns:tableooo', 'http://openoffice.org/2009/table');
        $obj_writer->write_attribute('xmlns:field', 'urn:openoffice:names:experimental:ooo-ms-interop:xmlns:field:1.0');
        $obj_writer->write_attribute('xmlns:formx', 'urn:openoffice:names:experimental:ooxml-odf-interop:xmlns:form:1.0');
        $obj_writer->write_attribute('xmlns:css3t', 'http://www.w3.org/TR/css3-text/');
        $obj_writer->write_attribute('office:version', '1.2');
        $obj_writer->write_element('office:scripts');
        $obj_writer->write_element('office:font-face-decls');
        // Styles XF
        $obj_writer->start_element('office:automatic-styles');
        $this->write_xf_styles($obj_writer, $this->get_parent_writer()->get_spreadsheet());
        $obj_writer->end_element();
        $obj_writer->start_element('office:body');
        $obj_writer->start_element('office:spreadsheet');
        $obj_writer->write_element('table:calculation-settings');
        $this->write_sheets($obj_writer);
        (new Auto_Filters($obj_writer, $this->get_parent_writer()->get_spreadsheet()))->write();
        // Defined names (ranges and formulae)
        (new Named_Expressions($obj_writer, $this->get_parent_writer()->get_spreadsheet(), $this->formula_convertor))->write();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write sheets.
     */
    private function write_sheets(Xml_Writer $obj_writer): void
    {
        $spreadsheet = $this->get_parent_writer()->get_spreadsheet();
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($sheet_index = 0; $sheet_index < $sheet_count; ++$sheet_index) {
            $spreadsheet->get_sheet($sheet_index)->calculate_arrays($this->get_parent_writer()->get_pre_calculate_formulas());
            $obj_writer->start_element('table:table');
            $obj_writer->write_attribute('table:name', $spreadsheet->get_sheet($sheet_index)->get_title());
            $obj_writer->write_attribute('table:style-name', Style::TABLE_STYLE_PREFIX . ($sheet_index + 1));
            $obj_writer->write_element('office:forms');
            $last_column = 0;
            foreach ($spreadsheet->get_sheet($sheet_index)->get_column_dimensions() as $column_dimension) {
                $this_column = $column_dimension->get_column_numeric();
                $empty_columns = $this_column - $last_column - 1;
                if ($empty_columns > 0) {
                    $obj_writer->start_element('table:table-column');
                    $obj_writer->write_attribute('table:number-columns-repeated', (string) $empty_columns);
                    $obj_writer->end_element();
                }
                $last_column = $this_column;
                $obj_writer->start_element('table:table-column');
                $obj_writer->write_attribute('table:style-name', sprintf('%s_%d_%d', Style::COLUMN_STYLE_PREFIX, $sheet_index, $column_dimension->get_column_numeric()));
                $obj_writer->end_element();
            }
            $this->write_rows($obj_writer, $spreadsheet->get_sheet($sheet_index), $sheet_index);
            $obj_writer->end_element();
        }
    }
    /**
     * Write rows of the specified sheet.
     */
    private function write_rows(Xml_Writer $obj_writer, Worksheet $sheet, int $sheet_index): void
    {
        $span_row = 0;
        $rows = $sheet->get_row_iterator();
        foreach ($rows as $row) {
            $cell_iterator = $row->get_cell_iterator(iterateOnlyExistingCells: true);
            $cell_iterator->rewind();
            $row_style_exists = $sheet->row_dimension_exists($row->get_row_index()) && $sheet->get_row_dimension($row->get_row_index())->get_row_height() > 0;
            if ($cell_iterator->valid() || $row_style_exists) {
                if ($span_row) {
                    $obj_writer->start_element('table:table-row');
                    $obj_writer->write_attribute('table:number-rows-repeated', (string) $span_row);
                    $obj_writer->end_element();
                    $span_row = 0;
                }
                $obj_writer->start_element('table:table-row');
                if ($row_style_exists) {
                    $obj_writer->write_attribute('table:style-name', sprintf('%s_%d_%d', Style::ROW_STYLE_PREFIX, $sheet_index, $row->get_row_index()));
                } elseif ($sheet->get_default_row_dimension()->get_row_height() > 0.0 && !$sheet->get_row_dimension($row->get_row_index())->get_custom_format()) {
                    $obj_writer->write_attribute('table:style-name', sprintf('%s%d', Style::ROW_STYLE_PREFIX, $sheet_index));
                }
                $this->write_cells($obj_writer, $cell_iterator);
                $obj_writer->end_element();
            } else {
                ++$span_row;
            }
        }
    }
    /**
     * Write cells of the specified row.
     */
    private function write_cells(Xml_Writer $obj_writer, Row_Cell_Iterator $cells): void
    {
        $prev_column = -1;
        foreach ($cells as $cell) {
            /** @var Cell $cell */
            $column = Coordinate::column_index_from_string($cell->get_column()) - 1;
            $attributes = $cell->get_formula_attributes() ?? [];
            $this->write_cell_span($obj_writer, $column, $prev_column);
            $obj_writer->start_element('table:table-cell');
            $this->write_cell_merge($obj_writer, $cell);
            // Style XF
            $style = $cell->get_xf_index();
            $obj_writer->write_attribute('table:style-name', Style::CELL_STYLE_PREFIX . $style);
            switch ($cell->get_data_type()) {
                case Data_Type::TYPE_BOOL:
                    $obj_writer->write_attribute('office:value-type', 'boolean');
                    $obj_writer->write_attribute('office:boolean-value', $cell->get_value() ? 'true' : 'false');
                    $obj_writer->write_element('text:p', Calculation::get_instance()->get_locale_boolean($cell->get_value() ? 'TRUE' : 'FALSE'));
                    break;
                case Data_Type::TYPE_ERROR:
                    $obj_writer->write_attribute('table:formula', 'of:=#NULL!');
                    $obj_writer->write_attribute('office:value-type', 'string');
                    $obj_writer->write_attribute('office:string-value', '');
                    $obj_writer->write_element('text:p', '#NULL!');
                    break;
                case Data_Type::TYPE_FORMULA:
                    $formula_value = $cell->get_value_string();
                    $formula_value_calc = $formula_value;
                    if ($this->get_parent_writer()->get_pre_calculate_formulas()) {
                        try {
                            $formula_value = $cell->get_calculated_value_string();
                            $formula_value_calc = $cell->get_calculated_value();
                        } catch (Calculation_Exception) {
                            $formula_value = $formula_value_calc = Excel_Error::CALC();
                        }
                    }
                    if (isset($attributes['ref'])) {
                        if (Preg::is_match('/^([A-Z]{1,3})([0-9]{1,7})(:([A-Z]{1,3})([0-9]{1,7}))?$/', $attributes['ref'], $matches)) {
                            $matrix_row_span = 1;
                            $matrix_col_span = 1;
                            if (isset($matches[3])) {
                                $min_row = (int) $matches[2];
                                $max_row = (int) $matches[5];
                                $matrix_row_span = $max_row - $min_row + 1;
                                $min_col = Coordinate::column_index_from_string($matches[1]);
                                $max_col = Coordinate::column_index_from_string($matches[4]);
                                $matrix_col_span = $max_col - $min_col + 1;
                            }
                            $obj_writer->write_attribute('table:number-matrix-columns-spanned', "{$matrix_col_span}");
                            $obj_writer->write_attribute('table:number-matrix-rows-spanned', "{$matrix_row_span}");
                        }
                    }
                    $obj_writer->write_attribute('table:formula', $this->formula_convertor->convert_formula($cell->get_value_string()));
                    if (is_bool($formula_value_calc)) {
                        $obj_writer->write_attribute('office:value-type', 'boolean');
                        $obj_writer->write_attribute('office:boolean-value', $formula_value_calc ? 'true' : 'false');
                        $obj_writer->write_element('text:p', $formula_value_calc ? 'TRUE' : 'FALSE');
                        break;
                    }
                    if (!is_numeric($formula_value)) {
                        $obj_writer->write_attribute('office:value-type', 'string');
                        $obj_writer->write_attribute('office:string-value', $formula_value);
                        $obj_writer->write_element('text:p', $formula_value);
                        break;
                    }
                // no break
                case Data_Type::TYPE_NUMERIC:
                    $hold_worksheet = $cell->get_worksheet();
                    $hold_selected = $hold_worksheet->get_selected_cells();
                    $hold_spreadsheet = $hold_worksheet->get_parent();
                    $hold_active_sheet_index = $hold_spreadsheet?->get_active_sheet_index();
                    $formatted = $cell->get_formatted_value();
                    $type = 'float';
                    $value_type = 'value';
                    $value = $cell->get_calculated_value_string();
                    $num_fmt = $cell->get_style()->get_number_format()->get_format_code() ?? '';
                    $hold_worksheet->set_selected_cells($hold_selected);
                    if (isset($hold_spreadsheet, $hold_active_sheet_index)) {
                        $hold_spreadsheet->set_active_sheet_index($hold_active_sheet_index);
                    }
                    if (Date::is_date_time_format_code($num_fmt, true)) {
                        $value_calc = $cell->get_calculated_value_string();
                        if (Preg::is_match('/[HhSs]/', $num_fmt) && !Preg::is_match('/[YyDd]/', $num_fmt)) {
                            $minus = '';
                            $type = 'time';
                            $value_type = 'time-value';
                            if (str_starts_with($value_calc, '-')) {
                                $minus = '-';
                                $abs_val = fmod(abs((float) $value), 1.0);
                                $hms = (int) round(86400 * $abs_val);
                                $hours = intdiv($hms, 3600);
                                $hms -= $hours * 3600;
                                $minutes = intdiv($hms, 60);
                                $seconds = $hms % 60;
                                $value = sprintf('-PT%02dH%02dM%02dS', $hours, $minutes, $seconds);
                                $formatted = sprintf('-%02d:%02d:%02d', $hours, $minutes, $seconds);
                            } else {
                                $hhmmss = Number_Format::to_formatted_string($value, Number_Format::FORMAT_DATE_TIME_INTERVAL_HMS);
                                $days_and_hours = 24 * (int) $value_calc + (int) substr($hhmmss, 0, 2);
                                $value = "PT{$days_and_hours}" . 'H' . substr($hhmmss, 3, 2) . 'M' . substr($hhmmss, 6, 2) . 'S';
                            }
                        } else {
                            $type = 'date';
                            $value_type = 'date-value';
                            $value = Number_Format::to_formatted_string($value, 'yyyy-mm-dd"T"hh:mm:ss');
                        }
                    } elseif (str_ends_with($num_fmt, '%')) {
                        $type = 'percentage';
                    }
                    if ($num_fmt === Number_Format::FORMAT_CURRENCY_EUR || $num_fmt === Number_Format::FORMAT_CURRENCY_EUR_INTEGER) {
                        $obj_writer->write_attribute('office:value-type', 'currency');
                        $obj_writer->write_attribute('office:currency', 'EUR');
                        $obj_writer->write_attribute('office:value', $value);
                    } else {
                        $obj_writer->write_attribute('office:value-type', $type);
                        $obj_writer->write_attribute("office:{$value_type}", $value);
                    }
                    $obj_writer->write_element('text:p', $formatted);
                    break;
                case Data_Type::TYPE_INLINE:
                // break intentionally omitted
                case Data_Type::TYPE_STRING:
                    $obj_writer->write_attribute('office:value-type', 'string');
                    $url = $cell->get_hyperlink()->get_url();
                    if (empty($url)) {
                        $obj_writer->write_element('text:p', $cell->get_value_string());
                    } else {
                        $obj_writer->start_element('text:p');
                        $obj_writer->start_element('text:a');
                        $sheets = 'sheet://';
                        $lensheets = strlen($sheets);
                        if (substr($url, 0, $lensheets) === $sheets) {
                            $url = '#' . substr($url, $lensheets);
                        }
                        $obj_writer->write_attribute('xlink:href', $url);
                        $obj_writer->write_attribute('xlink:type', 'simple');
                        $obj_writer->text($cell->get_value_string());
                        $obj_writer->end_element();
                        // text:a
                        $obj_writer->end_element();
                        // text:p
                    }
                    break;
            }
            Comment::write($obj_writer, $cell);
            $obj_writer->end_element();
            $prev_column = $column;
        }
    }
    /**
     * Write span.
     */
    private function write_cell_span(Xml_Writer $obj_writer, int $cur_column, int $prev_column): void
    {
        $diff = $cur_column - $prev_column - 1;
        if (1 === $diff) {
            $obj_writer->write_element('table:table-cell');
        } elseif ($diff > 1) {
            $obj_writer->start_element('table:table-cell');
            $obj_writer->write_attribute('table:number-columns-repeated', (string) $diff);
            $obj_writer->end_element();
        }
    }
    /** @var array<string, callable> */
    public array $additional_number_formats = [];
    /**
     * Write XF cell styles.
     */
    private function write_xf_styles(Xml_Writer $writer, Spreadsheet $spreadsheet): void
    {
        $style_writer = new Style($writer, $this->additional_number_formats);
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            $worksheet = $spreadsheet->get_sheet($i);
            $style_writer->write_table_style($worksheet, $i + 1);
            $worksheet->calculate_column_widths();
            foreach ($worksheet->get_column_dimensions() as $column_dimension) {
                if ($column_dimension->get_width() !== -1.0) {
                    $style_writer->write_column_styles($column_dimension, $i);
                }
            }
        }
        for ($i = 0; $i < $sheet_count; ++$i) {
            $worksheet = $spreadsheet->get_sheet($i);
            $default = $worksheet->get_default_row_dimension();
            if ($default->get_row_height() > 0.0) {
                $style_writer->write_default_row_style($default, $i);
            }
            foreach ($worksheet->get_row_dimensions() as $row_dimension) {
                if ($row_dimension->get_row_height() > 0.0) {
                    $style_writer->write_row_styles($row_dimension, $i);
                }
            }
        }
        foreach ($spreadsheet->get_cell_xf_collection() as $style) {
            $style_writer->write($style);
        }
    }
    /**
     * Write attributes for merged cell.
     */
    private function write_cell_merge(Xml_Writer $obj_writer, Cell $cell): void
    {
        if (!$cell->is_merge_range_value_cell()) {
            return;
        }
        $merge_range = Coordinate::split_range((string) $cell->get_merge_range());
        [$start_cell, $end_cell] = $merge_range[0];
        $start = Coordinate::coordinate_from_string($start_cell);
        $end = Coordinate::coordinate_from_string($end_cell);
        $column_span = Coordinate::column_index_from_string($end[0]) - Coordinate::column_index_from_string($start[0]) + 1;
        $row_span = (int) $end[1] - (int) $start[1] + 1;
        $obj_writer->write_attribute('table:number-columns-spanned', (string) $column_span);
        $obj_writer->write_attribute('table:number-rows-spanned', (string) $row_span);
    }
}