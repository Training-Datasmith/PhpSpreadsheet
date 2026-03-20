<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Choose_Rows_Etc
{
    /**
     * Transpose 2-dimensional array.
     * See https://stackoverflow.com/questions/797251/transposing-multidimensional-arrays-in-php
     * especially the comment from user17994717.
     *
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    public static function transpose(array $array): array
    {
        return empty($array) ? [] : array_map(count($array) === 1 ? fn($x): array => [$x] : null, ...$array);
        // @phpstan-ignore-line
    }
    /** @return mixed[] */
    private static function array_values(mixed $array): array
    {
        return is_array($array) ? array_values($array) : [$array];
    }
    /**
     * CHOOSECOLS.
     *
     * @param mixed $input expecting two-dimensional array
     *
     * @return mixed[]|string
     */
    public static function choose_cols(mixed $input, mixed ...$args): array|string
    {
        if (!is_array($input)) {
            $input = [[$input]];
        }
        $retval = self::choose_rows(self::transpose($input), ...$args);
        return is_array($retval) ? self::transpose($retval) : $retval;
    }
    /**
     * CHOOSEROWS.
     *
     * @param mixed $input expecting two-dimensional array
     *
     * @return mixed[]|string
     */
    public static function choose_rows(mixed $input, mixed ...$args): array|string
    {
        if (!is_array($input)) {
            $input = [[$input]];
        }
        $input_array = [[]];
        // no row 0
        $num_rows = 0;
        foreach ($input as $input_row) {
            $input_array[] = self::array_values($input_row);
            ++$num_rows;
        }
        $output_array = [];
        foreach (Functions::flatten_array2(...$args) as $arg) {
            if (!is_numeric($arg)) {
                return Excel_Error::VALUE();
            }
            $index = (int) $arg;
            if ($index < 0) {
                $index += $num_rows + 1;
            }
            if ($index <= 0 || $index > $num_rows) {
                return Excel_Error::VALUE();
            }
            $output_array[] = $input_array[$index];
        }
        return $output_array;
    }
    /**
     * @param mixed[] $array
     *
     * @return mixed[]|string
     */
    private static function drop_rows(array $array, mixed $offset): array|string
    {
        if ($offset === null) {
            return $array;
        }
        if (!is_numeric($offset)) {
            return Excel_Error::VALUE();
        }
        $offset = (int) $offset;
        $count = count($array);
        if (abs($offset) >= $count) {
            // In theory, this should be #CALC!, but Excel treats
            // #CALC! as corrupt, and it's not worth figuring out why
            return Excel_Error::VALUE();
        }
        if ($offset === 0) {
            return $array;
        }
        if ($offset > 0) {
            return array_slice($array, $offset);
        }
        return array_slice($array, 0, $count + $offset);
    }
    /**
     * DROP.
     *
     * @param mixed $input expect two-dimensional array
     *
     * @return mixed[]|string
     */
    public static function drop(mixed $input, mixed $rows = null, mixed $columns = null): array|string
    {
        if (!is_array($input)) {
            $input = [[$input]];
        }
        $input_array = [];
        // no row 0
        foreach ($input as $input_row) {
            $input_array[] = self::array_values($input_row);
        }
        $output_array1 = self::drop_rows($input_array, $rows);
        if (is_string($output_array1)) {
            return $output_array1;
        }
        $output_array2 = self::transpose($output_array1);
        $output_array3 = self::drop_rows($output_array2, $columns);
        if (is_string($output_array3)) {
            return $output_array3;
        }
        return self::transpose($output_array3);
    }
    /**
     * @param mixed[] $array
     *
     * @return mixed[]|string
     */
    private static function take_rows(array $array, mixed $offset): array|string
    {
        if ($offset === null) {
            return $array;
        }
        if (!is_numeric($offset)) {
            return Excel_Error::VALUE();
        }
        $offset = (int) $offset;
        if ($offset === 0) {
            // should be #CALC! - see above
            return Excel_Error::VALUE();
        }
        $count = count($array);
        if (abs($offset) >= $count) {
            return $array;
        }
        if ($offset > 0) {
            return array_slice($array, 0, $offset);
        }
        return array_slice($array, $count + $offset);
    }
    /**
     * TAKE.
     *
     * @param mixed $input expecting two-dimensional array
     *
     * @return mixed[]|string
     */
    public static function take(mixed $input, mixed $rows, mixed $columns = null): array|string
    {
        if (!is_array($input)) {
            $input = [[$input]];
        }
        if ($rows === null && $columns === null) {
            return $input;
        }
        $input_array = [];
        foreach ($input as $input_row) {
            $input_array[] = self::array_values($input_row);
        }
        $output_array1 = self::take_rows($input_array, $rows);
        if (is_string($output_array1)) {
            return $output_array1;
        }
        $output_array2 = self::transpose($output_array1);
        $output_array3 = self::take_rows($output_array2, $columns);
        if (is_string($output_array3)) {
            return $output_array3;
        }
        return self::transpose($output_array3);
    }
    /**
     * EXPAND.
     *
     * @param mixed $input expecting two-dimensional array
     *
     * @return mixed[]|string
     */
    public static function expand(mixed $input, mixed $rows, mixed $columns = null, mixed $pad = '#N/A'): array|string
    {
        if (!is_array($input)) {
            $input = [[$input]];
        }
        if ($rows === null && $columns === null) {
            return $input;
        }
        $num_rows = count($input);
        $rows ??= $num_rows;
        if (!is_numeric($rows)) {
            return Excel_Error::VALUE();
        }
        $rows = (int) $rows;
        if ($rows < count($input)) {
            return Excel_Error::VALUE();
        }
        $num_cols = 0;
        foreach ($input as $input_row) {
            $num_cols = max($num_cols, is_array($input_row) ? count($input_row) : 1);
        }
        $columns ??= $num_cols;
        if (!is_numeric($columns)) {
            return Excel_Error::VALUE();
        }
        $columns = (int) $columns;
        if ($columns < $num_cols) {
            return Excel_Error::VALUE();
        }
        $input_array = [];
        foreach ($input as $input_row) {
            $input_array[] = array_pad(self::array_values($input_row), $columns, $pad);
        }
        $output_array = [];
        $pad_row = array_pad([], $columns, $pad);
        for ($count = 0; $count < $rows; ++$count) {
            $output_array[] = $count >= $num_rows ? $pad_row : $input_array[$count];
        }
        return $output_array;
    }
}