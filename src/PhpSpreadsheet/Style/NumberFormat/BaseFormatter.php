<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format;

use Php_Office\Php_Spreadsheet\Shared\String_Helper;
abstract class Base_Formatter
{
    protected static function strip_quotes(string $format): string
    {
        // Some non-number strings are quoted, so we'll get rid of the quotes, likewise any positional * symbols
        return str_replace(['"', '*'], '', $format);
    }
    protected static function adjust_separators(string $value): string
    {
        $thousands_separator = String_Helper::get_thousands_separator();
        $decimal_separator = String_Helper::get_decimal_separator();
        if ($thousands_separator !== ',' || $decimal_separator !== '.') {
            return str_replace(['.', ',', "�"], ["�", $thousands_separator, $decimal_separator], $value);
        }
        return $value;
    }
}