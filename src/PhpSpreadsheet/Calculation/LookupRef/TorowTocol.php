<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Torow_Tocol
{
    /**
     * Excel function TOCOL.
     *
     * @return mixed[]|string
     */
    public static function tocol(mixed $array, mixed $ignore = 0, mixed $by_column = false): array|string
    {
        $result = self::torow($array, $ignore, $by_column);
        if (is_array($result)) {
            return array_map(fn($x): array => [$x], $result);
        }
        return $result;
    }
    /**
     * Excel function TOROW.
     *
     * @return mixed[]|string
     */
    public static function torow(mixed $array, mixed $ignore = 0, mixed $by_column = false): array|string
    {
        if (!is_numeric($ignore)) {
            return Excel_Error::VALUE();
        }
        $ignore = (int) $ignore;
        if ($ignore < 0 || $ignore > 3) {
            return Excel_Error::VALUE();
        }
        if (is_int($by_column) || is_float($by_column)) {
            $by_column = (bool) $by_column;
        }
        if (!is_bool($by_column)) {
            return Excel_Error::VALUE();
        }
        if (!is_array($array)) {
            $array = [$array];
        }
        if ($by_column) {
            $temp = [];
            foreach ($array as $row) {
                if (!is_array($row)) {
                    $row = [$row];
                }
                $temp[] = Functions::flatten_array($row);
            }
            $array = Choose_Rows_Etc::transpose($temp);
        } else {
            $array = Functions::flatten_array($array);
        }
        return self::by_row($array, $ignore);
    }
    /**
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    private static function by_row(array $array, int $ignore): array
    {
        $return_matrix = [];
        foreach ($array as $row) {
            if (!is_array($row)) {
                $row = [$row];
            }
            foreach ($row as $cell) {
                if ($cell === null) {
                    if ($ignore === 1) {
                        continue;
                    }
                    if ($ignore === 3) {
                        continue;
                    }
                    $cell = 0;
                } elseif (Error_Value::is_error($cell, true)) {
                    if ($ignore === 2) {
                        continue;
                    }
                    if ($ignore === 3) {
                        continue;
                    }
                }
                $return_matrix[] = $cell;
            }
        }
        return $return_matrix;
    }
}