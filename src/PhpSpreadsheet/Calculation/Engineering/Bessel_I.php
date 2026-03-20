<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Bessel_I
{
    use Array_Enabled;
    /**
     * BESSELI.
     *
     *    Returns the modified Bessel function In(x), which is equivalent to the Bessel function evaluated
     *        for purely imaginary arguments
     *
     *    Excel Function:
     *        BESSELI(x,ord)
     *
     * NOTE: The MS Excel implementation of the BESSELI function is still not accurate.
     *       This code provides a more accurate calculation
     *
     * @param mixed $x A float value at which to evaluate the function.
     *                                If x is nonnumeric, BESSELI returns the #VALUE! error value.
     *                      Or can be an array of values
     * @param mixed $ord The integer order of the Bessel function.
     *                                If ord is not an integer, it is truncated.
     *                                If $ord is nonnumeric, BESSELI returns the #VALUE! error value.
     *                                If $ord < 0, BESSELI returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BESSELI(mixed $x, mixed $ord): array|string|float
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
            0 => self::bessel_i0($x),
            1 => self::bessel_i1($x),
            default => self::bessel_i2($x, $ord),
        };
    }
    private static function bessel_i0(float $x): float
    {
        $ax = abs($x);
        if ($ax < 3.75) {
            $y = $x / 3.75;
            $y = $y * $y;
            return 1.0 + $y * (3.5156229 + $y * (3.0899424 + $y * (1.2067492 + $y * (0.2659732 + $y * (0.0360768 + $y * 0.0045813)))));
        }
        $y = 3.75 / $ax;
        return exp($ax) / sqrt($ax) * (0.39894228 + $y * (0.01328592 + $y * (0.00225319 + $y * (-0.00157565 + $y * (0.00916281 + $y * (-0.02057706 + $y * (0.02635537 + $y * (-0.01647633 + $y * 0.00392377))))))));
    }
    private static function bessel_i1(float $x): float
    {
        $ax = abs($x);
        if ($ax < 3.75) {
            $y = $x / 3.75;
            $y = $y * $y;
            $ans = $ax * (0.5 + $y * (0.87890594 + $y * (0.51498869 + $y * (0.15084934 + $y * (0.02658733 + $y * (0.00301532 + $y * 0.00032411))))));
            return $x < 0.0 ? -$ans : $ans;
        }
        $y = 3.75 / $ax;
        $ans = 0.02282967 + $y * (-0.02895312 + $y * (0.01787654 - $y * 0.00420059));
        $ans = 0.39894228 + $y * (-0.03988024 + $y * (-0.00362018 + $y * (0.00163801 + $y * (-0.01031555 + $y * $ans))));
        $ans *= exp($ax) / sqrt($ax);
        return $x < 0.0 ? -$ans : $ans;
    }
    private static function bessel_i2(float $x, int $ord): float
    {
        if ($x === 0.0) {
            return 0.0;
        }
        $tox = 2.0 / abs($x);
        $bip = 0;
        $ans = 0.0;
        $bi = 1.0;
        for ($j = 2 * ($ord + (int) sqrt(40.0 * $ord)); $j > 0; --$j) {
            $bim = $bip + $j * $tox * $bi;
            $bip = $bi;
            $bi = $bim;
            if (abs($bi) > 1000000000000.0) {
                $ans *= 1.0E-12;
                $bi *= 1.0E-12;
                $bip *= 1.0E-12;
            }
            if ($j === $ord) {
                $ans = $bip;
            }
        }
        $ans *= self::bessel_i0($x) / $bi;
        return $x < 0.0 && $ord % 2 === 1 ? -$ans : $ans;
    }
}