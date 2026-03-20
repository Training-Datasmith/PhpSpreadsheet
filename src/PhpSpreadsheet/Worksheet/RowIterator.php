<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Iterator as NativeIterator;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
/**
 * @implements NativeIterator<int, Row>
 */
class Row_Iterator implements Native_Iterator
{
    /**
     * Current iterator position.
     */
    private int $position = 1;
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
     * @param Worksheet $subject The worksheet to iterate over
     * @param int $startRow The row number at which to start iterating
     * @param ?int $endRow Optionally, the row number at which to stop iterating
     */
    public function __construct(private Worksheet $subject, int $start_row = 1, ?int $end_row = null)
    {
        $this->reset_end($end_row);
        $this->reset_start($start_row);
    }
    public function __destruct()
    {
        unset($this->subject);
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
        if ($start_row > $this->subject->get_highest_row()) {
            throw new Php_Spreadsheet_Exception("Start row ({$start_row}) is beyond highest row ({$this->subject->get_highest_row()})");
        }
        $this->start_row = $start_row;
        if ($this->end_row < $this->start_row) {
            $this->end_row = $this->start_row;
        }
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
        $this->end_row = $end_row ?: $this->subject->get_highest_row();
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
        if ($row < $this->start_row || $row > $this->end_row) {
            throw new Php_Spreadsheet_Exception("Row {$row} is out of range ({$this->start_row} - {$this->end_row})");
        }
        $this->position = $row;
        return $this;
    }
    /**
     * Rewind the iterator to the starting row.
     */
    public function rewind(): void
    {
        $this->position = $this->start_row;
    }
    /**
     * Return the current row in this worksheet.
     */
    public function current(): Row
    {
        return new Row($this->subject, $this->position);
    }
    /**
     * Return the current iterator key.
     */
    public function key(): int
    {
        return $this->position;
    }
    /**
     * Set the iterator to its next value.
     */
    public function next(): void
    {
        ++$this->position;
    }
    /**
     * Set the iterator to its previous value.
     */
    public function prev(): void
    {
        --$this->position;
    }
    /**
     * Indicate if more rows exist in the worksheet range of rows that we're iterating.
     */
    public function valid(): bool
    {
        return $this->position <= $this->end_row && $this->position >= $this->start_row;
    }
}