<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Log_Normal
{
    use Array_Enabled;
    /**
     * LOGNORMDIST.
     *
     * Returns the cumulative lognormal distribution of x, where ln(x) is normally distributed
     * with parameters mean and standard_dev.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $mean Mean value as a float
     *                      Or can be an array of values
     * @param mixed $stdDev Standard Deviation as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function cumulative(mixed $value, mixed $mean, mixed $std_dev)
    {
        if (is_array($value) || is_array($mean) || is_array($std_dev)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $mean, $std_dev);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $mean = Distribution_Validations::validate_float($mean);
            $std_dev = Distribution_Validations::validate_float($std_dev);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value <= 0 || $std_dev <= 0) {
            return Excel_Error::NAN();
        }
        return Standard_Normal::cumulative((log($value) - $mean) / $std_dev);
    }
    /**
     * LOGNORM.DIST.
     *
     * Returns the lognormal distribution of x, where ln(x) is normally distributed
     * with parameters mean and standard_dev.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $mean Mean value as a float
     *                      Or can be an array of values
     * @param mixed $stdDev Standard Deviation as a float
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $mean, mixed $std_dev, mixed $cumulative = false)
    {
        if (is_array($value) || is_array($mean) || is_array($std_dev) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $mean, $std_dev, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $mean = Distribution_Validations::validate_float($mean);
            $std_dev = Distribution_Validations::validate_float($std_dev);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value <= 0 || $std_dev <= 0) {
            return Excel_Error::NAN();
        }
        if ($cumulative === true) {
            return Standard_Normal::distribution((log($value) - $mean) / $std_dev, true);
        }
        return 1 / (sqrt(2 * M_PI) * $std_dev * $value) * exp(-((log($value) - $mean) ** 2 / (2 * $std_dev ** 2)));
    }
    /**
     * LOGINV.
     *
     * Returns the inverse of the lognormal cumulative distribution
     *
     * @param mixed $probability Float probability for which we want the value
     *                      Or can be an array of values
     * @param mixed $mean Mean Value as a float
     *                      Or can be an array of values
     * @param mixed $stdDev Standard Deviation as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     *
     * @TODO    Try implementing P J Acklam's refinement algorithm for greater
     *            accuracy if I can get my head round the mathematics
     *            (as described at) http://home.online.no/~pjacklam/notes/invnorm/
     */
    public static function inverse(mixed $probability, mixed $mean, mixed $std_dev): array|string|float
    {
        if (is_array($probability) || is_array($mean) || is_array($std_dev)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $probability, $mean, $std_dev);
        }
        try {
            $probability = Distribution_Validations::validate_probability($probability);
            $mean = Distribution_Validations::validate_float($mean);
            $std_dev = Distribution_Validations::validate_float($std_dev);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($std_dev <= 0) {
            return Excel_Error::NAN();
        }
        /** @var float $inverse */
        $inverse = Standard_Normal::inverse($probability);
        return exp($mean + $std_dev * $inverse);
    }
}