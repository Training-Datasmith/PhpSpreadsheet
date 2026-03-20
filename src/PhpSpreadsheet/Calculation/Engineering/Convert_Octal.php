<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Convert_Octal extends Convert_Base
{
    /**
     * toBinary.
     *
     * Return an octal value as binary.
     *
     * Excel Function:
     *        OCT2BIN(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The octal number you want to convert. Number may not
     *                          contain more than 10 characters. The most significant
     *                          bit of number is the sign bit. The remaining 29 bits
     *                          are magnitude bits. Negative numbers are represented
     *                          using two's-complement notation.
     *                      If number is negative, OCT2BIN ignores places and returns
     *                          a 10-character binary number.
     *                      If number is negative, it cannot be less than 7777777000,
     *                          and if number is positive, it cannot be greater than 777.
     *                      If number is not a valid octal number, OCT2BIN returns
     *                          the #NUM! error value.
     *                      If OCT2BIN requires more than places characters, it
     *                          returns the #NUM! error value.
     *                      Or can be an array of values
     * @param array<mixed>|int $places The number of characters to use. If places is omitted,
     *                          OCT2BIN uses the minimum number of characters necessary.
     *                          Places is useful for padding the return value with
     *                          leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, OCT2BIN returns the #VALUE!
     *                          error value.
     *                      If places is negative, OCT2BIN returns the #NUM! error
     *                          value.
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
            $value = self::validate_octal($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Convert_Decimal::to_binary(self::to_decimal($value), $places);
    }
    /**
     * toDecimal.
     *
     * Return an octal value as decimal.
     *
     * Excel Function:
     *        OCT2DEC(x)
     *
     * @param array<mixed>|bool|float|int|string $value The octal number you want to convert. Number may not contain
     *                          more than 10 octal characters (30 bits). The most significant
     *                          bit of number is the sign bit. The remaining 29 bits are
     *                          magnitude bits. Negative numbers are represented using
     *                          two's-complement notation.
     *                      If number is not a valid octal number, OCT2DEC returns the
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
            $value = self::validate_octal($value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $bin_x = '';
        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            $bin_x .= str_pad(decbin((int) $char), 3, '0', STR_PAD_LEFT);
        }
        if (strlen($bin_x) == 30 && $bin_x[0] == '1') {
            for ($i = 0; $i < 30; ++$i) {
                $bin_x[$i] = $bin_x[$i] == '1' ? '0' : '1';
            }
            return (bindec($bin_x) + 1) * -1;
        }
        return bindec($bin_x);
    }
    /**
     * toHex.
     *
     * Return an octal value as hex.
     *
     * Excel Function:
     *        OCT2HEX(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The octal number you want to convert. Number may not contain
     *                          more than 10 octal characters (30 bits). The most significant
     *                          bit of number is the sign bit. The remaining 29 bits are
     *                          magnitude bits. Negative numbers are represented using
     *                          two's-complement notation.
     *                      If number is negative, OCT2HEX ignores places and returns a
     *                          10-character hexadecimal number.
     *                      If number is not a valid octal number, OCT2HEX returns the
     *                          #NUM! error value.
     *                      If OCT2HEX requires more than places characters, it returns
     *                          the #NUM! error value.
     *                      Or can be an array of values
     * @param array<mixed>|int $places The number of characters to use. If places is omitted, OCT2HEX
     *                          uses the minimum number of characters necessary. Places is useful
     *                          for padding the return value with leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, OCT2HEX returns the #VALUE! error value.
     *                      If places is negative, OCT2HEX returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|string Result, or an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function to_hex($value, $places = null): array|string
    {
        if (is_array($value) || is_array($places)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $places);
        }
        try {
            $value = self::validate_value($value);
            $value = self::validate_octal($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $hex_val = strtoupper(dechex((int) self::to_decimal($value)));
        $hex_val = PHP_INT_SIZE === 4 && strlen($value) === 10 && $value[0] >= '4' ? "FF{$hex_val}" : $hex_val;
        return self::nbr_conversion_format($hex_val, $places);
    }
    protected static function validate_octal(string $value): string
    {
        $num_digits = (int) preg_match_all('/[01234567]/', $value);
        if (strlen($value) > $num_digits || $num_digits > 10) {
            throw new Exception(Excel_Error::NAN());
        }
        return $value;
    }
}