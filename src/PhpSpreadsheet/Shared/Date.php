<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Throwable;
class Date
{
    /** constants */
    public const CALENDAR_WINDOWS_1900 = 1900;
    //    Base date of 1st Jan 1900 = 1.0
    public const CALENDAR_MAC_1904 = 1904;
    //    Base date of 2nd Jan 1904 = 1.0
    /**
     * Names of the months of the year, indexed by shortname
     * Planned usage for locale settings.
     *
     * @var string[]
     */
    public static array $month_names = ['Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March', 'Apr' => 'April', 'May' => 'May', 'Jun' => 'June', 'Jul' => 'July', 'Aug' => 'August', 'Sep' => 'September', 'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December'];
    /**
     * @var string[]
     */
    public static array $number_suffixes = ['st', 'nd', 'rd', 'th'];
    /**
     * Base calendar year to use for calculations
     * Value is either CALENDAR_WINDOWS_1900 (1900) or CALENDAR_MAC_1904 (1904).
     */
    protected static int $excel_calendar = self::CALENDAR_WINDOWS_1900;
    /**
     * Default timezone to use for DateTime objects.
     */
    protected static ?DateTimeZone $default_time_zone = null;
    /**
     * Set the Excel calendar (Windows 1900 or Mac 1904).
     *
     * @param ?int $baseYear Excel base date (1900 or 1904)
     *
     * @return bool Success or failure
     */
    public static function set_excel_calendar(?int $base_year): bool
    {
        if ($base_year === self::CALENDAR_WINDOWS_1900 || $base_year === self::CALENDAR_MAC_1904) {
            self::$excel_calendar = $base_year;
            return true;
        }
        return false;
    }
    /**
     * Return the Excel calendar (Windows 1900 or Mac 1904).
     *
     * @return int Excel base date (1900 or 1904)
     */
    public static function get_excel_calendar(): int
    {
        return self::$excel_calendar;
    }
    /**
     * Set the Default timezone to use for dates.
     *
     * @param null|DateTimeZone|string $timeZone The timezone to set for all Excel datetimestamp to PHP DateTime Object conversions
     *
     * @return bool Success or failure
     */
    public static function set_default_timezone($time_zone): bool
    {
        try {
            $time_zone = self::validate_time_zone($time_zone);
            self::$default_time_zone = $time_zone;
            $retval = true;
        } catch (Php_Spreadsheet_Exception) {
            $retval = false;
        }
        return $retval;
    }
    /**
     * Return the Default timezone, or UTC if default not set.
     */
    public static function get_default_timezone(): DateTimeZone
    {
        return self::$default_time_zone ?? new DateTimeZone('UTC');
    }
    /**
     * Return the Default timezone, or local timezone if default is not set.
     */
    public static function get_default_or_local_timezone(): DateTimeZone
    {
        return self::$default_time_zone ?? new DateTimeZone(date_default_timezone_get());
    }
    /**
     * Return the Default timezone even if null.
     */
    public static function get_default_timezone_or_null(): ?DateTimeZone
    {
        return self::$default_time_zone;
    }
    /**
     * Validate a timezone.
     *
     * @param null|DateTimeZone|string $timeZone The timezone to validate, either as a timezone string or object
     *
     * @return ?DateTimeZone The timezone as a timezone object
     */
    private static function validate_time_zone($time_zone): ?DateTimeZone
    {
        if ($time_zone instanceof DateTimeZone || $time_zone === null) {
            return $time_zone;
        }
        if (in_array($time_zone, DateTimeZone::list_identifiers(DateTimeZone::ALL_WITH_BC))) {
            return new DateTimeZone($time_zone);
        }
        throw new Php_Spreadsheet_Exception('Invalid timezone');
    }
    /**
     * @param mixed $value Converts a date/time in ISO-8601 standard format date string to an Excel
     *                         serialized timestamp.
     *                     See https://en.wikipedia.org/wiki/ISO_8601 for details of the ISO-8601 standard format.
     */
    public static function convert_iso_date(mixed $value): float|int
    {
        if (!is_string($value)) {
            throw new Exception('Non-string value supplied for Iso Date conversion');
        }
        $date = new DateTime($value);
        $date_errors = DateTime::get_last_errors();
        if (is_array($date_errors) && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0)) {
            throw new Exception("Invalid string {$value} supplied for datatype Date");
        }
        $new_value = self::date_time_to_excel($date);
        if (preg_match('/^\s*\d?\d:\d\d(:\d\d([.]\d+)?)?\s*(am|pm)?\s*$/i', $value) == 1) {
            return fmod($new_value, 1.0);
        }
        return $new_value;
    }
    /**
     * Convert a MS serialized datetime value from Excel to a PHP Date/Time object.
     *
     * @param float|int $excelTimestamp MS Excel serialized date/time value
     * @param null|DateTimeZone|string $timeZone The timezone to assume for the Excel timestamp,
     *                                           if you don't want to treat it as a UTC value
     *                                           Use the default (UTC) unless you absolutely need a conversion
     *
     * @return DateTime PHP date/time object
     */
    public static function excel_to_date_time_object(float|int $excel_timestamp, null|DateTimeZone|string $time_zone = null): DateTime
    {
        $time_zone = $time_zone === null ? self::get_default_timezone() : self::validate_time_zone($time_zone);
        if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_EXCEL) {
            if ($excel_timestamp < 1 && self::$excel_calendar === self::CALENDAR_WINDOWS_1900) {
                // Unix timestamp base date
                $base_date = new DateTime('1970-01-01', $time_zone);
            } else if (self::$excel_calendar == self::CALENDAR_WINDOWS_1900) {
                // Allow adjustment for 1900 Leap Year in MS Excel
                $base_date = $excel_timestamp < 60 ? new DateTime('1899-12-31', $time_zone) : new DateTime('1899-12-30', $time_zone);
            } else {
                $base_date = new DateTime('1904-01-01', $time_zone);
            }
        } else {
            $base_date = new DateTime('1899-12-30', $time_zone);
        }
        if (is_int($excel_timestamp)) {
            if ($excel_timestamp >= 0) {
                return $base_date->modify("+ {$excel_timestamp} days");
            }
            return $base_date->modify("{$excel_timestamp} days");
        }
        $days = floor($excel_timestamp);
        $part_day = $excel_timestamp - $days;
        $hms = 86400 * $part_day;
        $microseconds = (int) round(fmod($hms, 1) * 1000000);
        $hms = (int) floor($hms);
        $hours = intdiv($hms, 3600);
        $hms -= $hours * 3600;
        $minutes = intdiv($hms, 60);
        $seconds = $hms % 60;
        if ($days >= 0) {
            $days = '+' . $days;
        }
        $interval = $days . ' days';
        return $base_date->modify($interval)->set_time($hours, $minutes, $seconds, $microseconds);
    }
    /**
     * Convert a MS serialized datetime value from Excel to a unix timestamp.
     * The use of Unix timestamps, and therefore this function, is discouraged.
     * They are not Y2038-safe on a 32-bit system, and have no timezone info.
     *
     * @param float|int $excelTimestamp MS Excel serialized date/time value
     * @param null|DateTimeZone|string $timeZone The timezone to assume for the Excel timestamp,
     *                                               if you don't want to treat it as a UTC value
     *                                               Use the default (UTC) unless you absolutely need a conversion
     *
     * @return int Unix timetamp for this date/time
     */
    public static function excel_to_timestamp(float|int $excel_timestamp, null|\DateTimeZone|string $time_zone = null): int
    {
        $dto = self::excel_to_date_time_object($excel_timestamp, $time_zone);
        self::round_microseconds($dto);
        return (int) $dto->format('U');
    }
    /**
     * Convert a date from PHP to an MS Excel serialized date/time value.
     *
     * @param mixed $dateValue PHP DateTime object or a string - Unix timestamp is also permitted, but discouraged;
     *    not Y2038-safe on a 32-bit system, and no timezone info
     *
     * @return false|float Excel date/time value
     *                                  or boolean FALSE on failure
     */
    public static function php_to_excel(mixed $date_value): float|bool
    {
        if (is_object($date_value) && $date_value instanceof DateTimeInterface) {
            return self::date_time_to_excel($date_value);
        }
        if (is_numeric($date_value)) {
            return self::timestamp_to_excel($date_value);
        }
        if (is_string($date_value)) {
            return self::string_to_excel($date_value);
        }
        return false;
    }
    /**
     * Convert a PHP DateTime object to an MS Excel serialized date/time value.
     *
     * @param DateTimeInterface $dateValue PHP DateTime object
     *
     * @return float MS Excel serialized date/time value
     */
    public static function date_time_to_excel(DateTimeInterface $date_value): float
    {
        $seconds = (float) sprintf('%d.%06d', $date_value->format('s'), $date_value->format('u'));
        return self::formatted_php_to_excel((int) $date_value->format('Y'), (int) $date_value->format('m'), (int) $date_value->format('d'), (int) $date_value->format('H'), (int) $date_value->format('i'), $seconds);
    }
    /**
     * Convert a Unix timestamp to an MS Excel serialized date/time value.
     * The use of Unix timestamps, and therefore this function, is discouraged.
     * They are not Y2038-safe on a 32-bit system, and have no timezone info.
     *
     * @param float|int|string $unixTimestamp Unix Timestamp
     *
     * @return false|float MS Excel serialized date/time value
     */
    public static function timestamp_to_excel($unix_timestamp): bool|float
    {
        if (!is_numeric($unix_timestamp)) {
            return false;
        }
        return self::date_time_to_excel(new DateTime('@' . $unix_timestamp));
    }
    /**
     * formattedPHPToExcel.
     *
     * @return float Excel date/time value
     */
    public static function formatted_php_to_excel(int $year, int $month, int $day, int $hours = 0, int $minutes = 0, float|int $seconds = 0): float
    {
        if (self::$excel_calendar == self::CALENDAR_WINDOWS_1900) {
            //
            //    Fudge factor for the erroneous fact that the year 1900 is treated as a Leap Year in MS Excel
            //    This affects every date following 28th February 1900
            //
            $excel1900is_leap_year = true;
            if ($year == 1900 && $month <= 2) {
                $excel1900is_leap_year = false;
            }
            $myexcel_base_date = 2415020;
        } else {
            $myexcel_base_date = 2416481;
            $excel1900is_leap_year = false;
        }
        //    Julian base date Adjustment
        if ($month > 2) {
            $month -= 3;
        } else {
            $month += 9;
            --$year;
        }
        //    Calculate the Julian Date, then subtract the Excel base date (JD 2415020 = 31-Dec-1899 Giving Excel Date of 0)
        $century = (int) substr((string) $year, 0, 2);
        $decade = (int) substr((string) $year, 2, 2);
        $excel_date = floor(146097 * $century / 4) + floor(1461 * $decade / 4) + floor((153 * $month + 2) / 5) + $day + 1721119 - $myexcel_base_date + $excel1900is_leap_year;
        $excel_time = ($hours * 3600 + $minutes * 60 + $seconds) / 86400;
        return $excel_date + $excel_time;
    }
    /**
     * Is a given cell a date/time?
     */
    public static function is_date_time(Cell $cell, mixed $value = null, bool $date_without_time_okay = true): bool
    {
        $result = false;
        $worksheet = $cell->get_worksheet_or_null();
        $spreadsheet = $worksheet === null ? null : $worksheet->get_parent();
        if ($worksheet !== null && $spreadsheet !== null) {
            $index = $spreadsheet->get_active_sheet_index();
            $selected = $worksheet->get_selected_cells();
            try {
                if ($value === null) {
                    $value = Functions::flatten_single_value($cell->get_calculated_value());
                }
                if (is_numeric($value)) {
                    $result = self::is_date_time_format($worksheet->get_style($cell->get_coordinate())->get_number_format(), $date_without_time_okay);
                    /** @var float|int $value */
                    self::excel_to_date_time_object($value);
                }
            } catch (Throwable) {
                $result = false;
            }
            $worksheet->set_selected_cells($selected);
            $spreadsheet->set_active_sheet_index($index);
        }
        return $result;
    }
    /**
     * Is a given NumberFormat code a date/time format code?
     */
    public static function is_date_time_format(Number_Format $excel_format_code, bool $date_without_time_okay = true): bool
    {
        return self::is_date_time_format_code((string) $excel_format_code->get_format_code(), $date_without_time_okay);
    }
    private const POSSIBLE_DATETIME_FORMAT_CHARACTERS = 'eymdHs';
    private const POSSIBLE_TIME_FORMAT_CHARACTERS = 'Hs';
    // note - no 'm' due to ambiguity
    /**
     * Is a given number format code a date/time?
     */
    public static function is_date_time_format_code(string $excel_format_code, bool $date_without_time_okay = true): bool
    {
        if (strtolower($excel_format_code) === strtolower(Number_Format::FORMAT_GENERAL)) {
            //    "General" contains an epoch letter 'e', so we trap for it explicitly here (case-insensitive check)
            return false;
        }
        if (preg_match('/[0#]E[+-]0/i', $excel_format_code)) {
            //    Scientific format
            return false;
        }
        // Switch on formatcode
        $excel_format_code = (string) Number_Format::convert_system_formats($excel_format_code);
        if (in_array($excel_format_code, Number_Format::DATE_TIME_OR_DATETIME_ARRAY, true)) {
            return $date_without_time_okay || in_array($excel_format_code, Number_Format::TIME_OR_DATETIME_ARRAY);
        }
        //    Typically number, currency or accounting (or occasionally fraction) formats
        if (str_starts_with($excel_format_code, '_') || str_starts_with($excel_format_code, '0 ')) {
            return false;
        }
        // Some "special formats" provided in German Excel versions were detected as date time value,
        // so filter them out here - "\C\H\-00000" (Switzerland) and "\D-00000" (Germany).
        if (str_contains($excel_format_code, '-00000')) {
            return false;
        }
        $possible_format_characters = $date_without_time_okay ? self::POSSIBLE_DATETIME_FORMAT_CHARACTERS : self::POSSIBLE_TIME_FORMAT_CHARACTERS;
        // Try checking for any of the date formatting characters that don't appear within square braces
        if (preg_match('/(^|\])[^\[]*[' . $possible_format_characters . ']/i', $excel_format_code)) {
            //    We might also have a format mask containing quoted strings...
            //        we don't want to test for any of our characters within the quoted blocks
            if (str_contains($excel_format_code, '"')) {
                $seg_matcher = false;
                foreach (explode('"', $excel_format_code) as $sub_val) {
                    //    Only test in alternate array entries (the non-quoted blocks)
                    $seg_matcher = $seg_matcher === false;
                    if ($seg_matcher && preg_match('/(^|\])[^\[]*[' . $possible_format_characters . ']/i', $sub_val)) {
                        return true;
                    }
                }
                return false;
            }
            return true;
        }
        // No date...
        return false;
    }
    /**
     * Convert a date/time string to Excel time.
     *
     * @param string $dateValue Examples: '2009-12-31', '2009-12-31 15:59', '2009-12-31 15:59:10'
     *
     * @return false|float Excel date/time serial value
     */
    public static function string_to_excel(string $date_value): bool|float
    {
        if (strlen($date_value) < 2) {
            return false;
        }
        if (!preg_match('/^(\d{1,4}[ \.\/\-][A-Z]{3,9}([ \.\/\-]\d{1,4})?|[A-Z]{3,9}[ \.\/\-]\d{1,4}([ \.\/\-]\d{1,4})?|\d{1,4}[ \.\/\-]\d{1,4}([ \.\/\-]\d{1,4})?)( \d{1,2}:\d{1,2}(:\d{1,2}([.]\d+)?)?)?$/iu', $date_value)) {
            return false;
        }
        $date_value_new = Date_Time_Excel\Date_Value::from_string($date_value);
        if (!is_float($date_value_new)) {
            return false;
        }
        if (str_contains($date_value, ':')) {
            $time_value = Date_Time_Excel\Time_Value::from_string($date_value);
            if (!is_float($time_value)) {
                return false;
            }
            $date_value_new += $time_value;
        }
        return $date_value_new;
    }
    /**
     * Converts a month name (either a long or a short name) to a month number.
     *
     * @param string $monthName Month name or abbreviation
     *
     * @return int|string Month number (1 - 12), or the original string argument if it isn't a valid month name
     */
    public static function month_string_to_number(string $month_name): int|string
    {
        $month_index = 1;
        foreach (self::$month_names as $short_month_name => $long_month_name) {
            if ($month_name === $long_month_name || $month_name === $short_month_name) {
                return $month_index;
            }
            ++$month_index;
        }
        return $month_name;
    }
    /**
     * Strips an ordinal from a numeric value.
     *
     * @param string $day Day number with an ordinal
     *
     * @return int|string The integer value with any ordinal stripped, or the original string argument if it isn't a valid numeric
     */
    public static function day_string_to_number(string $day): int|string
    {
        $stripped_day_value = str_replace(self::$number_suffixes, '', $day);
        if (is_numeric($stripped_day_value)) {
            return (int) $stripped_day_value;
        }
        return $day;
    }
    public static function date_time_from_timestamp(string $date, ?DateTimeZone $time_zone = null): DateTime
    {
        $dtobj = DateTime::create_from_format('U', $date) ?: new DateTime();
        $dtobj->set_time_zone($time_zone ?? self::get_default_or_local_timezone());
        return $dtobj;
    }
    public static function formatted_date_time_from_timestamp(string $date, string $format, ?DateTimeZone $time_zone = null): string
    {
        $dtobj = self::date_time_from_timestamp($date, $time_zone);
        return $dtobj->format($format);
    }
    /**
     * Round the given DateTime object to seconds.
     */
    public static function round_microseconds(DateTime $dti): void
    {
        $microseconds = (int) $dti->format('u');
        $rounded = (int) round($microseconds, -6);
        $modify = $rounded - $microseconds;
        if ($modify !== 0) {
            $dti->modify(($modify > 0 ? '+' : '') . $modify . ' microseconds');
        }
    }
}