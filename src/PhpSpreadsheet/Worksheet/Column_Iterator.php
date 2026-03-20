<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Iterator as NativeIterator;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
/**
 * @implements NativeIterator<string, Column>
 */
class Column_Iterator implements Native_Iterator
{
    /**
     * Current iterator position.
     */
    private int $current_column_index = 1;
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
     * @param string $startColumn The column address at which to start iterating
     * @param ?string $endColumn Optionally, the column address at which to stop iterating
     */
    public function __construct(private Worksheet $worksheet, string $start_column = 'A', ?string $end_column = null)
    {
        $this->reset_end($end_column);
        $this->reset_start($start_column);
    }
    /**
     * Destructor.
     */
    public function __destruct()
    {
        unset($this->worksheet);
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
        $start_column_index = Coordinate::column_index_from_string($start_column);
        if ($start_column_index > Coordinate::column_index_from_string($this->worksheet->get_highest_column())) {
            throw new Exception("Start column ({$start_column}) is beyond highest column ({$this->worksheet->get_highest_column()})");
        }
        $this->start_column_index = $start_column_index;
        if ($this->end_column_index < $this->start_column_index) {
            $this->end_column_index = $this->start_column_index;
        }
        $this->seek($start_column);
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
        $column = Coordinate::column_index_from_string($column);
        if ($column < $this->start_column_index || $column > $this->end_column_index) {
            throw new Php_Spreadsheet_Exception("Column {$column} is out of range ({$this->start_column_index} - {$this->end_column_index})");
        }
        $this->current_column_index = $column;
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
     * Return the current column in this worksheet.
     */
    public function current(): Column
    {
        return new Column($this->worksheet, Coordinate::string_from_column_index($this->current_column_index));
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
        ++$this->current_column_index;
    }
    /**
     * Set the iterator to its previous value.
     */
    public function prev(): void
    {
        --$this->current_column_index;
    }
    /**
     * Indicate if more columns exist in the worksheet range of columns that we're iterating.
     */
    public function valid(): bool
    {
        return $this->current_column_index <= $this->end_column_index && $this->current_column_index >= $this->start_column_index;
    }
}