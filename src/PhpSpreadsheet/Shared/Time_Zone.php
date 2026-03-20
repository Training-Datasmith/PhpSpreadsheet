<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use DateTimeZone;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Time_Zone
{
    /**
     * Default Timezone used for date/time conversions.
     */
    protected static string $timezone = 'UTC';
    /**
     * Validate a Timezone name.
     *
     * @param string $timezoneName Time zone (e.g. 'Europe/London')
     *
     * @return bool Success or failure
     */
    private static function validate_time_zone(string $timezone_name): bool
    {
        return in_array($timezone_name, DateTimeZone::list_identifiers(DateTimeZone::ALL_WITH_BC), true);
    }
    /**
     * Set the Default Timezone used for date/time conversions.
     *
     * @param string $timezoneName Time zone (e.g. 'Europe/London')
     *
     * @return bool Success or failure
     */
    public static function set_time_zone(string $timezone_name): bool
    {
        if (self::validate_time_zone($timezone_name)) {
            self::$timezone = $timezone_name;
            return true;
        }
        return false;
    }
    /**
     * Return the Default Timezone used for date/time conversions.
     *
     * @return string Timezone (e.g. 'Europe/London')
     */
    public static function get_time_zone(): string
    {
        return self::$timezone;
    }
    /**
     *    Return the Timezone offset used for date/time conversions to/from UST
     * This requires both the timezone and the calculated date/time to allow for local DST.
     *
     * @param ?string $timezoneName The timezone for finding the adjustment to UST
     * @param float|int $timestamp PHP date/time value
     *
     * @return int Number of seconds for timezone adjustment
     */
    public static function get_time_zone_adjustment(?string $timezone_name, $timestamp): int
    {
        $timezone_name ??= self::$timezone;
        $dtobj = Date::date_time_from_timestamp("{$timestamp}");
        if (!self::validate_time_zone($timezone_name)) {
            throw new Php_Spreadsheet_Exception("Invalid timezone {$timezone_name}");
        }
        $dtobj->set_time_zone(new DateTimeZone($timezone_name));
        return $dtobj->get_offset();
    }
}