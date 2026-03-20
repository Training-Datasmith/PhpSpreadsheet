<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateInterval;
use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Difference
{
    use Array_Enabled;
    /**
     * DATEDIF.
     *
     * @param mixed $startDate Excel date serial value, PHP date/time stamp, PHP DateTime object
     *                                    or a standard date string
     *                         Or can be an array of date values
     * @param mixed $endDate Excel date serial value, PHP date/time stamp, PHP DateTime object
     *                                    or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|string $unit Or can be an array of unit values
     *
     * @return array<mixed>|int|string Interval between the dates
     *         If an array of values is passed for the $startDate or $endDays,arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function interval(mixed $start_date, mixed $end_date, array|string $unit = 'D'): array|string|int
    {
        if (is_array($start_date) || is_array($end_date) || is_array($unit)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $start_date, $end_date, $unit);
        }
        try {
            $start_date = Helpers::get_date_value($start_date);
            $end_date = Helpers::get_date_value($end_date);
            $difference = self::initial_diff($start_date, $end_date);
            $unit = strtoupper($unit);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_start_date_object = Shared_Date_Helper::excel_to_date_time_object($start_date);
        $start_days = (int) $php_start_date_object->format('j');
        //$startMonths = (int) $PHPStartDateObject->format('n');
        $start_years = (int) $php_start_date_object->format('Y');
        $php_end_date_object = Shared_Date_Helper::excel_to_date_time_object($end_date);
        $end_days = (int) $php_end_date_object->format('j');
        //$endMonths = (int) $PHPEndDateObject->format('n');
        $end_years = (int) $php_end_date_object->format('Y');
        $php_diff_date_object = $php_end_date_object->diff($php_start_date_object);
        $ret_val = false;
        $ret_val = self::replace_ret_value($ret_val, $unit, 'D') ?? self::datedif_d($difference);
        $ret_val = self::replace_ret_value($ret_val, $unit, 'M') ?? self::datedif_m($php_diff_date_object);
        $ret_val = self::replace_ret_value($ret_val, $unit, 'MD') ?? self::datedif_md($start_days, $end_days, $php_end_date_object, $php_diff_date_object);
        $ret_val = self::replace_ret_value($ret_val, $unit, 'Y') ?? self::datedif_y($php_diff_date_object);
        $ret_val = self::replace_ret_value($ret_val, $unit, 'YD') ?? self::datedif_yd($difference, $start_years, $end_years, $php_start_date_object, $php_end_date_object);
        $ret_val = self::replace_ret_value($ret_val, $unit, 'YM') ?? self::datedif_ym($php_diff_date_object);
        return is_bool($ret_val) ? Excel_Error::VALUE() : $ret_val;
    }
    private static function initial_diff(float $start_date, float $end_date): float
    {
        // Validate parameters
        if ($start_date > $end_date) {
            throw new Exception(Excel_Error::NAN());
        }
        return $end_date - $start_date;
    }
    /**
     * Decide whether it's time to set retVal.
     */
    private static function replace_ret_value(bool|int $ret_val, string $unit, string $compare): null|bool|int
    {
        if ($ret_val !== false || $unit !== $compare) {
            return $ret_val;
        }
        return null;
    }
    private static function datedif_d(float $difference): int
    {
        return (int) $difference;
    }
    private static function datedif_m(DateInterval $php_diff_date_object): int
    {
        return 12 * (int) $php_diff_date_object->format('%y') + (int) $php_diff_date_object->format('%m');
    }
    private static function datedif_md(int $start_days, int $end_days, DateTime $php_end_date_object, DateInterval $php_diff_date_object): int
    {
        if ($end_days < $start_days) {
            $ret_val = $end_days;
            $php_end_date_object->modify('-' . $end_days . ' days');
            $adjust_days = (int) $php_end_date_object->format('j');
            $ret_val += $adjust_days - $start_days;
        } else {
            $ret_val = (int) $php_diff_date_object->format('%d');
        }
        return $ret_val;
    }
    private static function datedif_y(DateInterval $php_diff_date_object): int
    {
        return (int) $php_diff_date_object->format('%y');
    }
    private static function datedif_yd(float $difference, int $start_years, int $end_years, DateTime $php_start_date_object, DateTime $php_end_date_object): int
    {
        $ret_val = (int) $difference;
        if ($end_years > $start_years) {
            $is_leap_start_year = $php_start_date_object->format('L');
            $was_leap_end_year = $php_end_date_object->format('L');
            // Adjust end year to be as close as possible as start year
            while ($php_end_date_object >= $php_start_date_object) {
                $php_end_date_object->modify('-1 year');
                //$endYears = $PHPEndDateObject->format('Y');
            }
            $php_end_date_object->modify('+1 year');
            // Get the result
            $ret_val = (int) $php_end_date_object->diff($php_start_date_object)->days;
            // Adjust for leap years cases
            $is_leap_end_year = $php_end_date_object->format('L');
            $limit = new DateTime($php_end_date_object->format('Y-02-29'));
            if (!$is_leap_start_year && !$was_leap_end_year && $is_leap_end_year && $php_end_date_object >= $limit) {
                --$ret_val;
            }
        }
        return $ret_val;
    }
    private static function datedif_ym(DateInterval $php_diff_date_object): int
    {
        return (int) $php_diff_date_object->format('%m');
    }
}