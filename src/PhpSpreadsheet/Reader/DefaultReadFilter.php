<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

class Default_Read_Filter implements I_Read_Filter
{
    /**
     * Should this cell be read?
     *
     * @param string $columnAddress Column address (as a string value like "A", or "IV")
     * @param int $row Row number
     * @param string $worksheetName Optional worksheet name
     */
    public function read_cell(string $column_address, int $row, string $worksheet_name = ''): bool
    {
        return true;
    }
}