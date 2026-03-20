<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Days360
{
    use Array_Enabled;
    /**
     * DAYS360.
     *
     * Returns the number of days between two dates based on a 360-day year (twelve 30-day months),
     * which is used in some accounting calculations. Use this function to help compute payments if
     * your accounting system is based on twelve 30-day months.
     *
     * Excel Function:
     *        DAYS360(startDate,endDate[,method])
     *
     * @param array|mixed $startDate Excel date serial value (float), PHP date timestamp (integer),
     *                                        PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array|mixed $endDate Excel date serial value (float), PHP date timestamp (integer),
     *                                        PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array|mixed $method US or European Method as a bool
     *                                        FALSE or omitted: U.S. (NASD) method. If the starting date is
     *                                        the last day of a month, it becomes equal to the 30th of the
     *                                        same month. If the ending date is the last day of a month and
     *                                        the starting date is earlier than the 30th of a month, the
     *                                        ending date becomes equal to the 1st of the next month;
     *                                        otherwise the ending date becomes equal to the 30th of the
     *                                        same month.
     *                                        TRUE: European method. Starting dates and ending dates that
     *                                        occur on the 31st of a month become equal to the 30th of the
     *                                        same month.
     *                         Or can be an array of methods
     *
     * @return array<mixed>|int|string Number of days between start date and end date
     *         If an array of values is passed for the $startDate or $endDays,arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function between(mixed $start_date = 0, mixed $end_date = 0, mixed $method = false): array|string|int
    {
        if (is_array($start_date) || is_array($end_date) || is_array($method)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $start_date, $end_date, $method);
        }
        try {
            $start_date = Helpers::get_date_value($start_date);
            $end_date = Helpers::get_date_value($end_date);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (!is_bool($method)) {
            return Excel_Error::VALUE();
        }
        // Execute function
        $php_start_date_object = Shared_Date_Helper::excel_to_date_time_object($start_date);
        $start_day = $php_start_date_object->format('j');
        $start_month = $php_start_date_object->format('n');
        $start_year = $php_start_date_object->format('Y');
        $php_end_date_object = Shared_Date_Helper::excel_to_date_time_object($end_date);
        $end_day = $php_end_date_object->format('j');
        $end_month = $php_end_date_object->format('n');
        $end_year = $php_end_date_object->format('Y');
        return self::date_diff360((int) $start_day, (int) $start_month, (int) $start_year, (int) $end_day, (int) $end_month, (int) $end_year, !$method);
    }
    /**
     * Return the number of days between two dates based on a 360-day calendar.
     */
    private static function date_diff360(int $start_day, int $start_month, int $start_year, int $end_day, int $end_month, int $end_year, bool $method_us): int
    {
        $start_day = self::get_start_day($start_day, $start_month, $start_year, $method_us);
        $end_day = self::get_end_day($end_day, $end_month, $end_year, $start_day, $method_us);
        return $end_day + $end_month * 30 + $end_year * 360 - $start_day - $start_month * 30 - $start_year * 360;
    }
    private static function get_start_day(int $start_day, int $start_month, int $start_year, bool $method_us): int
    {
        if ($start_day == 31) {
            --$start_day;
        } elseif ($method_us && ($start_month == 2 && ($start_day == 29 || $start_day == 28 && !Helpers::is_leap_year($start_year)))) {
            $start_day = 30;
        }
        return $start_day;
    }
    private static function get_end_day(int $end_day, int &$end_month, int &$end_year, int $start_day, bool $method_us): int
    {
        if ($end_day == 31) {
            if ($method_us && $start_day != 30) {
                $end_day = 1;
                if ($end_month == 12) {
                    ++$end_year;
                    $end_month = 1;
                } else {
                    ++$end_month;
                }
            } else {
                $end_day = 30;
            }
        }
        return $end_day;
    }
}