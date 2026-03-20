<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use Composer\Pcre\Preg;
use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Time_Value
{
    use Array_Enabled;
    private const EXTRACT_TIME = '/\b' . '(\d+)' . '(:' . '(\d+' . '(:\d+' . '([.]\d+)?' . ')?' . ')' . '(\s*(a|p))?' . ')' . '/i';
    /**
     * TIMEVALUE.
     *
     * Returns a value that represents a particular time.
     * Use TIMEVALUE to convert a time represented by a text string to an Excel or PHP date/time stamp
     * value.
     *
     * NOTE: When used in a Cell Formula, MS Excel changes the cell format so that it matches the time
     * format of your regional settings. PhpSpreadsheet does not change cell formatting in this way.
     *
     * Excel Function:
     *        TIMEVALUE(timeValue)
     *
     * @param null|array<mixed>|bool|float|int|string $timeValue A text string that represents a time in any one of the Microsoft
     *                                    Excel time formats; for example, "6:45 PM" and "18:45" text strings
     *                                    within quotation marks that represent time.
     *                                    Date information in time_text is ignored.
     *                         Or can be an array of date/time values
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function from_string(null|array|string|int|bool|float $time_value): array|string|DateTime|int|float
    {
        if (is_array($time_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $time_value);
        }
        // try to parse as time iff there is at least one digit
        if (is_string($time_value) && !Preg::is_match('/\d/', $time_value)) {
            return Excel_Error::VALUE();
        }
        $time_value = trim((string) $time_value, '"');
        if (Preg::is_match(self::EXTRACT_TIME, $time_value, $matches)) {
            if (empty($matches[6])) {
                // am/pm
                $hour = (int) $matches[0];
                $time_value = $hour % 24 . $matches[2];
            } elseif ($matches[6] === $matches[7]) {
                // Excel wants space before am/pm
                return Excel_Error::VALUE();
            } else {
                $time_value = $matches[0] . 'm';
            }
        }
        $php_date_array = Helpers::date_parse($time_value);
        $ret_value = Excel_Error::VALUE();
        if (Helpers::date_parse_succeeded($php_date_array)) {
            $hour = $php_date_array['hour'];
            $minute = $php_date_array['minute'];
            $second = $php_date_array['second'];
            // OpenOffice-specific code removed - it works just like Excel
            $excel_date_value = Shared_Date_Helper::formatted_php_to_excel(1900, 1, 1, $hour, $minute, $second) - 1;
            $ret_type = Functions::get_return_date_type();
            if ($ret_type === Functions::RETURNDATE_EXCEL) {
                $ret_value = $excel_date_value;
            } elseif ($ret_type === Functions::RETURNDATE_UNIX_TIMESTAMP) {
                $ret_value = Shared_Date_Helper::excel_to_timestamp($excel_date_value + 25569) - 3600;
            } else {
                $ret_value = new DateTime('1900-01-01 ' . $php_date_array['hour'] . ':' . $php_date_array['minute'] . ':' . $php_date_array['second']);
            }
        }
        return $ret_value;
    }
}