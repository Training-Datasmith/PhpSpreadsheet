<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Helper;

class Handler
{
    private static string $invalid_hex = 'Y';
    // A bunch of methods to show that we continue
    // to capture messages even using PhpUnit 10.
    public static function suppressed(): bool
    {
        return @trigger_error('hello');
    }
    public static function deprecated(): string
    {
        return (string) hexdec(self::$invalid_hex);
    }
    public static function notice(string $value): void
    {
        date_default_timezone_set($value);
    }
    public static function warning(): bool
    {
        return file_get_contents(__FILE__ . 'noexist') !== false;
    }
    public static function user_deprecated(): bool
    {
        return trigger_error('hello', E_USER_DEPRECATED);
    }
    public static function user_notice(): bool
    {
        return trigger_error('userNotice', E_USER_NOTICE);
    }
    public static function user_warning(): bool
    {
        return trigger_error('userWarning', E_USER_WARNING);
    }
}