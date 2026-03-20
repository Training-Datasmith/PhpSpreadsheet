<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Constant;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Cash_Flow_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Periodic
{
    /**
     * FV.
     *
     * Returns the Future Value of a cash flow with constant payments and interest rate (annuities).
     *
     * Excel Function:
     *        FV(rate,nper,pmt[,pv[,type]])
     *
     * @param mixed $rate The interest rate per period
     * @param mixed $numberOfPeriods Total number of payment periods in an annuity as an integer
     * @param mixed $payment The payment made each period: it cannot change over the
     *                            life of the annuity. Typically, pmt contains principal
     *                            and interest but no other fees or taxes.
     * @param mixed $presentValue present Value, or the lump-sum amount that a series of
     *                            future payments is worth right now
     * @param mixed $type A number 0 or 1 and indicates when payments are due:
     *                      0 or omitted    At the end of the period.
     *                      1               At the beginning of the period.
     */
    public static function future_value(mixed $rate, mixed $number_of_periods, mixed $payment = 0.0, mixed $present_value = 0.0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
    {
        $rate = Functions::flatten_single_value($rate);
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $payment = Functions::flatten_single_value($payment) ?? 0.0;
        $present_value = Functions::flatten_single_value($present_value) ?? 0.0;
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $number_of_periods = Cash_Flow_Validations::validate_int($number_of_periods);
            $payment = Cash_Flow_Validations::validate_float($payment);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return self::calculate_future_value($rate, $number_of_periods, $payment, $present_value, $type);
    }
    /**
     * PV.
     *
     * Returns the Present Value of a cash flow with constant payments and interest rate (annuities).
     *
     * @param mixed $rate Interest rate per period
     * @param mixed $numberOfPeriods Number of periods as an integer
     * @param mixed $payment Periodic payment (annuity)
     * @param mixed $futureValue Future Value
     * @param mixed $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     *
     * @return float|string Result, or a string containing an error
     */
    public static function present_value(mixed $rate, mixed $number_of_periods, mixed $payment = 0.0, mixed $future_value = 0.0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
    {
        $rate = Functions::flatten_single_value($rate);
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $payment = Functions::flatten_single_value($payment) ?? 0.0;
        $future_value = Functions::flatten_single_value($future_value) ?? 0.0;
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $number_of_periods = Cash_Flow_Validations::validate_int($number_of_periods);
            $payment = Cash_Flow_Validations::validate_float($payment);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($number_of_periods < 0) {
            return Excel_Error::NAN();
        }
        return self::calculate_present_value($rate, $number_of_periods, $payment, $future_value, $type);
    }
    /**
     * NPER.
     *
     * Returns the number of periods for a cash flow with constant periodic payments (annuities), and interest rate.
     *
     * @param mixed $rate Interest rate per period
     * @param mixed $payment Periodic payment (annuity)
     * @param mixed $presentValue Present Value
     * @param mixed $futureValue Future Value
     * @param mixed $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     *
     * @return float|string Result, or a string containing an error
     */
    public static function periods(mixed $rate, mixed $payment, mixed $present_value, mixed $future_value = 0.0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
    {
        $rate = Functions::flatten_single_value($rate);
        $payment = Functions::flatten_single_value($payment);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = Functions::flatten_single_value($future_value) ?? 0.0;
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $payment = Cash_Flow_Validations::validate_float($payment);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($payment == 0.0) {
            return Excel_Error::NAN();
        }
        return self::calculate_periods($rate, $payment, $present_value, $future_value, $type);
    }
    private static function calculate_future_value(float $rate, int $number_of_periods, float $payment, float $present_value, int $type): float
    {
        if ($rate != 0) {
            return -$present_value * (1 + $rate) ** $number_of_periods - $payment * (1 + $rate * $type) * ((1 + $rate) ** $number_of_periods - 1) / $rate;
        }
        return -$present_value - $payment * $number_of_periods;
    }
    private static function calculate_present_value(float $rate, int $number_of_periods, float $payment, float $future_value, int $type): float
    {
        if ($rate != 0.0) {
            return (-$payment * (1 + $rate * $type) * (((1 + $rate) ** $number_of_periods - 1) / $rate) - $future_value) / (1 + $rate) ** $number_of_periods;
        }
        return -$future_value - $payment * $number_of_periods;
    }
    private static function calculate_periods(float $rate, float $payment, float $present_value, float $future_value, int $type): string|float
    {
        if ($rate != 0.0) {
            if ($present_value == 0.0) {
                return Excel_Error::NAN();
            }
            return log(($payment * (1 + $rate * $type) / $rate - $future_value) / ($present_value + $payment * (1 + $rate * $type) / $rate)) / log(1 + $rate);
        }
        return (-$present_value - $future_value) / $payment;
    }
}