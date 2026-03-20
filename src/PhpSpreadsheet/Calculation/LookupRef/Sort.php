<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Sort extends Lookup_Ref_Validations
{
    public const ORDER_ASCENDING = 1;
    public const ORDER_DESCENDING = -1;
    /**
     * SORT
     * The SORT function returns a sorted array of the elements in an array.
     * The returned array is the same shape as the provided array argument.
     * Both $sortIndex and $sortOrder can be arrays, to provide multi-level sorting.
     *
     * NOTE: If $sortArray contains a mixture of data types
     * (string/int/bool), the results may be unexpected.
     * This is also true if the array consists of string
     * representations of numbers, especially if there are
     * both positive and negative numbers in the mix.
     *
     * @param mixed $sortArray The range of cells being sorted
     * @param mixed $sortIndex The column or row number within the sortArray to sort on
     * @param mixed $sortOrder Flag indicating whether to sort ascending or descending
     *                          Ascending = 1 (self::ORDER_ASCENDING)
     *                          Descending = -1 (self::ORDER_DESCENDING)
     * @param mixed $byColumn Whether the sort should be determined by row (the default) or by column
     *
     * @return mixed The sorted values from the sort range
     */
    public static function sort(mixed $sort_array, mixed $sort_index = 1, mixed $sort_order = self::ORDER_ASCENDING, mixed $by_column = false): mixed
    {
        if (!is_array($sort_array)) {
            $sort_array = [[$sort_array]];
        }
        /** @var mixed[][] */
        $sort_array = self::enumerate_array_keys($sort_array);
        $by_column = (bool) $by_column;
        $lookup_index_size = $by_column ? count($sort_array) : count($sort_array[0]);
        try {
            // If $sortIndex and $sortOrder are scalars, then convert them into arrays
            if (!is_array($sort_index)) {
                $sort_index = [$sort_index];
                $sort_order = is_scalar($sort_order) ? [$sort_order] : $sort_order;
            }
            // but the values of those array arguments still need validation
            $sort_order = empty($sort_order) ? [self::ORDER_ASCENDING] : $sort_order;
            self::validate_array_arguments_for_sort($sort_index, $sort_order, $lookup_index_size);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // We want a simple, enumerated array of arrays where we can reference column by its index number.
        /** @var callable(mixed): mixed */
        $temp = 'array_values';
        /** @var array<int> $sortOrder */
        $sort_array = array_values(array_map($temp, $sort_array));
        /** @var int[] $sortIndex */
        return $by_column === true ? self::sort_by_column($sort_array, $sort_index, $sort_order) : self::sort_by_row($sort_array, $sort_index, $sort_order);
    }
    /**
     * SORTBY
     * The SORTBY function sorts the contents of a range or array based on the values in a corresponding range or array.
     * The returned array is the same shape as the provided array argument.
     * Both $sortIndex and $sortOrder can be arrays, to provide multi-level sorting.
     * Microsoft doesn't even bother documenting that a column sort
     * is possible. However, it is. According to:
     * https://exceljet.net/functions/sortby-function
     * When by_array is a horizontal range, SORTBY sorts horizontally by columns.
     * My interpretation of this is that by_array must be an
     * array which contains exactly one row.
     *
     * NOTE: If the "byArray" contains a mixture of data types
     * (string/int/bool), the results may be unexpected.
     * This is also true if the array consists of string
     * representations of numbers, especially if there are
     * both positive and negative numbers in the mix.
     *
     * @param mixed $sortArray The range of cells being sorted
     * @param mixed $args
     *              At least one additional argument must be provided, The vector or range to sort on
     *              After that, arguments are passed as pairs:
     *                    sort order: ascending or descending
     *                         Ascending = 1 (self::ORDER_ASCENDING)
     *                         Descending = -1 (self::ORDER_DESCENDING)
     *                    additional arrays or ranges for multi-level sorting
     *
     * @return mixed The sorted values from the sort range
     */
    public static function sort_by(mixed $sort_array, mixed ...$args): mixed
    {
        if (!is_array($sort_array)) {
            $sort_array = [[$sort_array]];
        }
        $transpose = false;
        $args0 = $args[0] ?? null;
        if (is_array($args0) && count($args0) === 1) {
            $args0 = reset($args0);
            if (is_array($args0) && count($args0) > 1) {
                $transpose = true;
                $sort_array = Matrix::transpose($sort_array);
            }
        }
        $sort_array = self::enumerate_array_keys($sort_array);
        $lookup_array_size = count($sort_array);
        $argument_count = count($args);
        try {
            $sort_by = $sort_order = [];
            for ($i = 0; $i < $argument_count; $i += 2) {
                $args_i = $args[$i];
                if (!is_array($args_i)) {
                    $args_i = [[$args_i]];
                }
                $sort_by[] = self::validate_sort_vector($args_i, $lookup_array_size);
                $sort_order[] = self::validate_sort_order($args[$i + 1] ?? self::ORDER_ASCENDING);
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        $temp = self::process_sort_by($sort_array, $sort_by, $sort_order);
        if ($transpose) {
            return Matrix::transpose($temp);
        }
        return $temp;
    }
    /**
     * @param mixed[] $sortArray
     *
     * @return mixed[]
     */
    private static function enumerate_array_keys(array $sort_array): array
    {
        array_walk($sort_array, function (&$columns): void {
            if (is_array($columns)) {
                $columns = array_values($columns);
            }
        });
        return array_values($sort_array);
    }
    private static function validate_scalar_arguments_for_sort(mixed &$sort_index, mixed &$sort_order, int $sort_array_size): void
    {
        $sort_index = self::validate_positive_int($sort_index, false);
        if ($sort_index > $sort_array_size) {
            throw new Exception(Excel_Error::VALUE());
        }
        $sort_order = self::validate_sort_order($sort_order);
    }
    /**
     * @param mixed[] $sortVector
     *
     * @return mixed[]
     */
    private static function validate_sort_vector(array $sort_vector, int $sort_array_size): array
    {
        // It doesn't matter if it's a row or a column vectors, it works either way
        $sort_vector = Functions::flatten_array($sort_vector);
        if (count($sort_vector) !== $sort_array_size) {
            throw new Exception(Excel_Error::VALUE());
        }
        return $sort_vector;
    }
    private static function validate_sort_order(mixed $sort_order): int
    {
        $sort_order = self::validate_int($sort_order);
        if (($sort_order == self::ORDER_ASCENDING || $sort_order === self::ORDER_DESCENDING) === false) {
            throw new Exception(Excel_Error::VALUE());
        }
        return $sort_order;
    }
    /** @param mixed[] $sortIndex */
    private static function validate_array_arguments_for_sort(array &$sort_index, mixed &$sort_order, int $sort_array_size): void
    {
        // It doesn't matter if they're row or column vectors, it works either way
        $sort_index = Functions::flatten_array($sort_index);
        $sort_order = Functions::flatten_array($sort_order);
        if (count($sort_order) === 0 || count($sort_order) > $sort_array_size || count($sort_order) > count($sort_index)) {
            throw new Exception(Excel_Error::VALUE());
        }
        if (count($sort_index) > count($sort_order)) {
            // If $sortOrder has fewer elements than $sortIndex, then the last order element is repeated.
            $sort_order = array_merge($sort_order, array_fill(0, count($sort_index) - count($sort_order), array_pop($sort_order)));
        }
        foreach ($sort_index as $key => &$value) {
            self::validate_scalar_arguments_for_sort($value, $sort_order[$key], $sort_array_size);
        }
    }
    /**
     * @param mixed[] $sortVector
     *
     * @return mixed[]
     */
    private static function prepare_sort_vector_values(array $sort_vector): array
    {
        // Strings should be sorted case-insensitive.
        // Booleans are a complete mess. Excel always seems to sort
        // booleans in a mixed vector at either the top or the bottom,
        // so converting them to string or int doesn't really work.
        // Best advice is to use them in a boolean-only vector.
        // Code below chooses int conversion, which is sensible,
        // and, as a bonus, compatible with LibreOffice.
        return array_map(function ($value) {
            if (is_bool($value)) {
                return (int) $value;
            }
            if (is_string($value)) {
                return String_Helper::str_to_lower($value);
            }
            return $value;
        }, $sort_vector);
    }
    /**
     * @param mixed[] $sortArray
     * @param mixed[] $sortIndex
     * @param int[] $sortOrder
     *
     * @return mixed[]
     */
    private static function process_sort_by(array $sort_array, array $sort_index, array $sort_order): array
    {
        $sort_arguments = [];
        /** @var mixed[] */
        $sort_data = [];
        foreach ($sort_index as $index => $sort_values) {
            /** @var mixed[] $sortValues */
            $sort_data[] = $sort_values;
            $sort_arguments[] = self::prepare_sort_vector_values($sort_values);
            $sort_arguments[] = $sort_order[$index] === self::ORDER_ASCENDING ? SORT_ASC : SORT_DESC;
        }
        $sort_vector = self::execute_vector_sort_query($sort_data, $sort_arguments);
        return self::sort_lookup_array_from_vector($sort_array, $sort_vector);
    }
    /**
     * @param mixed[] $sortArray
     * @param int[] $sortIndex
     * @param int[] $sortOrder
     *
     * @return mixed[]
     */
    private static function sort_by_row(array $sort_array, array $sort_index, array $sort_order): array
    {
        $sort_vector = self::build_vector_for_sort($sort_array, $sort_index, $sort_order);
        return self::sort_lookup_array_from_vector($sort_array, $sort_vector);
    }
    /**
     * @param mixed[] $sortArray
     * @param int[] $sortIndex
     * @param int[] $sortOrder
     *
     * @return mixed[]
     */
    private static function sort_by_column(array $sort_array, array $sort_index, array $sort_order): array
    {
        $sort_array = Matrix::transpose($sort_array);
        $result = self::sort_by_row($sort_array, $sort_index, $sort_order);
        return Matrix::transpose($result);
    }
    /**
     * @param mixed[] $sortArray
     * @param int[] $sortIndex
     * @param int[] $sortOrder
     *
     * @return mixed[]
     */
    private static function build_vector_for_sort(array $sort_array, array $sort_index, array $sort_order): array
    {
        $sort_arguments = [];
        $sort_data = [];
        foreach ($sort_index as $index => $sort_index_value) {
            $sort_values = array_column($sort_array, $sort_index_value - 1);
            $sort_data[] = $sort_values;
            $sort_arguments[] = self::prepare_sort_vector_values($sort_values);
            $sort_arguments[] = $sort_order[$index] === self::ORDER_ASCENDING ? SORT_ASC : SORT_DESC;
        }
        return self::execute_vector_sort_query($sort_data, $sort_arguments);
    }
    /**
     * @param mixed[] $sortData
     * @param mixed[] $sortArguments
     *
     * @return mixed[]
     */
    private static function execute_vector_sort_query(array $sort_data, array $sort_arguments): array
    {
        $sort_data = Matrix::transpose($sort_data);
        // We need to set an index that can be retained, as array_multisort doesn't maintain numeric keys.
        $sort_data_indexed = [];
        foreach ($sort_data as $key => $value) {
            $sort_data_indexed[Coordinate::string_from_column_index($key + 1)] = $value;
        }
        unset($sort_data);
        $sort_arguments[] =& $sort_data_indexed;
        array_multisort(...$sort_arguments);
        // After the sort, we restore the numeric keys that will now be in the correct, sorted order
        $sorted_data = [];
        foreach (array_keys($sort_data_indexed) as $key) {
            $sorted_data[] = Coordinate::column_index_from_string($key) - 1;
        }
        return $sorted_data;
    }
    /**
     * @param mixed[] $sortArray
     * @param mixed[] $sortVector
     *
     * @return mixed[]
     */
    private static function sort_lookup_array_from_vector(array $sort_array, array $sort_vector): array
    {
        // Building a new array in the correct (sorted) order works; but may be memory heavy for larger arrays
        $sorted_array = [];
        foreach ($sort_vector as $index) {
            /** @var int|string $index */
            $sorted_array[] = $sort_array[$index];
        }
        return $sorted_array;
        //        uksort(
        //            $lookupArray,
        //            function (int $a, int $b) use (array $sortVector) {
        //                return $sortVector[$a] <=> $sortVector[$b];
        //            }
        //        );
        //
        //        return $lookupArray;
    }
}