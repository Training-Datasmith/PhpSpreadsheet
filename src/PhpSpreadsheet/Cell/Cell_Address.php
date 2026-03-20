<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Stringable;
class Cell_Address implements Stringable
{
    protected string $cell_address;
    protected string $column_name = '';
    protected int $column_id;
    protected int $row_id;
    public function __construct(string $cell_address, protected ?Worksheet $worksheet = null)
    {
        $this->cell_address = str_replace('$', '', $cell_address);
        [$this->column_id, $this->row_id, $this->column_name] = Coordinate::indexes_from_string($this->cell_address);
    }
    public function __destruct()
    {
        unset($this->worksheet);
    }
    /**
     * @phpstan-assert int|numeric-string $columnId
     * @phpstan-assert int|numeric-string $rowId
     */
    private static function validate_column_and_row(int|string $column_id, int|string $row_id): void
    {
        if (!is_numeric($column_id) || $column_id <= 0 || !is_numeric($row_id) || $row_id <= 0) {
            throw new Exception('Row and Column Ids must be positive integer values');
        }
    }
    public static function from_column_and_row(int|string $column_id, int|string $row_id, ?Worksheet $worksheet = null): self
    {
        self::validate_column_and_row($column_id, $row_id);
        return new self(Coordinate::string_from_column_index($column_id) . $row_id, $worksheet);
    }
    /** @param array<int, int> $array */
    public static function from_column_row_array(array $array, ?Worksheet $worksheet = null): self
    {
        [$column_id, $row_id] = $array;
        return self::from_column_and_row($column_id, $row_id, $worksheet);
    }
    public static function from_cell_address(string $cell_address, ?Worksheet $worksheet = null): self
    {
        return new self($cell_address, $worksheet);
    }
    /**
     * The returned address string will contain the worksheet name as well, if available,
     *     (ie. if a Worksheet was provided to the constructor).
     *     e.g. "'Mark''s Worksheet'!C5".
     */
    public function full_cell_address(): string
    {
        if ($this->worksheet !== null) {
            $title = str_replace("'", "''", $this->worksheet->get_title());
            return "'{$title}'!{$this->cell_address}";
        }
        return $this->cell_address;
    }
    public function worksheet(): ?Worksheet
    {
        return $this->worksheet;
    }
    /**
     * The returned address string will contain just the column/row address,
     *     (even if a Worksheet was provided to the constructor).
     *     e.g. "C5".
     */
    public function cell_address(): string
    {
        return $this->cell_address;
    }
    public function row_id(): int
    {
        return $this->row_id;
    }
    public function column_id(): int
    {
        return $this->column_id;
    }
    public function column_name(): string
    {
        return $this->column_name;
    }
    public function next_row(int $offset = 1): self
    {
        $new_row_id = $this->row_id + $offset;
        if ($new_row_id < 1) {
            $new_row_id = 1;
        }
        return self::from_column_and_row($this->column_id, $new_row_id);
    }
    public function previous_row(int $offset = 1): self
    {
        return $this->next_row(-$offset);
    }
    public function next_column(int $offset = 1): self
    {
        $new_column_id = $this->column_id + $offset;
        if ($new_column_id < 1) {
            $new_column_id = 1;
        }
        return self::from_column_and_row($new_column_id, $this->row_id);
    }
    public function previous_column(int $offset = 1): self
    {
        return $this->next_column(-$offset);
    }
    /**
     * The returned address string will contain the worksheet name as well, if available,
     *     (ie. if a Worksheet was provided to the constructor).
     *     e.g. "'Mark''s Worksheet'!C5".
     */
    public function __toString(): string
    {
        return $this->full_cell_address();
    }
}