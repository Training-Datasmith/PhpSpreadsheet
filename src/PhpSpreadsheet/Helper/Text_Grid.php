<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Helper;

use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Text_Grid
{
    /** @var mixed[][] */
    protected array $matrix;
    /** @var int[] */
    protected array $rows;
    /** @var string[] */
    protected array $columns;
    protected string $grid_display;
    /** @param mixed[][] $matrix */
    public function __construct(array $matrix, protected bool $is_cli = true, protected bool $row_dividers = false, protected bool $row_headers = true, protected bool $column_headers = true, protected Text_Grid_Right_Align $numbers_right = Text_Grid_Right_Align::none)
    {
        $this->rows = array_keys($matrix);
        $this->columns = array_keys($matrix[$this->rows[0]]);
        $matrix = array_values($matrix);
        array_walk($matrix, function (array &$row): void {
            $row = array_values($row);
        });
        $this->matrix = $matrix;
    }
    public function set_numbers_right(Text_Grid_Right_Align $numbers_right): void
    {
        $this->numbers_right = $numbers_right;
    }
    public function render(): string
    {
        $this->grid_display = $this->is_cli ? '' : '<pre>' . PHP_EOL;
        if (!empty($this->rows)) {
            $max_row = max($this->rows);
            $max_row_length = $this->strlen((string) $max_row) + 1;
            $column_widths = $this->get_column_widths();
            $this->render_column_header($max_row_length, $column_widths);
            $this->render_rows($max_row_length, $column_widths);
            if (!$this->row_dividers) {
                $this->render_footer($max_row_length, $column_widths);
            }
        }
        $this->grid_display .= $this->is_cli ? '' : '</pre>';
        return $this->grid_display;
    }
    /** @param int[] $columnWidths */
    protected function render_rows(int $max_row_length, array $column_widths): void
    {
        foreach ($this->matrix as $row => $row_data) {
            if ($this->row_headers) {
                $this->grid_display .= '|' . str_pad((string) $this->rows[$row], $max_row_length, ' ', STR_PAD_LEFT) . ' ';
            }
            $this->render_cells($row_data, $column_widths);
            $this->grid_display .= '|' . PHP_EOL;
            if ($this->row_dividers) {
                $this->render_footer($max_row_length, $column_widths);
            }
        }
    }
    /**
     * @param mixed[] $rowData
     * @param int[] $columnWidths
     */
    protected function render_cells(array $row_data, array $column_widths): void
    {
        foreach ($row_data as $column => $cell) {
            $value_for_length = $this->get_string($cell);
            $display_cell = $this->is_cli ? $value_for_length : htmlentities($value_for_length);
            $this->grid_display .= '| ';
            if ($this->right_align($display_cell, $cell)) {
                $this->grid_display .= str_repeat(' ', $column_widths[$column] - $this->strlen($value_for_length)) . $display_cell . ' ';
            } else {
                $this->grid_display .= $display_cell . str_repeat(' ', $column_widths[$column] - $this->strlen($value_for_length) + 1);
            }
        }
    }
    protected function right_align(string $display_cell, mixed $cell = null): bool
    {
        return $this->numbers_right === Text_Grid_Right_Align::numeric && is_numeric($display_cell) || $this->numbers_right === Text_Grid_Right_Align::floatOrInt && (is_int($cell) || is_float($cell));
    }
    /** @param int[] $columnWidths */
    protected function render_column_header(int $max_row_length, array &$column_widths): void
    {
        if (!$this->column_headers) {
            $this->render_footer($max_row_length, $column_widths);
            return;
        }
        foreach ($this->columns as $column => $reference) {
            /** @var string $reference */
            $column_widths[$column] = max($column_widths[$column], $this->strlen($reference));
        }
        if ($this->row_headers) {
            $this->grid_display .= str_repeat(' ', $max_row_length + 2);
        }
        foreach ($this->columns as $column => $reference) {
            $this->grid_display .= '+-' . str_repeat('-', $column_widths[$column] + 1);
        }
        $this->grid_display .= '+' . PHP_EOL;
        if ($this->row_headers) {
            $this->grid_display .= str_repeat(' ', $max_row_length + 2);
        }
        foreach ($this->columns as $column => $reference) {
            /** @var scalar $reference */
            $this->grid_display .= '| ' . str_pad((string) $reference, $column_widths[$column] + 1, ' ');
        }
        $this->grid_display .= '|' . PHP_EOL;
        $this->render_footer($max_row_length, $column_widths);
    }
    /** @param int[] $columnWidths */
    protected function render_footer(int $max_row_length, array $column_widths): void
    {
        if ($this->row_headers) {
            $this->grid_display .= '+' . str_repeat('-', $max_row_length + 1);
        }
        foreach ($this->columns as $column => $reference) {
            $this->grid_display .= '+-';
            $this->grid_display .= str_pad('', $column_widths[$column] + 1, '-');
        }
        $this->grid_display .= '+' . PHP_EOL;
    }
    /** @return int[] */
    protected function get_column_widths(): array
    {
        $column_count = count($this->matrix, COUNT_RECURSIVE) / count($this->matrix);
        $column_widths = [];
        for ($column = 0; $column < $column_count; ++$column) {
            $column_widths[] = $this->get_column_width(array_column($this->matrix, $column));
        }
        return $column_widths;
    }
    /** @param mixed[] $columnData */
    protected function get_column_width(array $column_data): int
    {
        $column_width = 0;
        $column_data = array_values($column_data);
        foreach ($column_data as $column_value) {
            $column_width = max($column_width, $this->strlen($this->get_string($column_value)));
        }
        return $column_width;
    }
    protected function get_string(mixed $value): string
    {
        return String_Helper::convert_to_string($value, convertBool: true);
    }
    protected function strlen(string $value): int
    {
        return mb_strlen($value);
    }
}