<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
class Page_Break
{
    private readonly string $coordinate;
    /**
     * @param array{0: int, 1: int}|CellAddress|string $coordinate
     */
    public function __construct(private readonly int $break_type, Cell_Address|string|array $coordinate, private readonly int $max_col_or_row = -1)
    {
        $coordinate = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate));
        $this->coordinate = $coordinate;
    }
    public function get_break_type(): int
    {
        return $this->break_type;
    }
    public function get_coordinate(): string
    {
        return $this->coordinate;
    }
    public function get_max_col_or_row(): int
    {
        return $this->max_col_or_row;
    }
    public function get_column_int(): int
    {
        return Coordinate::indexes_from_string($this->coordinate)[0];
    }
    public function get_row(): int
    {
        return Coordinate::indexes_from_string($this->coordinate)[1];
    }
    public function get_column_string(): string
    {
        return Coordinate::indexes_from_string($this->coordinate)[2];
    }
}