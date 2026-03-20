<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Lookup
{
    use Array_Enabled;
    /**
     * LOOKUP
     * The LOOKUP function searches for value either from a one-row or one-column range or from an array.
     *
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param mixed $lookupVector The range of cells being searched
     * @param null|mixed $resultVector The column from which the matching value must be returned
     *
     * @return mixed The value of the found cell
     */
    public static function lookup(mixed $lookup_value, mixed $lookup_vector, $result_vector = null): mixed
    {
        if (is_array($lookup_value)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $lookup_value, $lookup_vector, $result_vector);
        }
        if (!is_array($lookup_vector)) {
            return Excel_Error::NA();
        }
        /** @var mixed[][] $lookupVector */
        $has_result_vector = isset($result_vector);
        $lookup_rows = self::row_count($lookup_vector);
        $lookup_columns = self::column_count($lookup_vector);
        // we correctly orient our results
        if ($lookup_rows === 1 && $lookup_columns > 1 || !$has_result_vector && $lookup_rows === 2 && $lookup_columns !== 2) {
            $lookup_vector = Matrix::transpose($lookup_vector);
            $lookup_rows = self::row_count($lookup_vector);
            /** @var mixed[][] $lookupVector */
            $lookup_columns = self::column_count($lookup_vector);
        }
        $result_vector = self::verify_result_vector($result_vector ?? $lookup_vector);
        //* @phpstan-ignore-line
        if ($lookup_rows === 2 && !$has_result_vector) {
            $result_vector = array_pop($lookup_vector);
            $lookup_vector = array_shift($lookup_vector);
        }
        /** @var array<int, mixed> $lookupVector */
        /** @var array<int, mixed> $resultVector */
        if ($lookup_columns !== 2) {
            $lookup_vector = self::verify_lookup_values($lookup_vector, $result_vector);
        }
        return V_Lookup::lookup($lookup_value, $lookup_vector, 2);
    }
    /**
     * @param array<int, mixed> $lookupVector
     * @param array<int, mixed> $resultVector
     *
     * @return mixed[]
     */
    private static function verify_lookup_values(array $lookup_vector, array $result_vector): array
    {
        foreach ($lookup_vector as &$value) {
            if (is_array($value)) {
                $k = array_keys($value);
                $key1 = $key2 = array_shift($k);
                ++$key2;
                $data_value1 = $value[$key1];
            } else {
                $key1 = 0;
                $key2 = 1;
                $data_value1 = $value;
            }
            $data_value2 = array_shift($result_vector);
            if (is_array($data_value2)) {
                $data_value2 = array_shift($data_value2);
            }
            /** @var int $key2 */
            $value = [$key1 => $data_value1, $key2 => $data_value2];
        }
        unset($value);
        return $lookup_vector;
    }
    /**
     * @param mixed[][] $resultVector
     *
     * @return mixed[]
     */
    private static function verify_result_vector(array $result_vector): array
    {
        $result_rows = self::row_count($result_vector);
        $result_columns = self::column_count($result_vector);
        // we correctly orient our results
        if ($result_rows === 1 && $result_columns > 1) {
            return Matrix::transpose($result_vector);
        }
        return $result_vector;
    }
    /** @param mixed[] $dataArray */
    private static function row_count(array $data_array): int
    {
        return count($data_array);
    }
    /** @param mixed[][] $dataArray */
    private static function column_count(array $data_array): int
    {
        $row_keys = array_keys($data_array);
        $row = array_shift($row_keys);
        return count($data_array[$row]);
    }
}