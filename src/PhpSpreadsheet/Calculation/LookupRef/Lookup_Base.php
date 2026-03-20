<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
abstract class Lookup_Base
{
    protected static function validate_lookup_array(mixed $lookup_array): void
    {
        if (!is_array($lookup_array)) {
            throw new Exception(Excel_Error::REF());
        }
    }
    /**
     * @param mixed[] $lookupArray
     * @param float|int|string $index_number number >= 1
     */
    protected static function validate_index_lookup(array $lookup_array, $index_number): int
    {
        // index_number must be a number greater than or equal to 1.
        // Excel results are inconsistent when index is non-numeric.
        // VLOOKUP(whatever, whatever, SQRT(-1)) yields NUM error, but
        // VLOOKUP(whatever, whatever, cellref) yields REF error
        //   when cellref is '=SQRT(-1)'. So just try our best here.
        // Similar results if string (literal yields VALUE, cellRef REF).
        if (!is_numeric($index_number)) {
            throw new Exception(Excel_Error::throw_error($index_number));
        }
        if ($index_number < 1) {
            throw new Exception(Excel_Error::VALUE());
        }
        // index_number must be less than or equal to the number of columns in lookupArray
        if (empty($lookup_array)) {
            throw new Exception(Excel_Error::REF());
        }
        return (int) $index_number;
    }
    protected static function check_match(bool $both_numeric, bool $both_not_numeric, bool $not_exact_match, int $row_key, string $cell_data_lower, string $lookup_lower, ?int $row_number): ?int
    {
        // remember the last key, but only if datatypes match
        if ($both_numeric || $both_not_numeric) {
            // Spreadsheets software returns first exact match,
            // we have sorted and we might have broken key orders
            // we want the first one (by its initial index)
            if ($not_exact_match) {
                $row_number = $row_key;
            } elseif ($cell_data_lower == $lookup_lower && ($row_number === null || $row_key < $row_number)) {
                $row_number = $row_key;
            }
        }
        return $row_number;
    }
}