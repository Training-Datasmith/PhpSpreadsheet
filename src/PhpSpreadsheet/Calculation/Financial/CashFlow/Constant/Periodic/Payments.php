<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Constant\Periodic;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Cash_Flow_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Payments
{
    /**
     * PMT.
     *
     * Returns the constant payment (annuity) for a cash flow with a constant interest rate.
     *
     * @param mixed $interestRate Interest rate per period
     * @param mixed $numberOfPeriods Number of periods
     * @param mixed $presentValue Present Value
     * @param mixed $futureValue Future Value
     * @param mixed $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     *
     * @return float|string Result, or a string containing an error
     */
    public static function annuity(mixed $interest_rate, mixed $number_of_periods, mixed $present_value, mixed $future_value = 0.0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
    {
        $interest_rate = Functions::flatten_single_value($interest_rate);
        $number_of_periods = Functions::flatten_single_value($number_of_periods);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = Functions::flatten_single_value($future_value) ?? 0.0;
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $interest_rate = Cash_Flow_Validations::validate_rate($interest_rate);
            $number_of_periods = Cash_Flow_Validations::validate_int($number_of_periods);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Calculate
        if ($interest_rate != 0.0) {
            return (-$future_value - $present_value * (1 + $interest_rate) ** $number_of_periods) / (1 + $interest_rate * $type) / (((1 + $interest_rate) ** $number_of_periods - 1) / $interest_rate);
        }
        return (-$present_value - $future_value) / $number_of_periods;
    }
    /**
     * PPMT.
     *
     * Returns the interest payment for a given period for an investment based on periodic, constant payments
     *         and a constant interest rate.
     *
     * @param mixed $interestRate Interest rate per period
     * @param mixed $period Period for which we want to find the interest
     * @param mixed $numberOfPeriods Number of periods
     * @param mixed $presentValue Present Value
     * @param mixed $futureValue Future Value
     * @param mixed $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     *
     * @return float|string Result, or a string containing an error
     */
    public static function interest_payment(mixed $interest_rate, mixed $period, mixed $number_of_periods, mixed $present_value, mixed $future_value = 0, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float
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
        return $interest_and_principal->principal();
    }
}