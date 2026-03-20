<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format;

use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
class Number_Formatter extends Base_Formatter
{
    private const NUMBER_REGEX = '/(0+)(\.?)(0*)/';
    /**
     * @param string[] $numbers
     * @param string[] $masks
     *
     * @return mixed[]
     */
    private static function merge_complex_number_format_masks(array $numbers, array $masks): array
    {
        $decimal_count = strlen($numbers[1]);
        $post_decimal_masks = [];
        do {
            $temp_mask = array_pop($masks);
            if ($temp_mask !== null) {
                $post_decimal_masks[] = $temp_mask;
                $decimal_count -= strlen($temp_mask);
            }
        } while ($temp_mask !== null && $decimal_count > 0);
        return [implode('.', $masks), implode('.', array_reverse($post_decimal_masks))];
    }
    private static function process_complex_number_format_mask(mixed $number, string $mask): string
    {
        /** @var string $result */
        $result = $number;
        $masking_block_count = preg_match_all('/0+/', $mask, $masking_blocks, PREG_OFFSET_CAPTURE);
        if ($masking_block_count > 1) {
            $masking_blocks = array_reverse($masking_blocks[0]);
            $offset = 0;
            foreach ($masking_blocks as $block) {
                $size = strlen($block[0]);
                $divisor = 10 ** $size;
                $offset = $block[1];
                /** @var float $numberFloat */
                $number_float = $number;
                $block_value = sprintf("%0{$size}d", fmod($number_float, $divisor));
                $number = floor($number_float / $divisor);
                $mask = substr_replace($mask, $block_value, $offset, $size);
            }
            /** @var string $numberString */
            $number_string = $number;
            if ($number > 0) {
                $mask = substr_replace($mask, $number_string, $offset, 0);
            }
            $result = $mask;
        }
        return self::make_string($result);
    }
    private static function complex_number_format_mask(mixed $number, string $mask, bool $split_on_point = true): string
    {
        /** @var float $numberFloat */
        $number_float = $number;
        if ($split_on_point) {
            $masks = explode('.', $mask);
            if (count($masks) <= 2) {
                $decmask = $masks[1] ?? '';
                $decpos = substr_count($decmask, '0');
                $number_float = round($number_float, $decpos);
            }
        }
        $sign = $number_float < 0.0 ? '-' : '';
        $number = self::f2s(abs($number_float));
        if ($split_on_point && str_contains($mask, '.') && str_contains($number, '.')) {
            $numbers = explode('.', $number);
            $masks = explode('.', $mask);
            if (count($masks) > 2) {
                $masks = self::merge_complex_number_format_masks($numbers, $masks);
            }
            /** @var string[] $masks */
            $integer_part = self::complex_number_format_mask($numbers[0], $masks[0], false);
            $numlen = strlen($numbers[1]);
            $msklen = strlen($masks[1]);
            if ($numlen < $msklen) {
                $numbers[1] .= str_repeat('0', $msklen - $numlen);
            }
            $decimal_part = strrev(self::complex_number_format_mask(strrev($numbers[1]), strrev($masks[1]), false));
            $decimal_part = substr($decimal_part, 0, $msklen);
            return "{$sign}{$integer_part}.{$decimal_part}";
        }
        if (strlen($number) < strlen($mask)) {
            $number = str_repeat('0', strlen($mask) - strlen($number)) . $number;
        }
        $result = self::process_complex_number_format_mask($number, $mask);
        return "{$sign}{$result}";
    }
    public static function f2s(float $f): string
    {
        return self::float_string_convert_scientific((string) $f);
    }
    public static function float_string_convert_scientific(string $s): string
    {
        // convert only normalized form of scientific notation:
        //  optional sign, single digit 1-9,
        //    decimal point and digits (allowed to be omitted),
        //    E (e permitted), optional sign, one or more digits
        if (preg_match('/^([+-])?([1-9])([.]([0-9]+))?[eE]([+-]?[0-9]+)$/', $s, $matches) === 1) {
            $exponent = (int) $matches[5];
            $sign = $matches[1] === '-' ? '-' : '';
            if ($exponent >= 0) {
                $exponent_plus1 = $exponent + 1;
                $out = $matches[2] . $matches[4];
                $len = strlen($out);
                if ($len < $exponent_plus1) {
                    $out .= str_repeat('0', $exponent_plus1 - $len);
                }
                $out = substr($out, 0, $exponent_plus1) . (strlen($out) === $exponent_plus1 ? '' : '.' . substr($out, $exponent_plus1));
                $s = "{$sign}{$out}";
            } else {
                $s = $sign . '0.' . str_repeat('0', -$exponent - 1) . $matches[2] . $matches[4];
            }
        }
        return $s;
    }
    /** @param string[] $matches */
    private static function format_straight_numeric_value(mixed $value, string $format, array $matches, bool $use_thousands): string
    {
        /** @var float $valueFloat */
        $value_float = $value;
        $left = $matches[1];
        $dec = $matches[2];
        $right = $matches[3];
        // minimum width of formatted number (including dot)
        $min_width = strlen($left) + strlen($dec) + strlen($right);
        if ($use_thousands) {
            $value = number_format($value_float, strlen($right), String_Helper::get_decimal_separator(), String_Helper::get_thousands_separator());
            return self::preg_replace(self::NUMBER_REGEX, $value, $format);
        }
        if (preg_match('/[0#]E[+-]0/i', $format)) {
            //    Scientific format
            $decimals = strlen($right);
            $size = $decimals + 3;
            return sprintf("%{$size}.{$decimals}E", $value_float);
        }
        if (preg_match('/0([^\d\.]+)0/', $format) || substr_count($format, '.') > 1) {
            if ($value_float == floor($value_float) && substr_count($format, '.') === 1) {
                $value *= 10 ** strlen(explode('.', $format)[1]);
                //* @phpstan-ignore-line
            }
            $result = self::complex_number_format_mask($value, $format);
            if (str_contains($result, 'E')) {
                // This is a hack and doesn't match Excel.
                // It will, at least, be an accurate representation,
                //  even if formatted incorrectly.
                // This is needed for absolute values >=1E18.
                return self::f2s($value_float);
            }
            return $result;
        }
        $sprintf_pattern = "%0{$min_width}." . strlen($right) . 'F';
        /** @var float $valueFloat */
        $value_float = $value;
        $value = self::adjust_separators(sprintf($sprintf_pattern, round($value_float, strlen($right))));
        return self::preg_replace(self::NUMBER_REGEX, $value, $format);
    }
    /** @param float|int|numeric-string $value value to be formatted */
    public static function format(mixed $value, string $format): string
    {
        // The "_" in this string has already been stripped out,
        // so this test is never true. Furthermore, testing
        // on Excel shows this format uses Euro symbol, not "EUR".
        // if ($format === NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE) {
        //     return 'EUR ' . sprintf('%1.2f', $value);
        // }
        $base_format = $format;
        $use_thousands = self::are_thousands_required($format);
        $scale = self::scale_thousands_millions($format);
        if (preg_match('/[#\?0]?.*[#\?0]\/(\?+|\d+|#)/', $format)) {
            // It's a dirty hack; but replace # and 0 digit placeholders with ?
            $format = (string) preg_replace('/[#0]+\//', '?/', $format);
            $format = (string) preg_replace('/\/[#0]+/', '/?', $format);
            $value = Fraction_Formatter::format($value, $format);
        } else {
            // Handle the number itself
            // scale number
            $value = $value / $scale;
            $padding_placeholder = str_contains($format, '?');
            // Replace # or ? with 0
            $format = self::preg_replace('/[\#\?](?=(?:[^"]*"[^"]*")*[^"]*\Z)/', '0', $format);
            // Remove locale code [$-###] for an LCID
            $format = self::preg_replace('/\[\$\-.*\]/', '', $format);
            $n = '/\[[^\]]+\]/';
            $m = self::preg_replace($n, '', $format);
            // Some non-number strings are quoted, so we'll get rid of the quotes, likewise any positional * symbols
            $format = self::make_string(str_replace(['"', '*'], '', $format));
            if (preg_match(self::NUMBER_REGEX, $m, $matches)) {
                // There are placeholders for digits, so inject digits from the value into the mask
                $value = self::format_straight_numeric_value($value, $format, $matches, $use_thousands);
                if ($padding_placeholder === true) {
                    $value = self::pad_value($value, $base_format);
                }
            } elseif ($format !== Number_Format::FORMAT_GENERAL) {
                // Yes, I know that this is basically just a hack;
                //      if there's no placeholders for digits, just return the format mask "as is"
                $value = self::make_string(str_replace('?', '', $format));
            }
        }
        if (preg_match('/\[\$(.*)\]/u', $format, $m)) {
            //  Currency or Accounting
            $value = preg_replace('/-0+(( |\xc2\xa0))?\[/', '- [', (string) $value) ?? $value;
            $currency_code = $m[1];
            [$currency_code] = explode('-', $currency_code);
            if ($currency_code == '') {
                $currency_code = String_Helper::get_currency_code();
            }
            $value = self::preg_replace('/\[\$([^\]]*)\]/u', $currency_code, (string) $value);
        }
        if (str_contains((string) $value, '0.') && (str_contains($base_format, '#.') || str_contains($base_format, '?.'))) {
            $value = preg_replace('/(\b)0\.|([^\d])0\./', '${2}.', (string) $value);
        }
        return (string) $value;
    }
    /** @param mixed[]|string $value */
    private static function make_string(array|string $value): string
    {
        return is_array($value) ? '' : "{$value}";
    }
    private static function preg_replace(string $pattern, string $replacement, string $subject): string
    {
        return self::make_string(preg_replace($pattern, $replacement, $subject) ?? '');
    }
    public static function pad_value(string $value, string $base_format): string
    {
        $pre_decimal = $post_decimal = '';
        $preg_array = preg_split('/\.(?=(?:[^"]*"[^"]*")*[^"]*\Z)/miu', $base_format . '.?');
        if (is_array($preg_array)) {
            $pre_decimal = $preg_array[0];
            $post_decimal = $preg_array[1] ?? '';
        }
        $length = strlen($value);
        if (str_contains($post_decimal, '?')) {
            $value = str_pad(rtrim($value, '0. '), $length, ' ', STR_PAD_RIGHT);
        }
        if (str_contains($pre_decimal, '?')) {
            return str_pad(ltrim($value, '0, '), $length, ' ', STR_PAD_LEFT);
        }
        return $value;
    }
    /**
     * Find out if we need thousands separator
     * This is indicated by a comma enclosed by a digit placeholders: #, 0 or ?
     */
    public static function are_thousands_required(string &$format): bool
    {
        $use_thousands = (bool) preg_match('/([#\?0]),([#\?0])/', $format);
        if ($use_thousands) {
            $format = self::preg_replace('/([#\?0]),([#\?0])/', '${1}${2}', $format);
        }
        return $use_thousands;
    }
    /**
     * Scale thousands, millions,...
     * This is indicated by a number of commas after a digit placeholder: #, or 0.0,, or ?,.
     */
    public static function scale_thousands_millions(string &$format): int
    {
        $scale = 1;
        // same as no scale
        if (preg_match('/(#|0|\?)(,+)/', $format, $matches)) {
            $scale = 1000 ** strlen($matches[2]);
            // strip the commas
            $format = self::preg_replace('/([#\?0]),+/', '${1}', $format);
        }
        return $scale;
    }
}