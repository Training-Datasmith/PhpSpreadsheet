<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class F
{
    use Array_Enabled;
    /**
     * F.DIST.
     *
     *    Returns the F probability distribution.
     *    You can use this function to determine whether two data sets have different degrees of diversity.
     *    For example, you can examine the test scores of men and women entering high school, and determine
     *        if the variability in the females is different from that found in the males.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $u The numerator degrees of freedom as an integer
     *                      Or can be an array of values
     * @param mixed $v The denominator degrees of freedom as an integer
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $u, mixed $v, mixed $cumulative): array|string|float
    {
        if (is_array($value) || is_array($u) || is_array($v) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $u, $v, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $u = Distribution_Validations::validate_int($u);
            $v = Distribution_Validations::validate_int($v);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value < 0 || $u < 1 || $v < 1) {
            return Excel_Error::NAN();
        }
        if ($cumulative) {
            $adjusted_value = $u * $value / ($u * $value + $v);
            return Beta::incomplete_beta($adjusted_value, $u / 2, $v / 2);
        }
        return Gamma::gamma_value(($v + $u) / 2) / (Gamma::gamma_value($u / 2) * Gamma::gamma_value($v / 2)) * ($u / $v) ** ($u / 2) * ($value ** (($u - 2) / 2) / (1 + $u / $v * $value) ** (($u + $v) / 2));
    }
}