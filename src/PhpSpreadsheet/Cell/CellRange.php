<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Stringable;
/**
 * @implements AddressRange<CellAddress>
 */
class Cell_Range implements Address_Range, Stringable
{
    protected Cell_Address $from;
    protected Cell_Address $to;
    public function __construct(Cell_Address $from, Cell_Address $to)
    {
        $this->validate_from_to($from, $to);
    }
    private function validate_from_to(Cell_Address $from, Cell_Address $to): void
    {
        // Identify actual top-left and bottom-right values (in case we've been given top-right and bottom-left)
        $first_column = min($from->column_id(), $to->column_id());
        $first_row = min($from->row_id(), $to->row_id());
        $last_column = max($from->column_id(), $to->column_id());
        $last_row = max($from->row_id(), $to->row_id());
        $from_worksheet = $from->worksheet();
        $to_worksheet = $to->worksheet();
        $this->validate_worksheets($from_worksheet, $to_worksheet);
        $this->from = $this->cell_address_wrapper($first_column, $first_row, $from_worksheet);
        $this->to = $this->cell_address_wrapper($last_column, $last_row, $to_worksheet);
    }
    private function validate_worksheets(?Worksheet $from_worksheet, ?Worksheet $to_worksheet): void
    {
        if ($from_worksheet !== null && $to_worksheet !== null) {
            // We could simply compare worksheets rather than worksheet titles; but at some point we may introduce
            //    support for 3d ranges; and at that point we drop this check and let the validation fall through
            //    to the check for same workbook; but unless we check on titles, this test will also detect if the
            //    worksheets are in different spreadsheets, and the next check will never execute or throw its
            //    own exception.
            if ($from_worksheet->get_title() !== $to_worksheet->get_title()) {
                throw new Exception('3d Cell Ranges are not supported');
            }
            if ($from_worksheet->get_parent() !== $to_worksheet->get_parent()) {
                throw new Exception('Worksheets must be in the same spreadsheet');
            }
        }
    }
    private function cell_address_wrapper(int $column, int $row, ?Worksheet $worksheet = null): Cell_Address
    {
        $cell_address = Coordinate::string_from_column_index($column) . $row;
        return new class($cell_address, $worksheet) extends Cell_Address
        {
            public function next_row(int $offset = 1): \Anonymous_Classeb686a09e052390a23ac77e0ec4936c8
            {
                $result = parent::next_row($offset);
                $this->row_id = $result->row_id;
                $this->cell_address = $result->cell_address;
                return $this;
            }
            public function previous_row(int $offset = 1): \Anonymous_Classeb686a09e052390a23ac77e0ec4936c8
            {
                $result = parent::previous_row($offset);
                $this->row_id = $result->row_id;
                $this->cell_address = $result->cell_address;
                return $this;
            }
            public function next_column(int $offset = 1): \Anonymous_Classeb686a09e052390a23ac77e0ec4936c8
            {
                $result = parent::next_column($offset);
                $this->column_id = $result->column_id;
                $this->column_name = $result->column_name;
                $this->cell_address = $result->cell_address;
                return $this;
            }
            public function previous_column(int $offset = 1): \Anonymous_Classeb686a09e052390a23ac77e0ec4936c8
            {
                $result = parent::previous_column($offset);
                $this->column_id = $result->column_id;
                $this->column_name = $result->column_name;
                $this->cell_address = $result->cell_address;
                return $this;
            }
        };
    }
    public function from(): Cell_Address
    {
        // Re-order from/to in case the cell addresses have been modified
        $this->validate_from_to($this->from, $this->to);
        return $this->from;
    }
    public function to(): Cell_Address
    {
        // Re-order from/to in case the cell addresses have been modified
        $this->validate_from_to($this->from, $this->to);
        return $this->to;
    }
    public function __toString(): string
    {
        // Re-order from/to in case the cell addresses have been modified
        $this->validate_from_to($this->from, $this->to);
        if ($this->from->cell_address() === $this->to->cell_address()) {
            return "{$this->from->full_cell_address()}";
        }
        $from_address = $this->from->full_cell_address();
        $to_address = $this->to->cell_address();
        return "{$from_address}:{$to_address}";
    }
}