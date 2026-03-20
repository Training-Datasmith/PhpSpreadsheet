<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Bit_Wise
{
    use Array_Enabled;
    public const SPLIT_DIVISOR = 2 ** 24;
    /**
     * Split a number into upper and lower portions for full 32-bit support.
     *
     * @return int[]
     */
    private static function split_number(float|int $number): array
    {
        return [(int) floor($number / self::SPLIT_DIVISOR), (int) fmod($number, self::SPLIT_DIVISOR)];
    }
    /**
     * BITAND.
     *
     * Returns the bitwise AND of two integer values.
     *
     * Excel Function:
     *        BITAND(number1, number2)
     *
     * @param null|array<mixed>|bool|float|int|string $number1 Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $number2 Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BITAND(null|array|bool|float|int|string $number1, null|array|bool|float|int|string $number2): array|string|int|float
    {
        if (is_array($number1) || is_array($number2)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number1, $number2);
        }
        try {
            $number1 = self::validate_bitwise_argument($number1);
            $number2 = self::validate_bitwise_argument($number2);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $split1 = self::split_number($number1);
        $split2 = self::split_number($number2);
        return self::SPLIT_DIVISOR * ($split1[0] & $split2[0]) + ($split1[1] & $split2[1]);
    }
    /**
     * BITOR.
     *
     * Returns the bitwise OR of two integer values.
     *
     * Excel Function:
     *        BITOR(number1, number2)
     *
     * @param null|array<mixed>|bool|float|int|string $number1 Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $number2 Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BITOR(null|array|bool|float|int|string $number1, null|array|bool|float|int|string $number2): array|string|int|float
    {
        if (is_array($number1) || is_array($number2)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number1, $number2);
        }
        try {
            $number1 = self::validate_bitwise_argument($number1);
            $number2 = self::validate_bitwise_argument($number2);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $split1 = self::split_number($number1);
        $split2 = self::split_number($number2);
        return self::SPLIT_DIVISOR * ($split1[0] | $split2[0]) + ($split1[1] | $split2[1]);
    }
    /**
     * BITXOR.
     *
     * Returns the bitwise XOR of two integer values.
     *
     * Excel Function:
     *        BITXOR(number1, number2)
     *
     * @param null|array<mixed>|bool|float|int|string $number1 Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $number2 Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BITXOR(null|array|bool|float|int|string $number1, null|array|bool|float|int|string $number2): array|string|int|float
    {
        if (is_array($number1) || is_array($number2)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number1, $number2);
        }
        try {
            $number1 = self::validate_bitwise_argument($number1);
            $number2 = self::validate_bitwise_argument($number2);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $split1 = self::split_number($number1);
        $split2 = self::split_number($number2);
        return self::SPLIT_DIVISOR * ($split1[0] ^ $split2[0]) + ($split1[1] ^ $split2[1]);
    }
    /**
     * BITLSHIFT.
     *
     * Returns the number value shifted left by shift_amount bits.
     *
     * Excel Function:
     *        BITLSHIFT(number, shift_amount)
     *
     * @param null|array<mixed>|bool|float|int|string $number Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $shiftAmount Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BITLSHIFT(null|array|bool|float|int|string $number, null|array|bool|float|int|string $shift_amount): array|string|float
    {
        if (is_array($number) || is_array($shift_amount)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $shift_amount);
        }
        try {
            $number = self::validate_bitwise_argument($number);
            $shift_amount = self::validate_shift_amount($shift_amount);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $result = floor($number * 2 ** $shift_amount);
        if ($result > 2 ** 48 - 1) {
            return Excel_Error::NAN();
        }
        return $result;
    }
    /**
     * BITRSHIFT.
     *
     * Returns the number value shifted right by shift_amount bits.
     *
     * Excel Function:
     *        BITRSHIFT(number, shift_amount)
     *
     * @param null|array<mixed>|bool|float|int|string $number Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $shiftAmount Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BITRSHIFT(null|array|bool|float|int|string $number, null|array|bool|float|int|string $shift_amount): array|string|float
    {
        if (is_array($number) || is_array($shift_amount)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $number, $shift_amount);
        }
        try {
            $number = self::validate_bitwise_argument($number);
            $shift_amount = self::validate_shift_amount($shift_amount);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $result = floor($number / 2 ** $shift_amount);
        if ($result > 2 ** 48 - 1) {
            // possible because shiftAmount can be negative
            return Excel_Error::NAN();
        }
        return $result;
    }
    /**
     * Validate arguments passed to the bitwise functions.
     */
    private static function validate_bitwise_argument(mixed $value): float
    {
        $value = self::null_false_true_to_number($value);
        if (is_numeric($value)) {
            $value = (float) $value;
            if ($value == floor($value)) {
                if ($value > 2 ** 48 - 1 || $value < 0) {
                    throw new Exception(Excel_Error::NAN());
                }
                return floor($value);
            }
            throw new Exception(Excel_Error::NAN());
        }
        throw new Exception(Excel_Error::VALUE());
    }
    /**
     * Validate arguments passed to the bitwise functions.
     */
    private static function validate_shift_amount(mixed $value): int
    {
        $value = self::null_false_true_to_number($value);
        if (is_numeric($value)) {
            if (abs($value + 0) > 53) {
                throw new Exception(Excel_Error::NAN());
            }
            return (int) $value;
        }
        throw new Exception(Excel_Error::VALUE());
    }
    /**
     * Many functions accept null/false/true argument treated as 0/0/1.
     */
    private static function null_false_true_to_number(mixed &$number): mixed
    {
        if ($number === null) {
            $number = 0;
        } elseif (is_bool($number)) {
            $number = (int) $number;
        }
        return $number;
    }
}