<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Color_Scale;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Data_Bar;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Formatting_Rule_Extension;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Icon_Set;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Row_Dimension;
use Php_Office\Php_Spreadsheet\Worksheet\Sheet_View;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet as PhpspreadsheetWorksheet;
class Worksheet extends Writer_Part
{
    private string $number_stored_as_text = '';
    private string $formula = '';
    private string $formula_range = '';
    private string $two_digit_text_year = '';
    private string $eval_error = '';
    private bool $explicit_style0;
    private bool $use_dynamic_arrays = false;
    private bool $restrict_max_column_width = false;
    /**
     * Write worksheet to XML format.
     *
     * @param string[] $stringTable
     * @param bool $includeCharts Flag indicating if we should write charts
     *
     * @return string XML Output
     */
    public function write_worksheet(Phpspreadsheet_Worksheet $worksheet, array $string_table = [], bool $include_charts = false): string
    {
        $this->use_dynamic_arrays = $this->get_parent_writer()->use_dynamic_arrays();
        $this->explicit_style0 = $this->get_parent_writer()->get_explicit_style0();
        $worksheet->calculate_arrays($this->get_parent_writer()->get_pre_calculate_formulas());
        $this->number_stored_as_text = '';
        $this->formula = '';
        $this->formula_range = '';
        $this->two_digit_text_year = '';
        $this->eval_error = '';
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        $this->restrict_max_column_width = $this->get_parent_writer()->get_restrict_max_column_width();
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Worksheet
        $obj_writer->start_element('worksheet');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
        $obj_writer->write_attribute('xmlns:xdr', Namespaces::SPREADSHEET_DRAWING);
        $obj_writer->write_attribute('xmlns:x14', Namespaces::DATA_VALIDATIONS1);
        $obj_writer->write_attribute('xmlns:xm', Namespaces::DATA_VALIDATIONS2);
        $obj_writer->write_attribute('xmlns:mc', Namespaces::COMPATIBILITY);
        $obj_writer->write_attribute('mc:Ignorable', 'x14ac');
        $obj_writer->write_attribute('xmlns:x14ac', Namespaces::SPREADSHEETML_AC);
        // sheetPr
        $this->write_sheet_pr($obj_writer, $worksheet);
        // Dimension
        $this->write_dimension($obj_writer, $worksheet);
        // sheetViews
        $this->write_sheet_views($obj_writer, $worksheet);
        // sheetFormatPr
        $this->write_sheet_format_pr($obj_writer, $worksheet);
        // cols
        $this->write_cols($obj_writer, $worksheet);
        // sheetData
        $this->write_sheet_data($obj_writer, $worksheet, $string_table);
        // sheetProtection
        $this->write_sheet_protection($obj_writer, $worksheet);
        // protectedRanges
        $this->write_protected_ranges($obj_writer, $worksheet);
        // autoFilter
        $this->write_auto_filter($obj_writer, $worksheet);
        // mergeCells
        $this->write_merge_cells($obj_writer, $worksheet);
        // conditionalFormatting
        $this->write_conditional_formatting($obj_writer, $worksheet);
        // dataValidations
        $this->write_data_validations($obj_writer, $worksheet);
        // hyperlinks
        $this->write_hyperlinks($obj_writer, $worksheet);
        // Print options
        $this->write_print_options($obj_writer, $worksheet);
        // Page margins
        $this->write_page_margins($obj_writer, $worksheet);
        // Page setup
        $this->write_page_setup($obj_writer, $worksheet);
        // Header / footer
        $this->write_header_footer($obj_writer, $worksheet);
        // Breaks
        $this->write_breaks($obj_writer, $worksheet);
        // IgnoredErrors
        $this->write_ignored_errors($obj_writer);
        // Drawings and/or Charts
        $this->write_drawings($obj_writer, $worksheet, $include_charts);
        // LegacyDrawing
        $this->write_legacy_drawing($obj_writer, $worksheet);
        // LegacyDrawingHF
        $this->write_legacy_drawing_hf($obj_writer, $worksheet);
        // AlternateContent
        $this->write_alternate_content($obj_writer, $worksheet);
        // BackgroundImage must come after ignored, before table
        $this->write_background_image($obj_writer, $worksheet);
        // Table
        $this->write_table($obj_writer, $worksheet);
        // ConditionalFormattingRuleExtensionList
        // (Must be inserted last. Not insert last, an Excel parse error will occur)
        $this->write_ext_lst($obj_writer, $worksheet);
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    private function write_ignored_error(Xml_Writer $obj_writer, bool &$started, string $attr, string $cells): void
    {
        if ($cells !== '') {
            if (!$started) {
                $obj_writer->start_element('ignoredErrors');
                $started = true;
            }
            $obj_writer->start_element('ignoredError');
            $obj_writer->write_attribute('sqref', substr($cells, 1));
            $obj_writer->write_attribute($attr, '1');
            $obj_writer->end_element();
        }
    }
    private function write_ignored_errors(Xml_Writer $obj_writer): void
    {
        $started = false;
        $this->write_ignored_error($obj_writer, $started, 'numberStoredAsText', $this->number_stored_as_text);
        $this->write_ignored_error($obj_writer, $started, 'formula', $this->formula);
        $this->write_ignored_error($obj_writer, $started, 'formulaRange', $this->formula_range);
        $this->write_ignored_error($obj_writer, $started, 'twoDigitTextYear', $this->two_digit_text_year);
        $this->write_ignored_error($obj_writer, $started, 'evalError', $this->eval_error);
        if ($started) {
            $obj_writer->end_element();
        }
    }
    /**
     * Write SheetPr.
     */
    private function write_sheet_pr(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // sheetPr
        $obj_writer->start_element('sheetPr');
        if ($worksheet->get_parent_or_throw()->has_macros()) {
            //if the workbook have macros, we need to have codeName for the sheet
            if (!$worksheet->has_code_name()) {
                $worksheet->set_code_name($worksheet->get_title());
            }
            self::write_attribute_not_null($obj_writer, 'codeName', $worksheet->get_code_name());
        }
        $auto_filter_range = $worksheet->get_auto_filter()->get_range();
        if (!empty($auto_filter_range)) {
            $obj_writer->write_attribute('filterMode', '1');
            if (!$worksheet->get_auto_filter()->get_evaluated()) {
                $worksheet->get_auto_filter()->show_hide_rows();
            }
        }
        $tables = $worksheet->get_table_collection();
        if (count($tables)) {
            foreach ($tables as $table) {
                if (!$table->get_auto_filter()->get_evaluated()) {
                    $table->get_auto_filter()->show_hide_rows();
                }
            }
        }
        // tabColor
        if ($worksheet->is_tab_color_set()) {
            $obj_writer->start_element('tabColor');
            $obj_writer->write_attribute('rgb', $worksheet->get_tab_color()->get_argb() ?? '');
            $obj_writer->end_element();
        }
        // outlinePr
        $obj_writer->start_element('outlinePr');
        $obj_writer->write_attribute('summaryBelow', $worksheet->get_show_summary_below() ? '1' : '0');
        $obj_writer->write_attribute('summaryRight', $worksheet->get_show_summary_right() ? '1' : '0');
        $obj_writer->end_element();
        // pageSetUpPr
        if ($worksheet->get_page_setup()->get_fit_to_page()) {
            $obj_writer->start_element('pageSetUpPr');
            $obj_writer->write_attribute('fitToPage', '1');
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
    }
    /**
     * Write Dimension.
     */
    private function write_dimension(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // dimension
        $obj_writer->start_element('dimension');
        $obj_writer->write_attribute('ref', $worksheet->calculate_worksheet_dimension());
        $obj_writer->end_element();
    }
    /**
     * Write SheetViews.
     */
    private function write_sheet_views(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // sheetViews
        $obj_writer->start_element('sheetViews');
        // Sheet selected?
        $sheet_selected = false;
        if ($this->get_parent_writer()->get_spreadsheet()->get_index($worksheet) == $this->get_parent_writer()->get_spreadsheet()->get_active_sheet_index()) {
            $sheet_selected = true;
        }
        // sheetView
        $obj_writer->start_element('sheetView');
        $obj_writer->write_attribute('tabSelected', $sheet_selected ? '1' : '0');
        $obj_writer->write_attribute('workbookViewId', '0');
        // Zoom scales
        $zoom_scale = $worksheet->get_sheet_view()->get_zoom_scale();
        if ($zoom_scale !== 100 && $zoom_scale !== null) {
            $obj_writer->write_attribute('zoomScale', (string) $zoom_scale);
        }
        $zoom_scale = $worksheet->get_sheet_view()->get_zoom_scale_normal();
        if ($zoom_scale !== 100 && $zoom_scale !== null) {
            $obj_writer->write_attribute('zoomScaleNormal', (string) $zoom_scale);
        }
        $zoom_scale = $worksheet->get_sheet_view()->get_zoom_scale_page_layout_view();
        if ($zoom_scale !== 100) {
            $obj_writer->write_attribute('zoomScalePageLayoutView', (string) $zoom_scale);
        }
        $zoom_scale = $worksheet->get_sheet_view()->get_zoom_scale_sheet_layout_view();
        if ($zoom_scale !== 100) {
            $obj_writer->write_attribute('zoomScaleSheetLayoutView', (string) $zoom_scale);
        }
        // Show zeros (Excel also writes this attribute only if set to false)
        if ($worksheet->get_sheet_view()->get_show_zeros() === false) {
            $obj_writer->write_attribute('showZeros', '0');
        }
        // View Layout Type
        if ($worksheet->get_sheet_view()->get_view() !== Sheet_View::SHEETVIEW_NORMAL) {
            $obj_writer->write_attribute('view', $worksheet->get_sheet_view()->get_view());
        }
        // Gridlines
        if ($worksheet->get_show_gridlines()) {
            $obj_writer->write_attribute('showGridLines', 'true');
        } else {
            $obj_writer->write_attribute('showGridLines', 'false');
        }
        // Row and column headers
        if ($worksheet->get_show_row_col_headers()) {
            $obj_writer->write_attribute('showRowColHeaders', '1');
        } else {
            $obj_writer->write_attribute('showRowColHeaders', '0');
        }
        // Right-to-left
        if ($worksheet->get_right_to_left()) {
            $obj_writer->write_attribute('rightToLeft', 'true');
        }
        $top_left_cell = $worksheet->get_top_left_cell();
        if (!empty($top_left_cell) && $worksheet->get_pane_state() !== Phpspreadsheet_Worksheet::PANE_FROZEN && $worksheet->get_pane_state() !== Phpspreadsheet_Worksheet::PANE_FROZENSPLIT) {
            $obj_writer->write_attribute('topLeftCell', $top_left_cell);
        }
        $active_cell = $worksheet->get_active_cell();
        $sqref = $worksheet->get_selected_cells();
        // Pane
        if ($worksheet->uses_panes()) {
            $obj_writer->start_element('pane');
            $x_split = $worksheet->get_x_split();
            $y_split = $worksheet->get_y_split();
            $pane = $worksheet->get_active_pane();
            $pane_top_left_cell = $worksheet->get_pane_top_left_cell();
            $pane_state = $worksheet->get_pane_state();
            $normal_freeze = '';
            if ($pane_state === Phpspreadsheet_Worksheet::PANE_FROZEN) {
                if ($y_split > 0) {
                    $normal_freeze = $x_split <= 0 ? 'bottomLeft' : 'bottomRight';
                } else {
                    $normal_freeze = 'topRight';
                }
            }
            if ($x_split > 0) {
                $obj_writer->write_attribute('xSplit', "{$x_split}");
            }
            if ($y_split > 0) {
                $obj_writer->write_attribute('ySplit', "{$y_split}");
            }
            if ($normal_freeze !== '') {
                $obj_writer->write_attribute('activePane', $normal_freeze);
            } elseif ($pane !== '') {
                $obj_writer->write_attribute('activePane', $pane);
            }
            if ($pane_state !== '') {
                $obj_writer->write_attribute('state', $pane_state);
            }
            if ($pane_top_left_cell !== '') {
                $obj_writer->write_attribute('topLeftCell', $pane_top_left_cell);
            }
            $obj_writer->end_element();
            // pane
            if ($normal_freeze !== '') {
                $obj_writer->start_element('selection');
                $obj_writer->write_attribute('pane', $normal_freeze);
                if ($active_cell !== '') {
                    $obj_writer->write_attribute('activeCell', $active_cell);
                }
                if ($sqref !== '') {
                    $obj_writer->write_attribute('sqref', $sqref);
                }
                $obj_writer->end_element();
                // selection
                $sqref = $active_cell = '';
            } else {
                foreach ($worksheet->get_panes() as $panex) {
                    if ($panex !== null) {
                        $sqref = $active_cell = '';
                        $obj_writer->start_element('selection');
                        $obj_writer->write_attribute('pane', $panex->get_position());
                        $active_cell_pane = $panex->get_active_cell();
                        if ($active_cell_pane !== '') {
                            $obj_writer->write_attribute('activeCell', $active_cell_pane);
                        }
                        $sqref_pane = $panex->get_sqref();
                        if ($sqref_pane !== '') {
                            $obj_writer->write_attribute('sqref', $sqref_pane);
                        }
                        $obj_writer->end_element();
                        // selection
                    }
                }
            }
        }
        // Selection
        // Only need to write selection element if we have a split pane
        // We cheat a little by over-riding the active cell selection, setting it to the split cell
        if (!empty($sqref) || !empty($active_cell)) {
            $obj_writer->start_element('selection');
            if (!empty($active_cell)) {
                $obj_writer->write_attribute('activeCell', $active_cell);
            }
            if (!empty($sqref)) {
                $obj_writer->write_attribute('sqref', $sqref);
            }
            $obj_writer->end_element();
            // selection
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Write SheetFormatPr.
     */
    private function write_sheet_format_pr(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // sheetFormatPr
        $obj_writer->start_element('sheetFormatPr');
        // Default row height
        if ($worksheet->get_default_row_dimension()->get_row_height() >= 0) {
            $obj_writer->write_attribute('customHeight', 'true');
            $obj_writer->write_attribute('defaultRowHeight', String_Helper::format_number($worksheet->get_default_row_dimension()->get_row_height()));
        } else {
            $obj_writer->write_attribute('defaultRowHeight', '14.4');
        }
        // Set Zero Height row
        if ($worksheet->get_default_row_dimension()->get_zero_height()) {
            $obj_writer->write_attribute('zeroHeight', '1');
        }
        // Default column width
        if ($worksheet->get_default_column_dimension()->get_width() >= 0) {
            $obj_writer->write_attribute('defaultColWidth', String_Helper::format_number($worksheet->get_default_column_dimension()->get_width_for_output($this->restrict_max_column_width)));
        }
        // Outline level - row
        $outline_level_row = 0;
        foreach ($worksheet->get_row_dimensions() as $dimension) {
            if ($dimension->get_outline_level() > $outline_level_row) {
                $outline_level_row = $dimension->get_outline_level();
            }
        }
        $obj_writer->write_attribute('outlineLevelRow', (string) (int) $outline_level_row);
        // Outline level - column
        $outline_level_col = 0;
        foreach ($worksheet->get_column_dimensions() as $dimension) {
            if ($dimension->get_outline_level() > $outline_level_col) {
                $outline_level_col = $dimension->get_outline_level();
            }
        }
        $obj_writer->write_attribute('outlineLevelCol', (string) (int) $outline_level_col);
        $obj_writer->end_element();
    }
    /**
     * Write Cols.
     */
    private function write_cols(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // cols
        if (count($worksheet->get_column_dimensions()) > 0) {
            $obj_writer->start_element('cols');
            $worksheet->calculate_column_widths();
            // Loop through column dimensions
            foreach ($worksheet->get_column_dimensions() as $col_dimension) {
                // col
                $obj_writer->start_element('col');
                $obj_writer->write_attribute('min', (string) Coordinate::column_index_from_string($col_dimension->get_column_index()));
                $obj_writer->write_attribute('max', (string) Coordinate::column_index_from_string($col_dimension->get_column_index()));
                if ($col_dimension->get_width() < 0) {
                    // No width set, apply default of 10
                    $obj_writer->write_attribute('width', '9.10');
                } else {
                    // Width set
                    $obj_writer->write_attribute('width', String_Helper::format_number($col_dimension->get_width_for_output($this->restrict_max_column_width)));
                }
                // Column visibility
                if ($col_dimension->get_visible() === false) {
                    $obj_writer->write_attribute('hidden', 'true');
                }
                // Auto size?
                if ($col_dimension->get_auto_size()) {
                    $obj_writer->write_attribute('bestFit', 'true');
                }
                // Custom width?
                if ($col_dimension->get_width() != $worksheet->get_default_column_dimension()->get_width()) {
                    $obj_writer->write_attribute('customWidth', 'true');
                }
                // Collapsed
                if ($col_dimension->get_collapsed() === true) {
                    $obj_writer->write_attribute('collapsed', 'true');
                }
                // Outline level
                if ($col_dimension->get_outline_level() > 0) {
                    $obj_writer->write_attribute('outlineLevel', (string) $col_dimension->get_outline_level());
                }
                // Style
                $obj_writer->write_attribute('style', (string) $col_dimension->get_xf_index());
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write SheetProtection.
     */
    private function write_sheet_protection(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        $protection = $worksheet->get_protection();
        if (!$protection->is_protection_enabled()) {
            return;
        }
        // sheetProtection
        $obj_writer->start_element('sheetProtection');
        if ($protection->get_algorithm()) {
            $obj_writer->write_attribute('algorithmName', $protection->get_algorithm());
            $obj_writer->write_attribute('hashValue', $protection->get_password());
            $obj_writer->write_attribute('saltValue', $protection->get_salt());
            $obj_writer->write_attribute('spinCount', (string) $protection->get_spin_count());
        } elseif ($protection->get_password() !== '') {
            $obj_writer->write_attribute('password', $protection->get_password());
        }
        self::write_protection_attribute($obj_writer, 'sheet', $protection->get_sheet());
        self::write_protection_attribute($obj_writer, 'objects', $protection->get_objects());
        self::write_protection_attribute($obj_writer, 'scenarios', $protection->get_scenarios());
        self::write_protection_attribute($obj_writer, 'formatCells', $protection->get_format_cells());
        self::write_protection_attribute($obj_writer, 'formatColumns', $protection->get_format_columns());
        self::write_protection_attribute($obj_writer, 'formatRows', $protection->get_format_rows());
        self::write_protection_attribute($obj_writer, 'insertColumns', $protection->get_insert_columns());
        self::write_protection_attribute($obj_writer, 'insertRows', $protection->get_insert_rows());
        self::write_protection_attribute($obj_writer, 'insertHyperlinks', $protection->get_insert_hyperlinks());
        self::write_protection_attribute($obj_writer, 'deleteColumns', $protection->get_delete_columns());
        self::write_protection_attribute($obj_writer, 'deleteRows', $protection->get_delete_rows());
        self::write_protection_attribute($obj_writer, 'sort', $protection->get_sort());
        self::write_protection_attribute($obj_writer, 'autoFilter', $protection->get_auto_filter());
        self::write_protection_attribute($obj_writer, 'pivotTables', $protection->get_pivot_tables());
        self::write_protection_attribute($obj_writer, 'selectLockedCells', $protection->get_select_locked_cells());
        self::write_protection_attribute($obj_writer, 'selectUnlockedCells', $protection->get_select_unlocked_cells());
        $obj_writer->end_element();
    }
    private static function write_protection_attribute(Xml_Writer $obj_writer, string $name, ?bool $value): void
    {
        if ($value === true) {
            $obj_writer->write_attribute($name, '1');
        } elseif ($value === false) {
            $obj_writer->write_attribute($name, '0');
        }
    }
    private static function write_attribute_if(Xml_Writer $obj_writer, ?bool $condition, string $attr, string $val): void
    {
        if ($condition) {
            $obj_writer->write_attribute($attr, $val);
        }
    }
    private static function write_attribute_not_null(Xml_Writer $obj_writer, string $attr, ?string $val): void
    {
        if ($val !== null) {
            $obj_writer->write_attribute($attr, $val);
        }
    }
    private static function write_element_if(Xml_Writer $obj_writer, bool $condition, string $attr, string $val): void
    {
        if ($condition) {
            $obj_writer->write_element($attr, $val);
        }
    }
    private static function write_other_cond_elements(Xml_Writer $obj_writer, Conditional $conditional, string $cell_coordinate): void
    {
        $conditions = $conditional->get_conditions();
        if ($conditional->get_condition_type() == Conditional::CONDITION_CELLIS || $conditional->get_condition_type() == Conditional::CONDITION_EXPRESSION || !empty($conditions)) {
            foreach ($conditions as $formula) {
                // Formula
                if (is_bool($formula)) {
                    $formula = $formula ? 'TRUE' : 'FALSE';
                }
                $obj_writer->write_element('formula', Function_Prefix::add_function_prefix("{$formula}"));
            }
        } else if ($conditional->get_condition_type() == Conditional::CONDITION_CONTAINSBLANKS) {
            // formula copied from ms xlsx xml source file
            $obj_writer->write_element('formula', 'LEN(TRIM(' . $cell_coordinate . '))=0');
        } elseif ($conditional->get_condition_type() == Conditional::CONDITION_NOTCONTAINSBLANKS) {
            // formula copied from ms xlsx xml source file
            $obj_writer->write_element('formula', 'LEN(TRIM(' . $cell_coordinate . '))>0');
        } elseif ($conditional->get_condition_type() == Conditional::CONDITION_CONTAINSERRORS) {
            // formula copied from ms xlsx xml source file
            $obj_writer->write_element('formula', 'ISERROR(' . $cell_coordinate . ')');
        } elseif ($conditional->get_condition_type() == Conditional::CONDITION_NOTCONTAINSERRORS) {
            // formula copied from ms xlsx xml source file
            $obj_writer->write_element('formula', 'NOT(ISERROR(' . $cell_coordinate . '))');
        }
    }
    private static function write_time_period_cond_elements(Xml_Writer $obj_writer, Conditional $conditional, string $cell_coordinate): void
    {
        $txt = $conditional->get_text();
        if (!empty($txt)) {
            $obj_writer->write_attribute('timePeriod', $txt);
            if (empty($conditional->get_conditions())) {
                if ($conditional->get_operator_type() == Conditional::TIMEPERIOD_TODAY) {
                    $obj_writer->write_element('formula', 'FLOOR(' . $cell_coordinate . ')=TODAY()');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_TOMORROW) {
                    $obj_writer->write_element('formula', 'FLOOR(' . $cell_coordinate . ')=TODAY()+1');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_YESTERDAY) {
                    $obj_writer->write_element('formula', 'FLOOR(' . $cell_coordinate . ')=TODAY()-1');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_LAST_7_DAYS) {
                    $obj_writer->write_element('formula', 'AND(TODAY()-FLOOR(' . $cell_coordinate . ',1)<=6,FLOOR(' . $cell_coordinate . ',1)<=TODAY())');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_LAST_WEEK) {
                    $obj_writer->write_element('formula', 'AND(TODAY()-ROUNDDOWN(' . $cell_coordinate . ',0)>=(WEEKDAY(TODAY())),TODAY()-ROUNDDOWN(' . $cell_coordinate . ',0)<(WEEKDAY(TODAY())+7))');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_THIS_WEEK) {
                    $obj_writer->write_element('formula', 'AND(TODAY()-ROUNDDOWN(' . $cell_coordinate . ',0)<=WEEKDAY(TODAY())-1,ROUNDDOWN(' . $cell_coordinate . ',0)-TODAY()<=7-WEEKDAY(TODAY()))');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_NEXT_WEEK) {
                    $obj_writer->write_element('formula', 'AND(ROUNDDOWN(' . $cell_coordinate . ',0)-TODAY()>(7-WEEKDAY(TODAY())),ROUNDDOWN(' . $cell_coordinate . ',0)-TODAY()<(15-WEEKDAY(TODAY())))');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_LAST_MONTH) {
                    $obj_writer->write_element('formula', 'AND(MONTH(' . $cell_coordinate . ')=MONTH(EDATE(TODAY(),0-1)),YEAR(' . $cell_coordinate . ')=YEAR(EDATE(TODAY(),0-1)))');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_THIS_MONTH) {
                    $obj_writer->write_element('formula', 'AND(MONTH(' . $cell_coordinate . ')=MONTH(TODAY()),YEAR(' . $cell_coordinate . ')=YEAR(TODAY()))');
                } elseif ($conditional->get_operator_type() == Conditional::TIMEPERIOD_NEXT_MONTH) {
                    $obj_writer->write_element('formula', 'AND(MONTH(' . $cell_coordinate . ')=MONTH(EDATE(TODAY(),0+1)),YEAR(' . $cell_coordinate . ')=YEAR(EDATE(TODAY(),0+1)))');
                }
            } else {
                $obj_writer->write_element('formula', (string) $conditional->get_conditions()[0]);
            }
        }
    }
    private static function write_text_cond_elements(Xml_Writer $obj_writer, Conditional $conditional, string $cell_coordinate): void
    {
        $txt = $conditional->get_text();
        if (!empty($txt)) {
            $obj_writer->write_attribute('text', $txt);
            if (empty($conditional->get_conditions())) {
                if ($conditional->get_operator_type() == Conditional::OPERATOR_CONTAINSTEXT) {
                    $obj_writer->write_element('formula', 'NOT(ISERROR(SEARCH("' . $txt . '",' . $cell_coordinate . ')))');
                } elseif ($conditional->get_operator_type() == Conditional::OPERATOR_BEGINSWITH) {
                    $obj_writer->write_element('formula', 'LEFT(' . $cell_coordinate . ',LEN("' . $txt . '"))="' . $txt . '"');
                } elseif ($conditional->get_operator_type() == Conditional::OPERATOR_ENDSWITH) {
                    $obj_writer->write_element('formula', 'RIGHT(' . $cell_coordinate . ',LEN("' . $txt . '"))="' . $txt . '"');
                } elseif ($conditional->get_operator_type() == Conditional::OPERATOR_NOTCONTAINS) {
                    $obj_writer->write_element('formula', 'ISERROR(SEARCH("' . $txt . '",' . $cell_coordinate . '))');
                }
            } else {
                $obj_writer->write_element('formula', (string) $conditional->get_conditions()[0]);
            }
        }
    }
    private static function write_ext_conditional_formatting_elements(Xml_Writer $obj_writer, Conditional_Formatting_Rule_Extension $rule_extension): void
    {
        $prefix = 'x14';
        $obj_writer->start_element_ns($prefix, 'conditionalFormatting', null);
        $obj_writer->start_element_ns($prefix, 'cfRule', null);
        $obj_writer->write_attribute('type', $rule_extension->get_cf_rule());
        $obj_writer->write_attribute('id', $rule_extension->get_id());
        $obj_writer->start_element_ns($prefix, 'dataBar', null);
        $data_bar = $rule_extension->get_data_bar_ext();
        foreach ($data_bar->get_xml_attributes() as $attr_key => $val) {
            /** @var string $val */
            $obj_writer->write_attribute($attr_key, $val);
        }
        $min_cfvo = $data_bar->get_minimum_conditional_format_value_object();
        // Phpstan is wrong about the next statement.
        // @phpstan-ignore-line
        $obj_writer->start_element_ns($prefix, 'cfvo', null);
        $obj_writer->write_attribute('type', $min_cfvo->get_type());
        if ($min_cfvo->get_cell_formula()) {
            $obj_writer->write_element('xm:f', $min_cfvo->get_cell_formula());
        }
        $obj_writer->end_element();
        //end cfvo
        $max_cfvo = $data_bar->get_maximum_conditional_format_value_object();
        // Phpstan is wrong about the next statement.
        // @phpstan-ignore-line
        $obj_writer->start_element_ns($prefix, 'cfvo', null);
        $obj_writer->write_attribute('type', $max_cfvo->get_type());
        if ($max_cfvo->get_cell_formula()) {
            $obj_writer->write_element('xm:f', $max_cfvo->get_cell_formula());
        }
        $obj_writer->end_element();
        //end cfvo
        foreach ($data_bar->get_xml_elements() as $elm_key => $elm_attr) {
            /** @var string[] $elmAttr */
            $obj_writer->start_element_ns($prefix, $elm_key, null);
            foreach ($elm_attr as $attr_key => $attr_val) {
                $obj_writer->write_attribute($attr_key, $attr_val);
            }
            $obj_writer->end_element();
            //end elmKey
        }
        $obj_writer->end_element();
        //end dataBar
        $obj_writer->end_element();
        //end cfRule
        $obj_writer->write_element('xm:sqref', $rule_extension->get_sqref());
        $obj_writer->end_element();
        //end conditionalFormatting
    }
    private static function write_data_bar_elements(Xml_Writer $obj_writer, ?Conditional_Data_Bar $data_bar): void
    {
        if ($data_bar) {
            $obj_writer->start_element('dataBar');
            self::write_attribute_if($obj_writer, null !== $data_bar->get_show_value(), 'showValue', $data_bar->get_show_value() ? '1' : '0');
            $min_cfvo = $data_bar->get_minimum_conditional_format_value_object();
            if ($min_cfvo) {
                $obj_writer->start_element('cfvo');
                $obj_writer->write_attribute('type', $min_cfvo->get_type());
                self::write_attribute_if($obj_writer, $min_cfvo->get_value() !== null, 'val', (string) $min_cfvo->get_value());
                $obj_writer->end_element();
            }
            $max_cfvo = $data_bar->get_maximum_conditional_format_value_object();
            if ($max_cfvo) {
                $obj_writer->start_element('cfvo');
                $obj_writer->write_attribute('type', $max_cfvo->get_type());
                self::write_attribute_if($obj_writer, $max_cfvo->get_value() !== null, 'val', (string) $max_cfvo->get_value());
                $obj_writer->end_element();
            }
            if ($data_bar->get_color()) {
                $obj_writer->start_element('color');
                $obj_writer->write_attribute('rgb', $data_bar->get_color());
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            // end dataBar
            if ($data_bar->get_conditional_formatting_rule_ext()) {
                $obj_writer->start_element('extLst');
                $extension = $data_bar->get_conditional_formatting_rule_ext();
                $obj_writer->start_element('ext');
                $obj_writer->write_attribute('uri', '{B025F937-C7B1-47D3-B67F-A62EFF666E3E}');
                $obj_writer->start_element_ns('x14', 'id', null);
                $obj_writer->text($extension->get_id());
                $obj_writer->end_element();
                $obj_writer->end_element();
                $obj_writer->end_element();
                //end extLst
            }
        }
    }
    private static function write_color_scale_elements(Xml_Writer $obj_writer, ?Conditional_Color_Scale $color_scale): void
    {
        if ($color_scale) {
            $obj_writer->start_element('colorScale');
            $min_cfvo = $color_scale->get_minimum_conditional_format_value_object();
            $min_argb = $color_scale->get_minimum_color()?->get_argb();
            $use_min = $min_cfvo !== null || $min_argb !== null;
            if ($use_min) {
                $obj_writer->start_element('cfvo');
                $type = 'min';
                $value = null;
                if ($min_cfvo !== null) {
                    $typex = $min_cfvo->get_type();
                    if ($typex === 'formula') {
                        $value = $min_cfvo->get_cell_formula();
                        if ($value !== null) {
                            $type = $typex;
                        }
                    } else {
                        $type = $typex;
                        $defaults = ['number' => '0', 'percent' => '0', 'percentile' => '10'];
                        $value = $min_cfvo->get_value() ?? $defaults[$type] ?? null;
                    }
                }
                $obj_writer->write_attribute('type', $type);
                self::write_attribute_if($obj_writer, $value !== null, 'val', (string) $value);
                $obj_writer->end_element();
            }
            $mid_cfvo = $color_scale->get_midpoint_conditional_format_value_object();
            $mid_argb = $color_scale->get_midpoint_color()?->get_argb();
            $use_mid = $mid_cfvo !== null || $mid_argb !== null;
            if ($use_mid) {
                $obj_writer->start_element('cfvo');
                $type = 'percentile';
                $value = '50';
                if ($mid_cfvo !== null) {
                    $type = $mid_cfvo->get_type();
                    if ($type === 'formula') {
                        $value = $mid_cfvo->get_cell_formula();
                        if ($value === null) {
                            $type = 'percentile';
                            $value = '50';
                        }
                    } else {
                        $defaults = ['number' => '0', 'percent' => '50', 'percentile' => '50'];
                        $value = $mid_cfvo->get_value() ?? $defaults[$type] ?? null;
                    }
                }
                $obj_writer->write_attribute('type', $type);
                self::write_attribute_if($obj_writer, $value !== null, 'val', (string) $value);
                $obj_writer->end_element();
            }
            $max_cfvo = $color_scale->get_maximum_conditional_format_value_object();
            $max_argb = $color_scale->get_maximum_color()?->get_argb();
            $use_max = $max_cfvo !== null || $max_argb !== null;
            if ($use_max) {
                $obj_writer->start_element('cfvo');
                $type = 'max';
                $value = null;
                if ($max_cfvo !== null) {
                    $typex = $max_cfvo->get_type();
                    if ($typex === 'formula') {
                        $value = $max_cfvo->get_cell_formula();
                        if ($value !== null) {
                            $type = $typex;
                        }
                    } else {
                        $type = $typex;
                        $defaults = ['number' => '0', 'percent' => '100', 'percentile' => '90'];
                        $value = $max_cfvo->get_value() ?? $defaults[$type] ?? null;
                    }
                }
                $obj_writer->write_attribute('type', $type);
                self::write_attribute_if($obj_writer, $value !== null, 'val', (string) $value);
                $obj_writer->end_element();
            }
            if ($use_min) {
                $obj_writer->start_element('color');
                self::write_attribute_if($obj_writer, $min_argb !== null, 'rgb', "{$min_argb}");
                $obj_writer->end_element();
            }
            if ($use_mid) {
                $obj_writer->start_element('color');
                self::write_attribute_if($obj_writer, $mid_argb !== null, 'rgb', "{$mid_argb}");
                $obj_writer->end_element();
            }
            if ($use_max) {
                $obj_writer->start_element('color');
                self::write_attribute_if($obj_writer, $max_argb !== null, 'rgb', "{$max_argb}");
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            // end colorScale
        }
    }
    private function write_icon_set_elements(Xml_Writer $obj_writer, ?Conditional_Icon_Set $icon_set): void
    {
        if ($icon_set === null) {
            return;
        }
        $obj_writer->start_element('iconSet');
        if ($icon_set->get_icon_set_type() !== null) {
            $obj_writer->write_attribute('iconSet', $icon_set->get_icon_set_type()->value);
        }
        foreach (['reverse' => $icon_set->get_reverse(), 'showValue' => $icon_set->get_show_value(), 'custom' => $icon_set->get_custom()] as $attr => $value) {
            self::write_attribute_if($obj_writer, $value !== null, $attr, $value ? '1' : '0');
        }
        foreach ($icon_set->get_cfvos() as $cfvo) {
            $obj_writer->start_element('cfvo');
            $obj_writer->write_attribute('type', $cfvo->get_type());
            self::write_attribute_if($obj_writer, $cfvo->get_value() !== null, 'val', (string) $cfvo->get_value());
            self::write_attribute_if($obj_writer, $cfvo->get_greater_than_or_equal() !== null, 'gte', $cfvo->get_greater_than_or_equal() ? '1' : '0');
            $obj_writer->end_element();
            // end cfvo
        }
        $obj_writer->end_element();
        // end iconSet
    }
    /**
     * Write ConditionalFormatting.
     */
    private function write_conditional_formatting(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // Conditional id
        $id = 0;
        foreach ($worksheet->get_conditional_styles_collection() as $conditional_styles) {
            foreach ($conditional_styles as $conditional) {
                $id = max($id, $conditional->get_priority());
            }
        }
        // Loop through styles in the current worksheet
        foreach ($worksheet->get_conditional_styles_collection() as $cell_coordinate => $conditional_styles) {
            $obj_writer->start_element('conditionalFormatting');
            // N.B. In Excel UI, intersection is space and union is comma.
            // But in Xml, intersection is comma and union is space.
            // Anyhow, I don't think Excel handles intersection correctly when reading.
            $out_coordinate = Coordinate::resolve_union_and_intersection(str_replace('$', '', $cell_coordinate), ' ');
            $obj_writer->write_attribute('sqref', $out_coordinate);
            foreach ($conditional_styles as $conditional) {
                // WHY was this again?
                // if ($this->getParentWriter()->getStylesConditionalHashTable()->getIndexForHashCode($conditional->getHashCode()) == '') {
                //    continue;
                // }
                // cfRule
                $obj_writer->start_element('cfRule');
                $obj_writer->write_attribute('type', $conditional->get_condition_type());
                self::write_attribute_if($obj_writer, $conditional->get_condition_type() !== Conditional::CONDITION_COLORSCALE && $conditional->get_condition_type() !== Conditional::CONDITION_DATABAR && $conditional->get_condition_type() !== Conditional::CONDITION_ICONSET && $conditional->get_no_format_set() === false, 'dxfId', (string) $this->get_parent_writer()->get_styles_conditional_hash_table()->get_index_for_hash_code($conditional->get_hash_code()));
                $priority = $conditional->get_priority() ?: ++$id;
                $obj_writer->write_attribute('priority', (string) $priority);
                self::write_attributeif($obj_writer, ($conditional->get_condition_type() === Conditional::CONDITION_CELLIS || $conditional->get_condition_type() === Conditional::CONDITION_CONTAINSTEXT || $conditional->get_condition_type() === Conditional::CONDITION_NOTCONTAINSTEXT || $conditional->get_condition_type() === Conditional::CONDITION_BEGINSWITH || $conditional->get_condition_type() === Conditional::CONDITION_ENDSWITH) && $conditional->get_operator_type() !== Conditional::OPERATOR_NONE, 'operator', $conditional->get_operator_type());
                self::write_attribute_if($obj_writer, $conditional->get_stop_if_true(), 'stopIfTrue', '1');
                $cell_range = Coordinate::split_range(str_replace('$', '', strtoupper((string) $cell_coordinate)));
                [$top_left_cell] = $cell_range[0];
                if ($conditional->get_condition_type() === Conditional::CONDITION_CONTAINSTEXT || $conditional->get_condition_type() === Conditional::CONDITION_NOTCONTAINSTEXT || $conditional->get_condition_type() === Conditional::CONDITION_BEGINSWITH || $conditional->get_condition_type() === Conditional::CONDITION_ENDSWITH) {
                    self::write_text_cond_elements($obj_writer, $conditional, $top_left_cell);
                } elseif ($conditional->get_condition_type() === Conditional::CONDITION_TIMEPERIOD) {
                    self::write_time_period_cond_elements($obj_writer, $conditional, $top_left_cell);
                } elseif ($conditional->get_condition_type() === Conditional::CONDITION_COLORSCALE) {
                    self::write_color_scale_elements($obj_writer, $conditional->get_color_scale());
                } elseif ($conditional->get_condition_type() === Conditional::CONDITION_ICONSET) {
                    self::write_icon_set_elements($obj_writer, $conditional->get_icon_set());
                } else {
                    self::write_other_cond_elements($obj_writer, $conditional, $top_left_cell);
                }
                //<dataBar>
                self::write_data_bar_elements($obj_writer, $conditional->get_data_bar());
                $obj_writer->end_element();
                //end cfRule
            }
            $obj_writer->end_element();
            //end conditionalFormatting
        }
    }
    /**
     * Write DataValidations.
     */
    private function write_data_validations(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // Datavalidation collection
        $data_validation_collection = $worksheet->get_data_validation_collection();
        // Write data validations?
        if (!empty($data_validation_collection)) {
            $obj_writer->start_element('dataValidations');
            $obj_writer->write_attribute('count', (string) count($data_validation_collection));
            foreach ($data_validation_collection as $coordinate => $dv) {
                $obj_writer->start_element('dataValidation');
                if ($dv->get_type() != '') {
                    $obj_writer->write_attribute('type', $dv->get_type());
                }
                if ($dv->get_error_style() != '') {
                    $obj_writer->write_attribute('errorStyle', $dv->get_error_style());
                }
                if ($dv->get_operator() != '') {
                    $obj_writer->write_attribute('operator', $dv->get_operator());
                }
                $obj_writer->write_attribute('allowBlank', $dv->get_allow_blank() ? '1' : '0');
                $obj_writer->write_attribute('showDropDown', !$dv->get_show_drop_down() ? '1' : '0');
                $obj_writer->write_attribute('showInputMessage', $dv->get_show_input_message() ? '1' : '0');
                $obj_writer->write_attribute('showErrorMessage', $dv->get_show_error_message() ? '1' : '0');
                if ($dv->get_error_title() !== '') {
                    $obj_writer->write_attribute('errorTitle', $dv->get_error_title());
                }
                if ($dv->get_error() !== '') {
                    $obj_writer->write_attribute('error', $dv->get_error());
                }
                if ($dv->get_prompt_title() !== '') {
                    $obj_writer->write_attribute('promptTitle', $dv->get_prompt_title());
                }
                if ($dv->get_prompt() !== '') {
                    $obj_writer->write_attribute('prompt', $dv->get_prompt());
                }
                $obj_writer->write_attribute('sqref', $dv->get_sqref() ?? $coordinate);
                if ($dv->get_formula1() !== '') {
                    $obj_writer->write_element('formula1', Function_Prefix::add_function_prefix($dv->get_formula1()));
                }
                if ($dv->get_formula2() !== '') {
                    $obj_writer->write_element('formula2', Function_Prefix::add_function_prefix($dv->get_formula2()));
                }
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write Hyperlinks.
     */
    private function write_hyperlinks(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // Hyperlink collection
        $hyperlink_collection = $worksheet->get_hyperlink_collection();
        // Relation ID
        $relation_id = 1;
        // Write hyperlinks?
        if (!empty($hyperlink_collection)) {
            $obj_writer->start_element('hyperlinks');
            foreach ($hyperlink_collection as $coordinate => $hyperlink) {
                $obj_writer->start_element('hyperlink');
                $obj_writer->write_attribute('ref', $coordinate);
                if (!$hyperlink->is_internal()) {
                    $obj_writer->write_attribute('r:id', 'rId_hyperlink_' . $relation_id);
                    ++$relation_id;
                } else {
                    $obj_writer->write_attribute('location', str_replace('sheet://', '', $hyperlink->get_url()));
                }
                if ($hyperlink->get_tooltip() !== '') {
                    $obj_writer->write_attribute('tooltip', $hyperlink->get_tooltip());
                }
                if ($hyperlink->get_display() !== '') {
                    $obj_writer->write_attribute('display', $hyperlink->get_display());
                } elseif ($hyperlink->get_tooltip() !== '') {
                    // Probably shouldn't do this,
                    // but avoids a breaking change.
                    // This was introduced in PR 904 in 2019.
                    $obj_writer->write_attribute('display', $hyperlink->get_tooltip());
                }
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write ProtectedRanges.
     */
    private function write_protected_ranges(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        if (count($worksheet->get_protected_cell_ranges()) > 0) {
            // protectedRanges
            $obj_writer->start_element('protectedRanges');
            // Loop protectedRanges
            foreach ($worksheet->get_protected_cell_ranges() as $protected_cell => $protected_range) {
                // protectedRange
                $obj_writer->start_element('protectedRange');
                $obj_writer->write_attribute('name', $protected_range->get_name());
                $obj_writer->write_attribute('sqref', $protected_cell);
                $password_hash = $protected_range->get_password();
                self::write_attribute_if($obj_writer, $password_hash !== '', 'password', $password_hash);
                $security_descriptor = $protected_range->get_security_descriptor();
                self::write_attribute_if($obj_writer, $security_descriptor !== '', 'securityDescriptor', $security_descriptor);
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write MergeCells.
     */
    private function write_merge_cells(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        if (count($worksheet->get_merge_cells()) > 0) {
            // mergeCells
            $obj_writer->start_element('mergeCells');
            // Loop mergeCells
            foreach ($worksheet->get_merge_cells() as $merge_cell) {
                // mergeCell
                $obj_writer->start_element('mergeCell');
                $obj_writer->write_attribute('ref', $merge_cell);
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write PrintOptions.
     */
    private function write_print_options(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // printOptions
        $obj_writer->start_element('printOptions');
        $obj_writer->write_attribute('gridLines', $worksheet->get_print_gridlines() ? 'true' : 'false');
        $obj_writer->write_attribute('gridLinesSet', 'true');
        if ($worksheet->get_page_setup()->get_horizontal_centered()) {
            $obj_writer->write_attribute('horizontalCentered', 'true');
        }
        if ($worksheet->get_page_setup()->get_vertical_centered()) {
            $obj_writer->write_attribute('verticalCentered', 'true');
        }
        $obj_writer->end_element();
    }
    /**
     * Write PageMargins.
     */
    private function write_page_margins(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // pageMargins
        $obj_writer->start_element('pageMargins');
        $obj_writer->write_attribute('left', String_Helper::format_number($worksheet->get_page_margins()->get_left()));
        $obj_writer->write_attribute('right', String_Helper::format_number($worksheet->get_page_margins()->get_right()));
        $obj_writer->write_attribute('top', String_Helper::format_number($worksheet->get_page_margins()->get_top()));
        $obj_writer->write_attribute('bottom', String_Helper::format_number($worksheet->get_page_margins()->get_bottom()));
        $obj_writer->write_attribute('header', String_Helper::format_number($worksheet->get_page_margins()->get_header()));
        $obj_writer->write_attribute('footer', String_Helper::format_number($worksheet->get_page_margins()->get_footer()));
        $obj_writer->end_element();
    }
    /**
     * Write AutoFilter.
     */
    private function write_auto_filter(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        Auto_Filter::write_auto_filter($obj_writer, $worksheet);
    }
    /**
     * Write Table.
     */
    private function write_table(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        $table_count = $worksheet->get_table_collection()->count();
        if ($table_count === 0) {
            return;
        }
        $obj_writer->start_element('tableParts');
        $obj_writer->write_attribute('count', (string) $table_count);
        for ($t = 1; $t <= $table_count; ++$t) {
            $obj_writer->start_element('tablePart');
            $obj_writer->write_attribute('r:id', 'rId_table_' . $t);
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
    }
    /**
     * Write Background Image.
     */
    private function write_background_image(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        if ($worksheet->get_background_image() !== '') {
            $obj_writer->start_element('picture');
            $obj_writer->write_attribute('r:id', 'rIdBg');
            $obj_writer->end_element();
        }
    }
    /**
     * Write PageSetup.
     */
    private function write_page_setup(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // pageSetup
        $obj_writer->start_element('pageSetup');
        $obj_writer->write_attribute('paperSize', (string) $worksheet->get_page_setup()->get_paper_size());
        $obj_writer->write_attribute('orientation', $worksheet->get_page_setup()->get_orientation());
        if ($worksheet->get_page_setup()->get_scale() !== null) {
            $obj_writer->write_attribute('scale', (string) $worksheet->get_page_setup()->get_scale());
        }
        if ($worksheet->get_page_setup()->get_fit_to_height() !== null) {
            $obj_writer->write_attribute('fitToHeight', (string) $worksheet->get_page_setup()->get_fit_to_height());
        } else {
            $obj_writer->write_attribute('fitToHeight', '0');
        }
        if ($worksheet->get_page_setup()->get_fit_to_width() !== null) {
            $obj_writer->write_attribute('fitToWidth', (string) $worksheet->get_page_setup()->get_fit_to_width());
        } else {
            $obj_writer->write_attribute('fitToWidth', '0');
        }
        if (!empty($worksheet->get_page_setup()->get_first_page_number())) {
            $obj_writer->write_attribute('firstPageNumber', (string) $worksheet->get_page_setup()->get_first_page_number());
            $obj_writer->write_attribute('useFirstPageNumber', '1');
        }
        $obj_writer->write_attribute('pageOrder', $worksheet->get_page_setup()->get_page_order());
        /** @var string[][][] */
        $get_unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        if (isset($get_unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['pageSetupRelId'])) {
            $obj_writer->write_attribute('r:id', $get_unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['pageSetupRelId']);
        }
        $obj_writer->end_element();
    }
    /**
     * Write Header / Footer.
     */
    private function write_header_footer(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // headerFooter
        $header_footer = $worksheet->get_header_footer();
        $odd_header = $header_footer->get_odd_header();
        $odd_footer = $header_footer->get_odd_footer();
        $even_header = $header_footer->get_even_header();
        $even_footer = $header_footer->get_even_footer();
        $first_header = $header_footer->get_first_header();
        $first_footer = $header_footer->get_first_footer();
        if ("{$odd_header}{$odd_footer}{$even_header}{$even_footer}{$first_header}{$first_footer}" === '') {
            return;
        }
        $obj_writer->start_element('headerFooter');
        $obj_writer->write_attribute('differentOddEven', $worksheet->get_header_footer()->get_different_odd_even() ? 'true' : 'false');
        $obj_writer->write_attribute('differentFirst', $worksheet->get_header_footer()->get_different_first() ? 'true' : 'false');
        $obj_writer->write_attribute('scaleWithDoc', $worksheet->get_header_footer()->get_scale_with_document() ? 'true' : 'false');
        $obj_writer->write_attribute('alignWithMargins', $worksheet->get_header_footer()->get_align_with_margins() ? 'true' : 'false');
        self::write_element_if($obj_writer, $odd_header !== '', 'oddHeader', $odd_header);
        self::write_element_if($obj_writer, $odd_footer !== '', 'oddFooter', $odd_footer);
        self::write_element_if($obj_writer, $even_header !== '', 'evenHeader', $even_header);
        self::write_element_if($obj_writer, $even_footer !== '', 'evenFooter', $even_footer);
        self::write_element_if($obj_writer, $first_header !== '', 'firstHeader', $first_header);
        self::write_element_if($obj_writer, $first_footer !== '', 'firstFooter', $first_footer);
        $obj_writer->end_element();
        // headerFooter
    }
    /**
     * Write Breaks.
     */
    private function write_breaks(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // Get row and column breaks
        $a_row_breaks = [];
        $a_column_breaks = [];
        foreach ($worksheet->get_row_breaks() as $cell => $break) {
            $a_row_breaks[$cell] = $break;
        }
        foreach ($worksheet->get_column_breaks() as $cell => $break) {
            $a_column_breaks[$cell] = $break;
        }
        // rowBreaks
        if (!empty($a_row_breaks)) {
            $obj_writer->start_element('rowBreaks');
            $obj_writer->write_attribute('count', (string) count($a_row_breaks));
            $obj_writer->write_attribute('manualBreakCount', (string) count($a_row_breaks));
            foreach ($a_row_breaks as $cell => $break) {
                $coords = Coordinate::coordinate_from_string($cell);
                $obj_writer->start_element('brk');
                $obj_writer->write_attribute('id', $coords[1]);
                $obj_writer->write_attribute('man', '1');
                $row_break_max = $break->get_max_col_or_row();
                if ($row_break_max >= 0) {
                    $obj_writer->write_attribute('max', "{$row_break_max}");
                } elseif ($worksheet->get_page_setup()->get_print_area() !== '') {
                    $max_col = Coordinate::column_index_from_string($worksheet->get_highest_column());
                    $obj_writer->write_attribute('max', "{$max_col}");
                }
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
        // Second, write column breaks
        if (!empty($a_column_breaks)) {
            $obj_writer->start_element('colBreaks');
            $obj_writer->write_attribute('count', (string) count($a_column_breaks));
            $obj_writer->write_attribute('manualBreakCount', (string) count($a_column_breaks));
            foreach ($a_column_breaks as $cell => $break) {
                $coords = Coordinate::indexes_from_string($cell);
                $obj_writer->start_element('brk');
                $obj_writer->write_attribute('id', (string) ((int) $coords[0] - 1));
                $obj_writer->write_attribute('man', '1');
                $col_break_max = $break->get_max_col_or_row();
                if ($col_break_max >= 0) {
                    $obj_writer->write_attribute('max', "{$col_break_max}");
                } elseif ($worksheet->get_page_setup()->get_print_area() !== '') {
                    $max_row = $worksheet->get_highest_row();
                    $obj_writer->write_attribute('max', "{$max_row}");
                }
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write SheetData.
     *
     * @param string[] $stringTable String table
     */
    private function write_sheet_data(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet, array $string_table): void
    {
        // Flipped stringtable, for faster index searching
        $a_flipped_string_table = $this->get_parent_writer()->get_writer_partstringtable()->flip_string_table($string_table);
        // sheetData
        $obj_writer->start_element('sheetData');
        // Get column count
        $col_count = Coordinate::column_index_from_string($worksheet->get_highest_column());
        // Highest row number
        $highest_row = $worksheet->get_highest_row();
        // Loop through cells building a comma-separated list of the columns in each row
        // This is a trade-off between the memory usage that is required for a full array of columns,
        //      and execution speed
        /** @var array<int, string> $cellsByRow */
        $cells_by_row = [];
        foreach ($worksheet->get_coordinates() as $coordinate) {
            [$column, $row] = Coordinate::coordinate_from_string($coordinate);
            if (!isset($cells_by_row[$row])) {
                $p_cell = $worksheet->get_cell("{$column}{$row}");
                $xfi = $p_cell->get_xf_index();
                $cell_value = $p_cell->get_value();
                $write_value = $cell_value !== '' && $cell_value !== null;
                if (!empty($xfi) || $write_value) {
                    $cells_by_row[$row] = "{$column},";
                }
            } else {
                $cells_by_row[$row] .= "{$column},";
            }
        }
        $custom_height_needed = false;
        if ($worksheet->get_default_row_dimension()->get_row_height() >= 0) {
            foreach ($worksheet->get_row_dimensions() as $row_dimension) {
                if ($row_dimension->get_custom_format()) {
                    $custom_height_needed = true;
                    break;
                }
            }
        }
        $current_row = 0;
        $empty_dimension = new Row_Dimension();
        while ($current_row++ < $highest_row) {
            $is_row_set = isset($cells_by_row[$current_row]);
            if ($is_row_set || $worksheet->row_dimension_exists($current_row)) {
                // Get row dimension
                $row_dimension = $worksheet->row_dimension_exists($current_row) ? $worksheet->get_row_dimension($current_row) : $empty_dimension;
                // Write current row?
                $write_current_row = $is_row_set || $row_dimension->get_row_height() >= 0 || $row_dimension->get_visible() === false || $row_dimension->get_collapsed() === true || $row_dimension->get_outline_level() > 0 || $row_dimension->get_xf_index() !== null;
                if ($write_current_row) {
                    // Start a new row
                    $custom_format_written = false;
                    $obj_writer->start_element('row');
                    $obj_writer->write_attribute('r', "{$current_row}");
                    $obj_writer->write_attribute('spans', '1:' . $col_count);
                    // Row dimensions
                    if ($row_dimension->get_row_height() >= 0) {
                        $obj_writer->write_attribute('customHeight', '1');
                        $obj_writer->write_attribute('ht', String_Helper::format_number($row_dimension->get_row_height()));
                    } elseif ($row_dimension->get_custom_format()) {
                        $obj_writer->write_attribute('customFormat', '1');
                        $custom_format_written = true;
                        $obj_writer->write_attribute('ht', String_Helper::format_number($row_dimension->get_row_height()));
                    } elseif ($custom_height_needed) {
                        $obj_writer->write_attribute('customHeight', '1');
                    }
                    // Row visibility
                    if (!$row_dimension->get_visible() === true) {
                        $obj_writer->write_attribute('hidden', 'true');
                    }
                    // Collapsed
                    if ($row_dimension->get_collapsed() === true) {
                        $obj_writer->write_attribute('collapsed', 'true');
                    }
                    // Outline level
                    if ($row_dimension->get_outline_level() > 0) {
                        $obj_writer->write_attribute('outlineLevel', (string) $row_dimension->get_outline_level());
                    }
                    // Style
                    if ($row_dimension->get_xf_index() !== null) {
                        $obj_writer->write_attribute('s', (string) $row_dimension->get_xf_index());
                        if (!$custom_format_written) {
                            $obj_writer->write_attribute('customFormat', '1');
                        }
                    }
                    // Write cells
                    if (isset($cells_by_row[$current_row])) {
                        // We have a comma-separated list of column names (with a trailing entry); split to an array
                        $columns_in_row = explode(',', $cells_by_row[$current_row]);
                        array_pop($columns_in_row);
                        foreach ($columns_in_row as $column) {
                            // Write cell
                            $coord = "{$column}{$current_row}";
                            if ($worksheet->get_cell($coord)->get_ignored_errors()->get_number_stored_as_text()) {
                                $this->number_stored_as_text .= " {$coord}";
                            }
                            if ($worksheet->get_cell($coord)->get_ignored_errors()->get_formula()) {
                                $this->formula .= " {$coord}";
                            }
                            if ($worksheet->get_cell($coord)->get_ignored_errors()->get_formula_range()) {
                                $this->formula_range .= " {$coord}";
                            }
                            if ($worksheet->get_cell($coord)->get_ignored_errors()->get_two_digit_text_year()) {
                                $this->two_digit_text_year .= " {$coord}";
                            }
                            if ($worksheet->get_cell($coord)->get_ignored_errors()->get_eval_error()) {
                                $this->eval_error .= " {$coord}";
                            }
                            $this->write_cell($obj_writer, $worksheet, $coord, $a_flipped_string_table);
                        }
                    }
                    // End row
                    $obj_writer->end_element();
                }
            }
        }
        $obj_writer->end_element();
    }
    private function write_cell_inline_str(Xml_Writer $obj_writer, string $mapped_type, Rich_Text|string $cell_value, ?Font $font): void
    {
        $obj_writer->write_attribute('t', $mapped_type);
        if (!$cell_value instanceof Rich_Text) {
            $obj_writer->start_element('is');
            $obj_writer->start_element('t');
            $text_to_write = String_Helper::control_character_php2ooxml($cell_value);
            if ($text_to_write !== trim($text_to_write)) {
                $obj_writer->write_attribute('xml:space', 'preserve');
            }
            $obj_writer->write_raw_data($text_to_write);
            $obj_writer->end_element();
            // t
            $obj_writer->end_element();
            // is
        } else {
            $obj_writer->start_element('is');
            $this->get_parent_writer()->get_writer_partstringtable()->write_rich_text($obj_writer, $cell_value, null, $font);
            $obj_writer->end_element();
        }
    }
    /**
     * @param string[] $flippedStringTable
     */
    private function write_cell_string(Xml_Writer $obj_writer, string $mapped_type, Rich_Text|string $cell_value, array $flipped_string_table): void
    {
        $obj_writer->write_attribute('t', $mapped_type);
        if (!$cell_value instanceof Rich_Text) {
            self::write_element_if($obj_writer, isset($flipped_string_table[$cell_value]), 'v', $flipped_string_table[$cell_value] ?? '');
        } else {
            $obj_writer->write_element('v', $flipped_string_table[$cell_value->get_hash_code()]);
        }
    }
    private function write_cell_numeric(Xml_Writer $obj_writer, float|int $cell_value): void
    {
        $result = String_Helper::convert_to_string($cell_value);
        if (is_float($cell_value) && !str_contains($result, '.')) {
            $result .= '.0';
        }
        $obj_writer->write_element('v', $result);
    }
    private function write_cell_boolean(Xml_Writer $obj_writer, string $mapped_type, bool $cell_value): void
    {
        $obj_writer->write_attribute('t', $mapped_type);
        $obj_writer->write_element('v', $cell_value ? '1' : '0');
    }
    private function write_cell_error(Xml_Writer $obj_writer, string $mapped_type, string $cell_value, string $formulaerr = '#NULL!'): void
    {
        $obj_writer->write_attribute('t', $mapped_type);
        $cell_is_formula = str_starts_with($cell_value, '=');
        self::write_element_if($obj_writer, $cell_is_formula, 'f', Function_Prefix::add_function_prefix_strip_equals($cell_value));
        $obj_writer->write_element('v', $cell_is_formula ? $formulaerr : $cell_value);
    }
    private function write_cell_drawing(Xml_Writer $obj_writer, int $index): void
    {
        $obj_writer->write_attribute('t', 'e');
        $obj_writer->write_attribute('vm', (string) $index);
        $obj_writer->write_element('v', '#VALUE!');
    }
    private function write_cell_formula(Xml_Writer $obj_writer, string $cell_value, Cell $cell): void
    {
        $attributes = $cell->get_formula_attributes() ?? [];
        $coordinate = $cell->get_coordinate();
        $calculated_value = $this->get_parent_writer()->get_pre_calculate_formulas() ? $cell->get_calculated_value() : $cell_value;
        if ($calculated_value === Excel_Error::SPILL()) {
            $obj_writer->write_attribute('t', 'e');
            //$objWriter->writeAttribute('cm', '1'); // already added
            $obj_writer->write_attribute('vm', '1');
            $obj_writer->start_element('f');
            $obj_writer->write_attribute('t', 'array');
            $obj_writer->write_attribute('aca', '1');
            $obj_writer->write_attribute('ref', $coordinate);
            $obj_writer->write_attribute('ca', '1');
            $obj_writer->text(Function_Prefix::add_function_prefix_strip_equals($cell_value));
            $obj_writer->end_element();
            // f
            $obj_writer->write_element('v', Excel_Error::VALUE());
            // note #VALUE! in xml even though error is #SPILL!
            return;
        }
        $calculated_value_string = $this->get_parent_writer()->get_pre_calculate_formulas() ? $cell->get_calculated_value_string() : $cell_value;
        $result = $calculated_value;
        while (is_array($result)) {
            $result = array_shift($result);
        }
        if (is_string($result)) {
            if (Error_Value::is_error($result)) {
                $this->write_cell_error($obj_writer, 'e', $cell_value, $result);
                return;
            }
            $obj_writer->write_attribute('t', 'str');
            $result = $calculated_value_string = String_Helper::control_character_php2ooxml($result);
            if (is_string($calculated_value)) {
                $calculated_value = $calculated_value_string;
            }
        } elseif (is_bool($result)) {
            $obj_writer->write_attribute('t', 'b');
            if (is_bool($calculated_value)) {
                $calculated_value = $result;
            }
            $result = (int) $result;
            $calculated_value_string = (string) $result;
        }
        if (isset($attributes['ref'])) {
            $ref = $this->parse_ref($coordinate, $attributes['ref']);
            if ($ref === "{$coordinate}:{$coordinate}") {
                $ref = $coordinate;
            }
        } else {
            $ref = $coordinate;
        }
        if (is_array($calculated_value)) {
            $attributes['t'] = 'array';
        }
        if (($attributes['t'] ?? null) === 'array') {
            $obj_writer->start_element('f');
            $obj_writer->write_attribute('t', 'array');
            $obj_writer->write_attribute('ref', $ref);
            $obj_writer->write_attribute('aca', '1');
            $obj_writer->write_attribute('ca', '1');
            $obj_writer->text(Function_Prefix::add_function_prefix_strip_equals($cell_value));
            $obj_writer->end_element();
            if (is_scalar($result) && $this->get_parent_writer()->get_office2003compatibility() === false && $this->get_parent_writer()->get_pre_calculate_formulas()) {
                $obj_writer->write_element('v', (string) $result);
            }
        } else {
            $obj_writer->write_element('f', Function_Prefix::add_function_prefix_strip_equals($cell_value));
            self::write_element_if($obj_writer, $this->get_parent_writer()->get_office2003compatibility() === false && $this->get_parent_writer()->get_pre_calculate_formulas() && $calculated_value !== null, 'v', !is_array($calculated_value) && !str_starts_with($calculated_value_string, '#') ? String_Helper::format_number($calculated_value_string) : '0');
        }
    }
    private function parse_ref(string $coordinate, string $ref): string
    {
        if (!Preg::is_match('/^([A-Z]{1,3})([0-9]{1,7})(:([A-Z]{1,3})([0-9]{1,7}))?$/', $ref, $matches)) {
            return $ref;
        }
        if (!isset($matches[3])) {
            // single cell, not range
            return $coordinate;
        }
        $min_row = (int) $matches[2];
        $max_row = (int) $matches[5];
        $rows = $max_row - $min_row + 1;
        $min_col = Coordinate::column_index_from_string($matches[1]);
        $max_col = Coordinate::column_index_from_string($matches[4]);
        $cols = $max_col - $min_col + 1;
        $first_cell_array = Coordinate::indexes_from_string($coordinate);
        $last_row = $first_cell_array[1] + $rows - 1;
        $last_column = $first_cell_array[0] + $cols - 1;
        $last_column_string = Coordinate::string_from_column_index($last_column);
        return "{$coordinate}:{$last_column_string}{$last_row}";
    }
    /**
     * Write Cell.
     *
     * @param string $cellAddress Cell Address
     * @param string[] $flippedStringTable String table (flipped), for faster index searching
     */
    private function write_cell(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet, string $cell_address, array $flipped_string_table): void
    {
        // Cell
        $p_cell = $worksheet->get_cell($cell_address);
        $xfi = $p_cell->get_xf_index();
        $cell_value = $p_cell->get_value();
        $cell_value_string = $p_cell->get_value_string();
        $write_value = $cell_value !== '' && $cell_value !== null;
        if (empty($xfi) && !$write_value) {
            return;
        }
        $style_array = $this->get_parent_writer()->get_spreadsheet()->get_cell_xf_collection();
        $font = $style_array[$xfi] ?? null;
        if ($font !== null) {
            $font = $font->get_font();
        }
        $obj_writer->start_element('c');
        $obj_writer->write_attribute('r', $cell_address);
        $mapped_type = $p_cell->get_data_type();
        if ($mapped_type === Data_Type::TYPE_FORMULA) {
            if ($this->use_dynamic_arrays) {
                if (preg_match(Phpspreadsheet_Worksheet::FUNCTION_LIKE_GROUPBY, $cell_value_string) === 1) {
                    $temp_calc = [];
                } else {
                    $temp_calc = $p_cell->get_calculated_value();
                }
                if (is_array($temp_calc)) {
                    $obj_writer->write_attribute('cm', '1');
                }
            }
        }
        // Sheet styles
        if ($xfi) {
            $obj_writer->write_attribute('s', "{$xfi}");
        } elseif ($this->explicit_style0) {
            $obj_writer->write_attribute('s', '0');
        }
        // If cell value is supplied, write cell value
        if ($write_value) {
            // Write data depending on its type
            switch (strtolower($mapped_type)) {
                case 'inlinestr':
                    // Inline string
                    /** @var RichText|string */
                    $rich_text = $cell_value;
                    $this->write_cell_inline_str($obj_writer, $mapped_type, $rich_text, $font);
                    break;
                case 's':
                    // String
                    $this->write_cell_string($obj_writer, $mapped_type, $cell_value instanceof Rich_Text ? $cell_value : $cell_value_string, $flipped_string_table);
                    break;
                case 'f':
                    // Formula
                    $this->write_cell_formula($obj_writer, $cell_value_string, $p_cell);
                    break;
                case 'n':
                    // Numeric
                    $cell_value_numeric = is_numeric($cell_value) ? $cell_value + 0 : 0;
                    $this->write_cell_numeric($obj_writer, $cell_value_numeric);
                    break;
                case 'b':
                    // Boolean
                    $this->write_cell_boolean($obj_writer, $mapped_type, (bool) $cell_value);
                    break;
                case 'drawingcell':
                    // DrawingInCell
                    if ($cell_value instanceof Base_Drawing) {
                        $index = $cell_value->get_index();
                        $this->write_cell_drawing($obj_writer, $index);
                    }
                    break;
                case 'e':
                    // Error
                    $this->write_cell_error($obj_writer, $mapped_type, $cell_value_string);
            }
        }
        $obj_writer->end_element();
        // c
    }
    /**
     * Write Drawings.
     *
     * @param bool $includeCharts Flag indicating if we should include drawing details for charts
     */
    private function write_drawings(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet, bool $include_charts = false): void
    {
        /** @var mixed[][][][] */
        $unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        $has_unparsed_drawing = isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingOriginalIds']);
        $chart_count = $include_charts ? $worksheet->get_chart_collection()->count() : 0;
        if ($chart_count == 0 && $worksheet->get_drawing_collection()->count() == 0 && !$has_unparsed_drawing) {
            return;
        }
        // If sheet contains drawings, add the relationships
        $obj_writer->start_element('drawing');
        $r_id = 'rId1';
        if (isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingOriginalIds'])) {
            $drawing_original_ids = $unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingOriginalIds'];
            // take first. In future can be overriten
            // (! synchronize with \PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels::writeWorksheetRelationships)
            $r_id = reset($drawing_original_ids);
        }
        /** @var string $rId */
        $obj_writer->write_attribute('r:id', $r_id);
        $obj_writer->end_element();
    }
    /**
     * Write LegacyDrawing.
     */
    private function write_legacy_drawing(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // If sheet contains comments, add the relationships
        /** @var mixed[][][][] */
        $unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        if (count($worksheet->get_comments()) > 0 || isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['legacyDrawing'])) {
            $obj_writer->start_element('legacyDrawing');
            $obj_writer->write_attribute('r:id', 'rId_comments_vml1');
            $obj_writer->end_element();
        }
    }
    /**
     * Write LegacyDrawingHF.
     */
    private function write_legacy_drawing_hf(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        // If sheet contains images, add the relationships
        if (count($worksheet->get_header_footer()->get_images()) > 0) {
            $obj_writer->start_element('legacyDrawingHF');
            $obj_writer->write_attribute('r:id', 'rId_headerfooter_vml1');
            $obj_writer->end_element();
        }
    }
    private function write_alternate_content(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        /** @var string[][][] */
        $unparsed_sheet = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data()['sheets'] ?? [];
        $unparsed_sheet = $unparsed_sheet[$worksheet->get_code_name()] ?? [];
        $unparsed_sheet = $unparsed_sheet['AlternateContents'] ?? [];
        foreach ($unparsed_sheet as $alternate_content) {
            $obj_writer->write_raw($alternate_content);
        }
    }
    /**
     * write <ExtLst>
     * only implementation conditionalFormattings.
     *
     * @url https://docs.microsoft.com/en-us/openspecs/office_standards/ms-xlsx/07d607af-5618-4ca2-b683-6a78dc0d9627
     */
    private function write_ext_lst(Xml_Writer $obj_writer, Phpspreadsheet_Worksheet $worksheet): void
    {
        $conditional_formatting_rule_ext_list = [];
        foreach ($worksheet->get_conditional_styles_collection() as $conditional_styles) {
            /** @var Conditional $conditional */
            foreach ($conditional_styles as $conditional) {
                $data_bar = $conditional->get_data_bar();
                if ($data_bar && $data_bar->get_conditional_formatting_rule_ext()) {
                    $conditional_formatting_rule_ext_list[] = $data_bar->get_conditional_formatting_rule_ext();
                }
            }
        }
        if (count($conditional_formatting_rule_ext_list) > 0) {
            $conditional_formatting_rule_ext_ns_prefix = 'x14';
            $obj_writer->start_element('extLst');
            $obj_writer->start_element('ext');
            $obj_writer->write_attribute('uri', '{78C0D931-6437-407d-A8EE-F0AAD7539E65}');
            $obj_writer->start_element_ns($conditional_formatting_rule_ext_ns_prefix, 'conditionalFormattings', null);
            foreach ($conditional_formatting_rule_ext_list as $extension) {
                self::write_ext_conditional_formatting_elements($obj_writer, $extension);
            }
            $obj_writer->end_element();
            //end conditionalFormattings
            $obj_writer->end_element();
            //end ext
            $obj_writer->end_element();
            //end extLst
        }
    }
}