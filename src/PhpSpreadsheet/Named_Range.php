<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Named_Range extends Defined_Name
{
    /**
     * Create a new Named Range.
     */
    public function __construct(string $name, ?Worksheet $worksheet = null, string $range = 'A1', bool $local_only = false, ?Worksheet $scope = null)
    {
        if ($worksheet === null && $scope === null) {
            throw new Exception('You must specify a worksheet or a scope for a Named Range');
        }
        parent::__construct($name, $worksheet, $range, $local_only, $scope);
    }
    /**
     * Get the range value.
     */
    public function get_range(): string
    {
        return $this->value;
    }
    /**
     * Set the range value.
     */
    public function set_range(string $range): self
    {
        if (!empty($range)) {
            $this->value = $range;
        }
        return $this;
    }
    /** @return string[] */
    public function get_cells_in_range(): array
    {
        $range = $this->value;
        if (str_starts_with($range, '=')) {
            $range = substr($range, 1);
        }
        return Coordinate::extract_all_cell_references_in_range($range);
    }
}