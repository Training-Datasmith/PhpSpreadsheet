<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Text_Data\Format;
class Dollar
{
    use Array_Enabled;
    /**
     * DOLLAR.
     *
     * This function converts a number to text using currency format, with the decimals rounded to the specified place.
     * The format used is $#,##0.00_);($#,##0.00)..
     *
     * @param mixed $number The value to format, or can be an array of numbers
     *                         Or can be an array of values
     * @param mixed $precision The number of digits to display to the right of the decimal point (as an integer).
     *                            If precision is negative, number is rounded to the left of the decimal point.
     *                            If you omit precision, it is assumed to be 2
     *              Or can be an array of precision values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function format(mixed $number, mixed $precision = 2)
    {
        return Format::DOLLAR($number, $precision);
    }
    /**
     * DOLLARDE.
     *
     * Converts a dollar price expressed as an integer part and a fraction
     *        part into a dollar price expressed as a decimal number.
     * Fractional dollar numbers are sometimes used for security prices.
     *
     * Excel Function:
     *        DOLLARDE(fractional_dollar,fraction)
     *
     * @param mixed $fractionalDollar Fractional Dollar
     *              Or can be an array of values
     * @param mixed $fraction Fraction
     *              Or can be an array of values
     *
     * @return array<mixed>|float|string
     */
    public static function decimal(mixed $fractional_dollar = null, mixed $fraction = 0): array|string|float
    {
        if (is_array($fractional_dollar) || is_array($fraction)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $fractional_dollar, $fraction);
        }
        try {
            $fractional_dollar = Financial_Validations::validate_float(Functions::flatten_single_value($fractional_dollar) ?? 0.0);
            $fraction = Financial_Validations::validate_int(Functions::flatten_single_value($fraction));
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Additional parameter validations
        if ($fraction < 0) {
            return Excel_Error::NAN();
        }
        if ($fraction == 0) {
            return Excel_Error::DIV0();
        }
        $dollars = $fractional_dollar < 0 ? ceil($fractional_dollar) : floor($fractional_dollar);
        $cents = fmod($fractional_dollar, 1.0);
        $cents /= $fraction;
        $cents *= 10 ** ceil(log10($fraction));
        return $dollars + $cents;
    }
    /**
     * DOLLARFR.
     *
     * Converts a dollar price expressed as a decimal number into a dollar price
     *        expressed as a fraction.
     * Fractional dollar numbers are sometimes used for security prices.
     *
     * Excel Function:
     *        DOLLARFR(decimal_dollar,fraction)
     *
     * @param mixed $decimalDollar Decimal Dollar
     *              Or can be an array of values
     * @param mixed $fraction Fraction
     *              Or can be an array of values
     *
     * @return array<mixed>|float|string
     */
    public static function fractional(mixed $decimal_dollar = null, mixed $fraction = 0): array|string|float
    {
        if (is_array($decimal_dollar) || is_array($fraction)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $decimal_dollar, $fraction);
        }
        try {
            $decimal_dollar = Financial_Validations::validate_float(Functions::flatten_single_value($decimal_dollar) ?? 0.0);
            $fraction = Financial_Validations::validate_int(Functions::flatten_single_value($fraction));
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Additional parameter validations
        if ($fraction < 0) {
            return Excel_Error::NAN();
        }
        if ($fraction == 0) {
            return Excel_Error::DIV0();
        }
        $dollars = $decimal_dollar < 0.0 ? ceil($decimal_dollar) : floor($decimal_dollar);
        $cents = fmod($decimal_dollar, 1);
        $cents *= $fraction;
        $cents *= 10 ** -ceil(log10($fraction));
        return $dollars + $cents;
    }
}