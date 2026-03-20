<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Spreadsheet;
/**
 * @implements \Iterator<int, Worksheet>
 */
class Iterator implements \Iterator
{
    /**
     * Current iterator position.
     */
    private int $position = 0;
    /**
     * Create a new worksheet iterator.
     */
    public function __construct(
        /**
         * Spreadsheet to iterate.
         */
        private readonly Spreadsheet $subject
    )
    {
    }
    /**
     * Rewind iterator.
     */
    public function rewind(): void
    {
        $this->position = 0;
    }
    /**
     * Current Worksheet.
     */
    public function current(): Worksheet
    {
        return $this->subject->get_sheet($this->position);
    }
    /**
     * Current key.
     */
    public function key(): int
    {
        return $this->position;
    }
    /**
     * Next value.
     */
    public function next(): void
    {
        ++$this->position;
    }
    /**
     * Are there more Worksheet instances available?
     */
    public function valid(): bool
    {
        return $this->position < $this->subject->get_sheet_count() && $this->position >= 0;
    }
}