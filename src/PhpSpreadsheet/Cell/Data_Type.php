<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Data_Type
{
    // Data types
    public const TYPE_STRING2 = 'str';
    public const TYPE_STRING = 's';
    public const TYPE_FORMULA = 'f';
    public const TYPE_NUMERIC = 'n';
    public const TYPE_BOOL = 'b';
    public const TYPE_NULL = 'null';
    public const TYPE_INLINE = 'inlineStr';
    public const TYPE_ERROR = 'e';
    public const TYPE_ISO_DATE = 'd';
    public const TYPE_DRAWING_IN_CELL = 'drawingCell';
    /**
     * List of error codes.
     *
     * @var array<string, int>
     */
    private static array $error_codes = ['#NULL!' => 0, '#DIV/0!' => 1, '#VALUE!' => 2, '#REF!' => 3, '#NAME?' => 4, '#NUM!' => 5, '#N/A' => 6, '#CALC!' => 7];
    public const MAX_STRING_LENGTH = 32767;
    /**
     * Get list of error codes.
     *
     * @return array<string, int>
     */
    public static function get_error_codes(): array
    {
        return self::$error_codes;
    }
    /**
     * Check a string that it satisfies Excel requirements.
     *
     * @param null|RichText|string $textValue Value to sanitize to an Excel string
     *
     * @return RichText|string Sanitized value
     */
    public static function check_string(null|Rich_Text|string $text_value, bool $preserve_cr = false): Rich_Text|string
    {
        if ($text_value instanceof Rich_Text) {
            // TODO: Sanitize Rich-Text string (max. character count is 32,767)
            return $text_value;
        }
        // string must never be longer than 32,767 characters, truncate if necessary
        $text_value = String_Helper::substring((string) $text_value, 0, self::MAX_STRING_LENGTH);
        // we require that newline is represented as "\n" in core, not as "\r\n" or "\r"
        if (!$preserve_cr) {
            return str_replace(["\r\n", "\r"], "\n", $text_value);
        }
        return $text_value;
    }
    /**
     * Check a value that it is a valid error code.
     *
     * @param mixed $value Value to sanitize to an Excel error code
     *
     * @return string Sanitized value
     */
    public static function check_error_code(mixed $value): string
    {
        $default = '#NULL!';
        $value = $value === null ? $default : String_Helper::convert_to_string($value, false, $default);
        if (!isset(self::$error_codes[$value])) {
            return $default;
        }
        return $value;
    }
}