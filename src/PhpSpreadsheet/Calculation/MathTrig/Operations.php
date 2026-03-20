<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Operations
{
    use Array_Enabled;
    /**
     * MOD.
     *
     * @param mixed $dividend Dividend
     *                      Or can be an array of values
     * @param mixed $divisor Divisor
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Remainder, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function mod(mixed $dividend, mixed $divisor): array|string|float
    {
        if (is_array($dividend) || is_array($divisor)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $dividend, $divisor);
        }
        try {
            $dividend = Helpers::validate_numeric_null_bool($dividend);
            $divisor = Helpers::validate_numeric_null_bool($divisor);
            Helpers::validate_not_zero($divisor);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($dividend < 0.0 && $divisor > 0.0) {
            return $divisor - fmod(abs($dividend), $divisor);
        }
        if ($dividend > 0.0 && $divisor < 0.0) {
            return $divisor + fmod($dividend, abs($divisor));
        }
        return fmod($dividend, $divisor);
    }
    /**
     * POWER.
     *
     * Computes x raised to the power y.
     *
     * @param null|array<mixed>|bool|float|int|string $x Or can be an array of values
     * @param null|array<mixed>|bool|float|int|string $y Or can be an array of values
     *
     * @return array<mixed>|float|int|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function power(null|array|bool|float|int|string $x, null|array|bool|float|int|string $y): array|float|int|string
    {
        if (is_array($x) || is_array($y)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $x, $y);
        }
        try {
            $x = Helpers::validate_numeric_null_bool($x);
            $y = Helpers::validate_numeric_null_bool($y);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if (!$x && !$y) {
            return Excel_Error::NAN();
        }
        if (!$x && $y < 0.0) {
            return Excel_Error::DIV0();
        }
        // Return
        $result = $x ** $y;
        return Helpers::number_or_nan($result);
    }
    /**
     * PRODUCT.
     *
     * PRODUCT returns the product of all the values and cells referenced in the argument list.
     *
     * Excel Function:
     *        PRODUCT(value1[,value2[, ...]])
     *
     * @param mixed ...$args Data values
     */
    public static function product(mixed ...$args): string|float
    {
        $args = array_filter(Functions::flatten_array($args), fn($value): bool => $value !== null);
        // Return value
        $return_value = count($args) === 0 ? 0.0 : 1.0;
        // Loop through arguments
        foreach ($args as $arg) {
            // Is it a numeric value?
            if (is_numeric($arg)) {
                $return_value *= $arg;
            } else {
                return Excel_Error::throw_error($arg);
            }
        }
        return (float) $return_value;
    }
    /**
     * QUOTIENT.
     *
     * QUOTIENT function returns the integer portion of a division. Numerator is the divided number
     *        and denominator is the divisor.
     *
     * Excel Function:
     *        QUOTIENT(value1,value2)
     *
     * @param mixed $numerator Expect float|int
     *                      Or can be an array of values
     * @param mixed $denominator Expect float|int
     *                      Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function quotient(mixed $numerator, mixed $denominator): array|string|int
    {
        if (is_array($numerator) || is_array($denominator)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $numerator, $denominator);
        }
        try {
            $numerator = Helpers::validate_numeric_null_substitution($numerator, 0);
            $denominator = Helpers::validate_numeric_null_substitution($denominator, 0);
            Helpers::validate_not_zero($denominator);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return (int) ($numerator / $denominator);
    }
}