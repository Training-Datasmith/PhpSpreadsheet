<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Stringable;
/**
 * @implements AddressRange<int>
 */
class Row_Range implements Address_Range, Stringable
{
    protected int $from;
    protected int $to;
    public function __construct(int $from, ?int $to = null, protected ?Worksheet $worksheet = null)
    {
        $this->validate_from_to($from, $to ?? $from);
    }
    public function __destruct()
    {
        $this->worksheet = null;
    }
    /** @param array{int, int} $array */
    public static function from_array(array $array, ?Worksheet $worksheet = null): self
    {
        [$from, $to] = $array;
        return new self($from, $to, $worksheet);
    }
    private function validate_from_to(int $from, int $to): void
    {
        // Identify actual top and bottom values (in case we've been given bottom and top)
        $this->from = min($from, $to);
        $this->to = max($from, $to);
    }
    public function from(): int
    {
        return $this->from;
    }
    public function to(): int
    {
        return $this->to;
    }
    public function row_count(): int
    {
        return $this->to - $this->from + 1;
    }
    public function shift_right(int $offset = 1): self
    {
        $new_from = $this->from + $offset;
        $new_from = $new_from < 1 ? 1 : $new_from;
        $new_to = $this->to + $offset;
        $new_to = $new_to < 1 ? 1 : $new_to;
        return new self($new_from, $new_to, $this->worksheet);
    }
    public function shift_left(int $offset = 1): self
    {
        return $this->shift_right(-$offset);
    }
    public function to_cell_range(): Cell_Range
    {
        return new Cell_Range(Cell_Address::from_column_and_row(Coordinate::column_index_from_string('A'), $this->from, $this->worksheet), Cell_Address::from_column_and_row(Coordinate::column_index_from_string(Address_Range::MAX_COLUMN), $this->to));
    }
    public function __toString(): string
    {
        if ($this->worksheet !== null) {
            $title = str_replace("'", "''", $this->worksheet->get_title());
            return "'{$title}'!{$this->from}:{$this->to}";
        }
        return "{$this->from}:{$this->to}";
    }
}