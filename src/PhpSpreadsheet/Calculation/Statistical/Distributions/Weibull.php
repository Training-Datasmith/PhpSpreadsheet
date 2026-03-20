<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Weibull
{
    use Array_Enabled;
    /**
     * WEIBULL.
     *
     * Returns the Weibull distribution. Use this distribution in reliability
     * analysis, such as calculating a device's mean time to failure.
     *
     * @param mixed $value Float value for the distribution
     *                      Or can be an array of values
     * @param mixed $alpha Float alpha Parameter
     *                      Or can be an array of values
     * @param mixed $beta Float beta Parameter
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string (string if result is an error)
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $alpha, mixed $beta, mixed $cumulative): array|string|float
    {
        if (is_array($value) || is_array($alpha) || is_array($beta) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $alpha, $beta, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $alpha = Distribution_Validations::validate_float($alpha);
            $beta = Distribution_Validations::validate_float($beta);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value < 0 || $alpha <= 0 || $beta <= 0) {
            return Excel_Error::NAN();
        }
        if ($cumulative) {
            return 1 - exp(-($value / $beta) ** $alpha);
        }
        return $alpha / $beta ** $alpha * $value ** ($alpha - 1) * exp(-($value / $beta) ** $alpha);
    }
}