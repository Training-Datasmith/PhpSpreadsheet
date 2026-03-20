<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Constant\Periodic;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Cash_Flow_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Interest
{
    private const FINANCIAL_MAX_ITERATIONS = 128;
    private const FINANCIAL_PRECISION = 1.0E-8;
    /**
     * IPMT.
     *
     * Returns the interest payment for a given period for an investment based on periodic, constant payments
     *         and a constant interest rate.
     *
     * Excel Function:
     *        IPMT(rate,per,nper,pv[,fv][,type])
     *
     * @param mixed $interestRate Interest rate per period
     * @param mixed $period Period for which we want to find the interest
     * @param mixed $numberOfPeriods Number of periods
     * @param mixed $presentValue Present Value
     * @param mixed $futureValue Future Value
     * @param mixed $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     */
    public static function payment(mixed $interest_rate, mixed $period, mixed $number_of_periods, mixed $present_value, mixed $future_value = 0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
    {
        $interest_rate = Functions::flatten_single_value($interest_rate);
        $period = Functions::flatten_single_value($period);
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = $future_value === null ? 0.0 : Functions::flatten_single_value($future_value);
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $interest_rate = Cash_Flow_Validations::validate_rate($interest_rate);
            $period = Cash_Flow_Validations::validate_int($period);
            $number_of_periods = Cash_Flow_Validations::validate_int($number_of_periods);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($period <= 0 || $period > $number_of_periods) {
            return Excel_Error::NAN();
        }
        // Calculate
        $interest_and_principal = new Interest_And_Principal($interest_rate, $period, $number_of_periods, $present_value, $future_value, $type);
        return $interest_and_principal->interest();
    }
    /**
     * ISPMT.
     *
     * Returns the interest payment for an investment based on an interest rate and a constant payment schedule.
     *
     * Excel Function:
     *     =ISPMT(interest_rate, period, number_payments, pv)
     *
     * @param mixed $interestRate is the interest rate for the investment
     * @param mixed $period is the period to calculate the interest rate.  It must be between 1 and number_payments.
     * @param mixed $numberOfPeriods is the number of payments for the annuity
     * @param mixed $principleRemaining is the loan amount or present value of the payments
     */
    public static function schedule_payment(mixed $interest_rate, mixed $period, mixed $number_of_periods, mixed $principle_remaining): string|float
    {
        $interest_rate = Functions::flatten_single_value($interest_rate);
        $period = Functions::flatten_single_value($period);
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $principle_remaining = Functions::flatten_single_value($principle_remaining);
        try {
            $interest_rate = Cash_Flow_Validations::validate_rate($interest_rate);
            $period = Cash_Flow_Validations::validate_int($period);
            $number_of_periods = Cash_Flow_Validations::validate_int($number_of_periods);
            $principle_remaining = Cash_Flow_Validations::validate_float($principle_remaining);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($period <= 0 || $period > $number_of_periods) {
            return Excel_Error::NAN();
        }
        // Return value
        $return_value = 0;
        // Calculate
        $principle_payment = $principle_remaining * 1.0 / ($number_of_periods * 1.0);
        for ($i = 0; $i <= $period; ++$i) {
            $return_value = $interest_rate * $principle_remaining * -1;
            $principle_remaining -= $principle_payment;
            // principle needs to be 0 after the last payment, don't let floating point screw it up
            if ($i == $number_of_periods) {
                $return_value = 0.0;
            }
        }
        return $return_value;
    }
    /**
     * RATE.
     *
     * Returns the interest rate per period of an annuity.
     * RATE is calculated by iteration and can have zero or more solutions.
     * If the successive results of RATE do not converge to within 0.0000001 after 20 iterations,
     * RATE returns the #NUM! error value.
     *
     * Excel Function:
     *        RATE(nper,pmt,pv[,fv[,type[,guess]]])
     *
     * @param mixed $numberOfPeriods The total number of payment periods in an annuity
     * @param mixed $payment The payment made each period and cannot change over the life of the annuity.
     *                           Typically, pmt includes principal and interest but no other fees or taxes.
     * @param mixed $presentValue The present value - the total amount that a series of future payments is worth now
     * @param mixed $futureValue The future value, or a cash balance you want to attain after the last payment is made.
     *                               If fv is omitted, it is assumed to be 0 (the future value of a loan,
     *                               for example, is 0).
     * @param mixed $type A number 0 or 1 and indicates when payments are due:
     *                      0 or omitted    At the end of the period.
     *                      1               At the beginning of the period.
     * @param mixed $guess Your guess for what the rate will be.
     *                          If you omit guess, it is assumed to be 10 percent.
     */
    public static function rate(mixed $number_of_periods, mixed $payment, mixed $present_value, mixed $future_value = 0.0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD, mixed $guess = 0.1): string|float
    {
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $payment = Functions::flatten_single_value($payment);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = Functions::flatten_single_value($future_value) ?? 0.0;
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        $guess = Functions::flatten_single_value($guess) ?? 0.1;
        try {
            $number_of_periods = Cash_Flow_Validations::validate_float($number_of_periods);
            $payment = Cash_Flow_Validations::validate_float($payment);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
            $guess = Cash_Flow_Validations::validate_float($guess);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $rate = $guess;
        // rest of code adapted from python/numpy
        $close = false;
        $iter = 0;
        while (!$close && $iter < self::FINANCIAL_MAX_ITERATIONS) {
            $nextdiff = self::rate_next_guess($rate, $number_of_periods, $payment, $present_value, $future_value, $type);
            if (!is_numeric($nextdiff)) {
                break;
            }
            $rate1 = $rate - $nextdiff;
            $close = abs($rate1 - $rate) < self::FINANCIAL_PRECISION;
            ++$iter;
            $rate = $rate1;
        }
        return $close ? $rate : Excel_Error::NAN();
    }
    private static function rate_next_guess(float $rate, float $number_of_periods, float $payment, float $present_value, float $future_value, int $type): string|float
    {
        if ($rate == 0.0) {
            return Excel_Error::NAN();
        }
        $tt1 = ($rate + 1) ** $number_of_periods;
        $tt2 = ($rate + 1) ** ($number_of_periods - 1);
        $numerator = $future_value + $tt1 * $present_value + $payment * ($tt1 - 1) * ($rate * $type + 1) / $rate;
        $denominator = $number_of_periods * $tt2 * $present_value - $payment * ($tt1 - 1) * ($rate * $type + 1) / ($rate * $rate) + $number_of_periods * $payment * $tt2 * ($rate * $type + 1) / $rate + $payment * ($tt1 - 1) * $type / $rate;
        if ($denominator == 0) {
            return Excel_Error::NAN();
        }
        return $numerator / $denominator;
    }
}