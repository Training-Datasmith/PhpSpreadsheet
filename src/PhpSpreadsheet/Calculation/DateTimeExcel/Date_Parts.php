<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Date_Parts
{
    use Array_Enabled;
    /**
     * DAYOFMONTH.
     *
     * Returns the day of the month, for a specified date. The day is given as an integer
     * ranging from 1 to 31.
     *
     * Excel Function:
     *        DAY(dateValue)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     *
     * @return array<mixed>|int|string Day of the month
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function day(mixed $date_value): array|int|string
    {
        if (is_array($date_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $date_value);
        }
        $weird_result = self::weird_condition($date_value);
        if ($weird_result >= 0) {
            return $weird_result;
        }
        try {
            $date_value = Helpers::get_date_value($date_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        Shared_Date_Helper::round_microseconds($php_date_object);
        return (int) $php_date_object->format('j');
    }
    /**
     * MONTHOFYEAR.
     *
     * Returns the month of a date represented by a serial number.
     * The month is given as an integer, ranging from 1 (January) to 12 (December).
     *
     * Excel Function:
     *        MONTH(dateValue)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     *
     * @return array<mixed>|int|string Month of the year
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function month(mixed $date_value): array|string|int
    {
        if (is_array($date_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $date_value);
        }
        try {
            $date_value = Helpers::get_date_value($date_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($date_value < 1 && Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_WINDOWS_1900) {
            return 1;
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        Shared_Date_Helper::round_microseconds($php_date_object);
        return (int) $php_date_object->format('n');
    }
    /**
     * YEAR.
     *
     * Returns the year corresponding to a date.
     * The year is returned as an integer in the range 1900-9999.
     *
     * Excel Function:
     *        YEAR(dateValue)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     *
     * @return array<mixed>|int|string Year
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function year(mixed $date_value): array|string|int
    {
        if (is_array($date_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $date_value);
        }
        try {
            $date_value = Helpers::get_date_value($date_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($date_value < 1 && Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_WINDOWS_1900) {
            return 1900;
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        Shared_Date_Helper::round_microseconds($php_date_object);
        return (int) $php_date_object->format('Y');
    }
    /**
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     */
    private static function weird_condition(mixed $date_value): int
    {
        // Excel does not treat 0 consistently for DAY vs. (MONTH or YEAR)
        if (Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_WINDOWS_1900 && Functions::get_compatibility_mode() == Functions::COMPATIBILITY_EXCEL) {
            if (is_bool($date_value)) {
                return (int) $date_value;
            }
            if ($date_value === null) {
                return 0;
            }
            if (is_numeric($date_value) && $date_value < 1 && $date_value >= 0) {
                return 0;
            }
        }
        return -1;
    }
}