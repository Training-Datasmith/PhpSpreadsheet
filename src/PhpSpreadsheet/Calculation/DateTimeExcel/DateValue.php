<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;

use DateTime;
use DateTimeImmutable;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDateHelper;
class Date_Value
{
    use Array_Enabled;
    /**
     * DATEVALUE.
     *
     * Returns a value that represents a particular date.
     * Use DATEVALUE to convert a date represented by a text string to an Excel or PHP date/time stamp
     * value.
     *
     * NOTE: When used in a Cell Formula, MS Excel changes the cell format so that it matches the date
     * format of your regional settings. PhpSpreadsheet does not change cell formatting in this way.
     *
     * Excel Function:
     *        DATEVALUE(dateValue)
     *
     * @param null|array<mixed>|bool|float|int|string $dateValue Text that represents a date in a Microsoft Excel date format.
     *                                    For example, "1/30/2008" or "30-Jan-2008" are text strings within
     *                                    quotation marks that represent dates. Using the default date
     *                                    system in Excel for Windows, date_text must represent a date from
     *                                    January 1, 1900, to December 31, 9999. Using the default date
     *                                    system in Excel for the Macintosh, date_text must represent a date
     *                                    from January 1, 1904, to December 31, 9999. DATEVALUE returns the
     *                                    #VALUE! error value if date_text is out of this range.
     *                         Or can be an array of date values
     *
     * @return array<mixed>|DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function from_string(null|array|string|int|bool|float $date_value): array|string|float|int|DateTime
    {
        if (is_array($date_value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $date_value);
        }
        // try to parse as date iff there is at least one digit
        if (is_string($date_value) && preg_match('/\d/', $date_value) !== 1) {
            return Excel_Error::VALUE();
        }
        $dti = new DateTimeImmutable();
        $base_year = Shared_Date_Helper::get_excel_calendar();
        $date_value = trim((string) $date_value, '"');
        //    Strip any ordinals because they're allowed in Excel (English only)
        $date_value = (string) preg_replace('/(\d)(st|nd|rd|th)([ -\/])/Ui', '$1$3', $date_value);
        //    Convert separators (/ . or space) to hyphens (should also handle dot used for ordinals in some countries, e.g. Denmark, Germany)
        $date_value = str_replace(['/', '.', '-', '  '], ' ', $date_value);
        $year_found = false;
        $t1 = explode(' ', $date_value);
        $t = '';
        foreach ($t1 as &$t) {
            if (is_numeric($t) && $t > 31) {
                if ($year_found) {
                    return Excel_Error::VALUE();
                }
                if ($t < 100) {
                    $t += 1900;
                }
                $year_found = true;
            }
        }
        if (count($t1) === 1) {
            //    We've been fed a time value without any date
            return !str_contains((string) $t, ':') ? Excel_Error::Value() : 0.0;
        }
        unset($t);
        $date_value = self::t1to_string($t1, $dti, $year_found);
        $php_date_array = self::set_up_array($date_value, $dti);
        return self::final_results($php_date_array, $dti, $base_year);
    }
    /** @param mixed[] $t1 */
    private static function t1to_string(array $t1, DateTimeImmutable $dti, bool $year_found): string
    {
        if (count($t1) == 2) {
            //    We only have two parts of the date: either day/month or month/year
            if ($year_found) {
                array_unshift($t1, 1);
            } else if (is_numeric($t1[1]) && $t1[1] > 29) {
                $t1[1] += 1900;
                array_unshift($t1, 1);
            } else {
                $t1[] = $dti->format('Y');
            }
        }
        return implode(' ', $t1);
    }
    /**
     * Parse date.
     *
     * @return mixed[]
     */
    private static function set_up_array(string $date_value, DateTimeImmutable $dti): array
    {
        $php_date_array = Helpers::date_parse($date_value);
        if (!Helpers::date_parse_succeeded($php_date_array)) {
            // If original count was 1, we've already returned.
            // If it was 2, we added another.
            // Therefore, neither of the first 2 strtoks below can fail.
            $test_val1 = strtok($date_value, '- ');
            $test_val2 = strtok('- ');
            $test_val3 = strtok('- ') ?: $dti->format('Y');
            Helpers::adjust_year((string) $test_val1, (string) $test_val2, $test_val3);
            $php_date_array = Helpers::date_parse($test_val1 . '-' . $test_val2 . '-' . $test_val3);
            if (!Helpers::date_parse_succeeded($php_date_array)) {
                $php_date_array = Helpers::date_parse($test_val2 . '-' . $test_val1 . '-' . $test_val3);
            }
        }
        return $php_date_array;
    }
    /**
     * Final results.
     *
     * @param mixed[] $PHPDateArray
     *
     * @return DateTime|float|int|string Excel date/time serial value, PHP date/time serial value or PHP date/time object,
     *                        depending on the value of the ReturnDateType flag
     */
    private static function final_results(array $php_date_array, DateTimeImmutable $dti, int $base_year): string|float|int|DateTime
    {
        $ret_value = Excel_Error::Value();
        if (Helpers::date_parse_succeeded($php_date_array)) {
            /** @var array{year: int, month: int, day: int, hour: int, minute: int, second: int} $PHPDateArray */
            // Execute function
            Helpers::replace_if_empty($php_date_array['year'], $dti->format('Y'));
            if ($php_date_array['year'] < $base_year) {
                return Excel_Error::VALUE();
            }
            Helpers::replace_if_empty($php_date_array['month'], $dti->format('m'));
            Helpers::replace_if_empty($php_date_array['day'], $dti->format('d'));
            /** @var array{year: int, month: int, day: int, hour: int, minute: int, second: int} $PHPDateArray */
            $php_date_array['hour'] = 0;
            $php_date_array['minute'] = 0;
            $php_date_array['second'] = 0;
            $month = self::get_int($php_date_array, 'month');
            $day = self::get_int($php_date_array, 'day');
            $year = self::get_int($php_date_array, 'year');
            if (!checkdate($month, $day, $year)) {
                return $year === 1900 && $month === 2 && $day === 29 ? Helpers::return_in3formats_float(60.0) : Excel_Error::VALUE();
            }
            $ret_value = Helpers::return_in3formats_array($php_date_array, true);
        }
        return $ret_value;
    }
    /** @param mixed[] $array */
    private static function get_int(array $array, string $index): int
    {
        return array_key_exists($index, $array) && is_numeric($array[$index]) ? (int) $array[$index] : 0;
    }
}