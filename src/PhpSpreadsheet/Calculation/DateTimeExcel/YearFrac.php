<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Year_Frac
{
    use Array_Enabled;
    /**
     * YEARFRAC.
     *
     * Calculates the fraction of the year represented by the number of whole days between two dates
     * (the start_date and the end_date).
     * Use the YEARFRAC worksheet function to identify the proportion of a whole year's benefits or
     * obligations to assign to a specific term.
     *
     * Excel Function:
     *        YEARFRAC(startDate,endDate[,method])
     * See https://lists.oasis-open.org/archives/office-formula/200806/msg00039.html
     *     for description of algorithm used in Excel
     *
     * @param mixed $startDate Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of values
     * @param mixed $endDate Excel date serial value (float), PHP date timestamp (integer),
     *                                    PHP DateTime object, or a standard date string
     *                         Or can be an array of methods
     * @param array<mixed>|int $method Method used for the calculation
     *                                        0 or omitted    US (NASD) 30/360
     *                                        1                Actual/actual
     *                                        2                Actual/360
     *                                        3                Actual/365
     *                                        4                European 30/360
     *                         Or can be an array of methods
     *
     * @return array<mixed>|float|int|string fraction of the year, or a string containing an error
     *         If an array of values is passed for the $startDate or $endDays,arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function fraction(mixed $start_date, mixed $end_date, array|int|string|null $method = 0): array|string|int|float
    {
        if (is_array($start_date) || is_array($end_date) || is_array($method)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $start_date, $end_date, $method);
        }
        try {
            $method = (int) Helpers::validate_numeric_null($method);
            $s_date = Helpers::get_date_value($start_date);
            $e_date = Helpers::get_date_value($end_date);
            $s_date = self::excel_bug($s_date, $start_date, $end_date, $method);
            $e_date = self::excel_bug($e_date, $end_date, $start_date, $method);
            $start_date = min($s_date, $e_date);
            $end_date = max($s_date, $e_date);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return match ($method) {
            0 => Helpers::float_or_int(Days360::between($start_date, $end_date)) / 360,
            1 => self::method1($start_date, $end_date),
            2 => Helpers::float_or_int(Difference::interval($start_date, $end_date)) / 360,
            3 => Helpers::float_or_int(Difference::interval($start_date, $end_date)) / 365,
            4 => Helpers::float_or_int(Days360::between($start_date, $end_date, true)) / 360,
            default => Excel_Error::NAN(),
        };
    }
    /**
     * Excel 1900 calendar treats date argument of null as 1900-01-00. Really.
     */
    private static function excel_bug(float $s_date, mixed $start_date, mixed $end_date, int $method): float
    {
        if (Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_OPENOFFICE && Shared_Date_Helper::get_excel_calendar() !== Shared_Date_Helper::CALENDAR_MAC_1904) {
            if ($end_date === null && $start_date !== null) {
                if (Date_Parts::month($s_date) == 12 && Date_Parts::day($s_date) === 31 && $method === 0) {
                    $s_date += 2;
                } else {
                    ++$s_date;
                }
            }
        }
        return $s_date;
    }
    private static function method1(float $start_date, float $end_date): float
    {
        $days = Helpers::float_or_int(Difference::interval($start_date, $end_date));
        $start_year = (int) Date_Parts::year($start_date);
        $end_year = (int) Date_Parts::year($end_date);
        $years = $end_year - $start_year + 1;
        $start_month = (int) Date_Parts::month($start_date);
        $start_day = (int) Date_Parts::day($start_date);
        $end_month = (int) Date_Parts::month($end_date);
        $end_day = (int) Date_Parts::day($end_date);
        $start_month_day = 100 * $start_month + $start_day;
        $end_month_day = 100 * $end_month + $end_day;
        if ($years == 1) {
            $tmp_calc_annual_basis = 365 + (int) Helpers::is_leap_year($end_year);
        } elseif ($years == 2 && $start_month_day >= $end_month_day) {
            if (Helpers::is_leap_year($start_year)) {
                $tmp_calc_annual_basis = 365 + (int) ($start_month_day <= 229);
            } elseif (Helpers::is_leap_year($end_year)) {
                $tmp_calc_annual_basis = 365 + (int) ($end_month_day >= 229);
            } else {
                $tmp_calc_annual_basis = 365;
            }
        } else {
            $tmp_calc_annual_basis = 0;
            for ($year = $start_year; $year <= $end_year; ++$year) {
                $tmp_calc_annual_basis += 365 + (int) Helpers::is_leap_year($year);
            }
            $tmp_calc_annual_basis /= $years;
        }
        return $days / $tmp_calc_annual_basis;
    }
}