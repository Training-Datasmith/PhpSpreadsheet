<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Table;

use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Column
{
    /**
     * Show Filter Button.
     */
    private bool $show_filter_button = true;
    /**
     * Total Row Label.
     */
    private ?string $totals_row_label = null;
    /**
     * Total Row Function.
     */
    private ?string $totals_row_function = null;
    /**
     * Total Row Formula.
     */
    private ?string $totals_row_formula = null;
    /**
     * Column Formula.
     */
    private ?string $column_formula = null;
    /**
     * Create a new Column.
     *
     * @param string $columnIndex Column (e.g. A)
     * @param ?Table $table Table for this column
     */
    public function __construct(private string $column_index, private ?Table $table = null)
    {
    }
    /**
     * Get Table column index as string eg: 'A'.
     */
    public function get_column_index(): string
    {
        return $this->column_index;
    }
    /**
     * Set Table column index as string eg: 'A'.
     *
     * @param string $column Column (e.g. A)
     */
    public function set_column_index(string $column): self
    {
        // Uppercase coordinate
        $column = strtoupper($column);
        if ($this->table !== null) {
            $this->table->is_column_in_range($column);
        }
        $this->column_index = $column;
        return $this;
    }
    /**
     * Get show Filter Button.
     */
    public function get_show_filter_button(): bool
    {
        return $this->show_filter_button;
    }
    /**
     * Set show Filter Button.
     */
    public function set_show_filter_button(bool $show_filter_button): self
    {
        $this->show_filter_button = $show_filter_button;
        return $this;
    }
    /**
     * Get total Row Label.
     */
    public function get_totals_row_label(): ?string
    {
        return $this->totals_row_label;
    }
    /**
     * Set total Row Label.
     */
    public function set_totals_row_label(string $totals_row_label): self
    {
        $this->totals_row_label = $totals_row_label;
        return $this;
    }
    /**
     * Get total Row Function.
     */
    public function get_totals_row_function(): ?string
    {
        return $this->totals_row_function;
    }
    /**
     * Set total Row Function.
     */
    public function set_totals_row_function(string $totals_row_function): self
    {
        $this->totals_row_function = $totals_row_function;
        return $this;
    }
    /**
     * Get total Row Formula.
     */
    public function get_totals_row_formula(): ?string
    {
        return $this->totals_row_formula;
    }
    /**
     * Set total Row Formula.
     */
    public function set_totals_row_formula(string $totals_row_formula): self
    {
        $this->totals_row_formula = $totals_row_formula;
        return $this;
    }
    /**
     * Get column Formula.
     */
    public function get_column_formula(): ?string
    {
        return $this->column_formula;
    }
    /**
     * Set column Formula.
     */
    public function set_column_formula(string $column_formula): self
    {
        $this->column_formula = $column_formula;
        return $this;
    }
    /**
     * Get this Column's Table.
     */
    public function get_table(): ?Table
    {
        return $this->table;
    }
    /**
     * Set this Column's Table.
     */
    public function set_table(?Table $table = null): self
    {
        $this->table = $table;
        return $this;
    }
    public static function update_structured_references(?Worksheet $work_sheet, ?string $old_title, ?string $new_title): void
    {
        if ($work_sheet === null || $old_title === null || $old_title === '' || $new_title === null) {
            return;
        }
        // Remember that table headings are case-insensitive
        if (String_Helper::str_to_lower($old_title) !== String_Helper::str_to_lower($new_title)) {
            // We need to check all formula cells that might contain Structured References that refer
            //    to this column, and update those formulae to reference the new column text
            $spreadsheet = $work_sheet->get_parent_or_throw();
            foreach ($spreadsheet->get_worksheet_iterator() as $sheet) {
                self::update_structured_references_in_cells($sheet, $old_title, $new_title);
            }
            self::update_structured_references_in_named_formulae($spreadsheet, $old_title, $new_title);
        }
    }
    private static function update_structured_references_in_cells(Worksheet $worksheet, string $old_title, string $new_title): void
    {
        $pattern = '/\[(@?)' . preg_quote($old_title, '/') . '\]/mui';
        foreach ($worksheet->get_coordinates(false) as $coordinate) {
            $cell = $worksheet->get_cell($coordinate);
            if ($cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                $formula = $cell->get_value_string();
                if (preg_match($pattern, $formula) === 1) {
                    $formula = preg_replace($pattern, "[\$1{$new_title}]", $formula);
                    $cell->set_value_explicit($formula, Data_Type::TYPE_FORMULA);
                }
            }
        }
    }
    private static function update_structured_references_in_named_formulae(Spreadsheet $spreadsheet, string $old_title, string $new_title): void
    {
        $pattern = '/\[(@?)' . preg_quote($old_title, '/') . '\]/mui';
        foreach ($spreadsheet->get_named_formulae() as $named_formula) {
            $formula = $named_formula->get_value();
            if (preg_match($pattern, $formula) === 1) {
                $formula = preg_replace($pattern, "[\$1{$new_title}]", $formula) ?? '';
                $named_formula->set_value($formula);
            }
        }
    }
}