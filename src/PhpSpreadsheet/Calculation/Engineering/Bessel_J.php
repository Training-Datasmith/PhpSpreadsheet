<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Bessel_J
{
    use Array_Enabled;
    /**
     * BESSELJ.
     *
     *    Returns the Bessel function
     *
     *    Excel Function:
     *        BESSELJ(x,ord)
     *
     * NOTE: The MS Excel implementation of the BESSELJ function is still not accurate, particularly for higher order
     *       values with x < -8 and x > 8. This code provides a more accurate calculation
     *
     * @param mixed $x A float value at which to evaluate the function.
     *                                If x is nonnumeric, BESSELJ returns the #VALUE! error value.
     *                      Or can be an array of values
     * @param mixed $ord The integer order of the Bessel function.
     *                       If ord is not an integer, it is truncated.
     *                                If $ord is nonnumeric, BESSELJ returns the #VALUE! error value.
     *                                If $ord < 0, BESSELJ returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BESSELJ(mixed $x, mixed $ord): array|string|float
    {
        if (is_array($x) || is_array($ord)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $x, $ord);
        }
        try {
            $x = Engineering_Validations::validate_float($x);
            $ord = Engineering_Validations::validate_int($ord);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($ord < 0) {
            return Excel_Error::NAN();
        }
        $f_result = self::calculate($x, $ord);
        return is_nan($f_result) ? Excel_Error::NAN() : $f_result;
    }
    private static function calculate(float $x, int $ord): float
    {
        return match ($ord) {
            0 => self::bessel_j0($x),
            1 => self::bessel_j1($x),
            default => self::bessel_j2($x, $ord),
        };
    }
    private static function bessel_j0(float $x): float
    {
        $ax = abs($x);
        if ($ax < 8.0) {
            $y = $x * $x;
            $ans1 = 57568490574.0 + $y * (-13362590354.0 + $y * (651619640.7 + $y * (-11214424.18 + $y * (77392.33017 + $y * -184.9052456))));
            $ans2 = 57568490411.0 + $y * (1029532985.0 + $y * (9494680.718 + $y * (59272.64853 + $y * (267.8532712 + $y * 1.0))));
            return $ans1 / $ans2;
        }
        $z = 8.0 / $ax;
        $y = $z * $z;
        $xx = $ax - 0.785398164;
        $ans1 = 1.0 + $y * (-0.001098628627 + $y * (2.734510407E-5 + $y * (-2.073370639E-6 + $y * 2.093887211E-7)));
        $ans2 = -0.01562499995 + $y * (0.0001430488765 + $y * (-6.911147651E-6 + $y * (7.621095161000001E-7 - $y * 9.349351519999999E-8)));
        return sqrt(0.636619772 / $ax) * (cos($xx) * $ans1 - $z * sin($xx) * $ans2);
    }
    private static function bessel_j1(float $x): float
    {
        $ax = abs($x);
        if ($ax < 8.0) {
            $y = $x * $x;
            $ans1 = $x * (72362614232.0 + $y * (-7895059235.0 + $y * (242396853.1 + $y * (-2972611.439 + $y * (15704.4826 + $y * -30.16036606)))));
            $ans2 = 144725228442.0 + $y * (2300535178.0 + $y * (18583304.74 + $y * (99447.43394 + $y * (376.9991397 + $y * 1.0))));
            return $ans1 / $ans2;
        }
        $z = 8.0 / $ax;
        $y = $z * $z;
        $xx = $ax - 2.356194491;
        $ans1 = 1.0 + $y * (0.00183105 + $y * (-3.516396496E-5 + $y * (2.457520174E-6 + $y * -2.40337019E-7)));
        $ans2 = 0.04687499995 + $y * (-0.0002002690873 + $y * (8.449199096E-6 + $y * (-8.8228987E-7 + $y * 1.05787412E-7)));
        $ans = sqrt(0.636619772 / $ax) * (cos($xx) * $ans1 - $z * sin($xx) * $ans2);
        return $x < 0.0 ? -$ans : $ans;
    }
    private static function bessel_j2(float $x, int $ord): float
    {
        $ax = abs($x);
        if ($ax === 0.0) {
            return 0.0;
        }
        if ($ax > $ord) {
            return self::besselj2a($ax, $ord, $x);
        }
        return self::besselj2b($ax, $ord, $x);
    }
    private static function besselj2a(float $ax, int $ord, float $x): float
    {
        $tox = 2.0 / $ax;
        $bjm = self::bessel_j0($ax);
        $bj = self::bessel_j1($ax);
        for ($j = 1; $j < $ord; ++$j) {
            $bjp = $j * $tox * $bj - $bjm;
            $bjm = $bj;
            $bj = $bjp;
        }
        $ans = $bj;
        return $x < 0.0 && $ord % 2 == 1 ? -$ans : $ans;
    }
    private static function besselj2b(float $ax, int $ord, float $x): float
    {
        $tox = 2.0 / $ax;
        $jsum = false;
        $bjp = $ans = $sum = 0.0;
        $bj = 1.0;
        for ($j = 2 * ($ord + (int) sqrt(40.0 * $ord)); $j > 0; --$j) {
            $bjm = $j * $tox * $bj - $bjp;
            $bjp = $bj;
            $bj = $bjm;
            if (abs($bj) > 10000000000.0) {
                $bj *= 1.0E-10;
                $bjp *= 1.0E-10;
                $ans *= 1.0E-10;
                $sum *= 1.0E-10;
            }
            if ($jsum === true) {
                $sum += $bj;
            }
            $jsum = $jsum === false;
            if ($j === $ord) {
                $ans = $bjp;
            }
        }
        $sum = 2.0 * $sum - $bj;
        $ans /= $sum;
        return $x < 0.0 && $ord % 2 === 1 ? -$ans : $ans;
    }
}