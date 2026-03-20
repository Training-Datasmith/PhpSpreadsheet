<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Stringable;
/**
 * @implements AddressRange<string>
 */
class Column_Range implements Address_Range, Stringable
{
    protected int $from;
    protected int $to;
    public function __construct(string $from, ?string $to = null, protected ?Worksheet $worksheet = null)
    {
        $this->validate_from_to(Coordinate::column_index_from_string($from), Coordinate::column_index_from_string($to ?? $from));
    }
    public function __destruct()
    {
        $this->worksheet = null;
    }
    public static function from_column_indexes(int $from, int $to, ?Worksheet $worksheet = null): self
    {
        return new self(Coordinate::string_from_column_index($from), Coordinate::string_from_column_index($to), $worksheet);
    }
    /**
     * @param array<int|string> $array
     */
    public static function from_array(array $array, ?Worksheet $worksheet = null): self
    {
        array_walk($array, function (int|string &$column): void {
            $column = is_numeric($column) ? Coordinate::string_from_column_index((int) $column) : $column;
        });
        /** @var string $from */
        /** @var string $to */
        [$from, $to] = $array;
        return new self($from, $to, $worksheet);
    }
    private function validate_from_to(int $from, int $to): void
    {
        // Identify actual top and bottom values (in case we've been given bottom and top)
        $this->from = min($from, $to);
        $this->to = max($from, $to);
    }
    public function column_count(): int
    {
        return $this->to - $this->from + 1;
    }
    public function shift_down(int $offset = 1): self
    {
        $new_from = $this->from + $offset;
        $new_from = $new_from < 1 ? 1 : $new_from;
        $new_to = $this->to + $offset;
        $new_to = $new_to < 1 ? 1 : $new_to;
        return self::from_column_indexes($new_from, $new_to, $this->worksheet);
    }
    public function shift_up(int $offset = 1): self
    {
        return $this->shift_down(-$offset);
    }
    public function from(): string
    {
        return Coordinate::string_from_column_index($this->from);
    }
    public function to(): string
    {
        return Coordinate::string_from_column_index($this->to);
    }
    public function from_index(): int
    {
        return $this->from;
    }
    public function to_index(): int
    {
        return $this->to;
    }
    public function to_cell_range(): Cell_Range
    {
        return new Cell_Range(Cell_Address::from_column_and_row($this->from, 1, $this->worksheet), Cell_Address::from_column_and_row($this->to, Address_Range::MAX_ROW));
    }
    public function __toString(): string
    {
        $from = $this->from();
        $to = $this->to();
        if ($this->worksheet !== null) {
            $title = str_replace("'", "''", $this->worksheet->get_title());
            return "'{$title}'!{$from}:{$to}";
        }
        return "{$from}:{$to}";
    }
}