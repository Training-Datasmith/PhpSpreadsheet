<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Complex\Complex as ComplexObject;
use Complex\Exception as ComplexException;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Complex
{
    use Array_Enabled;
    /**
     * COMPLEX.
     *
     * Converts real and imaginary coefficients into a complex number of the form x +/- yi or x +/- yj.
     *
     * Excel Function:
     *        COMPLEX(realNumber,imaginary[,suffix])
     *
     * @param mixed $realNumber the real float coefficient of the complex number
     *                      Or can be an array of values
     * @param mixed $imaginary the imaginary float coefficient of the complex number
     *                      Or can be an array of values
     * @param mixed $suffix The character suffix for the imaginary component of the complex number.
     *                          If omitted, the suffix is assumed to be "i".
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function COMPLEX(mixed $real_number = 0.0, mixed $imaginary = 0.0, mixed $suffix = 'i'): array|string
    {
        if (is_array($real_number) || is_array($imaginary) || is_array($suffix)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $real_number, $imaginary, $suffix);
        }
        $real_number ??= 0.0;
        $imaginary ??= 0.0;
        $suffix ??= 'i';
        try {
            $real_number = Engineering_Validations::validate_float($real_number);
            $imaginary = Engineering_Validations::validate_float($imaginary);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($suffix === 'i' || $suffix === 'j' || $suffix === '') {
            $complex = new Complex_Object($real_number, $imaginary, $suffix);
            return (string) $complex;
        }
        return Excel_Error::VALUE();
    }
    /**
     * IMAGINARY.
     *
     * Returns the imaginary coefficient of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMAGINARY(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the imaginary
     *                                         coefficient
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string (string if an error)
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMAGINARY($complex_number): array|string|float
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return $complex->get_imaginary();
    }
    /**
     * IMREAL.
     *
     * Returns the real coefficient of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMREAL(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the real coefficient
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string (string if an error)
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMREAL($complex_number): array|string|float
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return $complex->get_real();
    }
}