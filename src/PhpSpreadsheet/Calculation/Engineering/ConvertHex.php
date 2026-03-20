<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Convert_Hex extends Convert_Base
{
    /**
     * toBinary.
     *
     * Return a hex value as binary.
     *
     * Excel Function:
     *        HEX2BIN(x[,places])
     *
     * @param array<mixed>|bool|float|string $value The hexadecimal number you want to convert.
     *                      Number cannot contain more than 10 characters.
     *                      The most significant bit of number is the sign bit (40th bit from the right).
     *                      The remaining 9 bits are magnitude bits.
     *                      Negative numbers are represented using two's-complement notation.
     *                      If number is negative, HEX2BIN ignores places and returns a 10-character binary number.
     *                      If number is negative, it cannot be less than FFFFFFFE00,
     *                          and if number is positive, it cannot be greater than 1FF.
     *                      If number is not a valid hexadecimal number, HEX2BIN returns the #NUM! error value.
     *                      If HEX2BIN requires more than places characters, it returns the #NUM! error value.
     *                      Or can be an array of values
     * @param array<mixed>|int $places The number of characters to use. If places is omitted,
     *                          HEX2BIN uses the minimum number of characters necessary. Places
     *                          is useful for padding the return value with leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, HEX2BIN returns the #VALUE! error value.
     *                      If places is negative, HEX2BIN returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|string Result, or an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_binary($value, $places = null): array|string
    {
        if (is_array($value) || is_array($places)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $places);
        }
        try {
            $value = self::validate_value($value);
            $value = self::validate_hex($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $dec = self::to_decimal($value);
        return Convert_Decimal::to_binary($dec, $places);
    }
    /**
     * toDecimal.
     *
     * Return a hex value as decimal.
     *
     * Excel Function:
     *        HEX2DEC(x)
     *
     * @param array<mixed>|bool|float|int|string $value The hexadecimal number you want to convert. This number cannot
     *                          contain more than 10 characters (40 bits). The most significant
     *                          bit of number is the sign bit. The remaining 39 bits are magnitude
     *                          bits. Negative numbers are represented using two's-complement
     *                          notation.
     *                      If number is not a valid hexadecimal number, HEX2DEC returns the
     *                          #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string Result, or an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_decimal($value): array|string|float|int
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        try {
            $value = self::validate_value($value);
            $value = self::validate_hex($value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (strlen($value) > 10) {
            return Excel_Error::NAN();
        }
        $bin_x = '';
        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            $bin_x .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
        }
        if (strlen($bin_x) == 40 && $bin_x[0] == '1') {
            for ($i = 0; $i < 40; ++$i) {
                $bin_x[$i] = $bin_x[$i] == '1' ? '0' : '1';
            }
            return (bindec($bin_x) + 1) * -1;
        }
        return bindec($bin_x);
    }
    /**
     * toOctal.
     *
     * Return a hex value as octal.
     *
     * Excel Function:
     *        HEX2OCT(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The hexadecimal number you want to convert. Number cannot
     *                                    contain more than 10 characters. The most significant bit of
     *                                    number is the sign bit. The remaining 39 bits are magnitude
     *                                    bits. Negative numbers are represented using two's-complement
     *                                    notation.
     *                                    If number is negative, HEX2OCT ignores places and returns a
     *                                    10-character octal number.
     *                                    If number is negative, it cannot be less than FFE0000000, and
     *                                    if number is positive, it cannot be greater than 1FFFFFFF.
     *                                    If number is not a valid hexadecimal number, HEX2OCT returns
     *                                    the #NUM! error value.
     *                                    If HEX2OCT requires more than places characters, it returns
     *                                    the #NUM! error value.
     *                      Or can be an array of values
     * @param array<mixed>|int $places The number of characters to use. If places is omitted, HEX2OCT
     *                                    uses the minimum number of characters necessary. Places is
     *                                    useful for padding the return value with leading 0s (zeros).
     *                                    If places is not an integer, it is truncated.
     *                                    If places is nonnumeric, HEX2OCT returns the #VALUE! error
     *                                    value.
     *                                    If places is negative, HEX2OCT returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|string Result, or an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_octal($value, $places = null): array|string
    {
        if (is_array($value) || is_array($places)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $places);
        }
        try {
            $value = self::validate_value($value);
            $value = self::validate_hex($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $decimal = self::to_decimal($value);
        return Convert_Decimal::to_octal($decimal, $places);
    }
    protected static function validate_hex(string $value): string
    {
        if (strlen($value) > preg_match_all('/[0123456789ABCDEF]/', $value)) {
            throw new Exception(Excel_Error::NAN());
        }
        return $value;
    }
}