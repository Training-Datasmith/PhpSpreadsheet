<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class V_Lookup extends Lookup_Base
{
    use Array_Enabled;
    /**
     * VLOOKUP
     * The VLOOKUP function searches for value in the left-most column of lookup_array and returns the value
     *     in the same row based on the index_number.
     *
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param mixed[] $lookupArray The range of cells being searched
     * @param array<mixed>|float|int|string $indexNumber The column number in table_array from which the matching value must be returned.
     *                                The first column is 1.
     * @param mixed $notExactMatch determines if you are looking for an exact match based on lookup_value
     *
     * @return mixed The value of the found cell
     */
    public static function lookup(mixed $lookup_value, array $lookup_array, mixed $index_number, mixed $not_exact_match = true): mixed
    {
        if (is_array($lookup_value) || is_array($index_number)) {
            return self::evaluate_array_arguments_ignore([self::class, __FUNCTION__], 1, $lookup_value, $lookup_array, $index_number, $not_exact_match);
        }
        $not_exact_match = (bool) ($not_exact_match ?? true);
        try {
            self::validate_lookup_array($lookup_array);
            $index_number = self::validate_index_lookup($lookup_array, $index_number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $f = array_keys($lookup_array);
        $first_row = array_pop($f);
        if (!is_array($lookup_array[$first_row]) || $index_number > count($lookup_array[$first_row])) {
            return Excel_Error::REF();
        }
        $column_keys = array_keys($lookup_array[$first_row]);
        $return_column = $column_keys[--$index_number];
        $first_column = array_shift($column_keys) ?? 1;
        if (!$not_exact_match) {
            /** @var callable $callable */
            $callable = self::vlookup_sort(...);
            uasort($lookup_array, $callable);
        }
        /** @var string[][] $lookupArray */
        $row_number = self::v_lookup_search($lookup_value, $lookup_array, $first_column, $not_exact_match);
        if ($row_number !== null) {
            // return the appropriate value
            return $lookup_array[$row_number][$return_column];
        }
        return Excel_Error::NA();
    }
    /**
     * @param scalar[] $a
     * @param scalar[] $b
     */
    private static function vlookup_sort(array $a, array $b): int
    {
        $first_column = array_key_first($a);
        $a_lower = String_Helper::str_to_lower((string) $a[$first_column]);
        $b_lower = String_Helper::str_to_lower((string) $b[$first_column]);
        return $a_lower <=> $b_lower;
    }
    /**
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param string[][] $lookupArray
     * @param  int|string $column
     */
    private static function v_lookup_search(mixed $lookup_value, array $lookup_array, $column, bool $not_exact_match): ?int
    {
        $lookup_lower = String_Helper::str_to_lower(String_Helper::convert_to_string($lookup_value));
        $row_number = null;
        foreach ($lookup_array as $row_key => $row_data) {
            $both_numeric = self::numeric($lookup_value) && self::numeric($row_data[$column]);
            $both_not_numeric = !self::numeric($lookup_value) && !self::numeric($row_data[$column]);
            $cell_data_lower = String_Helper::str_to_lower((string) $row_data[$column]);
            // break if we have passed possible keys
            if ($not_exact_match && ($both_numeric && $row_data[$column] > $lookup_value || $both_not_numeric && $cell_data_lower > $lookup_lower)) {
                break;
            }
            $row_number = self::check_match($both_numeric, $both_not_numeric, $not_exact_match, $row_key, $cell_data_lower, $lookup_lower, $row_number);
        }
        return $row_number;
    }
    private static function numeric(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}