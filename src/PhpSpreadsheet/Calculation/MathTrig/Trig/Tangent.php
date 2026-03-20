<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Helpers;
class Tangent
{
    use Array_Enabled;
    /**
     * TAN.
     *
     * Returns the result of builtin function tan after validating args.
     *
     * @param mixed $angle Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string tangent
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function tan(mixed $angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::very_small_denominator(sin($angle), cos($angle));
    }
    /**
     * TANH.
     *
     * Returns the result of builtin function sinh after validating args.
     *
     * @param mixed $angle Should be numeric, or can be an array of numbers
     *
     * @return array<mixed>|float|string hyperbolic tangent
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function tanh(mixed $angle): array|string|float
    {
        if (is_array($angle)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $angle);
        }
        try {
            $angle = Helpers::validate_numeric_null_bool($angle);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return tanh($angle);
    }
    /**
     * ATAN.
     *
     * Returns the arctangent of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The arctangent of the number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function atan($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(atan($number));
    }
    /**
     * ATANH.
     *
     * Returns the inverse hyperbolic tangent of a number.
     *
     * @param array<mixed>|float $number Number, or can be an array of numbers
     *
     * @return array<mixed>|float|string The inverse hyperbolic tangent of the number
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function atanh($number): array|string|float
    {
        if (is_array($number)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $number);
        }
        try {
            $number = Helpers::validate_numeric_null_bool($number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return Helpers::number_or_nan(atanh($number));
    }
    /**
     * ATAN2.
     *
     * This function calculates the arc tangent of the two variables x and y. It is similar to
     *        calculating the arc tangent of y ÷ x, except that the signs of both arguments are used
     *        to determine the quadrant of the result.
     * The arctangent is the angle from the x-axis to a line containing the origin (0, 0) and a
     *        point with coordinates (xCoordinate, yCoordinate). The angle is given in radians between
     *        -pi and pi, excluding -pi.
     *
     * Note that the Excel ATAN2() function accepts its arguments in the reverse order to the standard
     *        PHP atan2() function, so we need to reverse them here before calling the PHP atan() function.
     *
     * Excel Function:
     *        ATAN2(xCoordinate,yCoordinate)
     *
     * @param mixed $xCoordinate should be float, the x-coordinate of the point, or can be an array of numbers
     * @param mixed $yCoordinate should be float, the y-coordinate of the point, or can be an array of numbers
     *
     * @return array<mixed>|float|string The inverse tangent of the specified x- and y-coordinates, or a string containing an error
     *         If an array of numbers is passed as one of the arguments, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function atan2(mixed $x_coordinate, mixed $y_coordinate): array|string|float
    {
        if (is_array($x_coordinate) || is_array($y_coordinate)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $x_coordinate, $y_coordinate);
        }
        try {
            $x_coordinate = Helpers::validate_numeric_null_bool($x_coordinate);
            $y_coordinate = Helpers::validate_numeric_null_bool($y_coordinate);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($x_coordinate == 0 && $y_coordinate == 0) {
            return Excel_Error::DIV0();
        }
        return atan2($y_coordinate, $x_coordinate);
    }
}