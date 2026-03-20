<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Search
{
    use Array_Enabled;
    /**
     * FIND (case-sensitive search).
     *
     * @param mixed $needle The string to look for
     *                         Or can be an array of values
     * @param mixed $haystack The string in which to look
     *                         Or can be an array of values
     * @param mixed $offset Integer offset within $haystack to start searching from
     *                         Or can be an array of values
     *
     * @return array<mixed>|int|string The offset where the first occurrence of needle was found in the haystack
     *         If an array of values is passed for the $value or $chars arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function sensitive(mixed $needle, mixed $haystack, mixed $offset = 1): array|string|int
    {
        if (is_array($needle) || is_array($haystack) || is_array($offset)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $needle, $haystack, $offset);
        }
        try {
            $needle = Helpers::extract_string($needle, true);
            $haystack = Helpers::extract_string($haystack, true);
            $offset = Helpers::extract_int($offset, 1, 0, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if (String_Helper::count_characters($haystack) >= $offset) {
            if (String_Helper::count_characters($needle) === 0) {
                return $offset;
            }
            $pos = mb_strpos($haystack, $needle, --$offset, 'UTF-8');
            if ($pos !== false) {
                return ++$pos;
            }
        }
        return Excel_Error::VALUE();
    }
    /**
     * SEARCH (case-insensitive search).
     *
     * @param mixed $needle The string to look for
     *                         Or can be an array of values
     * @param mixed $haystack The string in which to look
     *                         Or can be an array of values
     * @param mixed $offset Integer offset within $haystack to start searching from
     *                         Or can be an array of values
     *
     * @return array<mixed>|int|string The offset where the first occurrence of needle was found in the haystack
     *         If an array of values is passed for the $value or $chars arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function insensitive(mixed $needle, mixed $haystack, mixed $offset = 1): array|string|int
    {
        if (is_array($needle) || is_array($haystack) || is_array($offset)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $needle, $haystack, $offset);
        }
        try {
            $needle = Helpers::extract_string($needle, true);
            $haystack = Helpers::extract_string($haystack, true);
            $offset = Helpers::extract_int($offset, 1, 0, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if (String_Helper::count_characters($haystack) >= $offset) {
            if (String_Helper::count_characters($needle) === 0) {
                return $offset;
            }
            $pos = mb_stripos($haystack, $needle, --$offset, 'UTF-8');
            if ($pos !== false) {
                return ++$pos;
            }
        }
        return Excel_Error::VALUE();
    }
}