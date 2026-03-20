<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Sum_Squares
{
    /**
     * SUMSQ.
     *
     * SUMSQ returns the sum of the squares of the arguments
     *
     * Excel Function:
     *        SUMSQ(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function sum_square(mixed ...$args): string|int|float
    {
        try {
            $return_value = 0;
            // Loop through arguments
            foreach (Functions::flatten_array($args) as $arg) {
                $arg1 = Helpers::validate_numeric_null_substitution($arg, 0);
                $return_value += $arg1 * $arg1;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $return_value;
    }
    /**
     * @param mixed[] $array1
     * @param mixed[] $array2
     */
    private static function get_count(array $array1, array $array2): int
    {
        $count = count($array1);
        if ($count !== count($array2)) {
            throw new Exception(Excel_Error::NA());
        }
        return $count;
    }
    /**
     * These functions accept only numeric arguments, not even strings which are numeric.
     */
    private static function numeric_not_string(mixed $item): bool
    {
        return is_numeric($item) && !is_string($item);
    }
    /**
     * SUMX2MY2.
     *
     * @param mixed[] $matrixData1 Matrix #1
     * @param mixed[] $matrixData2 Matrix #2
     */
    public static function sum_x_squared_minus_y_squared(array $matrix_data1, array $matrix_data2): string|int|float
    {
        try {
            /** @var array<float|int> */
            $array1 = Functions::flatten_array($matrix_data1);
            /** @var array<float|int> */
            $array2 = Functions::flatten_array($matrix_data2);
            $count = self::get_count($array1, $array2);
            $result = 0;
            for ($i = 0; $i < $count; ++$i) {
                if (self::numeric_not_string($array1[$i]) && self::numeric_not_string($array2[$i])) {
                    $result += $array1[$i] * $array1[$i] - $array2[$i] * $array2[$i];
                }
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $result;
    }
    /**
     * SUMX2PY2.
     *
     * @param mixed[] $matrixData1 Matrix #1
     * @param mixed[] $matrixData2 Matrix #2
     */
    public static function sum_x_squared_plus_y_squared(array $matrix_data1, array $matrix_data2): string|int|float
    {
        try {
            /** @var array<float|int> */
            $array1 = Functions::flatten_array($matrix_data1);
            /** @var array<float|int> */
            $array2 = Functions::flatten_array($matrix_data2);
            $count = self::get_count($array1, $array2);
            $result = 0;
            for ($i = 0; $i < $count; ++$i) {
                if (self::numeric_not_string($array1[$i]) && self::numeric_not_string($array2[$i])) {
                    $result += $array1[$i] * $array1[$i] + $array2[$i] * $array2[$i];
                }
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $result;
    }
    /**
     * SUMXMY2.
     *
     * @param mixed[] $matrixData1 Matrix #1
     * @param mixed[] $matrixData2 Matrix #2
     */
    public static function sum_x_minus_y_squared(array $matrix_data1, array $matrix_data2): string|int|float
    {
        try {
            /** @var array<float|int> */
            $array1 = Functions::flatten_array($matrix_data1);
            /** @var array<float|int> */
            $array2 = Functions::flatten_array($matrix_data2);
            $count = self::get_count($array1, $array2);
            $result = 0;
            for ($i = 0; $i < $count; ++$i) {
                if (self::numeric_not_string($array1[$i]) && self::numeric_not_string($array2[$i])) {
                    $result += ($array1[$i] - $array2[$i]) * ($array1[$i] - $array2[$i]);
                }
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $result;
    }
}