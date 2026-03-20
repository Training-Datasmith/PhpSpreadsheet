<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
/**
 * @extends CellIterator<int>
 */
class Column_Cell_Iterator extends Cell_Iterator
{
    /**
     * Current iterator position.
     */
    private int $current_row;
    /**
     * Column index.
     */
    private readonly int $column_index;
    /**
     * Start position.
     */
    private int $start_row = 1;
    /**
     * End position.
     */
    private int $end_row = 1;
    /**
     * Create a new row iterator.
     *
     * @param Worksheet $worksheet The worksheet to iterate over
     * @param string $columnIndex The column that we want to iterate
     * @param int $startRow The row number at which to start iterating
     * @param ?int $endRow Optionally, the row number at which to stop iterating
     */
    public function __construct(Worksheet $worksheet, string $column_index = 'A', int $start_row = 1, ?int $end_row = null, bool $iterate_only_existing_cells = false)
    {
        // Set subject
        $this->worksheet = $worksheet;
        $this->cell_collection = $worksheet->get_cell_collection();
        $this->column_index = Coordinate::column_index_from_string($column_index);
        $this->reset_end($end_row);
        $this->reset_start($start_row);
        $this->set_iterate_only_existing_cells($iterate_only_existing_cells);
    }
    /**
     * (Re)Set the start row and the current row pointer.
     *
     * @param int $startRow The row number at which to start iterating
     *
     * @return $this
     */
    public function reset_start(int $start_row = 1): static
    {
        $this->start_row = $start_row;
        $this->adjust_for_existing_only_range();
        $this->seek($start_row);
        return $this;
    }
    /**
     * (Re)Set the end row.
     *
     * @param ?int $endRow The row number at which to stop iterating
     *
     * @return $this
     */
    public function reset_end(?int $end_row = null): static
    {
        $this->end_row = $end_row ?: $this->worksheet->get_highest_row();
        $this->adjust_for_existing_only_range();
        return $this;
    }
    /**
     * Set the row pointer to the selected row.
     *
     * @param int $row The row number to set the current pointer at
     *
     * @return $this
     */
    public function seek(int $row = 1): static
    {
        if ($this->only_existing_cells && !$this->cell_collection->has(Coordinate::string_from_column_index($this->column_index) . $row)) {
            throw new Php_Spreadsheet_Exception('In "IterateOnlyExistingCells" mode and Cell does not exist');
        }
        if ($row < $this->start_row || $row > $this->end_row) {
            throw new Php_Spreadsheet_Exception("Row {$row} is out of range ({$this->start_row} - {$this->end_row})");
        }
        $this->current_row = $row;
        return $this;
    }
    /**
     * Rewind the iterator to the starting row.
     */
    public function rewind(): void
    {
        $this->current_row = $this->start_row;
    }
    /**
     * Return the current cell in this worksheet column.
     */
    public function current(): ?Cell
    {
        $cell_address = Coordinate::string_from_column_index($this->column_index) . $this->current_row;
        return $this->cell_collection->has($cell_address) ? $this->cell_collection->get($cell_address) : ($this->if_not_exists === self::IF_NOT_EXISTS_CREATE_NEW ? $this->worksheet->create_new_cell($cell_address) : null);
    }
    /**
     * Return the current iterator key.
     */
    public function key(): int
    {
        return $this->current_row;
    }
    /**
     * Set the iterator to its next value.
     */
    public function next(): void
    {
        $column_address = Coordinate::string_from_column_index($this->column_index);
        do {
            ++$this->current_row;
        } while ($this->only_existing_cells && $this->current_row <= $this->end_row && !$this->cell_collection->has($column_address . $this->current_row));
    }
    /**
     * Set the iterator to its previous value.
     */
    public function prev(): void
    {
        $column_address = Coordinate::string_from_column_index($this->column_index);
        do {
            --$this->current_row;
        } while ($this->only_existing_cells && $this->current_row >= $this->start_row && !$this->cell_collection->has($column_address . $this->current_row));
    }
    /**
     * Indicate if more rows exist in the worksheet range of rows that we're iterating.
     */
    public function valid(): bool
    {
        return $this->current_row <= $this->end_row && $this->current_row >= $this->start_row;
    }
    /**
     * Validate start/end values for "IterateOnlyExistingCells" mode, and adjust if necessary.
     */
    protected function adjust_for_existing_only_range(): void
    {
        if ($this->only_existing_cells) {
            $column_address = Coordinate::string_from_column_index($this->column_index);
            while (!$this->cell_collection->has($column_address . $this->start_row) && $this->start_row <= $this->end_row) {
                ++$this->start_row;
            }
            while (!$this->cell_collection->has($column_address . $this->end_row) && $this->end_row >= $this->start_row) {
                --$this->end_row;
            }
        }
    }
}