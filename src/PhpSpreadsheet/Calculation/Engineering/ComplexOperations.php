<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Complex\Complex as ComplexObject;
use Complex\Exception as ComplexException;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Complex_Operations
{
    use Array_Enabled;
    /**
     * IMDIV.
     *
     * Returns the quotient of two complex numbers in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMDIV(complexDividend,complexDivisor)
     *
     * @param array<mixed>|string $complexDividend the complex numerator or dividend
     *                      Or can be an array of values
     * @param array<mixed>|string $complexDivisor the complex denominator or divisor
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMDIV(array|string $complex_dividend, array|string $complex_divisor): array|string
    {
        if (is_array($complex_dividend) || is_array($complex_divisor)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $complex_dividend, $complex_divisor);
        }
        try {
            return (string) (new Complex_Object($complex_dividend))->divideby(new Complex_Object($complex_divisor));
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
    }
    /**
     * IMSUB.
     *
     * Returns the difference of two complex numbers in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSUB(complexNumber1,complexNumber2)
     *
     * @param array<mixed>|string $complexNumber1 the complex number from which to subtract complexNumber2
     *                      Or can be an array of values
     * @param array<mixed>|string $complexNumber2 the complex number to subtract from complexNumber1
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSUB(array|string $complex_number1, array|string $complex_number2): array|string
    {
        if (is_array($complex_number1) || is_array($complex_number2)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $complex_number1, $complex_number2);
        }
        try {
            return (string) (new Complex_Object($complex_number1))->subtract(new Complex_Object($complex_number2));
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
    }
    /**
     * IMSUM.
     *
     * Returns the sum of two or more complex numbers in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSUM(complexNumber[,complexNumber[,...]])
     *
     * @param string ...$complexNumbers Series of complex numbers to add
     */
    public static function IMSUM(...$complex_numbers): string
    {
        // Return value
        $return_value = new Complex_Object(0.0);
        $a_args = Functions::flatten_array($complex_numbers);
        try {
            // Loop through the arguments
            foreach ($a_args as $complex) {
                $return_value = $return_value->add(new Complex_Object($complex));
            }
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $return_value;
    }
    /**
     * IMPRODUCT.
     *
     * Returns the product of two or more complex numbers in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMPRODUCT(complexNumber[,complexNumber[,...]])
     *
     * @param string ...$complexNumbers Series of complex numbers to multiply
     */
    public static function IMPRODUCT(...$complex_numbers): string
    {
        // Return value
        $return_value = new Complex_Object(1.0);
        $a_args = Functions::flatten_array($complex_numbers);
        try {
            // Loop through the arguments
            foreach ($a_args as $complex) {
                $return_value = $return_value->multiply(new Complex_Object($complex));
            }
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $return_value;
    }
}