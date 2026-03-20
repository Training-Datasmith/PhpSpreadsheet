<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Beta
{
    use Array_Enabled;
    private const MAX_ITERATIONS = 256;
    private const LOG_GAMMA_X_MAX_VALUE = 2.55E+305;
    private const XMININ = 2.23E-308;
    /**
     * BETADIST.
     *
     * Returns the beta distribution.
     *
     * @param mixed $value Float value at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $alpha Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $beta Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $rMin as a float
     *                      Or can be an array of values
     * @param mixed $rMax as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function distribution(mixed $value, mixed $alpha, mixed $beta, mixed $r_min = 0.0, mixed $r_max = 1.0): array|string|float
    {
        if (is_array($value) || is_array($alpha) || is_array($beta) || is_array($r_min) || is_array($r_max)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $alpha, $beta, $r_min, $r_max);
        }
        $r_min ??= 0.0;
        $r_max ??= 1.0;
        try {
            $value = Distribution_Validations::validate_float($value);
            $alpha = Distribution_Validations::validate_float($alpha);
            $beta = Distribution_Validations::validate_float($beta);
            $r_max = Distribution_Validations::validate_float($r_max);
            $r_min = Distribution_Validations::validate_float($r_min);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($r_min > $r_max) {
            $tmp = $r_min;
            $r_min = $r_max;
            $r_max = $tmp;
        }
        if ($value < $r_min || $value > $r_max || $alpha <= 0 || $beta <= 0 || $r_min == $r_max) {
            return Excel_Error::NAN();
        }
        $value -= $r_min;
        $value /= $r_max - $r_min;
        return self::incomplete_beta($value, $alpha, $beta);
    }
    /**
     * BETAINV.
     *
     * Returns the inverse of the Beta distribution.
     *
     * @param mixed $probability Float probability at which you want to evaluate the distribution
     *                      Or can be an array of values
     * @param mixed $alpha Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $beta Parameter to the distribution as a float
     *                      Or can be an array of values
     * @param mixed $rMin Minimum value as a float
     *                      Or can be an array of values
     * @param mixed $rMax Maximum value as a float
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function inverse(mixed $probability, mixed $alpha, mixed $beta, mixed $r_min = 0.0, mixed $r_max = 1.0): array|string|float
    {
        if (is_array($probability) || is_array($alpha) || is_array($beta) || is_array($r_min) || is_array($r_max)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $probability, $alpha, $beta, $r_min, $r_max);
        }
        $r_min ??= 0.0;
        $r_max ??= 1.0;
        try {
            $probability = Distribution_Validations::validate_probability($probability);
            $alpha = Distribution_Validations::validate_float($alpha);
            $beta = Distribution_Validations::validate_float($beta);
            $r_max = Distribution_Validations::validate_float($r_max);
            $r_min = Distribution_Validations::validate_float($r_min);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($r_min > $r_max) {
            $tmp = $r_min;
            $r_min = $r_max;
            $r_max = $tmp;
        }
        if ($alpha <= 0 || $beta <= 0 || $r_min == $r_max || $probability <= 0.0) {
            return Excel_Error::NAN();
        }
        return self::calculate_inverse($probability, $alpha, $beta, $r_min, $r_max);
    }
    private static function calculate_inverse(float $probability, float $alpha, float $beta, float $r_min, float $r_max): string|float
    {
        $a = 0;
        $b = 2;
        $guess = ($a + $b) / 2;
        $i = 0;
        while ($b - $a > Functions::PRECISION && ++$i <= self::MAX_ITERATIONS) {
            $guess = ($a + $b) / 2;
            $result = self::distribution($guess, $alpha, $beta);
            if ($result === $probability || $result === 0.0) {
                $b = $a;
            } elseif ($result > $probability) {
                $b = $guess;
            } else {
                $a = $guess;
            }
        }
        if ($i === self::MAX_ITERATIONS) {
            return Excel_Error::NA();
        }
        return round($r_min + $guess * ($r_max - $r_min), 12);
    }
    /**
     * Incomplete beta function.
     *
     * @author Jaco van Kooten
     * @author Paul Meagher
     *
     * The computation is based on formulas from Numerical Recipes, Chapter 6.4 (W.H. Press et al, 1992).
     *
     * @param float $x require 0<=x<=1
     * @param float $p require p>0
     * @param float $q require q>0
     *
     * @return float 0 if x<0, p<=0, q<=0 or p+q>2.55E305 and 1 if x>1 to avoid errors and over/underflow
     */
    public static function incomplete_beta(float $x, float $p, float $q): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }
        if ($p <= 0.0 || $q <= 0.0 || $p + $q > self::LOG_GAMMA_X_MAX_VALUE) {
            return 0.0;
        }
        $beta_gam = exp(-self::log_beta($p, $q) + $p * log($x) + $q * log(1.0 - $x));
        if ($x < ($p + 1.0) / ($p + $q + 2.0)) {
            return $beta_gam * self::beta_fraction($x, $p, $q) / $p;
        }
        return 1.0 - $beta_gam * self::beta_fraction(1 - $x, $q, $p) / $q;
    }
    // Function cache for logBeta function
    private static float $log_beta_cache_p = 0.0;
    private static float $log_beta_cache_q = 0.0;
    private static float $log_beta_cache_result = 0.0;
    /**
     * The natural logarithm of the beta function.
     *
     * @param float $p require p>0
     * @param float $q require q>0
     *
     * @return float 0 if p<=0, q<=0 or p+q>2.55E305 to avoid errors and over/underflow
     *
     * @author Jaco van Kooten
     */
    private static function log_beta(float $p, float $q): float
    {
        if ($p != self::$log_beta_cache_p || $q != self::$log_beta_cache_q) {
            self::$log_beta_cache_p = $p;
            self::$log_beta_cache_q = $q;
            if ($p <= 0.0 || $q <= 0.0 || $p + $q > self::LOG_GAMMA_X_MAX_VALUE) {
                self::$log_beta_cache_result = 0.0;
            } else {
                self::$log_beta_cache_result = Gamma::log_gamma($p) + Gamma::log_gamma($q) - Gamma::log_gamma($p + $q);
            }
        }
        return self::$log_beta_cache_result;
    }
    /**
     * Evaluates of continued fraction part of incomplete beta function.
     * Based on an idea from Numerical Recipes (W.H. Press et al, 1992).
     *
     * @author Jaco van Kooten
     */
    private static function beta_fraction(float $x, float $p, float $q): float
    {
        $c = 1.0;
        $sum_pq = $p + $q;
        $p_plus = $p + 1.0;
        $p_minus = $p - 1.0;
        $h = 1.0 - $sum_pq * $x / $p_plus;
        if (abs($h) < self::XMININ) {
            $h = self::XMININ;
        }
        $h = 1.0 / $h;
        $frac = $h;
        $m = 1;
        $delta = 0.0;
        while ($m <= self::MAX_ITERATIONS && abs($delta - 1.0) > Functions::PRECISION) {
            $m2 = 2 * $m;
            // even index for d
            $d = $m * ($q - $m) * $x / (($p_minus + $m2) * ($p + $m2));
            $h = 1.0 + $d * $h;
            if (abs($h) < self::XMININ) {
                $h = self::XMININ;
            }
            $h = 1.0 / $h;
            $c = 1.0 + $d / $c;
            if (abs($c) < self::XMININ) {
                $c = self::XMININ;
            }
            $frac *= $h * $c;
            // odd index for d
            $d = -($p + $m) * ($sum_pq + $m) * $x / (($p + $m2) * ($p_plus + $m2));
            $h = 1.0 + $d * $h;
            if (abs($h) < self::XMININ) {
                $h = self::XMININ;
            }
            $h = 1.0 / $h;
            $c = 1.0 + $d / $c;
            if (abs($c) < self::XMININ) {
                $c = self::XMININ;
            }
            $delta = $h * $c;
            $frac *= $delta;
            ++$m;
        }
        return $frac;
    }
    /*
    private static function betaValue(float $a, float $b): float
    {
        return (Gamma::gammaValue($a) * Gamma::gammaValue($b)) /
            Gamma::gammaValue($a + $b);
    }
    
    private static function regularizedIncompleteBeta(float $value, float $a, float $b): float
    {
        return self::incompleteBeta($value, $a, $b) / self::betaValue($a, $b);
    }
    */
}