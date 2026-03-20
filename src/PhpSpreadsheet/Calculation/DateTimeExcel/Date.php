<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Date
{
    use Array_Enabled;
    /**
     * DATE.
     *
     * The DATE function returns a value that represents a particular date.
     *
     * NOTE: When used in a Cell Formula, MS Excel changes the cell format so that it matches the date
     * format of your regional settings. PhpSpreadsheet does not change cell formatting in this way.
     *
     * Excel Function:
     *        DATE(year,month,day)
     *
     * PhpSpreadsheet is a lot more forgiving than MS Excel when passing non-numeric values to this function.
     * A Month name or abbreviation (English only at this point) such as 'January' or 'Jan' will still be accepted,
     *     as will a day value with a suffix (e.g. '21st' rather than simply 21); again only English language.
     *
     * @param array<mixed>|float|int|string $year The value of the year argument can include one to four digits.
     *                                Excel interprets the year argument according to the configured
     *                                date system: 1900 or 1904.
     *                                If year is between 0 (zero) and 1899 (inclusive), Excel adds that
     *                                value to 1900 to calculate the year. For example, DATE(108,1,2)
     *                                returns January 2, 2008 (1900+108).
     *                                If year is between 1900 and 9999 (inclusive), Excel uses that
     *                                value as the year. For example, DATE(2008,1,2) returns January 2,
     *                                2008.
     *                                If year is less than 0 or is 10000 or greater, Excel returns the
     *                                #NUM! error value.
     * @param array<mixed>|float|int|string $month A positive or negative integer representing the month of the year
     *                                from 1 to 12 (January to December).
     *                                If month is greater than 12, month adds that number of months to
     *                                the first month in the year specified. For example, DATE(2008,14,2)
     *                                returns the serial number representing February 2, 2009.
     *                                If month is less than 1, month subtracts the magnitude of that
     *                                number of months, plus 1, from the first month in the year
     *                                specified. For example, DATE(2008,-3,2) returns the serial number
     *                                representing September 2, 2007.
     * @param array<mixed>|float|int|string $day A positive or negative integer representing the day of the month
     *                                from 1 to 31.
     *                                If day is greater than the number of days in the month specified,
     *                                day adds that number of days to the first day in the month. For
     *                                example, DATE(2008,1,35) returns the serial number representing
     *                                February 4, 2008.
     *                                If day is less than 1, day subtracts the magnitude that number of
     *                                days, plus one, from the first day of the month specified. For
     *                                example, DATE(2008,1,-15) returns the serial number representing
     *                                December 16, 2007.
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function from_ymd(array|float|int|string $year, null|array|bool|float|int|string $month, array|float|int|string $day): float|int|DateTime|string|array
    {
        if (is_array($year) || is_array($month) || is_array($day)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $year, $month, $day);
        }
        $base_year = Shared_Date_Helper::get_excel_calendar();
        try {
            $year = self::get_year($year, $base_year);
            $month = self::get_month($month);
            $day = self::get_day($day);
            self::adjust_year_month($year, $month, $base_year);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $excel_date_value = Shared_Date_Helper::formatted_php_to_excel($year, $month, $day);
        return Helpers::return_in3formats_float($excel_date_value);
    }
    /**
     * Convert year from multiple formats to int.
     */
    private static function get_year(mixed $year, int $base_year): int
    {
        if ($year === null) {
            $year = 0;
        } elseif (is_scalar($year)) {
            $year = String_Helper::test_string_as_numeric((string) $year);
        }
        if (!is_numeric($year)) {
            throw new Exception(Excel_Error::VALUE());
        }
        $year = (int) $year;
        if ($year < $base_year - 1900) {
            throw new Exception(Excel_Error::NAN());
        }
        if ($base_year - 1900 !== 0 && $year < $base_year && $year >= 1900) {
            throw new Exception(Excel_Error::NAN());
        }
        if ($year < $base_year && $year >= $base_year - 1900) {
            $year += 1900;
        }
        return $year;
    }
    /**
     * Convert month from multiple formats to int.
     */
    private static function get_month(mixed $month): int
    {
        if (is_string($month)) {
            if (!is_numeric($month)) {
                $month = Shared_Date_Helper::month_string_to_number($month);
            }
        } elseif ($month === null) {
            $month = 0;
        } elseif (is_bool($month)) {
            $month = (int) $month;
        }
        if (!is_numeric($month)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (int) $month;
    }
    /**
     * Convert day from multiple formats to int.
     */
    private static function get_day(mixed $day): int
    {
        if (is_string($day) && !is_numeric($day)) {
            $day = Shared_Date_Helper::day_string_to_number($day);
        }
        if ($day === null) {
            $day = 0;
        } elseif (is_scalar($day)) {
            $day = String_Helper::test_string_as_numeric((string) $day);
        }
        if (!is_numeric($day)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (int) $day;
    }
    private static function adjust_year_month(int &$year, int &$month, int $base_year): void
    {
        if ($month < 1) {
            //    Handle year/month adjustment if month < 1
            --$month;
            $year += (int) (ceil($month / 12) - 1);
            $month = 13 - abs($month % 12);
        } elseif ($month > 12) {
            //    Handle year/month adjustment if month > 12
            $year += intdiv($month, 12);
            $month = $month % 12;
        }
        // Re-validate the year parameter after adjustments
        if ($year < $base_year || $year >= 10000) {
            throw new Exception(Excel_Error::NAN());
        }
    }
}