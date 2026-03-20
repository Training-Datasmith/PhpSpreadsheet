<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Extract
{
    use Array_Enabled;
    /**
     * LEFT.
     *
     * @param mixed $value String value from which to extract characters
     *                         Or can be an array of values
     * @param mixed $chars The number of characters to extract (as an integer)
     *                         Or can be an array of values
     *
     * @return array<mixed>|string The joined string
     *         If an array of values is passed for the $value or $chars arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function left(mixed $value, mixed $chars = 1): array|string
    {
        if (is_array($value) || is_array($chars)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $chars);
        }
        try {
            $value = Helpers::extract_string($value, true);
            $chars = Helpers::extract_int($chars, 0, 1);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return mb_substr($value, 0, $chars, 'UTF-8');
    }
    /**
     * MID.
     *
     * @param mixed $value String value from which to extract characters
     *                         Or can be an array of values
     * @param mixed $start Integer offset of the first character that we want to extract
     *                         Or can be an array of values
     * @param mixed $chars The number of characters to extract (as an integer)
     *                         Or can be an array of values
     *
     * @return array<mixed>|string The joined string
     *         If an array of values is passed for the $value, $start or $chars arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function mid(mixed $value, mixed $start, mixed $chars): array|string
    {
        if (is_array($value) || is_array($start) || is_array($chars)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $start, $chars);
        }
        try {
            $value = Helpers::extract_string($value, true);
            $start = Helpers::extract_int($start, 1);
            $chars = Helpers::extract_int($chars, 0);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return mb_substr($value, --$start, $chars, 'UTF-8');
    }
    /**
     * RIGHT.
     *
     * @param mixed $value String value from which to extract characters
     *                         Or can be an array of values
     * @param mixed $chars The number of characters to extract (as an integer)
     *                         Or can be an array of values
     *
     * @return array<mixed>|string The joined string
     *         If an array of values is passed for the $value or $chars arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function right(mixed $value, mixed $chars = 1): array|string
    {
        if (is_array($value) || is_array($chars)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $chars);
        }
        try {
            $value = Helpers::extract_string($value, true);
            $chars = Helpers::extract_int($chars, 0, 1);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        return mb_substr($value, mb_strlen($value, 'UTF-8') - $chars, $chars, 'UTF-8');
    }
    /**
     * TEXTBEFORE.
     *
     * @param mixed $text the text that you're searching
     *                    Or can be an array of values
     * @param null|array<string>|string $delimiter the text that marks the point before which you want to extract
     *                                 Multiple delimiters can be passed as an array of string values
     * @param mixed $instance The instance of the delimiter after which you want to extract the text.
     *                            By default, this is the first instance (1).
     *                            A negative value means start searching from the end of the text string.
     *                        Or can be an array of values
     * @param mixed $matchMode Determines whether the match is case-sensitive or not.
     *                           0 - Case-sensitive
     *                           1 - Case-insensitive
     *                        Or can be an array of values
     * @param mixed $matchEnd Treats the end of text as a delimiter.
     *                          0 - Don't match the delimiter against the end of the text.
     *                          1 - Match the delimiter against the end of the text.
     *                        Or can be an array of values
     * @param array<string>|bool|float|int|string $ifNotFound value to return if no match is found
     *                             The default is a #N/A Error
     *                          Or can be an array of values
     *
     * @return array<mixed>|string the string extracted from text before the delimiter; or the $ifNotFound value
     *         If an array of values is passed for any of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function before(mixed $text, $delimiter, mixed $instance = 1, mixed $match_mode = 0, mixed $match_end = 0, mixed $if_not_found = '#N/A'): array|string
    {
        if (is_array($text) || is_array($instance) || is_array($match_mode) || is_array($match_end) || is_array($if_not_found)) {
            return self::evaluate_array_arguments_ignore([self::class, __FUNCTION__], 1, $text, $delimiter, $instance, $match_mode, $match_end, $if_not_found);
        }
        try {
            $text = Helpers::extract_string($text ?? '', true);
            Helpers::extract_string(Functions::flatten_single_value($delimiter ?? ''), true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $instance = (int) String_Helper::convert_to_string($instance);
        $match_mode = (int) String_Helper::convert_to_string($match_mode);
        $match_end = (int) String_Helper::convert_to_string($match_end);
        $split = self::validate_text_before_after($text, $delimiter, $instance, $match_mode, $match_end, $if_not_found);
        if (is_string($split)) {
            return $split;
        }
        if (Helpers::extract_string(Functions::flatten_single_value($delimiter ?? '')) === '') {
            return $instance > 0 ? '' : $text;
        }
        // Adjustment for a match as the first element of the split
        $flags = self::match_flags($match_mode);
        $delimiter = self::build_delimiter($delimiter);
        $adjust = preg_match('/^' . $delimiter . "\$/{$flags}", $split[0]);
        $odd_reverse_adjustment = count($split) % 2;
        $split = $instance < 0 ? array_slice($split, 0, max(count($split) - (abs($instance) * 2 - 1) - $adjust - $odd_reverse_adjustment, 0)) : array_slice($split, 0, $instance * 2 - 1 - $adjust);
        return implode('', $split);
    }
    /**
     * TEXTAFTER.
     *
     * @param mixed $text the text that you're searching
     * @param null|array<string>|string $delimiter the text that marks the point before which you want to extract
     *                                 Multiple delimiters can be passed as an array of string values
     * @param mixed $instance The instance of the delimiter after which you want to extract the text.
     *                          By default, this is the first instance (1).
     *                          A negative value means start searching from the end of the text string.
     *                        Or can be an array of values
     * @param mixed $matchMode Determines whether the match is case-sensitive or not.
     *                            0 - Case-sensitive
     *                            1 - Case-insensitive
     *                         Or can be an array of values
     * @param mixed $matchEnd Treats the end of text as a delimiter.
     *                          0 - Don't match the delimiter against the end of the text.
     *                          1 - Match the delimiter against the end of the text.
     *                        Or can be an array of values
     * @param array<string>|scalar $ifNotFound value to return if no match is found
     *                             The default is a #N/A Error
     *                          Or can be an array of values
     *
     * @return array<mixed>|string the string extracted from text before the delimiter; or the $ifNotFound value
     *         If an array of values is passed for any of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function after(mixed $text, $delimiter, mixed $instance = 1, mixed $match_mode = 0, mixed $match_end = 0, mixed $if_not_found = '#N/A'): array|string
    {
        if (is_array($text) || is_array($instance) || is_array($match_mode) || is_array($match_end) || is_array($if_not_found)) {
            return self::evaluate_array_arguments_ignore([self::class, __FUNCTION__], 1, $text, $delimiter, $instance, $match_mode, $match_end, $if_not_found);
        }
        try {
            $text = Helpers::extract_string($text ?? '', true);
            Helpers::extract_string(Functions::flatten_single_value($delimiter ?? ''), true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $instance = (int) String_Helper::convert_to_string($instance);
        $match_mode = (int) String_Helper::convert_to_string($match_mode);
        $match_end = (int) String_Helper::convert_to_string($match_end);
        $split = self::validate_text_before_after($text, $delimiter, $instance, $match_mode, $match_end, $if_not_found);
        if (is_string($split)) {
            return $split;
        }
        if (Helpers::extract_string(Functions::flatten_single_value($delimiter ?? '')) === '') {
            return $instance < 0 ? '' : $text;
        }
        // Adjustment for a match as the first element of the split
        $flags = self::match_flags($match_mode);
        $delimiter = self::build_delimiter($delimiter);
        $adjust = preg_match('/^' . $delimiter . "\$/{$flags}", $split[0]);
        $odd_reverse_adjustment = count($split) % 2;
        $split = $instance < 0 ? array_slice($split, count($split) - abs($instance + 1) * 2 - $adjust - $odd_reverse_adjustment) : array_slice($split, $instance * 2 - $adjust);
        return implode('', $split);
    }
    /**
     * @param null|array<string>|string $delimiter
     * @param array<string>|scalar $ifNotFound
     *
     * @return array<string>|string
     */
    private static function validate_text_before_after(string $text, null|array|string $delimiter, int $instance, int $match_mode, int $match_end, mixed $if_not_found): array|string
    {
        $flags = self::match_flags($match_mode);
        $delimiter = self::build_delimiter($delimiter);
        if (preg_match('/' . $delimiter . "/{$flags}", $text) === 0 && $match_end === 0) {
            return is_array($if_not_found) ? $if_not_found : String_Helper::convert_to_string($if_not_found);
        }
        $split = preg_split('/' . $delimiter . "/{$flags}", $text, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if ($split === false) {
            return Excel_Error::NA();
        }
        if ($instance === 0 || abs($instance) > String_Helper::count_characters($text)) {
            return Excel_Error::VALUE();
        }
        if ($match_end === 0 && abs($instance) > floor(count($split) / 2)) {
            return Excel_Error::NA();
        }
        if ($match_end !== 0 && abs($instance) - 1 > ceil(count($split) / 2)) {
            return Excel_Error::NA();
        }
        return $split;
    }
    /**
     * @param null|array<string>|string $delimiter the text that marks the point before which you want to extract
     *                                 Multiple delimiters can be passed as an array of string values
     */
    private static function build_delimiter($delimiter): string
    {
        if (is_array($delimiter)) {
            /** @var array<?string> */
            $delimiter = Functions::flatten_array($delimiter);
            $quoted_delimiters = array_map(fn(?string $delimiter): string => preg_quote($delimiter ?? '', '/'), $delimiter);
            $delimiters = implode('|', $quoted_delimiters);
            return '(' . $delimiters . ')';
        }
        /** @var ?string $delimiter */
        return '(' . preg_quote($delimiter ?? '', '/') . ')';
    }
    private static function match_flags(int $match_mode): string
    {
        return $match_mode === 0 ? 'mu' : 'miu';
    }
}