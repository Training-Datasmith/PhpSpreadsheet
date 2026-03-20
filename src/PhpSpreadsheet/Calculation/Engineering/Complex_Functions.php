<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Complex\Complex as ComplexObject;
use Complex\Exception as ComplexException;
use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Complex_Functions
{
    use Array_Enabled;
    /**
     * IMABS.
     *
     * Returns the absolute value (modulus) of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMABS(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the absolute value
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMABS(array|string $complex_number): array|float|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return $complex->abs();
    }
    /**
     * IMARGUMENT.
     *
     * Returns the argument theta of a complex number, i.e. the angle in radians from the real
     * axis to the representation of the number in polar coordinates.
     *
     * Excel Function:
     *        IMARGUMENT(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the argument theta
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMARGUMENT(array|string $complex_number): array|float|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        if ($complex->get_real() == 0.0 && $complex->get_imaginary() == 0.0) {
            return Excel_Error::DIV0();
        }
        return $complex->argument();
    }
    /**
     * IMCONJUGATE.
     *
     * Returns the complex conjugate of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCONJUGATE(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the conjugate
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCONJUGATE(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->conjugate();
    }
    /**
     * IMCOS.
     *
     * Returns the cosine of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCOS(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the cosine
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCOS(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->cos();
    }
    /**
     * IMCOSH.
     *
     * Returns the hyperbolic cosine of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCOSH(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the hyperbolic cosine
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCOSH(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->cosh();
    }
    /**
     * IMCOT.
     *
     * Returns the cotangent of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCOT(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the cotangent
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCOT(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->cot();
    }
    /**
     * IMCSC.
     *
     * Returns the cosecant of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCSC(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the cosecant
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCSC(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->csc();
    }
    /**
     * IMCSCH.
     *
     * Returns the hyperbolic cosecant of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMCSCH(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the hyperbolic cosecant
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMCSCH(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->csch();
    }
    /**
     * IMSIN.
     *
     * Returns the sine of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSIN(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the sine
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSIN(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->sin();
    }
    /**
     * IMSINH.
     *
     * Returns the hyperbolic sine of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSINH(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the hyperbolic sine
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSINH(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->sinh();
    }
    /**
     * IMSEC.
     *
     * Returns the secant of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSEC(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the secant
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSEC(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->sec();
    }
    /**
     * IMSECH.
     *
     * Returns the hyperbolic secant of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSECH(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the hyperbolic secant
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSECH(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->sech();
    }
    /**
     * IMTAN.
     *
     * Returns the tangent of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMTAN(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the tangent
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMTAN(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->tan();
    }
    /**
     * IMSQRT.
     *
     * Returns the square root of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMSQRT(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the square root
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMSQRT(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        $theta = self::IMARGUMENT($complex_number);
        if ($theta === Excel_Error::DIV0()) {
            return '0';
        }
        return (string) $complex->sqrt();
    }
    /**
     * IMLN.
     *
     * Returns the natural logarithm of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMLN(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the natural logarithm
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMLN(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        if ($complex->get_real() == 0.0 && $complex->get_imaginary() == 0.0) {
            return Excel_Error::NAN();
        }
        return (string) $complex->ln();
    }
    /**
     * IMLOG10.
     *
     * Returns the common logarithm (base 10) of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMLOG10(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the common logarithm
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMLOG10(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        if ($complex->get_real() == 0.0 && $complex->get_imaginary() == 0.0) {
            return Excel_Error::NAN();
        }
        return (string) $complex->log10();
    }
    /**
     * IMLOG2.
     *
     * Returns the base-2 logarithm of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMLOG2(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the base-2 logarithm
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMLOG2(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        if ($complex->get_real() == 0.0 && $complex->get_imaginary() == 0.0) {
            return Excel_Error::NAN();
        }
        return (string) $complex->log2();
    }
    /**
     * IMEXP.
     *
     * Returns the exponential of a complex number in x + yi or x + yj text format.
     *
     * Excel Function:
     *        IMEXP(complexNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number for which you want the exponential
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMEXP(array|string $complex_number): array|string
    {
        if (is_array($complex_number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $complex_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        return (string) $complex->exp();
    }
    /**
     * IMPOWER.
     *
     * Returns a complex number in x + yi or x + yj text format raised to a power.
     *
     * Excel Function:
     *        IMPOWER(complexNumber,realNumber)
     *
     * @param array<mixed>|string $complexNumber the complex number you want to raise to a power
     *                      Or can be an array of values
     * @param array<mixed>|float|int|string $realNumber the power to which you want to raise the complex number
     *                      Or can be an array of values
     *
     * @return array<mixed>|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function IMPOWER(array|string $complex_number, array|float|int|string $real_number): array|string
    {
        if (is_array($complex_number) || is_array($real_number)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $complex_number, $real_number);
        }
        try {
            $complex = new Complex_Object($complex_number);
        } catch (Complex_Exception) {
            return Excel_Error::NAN();
        }
        if (!is_numeric($real_number)) {
            return Excel_Error::VALUE();
        }
        return (string) $complex->pow((float) $real_number);
    }
}