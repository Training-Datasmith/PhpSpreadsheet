<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Composer\Pcre\Preg;
use Intl_Calendar;
use Number_Formatter;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Stringable;
class String_Helper
{
    private const CONTROL_CHARACTERS_KEYS = ["\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\v", "\f", "\x0e", "\x0f", "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f"];
    private const CONTROL_CHARACTERS_VALUES = ['_x0000_', '_x0001_', '_x0002_', '_x0003_', '_x0004_', '_x0005_', '_x0006_', '_x0007_', '_x0008_', '_x000B_', '_x000C_', '_x000E_', '_x000F_', '_x0010_', '_x0011_', '_x0012_', '_x0013_', '_x0014_', '_x0015_', '_x0016_', '_x0017_', '_x0018_', '_x0019_', '_x001A_', '_x001B_', '_x001C_', '_x001D_', '_x001E_', '_x001F_'];
    /**
     * SYLK Characters array.
     */
    private const SYLK_CHARACTERS = [
        "\x1b 0" => "\x00",
        "\x1b 1" => "\x01",
        "\x1b 2" => "\x02",
        "\x1b 3" => "\x03",
        "\x1b 4" => "\x04",
        "\x1b 5" => "\x05",
        "\x1b 6" => "\x06",
        "\x1b 7" => "\x07",
        "\x1b 8" => "\x08",
        "\x1b 9" => "\t",
        "\x1b :" => "\n",
        "\x1b ;" => "\v",
        "\x1b <" => "\f",
        "\x1b =" => "\r",
        "\x1b >" => "\x0e",
        "\x1b ?" => "\x0f",
        "\x1b!0" => "\x10",
        "\x1b!1" => "\x11",
        "\x1b!2" => "\x12",
        "\x1b!3" => "\x13",
        "\x1b!4" => "\x14",
        "\x1b!5" => "\x15",
        "\x1b!6" => "\x16",
        "\x1b!7" => "\x17",
        "\x1b!8" => "\x18",
        "\x1b!9" => "\x19",
        "\x1b!:" => "\x1a",
        "\x1b!;" => "\x1b",
        "\x1b!<" => "\x1c",
        "\x1b!=" => "\x1d",
        "\x1b!>" => "\x1e",
        "\x1b!?" => "\x1f",
        "\x1b'?" => "",
        "\x1b(0" => '€',
        // 128 in CP1252
        "\x1b(2" => '‚',
        // 130 in CP1252
        "\x1b(3" => 'ƒ',
        // 131 in CP1252
        "\x1b(4" => '„',
        // 132 in CP1252
        "\x1b(5" => '…',
        // 133 in CP1252
        "\x1b(6" => '†',
        // 134 in CP1252
        "\x1b(7" => '‡',
        // 135 in CP1252
        "\x1b(8" => 'ˆ',
        // 136 in CP1252
        "\x1b(9" => '‰',
        // 137 in CP1252
        "\x1b(:" => 'Š',
        // 138 in CP1252
        "\x1b(;" => '‹',
        // 139 in CP1252
        "\x1bNj" => 'Œ',
        // 140 in CP1252
        "\x1b(>" => 'Ž',
        // 142 in CP1252
        "\x1b)1" => '‘',
        // 145 in CP1252
        "\x1b)2" => '’',
        // 146 in CP1252
        "\x1b)3" => '“',
        // 147 in CP1252
        "\x1b)4" => '”',
        // 148 in CP1252
        "\x1b)5" => '•',
        // 149 in CP1252
        "\x1b)6" => '–',
        // 150 in CP1252
        "\x1b)7" => '—',
        // 151 in CP1252
        "\x1b)8" => '˜',
        // 152 in CP1252
        "\x1b)9" => '™',
        // 153 in CP1252
        "\x1b):" => 'š',
        // 154 in CP1252
        "\x1b);" => '›',
        // 155 in CP1252
        "\x1bNz" => 'œ',
        // 156 in CP1252
        "\x1b)>" => 'ž',
        // 158 in CP1252
        "\x1b)?" => 'Ÿ',
        // 159 in CP1252
        "\x1b*0" => ' ',
        // 160 in CP1252
        "\x1bN!" => '¡',
        // 161 in CP1252
        "\x1bN\"" => '¢',
        // 162 in CP1252
        "\x1bN#" => '£',
        // 163 in CP1252
        "\x1bN(" => '¤',
        // 164 in CP1252
        "\x1bN%" => '¥',
        // 165 in CP1252
        "\x1b*6" => '¦',
        // 166 in CP1252
        "\x1bN'" => '§',
        // 167 in CP1252
        "\x1bNH " => '¨',
        // 168 in CP1252
        "\x1bNS" => '©',
        // 169 in CP1252
        "\x1bNc" => 'ª',
        // 170 in CP1252
        "\x1bN+" => '«',
        // 171 in CP1252
        "\x1b*<" => '¬',
        // 172 in CP1252
        "\x1b*=" => '­',
        // 173 in CP1252
        "\x1bNR" => '®',
        // 174 in CP1252
        "\x1b*?" => '¯',
        // 175 in CP1252
        "\x1bN0" => '°',
        // 176 in CP1252
        "\x1bN1" => '±',
        // 177 in CP1252
        "\x1bN2" => '²',
        // 178 in CP1252
        "\x1bN3" => '³',
        // 179 in CP1252
        "\x1bNB " => '´',
        // 180 in CP1252
        "\x1bN5" => 'µ',
        // 181 in CP1252
        "\x1bN6" => '¶',
        // 182 in CP1252
        "\x1bN7" => '·',
        // 183 in CP1252
        "\x1b+8" => '¸',
        // 184 in CP1252
        "\x1bNQ" => '¹',
        // 185 in CP1252
        "\x1bNk" => 'º',
        // 186 in CP1252
        "\x1bN;" => '»',
        // 187 in CP1252
        "\x1bN<" => '¼',
        // 188 in CP1252
        "\x1bN=" => '½',
        // 189 in CP1252
        "\x1bN>" => '¾',
        // 190 in CP1252
        "\x1bN?" => '¿',
        // 191 in CP1252
        "\x1bNAA" => 'À',
        // 192 in CP1252
        "\x1bNBA" => 'Á',
        // 193 in CP1252
        "\x1bNCA" => 'Â',
        // 194 in CP1252
        "\x1bNDA" => 'Ã',
        // 195 in CP1252
        "\x1bNHA" => 'Ä',
        // 196 in CP1252
        "\x1bNJA" => 'Å',
        // 197 in CP1252
        "\x1bNa" => 'Æ',
        // 198 in CP1252
        "\x1bNKC" => 'Ç',
        // 199 in CP1252
        "\x1bNAE" => 'È',
        // 200 in CP1252
        "\x1bNBE" => 'É',
        // 201 in CP1252
        "\x1bNCE" => 'Ê',
        // 202 in CP1252
        "\x1bNHE" => 'Ë',
        // 203 in CP1252
        "\x1bNAI" => 'Ì',
        // 204 in CP1252
        "\x1bNBI" => 'Í',
        // 205 in CP1252
        "\x1bNCI" => 'Î',
        // 206 in CP1252
        "\x1bNHI" => 'Ï',
        // 207 in CP1252
        "\x1bNb" => 'Ð',
        // 208 in CP1252
        "\x1bNDN" => 'Ñ',
        // 209 in CP1252
        "\x1bNAO" => 'Ò',
        // 210 in CP1252
        "\x1bNBO" => 'Ó',
        // 211 in CP1252
        "\x1bNCO" => 'Ô',
        // 212 in CP1252
        "\x1bNDO" => 'Õ',
        // 213 in CP1252
        "\x1bNHO" => 'Ö',
        // 214 in CP1252
        "\x1b-7" => '×',
        // 215 in CP1252
        "\x1bNi" => 'Ø',
        // 216 in CP1252
        "\x1bNAU" => 'Ù',
        // 217 in CP1252
        "\x1bNBU" => 'Ú',
        // 218 in CP1252
        "\x1bNCU" => 'Û',
        // 219 in CP1252
        "\x1bNHU" => 'Ü',
        // 220 in CP1252
        "\x1b-=" => 'Ý',
        // 221 in CP1252
        "\x1bNl" => 'Þ',
        // 222 in CP1252
        "\x1bN{" => 'ß',
        // 223 in CP1252
        "\x1bNAa" => 'à',
        // 224 in CP1252
        "\x1bNBa" => 'á',
        // 225 in CP1252
        "\x1bNCa" => 'â',
        // 226 in CP1252
        "\x1bNDa" => 'ã',
        // 227 in CP1252
        "\x1bNHa" => 'ä',
        // 228 in CP1252
        "\x1bNJa" => 'å',
        // 229 in CP1252
        "\x1bNq" => 'æ',
        // 230 in CP1252
        "\x1bNKc" => 'ç',
        // 231 in CP1252
        "\x1bNAe" => 'è',
        // 232 in CP1252
        "\x1bNBe" => 'é',
        // 233 in CP1252
        "\x1bNCe" => 'ê',
        // 234 in CP1252
        "\x1bNHe" => 'ë',
        // 235 in CP1252
        "\x1bNAi" => 'ì',
        // 236 in CP1252
        "\x1bNBi" => 'í',
        // 237 in CP1252
        "\x1bNCi" => 'î',
        // 238 in CP1252
        "\x1bNHi" => 'ï',
        // 239 in CP1252
        "\x1bNs" => 'ð',
        // 240 in CP1252
        "\x1bNDn" => 'ñ',
        // 241 in CP1252
        "\x1bNAo" => 'ò',
        // 242 in CP1252
        "\x1bNBo" => 'ó',
        // 243 in CP1252
        "\x1bNCo" => 'ô',
        // 244 in CP1252
        "\x1bNDo" => 'õ',
        // 245 in CP1252
        "\x1bNHo" => 'ö',
        // 246 in CP1252
        "\x1b/7" => '÷',
        // 247 in CP1252
        "\x1bNy" => 'ø',
        // 248 in CP1252
        "\x1bNAu" => 'ù',
        // 249 in CP1252
        "\x1bNBu" => 'ú',
        // 250 in CP1252
        "\x1bNCu" => 'û',
        // 251 in CP1252
        "\x1bNHu" => 'ü',
        // 252 in CP1252
        "\x1b/=" => 'ý',
        // 253 in CP1252
        "\x1bN|" => 'þ',
        // 254 in CP1252
        "\x1bNHy" => 'ÿ',
    ];
    /**
     * Decimal separator.
     */
    protected static ?string $decimal_separator = null;
    /**
     * Thousands separator.
     */
    protected static ?string $thousands_separator = null;
    /**
     * Currency code.
     */
    protected static ?string $currency_code = null;
    /**
     * Is iconv extension available?
     */
    protected static ?bool $is_iconv_enabled = null;
    /**
     * iconv options.
     */
    protected static string $iconv_options = '//IGNORE//TRANSLIT';
    /** @var string[] */
    protected static array $iconv_options_array = ['//IGNORE//TRANSLIT', '//IGNORE'];
    /** @internal */
    protected static string $iconv_name = 'iconv';
    /** @internal */
    protected static bool $iconv_test2 = false;
    /** @internal */
    protected static bool $iconv_test3 = false;
    /**
     * Get whether iconv extension is available.
     */
    public static function get_is_iconv_enabled(): bool
    {
        if (isset(static::$is_iconv_enabled)) {
            return static::$is_iconv_enabled;
        }
        // Assume no problems with iconv
        static::$is_iconv_enabled = true;
        // Fail if iconv doesn't exist
        if (!function_exists(static::$iconv_name)) {
            static::$is_iconv_enabled = false;
        } elseif (static::$iconv_test2 || !@iconv('UTF-8', 'UTF-16LE', 'x')) {
            // Sometimes iconv is not working, and e.g. iconv('UTF-8', 'UTF-16LE', 'x') just returns false,
            static::$is_iconv_enabled = false;
        } elseif (static::$iconv_test3 || defined('PHP_OS') && @stristr(PHP_OS, 'AIX') && defined('ICONV_IMPL') && @strcasecmp(ICONV_IMPL, 'unknown') == 0 && defined('ICONV_VERSION') && @strcasecmp(ICONV_VERSION, 'unknown') == 0) {
            // CUSTOM: IBM AIX iconv() does not work
            static::$is_iconv_enabled = false;
        }
        // Deactivate iconv default options if they fail (as seen on IBM i-series)
        if (static::$is_iconv_enabled) {
            static::$iconv_options = '';
            foreach (static::$iconv_options_array as $option) {
                if (@iconv('UTF-8', 'UTF-16LE' . $option, 'x') !== false) {
                    static::$iconv_options = $option;
                    break;
                }
            }
        }
        return static::$is_iconv_enabled;
    }
    /**
     * Convert from OpenXML escaped control character to PHP control character.
     *
     * Excel 2007 team:
     * ----------------
     * That's correct, control characters are stored directly in the shared-strings table.
     * We do encode characters that cannot be represented in XML using the following escape sequence:
     * _xHHHH_ where H represents a hexadecimal character in the character's value...
     * So you could end up with something like _x0008_ in a string (either in a cell value (<v>)
     * element or in the shared string <t> element.
     *
     * @param string $textValue Value to unescape
     */
    public static function control_character_ooxml2php(string $text_value): string
    {
        return Preg::replace_callback('/_x[0-9A-F]{4}_(_xD[CDEF][0-9A-F]{2}_)?/', self::to_out_char(...), $text_value);
    }
    private static function to_hex_val(string $char): int
    {
        if ($char >= '0' && $char <= '9') {
            return ord($char) - ord('0');
        }
        return ord($char) - ord('A') + 10;
    }
    /** @param array<?string> $match */
    private static function to_out_char(array $match): string
    {
        /** @var string */
        $chars = $match[0];
        $h = self::to_hex_val($chars[2]) << 12 | self::to_hex_val($chars[3]) << 8 | self::to_hex_val($chars[4]) << 4 | self::to_hex_val($chars[5]);
        if (strlen($chars) === 7) {
            // no low surrogate
            if ($chars[2] === 'D' && in_array($chars[3], ['8', '9', 'A', 'B', 'C', 'D', 'E', 'F'], true)) {
                return '�';
            }
            return mb_chr($h, 'UTF-8');
        }
        if ($chars[2] === 'D' && in_array($chars[3], ['C', 'D', 'D', 'F'], true)) {
            return '�';
            // Excel interprets as one substitute, not 2
        }
        if ($chars[2] !== 'D' || !in_array($chars[3], ['8', '9', 'A', 'B'], true)) {
            return mb_chr($h, 'UTF-8') . '�';
        }
        $l = self::to_hex_val($chars[9]) << 12 | self::to_hex_val($chars[10]) << 8 | self::to_hex_val($chars[11]) << 4 | self::to_hex_val($chars[12]);
        $result = 0x10000 + ($h - 0xd800) * 0x400 + ($l - 0xdc00);
        return mb_chr($result, 'UTF-8');
    }
    /**
     * Convert from PHP control character to OpenXML escaped control character.
     *
     * Excel 2007 team:
     * ----------------
     * That's correct, control characters are stored directly in the shared-strings table.
     * We do encode characters that cannot be represented in XML using the following escape sequence:
     * _xHHHH_ where H represents a hexadecimal character in the character's value...
     * So you could end up with something like _x0008_ in a string (either in a cell value (<v>)
     * element or in the shared string <t> element.
     *
     * @param string $textValue Value to escape
     */
    public static function control_character_php2ooxml(string $text_value): string
    {
        $text_value = Preg::replace('/_(x[0-9A-F]{4}_)/', '_x005F_$1', $text_value);
        return str_replace(self::CONTROL_CHARACTERS_KEYS, self::CONTROL_CHARACTERS_VALUES, $text_value);
    }
    /**
     * Try to sanitize UTF8, replacing invalid sequences with Unicode substitution characters.
     */
    public static function sanitize_utf8(string $text_value): string
    {
        $text_value = str_replace(["￾", "￿"], "�", $text_value);
        $subst = mb_substitute_character();
        // default is question mark
        mb_substitute_character(65533);
        // Unicode substitution character
        $return_value = mb_convert_encoding($text_value, 'UTF-8', 'UTF-8');
        mb_substitute_character($subst);
        return $return_value;
    }
    /**
     * Check if a string contains UTF8 data.
     */
    public static function is_utf8(string $text_value): bool
    {
        return $text_value === self::sanitize_utf8($text_value);
    }
    /**
     * Formats a numeric value as a string for output in various output writers forcing
     * point as decimal separator in case locale is other than English.
     */
    public static function format_number(float|int|string|null $numeric_value): string
    {
        if (is_float($numeric_value)) {
            return str_replace(',', '.', (string) $numeric_value);
        }
        return (string) $numeric_value;
    }
    /**
     * Converts a UTF-8 string into BIFF8 Unicode string data (8-bit string length)
     * Writes the string using uncompressed notation, no rich text, no Asian phonetics
     * If mbstring extension is not available, ASCII is assumed, and compressed notation is used
     * although this will give wrong results for non-ASCII strings
     * see OpenOffice.org's Documentation of the Microsoft Excel File Format, sect. 2.5.3.
     *
     * @param string $textValue UTF-8 encoded string
     * @param array<int, array{strlen: int, fontidx: int}> $arrcRuns Details of rich text runs in $value
     */
    public static function utf8to_biff8unicode_short(string $text_value, array $arrc_runs = []): string
    {
        // character count
        $ln = self::count_characters($text_value, 'UTF-8');
        // option flags
        if (empty($arrc_runs)) {
            $data = pack('CC', $ln, 0x1);
            // characters
            $data .= self::convert_encoding($text_value, 'UTF-16LE', 'UTF-8');
        } else {
            $data = pack('vC', $ln, 0x9);
            $data .= pack('v', count($arrc_runs));
            // characters
            $data .= self::convert_encoding($text_value, 'UTF-16LE', 'UTF-8');
            foreach ($arrc_runs as $c_run) {
                $data .= pack('v', $c_run['strlen']);
                $data .= pack('v', $c_run['fontidx']);
            }
        }
        return $data;
    }
    /**
     * Converts a UTF-8 string into BIFF8 Unicode string data (16-bit string length)
     * Writes the string using uncompressed notation, no rich text, no Asian phonetics
     * If mbstring extension is not available, ASCII is assumed, and compressed notation is used
     * although this will give wrong results for non-ASCII strings
     * see OpenOffice.org's Documentation of the Microsoft Excel File Format, sect. 2.5.3.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function utf8to_biff8unicode_long(string $text_value): string
    {
        // characters
        $chars = self::convert_encoding($text_value, 'UTF-16LE', 'UTF-8');
        $ln = (int) (strlen($chars) / 2);
        // N.B. - strlen, not mb_strlen issue #642
        return pack('vC', $ln, 0x1) . $chars;
    }
    /**
     * Convert string from one encoding to another.
     *
     * @param string $to Encoding to convert to, e.g. 'UTF-8'
     * @param string $from Encoding to convert from, e.g. 'UTF-16LE'
     */
    public static function convert_encoding(string $text_value, string $to, string $from, ?string $options = null): string
    {
        if (static::get_is_iconv_enabled()) {
            $result = iconv($from, $to . ($options ?? static::$iconv_options), $text_value);
            if (false !== $result) {
                return $result;
            }
        }
        return (string) mb_convert_encoding($text_value, $to, $from);
    }
    /**
     * Get character count.
     *
     * @param string $encoding Encoding
     *
     * @return int Character count
     */
    public static function count_characters(string $text_value, string $encoding = 'UTF-8'): int
    {
        return mb_strlen($text_value, $encoding);
    }
    /**
     * Get character count using mb_strwidth rather than mb_strlen.
     *
     * @param string $encoding Encoding
     *
     * @return int Character count
     */
    public static function count_characters_dbcs(string $text_value, string $encoding = 'UTF-8'): int
    {
        return mb_strwidth($text_value, $encoding);
    }
    /**
     * Get a substring of a UTF-8 encoded string.
     *
     * @param string $textValue UTF-8 encoded string
     * @param int $offset Start offset
     * @param ?int $length Maximum number of characters in substring
     */
    public static function substring(string $text_value, int $offset, ?int $length = 0): string
    {
        return mb_substr($text_value, $offset, $length, 'UTF-8');
    }
    /**
     * Convert a UTF-8 encoded string to upper case.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function str_to_upper(string $text_value): string
    {
        return mb_convert_case($text_value, MB_CASE_UPPER, 'UTF-8');
    }
    /**
     * Convert a UTF-8 encoded string to lower case.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function str_to_lower(string $text_value): string
    {
        return mb_convert_case($text_value, MB_CASE_LOWER, 'UTF-8');
    }
    /**
     * Convert a UTF-8 encoded string to title/proper case
     * (uppercase every first character in each word, lower case all other characters).
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function str_to_title(string $text_value): string
    {
        return mb_convert_case($text_value, MB_CASE_TITLE, 'UTF-8');
    }
    public static function mb_is_upper(string $character): bool
    {
        return mb_strtolower($character, 'UTF-8') !== $character;
    }
    /**
     * Splits a UTF-8 string into an array of individual characters.
     *
     * @return string[]
     */
    public static function mb_str_split(string $string): array
    {
        // Split at all position not after the start: ^
        // and not before the end: $
        $split = Preg::split('/(?<!^)(?!$)/u', $string);
        return $split;
    }
    /**
     * Reverse the case of a string, so that all uppercase characters become lowercase
     * and all lowercase characters become uppercase.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function str_case_reverse(string $text_value): string
    {
        $characters = self::mb_str_split($text_value);
        foreach ($characters as &$character) {
            if (self::mb_is_upper($character)) {
                $character = mb_strtolower($character, 'UTF-8');
            } else {
                $character = mb_strtoupper($character, 'UTF-8');
            }
        }
        return implode('', $characters);
    }
    private static function use_alt(string $alt_value, string $default, bool $trim_alt): string
    {
        return ($trim_alt ? trim($alt_value) : $alt_value) ?: $default;
    }
    private static function get_locale_value(string $key, string $alt_key, string $default, bool $trim_alt = false): string
    {
        /** @var string[] */
        $localeconv = localeconv();
        $rslt = $localeconv[$key];
        // win-1252 implements Euro as 0x80 plus other symbols
        // Not suitable for Composer\Pcre\Preg
        if (preg_match('//u', $rslt) !== 1) {
            $rslt = '';
        }
        return $rslt ?: self::use_alt($localeconv[$alt_key], $default, $trim_alt);
    }
    /**
     * Get the decimal separator. If it has not yet been set explicitly, try to obtain number
     * formatting information from locale.
     */
    public static function get_decimal_separator(): string
    {
        if (!isset(static::$decimal_separator)) {
            static::$decimal_separator = self::get_locale_value('decimal_point', 'mon_decimal_point', '.');
        }
        return static::$decimal_separator;
    }
    /**
     * Set the decimal separator. Only used by NumberFormat::toFormattedString()
     * to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param ?string $separator Character for decimal separator
     */
    public static function set_decimal_separator(?string $separator): void
    {
        static::$decimal_separator = $separator;
    }
    /**
     * Get the thousands separator. If it has not yet been set explicitly, try to obtain number
     * formatting information from locale.
     */
    public static function get_thousands_separator(): string
    {
        if (!isset(static::$thousands_separator)) {
            static::$thousands_separator = self::get_locale_value('thousands_sep', 'mon_thousands_sep', ',');
        }
        return static::$thousands_separator;
    }
    /**
     * Set the thousands separator. Only used by NumberFormat::toFormattedString()
     * to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param ?string $separator Character for thousands separator
     */
    public static function set_thousands_separator(?string $separator): void
    {
        static::$thousands_separator = $separator;
    }
    /**
     *    Get the currency code. If it has not yet been set explicitly, try to obtain the
     *        symbol information from locale.
     */
    public static function get_currency_code(bool $trim_alt = false): string
    {
        if (!isset(static::$currency_code)) {
            static::$currency_code = self::get_locale_value('currency_symbol', 'int_curr_symbol', '$', $trim_alt);
        }
        return static::$currency_code;
    }
    /**
     * Set the currency code. Only used by NumberFormat::toFormattedString()
     *        to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param ?string $currencyCode Character for currency code
     */
    public static function set_currency_code(?string $currency_code): void
    {
        static::$currency_code = $currency_code;
    }
    /**
     * Convert SYLK encoded string to UTF-8.
     *
     * @param string $textValue SYLK encoded string
     *
     * @return string UTF-8 encoded string
     */
    public static function syl_kto_utf8(string $text_value): string
    {
        // If there is no escape character in the string there is nothing to do
        if (!str_contains($text_value, "\x1b")) {
            return $text_value;
        }
        foreach (self::SYLK_CHARACTERS as $k => $v) {
            $text_value = str_replace($k, $v, $text_value);
        }
        return $text_value;
    }
    /**
     * Retrieve any leading numeric part of a string, or return the full string if no leading numeric
     * (handles basic integer or float, but not exponent or non decimal).
     *
     * @return float|string string or only the leading numeric part of the string
     */
    public static function test_string_as_numeric(string $text_value): float|string
    {
        if (is_numeric($text_value)) {
            return $text_value;
        }
        $v = (float) $text_value;
        return is_numeric(substr($text_value, 0, strlen((string) $v))) ? $v : $text_value;
    }
    public static function strlen_allow_null(?string $string): int
    {
        return strlen("{$string}");
    }
    /**
     * @param bool $convertBool If true, convert bool to locale-aware TRUE/FALSE rather than 1/null-string
     * @param bool $lessFloatPrecision If true, floats will be converted to a more human-friendly but less computationally accurate value
     */
    public static function convert_to_string(mixed $value, bool $throw = true, string $default = '', bool $convert_bool = false, bool $less_float_precision = false): string
    {
        if ($convert_bool && is_bool($value)) {
            return $value ? Calculation::get_true() : Calculation::get_false();
        }
        if (is_float($value) && !$less_float_precision) {
            $string = (string) $value;
            // look out for scientific notation
            if (!Preg::is_match('/[^-+0-9.]/', $string)) {
                $minus = $value < 0 ? '-' : '';
                $positive = abs($value);
                $floor = floor($positive);
                $old_frac = (string) ($positive - $floor);
                $frac = Preg::replace('/^0[.](\d+)$/', '$1', $old_frac);
                if ($frac !== $old_frac) {
                    return "{$minus}{$floor}.{$frac}";
                }
            }
            return $string;
        }
        if ($value === null || is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }
        if ($throw) {
            throw new Spreadsheet_Exception('Unable to convert to string');
        }
        return $default;
    }
    /**
     * Assist with POST items when samples are run in browser.
     * Never run as part of unit tests, which are command line.
     *
     * @codeCoverageIgnore
     */
    public static function convert_post_to_string(string $index, string $default = ''): string
    {
        if (isset($_POST[$index])) {
            return htmlentities(self::convert_to_string($_POST[$index], false, $default));
        }
        return $default;
    }
    /**
     * Php introduced str_increment with Php8.3,
     * but didn't issue deprecation notices till 8.5.
     *
     * @codeCoverageIgnore
     */
    public static function string_increment(string &$str): string
    {
        if (function_exists('str_increment')) {
            $str = str_increment($str);
            // @phpstan-ignore-line
        } else {
            ++$str;
            // @phpstan-ignore-line
        }
        return $str;
        // @phpstan-ignore-line
    }
    /** @internal */
    protected static string $test_class = Intl_Calendar::class;
    /**
     * Set all of currencyCode, thousandsSeparator, decimalSeparator,
     * and Calculation locale with a single call.
     * The main point here is avoid the use of Php setlocale,
     * which is not threadsafe. It uses the Intl extension instead,
     * which is not a requirement for PhpSpreadsheet.
     * Because of that, the function returns a bool which will
     * be false if Intl is not available, or the supplied locale
     * is not valid according to Intl.
     */
    public static function set_locale(?string $locale): bool
    {
        if ($locale === null) {
            self::$currency_code = null;
            self::$thousands_separator = null;
            self::$decimal_separator = null;
            Calculation::get_instance()->set_locale('en_us');
            return true;
        }
        $locale_calc = $locale;
        if (Preg::is_match('/^([a-z][a-z])_([a-z][a-z])(?:[.]utf-8)?$/i', $locale, $matches)) {
            $locale = strtolower((string) $matches[1]) . '_' . strtoupper((string) $matches[2]);
            $locale_calc = strtolower((string) $matches[1]) . '_' . strtolower((string) $matches[2]);
        }
        if (!class_exists(static::$test_class)) {
            return false;
        }
        // NumberFormatter constructor succeeds even with
        // bad locale before Php8.4, so try to validate
        // the locale beforehand.
        $locales = Intl_Calendar::get_available_locales();
        if (!in_array($locale, $locales, true)) {
            return false;
        }
        $formatter = new Number_Formatter($locale, Number_Formatter::CURRENCY);
        $currency = $formatter->get_symbol(Number_Formatter::CURRENCY_SYMBOL);
        $formatter = new Number_Formatter($locale, Number_Formatter::DECIMAL);
        $thousands = $formatter->get_symbol(Number_Formatter::GROUPING_SEPARATOR_SYMBOL);
        $decimal = $formatter->get_symbol(Number_Formatter::DECIMAL_SEPARATOR_SYMBOL);
        self::$currency_code = $currency;
        self::$thousands_separator = $thousands;
        self::$decimal_separator = $decimal;
        Calculation::get_instance()->set_locale($locale_calc);
        return true;
    }
}