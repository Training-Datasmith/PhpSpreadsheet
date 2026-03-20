<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use DateTimeInterface;
use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Helpers
{
    /**
     * daysPerYear.
     *
     * Returns the number of days in a specified year, as defined by the "basis" value
     *
     * @param mixed $year The year against which we're testing, expect int|string
     * @param int|string $basis The type of day count:
     *                              0 or omitted US (NASD)   360
     *                              1                        Actual (365 or 366 in a leap year)
     *                              2                        360
     *                              3                        365
     *                              4                        European 360
     *
     * @return int|string Result, or a string containing an error
     */
    public static function days_per_year(mixed $year, $basis = 0): string|int
    {
        if (!is_int($year) && !is_string($year)) {
            return Excel_Error::VALUE();
        }
        if (!is_numeric($basis)) {
            return Excel_Error::NAN();
        }
        switch ($basis) {
            case Financial_Constants::BASIS_DAYS_PER_YEAR_NASD:
            case Financial_Constants::BASIS_DAYS_PER_YEAR_360:
            case Financial_Constants::BASIS_DAYS_PER_YEAR_360_EUROPEAN:
                return 360;
            case Financial_Constants::BASIS_DAYS_PER_YEAR_365:
                return 365;
            case Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL:
                return Date_Time_Excel\Helpers::is_leap_year($year) ? 366 : 365;
        }
        return Excel_Error::NAN();
    }
    /**
     * isLastDayOfMonth.
     *
     * Returns a boolean TRUE/FALSE indicating if this date is the last date of the month
     *
     * @param DateTimeInterface $date The date for testing
     */
    public static function is_last_day_of_month(DateTimeInterface $date): bool
    {
        return $date->format('d') === $date->format('t');
    }
}