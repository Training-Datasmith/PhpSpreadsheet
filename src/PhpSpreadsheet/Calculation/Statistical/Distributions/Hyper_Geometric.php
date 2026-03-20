<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Combinations;
class Hyper_Geometric
{
    use Array_Enabled;
    /**
     * HYPGEOMDIST.
     *
     * Returns the hypergeometric distribution. HYPGEOMDIST returns the probability of a given number of
     * sample successes, given the sample size, population successes, and population size.
     *
     * @param mixed $sampleSuccesses Integer number of successes in the sample
     *                      Or can be an array of values
     * @param mixed $sampleNumber Integer size of the sample
     *                      Or can be an array of values
     * @param mixed $populationSuccesses Integer number of successes in the population
     *                      Or can be an array of values
     * @param mixed $populationNumber Integer population size
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $sample_successes, mixed $sample_number, mixed $population_successes, mixed $population_number): array|string|float
    {
        if (is_array($sample_successes) || is_array($sample_number) || is_array($population_successes) || is_array($population_number)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $sample_successes, $sample_number, $population_successes, $population_number);
        }
        try {
            $sample_successes = Distribution_Validations::validate_int($sample_successes);
            $sample_number = Distribution_Validations::validate_int($sample_number);
            $population_successes = Distribution_Validations::validate_int($population_successes);
            $population_number = Distribution_Validations::validate_int($population_number);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($sample_successes < 0 || $sample_successes > $sample_number || $sample_successes > $population_successes) {
            return Excel_Error::NAN();
        }
        if ($sample_number <= 0 || $sample_number > $population_number) {
            return Excel_Error::NAN();
        }
        if ($population_successes <= 0 || $population_successes > $population_number) {
            return Excel_Error::NAN();
        }
        $successes_population_and_sample = (float) Combinations::without_repetition($population_successes, $sample_successes);
        $numbers_population_and_sample = (float) Combinations::without_repetition($population_number, $sample_number);
        $adjusted_population_and_sample = (float) Combinations::without_repetition($population_number - $population_successes, $sample_number - $sample_successes);
        return $successes_population_and_sample * $adjusted_population_and_sample / $numbers_population_and_sample;
    }
}