<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Math_Trig\Combinations;
class Binomial
{
    use Array_Enabled;
    /**
     * BINOMDIST.
     *
     * Returns the individual term binomial distribution probability. Use BINOMDIST in problems with
     *        a fixed number of tests or trials, when the outcomes of any trial are only success or failure,
     *        when trials are independent, and when the probability of success is constant throughout the
     *        experiment. For example, BINOMDIST can calculate the probability that two of the next three
     *        babies born are male.
     *
     * @param mixed $value Integer number of successes in trials
     *                      Or can be an array of values
     * @param mixed $trials Integer umber of trials
     *                      Or can be an array of values
     * @param mixed $probability Probability of success on each trial as a float
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $trials, mixed $probability, mixed $cumulative): array|string|float|int
    {
        if (is_array($value) || is_array($trials) || is_array($probability) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $trials, $probability, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_int($value);
            $trials = Distribution_Validations::validate_int($trials);
            $probability = Distribution_Validations::validate_probability($probability);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($value < 0 || $value > $trials) {
            return Excel_Error::NAN();
        }
        if ($cumulative) {
            return self::calculate_cumulative_binomial($value, $trials, $probability);
        }
        /** @var float $comb */
        $comb = Combinations::without_repetition($trials, $value);
        return $comb * $probability ** $value * (1 - $probability) ** ($trials - $value);
    }
    /**
     * BINOM.DIST.RANGE.
     *
     * Returns the Binomial Distribution probability for the number of successes from a specified number
     *     of trials falling into a specified range.
     *
     * @param mixed $trials Integer number of trials
     *                      Or can be an array of values
     * @param mixed $probability Probability of success on each trial as a float
     *                      Or can be an array of values
     * @param mixed $successes The integer number of successes in trials
     *                      Or can be an array of values
     * @param mixed $limit Upper limit for successes in trials as null, or an integer
     *                           If null, then this will indicate the same as the number of Successes
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function range(mixed $trials, mixed $probability, mixed $successes, mixed $limit = null): array|string|float|int
    {
        if (is_array($trials) || is_array($probability) || is_array($successes) || is_array($limit)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $trials, $probability, $successes, $limit);
        }
        $limit ??= $successes;
        try {
            $trials = Distribution_Validations::validate_int($trials);
            $probability = Distribution_Validations::validate_probability($probability);
            $successes = Distribution_Validations::validate_int($successes);
            $limit = Distribution_Validations::validate_int($limit);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($successes < 0 || $successes > $trials) {
            return Excel_Error::NAN();
        }
        if ($limit < 0 || $limit > $trials || $limit < $successes) {
            return Excel_Error::NAN();
        }
        $summer = 0;
        for ($i = $successes; $i <= $limit; ++$i) {
            /** @var float $comb */
            $comb = Combinations::without_repetition($trials, $i);
            $summer += $comb * $probability ** $i * (1 - $probability) ** ($trials - $i);
        }
        return $summer;
    }
    /**
     * NEGBINOMDIST.
     *
     * Returns the negative binomial distribution. NEGBINOMDIST returns the probability that
     *        there will be number_f failures before the number_s-th success, when the constant
     *        probability of a success is probability_s. This function is similar to the binomial
     *        distribution, except that the number of successes is fixed, and the number of trials is
     *        variable. Like the binomial, trials are assumed to be independent.
     *
     * @param mixed $failures Number of Failures as an integer
     *                      Or can be an array of values
     * @param mixed $successes Threshold number of Successes as an integer
     *                      Or can be an array of values
     * @param mixed $probability Probability of success on each trial as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string The result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     *
     * TODO Add support for the cumulative flag not present for NEGBINOMDIST, but introduced for NEGBINOM.DIST
     *      The cumulative default should be false to reflect the behaviour of NEGBINOMDIST
     */
    public static function negative(mixed $failures, mixed $successes, mixed $probability): array|string|float
    {
        if (is_array($failures) || is_array($successes) || is_array($probability)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $failures, $successes, $probability);
        }
        try {
            $failures = Distribution_Validations::validate_int($failures);
            $successes = Distribution_Validations::validate_int($successes);
            $probability = Distribution_Validations::validate_probability($probability);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($failures < 0 || $successes < 1) {
            return Excel_Error::NAN();
        }
        if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_GNUMERIC) {
            if ($failures + $successes - 1 <= 0) {
                return Excel_Error::NAN();
            }
        }
        /** @var float $comb */
        $comb = Combinations::without_repetition($failures + $successes - 1, $successes - 1);
        return $comb * $probability ** $successes * (1 - $probability) ** $failures;
    }
    /**
     * BINOM.INV.
     *
     * Returns the smallest value for which the cumulative binomial distribution is greater
     *        than or equal to a criterion value
     *
     * @param mixed $trials number of Bernoulli trials as an integer
     *                      Or can be an array of values
     * @param mixed $probability probability of a success on each trial as a float
     *                      Or can be an array of values
     * @param mixed $alpha criterion value as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function inverse(mixed $trials, mixed $probability, mixed $alpha): array|string|int
    {
        if (is_array($trials) || is_array($probability) || is_array($alpha)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $trials, $probability, $alpha);
        }
        try {
            $trials = Distribution_Validations::validate_int($trials);
            $probability = Distribution_Validations::validate_probability($probability);
            $alpha = Distribution_Validations::validate_float($alpha);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($trials < 0) {
            return Excel_Error::NAN();
        }
        if ($alpha < 0.0 || $alpha > 1.0) {
            return Excel_Error::NAN();
        }
        $successes = 0;
        while ($successes <= $trials) {
            $result = self::calculate_cumulative_binomial($successes, $trials, $probability);
            if ($result >= $alpha) {
                break;
            }
            ++$successes;
        }
        return $successes;
    }
    private static function calculate_cumulative_binomial(int $value, int $trials, float $probability): float|int
    {
        $summer = 0;
        for ($i = 0; $i <= $value; ++$i) {
            /** @var float $comb */
            $comb = Combinations::without_repetition($trials, $i);
            $summer += $comb * $probability ** $i * (1 - $probability) ** ($trials - $i);
        }
        return $summer;
    }
}