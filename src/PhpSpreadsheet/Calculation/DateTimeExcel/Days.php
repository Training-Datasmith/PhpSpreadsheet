<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTimeInterface;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Days
{
    use Array_Enabled;
    /**
     * DAYS.
     *
     * Returns the number of days between two dates
     *
     * Excel Function:
     *        DAYS(endDate, startDate)
     *
     * @param array<mixed>|DateTimeInterface|float|int|string $endDate Excel date serial value (float),
     *           PHP date timestamp (integer), PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     * @param array<mixed>|DateTimeInterface|float|int|string $startDate Excel date serial value (float),
     *           PHP date timestamp (integer), PHP DateTime object, or a standard date string
     *                         Or can be an array of date values
     *
     * @return array<mixed>|int|string Number of days between start date and end date or an error
     *         If an array of values is passed for the $startDate or $endDays,arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function between(array|DateTimeInterface|float|int|string $end_date, array|DateTimeInterface|float|int|string $start_date): array|int|string
    {
        if (is_array($end_date) || is_array($start_date)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $end_date, $start_date);
        }
        try {
            $start_date = Helpers::get_date_value($start_date);
            $end_date = Helpers::get_date_value($end_date);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Execute function
        $php_start_date_object = Shared_Date_Helper::excel_to_date_time_object($start_date);
        $php_end_date_object = Shared_Date_Helper::excel_to_date_time_object($end_date);
        $days = Excel_Error::VALUE();
        $diff = $php_start_date_object->diff($php_end_date_object);
        if (!is_bool($diff->days)) {
            $days = $diff->days;
            if ($diff->invert) {
                $days = -$days;
            }
        }
        return $days;
    }
}