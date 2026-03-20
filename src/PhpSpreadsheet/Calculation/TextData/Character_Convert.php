<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Character_Convert
{
    use Array_Enabled;
    private static string $one_byte_character_set = 'Windows-1252';
    /**
     * CHAR.
     *
     * @param mixed $character Integer Value to convert to its character representation
     *                              Or can be an array of values
     *
     * @return array<mixed>|string The character string
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function character(mixed $character): array|string
    {
        if (is_array($character)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $character);
        }
        return self::character_both($character, true);
    }
    /** @return array<mixed>|string */
    public static function character_unicode(mixed $character): array|string
    {
        if (is_array($character)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $character);
        }
        return self::character_both($character, false);
    }
    private static function character_both(mixed $character, bool $ansi = true): string
    {
        try {
            $character = Helpers::validate_int($character, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if ($ansi && $character === 219 && self::$one_byte_character_set[0] === 'M') {
            return '€';
        }
        $min = Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE ? 0 : 1;
        if ($character < $min || $ansi && $character > 255 || $character > 0x10ffff) {
            return Excel_Error::VALUE();
        }
        if ($character > 0x10fffd) {
            // last assigned
            return Excel_Error::NA();
        }
        if ($ansi) {
            $result = chr($character);
            return (string) iconv(self::$one_byte_character_set, 'UTF-8//IGNORE', $result);
        }
        return mb_chr($character, 'UTF-8');
    }
    /**
     * CODE.
     *
     * @param mixed $characters String character to convert to its ASCII value
     *                              Or can be an array of values
     *
     * @return array<mixed>|int|string A string if arguments are invalid
     *         If an array of values is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function code(mixed $characters): array|string|int
    {
        if (is_array($characters)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $characters);
        }
        if (is_bool($characters) && Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
            $characters = $characters ? '1' : '0';
        }
        return self::code_both(String_Helper::convert_to_string($characters, convertBool: true), true);
    }
    /** @return array<mixed>|int|string */
    public static function code_unicode(mixed $characters): array|string|int
    {
        if (is_array($characters)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $characters);
        }
        if (is_bool($characters) && Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
            $characters = $characters ? '1' : '0';
        }
        return self::code_both(String_Helper::convert_to_string($characters, convertBool: true), false);
    }
    private static function code_both(string $characters, bool $ansi = true): int|string
    {
        try {
            $characters = Helpers::extract_string($characters, true);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if ($characters === '') {
            return Excel_Error::VALUE();
        }
        $character = $characters;
        if (mb_strlen($characters, 'UTF-8') > 1) {
            $character = mb_substr($characters, 0, 1, 'UTF-8');
        }
        if ($ansi && $character === '€' && self::$one_byte_character_set[0] === 'M') {
            return 219;
        }
        $result = mb_ord($character, 'UTF-8');
        if ($ansi) {
            $result = iconv('UTF-8', self::$one_byte_character_set . '//IGNORE', $character);
            return $result !== '' ? ord("{$result}") : 63;
            // question mark
        }
        return $result;
    }
    public static function set_windows_character_set(): void
    {
        self::$one_byte_character_set = 'Windows-1252';
    }
    public static function set_mac_character_set(): void
    {
        self::$one_byte_character_set = 'MAC';
    }
}