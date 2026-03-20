<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
/**
 * @extends CellIterator<string>
 */
class Row_Cell_Iterator extends Cell_Iterator
{
    /**
     * Current iterator position.
     */
    private int $current_column_index;
    /**
     * Start position.
     */
    private int $start_column_index = 1;
    /**
     * End position.
     */
    private int $end_column_index = 1;
    /**
     * Create a new column iterator.
     *
     * @param Worksheet $worksheet The worksheet to iterate over
     * @param int $rowIndex The row that we want to iterate
     * @param string $startColumn The column address at which to start iterating
     * @param ?string $endColumn Optionally, the column address at which to stop iterating
     */
    public function __construct(Worksheet $worksheet, private readonly int $row_index = 1, string $start_column = 'A', ?string $end_column = null, bool $iterate_only_existing_cells = false)
    {
        // Set subject and row index
        $this->worksheet = $worksheet;
        $this->cell_collection = $worksheet->get_cell_collection();
        $this->reset_end($end_column);
        $this->reset_start($start_column);
        $this->set_iterate_only_existing_cells($iterate_only_existing_cells);
    }
    /**
     * (Re)Set the start column and the current column pointer.
     *
     * @param string $startColumn The column address at which to start iterating
     *
     * @return $this
     */
    public function reset_start(string $start_column = 'A'): static
    {
        $this->start_column_index = Coordinate::column_index_from_string($start_column);
        $this->adjust_for_existing_only_range();
        $this->seek(Coordinate::string_from_column_index($this->start_column_index));
        return $this;
    }
    /**
     * (Re)Set the end column.
     *
     * @param ?string $endColumn The column address at which to stop iterating
     *
     * @return $this
     */
    public function reset_end(?string $end_column = null): static
    {
        $end_column = $end_column ?: $this->worksheet->get_highest_column();
        $this->end_column_index = Coordinate::column_index_from_string($end_column);
        $this->adjust_for_existing_only_range();
        return $this;
    }
    /**
     * Set the column pointer to the selected column.
     *
     * @param string $column The column address to set the current pointer at
     *
     * @return $this
     */
    public function seek(string $column = 'A'): static
    {
        $column_id = Coordinate::column_index_from_string($column);
        if ($this->only_existing_cells && !$this->cell_collection->has($column . $this->row_index)) {
            throw new Php_Spreadsheet_Exception('In "IterateOnlyExistingCells" mode and Cell does not exist');
        }
        if ($column_id < $this->start_column_index || $column_id > $this->end_column_index) {
            throw new Php_Spreadsheet_Exception("Column {$column} is out of range ({$this->start_column_index} - {$this->end_column_index})");
        }
        $this->current_column_index = $column_id;
        return $this;
    }
    /**
     * Rewind the iterator to the starting column.
     */
    public function rewind(): void
    {
        $this->current_column_index = $this->start_column_index;
    }
    /**
     * Return the current cell in this worksheet row.
     */
    public function current(): ?Cell
    {
        $cell_address = Coordinate::string_from_column_index($this->current_column_index) . $this->row_index;
        return $this->cell_collection->has($cell_address) ? $this->cell_collection->get($cell_address) : ($this->if_not_exists === self::IF_NOT_EXISTS_CREATE_NEW ? $this->worksheet->create_new_cell($cell_address) : null);
    }
    /**
     * Return the current iterator key.
     */
    public function key(): string
    {
        return Coordinate::string_from_column_index($this->current_column_index);
    }
    /**
     * Set the iterator to its next value.
     */
    public function next(): void
    {
        do {
            ++$this->current_column_index;
        } while ($this->only_existing_cells && !$this->cell_collection->has(Coordinate::string_from_column_index($this->current_column_index) . $this->row_index) && $this->current_column_index <= $this->end_column_index);
    }
    /**
     * Set the iterator to its previous value.
     */
    public function prev(): void
    {
        do {
            --$this->current_column_index;
        } while ($this->only_existing_cells && !$this->cell_collection->has(Coordinate::string_from_column_index($this->current_column_index) . $this->row_index) && $this->current_column_index >= $this->start_column_index);
    }
    /**
     * Indicate if more columns exist in the worksheet range of columns that we're iterating.
     */
    public function valid(): bool
    {
        return $this->current_column_index <= $this->end_column_index && $this->current_column_index >= $this->start_column_index;
    }
    /**
     * Return the current iterator position.
     */
    public function get_current_column_index(): int
    {
        return $this->current_column_index;
    }
    /**
     * Validate start/end values for "IterateOnlyExistingCells" mode, and adjust if necessary.
     */
    protected function adjust_for_existing_only_range(): void
    {
        if ($this->only_existing_cells) {
            while (!$this->cell_collection->has(Coordinate::string_from_column_index($this->start_column_index) . $this->row_index) && $this->start_column_index <= $this->end_column_index) {
                ++$this->start_column_index;
            }
            while (!$this->cell_collection->has(Coordinate::string_from_column_index($this->end_column_index) . $this->row_index) && $this->end_column_index >= $this->start_column_index) {
                --$this->end_column_index;
            }
        }
    }
}