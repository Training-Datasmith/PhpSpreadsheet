<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
use Throwable;
class Time_Parts
{
    use Array_Enabled;
    /**
     * HOUROFDAY.
     *
     * Returns the hour of a time value.
     * The hour is given as an integer, ranging from 0 (12:00 A.M.) to 23 (11:00 P.M.).
     *
     * Excel Function:
     *        HOUR(timeValue)
     *
     * @param mixed $timeValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard time string
     *                         Or can be an array of date/time values
     *
     * @return array<mixed>|int|string Hour
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function hour(mixed $time_value): array|string|int
    {
        if (is_array($time_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $time_value);
        }
        try {
            Helpers::null_false_true_to_number($time_value);
            if (is_string($time_value) && !is_numeric($time_value)) {
                $time_value = Helpers::get_time_value($time_value);
            }
            Helpers::validate_not_negative($time_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        try {
            Shared_Date_Helper::excel_to_date_time_object($time_value);
        } catch (Throwable) {
            return Excel_Error::NAN();
        }
        $time_value = fmod($time_value, 1);
        $time_value = Shared_Date_Helper::excel_to_date_time_object($time_value);
        Shared_Date_Helper::round_microseconds($time_value);
        return (int) $time_value->format('H');
    }
    /**
     * MINUTE.
     *
     * Returns the minutes of a time value.
     * The minute is given as an integer, ranging from 0 to 59.
     *
     * Excel Function:
     *        MINUTE(timeValue)
     *
     * @param mixed $timeValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard time string
     *                         Or can be an array of date/time values
     *
     * @return array<mixed>|int|string Minute
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function minute(mixed $time_value): array|string|int
    {
        if (is_array($time_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $time_value);
        }
        try {
            Helpers::null_false_true_to_number($time_value);
            if (is_string($time_value) && !is_numeric($time_value)) {
                $time_value = Helpers::get_time_value($time_value);
            }
            Helpers::validate_not_negative($time_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        try {
            Shared_Date_Helper::excel_to_date_time_object($time_value);
        } catch (Throwable) {
            return Excel_Error::NAN();
        }
        $time_value = fmod($time_value, 1);
        $time_value = Shared_Date_Helper::excel_to_date_time_object($time_value);
        Shared_Date_Helper::round_microseconds($time_value);
        return (int) $time_value->format('i');
    }
    /**
     * SECOND.
     *
     * Returns the seconds of a time value.
     * The minute is given as an integer, ranging from 0 to 59.
     *
     * Excel Function:
     *        SECOND(timeValue)
     *
     * @param mixed $timeValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard time string
     *                         Or can be an array of date/time values
     *
     * @return array<mixed>|int|string Second
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function second(mixed $time_value): array|string|int
    {
        if (is_array($time_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $time_value);
        }
        try {
            Helpers::null_false_true_to_number($time_value);
            if (is_string($time_value) && !is_numeric($time_value)) {
                $time_value = Helpers::get_time_value($time_value);
            }
            Helpers::validate_not_negative($time_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        try {
            Shared_Date_Helper::excel_to_date_time_object($time_value);
        } catch (Throwable) {
            return Excel_Error::NAN();
        }
        $time_value = fmod($time_value, 1);
        $time_value = Shared_Date_Helper::excel_to_date_time_object($time_value);
        Shared_Date_Helper::round_microseconds($time_value);
        return (int) $time_value->format('s');
    }
}