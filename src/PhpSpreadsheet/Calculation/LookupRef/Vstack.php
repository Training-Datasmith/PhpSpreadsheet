<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Vstack
{
    /**
     * Excel function VSTACK.
     *
     * @return mixed[]
     */
    public static function vstack(mixed ...$input_data): array|string
    {
        $return_matrix = [];
        $columns = 0;
        foreach ($input_data as $matrix) {
            if (!is_array($matrix)) {
                $count = 1;
            } else {
                $count = count(reset($matrix));
                //* @phpstan-ignore-line
            }
            $columns = max($columns, $count);
        }
        foreach ($input_data as $matrix) {
            if (!is_array($matrix)) {
                $matrix = [$matrix];
            }
            foreach ($matrix as $row) {
                if (!is_array($row)) {
                    $row = [$row];
                }
                $return_matrix[] = array_values(array_pad($row, $columns, Excel_Error::NA()));
            }
        }
        return $return_matrix;
    }
}