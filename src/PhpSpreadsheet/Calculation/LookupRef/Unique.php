<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Unique
{
    /**
     * UNIQUE
     * The UNIQUE function searches for value either from a one-row or one-column range or from an array.
     *
     * @param mixed $lookupVector The range of cells being searched
     * @param mixed $byColumn Whether the uniqueness should be determined by row (the default) or by column
     * @param mixed $exactlyOnce Whether the function should return only entries that occur just once in the list
     *
     * @return mixed The unique values from the search range
     */
    public static function unique(mixed $lookup_vector, mixed $by_column = false, mixed $exactly_once = false): mixed
    {
        if (!is_array($lookup_vector)) {
            // Scalars are always returned "as is"
            return $lookup_vector;
        }
        $by_column = (bool) $by_column;
        $exactly_once = (bool) $exactly_once;
        return $by_column === true ? self::unique_by_column($lookup_vector, $exactly_once) : self::unique_by_row($lookup_vector, $exactly_once);
    }
    /** @param mixed[] $lookupVector */
    private static function unique_by_row(array $lookup_vector, bool $exactly_once): mixed
    {
        // When not $byColumn, we count whole rows or values, not individual values
        //      so implode each row into a single string value
        array_walk(
            $lookup_vector,
            //* @phpstan-ignore-next-line
            function (array &$value): void {
                $valuex = '';
                $separator = '';
                $numeric_indicator = "\x01";
                foreach ($value as $cell_value) {
                    /** @var scalar $cellValue */
                    $valuex .= $separator . $cell_value;
                    $separator = "\x00";
                    if (is_int($cell_value) || is_float($cell_value)) {
                        $valuex .= $numeric_indicator;
                    }
                }
                $value = $valuex;
            }
        );
        /** @var string[] $lookupVector */
        $result = self::count_values_case_insensitive($lookup_vector);
        if ($exactly_once === true) {
            $result = self::exactly_once_filter($result);
        }
        if (count($result) === 0) {
            return Excel_Error::CALC();
        }
        $result = array_keys($result);
        // restore rows from their strings
        array_walk($result, function (string &$value): void {
            $value = explode("\x00", $value);
            foreach ($value as &$string_value) {
                if (str_ends_with($string_value, "\x01")) {
                    // x01 should only end a string which is otherwise a float or int,
                    // so phpstan is technically correct but what it fears should not happen.
                    $string_value = 0 + substr($string_value, 0, -1);
                    //@phpstan-ignore-line
                }
            }
        });
        return count($result) === 1 ? array_pop($result) : $result;
    }
    /** @param mixed[] $lookupVector */
    private static function unique_by_column(array $lookup_vector, bool $exactly_once): mixed
    {
        /** @var string[] */
        $flattened_lookup_vector = Functions::flatten_array($lookup_vector);
        if (count($lookup_vector, COUNT_RECURSIVE) > count($flattened_lookup_vector, COUNT_RECURSIVE) + 1) {
            // We're looking at a full column check (multiple rows)
            $transpose = Matrix::transpose($lookup_vector);
            $result = self::unique_by_row($transpose, $exactly_once);
            return is_array($result) ? Matrix::transpose($result) : $result;
        }
        $result = self::count_values_case_insensitive($flattened_lookup_vector);
        if ($exactly_once === true) {
            $result = self::exactly_once_filter($result);
        }
        if (count($result) === 0) {
            return Excel_Error::CALC();
        }
        return array_keys($result);
    }
    /**
     * @param string[] $caseSensitiveLookupValues
     *
     * @return mixed[]
     */
    private static function count_values_case_insensitive(array $case_sensitive_lookup_values): array
    {
        $case_insensitive_counts = array_count_values(array_map(String_Helper::str_to_upper(...), $case_sensitive_lookup_values));
        $case_sensitive_counts = [];
        foreach ($case_insensitive_counts as $case_insensitive_key => $count) {
            if (is_numeric($case_insensitive_key)) {
                $case_sensitive_counts[$case_insensitive_key] = $count;
            } else {
                foreach ($case_sensitive_lookup_values as $case_sensitive_value) {
                    if ($case_insensitive_key === String_Helper::str_to_upper($case_sensitive_value)) {
                        $case_sensitive_counts[$case_sensitive_value] = $count;
                        break;
                    }
                }
            }
        }
        return $case_sensitive_counts;
    }
    /**
     * @param mixed[] $values
     *
     * @return mixed[]
     */
    private static function exactly_once_filter(array $values): array
    {
        return array_filter($values, fn($value): bool => $value === 1);
    }
}