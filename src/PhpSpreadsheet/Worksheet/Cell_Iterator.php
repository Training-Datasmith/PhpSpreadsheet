<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Iterator as NativeIterator;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Collection\Cells;
/**
 * @template TKey
 *
 * @implements NativeIterator<TKey, Cell>
 */
abstract class Cell_Iterator implements Native_Iterator
{
    public const TREAT_NULL_VALUE_AS_EMPTY_CELL = 1;
    public const TREAT_EMPTY_STRING_AS_EMPTY_CELL = 2;
    public const IF_NOT_EXISTS_RETURN_NULL = false;
    public const IF_NOT_EXISTS_CREATE_NEW = true;
    /**
     * Worksheet to iterate.
     */
    protected Worksheet $worksheet;
    /**
     * Cell Collection to iterate.
     */
    protected Cells $cell_collection;
    /**
     * Iterate only existing cells.
     */
    protected bool $only_existing_cells = false;
    /**
     * If iterating all cells, and a cell doesn't exist, identifies whether a new cell should be created,
     *    or if the iterator should return a null value.
     */
    protected bool $if_not_exists = self::IF_NOT_EXISTS_CREATE_NEW;
    /**
     * Destructor.
     */
    public function __destruct()
    {
        unset($this->worksheet, $this->cell_collection);
    }
    public function get_if_not_exists(): bool
    {
        return $this->if_not_exists;
    }
    public function set_if_not_exists(bool $if_not_exists = self::IF_NOT_EXISTS_CREATE_NEW): void
    {
        $this->if_not_exists = $if_not_exists;
    }
    /**
     * Get loop only existing cells.
     */
    public function get_iterate_only_existing_cells(): bool
    {
        return $this->only_existing_cells;
    }
    /**
     * Validate start/end values for 'IterateOnlyExistingCells' mode, and adjust if necessary.
     */
    abstract protected function adjust_for_existing_only_range(): void;
    /**
     * Set the iterator to loop only existing cells.
     */
    public function set_iterate_only_existing_cells(bool $value): void
    {
        $this->only_existing_cells = $value;
        $this->adjust_for_existing_only_range();
    }
}