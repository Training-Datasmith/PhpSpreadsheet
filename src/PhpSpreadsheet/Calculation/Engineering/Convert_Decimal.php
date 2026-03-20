<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Convert_Decimal extends Convert_Base
{
    public const LARGEST_OCTAL_IN_DECIMAL = 536870911;
    public const SMALLEST_OCTAL_IN_DECIMAL = -536870912;
    public const LARGEST_BINARY_IN_DECIMAL = 511;
    public const SMALLEST_BINARY_IN_DECIMAL = -512;
    public const LARGEST_HEX_IN_DECIMAL = 549755813887;
    public const SMALLEST_HEX_IN_DECIMAL = -549755813888;
    /**
     * toBinary.
     *
     * Return a decimal value as binary.
     *
     * Excel Function:
     *        DEC2BIN(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The decimal integer you want to convert. If number is negative,
     *                          valid place values are ignored and DEC2BIN returns a 10-character
     *                          (10-bit) binary number in which the most significant bit is the sign
     *                          bit. The remaining 9 bits are magnitude bits. Negative numbers are
     *                          represented using two's-complement notation.
     *                      If number < -512 or if number > 511, DEC2BIN returns the #NUM! error
     *                          value.
     *                      If number is nonnumeric, DEC2BIN returns the #VALUE! error value.
     *                      If DEC2BIN requires more than places characters, it returns the #NUM!
     *                          error value.
     *                      Or can be an array of values
     * @param null|array<mixed>|float|int|string $places The number of characters to use. If places is omitted, DEC2BIN uses
     *                          the minimum number of characters necessary. Places is useful for
     *                          padding the return value with leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, DEC2BIN returns the #VALUE! error value.
     *                      If places is zero or negative, DEC2BIN returns the #NUM! error value.
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
            $value = self::validate_decimal($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $value = (int) floor((float) $value);
        if ($value > self::LARGEST_BINARY_IN_DECIMAL || $value < self::SMALLEST_BINARY_IN_DECIMAL) {
            return Excel_Error::NAN();
        }
        $r = decbin($value);
        // Two's Complement
        $r = substr($r, -10);
        return self::nbr_conversion_format($r, $places);
    }
    /**
     * toHex.
     *
     * Return a decimal value as hex.
     *
     * Excel Function:
     *        DEC2HEX(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The decimal integer you want to convert. If number is negative,
     *                          places is ignored and DEC2HEX returns a 10-character (40-bit)
     *                          hexadecimal number in which the most significant bit is the sign
     *                          bit. The remaining 39 bits are magnitude bits. Negative numbers
     *                          are represented using two's-complement notation.
     *                      If number < -549,755,813,888 or if number > 549,755,813,887,
     *                          DEC2HEX returns the #NUM! error value.
     *                      If number is nonnumeric, DEC2HEX returns the #VALUE! error value.
     *                      If DEC2HEX requires more than places characters, it returns the
     *                          #NUM! error value.
     *                      Or can be an array of values
     * @param null|array<mixed>|float|int|string $places The number of characters to use. If places is omitted, DEC2HEX uses
     *                          the minimum number of characters necessary. Places is useful for
     *                          padding the return value with leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, DEC2HEX returns the #VALUE! error value.
     *                      If places is zero or negative, DEC2HEX returns the #NUM! error value.
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
            $value = self::validate_decimal($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $value = floor((float) $value);
        if ($value > self::LARGEST_HEX_IN_DECIMAL || $value < self::SMALLEST_HEX_IN_DECIMAL) {
            return Excel_Error::NAN();
        }
        $r = strtoupper(dechex((int) $value));
        $r = self::hex32bit($value, $r);
        return self::nbr_conversion_format($r, $places);
    }
    public static function hex32bit(float $value, string $hexstr, bool $force = false): string
    {
        if (PHP_INT_SIZE === 4 || $force) {
            if ($value >= 2 ** 32) {
                $quotient = (int) ($value / 2 ** 32);
                return strtoupper(substr('0' . dechex($quotient), -2) . $hexstr);
            }
            if ($value < -2 ** 32) {
                $quotient = 256 - (int) ceil(-$value / 2 ** 32);
                return strtoupper(substr('0' . dechex($quotient), -2) . substr("00000000{$hexstr}", -8));
            }
            if ($value < 0) {
                return "FF{$hexstr}";
            }
        }
        return $hexstr;
    }
    /**
     * toOctal.
     *
     * Return a decimal value as octal.
     *
     * Excel Function:
     *        DEC2OCT(x[,places])
     *
     * @param array<mixed>|bool|float|int|string $value The decimal integer you want to convert. If number is negative,
     *                          places is ignored and DEC2OCT returns a 10-character (30-bit)
     *                          octal number in which the most significant bit is the sign bit.
     *                          The remaining 29 bits are magnitude bits. Negative numbers are
     *                          represented using two's-complement notation.
     *                      If number < -536,870,912 or if number > 536,870,911, DEC2OCT
     *                          returns the #NUM! error value.
     *                      If number is nonnumeric, DEC2OCT returns the #VALUE! error value.
     *                      If DEC2OCT requires more than places characters, it returns the
     *                          #NUM! error value.
     *                      Or can be an array of values
     * @param array<mixed>|int $places The number of characters to use. If places is omitted, DEC2OCT uses
     *                          the minimum number of characters necessary. Places is useful for
     *                          padding the return value with leading 0s (zeros).
     *                      If places is not an integer, it is truncated.
     *                      If places is nonnumeric, DEC2OCT returns the #VALUE! error value.
     *                      If places is zero or negative, DEC2OCT returns the #NUM! error value.
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
            $value = self::validate_decimal($value);
            $places = self::validate_places($places);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $value = (int) floor((float) $value);
        if ($value > self::LARGEST_OCTAL_IN_DECIMAL || $value < self::SMALLEST_OCTAL_IN_DECIMAL) {
            return Excel_Error::NAN();
        }
        $r = decoct($value);
        $r = substr($r, -10);
        return self::nbr_conversion_format($r, $places);
    }
    protected static function validate_decimal(string $value): string
    {
        if (strlen($value) > preg_match_all('/[-0123456789.]/', $value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return $value;
    }
}