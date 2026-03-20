<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Text
{
    use Array_Enabled;
    /**
     * LEN.
     *
     * @param mixed $value String Value
     *                         Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of values is passed for the argument, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function length(mixed $value = ''): array|int|string
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        try {
            $value = Helpers::extract_string($value, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return mb_strlen($value, 'UTF-8');
    }
    /**
     * Compares two text strings and returns TRUE if they are exactly the same, FALSE otherwise.
     * EXACT is case-sensitive but ignores formatting differences.
     * Use EXACT to test text being entered into a document.
     *
     * @param mixed $value1 String Value
     *                         Or can be an array of values
     * @param mixed $value2 String Value
     *                         Or can be an array of values
     *
     * @return array<mixed>|bool|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function exact(mixed $value1, mixed $value2): array|bool|string
    {
        if (is_array($value1) || is_array($value2)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value1, $value2);
        }
        try {
            $value1 = Helpers::extract_string($value1, true);
            $value2 = Helpers::extract_string($value2, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return $value2 === $value1;
    }
    /**
     * T.
     *
     * @param mixed $testValue Value to check
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for the argument, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function test(mixed $test_value = ''): array|string
    {
        if (is_array($test_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $test_value);
        }
        if (is_string($test_value)) {
            return $test_value;
        }
        return '';
    }
    /**
     * TEXTSPLIT.
     *
     * @param mixed $text the text that you're searching
     * @param null|array<string>|string $columnDelimiter The text that marks the point where to spill the text across columns.
     *                          Multiple delimiters can be passed as an array of string values
     * @param null|array<string>|string $rowDelimiter The text that marks the point where to spill the text down rows.
     *                          Multiple delimiters can be passed as an array of string values
     * @param bool $ignoreEmpty Specify FALSE to create an empty cell when two delimiters are consecutive.
     *                              true = create empty cells
     *                              false = skip empty cells
     *                              Defaults to TRUE, which creates an empty cell
     * @param bool $matchMode Determines whether the match is case-sensitive or not.
     *                              true = case-sensitive
     *                              false = case-insensitive
     *                         By default, a case-sensitive match is done.
     * @param mixed $padding The value with which to pad the result.
     *                              The default is #N/A.
     *
     * @return array<mixed>|string the array built from the text, split by the row and column delimiters, or an error string
     */
    public static function split(mixed $text, $column_delimiter = null, $row_delimiter = null, bool $ignore_empty = false, bool $match_mode = true, mixed $padding = '#N/A'): array|string
    {
        $text = Functions::flatten_single_value($text);
        if (Error_Value::is_error($text, true)) {
            return String_Helper::convert_to_string($text);
        }
        $flags = self::match_flags($match_mode);
        if ($row_delimiter !== null) {
            $delimiter = self::build_delimiter($row_delimiter);
            $rows = $delimiter === '()' ? [$text] : Preg::split("/{$delimiter}/{$flags}", String_Helper::convert_to_string($text));
        } else {
            $rows = [$text];
        }
        if ($ignore_empty === true) {
            $rows = array_values(array_filter($rows, fn($row): bool => $row !== ''));
        }
        if ($column_delimiter !== null) {
            $delimiter = self::build_delimiter($column_delimiter);
            array_walk($rows, function (&$row) use ($delimiter, $flags, $ignore_empty): void {
                $row = $delimiter === '()' ? [$row] : Preg::split("/{$delimiter}/{$flags}", String_Helper::convert_to_string($row));
                if ($ignore_empty === true) {
                    $row = array_values(array_filter($row, fn($value): bool => $value !== ''));
                }
            });
            if ($ignore_empty === true) {
                $rows = array_values(array_filter($rows, fn($row): bool => $row !== [] && $row !== ['']));
            }
        }
        return self::apply_padding($rows, $padding);
    }
    /**
     * @param mixed[] $rows
     *
     * @return mixed[]
     */
    private static function apply_padding(array $rows, mixed $padding): array
    {
        $column_count = array_reduce(
            $rows,
            fn(int $counter, array $row): int => max($counter, count($row)),
            //* @phpstan-ignore-line
            0
        );
        return array_map(fn(array $row): array => count($row) < $column_count ? array_merge($row, array_fill(0, $column_count - count($row), $padding)) : $row, $rows);
    }
    /**
     * @param null|array<string>|string $delimiter the text that marks the point before which you want to split
     *                                 Multiple delimiters can be passed as an array of string values
     */
    private static function build_delimiter($delimiter): string
    {
        $value_set = Functions::flatten_array($delimiter);
        if (is_array($delimiter) && count($value_set) > 1) {
            /** @var array<?string> $valueSet */
            $quoted_delimiters = array_map(fn(?string $delimiter): string => preg_quote($delimiter ?? '', '/'), $value_set);
            $delimiters = implode('|', $quoted_delimiters);
            return '(' . $delimiters . ')';
        }
        return '(' . preg_quote(String_Helper::convert_to_string(Functions::flatten_single_value($delimiter)), '/') . ')';
    }
    private static function match_flags(bool $match_mode): string
    {
        return $match_mode === true ? 'miu' : 'mu';
    }
    /** @param mixed[][] $array */
    public static function from_array(array $array, int $format = 0): string
    {
        $result = [];
        foreach ($array as $row) {
            $cells = [];
            foreach ($row as $cell_value) {
                $value = $format === 1 ? self::format_value_mode1($cell_value) : self::format_value_mode0($cell_value);
                $cells[] = $value;
            }
            $result[] = implode($format === 1 ? ',' : ', ', $cells);
        }
        $result = implode($format === 1 ? ';' : ', ', $result);
        return $format === 1 ? '{' . $result . '}' : $result;
    }
    private static function format_value_mode0(mixed $cell_value): string
    {
        if (is_bool($cell_value)) {
            return Calculation::get_locale_boolean($cell_value ? 'TRUE' : 'FALSE');
        }
        return String_Helper::convert_to_string($cell_value);
    }
    private static function format_value_mode1(mixed $cell_value): string
    {
        if (is_string($cell_value) && Error_Value::is_error($cell_value) === false) {
            return Calculation::FORMULA_STRING_QUOTE . $cell_value . Calculation::FORMULA_STRING_QUOTE;
        }
        if (is_bool($cell_value)) {
            return Calculation::get_locale_boolean($cell_value ? 'TRUE' : 'FALSE');
        }
        return String_Helper::convert_to_string($cell_value);
    }
}