<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Case_Convert
{
    use Array_Enabled;
    /**
     * LOWERCASE.
     *
     * Converts a string value to upper case.
     *
     * @param mixed $mixedCaseValue The string value to convert to lower case
     *                              Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function lower(mixed $mixed_case_value): array|string
    {
        if (is_array($mixed_case_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $mixed_case_value);
        }
        try {
            $mixed_case_value = Helpers::extract_string($mixed_case_value, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return String_Helper::str_to_lower($mixed_case_value);
    }
    /**
     * UPPERCASE.
     *
     * Converts a string value to upper case.
     *
     * @param mixed $mixedCaseValue The string value to convert to upper case
     *                              Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function upper(mixed $mixed_case_value): array|string
    {
        if (is_array($mixed_case_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $mixed_case_value);
        }
        try {
            $mixed_case_value = Helpers::extract_string($mixed_case_value, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return String_Helper::str_to_upper($mixed_case_value);
    }
    /**
     * PROPERCASE.
     *
     * Converts a string value to proper or title case.
     *
     * @param mixed $mixedCaseValue The string value to convert to title case
     *                              Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function proper(mixed $mixed_case_value): array|string
    {
        if (is_array($mixed_case_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $mixed_case_value);
        }
        try {
            $mixed_case_value = Helpers::extract_string($mixed_case_value, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return String_Helper::str_to_title($mixed_case_value);
    }
}