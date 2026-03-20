<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Chi_Squared
{
    use Array_Enabled;
    private const EPS = 2.22E-16;
    /**
     * CHIDIST.
     *
     * Returns the one-tailed probability of the chi-squared distribution.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $degrees Integer degrees of freedom
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution_right_tail(mixed $value, mixed $degrees): array|string|int|float
    {
        if (is_array($value) || is_array($degrees)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $degrees);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $degrees = Distribution_Validations::validate_int($degrees);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($degrees < 1) {
            return Excel_Error::NAN();
        }
        if ($value < 0) {
            if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_GNUMERIC) {
                return 1;
            }
            return Excel_Error::NAN();
        }
        return 1 - Gamma::incomplete_gamma($degrees / 2, $value / 2) / Gamma::gamma_value($degrees / 2);
    }
    /**
     * CHIDIST.
     *
     * Returns the one-tailed probability of the chi-squared distribution.
     *
     * @param mixed $value Float value for which we want the probability
     *                      Or can be an array of values
     * @param mixed $degrees Integer degrees of freedom
     *                      Or can be an array of values
     * @param mixed $cumulative Boolean value indicating if we want the cdf (true) or the pdf (false)
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|int|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution_left_tail(mixed $value, mixed $degrees, mixed $cumulative): array|string|int|float
    {
        if (is_array($value) || is_array($degrees) || is_array($cumulative)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $degrees, $cumulative);
        }
        try {
            $value = Distribution_Validations::validate_float($value);
            $degrees = Distribution_Validations::validate_int($degrees);
            $cumulative = Distribution_Validations::validate_bool($cumulative);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($degrees < 1) {
            return Excel_Error::NAN();
        }
        if ($value < 0) {
            if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_GNUMERIC) {
                return 1;
            }
            return Excel_Error::NAN();
        }
        if ($cumulative === true) {
            $temp = self::distribution_right_tail($value, $degrees);
            return 1 - (is_numeric($temp) ? $temp : 0);
        }
        return $value ** ($degrees / 2 - 1) * exp(-$value / 2) / (2 ** ($degrees / 2) * Gamma::gamma_value($degrees / 2));
    }
    /**
     * CHIINV.
     *
     * Returns the inverse of the right-tailed probability of the chi-squared distribution.
     *
     * @param mixed $probability Float probability at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $degrees Integer degrees of freedom
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function inverse_right_tail(mixed $probability, mixed $degrees): array|string|int|float
    {
        if (is_array($probability) || is_array($degrees)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $probability, $degrees);
        }
        try {
            $probability = Distribution_Validations::validate_probability($probability);
            $degrees = Distribution_Validations::validate_int($degrees);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($degrees < 1) {
            return Excel_Error::NAN();
        }
        $callback = fn(float $value): float => 1 - Gamma::incomplete_gamma($degrees / 2, $value / 2) / Gamma::gamma_value($degrees / 2);
        $newton_raphson = new Newton_Raphson($callback);
        return $newton_raphson->execute($probability);
    }
    /**
     * CHIINV.
     *
     * Returns the inverse of the left-tailed probability of the chi-squared distribution.
     *
     * @param mixed $probability Float probability at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $degrees Integer degrees of freedom
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function inverse_left_tail(mixed $probability, mixed $degrees): array|string|float
    {
        if (is_array($probability) || is_array($degrees)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $probability, $degrees);
        }
        try {
            $probability = Distribution_Validations::validate_probability($probability);
            $degrees = Distribution_Validations::validate_int($degrees);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($degrees < 1) {
            return Excel_Error::NAN();
        }
        return self::inverse_left_tail_calculation($probability, $degrees);
    }
    /**
     * CHITEST.
     *
     * Uses the chi-square test to calculate the probability that the differences between two supplied data sets
     *      (of observed and expected frequencies), are likely to be simply due to sampling error,
     *      or if they are likely to be real.
     *
     * @param float[] $actual an array of observed frequencies
     * @param float[] $expected an array of expected frequencies
     */
    public static function test($actual, $expected): float|string
    {
        $rows = count($actual);
        /** @var float[] */
        $actual = Functions::flatten_array($actual);
        /** @var float[] */
        $expected = Functions::flatten_array($expected);
        $columns = intdiv(count($actual), $rows);
        $count_actuals = count($actual);
        $count_expected = count($expected);
        if ($count_actuals !== $count_expected || $count_actuals === 1) {
            return Excel_Error::NAN();
        }
        $result = 0.0;
        for ($i = 0; $i < $count_actuals; ++$i) {
            if ($expected[$i] == 0.0) {
                return Excel_Error::DIV0();
            }
            if ($expected[$i] < 0.0) {
                return Excel_Error::NAN();
            }
            $result += ($actual[$i] - $expected[$i]) ** 2 / $expected[$i];
        }
        $degrees = self::degrees($rows, $columns);
        /** @var float|string */
        $result = Functions::scalar(self::distribution_right_tail($result, $degrees));
        return $result;
    }
    protected static function degrees(int $rows, int $columns): int
    {
        if ($rows === 1) {
            return $columns - 1;
        }
        if ($columns === 1) {
            return $rows - 1;
        }
        return ($columns - 1) * ($rows - 1);
    }
    private static function inverse_left_tail_calculation(float $probability, int $degrees): float
    {
        // bracket the root
        $min = 0;
        $sd = sqrt(2.0 * $degrees);
        $max = 2 * $sd;
        $s = -1;
        while ($s * self::pchisq($max, $degrees) > $probability * $s) {
            $min = $max;
            $max += 2 * $sd;
        }
        // Find root using bisection
        $chi2 = 0.5 * ($min + $max);
        while ($max - $min > self::EPS * $chi2) {
            if ($s * self::pchisq($chi2, $degrees) > $probability * $s) {
                $min = $chi2;
            } else {
                $max = $chi2;
            }
            $chi2 = 0.5 * ($min + $max);
        }
        return $chi2;
    }
    private static function pchisq(float $chi2, int $degrees): float
    {
        return self::gammp($degrees, 0.5 * $chi2);
    }
    private static function gammp(int $n, float $x): float
    {
        if ($x < 0.5 * $n + 1) {
            return self::gser($n, $x);
        }
        return 1 - self::gcf($n, $x);
    }
    // Return the incomplete gamma function P(n/2,x) evaluated by
    // series representation. Algorithm from numerical recipe.
    // Assume that n is a positive integer and x>0, won't check arguments.
    // Relative error controlled by the eps parameter
    private static function gser(int $n, float $x): float
    {
        /** @var float $gln */
        $gln = Gamma::ln($n / 2);
        $a = 0.5 * $n;
        $ap = $a;
        $sum = 1.0 / $a;
        $del = $sum;
        for ($i = 1; $i < 101; ++$i) {
            ++$ap;
            $del = $del * $x / $ap;
            $sum += $del;
            if ($del < $sum * self::EPS) {
                break;
            }
        }
        return $sum * exp(-$x + $a * log($x) - $gln);
    }
    // Return the incomplete gamma function Q(n/2,x) evaluated by
    // its continued fraction representation. Algorithm from numerical recipe.
    // Assume that n is a postive integer and x>0, won't check arguments.
    // Relative error controlled by the eps parameter
    private static function gcf(int $n, float $x): float
    {
        /** @var float $gln */
        $gln = Gamma::ln($n / 2);
        $a = 0.5 * $n;
        $b = $x + 1 - $a;
        $fpmin = 1.0E-300;
        $c = 1 / $fpmin;
        $d = 1 / $b;
        $h = $d;
        for ($i = 1; $i < 101; ++$i) {
            $an = -$i * ($i - $a);
            $b += 2;
            $d = $an * $d + $b;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = $b + $an / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1 / $d;
            $del = $d * $c;
            $h = $h * $del;
            if (abs($del - 1) < self::EPS) {
                break;
            }
        }
        return $h * exp(-$x + $a * log($x) - $gln);
    }
}