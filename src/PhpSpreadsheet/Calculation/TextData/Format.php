<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Composer\Pcre\Preg;
use DateTimeInterface;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
class Format
{
    use Array_Enabled;
    /**
     * DOLLAR.
     *
     * This function converts a number to text using currency format, with the decimals rounded to the specified place.
     * The format used is $#,##0.00_);($#,##0.00)..
     *
     * @param mixed $value The value to format
     *                         Or can be an array of values
     * @param mixed $decimals The number of digits to display to the right of the decimal point (as an integer).
     *                            If decimals is negative, number is rounded to the left of the decimal point.
     *                            If you omit decimals, it is assumed to be 2
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function DOLLAR(mixed $value = 0, mixed $decimals = 2): array|string
    {
        if (is_array($value) || is_array($decimals)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $decimals);
        }
        try {
            $value = Helpers::extract_float($value);
            $decimals = Helpers::extract_int($decimals, -100, 0, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $mask = '$#,##0';
        if ($decimals > 0) {
            $mask .= '.' . str_repeat('0', $decimals);
        } else {
            $round = 10 ** abs($decimals);
            if ($value < 0) {
                $round = 0 - $round;
            }
            /** @var float|int|string */
            $value = Math_Trig\Round::multiple($value, $round);
        }
        $mask = "{$mask};-{$mask}";
        return Number_Format::to_formatted_string($value, $mask);
    }
    /**
     * FIXED.
     *
     * @param mixed $value The value to format
     *                         Or can be an array of values
     * @param mixed $decimals Integer value for the number of decimal places that should be formatted
     *                         Or can be an array of values
     * @param mixed $noCommas Boolean value indicating whether the value should have thousands separators or not
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function FIXEDFORMAT(mixed $value, mixed $decimals = 2, mixed $no_commas = false): array|string
    {
        if (is_array($value) || is_array($decimals) || is_array($no_commas)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $decimals, $no_commas);
        }
        try {
            $value = Helpers::extract_float($value);
            $decimals = Helpers::extract_int($decimals, -100, 0, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $value_result = round($value, $decimals);
        if ($decimals < 0) {
            $decimals = 0;
        }
        if ($no_commas === false) {
            $value_result = number_format($value_result, $decimals, String_Helper::get_decimal_separator(), String_Helper::get_thousands_separator());
        }
        return (string) $value_result;
    }
    /**
     * TEXT.
     *
     * @param mixed $value The value to format
     *                         Or can be an array of values
     * @param mixed $format A string with the Format mask that should be used
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function TEXTFORMAT(mixed $value, mixed $format): array|string
    {
        if (is_array($value) || is_array($format)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $format);
        }
        try {
            $value = Helpers::extract_string($value, true);
            $format = Helpers::extract_string($format, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $format = (string) Number_Format::convert_system_formats($format);
        if (!is_numeric($value) && Date::is_date_time_format_code($format) && !Preg::is_match('/^\s*\d+(\s+\d+)+\s*$/', $value)) {
            $value1 = Date_Time_Excel\Date_Value::from_string($value);
            $value2 = Date_Time_Excel\Time_Value::from_string($value);
            $value = is_numeric($value1) && is_numeric($value2) ? $value1 + $value2 : (is_numeric($value1) ? $value1 : (is_numeric($value2) ? $value2 : $value));
        }
        return Number_Format::to_formatted_string($value, $format);
    }
    /**
     * @param mixed $value Value to check
     */
    private static function convert_value(mixed $value, bool $spaces_mean_zero = false): mixed
    {
        $value ??= 0;
        if (is_bool($value)) {
            if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
                $value = (int) $value;
            } else {
                throw new Calc_Exp(Excel_Error::VALUE());
            }
        }
        if (is_string($value)) {
            $value = trim($value);
            if (Error_Value::is_error($value, true)) {
                throw new Calc_Exp($value);
            }
            if ($spaces_mean_zero && $value === '') {
                $value = 0;
            }
        }
        return $value;
    }
    /**
     * VALUE.
     *
     * @param mixed $value Value to check
     *                         Or can be an array of values
     *
     * @return array<mixed>|DateTimeInterface|float|int|string A string if arguments are invalid
     *         If an array of values is passed for the argument, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function VALUE(mixed $value = '')
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        try {
            $value = self::convert_value($value);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if (!is_numeric($value)) {
            $value = String_Helper::convert_to_string($value);
            $number_value = str_replace(String_Helper::get_thousands_separator(), '', trim($value, " \t\n\r\x00\v" . String_Helper::get_currency_code()));
            if ($number_value === '') {
                return Excel_Error::VALUE();
            }
            if (is_numeric($number_value)) {
                return (float) $number_value;
            }
            $date_setting = Functions::get_return_date_type();
            Functions::set_return_date_type(Functions::RETURNDATE_EXCEL);
            if (str_contains($value, ':')) {
                $time_value = Functions::scalar(Date_Time_Excel\Time_Value::from_string($value));
                if ($time_value !== Excel_Error::VALUE()) {
                    Functions::set_return_date_type($date_setting);
                    return $time_value;
                    //* @phpstan-ignore-line
                }
            }
            $date_value = Functions::scalar(Date_Time_Excel\Date_Value::from_string($value));
            if ($date_value !== Excel_Error::VALUE()) {
                Functions::set_return_date_type($date_setting);
                return $date_value;
                //* @phpstan-ignore-line
            }
            Functions::set_return_date_type($date_setting);
            return Excel_Error::VALUE();
        }
        return (float) $value;
    }
    /**
     * VALUETOTEXT.
     *
     * @param mixed $value The value to format
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function value_to_text(mixed $value, mixed $format = false): array|string
    {
        if (is_array($value) || is_array($format)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $format);
        }
        $format = (bool) $format;
        if (is_object($value) && $value instanceof Rich_Text) {
            $value = $value->get_plain_text();
        }
        if (is_string($value)) {
            $value = $format === true ? String_Helper::convert_to_string(Calculation::wrap_result($value)) : $value;
            $value = str_replace("\n", '', $value);
        } elseif (is_bool($value)) {
            $value = Calculation::get_locale_boolean($value ? 'TRUE' : 'FALSE');
        }
        return String_Helper::convert_to_string($value);
    }
    private static function get_decimal_separator(mixed $decimal_separator): string
    {
        return empty($decimal_separator) ? String_Helper::get_decimal_separator() : String_Helper::convert_to_string($decimal_separator);
    }
    private static function get_group_separator(mixed $group_separator): string
    {
        return empty($group_separator) ? String_Helper::get_thousands_separator() : String_Helper::convert_to_string($group_separator);
    }
    /**
     * NUMBERVALUE.
     *
     * @param mixed $value The value to format
     *                         Or can be an array of values
     * @param mixed $decimalSeparator A string with the decimal separator to use, defaults to locale defined value
     *                         Or can be an array of values
     * @param mixed $groupSeparator A string with the group/thousands separator to use, defaults to locale defined value
     *                         Or can be an array of values
     *
     * @return array<mixed>|float|string
     */
    public static function NUMBERVALUE(mixed $value = '', mixed $decimal_separator = null, mixed $group_separator = null): array|string|float
    {
        if (is_array($value) || is_array($decimal_separator) || is_array($group_separator)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $decimal_separator, $group_separator);
        }
        try {
            $value = self::convert_value($value, true);
            $decimal_separator = self::get_decimal_separator($decimal_separator);
            $group_separator = self::get_group_separator($group_separator);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        /** @var null|array<scalar>|scalar $value */
        if (!is_array($value) && !is_numeric($value)) {
            $value = String_Helper::convert_to_string($value);
            $decimal_positions = Preg::match_all_with_offsets('/' . preg_quote($decimal_separator, '/') . '/', $value, $matches);
            if ($decimal_positions > 1) {
                return Excel_Error::VALUE();
            }
            $decimal_offset = array_pop($matches[0])[1] ?? null;
            if ($decimal_offset === null || str_contains(substr($value, $decimal_offset), $group_separator)) {
                return Excel_Error::VALUE();
            }
            $value = str_replace([$group_separator, $decimal_separator], ['', '.'], $value);
            // Handle the special case of trailing % signs
            $percentage_string = rtrim($value, '%');
            if (!is_numeric($percentage_string)) {
                return Excel_Error::VALUE();
            }
            $percentage_adjustment = strlen($value) - strlen($percentage_string);
            if ($percentage_adjustment) {
                $value = (float) $percentage_string;
                $value /= 10 ** ($percentage_adjustment * 2);
            }
        }
        return is_array($value) ? Excel_Error::VALUE() : (float) $value;
    }
}