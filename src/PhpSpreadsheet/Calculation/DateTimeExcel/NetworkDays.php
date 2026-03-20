<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Network_Days
{
    use Array_Enabled;
    /**
     * NETWORKDAYS.
     *
     * Returns the number of whole working days between start_date and end_date. Working days
     * exclude weekends and any dates identified in holidays.
     * Use NETWORKDAYS to calculate employee benefits that accrue based on the number of days
     * worked during a specific term.
     *
     * Excel Function:
     *        NETWORKDAYS(startDate,endDate[,holidays[,holiday[,...]]])
     *
     * @param mixed $startDate Excel date serial value (float), PHP date timestamp (integer),
     *                                            PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param mixed $endDate Excel date serial value (float), PHP date timestamp (integer),
     *                                            PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param mixed $dateArgs An array of dates (such as holidays) to exclude from the calculation
     *
     * @return array<mixed>|int|string Interval between the dates
     *         If an array of values is passed for the $startDate or $endDate arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function count(mixed $start_date, mixed $end_date, mixed ...$date_args): array|string|int
    {
        if (is_array($start_date) || is_array($end_date)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 2, $start_date, $end_date, ...$date_args);
        }
        try {
            //    Retrieve the mandatory start and end date that are referenced in the function definition
            $s_date = Helpers::get_date_value($start_date);
            $e_date = Helpers::get_date_value($end_date);
            $start_date = min($s_date, $e_date);
            $end_date = max($s_date, $e_date);
            //    Get the optional days
            $date_args = Functions::flatten_array($date_args);
            //    Test any extra holiday parameters
            $holiday_array = [];
            foreach ($date_args as $holiday_date) {
                $holiday_array[] = Helpers::get_date_value($holiday_date);
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $start_dow = self::calc_start_dow($start_date);
        $end_dow = self::calc_end_dow($end_date);
        $whole_week_days = (int) floor(($end_date - $start_date) / 7) * 5;
        $part_week_days = self::calc_part_week_days($start_dow, $end_dow);
        //    Test any extra holiday parameters
        $holiday_counted_array = [];
        foreach ($holiday_array as $holiday_date) {
            if (!($holiday_date >= $start_date)) {
                continue;
            }
            if (!($holiday_date <= $end_date)) {
                continue;
            }
            if (!(Week::day($holiday_date, 2) < 6)) {
                continue;
            }
            if (in_array($holiday_date, $holiday_counted_array)) {
                continue;
            }
            --$part_week_days;
            $holiday_counted_array[] = $holiday_date;
        }
        return self::apply_sign($whole_week_days + $part_week_days, $s_date, $e_date);
    }
    private static function calc_start_dow(float $start_date): int
    {
        $start_dow = 6 - (int) Week::day($start_date, 2);
        if ($start_dow < 0) {
            return 5;
        }
        return $start_dow;
    }
    private static function calc_end_dow(float $end_date): int
    {
        $end_dow = (int) Week::day($end_date, 2);
        if ($end_dow >= 6) {
            return 0;
        }
        return $end_dow;
    }
    private static function calc_part_week_days(int $start_dow, int $end_dow): int
    {
        $part_week_days = $end_dow + $start_dow;
        if ($part_week_days > 5) {
            $part_week_days -= 5;
        }
        return $part_week_days;
    }
    private static function apply_sign(int $result, float $s_date, float $e_date): int
    {
        return $s_date > $e_date ? -$result : $result;
    }
}