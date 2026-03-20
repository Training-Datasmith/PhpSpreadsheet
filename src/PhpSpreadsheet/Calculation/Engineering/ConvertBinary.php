<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Convert_Binary extends Convert_Base
{
    /**
     * toDecimal.
     *
     * Return a binary value as decimal.
     *
     * Excel Function:
     *        BIN2DEC(x)
     *
     * @param array<mixed>|bool|float|int|string $value The binary number (as a string) that you want to convert. The number
     *                                cannot contain more than 10 characters (10 bits). The most significant
     *                                bit of number is the sign bit. The remaining 9 bits are magnitude bits.
     *                                Negative numbers are represented using two's-complement notation.
     *                                If number is not a valid binary number, or if number contains more than
     *                                10 characters (10 bits), BIN2DEC returns the #NUM! error value.
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
            $value = self::validate_binary($value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (strlen($value) == 10 && $value[0] === '1') {
            //    Two's Complement
            $value = substr($value, -9);
            return -(512 - bindec($value));
        }
        return bindec($value);
    }
    /**
     * toHex.
     *
     * Return a binary value as hex.
     *
     * Excel Function:
     *        BIN2HEX(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The binary number (as a string) that you want to convert. The number
     *                                cannot contain more than 10 characters (10 bits). The most significant
     *                                bit of number is the sign bit. The remaining 9 bits are magnitude bits.
     *                                Negative numbers are represented using two's-complement notation.
     *                                If number is not a valid binary number, or if number contains more than
     *                                10 characters (10 bits), BIN2HEX returns the #NUM! error value.
     *                      Or can be an array of values
     * @param null|array<mixed>|float|int|string $places The number of characters to use. If places is omitted, BIN2HEX uses the
     *                                minimum number of characters necessary. Places is useful for padding the
     *                                return value with leading 0s (zeros).
     *                                If places is not an integer, it is truncated.
     *                                If places is nonnumeric, BIN2HEX returns the #VALUE! error value.
     *                                If places is negative, BIN2HEX returns the #NUM! error value.
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
            $value = self::validate_binary($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (strlen($value) == 10 && $value[0] === '1') {
            $high2 = substr($value, 0, 2);
            $low8 = substr($value, 2);
            $xarr = ['00' => '00000000', '01' => '00000001', '10' => 'FFFFFFFE', '11' => 'FFFFFFFF'];
            return $xarr[$high2] . strtoupper(substr('0' . dechex((int) bindec($low8)), -2));
        }
        $hex_val = (string) strtoupper(dechex((int) bindec($value)));
        return self::nbr_conversion_format($hex_val, $places);
    }
    /**
     * toOctal.
     *
     * Return a binary value as octal.
     *
     * Excel Function:
     *        BIN2OCT(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The binary number (as a string) that you want to convert. The number
     *                                cannot contain more than 10 characters (10 bits). The most significant
     *                                bit of number is the sign bit. The remaining 9 bits are magnitude bits.
     *                                Negative numbers are represented using two's-complement notation.
     *                                If number is not a valid binary number, or if number contains more than
     *                                10 characters (10 bits), BIN2OCT returns the #NUM! error value.
     *                      Or can be an array of values
     * @param null|array<mixed>|float|int|string $places The number of characters to use. If places is omitted, BIN2OCT uses the
     *                                minimum number of characters necessary. Places is useful for padding the
     *                                return value with leading 0s (zeros).
     *                                If places is not an integer, it is truncated.
     *                                If places is nonnumeric, BIN2OCT returns the #VALUE! error value.
     *                                If places is negative, BIN2OCT returns the #NUM! error value.
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
            $value = self::validate_binary($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if (strlen($value) == 10 && $value[0] === '1') {
            //    Two's Complement
            return str_repeat('7', 6) . strtoupper(decoct((int) bindec("11{$value}")));
        }
        $oct_val = decoct((int) bindec($value));
        return self::nbr_conversion_format($oct_val, $places);
    }
    protected static function validate_binary(string $value): string
    {
        if (strlen($value) > preg_match_all('/[01]/', $value) || strlen($value) > 10) {
            throw new Exception(Excel_Error::NAN());
        }
        return $value;
    }
}