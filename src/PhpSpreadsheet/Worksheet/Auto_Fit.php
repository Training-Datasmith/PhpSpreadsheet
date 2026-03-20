<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Cell_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
class Auto_Fit
{
    public function __construct(protected Worksheet $worksheet)
    {
    }
    /** @return mixed[] */
    public function get_auto_filter_indent_ranges(): array
    {
        $auto_filter_indent_ranges = [];
        $auto_filter_indent_ranges[] = $this->get_auto_filter_indent_range($this->worksheet->get_auto_filter());
        foreach ($this->worksheet->get_table_collection() as $table) {
            if ($table->get_show_header_row() === true && $table->get_allow_filter() === true) {
                $auto_filter = $table->get_auto_filter();
                $auto_filter_indent_ranges[] = $this->get_auto_filter_indent_range($auto_filter);
            }
        }
        return array_filter($auto_filter_indent_ranges);
    }
    private function get_auto_filter_indent_range(Auto_Filter $auto_filter): ?string
    {
        $auto_filter_range = $auto_filter->get_range();
        $auto_filter_indent_range = null;
        if (!empty($auto_filter_range)) {
            $auto_filter_range_boundaries = Coordinate::range_boundaries($auto_filter_range);
            $auto_filter_indent_range = (string) new Cell_Range(Cell_Address::from_column_and_row($auto_filter_range_boundaries[0][0], $auto_filter_range_boundaries[0][1]), Cell_Address::from_column_and_row($auto_filter_range_boundaries[1][0], $auto_filter_range_boundaries[0][1]));
        }
        return $auto_filter_indent_range;
    }
}