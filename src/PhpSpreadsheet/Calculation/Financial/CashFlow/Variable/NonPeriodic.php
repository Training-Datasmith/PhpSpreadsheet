<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Variable;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Non_Periodic
{
    public const FINANCIAL_MAX_ITERATIONS = 128;
    public const FINANCIAL_PRECISION = 1.0E-8;
    public const DEFAULT_GUESS = 0.1;
    /**
     * XIRR.
     *
     * Returns the internal rate of return for a schedule of cash flows that is not necessarily periodic.
     *
     * Excel Function:
     *        =XIRR(values,dates,guess)
     *
     * @param array<int, float|int|numeric-string> $values     A series of cash flow payments, expecting float[]
     *                                The series of values must contain at least one positive value & one negative value
     * @param array<int, float|int|numeric-string> $dates      A series of payment dates
     *                                The first payment date indicates the beginning of the schedule of payments
     *                                All other dates must be later than this date, but they may occur in any order
     * @param mixed $guess        An optional guess at the expected answer
     */
    public static function rate(mixed $values, $dates, mixed $guess = self::DEFAULT_GUESS): float|string
    {
        $rslt = self::xirr_part1($values, $dates);
        /** @var array<int, float|int|numeric-string> $dates */
        if ($rslt !== '') {
            return $rslt;
        }
        // create an initial range, with a root somewhere between 0 and guess
        $guess = Functions::flatten_single_value($guess) ?? self::DEFAULT_GUESS;
        if (!is_numeric($guess)) {
            return Excel_Error::VALUE();
        }
        $guess = $guess + 0.0 ?: self::DEFAULT_GUESS;
        $x1 = 0.0;
        $x2 = $guess + 0.0;
        $f1 = self::xnpv_ordered($x1, $values, $dates, false);
        $f2 = self::xnpv_ordered($x2, $values, $dates, false);
        $found = false;
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            if (!is_numeric($f1)) {
                return $f1;
            }
            if (!is_numeric($f2)) {
                return $f2;
            }
            $f1 = (float) $f1;
            $f2 = (float) $f2;
            if ($f1 * $f2 < 0.0) {
                $found = true;
                break;
            } elseif (abs($f1) < abs($f2)) {
                $x1 += 1.6 * ($x1 - $x2);
                $f1 = self::xnpv_ordered($x1, $values, $dates, false);
            } else {
                $x2 += 1.6 * ($x2 - $x1);
                $f2 = self::xnpv_ordered($x2, $values, $dates, false);
            }
        }
        if ($found) {
            return self::xirr_part3($values, $dates, $x1, $x2);
        }
        // Newton-Raphson didn't work - try bisection
        $x1 = $guess - 0.5;
        $x2 = $guess + 0.5;
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            $f1 = self::xnpv_ordered($x1, $values, $dates, false, true);
            $f2 = self::xnpv_ordered($x2, $values, $dates, false, true);
            if (!is_numeric($f1) || !is_numeric($f2)) {
                break;
            }
            if ($f1 * $f2 <= 0) {
                $found = true;
                break;
            }
            $x1 -= 0.5;
            $x2 += 0.5;
        }
        if ($found) {
            /** @var array<int, float|int|numeric-string> $dates */
            return self::xirr_bisection($values, $dates, $x1, $x2);
        }
        return Excel_Error::NAN();
    }
    /**
     * XNPV.
     *
     * Returns the net present value for a schedule of cash flows that is not necessarily periodic.
     * To calculate the net present value for a series of cash flows that is periodic, use the NPV function.
     *
     * Excel Function:
     *        =XNPV(rate,values,dates)
     *
     * @param mixed $rate the discount rate to apply to the cash flows, expect array|float
     * @param array<int,float|int|numeric-string> $values A series of cash flows that corresponds to a schedule of payments in dates, expecting float[].
     *                          The first payment is optional and corresponds to a cost or payment that occurs
     *                              at the beginning of the investment.
     *                          If the first value is a cost or payment, it must be a negative value.
     *                             All succeeding payments are discounted based on a 365-day year.
     *                          The series of values must contain at least one positive value and one negative value.
     * @param mixed $dates A schedule of payment dates that corresponds to the cash flow payments, expecting mixed[].
     *                         The first payment date indicates the beginning of the schedule of payments.
     *                         All other dates must be later than this date, but they may occur in any order.
     */
    public static function present_value(mixed $rate, mixed $values, mixed $dates): float|string
    {
        return self::xnpv_ordered($rate, $values, $dates, true);
    }
    private static function both_neg_and_pos(bool $neg, bool $pos): bool
    {
        return $neg && $pos;
    }
    /** @param array<int, float|int|numeric-string> $values */
    private static function xirr_part1(mixed &$values, mixed &$dates): string
    {
        /** @var array<int, float|int|numeric-string> */
        $temp = Functions::flatten_array($values);
        $values = $temp;
        $dates = Functions::flatten_array($dates);
        $values_is_array = count($values) > 1;
        $dates_is_array = count($dates) > 1;
        if (!$values_is_array && !$dates_is_array) {
            return Excel_Error::NA();
        }
        if (count($values) != count($dates)) {
            return Excel_Error::NAN();
        }
        $dates_count = count($dates);
        for ($i = 0; $i < $dates_count; ++$i) {
            try {
                $dates[$i] = Date_Time_Excel\Helpers::get_date_value($dates[$i]);
            } catch (Exception $e) {
                return $e->get_message();
            }
        }
        return self::xirr_part2($values);
    }
    /** @param array<int, float|int|numeric-string> $values */
    private static function xirr_part2(array &$values): string
    {
        $val_count = count($values);
        $foundpos = false;
        $foundneg = false;
        for ($i = 0; $i < $val_count; ++$i) {
            $fld = $values[$i];
            if (!is_numeric($fld)) {
                //* @phpstan-ignore-line
                return Excel_Error::VALUE();
            }
            if ($fld > 0) {
                $foundpos = true;
            } elseif ($fld < 0) {
                $foundneg = true;
            }
        }
        if (!self::both_neg_and_pos($foundneg, $foundpos)) {
            return Excel_Error::NAN();
        }
        return '';
    }
    /**
     * @param array<int, float|int|numeric-string> $values
     * @param array<int, float|int|numeric-string> $dates
     */
    private static function xirr_part3(array $values, array $dates, float $x1, float $x2): float|string
    {
        $f = self::xnpv_ordered($x1, $values, $dates, false);
        if ($f < 0.0) {
            $rtb = $x1;
            $dx = $x2 - $x1;
        } else {
            $rtb = $x2;
            $dx = $x1 - $x2;
        }
        $rslt = Excel_Error::VALUE();
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            $dx *= 0.5;
            $x_mid = $rtb + $dx;
            $f_mid = (float) self::xnpv_ordered($x_mid, $values, $dates, false);
            if ($f_mid <= 0.0) {
                $rtb = $x_mid;
            }
            if (abs($f_mid) < self::FINANCIAL_PRECISION || abs($dx) < self::FINANCIAL_PRECISION) {
                $rslt = $x_mid;
                break;
            }
        }
        return $rslt;
    }
    /**
     * @param array<int, float|int|numeric-string> $values
     * @param array<int, float|int|numeric-string> $dates
     */
    private static function xirr_bisection(array $values, array $dates, float $x1, float $x2): string|float
    {
        $rslt = Excel_Error::NAN();
        for ($i = 0; $i < self::FINANCIAL_MAX_ITERATIONS; ++$i) {
            $rslt = Excel_Error::NAN();
            $f1 = self::xnpv_ordered($x1, $values, $dates, false, true);
            $f2 = self::xnpv_ordered($x2, $values, $dates, false, true);
            if (!is_numeric($f1) || !is_numeric($f2)) {
                break;
            }
            $f1 = (float) $f1;
            $f2 = (float) $f2;
            if (abs($f1) < self::FINANCIAL_PRECISION && abs($f2) < self::FINANCIAL_PRECISION) {
                break;
            }
            if ($f1 * $f2 > 0) {
                break;
            }
            $rslt = ($x1 + $x2) / 2;
            $f3 = self::xnpv_ordered($rslt, $values, $dates, false, true);
            if (!is_float($f3)) {
                break;
            }
            if ($f3 * $f1 < 0) {
                $x2 = $rslt;
            } else {
                $x1 = $rslt;
            }
            if (abs($f3) < self::FINANCIAL_PRECISION) {
                break;
            }
        }
        return $rslt;
    }
    /** @param array<int,float|int|numeric-string> $values> */
    private static function xnpv_ordered(mixed $rate, mixed $values, mixed $dates, bool $ordered = true, bool $cap_at_negative1 = false): float|string
    {
        $rate = Functions::flatten_single_value($rate);
        if (!is_numeric($rate)) {
            return Excel_Error::VALUE();
        }
        $values = Functions::flatten_array($values);
        $dates = Functions::flatten_array($dates);
        $val_count = count($values);
        try {
            self::validate_xnpv($rate, $values, $dates);
            if ($cap_at_negative1 && $rate <= -1) {
                $rate = -1.0 + 1.0E-10;
            }
            $date0 = Date_Time_Excel\Helpers::get_date_value($dates[0]);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $xnpv = 0.0;
        for ($i = 0; $i < $val_count; ++$i) {
            if (!is_numeric($values[$i])) {
                return Excel_Error::VALUE();
            }
            try {
                $datei = Date_Time_Excel\Helpers::get_date_value($dates[$i]);
            } catch (Exception $e) {
                return $e->get_message();
            }
            if ($date0 > $datei) {
                $dif = $ordered ? Excel_Error::NAN() : -(int) Date_Time_Excel\Difference::interval($datei, $date0, 'd');
            } else {
                $dif = Functions::scalar(Date_Time_Excel\Difference::interval($date0, $datei, 'd'));
            }
            if (!is_numeric($dif)) {
                return String_Helper::convert_to_string($dif);
            }
            if ($rate <= -1.0) {
                $xnpv += -abs($values[$i] + 0) / (-1 - $rate) ** ($dif / 365);
            } else {
                $xnpv += $values[$i] / (1 + $rate) ** ($dif / 365);
            }
        }
        return is_finite($xnpv) ? $xnpv : Excel_Error::VALUE();
    }
    /**
     * @param mixed[] $values
     * @param mixed[] $dates
     */
    private static function validate_xnpv(mixed $rate, array $values, array $dates): void
    {
        if (!is_numeric($rate)) {
            throw new Exception(Excel_Error::VALUE());
        }
        $val_count = count($values);
        if ($val_count != count($dates)) {
            throw new Exception(Excel_Error::NAN());
        }
        if (count($values) > 1 && (min($values) > 0 || max($values) < 0)) {
            throw new Exception(Excel_Error::NAN());
        }
    }
}