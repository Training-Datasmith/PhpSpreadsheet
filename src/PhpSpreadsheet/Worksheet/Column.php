<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

class Column
{
    /**
     * Create a new column.
     */
    public function __construct(
        private Worksheet $worksheet,
        /**
         * Column index.
         */
        private readonly string $column_index = 'A'
    )
    {
    }
    /**
     * Destructor.
     */
    public function __destruct()
    {
        unset($this->worksheet);
    }
    /**
     * Get column index as string eg: 'A'.
     */
    public function get_column_index(): string
    {
        return $this->column_index;
    }
    /**
     * Get cell iterator.
     *
     * @param int $startRow The row number at which to start iterating
     * @param ?int $endRow Optionally, the row number at which to stop iterating
     */
    public function get_cell_iterator(int $start_row = 1, ?int $end_row = null, bool $iterate_only_existing_cells = false): Column_Cell_Iterator
    {
        return new Column_Cell_Iterator($this->worksheet, $this->column_index, $start_row, $end_row, $iterate_only_existing_cells);
    }
    /**
     * Get row iterator. Synonym for getCellIterator().
     *
     * @param int $startRow The row number at which to start iterating
     * @param ?int $endRow Optionally, the row number at which to stop iterating
     */
    public function get_row_iterator(int $start_row = 1, ?int $end_row = null, bool $iterate_only_existing_cells = false): Column_Cell_Iterator
    {
        return $this->get_cell_iterator($start_row, $end_row, $iterate_only_existing_cells);
    }
    /**
     * Returns a boolean true if the column contains no cells. By default, this means that no cell records exist in the
     *         collection for this column. false will be returned otherwise.
     *     This rule can be modified by passing a $definitionOfEmptyFlags value:
     *          1 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL If the only cells in the collection are null value
     *                  cells, then the column will be considered empty.
     *          2 - CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL If the only cells in the collection are empty
     *                  string value cells, then the column will be considered empty.
     *          3 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     *                  If the only cells in the collection are null value or empty string value cells, then the column
     *                  will be considered empty.
     *
     * @param int $definitionOfEmptyFlags
     *              Possible Flag Values are:
     *                  CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL
     *                  CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     * @param int $startRow The row number at which to start checking if cells are empty
     * @param ?int $endRow Optionally, the row number at which to stop checking if cells are empty
     */
    public function is_empty(int $definition_of_empty_flags = 0, int $start_row = 1, ?int $end_row = null): bool
    {
        $null_value_cell_is_empty = (bool) ($definition_of_empty_flags & Cell_Iterator::TREAT_NULL_VALUE_AS_EMPTY_CELL);
        $empty_string_cell_is_empty = (bool) ($definition_of_empty_flags & Cell_Iterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL);
        $cell_iterator = $this->get_cell_iterator($start_row, $end_row);
        $cell_iterator->set_iterate_only_existing_cells(true);
        foreach ($cell_iterator as $cell) {
            $value = $cell->get_value();
            if ($value === null && $null_value_cell_is_empty === true) {
                continue;
            }
            if ($value === '' && $empty_string_cell_is_empty === true) {
                continue;
            }
            return false;
        }
        return true;
    }
    /**
     * Returns bound worksheet.
     */
    public function get_worksheet(): Worksheet
    {
        return $this->worksheet;
    }
}