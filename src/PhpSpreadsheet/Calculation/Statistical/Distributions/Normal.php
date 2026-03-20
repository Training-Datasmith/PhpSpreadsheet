<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Engineering;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Normal
{
    use Array_Enabled;
    public const SQRT2PI = 2.5066282746310007;
    /**
     * NORMDIST.
     *
     * Returns the normal distribution for the specified mean and standard deviation. This
     * function has a very wide range of applications in statistics, including hypothesis
     * testing.
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
    public static function distribution(mixed $value, mixed $mean, mixed $std_dev, mixed $cumulative): array|string|float
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
        if ($std_dev < 0) {
            return Excel_Error::NAN();
        }
        if ($cumulative) {
            return 0.5 * (1 + Engineering\Erf::erf_value(($value - $mean) / ($std_dev * sqrt(2))));
        }
        return 1 / (self::SQRT2PI * $std_dev) * exp(-(($value - $mean) ** 2 / (2 * ($std_dev * $std_dev))));
    }
    /**
     * NORMINV.
     *
     * Returns the inverse of the normal cumulative distribution for the specified mean and standard deviation.
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
        if ($std_dev < 0) {
            return Excel_Error::NAN();
        }
        return self::inverse_ncdf($probability) * $std_dev + $mean;
    }
    /*
     *                                inverse_ncdf.php
     *                            -------------------
     *    begin                : Friday, January 16, 2004
     *    copyright            : (C) 2004 Michael Nickerson
     *    email                : nickersonm@yahoo.com
     *
     */
    private static function inverse_ncdf(float $p): float
    {
        //    Inverse ncdf approximation by Peter J. Acklam, implementation adapted to
        //    PHP by Michael Nickerson, using Dr. Thomas Ziegler's C implementation as
        //    a guide. http://home.online.no/~pjacklam/notes/invnorm/index.html
        //    I have not checked the accuracy of this implementation. Be aware that PHP
        //    will truncate the coeficcients to 14 digits.
        //    You have permission to use and distribute this function freely for
        //    whatever purpose you want, but please show common courtesy and give credit
        //    where credit is due.
        //    Input parameter is $p - probability - where 0 < p < 1.
        //    Coefficients in rational approximations
        /** @var array<int, float> */
        static $a = [1 => -39.69683028665376, 2 => 220.9460984245205, 3 => -275.9285104469687, 4 => 138.357751867269, 5 => -30.66479806614716, 6 => 2.506628277459239];
        /** @var array<int, float> */
        static $b = [1 => -54.47609879822406, 2 => 161.5858368580409, 3 => -155.6989798598866, 4 => 66.80131188771972, 5 => -13.28068155288572];
        /** @var array<int, float> */
        static $c = [1 => -0.007784894002430293, 2 => -0.3223964580411365, 3 => -2.400758277161838, 4 => -2.549732539343734, 5 => 4.374664141464968, 6 => 2.938163982698783];
        /** @var array<int, float> */
        static $d = [1 => 0.007784695709041462, 2 => 0.3224671290700398, 3 => 2.445134137142996, 4 => 3.754408661907416];
        //    Define lower and upper region break-points.
        $p_low = 0.02425;
        //Use lower region approx. below this
        $p_high = 1 - $p_low;
        //Use upper region approx. above this
        if (0 < $p && $p < $p_low) {
            //    Rational approximation for lower region.
            $q = sqrt(-2 * log($p));
            return ((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) * $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) * $q + $d[4]) * $q + 1);
        }
        if ($p_high < $p && $p < 1) {
            //    Rational approximation for upper region.
            $q = sqrt(-2 * log(1 - $p));
            return -((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) * $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) * $q + $d[4]) * $q + 1);
        }
        //    Rational approximation for central region.
        $q = $p - 0.5;
        $r = $q * $q;
        return ((((($a[1] * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $r + $a[6]) * $q / ((((($b[1] * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + $b[5]) * $r + 1);
    }
}