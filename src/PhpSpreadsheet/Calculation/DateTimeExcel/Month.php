<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Month
{
    use Array_Enabled;
    /**
     * EDATE.
     *
     * Returns the serial number that represents the date that is the indicated number of months
     * before or after a specified date (the start_date).
     * Use EDATE to calculate maturity dates or due dates that fall on the same day of the month
     * as the date of issue.
     *
     * Excel Function:
     *        EDATE(dateValue,adjustmentMonths)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                        PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|int $adjustmentMonths The number of months before or after start_date.
     *                                        A positive value for months yields a future date;
     *                                        a negative value yields a past date.
     *                         Or can be an array of adjustment values
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function adjust(mixed $date_value, array|string|bool|float|int $adjustment_months): DateTime|float|int|string|array
    {
        if (is_array($date_value) || is_array($adjustment_months)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $date_value, $adjustment_months);
        }
        try {
            $date_value = Helpers::get_date_value($date_value, false);
            $adjustment_months = Helpers::validate_numeric_null($adjustment_months);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $date_value = floor($date_value);
        $adjustment_months = floor($adjustment_months);
        // Execute function
        $php_date_object = Helpers::adjust_date_by_months($date_value, $adjustment_months);
        return Helpers::return_in3formats_object($php_date_object);
    }
    /**
     * EOMONTH.
     *
     * Returns the date value for the last day of the month that is the indicated number of months
     * before or after start_date.
     * Use EOMONTH to calculate maturity dates or due dates that fall on the last day of the month.
     *
     * Excel Function:
     *        EOMONTH(dateValue,adjustmentMonths)
     *
     * @param mixed $dateValue Excel date serial value (float), PHP date timestamp (integer),
     *                                        PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|int $adjustmentMonths The number of months before or after start_date.
     *                                        A positive value for months yields a future date;
     *                                        a negative value yields a past date.
     *                         Or can be an array of adjustment values
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function last_day(mixed $date_value, array|float|int|bool|string $adjustment_months): array|string|DateTime|float|int
    {
        if (is_array($date_value) || is_array($adjustment_months)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $date_value, $adjustment_months);
        }
        try {
            $date_value = Helpers::get_date_value($date_value, false);
            $adjustment_months = Helpers::validate_numeric_null($adjustment_months);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $date_value = floor($date_value);
        $adjustment_months = floor($adjustment_months);
        // Execute function
        $php_date_object = Helpers::adjust_date_by_months($date_value, $adjustment_months + 1);
        $adjust_days = (int) $php_date_object->format('d');
        $adjust_days_string = '-' . $adjust_days . ' days';
        $php_date_object->modify($adjust_days_string);
        return Helpers::return_in3formats_object($php_date_object);
    }
}