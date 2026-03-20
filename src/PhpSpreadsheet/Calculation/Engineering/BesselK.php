<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Bessel_K
{
    use Array_Enabled;
    /**
     * BESSELK.
     *
     *    Returns the modified Bessel function Kn(x), which is equivalent to the Bessel functions evaluated
     *        for purely imaginary arguments.
     *
     *    Excel Function:
     *        BESSELK(x,ord)
     *
     * @param mixed $x A float value at which to evaluate the function.
     *                                If x is nonnumeric, BESSELK returns the #VALUE! error value.
     *                      Or can be an array of values
     * @param mixed $ord The integer order of the Bessel function.
     *                       If ord is not an integer, it is truncated.
     *                                If $ord is nonnumeric, BESSELK returns the #VALUE! error value.
     *                       If $ord < 0, BESSELKI returns the #NUM! error value.
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Result, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function BESSELK(mixed $x, mixed $ord): array|string|float
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
        if ($ord < 0 || $x <= 0.0) {
            return Excel_Error::NAN();
        }
        $f_bk = self::calculate($x, $ord);
        return is_nan($f_bk) ? Excel_Error::NAN() : $f_bk;
    }
    private static function calculate(float $x, int $ord): float
    {
        return match ($ord) {
            0 => self::bessel_k0($x),
            1 => self::bessel_k1($x),
            default => self::bessel_k2($x, $ord),
        };
    }
    /**
     * Mollify Phpstan.
     *
     * @codeCoverageIgnore
     */
    private static function call_bessel_i(float $x, int $ord): float
    {
        $rslt = Bessel_I::BESSELI($x, $ord);
        if (!is_float($rslt)) {
            throw new Exception('Unexpected array or string');
        }
        return $rslt;
    }
    private static function bessel_k0(float $x): float
    {
        if ($x <= 2) {
            $f_num2 = $x * 0.5;
            $y = $f_num2 * $f_num2;
            return -log($f_num2) * self::call_bessel_i($x, 0) + (-0.57721566 + $y * (0.4227842 + $y * (0.23069756 + $y * (0.0348859 + $y * (0.00262698 + $y * (0.0001075 + $y * 7.4E-6))))));
        }
        $y = 2 / $x;
        return exp(-$x) / sqrt($x) * (1.25331414 + $y * (-0.07832358 + $y * (0.02189568 + $y * (-0.01062446 + $y * (0.00587872 + $y * (-0.0025154 + $y * 0.00053208))))));
    }
    private static function bessel_k1(float $x): float
    {
        if ($x <= 2) {
            $f_num2 = $x * 0.5;
            $y = $f_num2 * $f_num2;
            return log($f_num2) * self::call_bessel_i($x, 1) + (1 + $y * (0.15443144 + $y * (-0.6727857900000001 + $y * (-0.18156897 + $y * (-0.01919402 + $y * (-0.00110404 + $y * -4.686E-5)))))) / $x;
        }
        $y = 2 / $x;
        return exp(-$x) / sqrt($x) * (1.25331414 + $y * (0.23498619 + $y * (-0.0365562 + $y * (0.01504268 + $y * (-0.00780353 + $y * (0.00325614 + $y * -0.00068245))))));
    }
    private static function bessel_k2(float $x, int $ord): float
    {
        $f_tox = 2 / $x;
        $f_bkm = self::bessel_k0($x);
        $f_bk = self::bessel_k1($x);
        for ($n = 1; $n < $ord; ++$n) {
            $f_bkp = $f_bkm + $n * $f_tox * $f_bk;
            $f_bkm = $f_bk;
            $f_bk = $f_bkp;
        }
        return $f_bk;
    }
}