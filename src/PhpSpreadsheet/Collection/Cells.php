<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Collection;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Settings;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Psr\Simple_Cache\Cache_Interface;
class Cells
{
    /** @deprecated 5.6.0 use AddressRange::MAX_COLUMN_INT */
    protected const MAX_COLUMN_ID = Address_Range::MAX_COLUMN_INT;
    private Cache_Interface $cache;
    /**
     * The currently active Cell.
     */
    private ?Cell $current_cell = null;
    /**
     * Coordinate of the currently active Cell.
     */
    private ?string $current_coordinate = null;
    /**
     * Flag indicating whether the currently active Cell requires saving.
     */
    private bool $current_cell_is_dirty = false;
    /**
     * An index of existing cells. int pointer to the coordinate (0-base-indexed row * 16,384 + 1-base indexed column)
     *    indexed by their coordinate.
     *
     * @var int[]
     */
    private array $index = [];
    /**
     * Flag to avoid sorting the index every time.
     */
    private bool $index_sorted = false;
    /**
     * Index keys cache to avoid recalculating on large arrays.
     *
     * @var null|string[]
     */
    private ?array $index_keys_cache = null;
    /**
     * Index values cache to avoid recalculating on large arrays.
     *
     * @var null|int[]
     */
    private ?array $index_values_cache = null;
    /**
     * Prefix used to uniquely identify cache data for this worksheet.
     */
    private string $cache_prefix;
    /**
     * Initialise this new cell collection.
     *
     * @param Worksheet $parent The worksheet for this cell collection
     */
    public function __construct(private ?Worksheet $parent, Cache_Interface $cache)
    {
        $this->cache = $cache;
        $this->cache_prefix = $this->get_unique_id();
    }
    /**
     * Return the parent worksheet for this cell collection.
     */
    public function get_parent(): ?Worksheet
    {
        return $this->parent;
    }
    /**
     * Whether the collection holds a cell for the given coordinate.
     *
     * @param string $cellCoordinate Coordinate of the cell to check
     */
    public function has(string $cell_coordinate): bool
    {
        return $cell_coordinate === $this->current_coordinate || isset($this->index[$cell_coordinate]);
    }
    public function has2(string $cell_coordinate): bool
    {
        return isset($this->index[$cell_coordinate]);
    }
    /**
     * Add or update a cell in the collection.
     *
     * @param Cell $cell Cell to update
     */
    public function update(Cell $cell): Cell
    {
        return $this->add($cell->get_coordinate(), $cell);
    }
    /**
     * Delete a cell in cache identified by coordinate.
     *
     * @param string $cellCoordinate Coordinate of the cell to delete
     */
    public function delete(string $cell_coordinate): void
    {
        if ($cell_coordinate === $this->current_coordinate && $this->current_cell !== null) {
            $this->current_cell->detach();
            $this->current_coordinate = null;
            $this->current_cell = null;
            $this->current_cell_is_dirty = false;
        }
        unset($this->index[$cell_coordinate]);
        // Clear index caches
        $this->index_keys_cache = null;
        $this->index_values_cache = null;
        // Delete the entry from cache
        $this->cache->delete($this->cache_prefix . $cell_coordinate);
    }
    /**
     * Get a list of all cell coordinates currently held in the collection.
     *
     * @return string[]
     */
    public function get_coordinates(): array
    {
        // Build or rebuild index keys cache
        if ($this->index_keys_cache === null) {
            $this->index_keys_cache = array_keys($this->index);
        }
        return $this->index_keys_cache;
    }
    /**
     * Get a sorted list of all cell coordinates currently held in the collection by row and column.
     *
     * @return string[]
     */
    public function get_sorted_coordinates(): array
    {
        // Sort only when required
        if (!$this->index_sorted) {
            asort($this->index);
            $this->index_sorted = true;
            // Clear unsorted cache
            $this->index_keys_cache = null;
            $this->index_values_cache = null;
        }
        // Build or rebuild index keys cache
        if ($this->index_keys_cache === null) {
            $this->index_keys_cache = array_keys($this->index);
        }
        return $this->index_keys_cache;
    }
    /**
     * Get a sorted list of all cell coordinates currently held in the collection by index (16384*row+column).
     *
     * @return int[]
     */
    public function get_sorted_coordinates_int(): array
    {
        if (!$this->index_sorted) {
            asort($this->index);
            $this->index_sorted = true;
            // Clear unsorted cache
            $this->index_keys_cache = null;
            $this->index_values_cache = null;
        }
        if ($this->index_values_cache === null) {
            $this->index_values_cache = array_values($this->index);
        }
        return $this->index_values_cache;
    }
    /**
     * Return the cell coordinate of the currently active cell object.
     */
    public function get_current_coordinate(): ?string
    {
        return $this->current_coordinate;
    }
    /**
     * Return the column coordinate of the currently active cell object.
     */
    public function get_current_column(): string
    {
        $column = 0;
        $row = '';
        sscanf($this->current_coordinate ?? '', '%[A-Z]%d', $column, $row);
        return (string) $column;
    }
    /**
     * Return the row coordinate of the currently active cell object.
     */
    public function get_current_row(): int
    {
        $column = 0;
        $row = '';
        sscanf($this->current_coordinate ?? '', '%[A-Z]%d', $column, $row);
        return (int) $row;
    }
    /**
     * Get highest worksheet column and highest row that have cell records.
     *
     * @return array{row: int, column: string} Highest column name and highest row number
     */
    public function get_highest_row_and_column(): array
    {
        // Lookup highest column and highest row
        $max_row = $max_column = 1;
        foreach ($this->index as $coordinate) {
            $row = (int) floor(($coordinate - 1) / Address_Range::MAX_COLUMN_INT) + 1;
            $max_row = $max_row > $row ? $max_row : $row;
            $column = $coordinate % Address_Range::MAX_COLUMN_INT ?: Address_Range::MAX_COLUMN_INT;
            $max_column = $max_column > $column ? $max_column : $column;
        }
        return ['row' => $max_row, 'column' => Coordinate::string_from_column_index($max_column)];
    }
    /**
     * Get highest worksheet column.
     *
     * @param null|int|string $row Return the highest column for the specified row,
     *                    or the highest column of any row if no row number is passed
     *
     * @return string Highest column name
     */
    public function get_highest_column($row = null): string
    {
        if ($row === null) {
            return $this->get_highest_row_and_column()['column'];
        }
        $row = (int) $row;
        if ($row <= 0) {
            throw new Php_Spreadsheet_Exception('Row number must be a positive integer');
        }
        $max_column = 1;
        $to_row = $row * Address_Range::MAX_COLUMN_INT;
        $from_row = --$row * Address_Range::MAX_COLUMN_INT;
        foreach ($this->index as $coordinate) {
            if ($coordinate < $from_row) {
                continue;
            }
            if ($coordinate >= $to_row) {
                continue;
            }
            $column = $coordinate % Address_Range::MAX_COLUMN_INT ?: Address_Range::MAX_COLUMN_INT;
            $max_column = max($column, $max_column);
        }
        return Coordinate::string_from_column_index($max_column);
    }
    /**
     * Get highest worksheet row.
     *
     * @param null|string $column Return the highest row for the specified column,
     *                       or the highest row of any column if no column letter is passed
     *
     * @return int Highest row number
     */
    public function get_highest_row(?string $column = null): int
    {
        if ($column === null) {
            return $this->get_highest_row_and_column()['row'];
        }
        $max_row = 1;
        $column_index = Coordinate::column_index_from_string($column);
        foreach ($this->index as $coordinate) {
            if ($coordinate % Address_Range::MAX_COLUMN_INT !== $column_index) {
                continue;
            }
            $row = (int) floor($coordinate / Address_Range::MAX_COLUMN_INT) + 1;
            $max_row = $max_row > $row ? $max_row : $row;
        }
        return $max_row;
    }
    /**
     * Generate a unique ID for cache referencing.
     *
     * @return string Unique Reference
     */
    private function get_unique_id(): string
    {
        $cache_type = Settings::get_cache();
        return $cache_type instanceof Memory\Simple_Cache1 || $cache_type instanceof Memory\Simple_Cache3 ? random_bytes(7) . ':' : uniqid('phpspreadsheet.', true) . '.';
    }
    /**
     * Clone the cell collection.
     */
    public function clone_cell_collection(Worksheet $worksheet): static
    {
        $this->store_current_cell();
        $new_collection = clone $this;
        $new_collection->parent = $worksheet;
        $new_collection->cache_prefix = $new_collection->get_unique_id();
        foreach ($this->index as $key => $value) {
            $new_collection->index[$key] = $value;
            $stored = $new_collection->cache->set($new_collection->cache_prefix . $key, clone $this->get_cache($key));
            if ($stored === false) {
                $this->destruct_if_needed($new_collection, 'Failed to copy cells in cache');
            }
        }
        // Clear index sorted flag and index caches
        $new_collection->index_sorted = false;
        $new_collection->index_keys_cache = null;
        $new_collection->index_values_cache = null;
        return $new_collection;
    }
    /**
     * Remove a row, deleting all cells in that row.
     *
     * @param int|string $row Row number to remove
     */
    public function remove_row($row): void
    {
        $this->store_current_cell();
        $row = (int) $row;
        if ($row <= 0) {
            throw new Php_Spreadsheet_Exception('Row number must be a positive integer');
        }
        $to_row = $row * Address_Range::MAX_COLUMN_INT;
        $from_row = --$row * Address_Range::MAX_COLUMN_INT;
        foreach ($this->index as $coordinate) {
            if ($coordinate >= $from_row && $coordinate < $to_row) {
                $row = (int) floor($coordinate / Address_Range::MAX_COLUMN_INT) + 1;
                $column = Coordinate::string_from_column_index($coordinate % Address_Range::MAX_COLUMN_INT);
                $this->delete("{$column}{$row}");
            }
        }
    }
    /**
     * Remove a column, deleting all cells in that column.
     *
     * @param string $column Column ID to remove
     */
    public function remove_column(string $column): void
    {
        $this->store_current_cell();
        $column_index = Coordinate::column_index_from_string($column);
        foreach ($this->index as $coordinate) {
            if ($coordinate % Address_Range::MAX_COLUMN_INT === $column_index) {
                $row = (int) floor($coordinate / Address_Range::MAX_COLUMN_INT) + 1;
                $column = Coordinate::string_from_column_index($coordinate % Address_Range::MAX_COLUMN_INT);
                $this->delete("{$column}{$row}");
            }
        }
    }
    /**
     * Store cell data in cache for the current cell object if it's "dirty",
     * and the 'nullify' the current cell object.
     */
    private function store_current_cell(): void
    {
        if ($this->current_cell_is_dirty && isset($this->current_coordinate, $this->current_cell)) {
            $this->current_cell->detach();
            $stored = $this->cache->set($this->cache_prefix . $this->current_coordinate, $this->current_cell);
            if ($stored === false) {
                $this->destruct_if_needed($this, "Failed to store cell {$this->current_coordinate} in cache");
            }
            $this->current_cell_is_dirty = false;
        }
        $this->current_coordinate = null;
        $this->current_cell = null;
    }
    private function destruct_if_needed(self $cells, string $message): void
    {
        $cells->__destruct();
        throw new Php_Spreadsheet_Exception($message);
    }
    /**
     * Add or update a cell identified by its coordinate into the collection.
     *
     * @param string $cellCoordinate Coordinate of the cell to update
     * @param Cell $cell Cell to update
     */
    public function add(string $cell_coordinate, Cell $cell): Cell
    {
        if ($cell_coordinate !== $this->current_coordinate) {
            $this->store_current_cell();
        }
        $column = 0;
        $row = '';
        sscanf($cell_coordinate, '%[A-Z]%d', $column, $row);
        /** @var int $row */
        $this->index[$cell_coordinate] = --$row * Address_Range::MAX_COLUMN_INT + Coordinate::column_index_from_string((string) $column);
        // Clear index sorted flag and index caches
        $this->index_sorted = false;
        $this->index_keys_cache = null;
        $this->index_values_cache = null;
        $this->current_coordinate = $cell_coordinate;
        $this->current_cell = $cell;
        $this->current_cell_is_dirty = true;
        return $cell;
    }
    /**
     * Get cell at a specific coordinate.
     *
     * @param string $cellCoordinate Coordinate of the cell
     *
     * @return null|Cell Cell that was found, or null if not found
     */
    public function get(string $cell_coordinate): ?Cell
    {
        if ($cell_coordinate === $this->current_coordinate) {
            return $this->current_cell;
        }
        $this->store_current_cell();
        // Return null if requested entry doesn't exist in collection
        if ($this->has($cell_coordinate) === false) {
            return null;
        }
        $cell = $this->getcache($cell_coordinate);
        // Set current entry to the requested entry
        $this->current_coordinate = $cell_coordinate;
        $this->current_cell = $cell;
        // Re-attach this as the cell's parent
        $this->current_cell->attach($this);
        // Return requested entry
        return $this->current_cell;
    }
    /**
     * Clear the cell collection and disconnect from our parent.
     */
    public function unset_worksheet_cells(): void
    {
        if ($this->current_cell !== null) {
            $this->current_cell->detach();
            $this->current_cell = null;
            $this->current_coordinate = null;
        }
        // Flush the cache
        $this->__destruct();
        $this->index = [];
        // Clear index sorted flag and index caches
        $this->index_sorted = false;
        $this->index_keys_cache = null;
        $this->index_values_cache = null;
        // detach ourself from the worksheet, so that it can then delete this object successfully
        $this->parent = null;
    }
    /**
     * Destroy this cell collection.
     */
    public function __destruct()
    {
        $this->cache->delete_multiple($this->get_all_cache_keys());
        $this->parent = null;
    }
    /**
     * Returns all known cache keys.
     *
     * @return iterable<string>
     */
    private function get_all_cache_keys(): iterable
    {
        foreach ($this->index as $coordinate => $value) {
            yield $this->cache_prefix . $coordinate;
        }
    }
    private function get_cache(string $cell_coordinate): Cell
    {
        $cell = $this->cache->get($this->cache_prefix . $cell_coordinate);
        if (!$cell instanceof Cell) {
            throw new Php_Spreadsheet_Exception("Cell entry {$cell_coordinate} no longer exists in cache. This probably means that the cache was cleared by someone else.");
        }
        return $cell;
    }
}