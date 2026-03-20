<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

/**
 * @template T of IComparable
 */
class Hash_Table
{
    /**
     * HashTable elements.
     *
     * @var array<string, T>
     */
    protected array $items = [];
    /**
     * HashTable key map.
     *
     * @var array<int, string>
     */
    protected array $key_map = [];
    /**
     * Create a new HashTable.
     *
     * @param T[] $source Optional source array to create HashTable from
     */
    public function __construct(?array $source = [])
    {
        if ($source !== null) {
            // Create HashTable
            $this->add_from_source($source);
        }
    }
    /**
     * Add HashTable items from source.
     *
     * @param T[] $source Source array to create HashTable from
     */
    public function add_from_source(?array $source = null): void
    {
        // Check if an array was passed
        if ($source === null) {
            return;
        }
        foreach ($source as $item) {
            $this->add($item);
        }
    }
    /**
     * Add HashTable item.
     *
     * @param T $source Item to add
     */
    public function add(I_Comparable $source): void
    {
        $hash = $source->get_hash_code();
        if (!isset($this->items[$hash])) {
            $this->items[$hash] = $source;
            $this->key_map[count($this->items) - 1] = $hash;
        }
    }
    /**
     * Remove HashTable item.
     *
     * @param T $source Item to remove
     */
    public function remove(I_Comparable $source): void
    {
        $hash = $source->get_hash_code();
        if (isset($this->items[$hash])) {
            unset($this->items[$hash]);
            $delete_key = -1;
            foreach ($this->key_map as $key => $value) {
                if ($delete_key >= 0) {
                    $this->key_map[$key - 1] = $value;
                }
                if ($value == $hash) {
                    $delete_key = $key;
                }
            }
            unset($this->key_map[count($this->key_map) - 1]);
        }
    }
    /**
     * Clear HashTable.
     */
    public function clear(): void
    {
        $this->items = [];
        $this->key_map = [];
    }
    /**
     * Count.
     */
    public function count(): int
    {
        return count($this->items);
    }
    /**
     * Get index for hash code.
     */
    public function get_index_for_hash_code(string $hash_code): false|int
    {
        return array_search($hash_code, $this->key_map, true);
    }
    /**
     * Get by index.
     *
     * @return null|T
     */
    public function get_by_index(int $index): ?I_Comparable
    {
        if (isset($this->key_map[$index])) {
            return $this->get_by_hash_code($this->key_map[$index]);
        }
        return null;
    }
    /**
     * Get by hashcode.
     *
     * @return null|T
     */
    public function get_by_hash_code(string $hash_code): ?I_Comparable
    {
        return $this->items[$hash_code] ?? null;
    }
    /**
     * HashTable to array.
     *
     * @return T[]
     */
    public function to_array(): array
    {
        return $this->items;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            // each member of this class is an array
            if (is_array($value)) {
                $array1 = $value;
                foreach ($array1 as $key1 => $value1) {
                    if (is_object($value1)) {
                        $array1[$key1] = clone $value1;
                    }
                }
                $this->{$key} = $array1;
            }
        }
    }
}