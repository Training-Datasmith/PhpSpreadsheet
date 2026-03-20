<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Week
{
    use Array_Enabled;
    /**
     * WEEKNUM.
     *
     * Returns the week of the year for a specified date.
     * The WEEKNUM function considers the week containing January 1 to be the first week of the year.
     * However, there is a European standard that defines the first week as the one with the majority
     * of days (four or more) falling in the new year. This means that for years in which there are
     * three days or less in the first week of January, the WEEKNUM function returns week numbers
     * that are incorrect according to the European standard.
     *
     * Excel Function:
     *        WEEKNUM(dateValue[,style])
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|int $method Week begins on Sunday or Monday
     *                                        1 or omitted    Week begins on Sunday.
     *                                        2                Week begins on Monday.
     *                                        11               Week begins on Monday.
     *                                        12               Week begins on Tuesday.
     *                                        13               Week begins on Wednesday.
     *                                        14               Week begins on Thursday.
     *                                        15               Week begins on Friday.
     *                                        16               Week begins on Saturday.
     *                                        17               Week begins on Sunday.
     *                                        21               ISO (Jan. 4 is week 1, begins on Monday).
     *                         Or can be an array of methods
     *
     * @return array<mixed>|int|string Week Number
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function number(mixed $date_value, array|int|string|null $method = Constants::STARTWEEK_SUNDAY): array|int|string
    {
        if (is_array($date_value) || is_array($method)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $date_value, $method);
        }
        $orig_date_value_null = empty($date_value);
        try {
            $method = self::validate_method($method);
            if ($date_value === null) {
                // boolean not allowed
                $date_value = Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_MAC_1904 || $method === Constants::DOW_SUNDAY ? 0 : 1;
            }
            $date_value = self::validate_date_value($date_value);
            if (!$date_value && self::buggy_week_num1900($method)) {
                // This seems to be an additional Excel bug.
                return 0;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        if ($method == Constants::STARTWEEK_MONDAY_ISO) {
            Helpers::silly1900($php_date_object);
            return (int) $php_date_object->format('W');
        }
        if (self::buggy_week_num1904($method, $orig_date_value_null, $php_date_object)) {
            return 0;
        }
        Helpers::silly1900($php_date_object, '+ 5 years');
        // 1905 calendar matches
        $day_of_year = (int) $php_date_object->format('z');
        $php_date_object->modify('-' . $day_of_year . ' days');
        $first_day_of_first_week = (int) $php_date_object->format('w');
        $days_in_first_week = (6 - $first_day_of_first_week + $method) % 7;
        $days_in_first_week += 7 * !$days_in_first_week;
        $end_first_week = $days_in_first_week - 1;
        $week_of_year = floor(($day_of_year - $end_first_week + 13) / 7);
        return (int) $week_of_year;
    }
    /**
     * ISOWEEKNUM.
     *
     * Returns the ISO 8601 week number of the year for a specified date.
     *
     * Excel Function:
     *        ISOWEEKNUM(dateValue)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     *
     * @return array<mixed>|int|string Week Number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function iso_week_number(mixed $date_value): array|int|string
    {
        if (is_array($date_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $date_value);
        }
        if (self::apparent_bug($date_value)) {
            return 52;
        }
        try {
            $date_value = Helpers::get_date_value($date_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        Helpers::silly1900($php_date_object);
        return (int) $php_date_object->format('W');
    }
    /**
     * WEEKDAY.
     *
     * Returns the day of the week for a specified date. The day is given as an integer
     * ranging from 0 to 7 (dependent on the requested style).
     *
     * Excel Function:
     *        WEEKDAY(dateValue[,style])
     *
     * @param null|array<mixed>|bool|float|int|string $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param mixed $style A number that determines the type of return value
     *                                        1 or omitted    Numbers 1 (Sunday) through 7 (Saturday).
     *                                        2                Numbers 1 (Monday) through 7 (Sunday).
     *                                        3                Numbers 0 (Monday) through 6 (Sunday).
     *                         Or can be an array of styles
     *
     * @return array<mixed>|int|string Day of the week value
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function day(null|array|float|int|string|bool $date_value, mixed $style = 1): array|string|int
    {
        if (is_array($date_value) || is_array($style)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $date_value, $style);
        }
        try {
            $date_value = Helpers::get_date_value($date_value);
            $style = self::validate_style($style);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_date_object = Shared_Date_Helper::excel_to_date_time_object($date_value);
        Helpers::silly1900($php_date_object);
        $do_w = (int) $php_date_object->format('w');
        switch ($style) {
            case 1:
                ++$do_w;
                break;
            case 2:
                $do_w = self::dow0Becomes7($do_w);
                break;
            case 3:
                $do_w = self::dow0Becomes7($do_w) - 1;
                break;
        }
        return $do_w;
    }
    /**
     * @param mixed $style expect int
     */
    private static function validate_style(mixed $style): int
    {
        if (!is_numeric($style)) {
            throw new Exception(Excel_Error::VALUE());
        }
        $style = (int) $style;
        if ($style < 1 || $style > 3) {
            throw new Exception(Excel_Error::NAN());
        }
        return $style;
    }
    private static function dow0Becomes7(int $do_w): int
    {
        return $do_w === 0 ? 7 : $do_w;
    }
    /**
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     */
    private static function apparent_bug(mixed $date_value): bool
    {
        if (Shared_Date_Helper::get_excel_calendar() !== Shared_Date_Helper::CALENDAR_MAC_1904) {
            if (is_bool($date_value)) {
                return true;
            }
            if (is_numeric($date_value) && !(int) $date_value) {
                return true;
            }
        }
        return false;
    }
    /**
     * Validate dateValue parameter.
     */
    private static function validate_date_value(mixed $date_value): float
    {
        if (is_bool($date_value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return Helpers::get_date_value($date_value);
    }
    /**
     * Validate method parameter.
     */
    private static function validate_method(mixed $method): int
    {
        if ($method === null) {
            $method = Constants::STARTWEEK_SUNDAY;
        }
        if (!is_numeric($method)) {
            throw new Exception(Excel_Error::VALUE());
        }
        $method = (int) $method;
        if (!array_key_exists($method, Constants::METHODARR)) {
            throw new Exception(Excel_Error::NAN());
        }
        return Constants::METHODARR[$method];
    }
    private static function buggy_week_num1900(int $method): bool
    {
        return $method === Constants::DOW_SUNDAY && Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_WINDOWS_1900;
    }
    private static function buggy_week_num1904(int $method, bool $orig_null, DateTime $date_object): bool
    {
        // This appears to be another Excel bug.
        return $method === Constants::DOW_SUNDAY && Shared_Date_Helper::get_excel_calendar() === Shared_Date_Helper::CALENDAR_MAC_1904 && !$orig_null && $date_object->format('Y-m-d') === '1904-01-01';
    }
}