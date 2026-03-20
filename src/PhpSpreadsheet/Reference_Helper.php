<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Reference_Helper
{
    /**    Constants                */
    /**    Regular Expressions      */
    private const SHEETNAME_PART = '((\w*|\'[^!]*\')!)';
    private const SHEETNAME_PART_WITH_SLASHES = '/' . self::SHEETNAME_PART . '/';
    public const REFHELPER_REGEXP_CELLREF = self::SHEETNAME_PART . '?(?<![:a-z1-9_\.\$])(\$?[a-z]{1,3}\$?\d+)(?=[^:!\d\'])';
    public const REFHELPER_REGEXP_CELLRANGE = self::SHEETNAME_PART . '?(\$?[a-z]{1,3}\$?\d+):(\$?[a-z]{1,3}\$?\d+)';
    public const REFHELPER_REGEXP_ROWRANGE = self::SHEETNAME_PART . '?(\$?\d+):(\$?\d+)';
    public const REFHELPER_REGEXP_COLRANGE = self::SHEETNAME_PART . '?(\$?[a-z]{1,3}):(\$?[a-z]{1,3})';
    /**
     * Instance of this class.
     */
    private static ?Reference_Helper $instance = null;
    private ?Cell_Reference_Helper $cell_reference_helper = null;
    /**
     * Get an instance of this class.
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Create a new ReferenceHelper.
     */
    protected function __construct()
    {
    }
    /**
     * Compare two column addresses
     * Intended for use as a Callback function for sorting column addresses by column.
     *
     * @param string $a First column to test (e.g. 'AA')
     * @param string $b Second column to test (e.g. 'Z')
     */
    public static function column_sort(string $a, string $b): int
    {
        return strcasecmp(strlen($a) . $a, strlen($b) . $b);
    }
    /**
     * Compare two column addresses
     * Intended for use as a Callback function for reverse sorting column addresses by column.
     *
     * @param string $a First column to test (e.g. 'AA')
     * @param string $b Second column to test (e.g. 'Z')
     */
    public static function column_reverse_sort(string $a, string $b): int
    {
        return -strcasecmp(strlen($a) . $a, strlen($b) . $b);
    }
    /**
     * Compare two cell addresses
     * Intended for use as a Callback function for sorting cell addresses by column and row.
     *
     * @param string $a First cell to test (e.g. 'AA1')
     * @param string $b Second cell to test (e.g. 'Z1')
     */
    public static function cell_sort(string $a, string $b): int
    {
        sscanf($a, '%[A-Z]%d', $ac, $ar);
        /** @var int $ar */
        /** @var string $ac */
        sscanf($b, '%[A-Z]%d', $bc, $br);
        /** @var int $br */
        /** @var string $bc */
        if ($ar === $br) {
            return strcasecmp(strlen($ac) . $ac, strlen($bc) . $bc);
        }
        return $ar < $br ? -1 : 1;
    }
    /**
     * Compare two cell addresses
     * Intended for use as a Callback function for sorting cell addresses by column and row.
     *
     * @param string $a First cell to test (e.g. 'AA1')
     * @param string $b Second cell to test (e.g. 'Z1')
     */
    public static function cell_reverse_sort(string $a, string $b): int
    {
        sscanf($a, '%[A-Z]%d', $ac, $ar);
        /** @var int $ar */
        /** @var string $ac */
        sscanf($b, '%[A-Z]%d', $bc, $br);
        /** @var int $br */
        /** @var string $bc */
        if ($ar === $br) {
            return -strcasecmp(strlen($ac) . $ac, strlen($bc) . $bc);
        }
        return $ar < $br ? 1 : -1;
    }
    /**
     * Update page breaks when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_page_breaks(Worksheet $worksheet, int $number_of_columns, int $number_of_rows): void
    {
        $a_breaks = $worksheet->get_breaks();
        $number_of_columns > 0 || $number_of_rows > 0 ? uksort($a_breaks, self::cell_reverse_sort(...)) : uksort($a_breaks, self::cell_sort(...));
        foreach ($a_breaks as $cell_address => $value) {
            /** @var CellReferenceHelper */
            $cell_reference_helper = $this->cell_reference_helper;
            if ($cell_reference_helper->cell_address_in_delete_range($cell_address) === true) {
                //    If we're deleting, then clear any defined breaks that are within the range
                //        of rows/columns that we're deleting
                $worksheet->set_break($cell_address, Worksheet::BREAK_NONE);
            } else {
                //    Otherwise update any affected breaks by inserting a new break at the appropriate point
                //        and removing the old affected break
                $new_reference = $this->update_cell_reference($cell_address);
                if ($cell_address !== $new_reference) {
                    $worksheet->set_break($new_reference, $value)->set_break($cell_address, Worksheet::BREAK_NONE);
                }
            }
        }
    }
    /**
     * Update cell comments when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     */
    protected function adjust_comments(Worksheet $worksheet): void
    {
        $a_comments = $worksheet->get_comments();
        $a_new_comments = [];
        // the new array of all comments
        foreach ($a_comments as $cell_address => &$value) {
            // Any comments inside a deleted range will be ignored
            /** @var CellReferenceHelper */
            $cell_reference_helper = $this->cell_reference_helper;
            if ($cell_reference_helper->cell_address_in_delete_range($cell_address) === false) {
                // Otherwise build a new array of comments indexed by the adjusted cell reference
                $new_reference = $this->update_cell_reference($cell_address);
                $a_new_comments[$new_reference] = $value;
            }
        }
        //    Replace the comments array with the new set of comments
        $worksheet->set_comments($a_new_comments);
    }
    /**
     * Update hyperlinks when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_hyperlinks(Worksheet $worksheet, int $number_of_columns, int $number_of_rows): void
    {
        $a_hyperlink_collection = $worksheet->get_hyperlink_collection();
        $number_of_columns > 0 || $number_of_rows > 0 ? uksort($a_hyperlink_collection, self::cell_reverse_sort(...)) : uksort($a_hyperlink_collection, self::cell_sort(...));
        foreach ($a_hyperlink_collection as $cell_address => $value) {
            $new_reference = $this->update_cell_reference($cell_address);
            /** @var CellReferenceHelper */
            $cell_reference_helper = $this->cell_reference_helper;
            if ($cell_reference_helper->cell_address_in_delete_range($cell_address) === true) {
                $worksheet->set_hyperlink($cell_address);
            } elseif ($cell_address !== $new_reference) {
                $worksheet->set_hyperlink($cell_address);
                if ($new_reference) {
                    $worksheet->set_hyperlink($new_reference, $value);
                }
            }
        }
    }
    /**
     * Update conditional formatting styles when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_conditional_formatting(Worksheet $worksheet, int $number_of_columns, int $number_of_rows): void
    {
        $a_styles = $worksheet->get_conditional_styles_collection();
        $number_of_columns > 0 || $number_of_rows > 0 ? uksort($a_styles, self::cell_reverse_sort(...)) : uksort($a_styles, self::cell_sort(...));
        foreach ($a_styles as $cell_address => $cf_rules) {
            $worksheet->remove_conditional_styles($cell_address);
            $new_reference = $this->update_cell_reference($cell_address);
            foreach ($cf_rules as &$cf_rule) {
                /** @var Conditional $cfRule */
                $conditions = $cf_rule->get_conditions();
                foreach ($conditions as &$condition) {
                    if (is_string($condition)) {
                        /** @var CellReferenceHelper */
                        $cell_reference_helper = $this->cell_reference_helper;
                        $condition = $this->update_formula_references($condition, $cell_reference_helper->before_cell_address(), $number_of_columns, $number_of_rows, $worksheet->get_title(), true);
                    }
                }
                $cf_rule->set_conditions($conditions);
            }
            $worksheet->set_conditional_styles($new_reference, $cf_rules);
        }
    }
    /**
     * Update data validations when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_data_validations(Worksheet $worksheet, int $number_of_columns, int $number_of_rows, string $before_cell_address): void
    {
        $a_data_validation_collection = $worksheet->get_data_validation_collection();
        $number_of_columns > 0 || $number_of_rows > 0 ? uksort($a_data_validation_collection, self::cell_reverse_sort(...)) : uksort($a_data_validation_collection, self::cell_sort(...));
        foreach ($a_data_validation_collection as $cell_address => $data_validation) {
            $formula = $data_validation->get_formula1();
            if ($formula !== '') {
                $data_validation->set_formula1($this->update_formula_references($formula, $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true));
            }
            $formula = $data_validation->get_formula2();
            if ($formula !== '') {
                $data_validation->set_formula2($this->update_formula_references($formula, $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true));
            }
            $address_parts = explode(' ', (string) $cell_address);
            $new_reference = '';
            $separator = '';
            foreach ($address_parts as $address_part) {
                $new_reference .= $separator . $this->update_cell_reference($address_part);
                $separator = ' ';
            }
            if ($cell_address !== $new_reference) {
                $worksheet->set_data_validation($new_reference, $data_validation);
                $worksheet->set_data_validation($cell_address);
                if ($new_reference) {
                    $worksheet->set_data_validation($new_reference, $data_validation);
                }
            }
        }
    }
    /**
     * Update merged cells when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     */
    protected function adjust_merge_cells(Worksheet $worksheet): void
    {
        $a_merge_cells = $worksheet->get_merge_cells();
        $a_new_merge_cells = [];
        // the new array of all merge cells
        foreach ($a_merge_cells as $cell_address => &$value) {
            $new_reference = $this->update_cell_reference($cell_address);
            if ($new_reference) {
                $a_new_merge_cells[$new_reference] = $new_reference;
            }
        }
        $worksheet->set_merge_cells($a_new_merge_cells);
        // replace the merge cells array
    }
    /**
     * Update protected cells when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_protected_cells(Worksheet $worksheet, int $number_of_columns, int $number_of_rows): void
    {
        $a_protected_cells = $worksheet->get_protected_cell_ranges();
        /** @var CellReferenceHelper */
        $cell_reference_helper = $this->cell_reference_helper;
        if ($number_of_rows >= 0 && $number_of_columns >= 0) {
            foreach ($a_protected_cells as $key2 => $value) {
                $ranges = $value->all_ranges();
                $new_key = $separator = '';
                foreach ($ranges as $range) {
                    $old_key = $range[0] . (array_key_exists(1, $range) ? ':' . $range[1] : '');
                    $new_key .= $separator . $this->update_cell_reference($old_key);
                    $separator = ' ';
                }
                if ($key2 !== $new_key) {
                    $worksheet->unprotect_cells($key2);
                    $worksheet->protect_cells($new_key, $value->get_password(), true, $value->get_name(), $value->get_security_descriptor());
                }
            }
        } else {
            foreach ($a_protected_cells as $key2 => $value) {
                $range = str_replace([' ', ',', "\x00"], ["\x00", ' ', ','], $key2);
                $extracted = Coordinate::extract_all_cell_references_in_range($range);
                $out_array = [];
                foreach ($extracted as $cell_address) {
                    if (!$cell_reference_helper->cell_address_in_delete_range($cell_address)) {
                        $out_array[$this->update_cell_reference($cell_address)] = 'x';
                    }
                }
                $out_array2 = Coordinate::merge_ranges_in_collection($out_array);
                $new_key = implode(' ', array_keys($out_array2));
                if ($key2 !== $new_key) {
                    $worksheet->unprotect_cells($key2);
                    $worksheet->protect_cells($new_key, $value->get_password(), true, $value->get_name(), $value->get_security_descriptor());
                }
            }
        }
    }
    /**
     * Update column dimensions when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     */
    protected function adjust_column_dimensions(Worksheet $worksheet): void
    {
        $a_column_dimensions = array_reverse($worksheet->get_column_dimensions(), true);
        if (!empty($a_column_dimensions)) {
            foreach ($a_column_dimensions as $obj_column_dimension) {
                $new_reference = $this->update_cell_reference($obj_column_dimension->get_column_index() . '1');
                [$new_reference] = Coordinate::coordinate_from_string($new_reference);
                if ($obj_column_dimension->get_column_index() !== $new_reference) {
                    $obj_column_dimension->set_column_index($new_reference);
                }
            }
            $worksheet->refresh_column_dimensions();
        }
    }
    /**
     * Update row dimensions when inserting/deleting rows/columns.
     *
     * @param Worksheet $worksheet The worksheet that we're editing
     * @param int $beforeRow Number of the row we're inserting/deleting before
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     */
    protected function adjust_row_dimensions(Worksheet $worksheet, int $before_row, int $number_of_rows): void
    {
        $a_row_dimensions = array_reverse($worksheet->get_row_dimensions(), true);
        if (!empty($a_row_dimensions)) {
            foreach ($a_row_dimensions as $obj_row_dimension) {
                $new_reference = $this->update_cell_reference('A' . $obj_row_dimension->get_row_index());
                [, $new_reference] = Coordinate::coordinate_from_string($new_reference);
                $new_roweference = (int) $new_reference;
                if ($obj_row_dimension->get_row_index() !== $new_roweference) {
                    $obj_row_dimension->set_row_index($new_roweference);
                }
            }
            $worksheet->refresh_row_dimensions();
            $copy_dimension = $worksheet->get_row_dimension($before_row - 1);
            for ($i = $before_row; $i <= $before_row - 1 + $number_of_rows; ++$i) {
                $new_dimension = $worksheet->get_row_dimension($i);
                $new_dimension->set_row_height($copy_dimension->get_row_height());
                $new_dimension->set_visible($copy_dimension->get_visible());
                $new_dimension->set_outline_level($copy_dimension->get_outline_level());
                $new_dimension->set_collapsed($copy_dimension->get_collapsed());
            }
        }
    }
    /**
     * Insert a new column or row, updating all possible related data.
     *
     * @param string $beforeCellAddress Insert before this cell address (e.g. 'A1')
     * @param int $numberOfColumns Number of columns to insert/delete (negative values indicate deletion)
     * @param int $numberOfRows Number of rows to insert/delete (negative values indicate deletion)
     * @param Worksheet $worksheet The worksheet that we're editing
     */
    public function insert_new_before(string $before_cell_address, int $number_of_columns, int $number_of_rows, Worksheet $worksheet): void
    {
        $remove = $number_of_columns < 0 || $number_of_rows < 0;
        if ($this->cell_reference_helper === null || $this->cell_reference_helper->refresh_required($before_cell_address, $number_of_columns, $number_of_rows)) {
            $this->cell_reference_helper = new Cell_Reference_Helper($before_cell_address, $number_of_columns, $number_of_rows);
        }
        // Get coordinate of $beforeCellAddress
        [$before_column, $before_row, $before_column_string] = Coordinate::indexes_from_string($before_cell_address);
        // Clear cells if we are removing columns or rows
        $highest_column = $worksheet->get_highest_column();
        $highest_data_column = $worksheet->get_highest_data_column();
        $highest_row = $worksheet->get_highest_row();
        $highest_data_row = $worksheet->get_highest_data_row();
        // 1. Clear column strips if we are removing columns
        if ($number_of_columns < 0 && $before_column - 2 + $number_of_columns > 0) {
            $this->clear_column_strips($highest_row, $before_column, $number_of_columns, $worksheet);
        }
        // 2. Clear row strips if we are removing rows
        if ($number_of_rows < 0 && $before_row - 1 + $number_of_rows > 0) {
            $this->clear_row_strips($highest_column, $before_column, $before_row, $number_of_rows, $worksheet);
        }
        // Find missing coordinates. This is important when inserting or deleting column before the last column
        $start_row = $start_col = 1;
        $start_col_string = 'A';
        if ($number_of_rows === 0) {
            $start_col = $before_column;
            $start_col_string = $before_column_string;
        } elseif ($number_of_columns === 0) {
            $start_row = $before_row;
        }
        $high_column = Coordinate::column_index_from_string($highest_data_column);
        for ($row = $start_row; $row <= $highest_data_row; ++$row) {
            for ($col = $start_col, $col_string = $start_col_string; $col <= $high_column; ++$col, String_Helper::string_increment($col_string)) {
                $worksheet->get_cell("{$col_string}{$row}");
                // create cell if it doesn't exist
            }
        }
        $all_coordinates = $worksheet->get_coordinates();
        if ($remove) {
            // It's faster to reverse and pop than to use unshift, especially with large cell collections
            $all_coordinates = array_reverse($all_coordinates);
        }
        // Loop through cells, bottom-up, and change cell coordinate
        while ($coordinate = array_pop($all_coordinates)) {
            $cell = $worksheet->get_cell($coordinate);
            $cell_index = Coordinate::column_index_from_string($cell->get_column());
            // Don't update cells that are being removed
            if ($number_of_columns < 0 && $cell_index >= $before_column + $number_of_columns && $cell_index < $before_column) {
                continue;
            }
            // Should the cell be updated? Move value and cellXf index from one cell to another.
            if ($cell_index >= $before_column && $cell->get_row() >= $before_row) {
                // New coordinate
                $new_column = $cell_index + $number_of_columns;
                $new_row = $cell->get_row() + $number_of_rows;
                if ($new_column > 0 && $new_row > 0 && $new_column <= Address_Range::MAX_COLUMN_INT && $new_row <= Address_Range::MAX_ROW) {
                    $new_coordinate = Coordinate::string_from_column_index($new_column) . $new_row;
                    // Update cell styles
                    $worksheet->get_cell($new_coordinate)->set_xf_index($cell->get_xf_index());
                    // Insert this cell at its new location
                    if ($cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                        // Formula should be adjusted
                        $worksheet->get_cell($new_coordinate)->set_value($this->update_formula_references($cell->get_value_string(), $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true));
                    } else {
                        // Cell value should not be adjusted
                        $worksheet->get_cell($new_coordinate)->set_value_explicit($cell->get_value(), $cell->get_data_type());
                    }
                }
                // Clear the original cell
                $worksheet->get_cell_collection()->delete($coordinate);
            } else if ($cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                // Formula should be adjusted
                $cell->set_value($this->update_formula_references($cell->get_value_string(), $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true));
            }
        }
        // Duplicate styles for the newly inserted cells
        $highest_column = $worksheet->get_highest_column();
        $highest_row = $worksheet->get_highest_row();
        if ($number_of_columns > 0 && $before_column > 1) {
            $this->duplicate_styles_by_column($worksheet, $before_column, $before_row, $highest_row, $number_of_columns);
        }
        if ($number_of_rows > 0 && $before_row - 1 > 0) {
            $this->duplicate_styles_by_row($worksheet, $before_column, $before_row, $highest_column, $number_of_rows);
        }
        // Update worksheet: column dimensions
        $this->adjust_column_dimensions($worksheet);
        // Update worksheet: row dimensions
        $this->adjust_row_dimensions($worksheet, $before_row, $number_of_rows);
        //    Update worksheet: page breaks
        $this->adjust_page_breaks($worksheet, $number_of_columns, $number_of_rows);
        //    Update worksheet: comments
        $this->adjust_comments($worksheet);
        // Update worksheet: hyperlinks
        $this->adjust_hyperlinks($worksheet, $number_of_columns, $number_of_rows);
        // Update worksheet: conditional formatting styles
        $this->adjust_conditional_formatting($worksheet, $number_of_columns, $number_of_rows);
        // Update worksheet: data validations
        $this->adjust_data_validations($worksheet, $number_of_columns, $number_of_rows, $before_cell_address);
        // Update worksheet: merge cells
        $this->adjust_merge_cells($worksheet);
        // Update worksheet: protected cells
        $this->adjust_protected_cells($worksheet, $number_of_columns, $number_of_rows);
        // Update worksheet: autofilter
        $this->adjust_auto_filter($worksheet, $before_cell_address, $number_of_columns);
        // Update worksheet: table
        $this->adjust_table($worksheet, $before_cell_address, $number_of_columns);
        // Update worksheet: freeze pane
        if ($worksheet->get_freeze_pane()) {
            $split_cell = $worksheet->get_freeze_pane();
            $top_left_cell = $worksheet->get_top_left_cell() ?? '';
            $split_cell = $this->update_cell_reference($split_cell);
            $top_left_cell = $this->update_cell_reference($top_left_cell);
            $worksheet->freeze_pane($split_cell, $top_left_cell);
        }
        $this->update_print_areas($worksheet, $before_cell_address, $number_of_columns, $number_of_rows);
        // Update worksheet: drawings
        $a_drawings = $worksheet->get_drawing_collection();
        foreach ($a_drawings as $obj_drawing) {
            $new_reference = $this->update_cell_reference($obj_drawing->get_coordinates());
            if ($obj_drawing->get_coordinates() != $new_reference) {
                $obj_drawing->set_coordinates($new_reference);
            }
            if ($obj_drawing->get_coordinates2() !== '') {
                $new_reference = $this->update_cell_reference($obj_drawing->get_coordinates2());
                if ($obj_drawing->get_coordinates2() != $new_reference) {
                    $obj_drawing->set_coordinates2($new_reference);
                }
            }
        }
        // Update workbook: define names
        if (count($worksheet->get_parent_or_throw()->get_defined_names()) > 0) {
            $this->update_defined_names($worksheet, $before_cell_address, $number_of_columns, $number_of_rows);
        }
        // Garbage collect
        $worksheet->garbage_collect();
    }
    private function update_print_areas(Worksheet $worksheet, string $before_cell_address, int $number_of_columns, int $number_of_rows): void
    {
        $page_setup = $worksheet->get_page_setup();
        if (!$page_setup->is_print_area_set()) {
            return;
        }
        $print_areas = explode(',', $page_setup->get_print_area());
        $new_print_areas = [];
        foreach ($print_areas as $print_area) {
            $result = $this->update_print_area($print_area, $before_cell_address, $number_of_columns, $number_of_rows);
            if ($result !== '') {
                $new_print_areas[] = $result;
            }
        }
        $result = implode(',', $new_print_areas);
        if ($result === '') {
            $page_setup->clear_print_area();
        } else {
            $page_setup->set_print_area($result);
        }
    }
    private function update_print_area(string $print_area, string $before_cell_address, int $number_of_columns, int $number_of_rows): string
    {
        $coordinates = Coordinate::indexes_from_string($before_cell_address);
        if (preg_match('/^([A-Z]{1,3})(\d{1,7}):([A-Z]{1,3})(\d{1,7})$/i', $print_area, $matches) === 1) {
            $first_row = (int) $matches[2];
            $last_row = (int) $matches[4];
            $first_column_string = $matches[1];
            $last_column_string = $matches[3];
            if ($number_of_rows < 0) {
                $affected_row = $coordinates[1] + $number_of_rows - 1;
                $last_affected_row = $coordinates[1] - 1;
                if ($affected_row >= $first_row && $affected_row <= $last_row) {
                    $new_last_row = max($affected_row, $last_row + $number_of_rows);
                    if ($new_last_row >= $first_row) {
                        return $matches[1] . $matches[2] . ':' . $matches[3] . $new_last_row;
                    }
                    return '';
                }
                if ($last_affected_row >= $first_row && $affected_row <= $last_row) {
                    $new_first_row = $affected_row + 1;
                    $new_last_row = $last_row + $number_of_rows;
                    if ($new_first_row >= 1 && $new_last_row >= $new_first_row) {
                        return $matches[1] . $new_first_row . ':' . $matches[3] . $new_last_row;
                    }
                    return '';
                }
            }
            if ($number_of_columns < 0) {
                $first_column_int = Coordinate::column_index_from_string($first_column_string);
                $last_column_int = Coordinate::column_index_from_string($last_column_string);
                $affected_column = $coordinates[0] + $number_of_columns - 1;
                $last_affected_column = $coordinates[0] - 1;
                if ($affected_column >= $first_column_int && $affected_column <= $last_column_int) {
                    $new_last_column_int = max($affected_column, $last_column_int + $number_of_columns);
                    if ($new_last_column_int >= $first_column_int) {
                        $new_last_column_string = Coordinate::string_from_column_index($new_last_column_int);
                        return $matches[1] . $matches[2] . ':' . $new_last_column_string . $matches[4];
                    }
                    return '';
                }
                if ($affected_column < $first_column_int && $last_affected_column > $last_column_int) {
                    return '';
                }
                if ($last_affected_column >= $first_column_int && $last_affected_column <= $last_column_int) {
                    $new_first_column = $affected_column + 1;
                    $new_last_column = $last_column_int + $number_of_columns;
                    if ($new_first_column >= 1 && $new_last_column >= $new_first_column) {
                        $first_string = Coordinate::string_from_column_index($new_first_column);
                        $last_string = Coordinate::string_from_column_index($new_last_column);
                        return $first_string . $matches[2] . ':' . $last_string . $matches[4];
                    }
                    return '';
                }
            }
        }
        return $this->update_cell_reference($print_area);
    }
    private static function match_sheet_name(?string $match, string $worksheet_name): bool
    {
        return $match === null || $match === '' || $match === "'￼'" || $match === "'￻'" || strcasecmp(trim($match, "'"), $worksheet_name) === 0;
    }
    private static function sheetname_before_cells(string $match, string $worksheet_name, string $cells): string
    {
        $to_string = $match > '' ? "{$match}!" : '';
        return str_replace(["￼", "'￻'"], $worksheet_name, $to_string) . $cells;
    }
    /**
     * Update references within formulas.
     *
     * @param string $formula Formula to update
     * @param string $beforeCellAddress Insert before this one
     * @param int $numberOfColumns Number of columns to insert
     * @param int $numberOfRows Number of rows to insert
     * @param string $worksheetName Worksheet name/title
     *
     * @return string Updated formula
     */
    public function update_formula_references(string $formula = '', string $before_cell_address = 'A1', int $number_of_columns = 0, int $number_of_rows = 0, string $worksheet_name = '', bool $include_absolute_references = false, bool $only_absolute_references = false): string
    {
        $callback = fn(array $matches): string => strcasecmp(trim((string) $matches[2], "'"), $worksheet_name) === 0 ? $matches[2][0] === "'" ? "'￼'!" : "'￻'!" : "'�'!";
        if ($this->cell_reference_helper === null || $this->cell_reference_helper->refresh_required($before_cell_address, $number_of_columns, $number_of_rows)) {
            $this->cell_reference_helper = new Cell_Reference_Helper($before_cell_address, $number_of_columns, $number_of_rows);
        }
        //    Update cell references in the formula
        $formula_blocks = explode('"', $formula);
        $i = false;
        foreach ($formula_blocks as &$formula_block) {
            //    Ignore blocks that were enclosed in quotes (alternating entries in the $formulaBlocks array after the explode)
            $i = $i === false;
            if ($i) {
                $adjust_count = 0;
                $new_cell_tokens = $cell_tokens = [];
                //    Search for row ranges (e.g. 'Sheet1'!3:5 or 3:5) with or without $ absolutes (e.g. $3:5)
                $formula_blockx = ' ' . (preg_replace_callback(self::SHEETNAME_PART_WITH_SLASHES, $callback, $formula_block) ?? $formula_block) . ' ';
                $match_count = preg_match_all('/' . self::REFHELPER_REGEXP_ROWRANGE . '/mui', $formula_blockx, $matches, PREG_SET_ORDER);
                if ($match_count > 0) {
                    foreach ($matches as $match) {
                        $from_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$match[3]}:{$match[4]}");
                        $modified3 = substr($this->update_cell_reference('$A' . $match[3], $include_absolute_references, $only_absolute_references, true), 2);
                        $modified4 = substr($this->update_cell_reference('$A' . $match[4], $include_absolute_references, $only_absolute_references, false), 2);
                        if ($match[3] . ':' . $match[4] !== $modified3 . ':' . $modified4) {
                            if (self::match_sheet_name($match[2], $worksheet_name)) {
                                $to_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$modified3}:{$modified4}");
                                //    Max worksheet size is 1,048,576 rows by 16,384 columns in Excel 2007, so our adjustments need to be at least one digit more
                                $column = 100000;
                                $row = 10000000 + (int) trim($match[3], '$');
                                $cell_index = "{$column}{$row}";
                                $new_cell_tokens[$cell_index] = preg_quote($to_string, '/');
                                $cell_tokens[$cell_index] = '/(?<!\d\$\!)' . preg_quote($from_string, '/') . '(?!\d)/i';
                                ++$adjust_count;
                            }
                        }
                    }
                }
                //    Search for column ranges (e.g. 'Sheet1'!C:E or C:E) with or without $ absolutes (e.g. $C:E)
                $formula_blockx = ' ' . (preg_replace_callback(self::SHEETNAME_PART_WITH_SLASHES, $callback, $formula_block) ?? $formula_block) . ' ';
                $match_count = preg_match_all('/' . self::REFHELPER_REGEXP_COLRANGE . '/mui', $formula_blockx, $matches, PREG_SET_ORDER);
                if ($match_count > 0) {
                    foreach ($matches as $match) {
                        $from_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$match[3]}:{$match[4]}");
                        $modified3 = substr($this->update_cell_reference($match[3] . '$1', $include_absolute_references, $only_absolute_references, true), 0, -2);
                        $modified4 = substr($this->update_cell_reference($match[4] . '$1', $include_absolute_references, $only_absolute_references, false), 0, -2);
                        if ($match[3] . ':' . $match[4] !== $modified3 . ':' . $modified4) {
                            if (self::match_sheet_name($match[2], $worksheet_name)) {
                                $to_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$modified3}:{$modified4}");
                                //    Max worksheet size is 1,048,576 rows by 16,384 columns in Excel 2007, so our adjustments need to be at least one digit more
                                $column = Coordinate::column_index_from_string(trim($match[3], '$')) + 100000;
                                $row = 10000000;
                                $cell_index = "{$column}{$row}";
                                $new_cell_tokens[$cell_index] = preg_quote($to_string, '/');
                                $cell_tokens[$cell_index] = '/(?<![A-Z\$\!])' . preg_quote($from_string, '/') . '(?![A-Z])/i';
                                ++$adjust_count;
                            }
                        }
                    }
                }
                //    Search for cell ranges (e.g. 'Sheet1'!A3:C5 or A3:C5) with or without $ absolutes (e.g. $A1:C$5)
                $formula_blockx = ' ' . (preg_replace_callback(self::SHEETNAME_PART_WITH_SLASHES, $callback, "{$formula_block}") ?? "{$formula_block}") . ' ';
                $match_count = preg_match_all('/' . self::REFHELPER_REGEXP_CELLRANGE . '/mui', $formula_blockx, $matches, PREG_SET_ORDER);
                if ($match_count > 0) {
                    foreach ($matches as $match) {
                        $from_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$match[3]}:{$match[4]}");
                        $modified3 = $this->update_cell_reference($match[3], $include_absolute_references, $only_absolute_references, true);
                        $modified4 = $this->update_cell_reference($match[4], $include_absolute_references, $only_absolute_references, false);
                        if ($match[3] . $match[4] !== $modified3 . $modified4) {
                            if (self::match_sheet_name($match[2], $worksheet_name)) {
                                $to_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$modified3}:{$modified4}");
                                [$column, $row] = Coordinate::coordinate_from_string($match[3]);
                                //    Max worksheet size is 1,048,576 rows by 16,384 columns in Excel 2007, so our adjustments need to be at least one digit more
                                $column = Coordinate::column_index_from_string(trim($column, '$')) + 100000;
                                $row = (int) trim($row, '$') + 10000000;
                                $cell_index = "{$column}{$row}";
                                $new_cell_tokens[$cell_index] = preg_quote($to_string, '/');
                                $cell_tokens[$cell_index] = '/(?<![A-Z]\$\!)' . preg_quote($from_string, '/') . '(?!\d)/i';
                                ++$adjust_count;
                            }
                        }
                    }
                }
                //    Search for cell references (e.g. 'Sheet1'!A3 or C5) with or without $ absolutes (e.g. $A1 or C$5)
                $formula_blockx = ' ' . (preg_replace_callback(self::SHEETNAME_PART_WITH_SLASHES, $callback, $formula_block) ?? $formula_block) . ' ';
                $match_count = preg_match_all('/' . self::REFHELPER_REGEXP_CELLREF . '/mui', $formula_blockx, $matches, PREG_SET_ORDER);
                if ($match_count > 0) {
                    foreach ($matches as $match) {
                        $from_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$match[3]}");
                        $modified3 = $this->update_cell_reference($match[3], $include_absolute_references, $only_absolute_references);
                        if ($match[3] !== $modified3) {
                            if (self::match_sheet_name($match[2], $worksheet_name)) {
                                $to_string = self::sheetname_before_cells($match[2], $worksheet_name, "{$modified3}");
                                [$column, $row] = Coordinate::coordinate_from_string($match[3]);
                                $column_additional_index = $column[0] === '$' ? 1 : 0;
                                $row_additional_index = $row[0] === '$' ? 1 : 0;
                                //    Max worksheet size is 1,048,576 rows by 16,384 columns in Excel 2007, so our adjustments need to be at least one digit more
                                $column = Coordinate::column_index_from_string(trim($column, '$')) + 100000;
                                $row = (int) trim($row, '$') + 10000000;
                                $cell_index = $row . $row_additional_index . $column . $column_additional_index;
                                $new_cell_tokens[$cell_index] = preg_quote($to_string, '/');
                                $cell_tokens[$cell_index] = '/(?<![A-Z\$\!])' . preg_quote($from_string, '/') . '(?!\d)/i';
                                ++$adjust_count;
                            }
                        }
                    }
                }
                if ($adjust_count > 0) {
                    if ($number_of_columns > 0 || $number_of_rows > 0) {
                        krsort($cell_tokens);
                        krsort($new_cell_tokens);
                    } else {
                        ksort($cell_tokens);
                        ksort($new_cell_tokens);
                    }
                    //  Update cell references in the formula
                    $formula_block = str_replace('\\', '', (string) preg_replace($cell_tokens, $new_cell_tokens, $formula_block));
                }
            }
        }
        unset($formula_block);
        //    Then rebuild the formula string
        return implode('"', $formula_blocks);
    }
    /**
     * Update all cell references within a formula, irrespective of worksheet.
     */
    public function update_formula_references_any_worksheet(string $formula = '', int $number_of_columns = 0, int $number_of_rows = 0): string
    {
        $formula = $this->update_cell_references_all_worksheets($formula, $number_of_columns, $number_of_rows);
        if ($number_of_columns !== 0) {
            $formula = $this->update_column_ranges_all_worksheets($formula, $number_of_columns);
        }
        if ($number_of_rows !== 0) {
            return $this->update_row_ranges_all_worksheets($formula, $number_of_rows);
        }
        return $formula;
    }
    private function update_cell_references_all_worksheets(string $formula, int $number_of_columns, int $number_of_rows): string
    {
        $split_count = preg_match_all('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/mui', $formula, $split_ranges, PREG_OFFSET_CAPTURE);
        $column_lengths = array_map(strlen(...), array_column($split_ranges[6], 0));
        $row_lengths = array_map(strlen(...), array_column($split_ranges[7], 0));
        $column_offsets = array_column($split_ranges[6], 1);
        $row_offsets = array_column($split_ranges[7], 1);
        $columns = $split_ranges[6];
        $rows = $split_ranges[7];
        while ($split_count > 0) {
            --$split_count;
            $column_length = $column_lengths[$split_count];
            $row_length = $row_lengths[$split_count];
            $column_offset = $column_offsets[$split_count];
            $row_offset = $row_offsets[$split_count];
            $column = $columns[$split_count][0];
            $row = $rows[$split_count][0];
            if ($column[0] !== '$') {
                $column = (Coordinate::column_index_from_string($column) + $number_of_columns) % Address_Range::MAX_COLUMN_INT ?: Address_Range::MAX_COLUMN_INT;
                $column = Coordinate::string_from_column_index($column);
                $row_offset -= $column_length - strlen($column);
                $formula = substr($formula, 0, $column_offset) . $column . substr($formula, $column_offset + $column_length);
            }
            if (!empty($row) && $row[0] !== '$') {
                $row = ((int) $row + $number_of_rows) % Address_Range::MAX_ROW ?: Address_Range::MAX_ROW;
                $formula = substr($formula, 0, $row_offset) . $row . substr($formula, $row_offset + $row_length);
            }
        }
        return $formula;
    }
    private function update_column_ranges_all_worksheets(string $formula, int $number_of_columns): string
    {
        $split_count = preg_match_all('/' . Calculation::CALCULATION_REGEXP_COLUMNRANGE_RELATIVE . '/mui', $formula, $split_ranges, PREG_OFFSET_CAPTURE);
        $from_column_lengths = array_map(strlen(...), array_column($split_ranges[1], 0));
        $from_column_offsets = array_column($split_ranges[1], 1);
        $to_column_lengths = array_map(strlen(...), array_column($split_ranges[2], 0));
        $to_column_offsets = array_column($split_ranges[2], 1);
        $from_columns = $split_ranges[1];
        $to_columns = $split_ranges[2];
        while ($split_count > 0) {
            --$split_count;
            $from_column_length = $from_column_lengths[$split_count];
            $to_column_length = $to_column_lengths[$split_count];
            $from_column_offset = $from_column_offsets[$split_count];
            $to_column_offset = $to_column_offsets[$split_count];
            $from_column = $from_columns[$split_count][0];
            $to_column = $to_columns[$split_count][0];
            if (!empty($from_column) && $from_column[0] !== '$') {
                $from_column = Coordinate::string_from_column_index(Coordinate::column_index_from_string($from_column) + $number_of_columns);
                $formula = substr($formula, 0, $from_column_offset) . $from_column . substr($formula, $from_column_offset + $from_column_length);
            }
            if (!empty($to_column) && $to_column[0] !== '$') {
                $to_column = Coordinate::string_from_column_index(Coordinate::column_index_from_string($to_column) + $number_of_columns);
                $formula = substr($formula, 0, $to_column_offset) . $to_column . substr($formula, $to_column_offset + $to_column_length);
            }
        }
        return $formula;
    }
    private function update_row_ranges_all_worksheets(string $formula, int $number_of_rows): string
    {
        $split_count = preg_match_all('/' . Calculation::CALCULATION_REGEXP_ROWRANGE_RELATIVE . '/mui', $formula, $split_ranges, PREG_OFFSET_CAPTURE);
        $from_row_lengths = array_map(strlen(...), array_column($split_ranges[1], 0));
        $from_row_offsets = array_column($split_ranges[1], 1);
        $to_row_lengths = array_map(strlen(...), array_column($split_ranges[2], 0));
        $to_row_offsets = array_column($split_ranges[2], 1);
        $from_rows = $split_ranges[1];
        $to_rows = $split_ranges[2];
        while ($split_count > 0) {
            --$split_count;
            $from_row_length = $from_row_lengths[$split_count];
            $to_row_length = $to_row_lengths[$split_count];
            $from_row_offset = $from_row_offsets[$split_count];
            $to_row_offset = $to_row_offsets[$split_count];
            $from_row = $from_rows[$split_count][0];
            $to_row = $to_rows[$split_count][0];
            if (!empty($from_row) && $from_row[0] !== '$') {
                $from_row = (int) $from_row + $number_of_rows;
                $formula = substr($formula, 0, $from_row_offset) . $from_row . substr($formula, $from_row_offset + $from_row_length);
            }
            if (!empty($to_row) && $to_row[0] !== '$') {
                $to_row = (int) $to_row + $number_of_rows;
                $formula = substr($formula, 0, $to_row_offset) . $to_row . substr($formula, $to_row_offset + $to_row_length);
            }
        }
        return $formula;
    }
    /**
     * Update cell reference.
     *
     * @param string $cellReference Cell address or range of addresses
     *
     * @return string Updated cell range
     */
    private function update_cell_reference(string $cell_reference = 'A1', bool $include_absolute_references = false, bool $only_absolute_references = false, ?bool $top_left = null)
    {
        // Is it in another worksheet? Will not have to update anything.
        if (str_contains($cell_reference, '!')) {
            return $cell_reference;
        }
        // Is it a range or a single cell?
        if (!Coordinate::coordinate_is_range($cell_reference)) {
            // Single cell
            /** @var CellReferenceHelper */
            $cell_reference_helper = $this->cell_reference_helper;
            return $cell_reference_helper->update_cell_reference($cell_reference, $include_absolute_references, $only_absolute_references, $top_left);
        }
        // Range
        return $this->update_cell_range($cell_reference, $include_absolute_references, $only_absolute_references);
    }
    /**
     * Update named formulae (i.e. containing worksheet references / named ranges).
     *
     * @param Spreadsheet $spreadsheet Object to update
     * @param string $oldName Old name (name to replace)
     * @param string $newName New name
     */
    public function update_named_formulae(Spreadsheet $spreadsheet, string $old_name = '', string $new_name = ''): void
    {
        if ($old_name == '') {
            return;
        }
        foreach ($spreadsheet->get_worksheet_iterator() as $sheet) {
            foreach ($sheet->get_coordinates(false) as $coordinate) {
                $cell = $sheet->get_cell($coordinate);
                if ($cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                    $formula = $cell->get_value_string();
                    if (str_contains($formula, $old_name)) {
                        $formula = str_replace("'" . $old_name . "'!", "'" . $new_name . "'!", $formula);
                        $formula = str_replace($old_name . '!', $new_name . '!', $formula);
                        $cell->set_value_explicit($formula, Data_Type::TYPE_FORMULA);
                    }
                }
            }
        }
    }
    private function update_defined_names(Worksheet $worksheet, string $before_cell_address, int $number_of_columns, int $number_of_rows): void
    {
        foreach ($worksheet->get_parent_or_throw()->get_defined_names() as $defined_name) {
            if ($defined_name->is_formula() === false) {
                $this->update_named_range($defined_name, $worksheet, $before_cell_address, $number_of_columns, $number_of_rows);
            } else {
                $this->update_named_formula($defined_name, $worksheet, $before_cell_address, $number_of_columns, $number_of_rows);
            }
        }
    }
    private function update_named_range(Defined_Name $defined_name, Worksheet $worksheet, string $before_cell_address, int $number_of_columns, int $number_of_rows): void
    {
        $cell_address = $defined_name->get_value();
        $as_formula = $cell_address[0] === '=';
        if ($defined_name->get_worksheet() === $worksheet) {
            /**
             * If we delete the entire range that is referenced by a Named Range, MS Excel sets the value to #REF!
             * PhpSpreadsheet still only does a basic adjustment, so the Named Range will still reference Cells.
             * Note that this applies only when deleting columns/rows; subsequent insertion won't fix the #REF!
             * TODO Can we work out a method to identify Named Ranges that cease to be valid, so that we can replace
             *      them with a #REF!
             */
            if ($as_formula === true) {
                $formula = $this->update_formula_references($cell_address, $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true, true);
                $defined_name->set_value($formula);
            } else {
                $defined_name->set_value($this->update_cell_reference(ltrim($cell_address, '='), true));
            }
        }
    }
    private function update_named_formula(Defined_Name $defined_name, Worksheet $worksheet, string $before_cell_address, int $number_of_columns, int $number_of_rows): void
    {
        if ($defined_name->get_worksheet() === $worksheet) {
            /**
             * If we delete the entire range that is referenced by a Named Formula, MS Excel sets the value to #REF!
             * PhpSpreadsheet still only does a basic adjustment, so the Named Formula will still reference Cells.
             * Note that this applies only when deleting columns/rows; subsequent insertion won't fix the #REF!
             * TODO Can we work out a method to identify Named Ranges that cease to be valid, so that we can replace
             *      them with a #REF!
             */
            $formula = $defined_name->get_value();
            $formula = $this->update_formula_references($formula, $before_cell_address, $number_of_columns, $number_of_rows, $worksheet->get_title(), true);
            $defined_name->set_value($formula);
        }
    }
    /**
     * Update cell range.
     *
     * @param string $cellRange Cell range    (e.g. 'B2:D4', 'B:C' or '2:3')
     *
     * @return string Updated cell range
     */
    private function update_cell_range(string $cell_range = 'A1:A1', bool $include_absolute_references = false, bool $only_absolute_references = false): string
    {
        if (!Coordinate::coordinate_is_range($cell_range)) {
            throw new Exception('Only cell ranges may be passed to this method.');
        }
        // Update range
        $range = Coordinate::split_range($cell_range);
        $ic = count($range);
        for ($i = 0; $i < $ic; ++$i) {
            $jc = count($range[$i]);
            for ($j = 0; $j < $jc; ++$j) {
                /** @var CellReferenceHelper */
                $cell_reference_helper = $this->cell_reference_helper;
                if (ctype_alpha($range[$i][$j])) {
                    $range[$i][$j] = Coordinate::coordinate_from_string($cell_reference_helper->update_cell_reference($range[$i][$j] . '1', $include_absolute_references, $only_absolute_references))[0];
                } elseif (ctype_digit($range[$i][$j])) {
                    $range[$i][$j] = Coordinate::coordinate_from_string($cell_reference_helper->update_cell_reference('A' . $range[$i][$j], $include_absolute_references, $only_absolute_references))[1];
                } else {
                    $range[$i][$j] = $cell_reference_helper->update_cell_reference($range[$i][$j], $include_absolute_references, $only_absolute_references);
                }
            }
        }
        // Recreate range string
        return Coordinate::build_range($range);
    }
    private function clear_column_strips(int $highest_row, int $before_column, int $number_of_columns, Worksheet $worksheet): void
    {
        $start_column_id = Coordinate::string_from_column_index($before_column + $number_of_columns);
        $end_column_id = Coordinate::string_from_column_index($before_column);
        for ($row = 1; $row <= $highest_row - 1; ++$row) {
            for ($column = $start_column_id; $column !== $end_column_id; String_Helper::string_increment($column)) {
                $coordinate = $column . $row;
                $this->clear_strip_cell($worksheet, $coordinate);
            }
        }
    }
    private function clear_row_strips(string $highest_column, int $before_column, int $before_row, int $number_of_rows, Worksheet $worksheet): void
    {
        $start_column_id = Coordinate::string_from_column_index($before_column);
        String_Helper::string_increment($highest_column);
        for ($column = $start_column_id; $column !== $highest_column; String_Helper::string_increment($column)) {
            for ($row = $before_row + $number_of_rows; $row <= $before_row - 1; ++$row) {
                $coordinate = $column . $row;
                $this->clear_strip_cell($worksheet, $coordinate);
            }
        }
    }
    private function clear_strip_cell(Worksheet $worksheet, string $coordinate): void
    {
        $worksheet->remove_conditional_styles($coordinate);
        $worksheet->set_hyperlink($coordinate, null, false);
        $worksheet->set_data_validation($coordinate);
        $worksheet->remove_comment($coordinate);
        if ($worksheet->cell_exists($coordinate)) {
            $worksheet->get_cell($coordinate)->set_value_explicit(null, Data_Type::TYPE_NULL);
            $worksheet->get_cell($coordinate)->set_xf_index(0);
        }
    }
    private function adjust_auto_filter(Worksheet $worksheet, string $before_cell_address, int $number_of_columns): void
    {
        $auto_filter = $worksheet->get_auto_filter();
        $auto_filter_range = $auto_filter->get_range();
        if (!empty($auto_filter_range)) {
            if ($number_of_columns !== 0) {
                $auto_filter_columns = $auto_filter->get_columns();
                if (count($auto_filter_columns) > 0) {
                    $column = '';
                    $row = 0;
                    sscanf($before_cell_address, '%[A-Z]%d', $column, $row);
                    $column_index = Coordinate::column_index_from_string((string) $column);
                    [$range_start, $range_end] = Coordinate::range_boundaries($auto_filter_range);
                    if ($column_index <= $range_end[0]) {
                        if ($number_of_columns < 0) {
                            $this->adjust_auto_filter_delete_rules($column_index, $number_of_columns, $auto_filter_columns, $auto_filter);
                        }
                        $start_col = $column_index > $range_start[0] ? $column_index : $range_start[0];
                        //    Shuffle columns in autofilter range
                        if ($number_of_columns > 0) {
                            $this->adjust_auto_filter_insert($start_col, $number_of_columns, $range_end[0], $auto_filter);
                        } else {
                            $this->adjust_auto_filter_delete($start_col, $number_of_columns, $range_end[0], $auto_filter);
                        }
                    }
                }
            }
            $worksheet->set_auto_filter($this->update_cell_reference($auto_filter_range));
        }
    }
    /** @param mixed[] $autoFilterColumns */
    private function adjust_auto_filter_delete_rules(int $column_index, int $number_of_columns, array $auto_filter_columns, Auto_Filter $auto_filter): void
    {
        // If we're actually deleting any columns that fall within the autofilter range,
        //    then we delete any rules for those columns
        $delete_column = $column_index + $number_of_columns - 1;
        $delete_count = abs($number_of_columns);
        for ($i = 1; $i <= $delete_count; ++$i) {
            $column_name = Coordinate::string_from_column_index($delete_column + 1);
            if (isset($auto_filter_columns[$column_name])) {
                $auto_filter->clear_column($column_name);
            }
            ++$delete_column;
        }
    }
    private function adjust_auto_filter_insert(int $start_col, int $number_of_columns, int $range_end, Auto_Filter $auto_filter): void
    {
        $start_col_ref = $start_col;
        $end_col_ref = $range_end;
        $to_col_ref = $range_end + $number_of_columns;
        do {
            $auto_filter->shift_column(Coordinate::string_from_column_index($end_col_ref), Coordinate::string_from_column_index($to_col_ref));
            --$end_col_ref;
            --$to_col_ref;
        } while ($start_col_ref <= $end_col_ref);
    }
    private function adjust_auto_filter_delete(int $start_col, int $number_of_columns, int $range_end, Auto_Filter $auto_filter): void
    {
        // For delete, we shuffle from beginning to end to avoid overwriting
        $start_col_id = Coordinate::string_from_column_index($start_col);
        $to_col_id = Coordinate::string_from_column_index($start_col + $number_of_columns);
        $end_col_id = Coordinate::string_from_column_index($range_end + 1);
        do {
            $auto_filter->shift_column($start_col_id, $to_col_id);
            String_Helper::string_increment($to_col_id);
            String_Helper::string_increment($start_col_id);
        } while ($start_col_id !== $end_col_id);
    }
    private function adjust_table(Worksheet $worksheet, string $before_cell_address, int $number_of_columns): void
    {
        $table_collection = $worksheet->get_table_collection();
        foreach ($table_collection as $table) {
            $table_range = $table->get_range();
            if (!empty($table_range)) {
                if ($number_of_columns !== 0) {
                    $table_columns = $table->get_columns();
                    if (count($table_columns) > 0) {
                        $column = '';
                        $row = 0;
                        sscanf($before_cell_address, '%[A-Z]%d', $column, $row);
                        $column_index = Coordinate::column_index_from_string((string) $column);
                        [$range_start, $range_end] = Coordinate::range_boundaries($table_range);
                        if ($column_index <= $range_end[0]) {
                            if ($number_of_columns < 0) {
                                $this->adjust_table_delete_rules($column_index, $number_of_columns, $table_columns, $table);
                            }
                            $start_col = $column_index > $range_start[0] ? $column_index : $range_start[0];
                            //    Shuffle columns in table range
                            if ($number_of_columns > 0) {
                                $this->adjust_table_insert($start_col, $number_of_columns, $range_end[0], $table);
                            } else {
                                $this->adjust_table_delete($start_col, $number_of_columns, $range_end[0], $table);
                            }
                        }
                    }
                }
                $table->set_range($this->update_cell_reference($table_range));
            }
        }
    }
    /** @param mixed[] $tableColumns */
    private function adjust_table_delete_rules(int $column_index, int $number_of_columns, array $table_columns, Table $table): void
    {
        // If we're actually deleting any columns that fall within the table range,
        //    then we delete any rules for those columns
        $delete_column = $column_index + $number_of_columns - 1;
        $delete_count = abs($number_of_columns);
        for ($i = 1; $i <= $delete_count; ++$i) {
            $column_name = Coordinate::string_from_column_index($delete_column + 1);
            if (isset($table_columns[$column_name])) {
                $table->clear_column($column_name);
            }
            ++$delete_column;
        }
    }
    private function adjust_table_insert(int $start_col, int $number_of_columns, int $range_end, Table $table): void
    {
        $start_col_ref = $start_col;
        $end_col_ref = $range_end;
        $to_col_ref = $range_end + $number_of_columns;
        do {
            $table->shift_column(Coordinate::string_from_column_index($end_col_ref), Coordinate::string_from_column_index($to_col_ref));
            --$end_col_ref;
            --$to_col_ref;
        } while ($start_col_ref <= $end_col_ref);
    }
    private function adjust_table_delete(int $start_col, int $number_of_columns, int $range_end, Table $table): void
    {
        // For delete, we shuffle from beginning to end to avoid overwriting
        $start_col_id = Coordinate::string_from_column_index($start_col);
        $to_col_id = Coordinate::string_from_column_index($start_col + $number_of_columns);
        $end_col_id = Coordinate::string_from_column_index($range_end + 1);
        do {
            $table->shift_column($start_col_id, $to_col_id);
            String_Helper::string_increment($to_col_id);
            String_Helper::string_increment($start_col_id);
        } while ($start_col_id !== $end_col_id);
    }
    private function duplicate_styles_by_column(Worksheet $worksheet, int $before_column, int $before_row, int $highest_row, int $number_of_columns): void
    {
        $before_column_name = Coordinate::string_from_column_index($before_column - 1);
        for ($i = $before_row; $i <= $highest_row; ++$i) {
            // Style
            $coordinate = $before_column_name . $i;
            if ($worksheet->cell_exists($coordinate)) {
                $xf_index = $worksheet->get_cell($coordinate)->get_xf_index();
                for ($j = $before_column; $j <= $before_column - 1 + $number_of_columns; ++$j) {
                    if (!empty($xf_index) || $worksheet->cell_exists([$j, $i])) {
                        $worksheet->get_cell([$j, $i])->set_xf_index($xf_index);
                    }
                }
            }
        }
    }
    private function duplicate_styles_by_row(Worksheet $worksheet, int $before_column, int $before_row, string $highest_column, int $number_of_rows): void
    {
        $highest_column_index = Coordinate::column_index_from_string($highest_column);
        for ($i = $before_column; $i <= $highest_column_index; ++$i) {
            // Style
            $coordinate = Coordinate::string_from_column_index($i) . ($before_row - 1);
            if ($worksheet->cell_exists($coordinate)) {
                $xf_index = $worksheet->get_cell($coordinate)->get_xf_index();
                for ($j = $before_row; $j <= $before_row - 1 + $number_of_rows; ++$j) {
                    if (!empty($xf_index) || $worksheet->cell_exists([$i, $j])) {
                        $worksheet->get_cell(Coordinate::string_from_column_index($i) . $j)->set_xf_index($xf_index);
                    }
                }
            }
        }
    }
    /**
     * __clone implementation. Cloning should not be allowed in a Singleton!
     */
    final public function __clone()
    {
        throw new Exception('Cloning a Singleton is not allowed!');
    }
}