<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Single
{
    /**
     * FVSCHEDULE.
     *
     * Returns the future value of an initial principal after applying a series of compound interest rates.
     * Use FVSCHEDULE to calculate the future value of an investment with a variable or adjustable rate.
     *
     * Excel Function:
     *        FVSCHEDULE(principal,schedule)
     *
     * @param mixed $principal the present value
     * @param float[] $schedule an array of interest rates to apply
     */
    public static function future_value(mixed $principal, array $schedule): string|float
    {
        $principal = Functions::flatten_single_value($principal);
        $schedule = Functions::flatten_array($schedule);
        try {
            $principal = Cash_Flow_Validations::validate_float($principal);
            foreach ($schedule as $rate) {
                $rate = Cash_Flow_Validations::validate_float($rate);
                $principal *= 1 + $rate;
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $principal;
    }
    /**
     * PDURATION.
     *
     * Calculates the number of periods required for an investment to reach a specified value.
     *
     * @param mixed $rate Interest rate per period
     * @param mixed $presentValue Present Value
     * @param mixed $futureValue Future Value
     *
     * @return float|string Result, or a string containing an error
     */
    public static function periods(mixed $rate, mixed $present_value, mixed $future_value): string|float
    {
        $rate = Functions::flatten_single_value($rate);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = Functions::flatten_single_value($future_value);
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($rate <= 0.0 || $present_value <= 0.0 || $future_value <= 0.0) {
            return Excel_Error::NAN();
        }
        return (log($future_value) - log($present_value)) / log(1 + $rate);
    }
    /**
     * RRI.
     *
     * Calculates the interest rate required for an investment to grow to a specified future value .
     *
     * @param mixed $periods The number of periods over which the investment is made, expect array|float
     * @param mixed $presentValue Present Value, expect array|float
     * @param mixed $futureValue Future Value, expect array|float
     *
     * @return float|string Result, or a string containing an error
     */
    public static function interest_rate(mixed $periods = 0.0, mixed $present_value = 0.0, mixed $future_value = 0.0): string|float
    {
        $periods = Functions::flatten_single_value($periods);
        $present_value = Functions::flatten_single_value($present_value);
        $future_value = Functions::flatten_single_value($future_value);
        try {
            $periods = Cash_Flow_Validations::validate_float($periods);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $future_value = Cash_Flow_Validations::validate_future_value($future_value);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($periods <= 0.0 || $present_value <= 0.0 || $future_value < 0.0) {
            return Excel_Error::NAN();
        }
        return ($future_value / $present_value) ** (1 / $periods) - 1;
    }
}