<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Securities;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Rates
{
    /**
     * DISC.
     *
     * Returns the discount rate for a security.
     *
     * Excel Function:
     *        DISC(settlement,maturity,price,redemption[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $price The security's price per $100 face value
     * @param mixed $redemption The security's redemption value per $100 face value
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function discount(mixed $settlement, mixed $maturity, mixed $price, mixed $redemption, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): float|string
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $price = Functions::flatten_single_value($price);
        $redemption = Functions::flatten_single_value($redemption);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $price = Security_Validations::validate_price($price);
            $redemption = Security_Validations::validate_redemption($redemption);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($price <= 0.0) {
            return Excel_Error::NAN();
        }
        $days_between_settlement_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis));
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_settlement_and_maturity);
        }
        return (1 - $price / $redemption) / $days_between_settlement_and_maturity;
    }
    /**
     * INTRATE.
     *
     * Returns the interest rate for a fully invested security.
     *
     * Excel Function:
     *        INTRATE(settlement,maturity,investment,redemption[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue date when the security
     *                                  is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $investment the amount invested in the security
     * @param mixed $redemption the amount to be received at maturity
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function interest(mixed $settlement, mixed $maturity, mixed $investment, mixed $redemption, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): float|string
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $investment = Functions::flatten_single_value($investment);
        $redemption = Functions::flatten_single_value($redemption);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $investment = Security_Validations::validate_float($investment);
            $redemption = Security_Validations::validate_redemption($redemption);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($investment <= 0) {
            return Excel_Error::NAN();
        }
        $days_between_settlement_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis));
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_settlement_and_maturity);
        }
        return ($redemption / $investment - 1) / $days_between_settlement_and_maturity;
    }
}