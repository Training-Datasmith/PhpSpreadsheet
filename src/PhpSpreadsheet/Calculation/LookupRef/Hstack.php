<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Hstack
{
    /**
     * Excel function HSTACK.
     *
     * @return mixed[]|string
     */
    public static function hstack(mixed ...$input_data): array|string
    {
        $max_row = 0;
        foreach ($input_data as $matrix) {
            if (!is_array($matrix)) {
                $count = 1;
            } else {
                $count = count($matrix);
            }
            $max_row = max($max_row, $count);
        }
        /** @var mixed[] $inputData */
        foreach ($input_data as &$matrix) {
            if (!is_array($matrix)) {
                $matrix = [$matrix];
            }
            $rows = count($matrix);
            $reset = reset($matrix);
            $columns = is_array($reset) ? count($reset) : 1;
            while ($max_row > $rows) {
                $matrix[] = array_pad([], $columns, Excel_Error::NA());
                ++$rows;
            }
        }
        $transpose = array_map(null, ...$input_data);
        //* @phpstan-ignore-line
        $return_matrix = [];
        foreach ($transpose as $array) {
            $return_matrix[] = Functions::flatten_array($array);
        }
        return $return_matrix;
    }
}