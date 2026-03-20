<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Table\Table_Style;
use Stringable;
class Table implements Stringable
{
    /**
     * Table Name.
     */
    private string $name;
    /**
     * Show Header Row.
     */
    private bool $show_header_row = true;
    /**
     * Show Totals Row.
     */
    private bool $show_totals_row = false;
    /**
     * Table Range.
     */
    private string $range = '';
    /**
     * Table Worksheet.
     */
    private ?Worksheet $work_sheet = null;
    /**
     * Table allow filter.
     */
    private bool $allow_filter = true;
    /**
     * Table Column.
     *
     * @var Table\Column[]
     */
    private array $columns = [];
    /**
     * Table Style.
     */
    private Table_Style $style;
    /**
     * Table AutoFilter.
     */
    private Auto_Filter $auto_filter;
    /**
     * Create a new Table.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range
     *            A simple string containing a Cell range like 'A1:E10' is permitted
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange object.
     * @param string $name (e.g. Table1)
     */
    public function __construct(Address_Range|string|array $range = '', string $name = '')
    {
        $this->style = new Table_Style();
        $this->auto_filter = new Auto_Filter($range);
        $this->set_range($range);
        $this->set_name($name);
    }
    /**
     * Code to execute when this table is unset().
     */
    public function __destruct()
    {
        $this->work_sheet = null;
    }
    /**
     * Get Table name.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Set Table name.
     *
     * @throws PhpSpreadsheetException
     */
    public function set_name(string $name): self
    {
        $name = trim($name);
        if (!empty($name)) {
            if (strlen($name) === 1 && in_array($name, ['C', 'c', 'R', 'r'])) {
                throw new Php_Spreadsheet_Exception('The table name is invalid');
            }
            if (String_Helper::count_characters($name) > 255) {
                throw new Php_Spreadsheet_Exception('The table name cannot be longer than 255 characters');
            }
            // Check for A1 or R1C1 cell reference notation
            if (preg_match(Coordinate::A1_COORDINATE_REGEX, $name) || preg_match('/^R\[?\-?[0-9]*\]?C\[?\-?[0-9]*\]?$/i', $name)) {
                throw new Php_Spreadsheet_Exception('The table name can\'t be the same as a cell reference');
            }
            if (!preg_match('/^[\p{L}_\\\\]/iu', $name)) {
                throw new Php_Spreadsheet_Exception('The table name must begin a name with a letter, an underscore character (_), or a backslash (\)');
            }
            if (!preg_match('/^[\p{L}_\\\\][\p{L}\p{M}0-9\._]*$/iu', $name)) {
                throw new Php_Spreadsheet_Exception('The table name contains invalid characters');
            }
            $this->check_for_duplicate_table_names($name, $this->work_sheet);
            $this->update_structured_references($name);
        }
        $this->name = $name;
        return $this;
    }
    /**
     * @throws PhpSpreadsheetException
     */
    private function check_for_duplicate_table_names(string $name, ?Worksheet $worksheet): void
    {
        // Remember that table names are case-insensitive
        $table_name = String_Helper::str_to_lower($name);
        if ($worksheet !== null && String_Helper::str_to_lower($this->name) !== $name) {
            $spreadsheet = $worksheet->get_parent_or_throw();
            foreach ($spreadsheet->get_worksheet_iterator() as $sheet) {
                foreach ($sheet->get_table_collection() as $table) {
                    if (String_Helper::str_to_lower($table->get_name()) === $table_name && $table != $this) {
                        throw new Php_Spreadsheet_Exception("Spreadsheet already contains a table named '{$this->name}'");
                    }
                }
            }
        }
    }
    private function update_structured_references(string $name): void
    {
        if (!$this->work_sheet || !$this->name) {
            return;
        }
        // Remember that table names are case-insensitive
        if (String_Helper::str_to_lower($this->name) !== String_Helper::str_to_lower($name)) {
            // We need to check all formula cells that might contain fully-qualified Structured References
            //    that refer to this table, and update those formulae to reference the new table name
            $spreadsheet = $this->work_sheet->get_parent_or_throw();
            foreach ($spreadsheet->get_worksheet_iterator() as $sheet) {
                $this->update_structured_references_in_cells($sheet, $name);
            }
            $this->update_structured_references_in_named_formulae($spreadsheet, $name);
        }
    }
    private function update_structured_references_in_cells(Worksheet $worksheet, string $new_name): void
    {
        $pattern = '/' . preg_quote($this->name, '/') . '\[/mui';
        foreach ($worksheet->get_coordinates(false) as $coordinate) {
            $cell = $worksheet->get_cell($coordinate);
            if ($cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                $formula = $cell->get_value_string();
                if (preg_match($pattern, $formula) === 1) {
                    $formula = preg_replace($pattern, "{$new_name}[", $formula);
                    $cell->set_value_explicit($formula, Data_Type::TYPE_FORMULA);
                }
            }
        }
    }
    private function update_structured_references_in_named_formulae(Spreadsheet $spreadsheet, string $new_name): void
    {
        $pattern = '/' . preg_quote($this->name, '/') . '\[/mui';
        foreach ($spreadsheet->get_named_formulae() as $named_formula) {
            $formula = $named_formula->get_value();
            if (preg_match($pattern, $formula) === 1) {
                $formula = preg_replace($pattern, "{$new_name}[", $formula) ?? '';
                $named_formula->set_value($formula);
            }
        }
    }
    /**
     * Get show Header Row.
     */
    public function get_show_header_row(): bool
    {
        return $this->show_header_row;
    }
    /**
     * Set show Header Row.
     */
    public function set_show_header_row(bool $show_header_row): self
    {
        $this->show_header_row = $show_header_row;
        return $this;
    }
    /**
     * Get show Totals Row.
     */
    public function get_show_totals_row(): bool
    {
        return $this->show_totals_row;
    }
    /**
     * Set show Totals Row.
     */
    public function set_show_totals_row(bool $show_totals_row): self
    {
        $this->show_totals_row = $show_totals_row;
        return $this;
    }
    /**
     * Get allow filter.
     * If false, autofiltering is disabled for the table, if true it is enabled.
     */
    public function get_allow_filter(): bool
    {
        return $this->allow_filter;
    }
    /**
     * Set show Autofiltering.
     * Disabling autofiltering has the same effect as hiding the filter button on all the columns in the table.
     */
    public function set_allow_filter(bool $allow_filter): self
    {
        $this->allow_filter = $allow_filter;
        return $this;
    }
    /**
     * Get Table Range.
     */
    public function get_range(): string
    {
        return $this->range;
    }
    /**
     * Set Table Cell Range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range
     *            A simple string containing a Cell range like 'A1:E10' is permitted
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange object.
     */
    public function set_range(Address_Range|string|array $range = ''): self
    {
        // extract coordinate
        if ($range !== '') {
            [, $range] = Worksheet::extract_sheet_title(Validations::validate_cell_range($range), true);
        }
        if (empty($range)) {
            //    Discard all column rules
            $this->columns = [];
            $this->range = '';
            return $this;
        }
        if (!str_contains($range, ':')) {
            throw new Php_Spreadsheet_Exception('Table must be set on a range of cells.');
        }
        [$width, $height] = Coordinate::range_dimension($range);
        if ($width < 1 || $height < 1) {
            throw new Php_Spreadsheet_Exception('The table range must be at least 1 column and row');
        }
        $this->range = $range;
        $this->auto_filter->set_range($range);
        //    Discard any column rules that are no longer valid within this range
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        foreach ($this->columns as $key => $value) {
            $col_index = Coordinate::column_index_from_string($key);
            if ($range_start[0] > $col_index || $range_end[0] < $col_index) {
                unset($this->columns[$key]);
            }
        }
        return $this;
    }
    /**
     * Set Table Cell Range to max row.
     */
    public function set_range_to_max_row(): self
    {
        if ($this->work_sheet !== null) {
            $thisrange = $this->range;
            $range = (string) preg_replace('/\d+$/', (string) $this->work_sheet->get_highest_row(), $thisrange);
            if ($range !== $thisrange) {
                $this->set_range($range);
            }
        }
        return $this;
    }
    /**
     * Get Table's Worksheet.
     */
    public function get_worksheet(): ?Worksheet
    {
        return $this->work_sheet;
    }
    /**
     * Set Table's Worksheet.
     */
    public function set_worksheet(?Worksheet $worksheet = null): self
    {
        if ($this->name !== '' && $worksheet !== null) {
            $spreadsheet = $worksheet->get_parent_or_throw();
            $table_name = String_Helper::str_to_upper($this->name);
            foreach ($spreadsheet->get_worksheet_iterator() as $sheet) {
                foreach ($sheet->get_table_collection() as $table) {
                    if (String_Helper::str_to_upper($table->get_name()) === $table_name) {
                        throw new Php_Spreadsheet_Exception("Workbook already contains a table named '{$this->name}'");
                    }
                }
            }
        }
        $this->work_sheet = $worksheet;
        $this->auto_filter->set_parent($worksheet);
        return $this;
    }
    /**
     * Get all Table Columns.
     *
     * @return Table\Column[]
     */
    public function get_columns(): array
    {
        return $this->columns;
    }
    /**
     * Validate that the specified column is in the Table range.
     *
     * @param string $column Column name (e.g. A)
     *
     * @return int The column offset within the table range
     */
    public function is_column_in_range(string $column): int
    {
        if (empty($this->range)) {
            throw new Php_Spreadsheet_Exception('No table range is defined.');
        }
        $column_index = Coordinate::column_index_from_string($column);
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        if ($range_start[0] > $column_index || $range_end[0] < $column_index) {
            throw new Php_Spreadsheet_Exception('Column is outside of current table range.');
        }
        return $column_index - $range_start[0];
    }
    /**
     * Get a specified Table Column Offset within the defined Table range.
     *
     * @param string $column Column name (e.g. A)
     *
     * @return int The offset of the specified column within the table range
     */
    public function get_column_offset(string $column): int
    {
        return $this->is_column_in_range($column);
    }
    /**
     * Get a specified Table Column.
     *
     * @param string $column Column name (e.g. A)
     */
    public function get_column(string $column): Table\Column
    {
        $this->is_column_in_range($column);
        if (!isset($this->columns[$column])) {
            $this->columns[$column] = new Table\Column($column, $this);
        }
        return $this->columns[$column];
    }
    /**
     * Get a specified Table Column by its offset.
     *
     * @param int $columnOffset Column offset within range (starting from 0)
     */
    public function get_column_by_offset(int $column_offset): Table\Column
    {
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        $p_column = Coordinate::string_from_column_index($range_start[0] + $column_offset);
        return $this->get_column($p_column);
    }
    /**
     * Set Table.
     *
     * @param string|Table\Column $columnObjectOrString
     *            A simple string containing a Column ID like 'A' is permitted
     */
    public function set_column(string|Table\Column $column_object_or_string): self
    {
        if (is_string($column_object_or_string) && !empty($column_object_or_string)) {
            $column = $column_object_or_string;
        } elseif ($column_object_or_string instanceof Table\Column) {
            $column = $column_object_or_string->get_column_index();
        } else {
            throw new Php_Spreadsheet_Exception('Column is not within the table range.');
        }
        $this->is_column_in_range($column);
        if (is_string($column_object_or_string)) {
            $this->columns[$column_object_or_string] = new Table\Column($column_object_or_string, $this);
        } else {
            $column_object_or_string->set_table($this);
            $this->columns[$column] = $column_object_or_string;
        }
        ksort($this->columns);
        return $this;
    }
    /**
     * Clear a specified Table Column.
     *
     * @param string $column Column name (e.g. A)
     */
    public function clear_column(string $column): self
    {
        $this->is_column_in_range($column);
        if (isset($this->columns[$column])) {
            unset($this->columns[$column]);
        }
        return $this;
    }
    /**
     * Shift a Table Column Rule to a different column.
     *
     * Note: This method bypasses validation of the destination column to ensure it is within this Table range.
     *        Nor does it verify whether any column rule already exists at $toColumn, but will simply override any existing value.
     *        Use with caution.
     *
     * @param string $fromColumn Column name (e.g. A)
     * @param string $toColumn Column name (e.g. B)
     */
    public function shift_column(string $from_column, string $to_column): self
    {
        $from_column = strtoupper($from_column);
        $to_column = strtoupper($to_column);
        if (isset($this->columns[$from_column])) {
            $this->columns[$from_column]->set_table();
            $this->columns[$from_column]->set_column_index($to_column);
            $this->columns[$to_column] = $this->columns[$from_column];
            $this->columns[$to_column]->set_table($this);
            unset($this->columns[$from_column]);
            ksort($this->columns);
        }
        return $this;
    }
    /**
     * Get table Style.
     */
    public function get_style(): Table_Style
    {
        return $this->style;
    }
    /**
     * Set table Style.
     */
    public function set_style(Table_Style $style): self
    {
        $this->style = $style;
        return $this;
    }
    /**
     * Get AutoFilter.
     */
    public function get_auto_filter(): Auto_Filter
    {
        return $this->auto_filter;
    }
    /**
     * Set AutoFilter.
     */
    public function set_auto_filter(Auto_Filter $auto_filter): self
    {
        $this->auto_filter = $auto_filter;
        return $this;
    }
    /**
     * Get the row number on this table for given coordinates.
     */
    public function get_row_number(string $coordinate): int
    {
        $range = $this->get_range();
        $coords = Coordinate::split_range($range);
        $first_cell = Coordinate::coordinate_from_string($coords[0][0]);
        $this_cell = Coordinate::coordinate_from_string($coordinate);
        return (int) $this_cell[1] - (int) $first_cell[1];
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                if ($key === 'workSheet') {
                    //    Detach from worksheet
                    $this->{$key} = null;
                } else {
                    $this->{$key} = clone $value;
                }
            } elseif (is_array($value) && $key === 'columns') {
                //    The columns array of \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet\Table objects
                $this->{$key} = [];
                foreach ($value as $k => $v) {
                    /** @var Table\Column $v */
                    $this->{$key}[$k] = clone $v;
                    // attach the new cloned Column to this new cloned Table object
                    $this->{$key}[$k]->set_table($this);
                }
            } else {
                $this->{$key} = $value;
            }
        }
    }
    /**
     * toString method replicates previous behavior by returning the range if object is
     * referenced as a property of its worksheet.
     */
    public function __toString(): string
    {
        return $this->range;
    }
}