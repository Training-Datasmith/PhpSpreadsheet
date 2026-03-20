<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Constant\Periodic;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Cash_Flow_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Cumulative
{
    /**
     * CUMIPMT.
     *
     * Returns the cumulative interest paid on a loan between the start and end periods.
     *
     * Excel Function:
     *        CUMIPMT(rate,nper,pv,start,end[,type])
     *
     * @param mixed $rate The Interest rate
     * @param mixed $periods The total number of payment periods
     * @param mixed $presentValue Present Value
     * @param mixed $start The first period in the calculation.
     *                       Payment periods are numbered beginning with 1.
     * @param mixed $end the last period in the calculation
     * @param mixed $type A number 0 or 1 and indicates when payments are due:
     *                    0 or omitted    At the end of the period.
     *                    1               At the beginning of the period.
     */
    public static function interest(mixed $rate, mixed $periods, mixed $present_value, mixed $start, mixed $end, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float|int
    {
        $rate = Functions::flatten_single_value($rate);
        $periods = Functions::flatten_single_value($periods);
        $present_value = Functions::flatten_single_value($present_value);
        $start = Functions::flatten_single_value($start);
        $end = Functions::flatten_single_value($end);
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $periods = Cash_Flow_Validations::validate_int($periods);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $start = Cash_Flow_Validations::validate_int($start);
            $end = Cash_Flow_Validations::validate_int($end);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($start < 1 || $start > $end) {
            return Excel_Error::NAN();
        }
        // Calculate
        $interest = 0;
        for ($per = $start; $per <= $end; ++$per) {
            $ipmt = Interest::payment($rate, $per, $periods, $present_value, 0, $type);
            if (is_string($ipmt)) {
                return $ipmt;
            }
            $interest += $ipmt;
        }
        return $interest;
    }
    /**
     * CUMPRINC.
     *
     * Returns the cumulative principal paid on a loan between the start and end periods.
     *
     * Excel Function:
     *        CUMPRINC(rate,nper,pv,start,end[,type])
     *
     * @param mixed $rate The Interest rate
     * @param mixed $periods The total number of payment periods as an integer
     * @param mixed $presentValue Present Value
     * @param mixed $start The first period in the calculation.
     *                       Payment periods are numbered beginning with 1.
     * @param mixed $end the last period in the calculation
     * @param mixed $type A number 0 or 1 and indicates when payments are due:
     *                    0 or omitted    At the end of the period.
     *                    1               At the beginning of the period.
     */
    public static function principal(mixed $rate, mixed $periods, mixed $present_value, mixed $start, mixed $end, mixed $type = Financial_Constants::PAYMENT_END_OF_PERIOD): string|float|int
    {
        $rate = Functions::flatten_single_value($rate);
        $periods = Functions::flatten_single_value($periods);
        $present_value = Functions::flatten_single_value($present_value);
        $start = Functions::flatten_single_value($start);
        $end = Functions::flatten_single_value($end);
        $type = Functions::flatten_single_value($type) ?? Financial_Constants::PAYMENT_END_OF_PERIOD;
        try {
            $rate = Cash_Flow_Validations::validate_rate($rate);
            $periods = Cash_Flow_Validations::validate_int($periods);
            $present_value = Cash_Flow_Validations::validate_present_value($present_value);
            $start = Cash_Flow_Validations::validate_int($start);
            $end = Cash_Flow_Validations::validate_int($end);
            $type = Cash_Flow_Validations::validate_period_type($type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Validate parameters
        if ($start < 1 || $start > $end) {
            return Excel_Error::VALUE();
        }
        // Calculate
        $principal = 0;
        for ($per = $start; $per <= $end; ++$per) {
            $ppmt = Payments::interest_payment($rate, $per, $periods, $present_value, 0, $type);
            if (is_string($ppmt)) {
                return $ppmt;
            }
            $principal += $ppmt;
        }
        return $principal;
    }
}