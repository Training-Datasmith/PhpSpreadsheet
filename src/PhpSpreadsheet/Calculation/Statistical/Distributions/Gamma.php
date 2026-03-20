<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Gamma extends Gamma_Base
{
    use Array_Enabled;
    /**
     * GAMMA.
     *
     * Return the gamma function value.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function gamma(mixed $value): array|string|float
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ((int) $value == $value && $value <= 0.0) {
            return Excel_Error::NAN();
        }
        return self::gamma_value($value);
    }
    /**
     * GAMMADIST.
     *
     * Returns the gamma distribution.
     *
     * @param mixed $value Float Value at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $a Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $b Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $a, mixed $b, mixed $cumulative): array|string|float
    {
        if (is_array($value) || is_array($a) || is_array($b) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $a, $b, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $a = Distribution_Validations::validate_float($a);
            $b = Distribution_Validations::validate_float($b);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value < 0 || $a <= 0 || $b <= 0) {
            return Excel_Error::NAN();
        }
        return self::calculate_distribution($value, $a, $b, $cumulative);
    }
    /**
     * GAMMAINV.
     *
     * Returns the inverse of the Gamma distribution.
     *
     * @param mixed $probability Float probability at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $alpha Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $beta Parameter to the distribution as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function inverse(mixed $probability, mixed $alpha, mixed $beta)
    {
        if (is_array($probability) || is_array($alpha) || is_array($beta)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $probability, $alpha, $beta);
        }
        try {
            $probability = Distribution_Validations::validate_probability($probability);
            $alpha = Distribution_Validations::validate_float($alpha);
            $beta = Distribution_Validations::validate_float($beta);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($alpha <= 0.0 || $beta <= 0.0) {
            return Excel_Error::NAN();
        }
        return self::calculate_inverse($probability, $alpha, $beta);
    }
    /**
     * GAMMALN.
     *
     * Returns the natural logarithm of the gamma function.
     *
     * @param mixed $value Float Value at which you want to evaluate the distribution
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function ln(mixed $value): array|string|float
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value <= 0) {
            return Excel_Error::NAN();
        }
        return log(self::gamma_value($value));
    }
}