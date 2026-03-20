<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class H_Lookup extends Lookup_Base
{
    use Array_Enabled;
    /**
     * HLOOKUP
     * The HLOOKUP function searches for value in the top-most row of lookup_array and returns the value
     *     in the same column based on the index_number.
     *
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param mixed[][] $lookupArray The range of cells being searched
     * @param array<mixed>|float|int|string $indexNumber The row number in table_array from which the matching value must be returned.
     *                                The first row is 1.
     * @param mixed $notExactMatch determines if you are looking for an exact match based on lookup_value
     *
     * @return mixed The value of the found cell
     */
    public static function lookup(mixed $lookup_value, $lookup_array, $index_number, mixed $not_exact_match = true): mixed
    {
        if (is_array($lookup_value) || is_array($index_number)) {
            return self::evaluate_array_arguments_ignore([self::class, __FUNCTION__], 1, $lookup_value, $lookup_array, $index_number, $not_exact_match);
        }
        $not_exact_match = (bool) ($not_exact_match ?? true);
        try {
            self::validate_lookup_array($lookup_array);
            $lookup_array = self::convert_literal_array($lookup_array);
            $index_number = self::validate_index_lookup($lookup_array, $index_number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $f = array_keys($lookup_array);
        $first_row = reset($f);
        if (!is_array($lookup_array[$first_row]) || $index_number > count($lookup_array)) {
            return Excel_Error::REF();
        }
        $firstkey = $f[0] - 1;
        $return_column = $firstkey + $index_number;
        /** @var mixed[][] $lookupArray */
        $first_column = array_shift($f) ?? 1;
        $row_number = self::h_lookup_search($lookup_value, $lookup_array, $first_column, $not_exact_match);
        if ($row_number !== null) {
            //  otherwise return the appropriate value
            return $lookup_array[$return_column][Coordinate::string_from_column_index($row_number)];
        }
        return Excel_Error::NA();
    }
    /**
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param mixed[][] $lookupArray
     * @param  int|string $column
     */
    private static function h_lookup_search(mixed $lookup_value, array $lookup_array, $column, bool $not_exact_match): ?int
    {
        $lookup_lower = String_Helper::str_to_lower(String_Helper::convert_to_string($lookup_value));
        $row_number = null;
        foreach ($lookup_array[$column] as $row_key => $row_data) {
            // break if we have passed possible keys
            /** @var string $rowKey */
            $both_numeric = is_numeric($lookup_value) && is_numeric($row_data);
            $both_not_numeric = !is_numeric($lookup_value) && !is_numeric($row_data);
            /** @var scalar $rowData */
            $cell_data_lower = String_Helper::str_to_lower((string) $row_data);
            if ($not_exact_match && ($both_numeric && $row_data > $lookup_value || $both_not_numeric && $cell_data_lower > $lookup_lower)) {
                break;
            }
            $row_number = self::check_match($both_numeric, $both_not_numeric, $not_exact_match, Coordinate::column_index_from_string($row_key), $cell_data_lower, $lookup_lower, $row_number);
        }
        return $row_number;
    }
    /**
     * @param mixed[] $lookupArray
     *
     * @return mixed[]
     */
    private static function convert_literal_array(array $lookup_array): array
    {
        if (array_key_exists(0, $lookup_array)) {
            $lookup_array2 = [];
            $row = 0;
            foreach ($lookup_array as $array_val) {
                ++$row;
                if (!is_array($array_val)) {
                    $array_val = [$array_val];
                }
                $array_val2 = [];
                foreach ($array_val as $key2 => $val2) {
                    $index = Coordinate::string_from_column_index($key2 + 1);
                    $array_val2[$index] = $val2;
                }
                $lookup_array2[$row] = $array_val2;
            }
            $lookup_array = $lookup_array2;
        }
        return $lookup_array;
    }
}