<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Erf
{
    use Array_Enabled;
    private const TWO_SQRT_PI = 1.1283791670955126;
    /**
     * ERF.
     *
     * Returns the error function integrated between the lower and upper bound arguments.
     *
     *    Note: In Excel 2007 or earlier, if you input a negative value for the upper or lower bound arguments,
     *            the function would return a #NUM! error. However, in Excel 2010, the function algorithm was
     *            improved, so that it can now calculate the function for both positive and negative ranges.
     *            PhpSpreadsheet follows Excel 2010 behaviour, and accepts negative arguments.
     *
     *    Excel Function:
     *        ERF(lower[,upper])
     *
     * @param mixed $lower Lower bound float for integrating ERF
     *                      Or can be an array of values
     * @param mixed $upper Upper bound float for integrating ERF.
     *                           If omitted, ERF integrates between zero and lower_limit
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function ERF(mixed $lower, mixed $upper = null): array|float|string
    {
        if (is_array($lower) || is_array($upper)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $lower, $upper);
        }
        if (is_numeric($lower)) {
            if ($upper === null) {
                return self::erf_value($lower);
            }
            if (is_numeric($upper)) {
                return self::erf_value($upper) - self::erf_value($lower);
            }
        }
        return Excel_Error::VALUE();
    }
    /**
     * ERFPRECISE.
     *
     * Returns the error function integrated between the lower and upper bound arguments.
     *
     *    Excel Function:
     *        ERF.PRECISE(limit)
     *
     * @param mixed $limit Float bound for integrating ERF, other bound is zero
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function ERFPRECISE(mixed $limit): array|float|string
    {
        if (is_array($limit)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $limit);
        }
        return self::ERF($limit);
    }
    private static function make_float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
    /**
     * Method to calculate the erf value.
     */
    public static function erf_value(float|int|string $value): float
    {
        $value = (float) $value;
        if (abs($value) > 2.2) {
            return 1 - self::make_float(Erf_C::ERFC($value));
        }
        $sum = $term = $value;
        $xsqr = $value * $value;
        $j = 1;
        do {
            $term *= $xsqr / $j;
            $sum -= $term / (2 * $j + 1);
            ++$j;
            $term *= $xsqr / $j;
            $sum += $term / (2 * $j + 1);
            ++$j;
            if ($sum == 0.0) {
                break;
            }
        } while (abs($term / $sum) > Functions::PRECISION);
        return self::TWO_SQRT_PI * $sum;
    }
}