<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Reader\Xls\Color\BIFF8;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
class Formatter extends Base_Formatter
{
    /**
     * Matches any @ symbol that isn't enclosed in quotes.
     */
    private const SYMBOL_AT = '/@(?=(?:[^"]*"[^"]*")*[^"]*\Z)/miu';
    private const QUOTE_REPLACEMENT = "￾";
    // invalid Unicode character
    /**
     * Matches any ; symbol that isn't enclosed in quotes, for a "section" split.
     */
    private const SECTION_SPLIT = '/;(?=(?:[^"]*"[^"]*")*[^"]*\Z)/miu';
    private static function split_format_comparison(mixed $value, ?string $condition, mixed $comparison_value, string $default_condition, mixed $default_comparison_value): bool
    {
        if (!$condition) {
            $condition = $default_condition;
            $comparison_value = $default_comparison_value;
        }
        return match ($condition) {
            '>' => $value > $comparison_value,
            '<' => $value < $comparison_value,
            '<=' => $value <= $comparison_value,
            '<>' => $value != $comparison_value,
            '=' => $value == $comparison_value,
            default => $value >= $comparison_value,
        };
    }
    /**
     * @param float|int|numeric-string $value value to be formatted
     * @param string[] $sections
     *
     * @return mixed[]
     */
    private static function split_format_for_section_selection(array $sections, mixed $value): array
    {
        // Extract the relevant section depending on whether number is positive, negative, or zero?
        // Text not supported yet.
        // Here is how the sections apply to various values in Excel:
        //   1 section:   [POSITIVE/NEGATIVE/ZERO/TEXT]
        //   2 sections:  [POSITIVE/ZERO/TEXT] [NEGATIVE]
        //   3 sections:  [POSITIVE/TEXT] [NEGATIVE] [ZERO]
        //   4 sections:  [POSITIVE] [NEGATIVE] [ZERO] [TEXT]
        $section_count = count($sections);
        // Colour could be a named colour, or a numeric index entry in the colour-palette
        $color_regex = '/\[(' . implode('|', Color::NAMED_COLORS) . '|color\s*(\d+))\]/mui';
        $cond_regex = '/\[(>|>=|<|<=|=|<>)([+-]?\d+([.]\d+)?)\]/';
        $colors = ['', '', '', '', ''];
        $condition_operations = ['', '', '', '', ''];
        $condition_comparison_values = [0, 0, 0, 0, 0];
        for ($idx = 0; $idx < $section_count; ++$idx) {
            if (preg_match($color_regex, $sections[$idx], $matches)) {
                if (isset($matches[2])) {
                    $colors[$idx] = '#' . BIFF8::lookup((int) $matches[2] + 7)['rgb'];
                } else {
                    $colors[$idx] = $matches[0];
                }
                $sections[$idx] = (string) preg_replace($color_regex, '', $sections[$idx]);
            }
            if (preg_match($cond_regex, $sections[$idx], $matches)) {
                $condition_operations[$idx] = $matches[1];
                $condition_comparison_values[$idx] = $matches[2];
                $sections[$idx] = (string) preg_replace($cond_regex, '', $sections[$idx]);
            }
        }
        $color = $colors[0];
        $format = $sections[0];
        $absval = $value;
        switch ($section_count) {
            case 2:
                $absval = abs($value + 0);
                if (!self::split_format_comparison($value, $condition_operations[0], $condition_comparison_values[0], '>=', 0)) {
                    $color = $colors[1];
                    $format = $sections[1];
                }
                break;
            case 3:
            case 4:
                $absval = abs($value + 0);
                if (!self::split_format_comparison($value, $condition_operations[0], $condition_comparison_values[0], '>', 0)) {
                    if (self::split_format_comparison($value, $condition_operations[1], $condition_comparison_values[1], '<', 0)) {
                        $color = $colors[1];
                        $format = $sections[1];
                    } else {
                        $color = $colors[2];
                        $format = $sections[2];
                    }
                }
                break;
        }
        return [$color, $format, $absval];
    }
    /**
     * Convert a value in a pre-defined format to a PHP string.
     *
     * @param null|array<mixed>|bool|float|int|RichText|string $value Value to format
     * @param string $format Format code: see = self::FORMAT_* for predefined values;
     *                          or can be any valid MS Excel custom format string
     * @param null|array<mixed>|callable $callBack Callback function for additional formatting of string
     * @param bool $lessFloatPrecision If true, unstyled floats will be converted to a more human-friendly but less computationally accurate value
     *
     * @return string Formatted string
     */
    public static function to_formatted_string($value, string $format, null|array|callable $call_back = null, bool $less_float_precision = false): string
    {
        while (is_array($value)) {
            $value = array_shift($value);
        }
        if (is_bool($value)) {
            return $value ? Calculation::get_true() : Calculation::get_false();
        }
        // For now we do not treat strings in sections, although section 4 of a format code affects strings
        // Process a single block format code containing @ for text substitution
        $formatx = str_replace('\"', self::QUOTE_REPLACEMENT, $format);
        if (preg_match(self::SECTION_SPLIT, $format) === 0 && preg_match(self::SYMBOL_AT, $formatx) === 1) {
            if (!str_contains($format, '"')) {
                return str_replace('@', String_Helper::convert_to_string($value, lessFloatPrecision: $less_float_precision), $format);
            }
            //escape any dollar signs on the string, so they are not replaced with an empty value
            $value = str_replace(['$', '"'], ['\$', self::QUOTE_REPLACEMENT], String_Helper::convert_to_string($value, lessFloatPrecision: $less_float_precision));
            return str_replace(['"', self::QUOTE_REPLACEMENT], ['', '"'], preg_replace(self::SYMBOL_AT, $value, $formatx) ?? $value);
        }
        // If we have a text value, return it "as is"
        if (!is_numeric($value)) {
            return String_Helper::convert_to_string($value, lessFloatPrecision: $less_float_precision);
        }
        // For 'General' format code, we just pass the value although this is not entirely the way Excel does it,
        // it seems to round numbers to a total of 10 digits.
        if ($format === Number_Format::FORMAT_GENERAL || $format === Number_Format::FORMAT_TEXT) {
            if (is_float($value) && $less_float_precision) {
                return self::adjust_separators((string) $value);
            }
            return self::adjust_separators(String_Helper::convert_to_string($value, lessFloatPrecision: $less_float_precision));
        }
        // Ignore square-$-brackets prefix in format string, like "[$-411]ge.m.d", "[$-010419]0%", etc
        $format = (string) preg_replace('/^\[\$-[^\]]*\]/', '', $format);
        $format = (string) preg_replace_callback('/(["])(?:(?=(\\\\?))\2.)*?\1/u', fn(array $matches): string => str_replace('.', chr(0x0), $matches[0]), $format);
        // Convert any other escaped characters to quoted strings, e.g. (\T to "T")
        $format = (string) preg_replace('/(\\\\(((.)(?!((AM\/PM)|(A\/P))))|([^ ])))(?=(?:[^"]|"[^"]*")*$)/ui', '"${2}"', $format);
        // Get the sections, there can be up to four sections, separated with a semicolon (but only if not a quoted literal)
        $sections = preg_split(self::SECTION_SPLIT, $format) ?: [];
        [$colors, $format, $value] = self::split_format_for_section_selection($sections, $value);
        // In Excel formats, "_" is used to add spacing,
        //    The following character indicates the size of the spacing, which we can't do in HTML, so we just use a standard space
        /** @var string */
        $temp = $format;
        $format = (string) preg_replace('/_.?/ui', ' ', $temp);
        // Let's begin inspecting the format and converting the value to a formatted string
        if (preg_match('/(\[\$[A-Z]*-[0-9A-F]*\])*[hmsdy](?=(?:[^"]|"[^"]*")*$)/miu', $format) && !preg_match('/\[\$[A-Z]{3}\]/miu', $format) && preg_match('/[0\?#]\.(?![^\[]*\])/miu', $format) === 0) {
            // datetime format
            /** @var float|int */
            $temp = $value;
            $value = Date_Formatter::format($temp, $format);
        } else if (str_starts_with($format, '"') && str_ends_with($format, '"') && substr_count($format, '"') === 2) {
            $value = substr($format, 1, -1);
        } elseif (preg_match('/[0#, ]%/', $format)) {
            // % number format - avoid weird '-0' problem
            /** @var float */
            $temp = $value;
            $value = Percentage_Formatter::format((float) $temp, $format);
        } else {
            /** @var float|int|numeric-string */
            $temp = $value;
            $value = Number_Formatter::format($temp, $format);
        }
        // Additional formatting provided by callback function
        if (is_callable($call_back)) {
            $value = $call_back($value, $colors);
        }
        /** @var string $value */
        return str_replace(chr(0x0), '.', $value);
    }
}