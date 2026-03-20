<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Work_Day
{
    use Array_Enabled;
    /**
     * WORKDAY.
     *
     * Returns the date that is the indicated number of working days before or after a date (the
     * starting date). Working days exclude weekends and any dates identified as holidays.
     * Use WORKDAY to exclude weekends or holidays when you calculate invoice due dates, expected
     * delivery times, or the number of days of work performed.
     *
     * Excel Function:
     *        WORKDAY(startDate,endDays[,holidays[,holiday[,...]]])
     *
     * @param array<mixed>|mixed $startDate Excel date serial value (float), PHP date timestamp (integer),
     *                                        PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|int $endDays The number of nonweekend and nonholiday days before or after
     *                                        startDate. A positive value for days yields a future date; a
     *                                        negative value yields a past date.
     *                         Or can be an array of int values
     * @param null|mixed $dateArgs An array of dates (such as holidays) to exclude from the calculation
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of values is passed for the $startDate or $endDays,arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function date(mixed $start_date, array|int|string $end_days, mixed ...$date_args): array|float|int|DateTime|string
    {
        if (is_array($start_date) || is_array($end_days)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 2, $start_date, $end_days, ...$date_args);
        }
        //    Retrieve the mandatory start date and days that are referenced in the function definition
        try {
            $start_date = Helpers::get_date_value($start_date);
            $end_days = Helpers::validate_numeric_null($end_days);
            $holiday_array = array_map(Helpers::get_date_value(...), Functions::flatten_array($date_args));
        } catch (Exception $e) {
            return $e->get_message();
        }
        $start_date = floor($start_date);
        $end_days = (int) floor($end_days);
        //    If endDays is 0, we always return startDate
        if ($end_days == 0) {
            return $start_date;
        }
        if ($end_days < 0) {
            return self::decrementing($start_date, $end_days, $holiday_array);
        }
        return self::incrementing($start_date, $end_days, $holiday_array);
    }
    /**
     * Use incrementing logic to determine Workday.
     *
     * @param array<mixed> $holidayArray
     */
    private static function incrementing(float $start_date, int $end_days, array $holiday_array): float|int|DateTime
    {
        //    Adjust the start date if it falls over a weekend
        $start_do_w = self::get_week_day($start_date, 3);
        if ($start_do_w >= 5) {
            $start_date += 7 - $start_do_w;
            --$end_days;
        }
        //    Add endDays
        $end_date = $start_date + (int) ($end_days / 5) * 7;
        $end_days = $end_days % 5;
        while ($end_days > 0) {
            ++$end_date;
            //    Adjust the calculated end date if it falls over a weekend
            $end_dow = self::get_week_day($end_date, 3);
            if ($end_dow >= 5) {
                $end_date += 7 - $end_dow;
            }
            --$end_days;
        }
        //    Test any extra holiday parameters
        if (!empty($holiday_array)) {
            $end_date = self::incrementing_array($start_date, $end_date, $holiday_array);
        }
        return Helpers::return_in3formats_float($end_date);
    }
    /** @param array<mixed> $holidayArray */
    private static function incrementing_array(float $start_date, float $end_date, array $holiday_array): float
    {
        $holiday_counted_array = $holiday_dates = [];
        foreach ($holiday_array as $holiday_date) {
            /** @var float $holidayDate */
            if (self::get_week_day($holiday_date, 3) < 5) {
                $holiday_dates[] = $holiday_date;
            }
        }
        sort($holiday_dates, SORT_NUMERIC);
        foreach ($holiday_dates as $holiday_date) {
            if ($holiday_date >= $start_date && $holiday_date <= $end_date) {
                if (!in_array($holiday_date, $holiday_counted_array)) {
                    ++$end_date;
                    $holiday_counted_array[] = $holiday_date;
                }
            }
            //    Adjust the calculated end date if it falls over a weekend
            $end_do_w = self::get_week_day($end_date, 3);
            if ($end_do_w >= 5) {
                $end_date += 7 - $end_do_w;
            }
        }
        return $end_date;
    }
    /**
     * Use decrementing logic to determine Workday.
     *
     * @param array<mixed> $holidayArray
     */
    private static function decrementing(float $start_date, int $end_days, array $holiday_array): float|int|DateTime
    {
        //    Adjust the start date if it falls over a weekend
        $start_do_w = self::get_week_day($start_date, 3);
        if ($start_do_w >= 5) {
            $start_date += -$start_do_w + 4;
            ++$end_days;
        }
        //    Add endDays
        $end_date = $start_date + (int) ($end_days / 5) * 7;
        $end_days = $end_days % 5;
        while ($end_days < 0) {
            --$end_date;
            //    Adjust the calculated end date if it falls over a weekend
            $end_dow = self::get_week_day($end_date, 3);
            if ($end_dow >= 5) {
                $end_date += 4 - $end_dow;
            }
            ++$end_days;
        }
        //    Test any extra holiday parameters
        if (!empty($holiday_array)) {
            $end_date = self::decrementing_array($start_date, $end_date, $holiday_array);
        }
        return Helpers::return_in3formats_float($end_date);
    }
    /** @param array<mixed> $holidayArray */
    private static function decrementing_array(float $start_date, float $end_date, array $holiday_array): float
    {
        $holiday_counted_array = $holiday_dates = [];
        foreach ($holiday_array as $holiday_date) {
            /** @var float $holidayDate */
            if (self::get_week_day($holiday_date, 3) < 5) {
                $holiday_dates[] = $holiday_date;
            }
        }
        rsort($holiday_dates, SORT_NUMERIC);
        foreach ($holiday_dates as $holiday_date) {
            if ($holiday_date <= $start_date && $holiday_date >= $end_date) {
                if (!in_array($holiday_date, $holiday_counted_array)) {
                    --$end_date;
                    $holiday_counted_array[] = $holiday_date;
                }
            }
            //    Adjust the calculated end date if it falls over a weekend
            $end_do_w = self::get_week_day($end_date, 3);
            /** int $endDoW */
            if ($end_do_w >= 5) {
                $end_date += -$end_do_w + 4;
            }
        }
        return $end_date;
    }
    private static function get_week_day(float $date, int $wd): int
    {
        $result = Functions::scalar(Week::day($date, $wd));
        return is_int($result) ? $result : -1;
    }
}