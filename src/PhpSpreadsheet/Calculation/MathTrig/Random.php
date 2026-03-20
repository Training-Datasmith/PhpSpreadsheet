<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Random
{
    use Array_Enabled;
    /**
     * RAND.
     *
     * @return float|int Random number
     */
    public static function rand(): int|float
    {
        return mt_rand(0, 10000000) / 10000000;
    }
    /**
     * RANDBETWEEN.
     *
     * @param mixed $min Minimal value
     *                      Or can be an array of values
     * @param mixed $max Maximal value
     *                      Or can be an array of values
     *
     * @return array<mixed>|int|string Random number
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function rand_between(mixed $min, mixed $max): array|string|int
    {
        if (is_array($min) || is_array($max)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $min, $max);
        }
        try {
            $min = (int) Helpers::validate_numeric_null_bool($min);
            $max = (int) Helpers::validate_numeric_null_bool($max);
            Helpers::validate_not_negative($max - $min);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return mt_rand($min, $max);
    }
    /**
     * RANDARRAY.
     *
     * Generates a list of sequential numbers in an array.
     *
     * Excel Function:
     *      RANDARRAY([rows],[columns],[start],[step])
     *
     * @param mixed $rows the number of rows to return, defaults to 1
     * @param mixed $columns the number of columns to return, defaults to 1
     * @param mixed $min the minimum number to be returned, defaults to 0
     * @param mixed $max the maximum number to be returned, defaults to 1
     * @param bool $wholeNumber the type of numbers to return:
     *                             False - Decimal numbers to 15 decimal places. (default)
     *                             True - Whole (integer) numbers
     *
     * @return array<mixed>|string The resulting array, or a string containing an error
     */
    public static function rand_array(mixed $rows = 1, mixed $columns = 1, mixed $min = 0, mixed $max = 1, bool $whole_number = false): string|array
    {
        try {
            $rows = (int) Helpers::validate_numeric_null_substitution($rows, 1);
            Helpers::validate_positive($rows);
            $columns = (int) Helpers::validate_numeric_null_substitution($columns, 1);
            Helpers::validate_positive($columns);
            $min = Helpers::validate_numeric_null_substitution($min, 1);
            $max = Helpers::validate_numeric_null_substitution($max, 1);
            if ($max <= $min) {
                return Excel_Error::VALUE();
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return array_chunk(array_map(fn(): int|float => $whole_number ? mt_rand((int) $min, (int) $max) : mt_rand() / mt_getrandmax() * ($max - $min) + $min, array_fill(0, $rows * $columns, $min)), max($columns, 1));
    }
}