<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Information;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Named_Range;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Value
{
    use Array_Enabled;
    /**
     * IS_BLANK.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_blank(mixed $value = null): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return $value === null;
    }
    /**
     * IS_REF.
     *
     * @param mixed $value Value to check
     */
    public static function is_ref(mixed $value, ?Cell $cell = null): bool
    {
        if ($cell === null) {
            return false;
        }
        $value = String_Helper::convert_to_string($value);
        $cell_value = Functions::trim_trailing_range($value);
        if (preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/ui', $cell_value) === 1) {
            [$worksheet, $cell_value] = Worksheet::extract_sheet_title($cell_value, true, true);
            if (!empty($worksheet) && $cell->get_worksheet()->get_parent_or_throw()->get_sheet_by_name($worksheet) === null) {
                return false;
            }
            try {
                [$column, $row] = Coordinate::indexes_from_string($cell_value ?? '');
            } catch (Spreadsheet_Exception) {
                return false;
            }
            return true;
        }
        $named_range = $cell->get_worksheet()->get_parent_or_throw()->get_named_range($value);
        return $named_range instanceof Named_Range;
    }
    /**
     * IS_EVEN.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_even(mixed $value = null): array|string|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        if ($value === null) {
            return Excel_Error::NAME();
        }
        if (!is_numeric($value)) {
            return Excel_Error::VALUE();
        }
        return (int) fmod($value + 0, 2) === 0;
    }
    /**
     * IS_ODD.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_odd(mixed $value = null): array|string|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        if ($value === null) {
            return Excel_Error::NAME();
        }
        if (!is_numeric($value)) {
            return Excel_Error::VALUE();
        }
        return (int) fmod($value + 0, 2) !== 0;
    }
    /**
     * IS_NUMBER.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_number(mixed $value = null): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        if (is_string($value)) {
            return false;
        }
        return is_numeric($value);
    }
    /**
     * IS_LOGICAL.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_logical(mixed $value = null): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return is_bool($value);
    }
    /**
     * IS_TEXT.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_text(mixed $value = null): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return is_string($value) && !Error_Value::is_error($value);
    }
    /**
     * IS_NONTEXT.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_non_text(mixed $value = null): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return !self::is_text($value);
    }
    /**
     * ISFORMULA.
     *
     * @param mixed $cellReference The cell to check
     * @param ?Cell $cell The current cell (containing this formula)
     *
     * @return array<mixed>|bool|string
     */
    public static function is_formula(mixed $cell_reference = '', ?Cell $cell = null): array|bool|string
    {
        if ($cell === null) {
            return Excel_Error::REF();
        }
        $cell_reference = String_Helper::convert_to_string($cell_reference);
        $full_cell_reference = Functions::expand_defined_name($cell_reference, $cell);
        if (str_contains($cell_reference, '!')) {
            $cell_reference = Functions::trim_sheet_from_cell_reference($cell_reference);
            $cell_references = Coordinate::extract_all_cell_references_in_range($cell_reference);
            if (count($cell_references) > 1) {
                return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $cell_references, $cell);
            }
        }
        $full_cell_reference = Functions::trim_trailing_range($full_cell_reference);
        $worksheet_name = '';
        if (1 == preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/i', $full_cell_reference, $matches)) {
            $full_cell_reference = $matches[6] . $matches[7];
            $worksheet_name = str_replace("''", "'", trim($matches[2], "'"));
        }
        $worksheet = !empty($worksheet_name) ? $cell->get_worksheet()->get_parent_or_throw()->get_sheet_by_name($worksheet_name) : $cell->get_worksheet();
        if ($worksheet === null) {
            return Excel_Error::REF();
        }
        try {
            return $worksheet->get_cell($full_cell_reference)->is_formula();
        } catch (Spreadsheet_Exception) {
            return true;
        }
    }
    /**
     * N.
     *
     * Returns a value converted to a number
     *
     * @param null|mixed $value The value you want converted
     *
     * @return number|string N converts values listed in the following table
     *        If value is or refers to N returns
     *        A number            That number value
     *        A date              The Excel serialized number of that date
     *        TRUE                1
     *        FALSE               0
     *        An error value      The error value
     *        Anything else       0
     */
    public static function as_number($value = null): float|int|string
    {
        while (is_array($value)) {
            $value = array_shift($value);
        }
        if (is_float($value) || is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_string($value) && str_starts_with($value, '#')) {
            return $value;
        }
        return 0;
    }
    /**
     * TYPE.
     *
     * Returns a number that identifies the type of a value
     *
     * @param null|mixed $value The value you want tested
     *
     * @return int N converts values listed in the following table
     *        If value is or refers to N returns
     *        A number            1
     *        Text                2
     *        Logical Value       4
     *        An error value      16
     *        Array or Matrix     64
     */
    public static function type($value = null): int
    {
        $value = Functions::flatten_array_indexed($value);
        if (count($value) > 1) {
            $a = array_key_last($value);
            //    Range of cells is an error
            if (Functions::is_cell_value($a)) {
                return 16;
                //    Test for Matrix
            } elseif (Functions::is_matrix_value($a)) {
                return 64;
            }
        } elseif (empty($value)) {
            //    Empty Cell
            return 1;
        }
        $value = Functions::flatten_single_value($value);
        if ($value === null || is_float($value) || is_int($value)) {
            return 1;
        }
        if (is_bool($value)) {
            return 4;
        }
        if (is_array($value)) {
            return 64;
        }
        if (is_string($value)) {
            //    Errors
            if ($value !== '' && $value[0] == '#') {
                return 16;
            }
            return 2;
        }
        return 0;
    }
}