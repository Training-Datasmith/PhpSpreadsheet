<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
class Cell_Reference_Helper
{
    protected string $before_cell_address;
    protected int $before_column;
    protected bool $before_column_absolute = false;
    protected string $before_column_string;
    protected int $before_row;
    protected bool $before_row_absolute = false;
    public function __construct(string $before_cell_address = 'A1', protected int $number_of_columns = 0, protected int $number_of_rows = 0)
    {
        $this->before_column_absolute = $before_cell_address[0] === '$';
        $this->before_row_absolute = str_contains(substr($before_cell_address, 1), '$');
        $this->before_cell_address = str_replace('$', '', $before_cell_address);
        // Get coordinate of $beforeCellAddress
        [$before_column, $before_row] = Coordinate::coordinate_from_string($before_cell_address);
        $this->before_column_string = $before_column;
        $this->before_column = Coordinate::column_index_from_string($before_column);
        $this->before_row = (int) $before_row;
    }
    public function before_cell_address(): string
    {
        return $this->before_cell_address;
    }
    public function refresh_required(string $before_cell_address, int $number_of_columns, int $number_of_rows): bool
    {
        return $this->before_cell_address !== $before_cell_address || $this->number_of_columns !== $number_of_columns || $this->number_of_rows !== $number_of_rows;
    }
    public function update_cell_reference(string $cell_reference = 'A1', bool $include_absolute_references = false, bool $only_absolute_references = false, ?bool $top_left = null): string
    {
        if (Coordinate::coordinate_is_range($cell_reference)) {
            throw new Exception('Only single cell references may be passed to this method.');
        }
        // Get coordinate of $cellReference
        [$new_column, $new_row] = Coordinate::coordinate_from_string($cell_reference);
        $new_column_index = Coordinate::column_index_from_string(str_replace('$', '', $new_column));
        $new_row_index = (int) str_replace('$', '', $new_row);
        $absolute_column = $new_column[0] === '$' ? '$' : '';
        $absolute_row = $new_row[0] === '$' ? '$' : '';
        // Verify which parts should be updated
        if ($only_absolute_references === true) {
            $update_column = $absolute_column === '$' && $new_column_index >= $this->before_column;
            $update_row = $absolute_row === '$' && $new_row_index >= $this->before_row;
        } elseif ($include_absolute_references === false) {
            $update_column = $absolute_column !== '$' && $new_column_index >= $this->before_column;
            $update_row = $absolute_row !== '$' && $new_row_index >= $this->before_row;
        } else {
            $new_column_index = $this->compute_new_column_index($new_column_index, $top_left);
            $new_column = $absolute_column . Coordinate::string_from_column_index($new_column_index);
            $update_column = false;
            $new_row_index = $this->compute_new_row_index($new_row_index, $top_left);
            $new_row = $absolute_row . $new_row_index;
            $update_row = false;
        }
        // Create new column reference
        if ($update_column) {
            $new_column = $this->update_column_reference($new_column_index, $absolute_column);
        }
        // Create new row reference
        if ($update_row) {
            $new_row = $this->update_row_reference($new_row_index, $absolute_row);
        }
        // Return new reference
        return "{$new_column}{$new_row}";
    }
    public function compute_new_column_index(int $new_column_index, ?bool $top_left): int
    {
        // A special case is removing the left/top or bottom/right edge of a range
        // $topLeft is null if we aren't adjusting a range at all.
        if ($top_left !== null && $this->number_of_columns < 0 && $new_column_index >= $this->before_column + $this->number_of_columns && $new_column_index <= $this->before_column - 1) {
            if ($top_left) {
                $new_column_index = $this->before_column + $this->number_of_columns;
            } else {
                $new_column_index = $this->before_column + $this->number_of_columns - 1;
            }
        } elseif ($new_column_index >= $this->before_column) {
            // Create new column reference
            $new_column_index += $this->number_of_columns;
        }
        return $new_column_index;
    }
    public function compute_new_row_index(int $new_row_index, ?bool $top_left): int
    {
        // A special case is removing the left/top or bottom/right edge of a range
        // $topLeft is null if we aren't adjusting a range at all.
        if ($top_left !== null && $this->number_of_rows < 0 && $new_row_index >= $this->before_row + $this->number_of_rows && $new_row_index <= $this->before_row - 1) {
            if ($top_left) {
                $new_row_index = $this->before_row + $this->number_of_rows;
            } else {
                $new_row_index = $this->before_row + $this->number_of_rows - 1;
            }
        } elseif ($new_row_index >= $this->before_row) {
            $new_row_index = $new_row_index + $this->number_of_rows;
        }
        return $new_row_index;
    }
    public function cell_address_in_delete_range(string $cell_address): bool
    {
        [$cell_column, $cell_row] = Coordinate::coordinate_from_string($cell_address);
        $cell_column_index = Coordinate::column_index_from_string($cell_column);
        //    Is cell within the range of rows/columns if we're deleting
        if ($this->number_of_rows < 0 && $cell_row >= $this->before_row + $this->number_of_rows && $cell_row < $this->before_row) {
            return true;
        }
        //    Is cell within the range of rows/columns if we're deleting
        if ($this->number_of_columns < 0 && $cell_column_index >= $this->before_column + $this->number_of_columns && $cell_column_index < $this->before_column) {
            return true;
        }
        return false;
    }
    protected function update_column_reference(int $new_column_index, string $absolute_column): string
    {
        $new_column = Coordinate::string_from_column_index(min($new_column_index + $this->number_of_columns, Address_Range::MAX_COLUMN_INT));
        return "{$absolute_column}{$new_column}";
    }
    protected function update_row_reference(int $new_row_index, string $absolute_row): string
    {
        $new_row = $new_row_index + $this->number_of_rows;
        $new_row = $new_row > Address_Range::MAX_ROW ? Address_Range::MAX_ROW : $new_row;
        return "{$absolute_row}{$new_row}";
    }
}