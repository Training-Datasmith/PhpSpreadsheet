<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
use Throwable;
class Helpers
{
    /**
     * Identify if a year is a leap year or not.
     *
     * @param int|string $year The year to test
     *
     * @return bool TRUE if the year is a leap year, otherwise FALSE
     */
    public static function is_leap_year(int|string $year): bool
    {
        $year = (int) $year;
        return $year % 4 === 0 && $year % 100 !== 0 || $year % 400 === 0;
    }
    /**
     * getDateValue.
     *
     * @return float Excel date/time serial value
     */
    public static function get_date_value(mixed $date_value, bool $allow_bool = true): float
    {
        if (is_object($date_value)) {
            $retval = Shared_Date_Helper::php_to_excel($date_value);
            if (is_bool($retval)) {
                throw new Exception(Excel_Error::VALUE());
            }
            return $retval;
        }
        self::null_false_true_to_number($date_value, $allow_bool);
        if (!is_numeric($date_value)) {
            $save_return_date_type = Functions::get_return_date_type();
            Functions::set_return_date_type(Functions::RETURNDATE_EXCEL);
            if (is_string($date_value)) {
                $date_value = Date_Value::from_string($date_value);
            }
            Functions::set_return_date_type($save_return_date_type);
            if (!is_numeric($date_value)) {
                throw new Exception(Excel_Error::VALUE());
            }
        }
        if ($date_value < 0 && Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_OPENOFFICE) {
            throw new Exception(Excel_Error::NAN());
        }
        try {
            Shared_Date_Helper::excel_to_date_time_object((float) $date_value);
        } catch (Throwable) {
            throw new Exception(Excel_Error::NAN());
        }
        return (float) $date_value;
    }
    /**
     * getTimeValue.
     *
     * @return float|string Excel date/time serial value, or string if error
     */
    public static function get_time_value(string $time_value): string|float
    {
        $save_return_date_type = Functions::get_return_date_type();
        Functions::set_return_date_type(Functions::RETURNDATE_EXCEL);
        /** @var float|string $timeValue */
        $time_value = Time_Value::from_string($time_value);
        Functions::set_return_date_type($save_return_date_type);
        return $time_value;
    }
    /**
     * Adjust date by given months.
     *
     * @param float|int $dateValue date to be adjusted
     */
    public static function adjust_date_by_months(float|int $date_value = 0, float $adjustment_months = 0): DateTime
    {
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        $o_month = (int) $php_date_object->format('m');
        $o_year = (int) $php_date_object->format('Y');
        $adjustment_months_string = (string) $adjustment_months;
        if ($adjustment_months > 0) {
            $adjustment_months_string = '+' . $adjustment_months;
        }
        if ($adjustment_months != 0) {
            $php_date_object->modify($adjustment_months_string . ' months');
        }
        $n_month = (int) $php_date_object->format('m');
        $n_year = (int) $php_date_object->format('Y');
        $month_diff = $n_month - $o_month + ($n_year - $o_year) * 12;
        if ($month_diff != $adjustment_months) {
            $adjust_days = (int) $php_date_object->format('d');
            $adjust_days_string = '-' . $adjust_days . ' days';
            $php_date_object->modify($adjust_days_string);
        }
        return $php_date_object;
    }
    /**
     * Help reduce perceived complexity of some tests.
     */
    public static function replace_if_empty(mixed &$value, mixed $alt_value): void
    {
        $value = $value ?: $alt_value;
    }
    /**
     * Adjust year in ambiguous situations.
     */
    public static function adjust_year(string $test_val1, string $test_val2, string &$test_val3): void
    {
        if (!is_numeric($test_val1) || $test_val1 < 31) {
            if (!is_numeric($test_val2) || $test_val2 < 12) {
                if (is_numeric($test_val3) && $test_val3 < 12) {
                    $test_val3 = (string) ($test_val3 + 2000);
                }
            }
        }
    }
    /**
     * Return result in one of three formats.
     *
     * @param array{year: int, month: int, day: int, hour: int, minute: int, second: int} $dateArray
     */
    public static function return_in3formats_array(array $date_array, bool $no_frac = false): DateTime|float|int
    {
        $ret_type = Functions::get_return_date_type();
        if ($ret_type === Functions::RETURNDATE_PHP_DATETIME_OBJECT) {
            return new DateTime($date_array['year'] . '-' . $date_array['month'] . '-' . $date_array['day'] . ' ' . $date_array['hour'] . ':' . $date_array['minute'] . ':' . $date_array['second']);
        }
        $excel_date_value = Shared_Date_Helper::formatted_php_to_excel($date_array['year'], $date_array['month'], $date_array['day'], $date_array['hour'], $date_array['minute'], $date_array['second']);
        if ($ret_type === Functions::RETURNDATE_EXCEL) {
            return $no_frac ? floor($excel_date_value) : $excel_date_value;
        }
        // RETURNDATE_UNIX_TIMESTAMP)
        return Shared_Date_Helper::excel_to_timestamp($excel_date_value);
    }
    /**
     * Return result in one of three formats.
     */
    public static function return_in3formats_float(float $excel_date_value): float|int|DateTime
    {
        $ret_type = Functions::get_return_date_type();
        if ($ret_type === Functions::RETURNDATE_EXCEL) {
            return $excel_date_value;
        }
        if ($ret_type === Functions::RETURNDATE_UNIX_TIMESTAMP) {
            return Shared_Date_Helper::excel_to_timestamp($excel_date_value);
        }
        // RETURNDATE_PHP_DATETIME_OBJECT
        return Shared_Date_Helper::excel_to_date_time_object($excel_date_value);
    }
    /**
     * Return result in one of three formats.
     */
    public static function return_in3formats_object(DateTime $php_date_object): DateTime|float|int
    {
        $ret_type = Functions::get_return_date_type();
        if ($ret_type === Functions::RETURNDATE_PHP_DATETIME_OBJECT) {
            return $php_date_object;
        }
        if ($ret_type === Functions::RETURNDATE_EXCEL) {
            return (float) Shared_Date_Helper::php_to_excel($php_date_object);
        }
        // RETURNDATE_UNIX_TIMESTAMP
        $stamp = Shared_Date_Helper::php_to_excel($php_date_object);
        $stamp = is_bool($stamp) ? (int) $stamp : $stamp;
        return Shared_Date_Helper::excel_to_timestamp($stamp);
    }
    private static function base_date(): int
    {
        if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
            return 0;
        }
        if (Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_MAC_1904) {
            return 0;
        }
        return 1;
    }
    /**
     * Many functions accept null/false/true argument treated as 0/0/1.
     */
    public static function null_false_true_to_number(mixed &$number, bool $allow_bool = true): void
    {
        $number = Functions::flatten_single_value($number);
        $null_val = self::base_date();
        if ($number === null) {
            $number = $null_val;
        } elseif ($allow_bool && is_bool($number)) {
            $number = $null_val + (int) $number;
        }
    }
    /**
     * Many functions accept null argument treated as 0.
     */
    public static function validate_numeric_null(mixed $number): int|float
    {
        $number = Functions::flatten_single_value($number);
        if ($number === null) {
            return 0;
        }
        if (is_int($number)) {
            return $number;
        }
        if (is_numeric($number)) {
            return (float) $number;
        }
        throw new Exception(Excel_Error::VALUE());
    }
    /**
     * Many functions accept null/false/true argument treated as 0/0/1.
     *
     * @phpstan-assert float $number
     */
    public static function validate_not_negative(mixed $number): float
    {
        if (!is_numeric($number)) {
            throw new Exception(Excel_Error::VALUE());
        }
        if ($number >= 0) {
            return (float) $number;
        }
        throw new Exception(Excel_Error::NAN());
    }
    public static function silly1900(DateTime $php_date_object, string $mod = '-1 day'): void
    {
        $iso_date = $php_date_object->format('c');
        if ($iso_date < '1900-03-01') {
            $php_date_object->modify($mod);
        }
    }
    /** @return array{year: int, month: int, day: int, hour: int, minute: int, second: int} */
    public static function date_parse(string $string): array
    {
        /** @var array{year: int, month: int, day: int, hour: int, minute: int, second: int} */
        $temp = self::force_array(date_parse($string));
        return $temp;
    }
    /** @param mixed[] $dateArray */
    public static function date_parse_succeeded(array $date_array): bool
    {
        return $date_array['error_count'] === 0;
    }
    /**
     * Despite documentation, date_parse probably never returns false.
     * Just in case, this routine helps guarantee it.
     *
     * @param array<mixed>|false $dateArray
     *
     * @return mixed[]
     */
    private static function force_array(array|bool $date_array): array
    {
        return is_array($date_array) ? $date_array : ['error_count' => 1];
    }
    public static function float_or_int(mixed $value): float|int
    {
        $result = Functions::scalar($value);
        return is_numeric($result) ? $result + 0 : 0;
    }
}