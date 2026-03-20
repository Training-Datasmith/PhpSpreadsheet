<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

class Row
{
    /**
     * Create a new row.
     */
    public function __construct(
        private Worksheet $worksheet,
        /**
         * Row index.
         */
        private readonly int $row_index = 1
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
     * Get row index.
     */
    public function get_row_index(): int
    {
        return $this->row_index;
    }
    /**
     * Get cell iterator.
     *
     * @param string $startColumn The column address at which to start iterating
     * @param ?string $endColumn Optionally, the column address at which to stop iterating
     */
    public function get_cell_iterator(string $start_column = 'A', ?string $end_column = null, bool $iterate_only_existing_cells = false): Row_Cell_Iterator
    {
        return new Row_Cell_Iterator($this->worksheet, $this->row_index, $start_column, $end_column, $iterate_only_existing_cells);
    }
    /**
     * Get column iterator. Synonym for getCellIterator().
     *
     * @param string $startColumn The column address at which to start iterating
     * @param ?string $endColumn Optionally, the column address at which to stop iterating
     */
    public function get_column_iterator(string $start_column = 'A', ?string $end_column = null, bool $iterate_only_existing_cells = false): Row_Cell_Iterator
    {
        return $this->get_cell_iterator($start_column, $end_column, $iterate_only_existing_cells);
    }
    /**
     * Returns a boolean true if the row contains no cells. By default, this means that no cell records exist in the
     *         collection for this row. false will be returned otherwise.
     *     This rule can be modified by passing a $definitionOfEmptyFlags value:
     *          1 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL If the only cells in the collection are null value
     *                  cells, then the row will be considered empty.
     *          2 - CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL If the only cells in the collection are empty
     *                  string value cells, then the row will be considered empty.
     *          3 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     *                  If the only cells in the collection are null value or empty string value cells, then the row
     *                  will be considered empty.
     *
     * @param int $definitionOfEmptyFlags
     *              Possible Flag Values are:
     *                  CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL
     *                  CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     * @param string $startColumn The column address at which to start checking if cells are empty
     * @param ?string $endColumn Optionally, the column address at which to stop checking if cells are empty
     */
    public function is_empty(int $definition_of_empty_flags = 0, string $start_column = 'A', ?string $end_column = null): bool
    {
        $null_value_cell_is_empty = (bool) ($definition_of_empty_flags & Cell_Iterator::TREAT_NULL_VALUE_AS_EMPTY_CELL);
        $empty_string_cell_is_empty = (bool) ($definition_of_empty_flags & Cell_Iterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL);
        $cell_iterator = $this->get_cell_iterator($start_column, $end_column);
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