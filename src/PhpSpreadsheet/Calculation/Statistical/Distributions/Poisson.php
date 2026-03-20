<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig;
class Poisson
{
    use Array_Enabled;
    /**
     * POISSON.
     *
     * Returns the Poisson distribution. A common application of the Poisson distribution
     * is predicting the number of events over a specific time, such as the number of
     * cars arriving at a toll plaza in 1 minute.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $mean Mean value as a float
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $mean, mixed $cumulative): array|string|float
    {
        if (is_array($value) || is_array($mean) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $mean, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $mean = Distribution_Validations::validate_float($mean);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value < 0 || $mean < 0) {
            return Excel_Error::NAN();
        }
        if ($cumulative) {
            $summer = 0;
            $floor = floor($value);
            for ($i = 0; $i <= $floor; ++$i) {
                /** @var float $fact */
                $fact = Math_Trig\Factorial::fact($i);
                $summer += $mean ** $i / $fact;
            }
            return exp(-$mean) * $summer;
        }
        /** @var float $fact */
        $fact = Math_Trig\Factorial::fact($value);
        return exp(-$mean) * $mean ** $value / $fact;
    }
}