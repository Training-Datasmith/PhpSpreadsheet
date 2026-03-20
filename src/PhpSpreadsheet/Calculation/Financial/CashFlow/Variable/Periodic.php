<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Variable;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Periodic
{
    public const FINANCIAL_MAX_ITERATIONS = 128;
    public const FINANCIAL_PRECISION = 1.0E-8;
    /**
     * IRR.
     *
     * Returns the internal rate of return for a series of cash flows represented by the numbers in values.
     * These cash flows do not have to be even, as they would be for an annuity. However, the cash flows must occur
     * at regular intervals, such as monthly or annually. The internal rate of return is the interest rate received
     * for an investment consisting of payments (negative values) and income (positive values) that occur at regular
     * periods.
     *
     * Excel Function:
     *        IRR(values[,guess])
     *
     * @param mixed $values An array or a reference to cells that contain numbers for which you want
     *                                    to calculate the internal rate of return.
     *                                Values must contain at least one positive value and one negative value to
     *                                    calculate the internal rate of return.
     * @param mixed $guess A number that you guess is close to the result of IRR
     */
    public static function rate(mixed $values, mixed $guess = 0.1): string|float
    {
        if (!is_array($values)) {
            return Excel_Error::VALUE();
        }
        $values = Functions::flatten_array($values);
        $guess = Functions::flatten_single_value($guess);
        if (!is_numeric($guess)) {
            return Excel_Error::VALUE();
        }
        // create an initial range, with a root somewhere between 0 and guess
        $x1 = 0.0;
        $x2 = $guess;
        $f1 = self::present_value($x1, $values);
        $f2 = self::present_value($x2, $values);
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            if ($f1 * $f2 < 0.0) {
                break;
            }
            if (abs($f1) < abs($f2)) {
                $f1 = self::present_value($x1 += 1.6 * ($x1 - $x2), $values);
            } else {
                $f2 = self::present_value($x2 += 1.6 * ($x2 - $x1), $values);
            }
        }
        if ($f1 * $f2 > 0.0) {
            return Excel_Error::VALUE();
        }
        $f = self::present_value($x1, $values);
        if ($f < 0.0) {
            $rtb = $x1;
            $dx = $x2 - $x1;
        } else {
            $rtb = $x2;
            $dx = $x1 - $x2;
        }
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            $dx *= 0.5;
            $x_mid = $rtb + $dx;
            $f_mid = self::present_value($x_mid, $values);
            if ($f_mid <= 0.0) {
                $rtb = $x_mid;
            }
            if (abs($f_mid) < self::FINANCIAL_PRECISION || abs($dx) < self::FINANCIAL_PRECISION) {
                return $x_mid;
            }
        }
        return Excel_Error::VALUE();
    }
    /**
     * MIRR.
     *
     * Returns the modified internal rate of return for a series of periodic cash flows. MIRR considers both
     *        the cost of the investment and the interest received on reinvestment of cash.
     *
     * Excel Function:
     *        MIRR(values,finance_rate, reinvestment_rate)
     *
     * @param mixed $values An array or a reference to cells that contain a series of payments and
     *                         income occurring at regular intervals.
     *                      Payments are negative value, income is positive values.
     * @param mixed $financeRate The interest rate you pay on the money used in the cash flows
     * @param mixed $reinvestmentRate The interest rate you receive on the cash flows as you reinvest them
     *
     * @return float|string Result, or a string containing an error
     */
    public static function modified_rate(mixed $values, mixed $finance_rate, mixed $reinvestment_rate): string|float
    {
        if (!is_array($values)) {
            return Excel_Error::DIV0();
        }
        $values = Functions::flatten_array($values);
        /** @var float */
        $finance_rate = Functions::flatten_single_value($finance_rate);
        /** @var float */
        $reinvestment_rate = Functions::flatten_single_value($reinvestment_rate);
        $n = count($values);
        $rr = 1.0 + $reinvestment_rate;
        $fr = 1.0 + $finance_rate;
        $npv_pos = $npv_neg = 0.0;
        foreach ($values as $i => $v) {
            /** @var float $v */
            if ($v >= 0) {
                $npv_pos += $v / $rr ** $i;
            } else {
                $npv_neg += $v / $fr ** $i;
            }
        }
        if ($npv_neg === 0.0 || $npv_pos === 0.0) {
            return Excel_Error::DIV0();
        }
        $mirr = (-$npv_pos * $rr ** $n / ($npv_neg * $rr)) ** (1.0 / ($n - 1)) - 1.0;
        return is_finite($mirr) ? $mirr : Excel_Error::NAN();
    }
    /**
     * NPV.
     *
     * Returns the Net Present Value of a cash flow series given a discount rate.
     *
     * @param array<mixed> $args
     */
    public static function present_value(mixed $rate, ...$args): int|float
    {
        $return_value = 0;
        /** @var float */
        $rate = Functions::flatten_single_value($rate);
        $a_args = Functions::flatten_array($args);
        // Calculate
        $count_args = count($a_args);
        for ($i = 1; $i <= $count_args; ++$i) {
            // Is it a numeric value?
            if (is_numeric($a_args[$i - 1])) {
                $return_value += $a_args[$i - 1] / (1 + $rate) ** $i;
            }
        }
        return $return_value;
    }
}