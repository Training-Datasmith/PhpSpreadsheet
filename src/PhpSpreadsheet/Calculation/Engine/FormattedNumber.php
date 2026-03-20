<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Formatted_Number
{
    /**    Constants                */
    /**    Regular Expressions        */
    private const STRING_REGEXP_FRACTION = '~^\s*(-?)((\d*)\s+)?(\d+\/\d+)\s*$~';
    private const STRING_REGEXP_PERCENT = '~^(?:(?: *(?<PrefixedSign>[-+])? *\% *(?<PrefixedSign2>[-+])? *(?<PrefixedValue>[0-9]+\.?[0-9*]*(?:E[-+]?[0-9]*)?) *)|(?: *(?<PostfixedSign>[-+])? *(?<PostfixedValue>[0-9]+\.?[0-9]*(?:E[-+]?[0-9]*)?) *\% *))$~i';
    // preg_quoted string for major currency symbols, with a %s for locale currency
    private const CURRENCY_CONVERSION_LIST = '\$€£¥%s';
    /**
     * Identify whether a string contains a formatted numeric value,
     * and convert it to a numeric if it is.
     *
     * @param float|string $operand string value to test
     */
    public static function convert_to_number_if_formatted(float|string &$operand): bool
    {
        if (self::convert_to_number_if_numeric($operand)) {
            return true;
        }
        if (self::convert_to_number_if_fraction($operand)) {
            return true;
        }
        if (self::convert_to_number_if_percent($operand)) {
            return true;
        }
        return self::convert_to_number_if_currency($operand);
    }
    /**
     * Identify whether a string contains a numeric value,
     * and convert it to a numeric if it is.
     *
     * @param float|string $operand string value to test
     */
    public static function convert_to_number_if_numeric(float|string &$operand): bool
    {
        $thousands_separator = preg_quote(String_Helper::get_thousands_separator(), '/');
        $value = preg_replace(['/(\d)' . $thousands_separator . '(\d)/u', '/([+-])\s+(\d)/u'], ['$1$2', '$1$2'], trim("{$operand}"));
        $decimal_separator = preg_quote(String_Helper::get_decimal_separator(), '/');
        $value = preg_replace(['/(\d)' . $decimal_separator . '(\d)/u', '/([+-])\s+(\d)/u'], ['$1.$2', '$1$2'], $value ?? '');
        if (is_numeric($value)) {
            $operand = (float) $value;
            return true;
        }
        return false;
    }
    /**
     * Identify whether a string contains a fractional numeric value,
     * and convert it to a numeric if it is.
     *
     * @param string $operand string value to test
     */
    public static function convert_to_number_if_fraction(float|string &$operand): bool
    {
        if (is_string($operand) && preg_match(self::STRING_REGEXP_FRACTION, $operand, $match)) {
            $sign = $match[1] === '-' ? '-' : '+';
            $whole_part = $match[3] === '' ? '' : $sign . $match[3];
            $fraction_formula = '=' . $whole_part . $sign . $match[4];
            /** @var string */
            $operandx = Calculation::get_instance()->_calculate_formula_value($fraction_formula);
            $operand = $operandx;
            return true;
        }
        return false;
    }
    /**
     * Identify whether a string contains a percentage, and if so,
     * convert it to a numeric.
     *
     * @param float|string $operand string value to test
     */
    public static function convert_to_number_if_percent(float|string &$operand): bool
    {
        $thousands_separator = preg_quote(String_Helper::get_thousands_separator(), '/');
        $value = preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', trim("{$operand}"));
        $decimal_separator = preg_quote(String_Helper::get_decimal_separator(), '/');
        $value = preg_replace(['/(\d)' . $decimal_separator . '(\d)/u', '/([+-])\s+(\d)/u'], ['$1.$2', '$1$2'], $value ?? '');
        $match = [];
        if ($value !== null && preg_match(self::STRING_REGEXP_PERCENT, $value, $match, PREG_UNMATCHED_AS_NULL)) {
            //Calculate the percentage
            $sign = ($match['PrefixedSign'] ?? $match['PrefixedSign2'] ?? $match['PostfixedSign']) ?? '';
            $operand = (float) ($sign . ($match['PostfixedValue'] ?? $match['PrefixedValue'])) / 100;
            return true;
        }
        return false;
    }
    /**
     * Identify whether a string contains a currency value, and if so,
     * convert it to a numeric.
     *
     * @param float|string $operand string value to test
     */
    public static function convert_to_number_if_currency(float|string &$operand): bool
    {
        $currency_regexp = self::currency_matcher_regexp();
        $thousands_separator = preg_quote(String_Helper::get_thousands_separator(), '/');
        $value = preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', "{$operand}");
        $match = [];
        if ($value !== null && preg_match($currency_regexp, $value, $match, PREG_UNMATCHED_AS_NULL)) {
            //Determine the sign
            $sign = ($match['PrefixedSign'] ?? $match['PrefixedSign2'] ?? $match['PostfixedSign']) ?? '';
            $decimal_separator = String_Helper::get_decimal_separator();
            //Cast to a float
            $intermediate = (string) ($match['PostfixedValue'] ?? $match['PrefixedValue']);
            $intermediate = str_replace($decimal_separator, '.', $intermediate);
            if (is_numeric($intermediate)) {
                $operand = (float) ($sign . str_replace($decimal_separator, '.', $intermediate));
                return true;
            }
        }
        return false;
    }
    public static function currency_matcher_regexp(): string
    {
        $currency_codes = sprintf(self::CURRENCY_CONVERSION_LIST, preg_quote(String_Helper::get_currency_code(), '/'));
        $decimal_separator = preg_quote(String_Helper::get_decimal_separator(), '/');
        return '~^(?:(?: *(?<PrefixedSign>[-+])? *(?<PrefixedCurrency>[' . $currency_codes . ']) *(?<PrefixedSign2>[-+])? *(?<PrefixedValue>[0-9]+[' . $decimal_separator . ']?[0-9*]*(?:E[-+]?[0-9]*)?) *)|(?: *(?<PostfixedSign>[-+])? *(?<PostfixedValue>[0-9]+' . $decimal_separator . '?[0-9]*(?:E[-+]?[0-9]*)?) *(?<PostfixedCurrency>[' . $currency_codes . ']) *))$~ui';
    }
}