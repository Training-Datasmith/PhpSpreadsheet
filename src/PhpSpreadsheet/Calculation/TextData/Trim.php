<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
class Trim
{
    use Array_Enabled;
    /**
     * CLEAN.
     *
     * @param mixed $stringValue String Value to check
     *                              Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function non_printable(mixed $string_value = '')
    {
        if (is_array($string_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $string_value);
        }
        $string_value = Helpers::extract_string($string_value);
        return (string) preg_replace('/[\x00-\x1f]/', '', "{$string_value}");
    }
    /**
     * TRIM.
     *
     * @param mixed $stringValue String Value to check
     *                              Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function spaces(mixed $string_value = ''): array|string
    {
        if (is_array($string_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $string_value);
        }
        $string_value = Helpers::extract_string($string_value);
        return trim(preg_replace('/ +/', ' ', trim("{$string_value}", ' ')) ?? '', ' ');
    }
}