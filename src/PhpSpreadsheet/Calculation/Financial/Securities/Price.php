<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Securities;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Coupons;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Helpers;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Price
{
    /**
     * PRICE.
     *
     * Returns the price per $100 face value of a security that pays periodic interest.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue date when the security
     *                              is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                                The maturity date is the date when the security expires.
     * @param mixed $rate the security's annual coupon rate
     * @param mixed $yield the security's annual yield
     * @param mixed $redemption The number of coupon payments per year.
     *                              For annual payments, frequency = 1;
     *                              for semiannual, frequency = 2;
     *                              for quarterly, frequency = 4.
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function price(mixed $settlement, mixed $maturity, mixed $rate, mixed $yield, mixed $redemption, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $rate = Functions::flatten_single_value($rate);
        $yield = Functions::flatten_single_value($yield);
        $redemption = Functions::flatten_single_value($redemption);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $rate = Security_Validations::validate_rate($rate);
            $yield = Security_Validations::validate_yield($yield);
            $redemption = Security_Validations::validate_redemption($redemption);
            $frequency = Security_Validations::validate_frequency($frequency);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $dsc = (float) Coupons::COUPDAYSNC($settlement, $maturity, $frequency, $basis);
        $e = (float) Coupons::COUPDAYS($settlement, $maturity, $frequency, $basis);
        $n = (int) Coupons::COUPNUM($settlement, $maturity, $frequency, $basis);
        $a = (float) Coupons::COUPDAYBS($settlement, $maturity, $frequency, $basis);
        $base_yf = 1.0 + $yield / $frequency;
        $rfp = 100 * ($rate / $frequency);
        $de = $dsc / $e;
        $result = $redemption / $base_yf ** (--$n + $de);
        for ($k = 0; $k <= $n; ++$k) {
            $result += $rfp / $base_yf ** ($k + $de);
        }
        return $result - $rfp * ($a / $e);
    }
    /**
     * PRICEDISC.
     *
     * Returns the price per $100 face value of a discounted security.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue date when the security
     *                              is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                                The maturity date is the date when the security expires.
     * @param mixed $discount The security's discount rate
     * @param mixed $redemption The security's redemption value per $100 face value
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function price_discounted(mixed $settlement, mixed $maturity, mixed $discount, mixed $redemption, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $discount = Functions::flatten_single_value($discount);
        $redemption = Functions::flatten_single_value($redemption);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $discount = Security_Validations::validate_discount($discount);
            $redemption = Security_Validations::validate_redemption($redemption);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_between_settlement_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis));
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_settlement_and_maturity);
        }
        return $redemption * (1 - $discount * $days_between_settlement_and_maturity);
    }
    /**
     * PRICEMAT.
     *
     * Returns the price per $100 face value of a security that pays interest at maturity.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security's settlement date is the date after the issue date when the
     *                              security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                                The maturity date is the date when the security expires.
     * @param mixed $issue The security's issue date
     * @param mixed $rate The security's interest rate at date of issue
     * @param mixed $yield The security's annual yield
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function price_at_maturity(mixed $settlement, mixed $maturity, mixed $issue, mixed $rate, mixed $yield, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $issue = Functions::flatten_single_value($issue);
        $rate = Functions::flatten_single_value($rate);
        $yield = Functions::flatten_single_value($yield);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $issue = Security_Validations::validate_issue_date($issue);
            $rate = Security_Validations::validate_rate($rate);
            $yield = Security_Validations::validate_yield($yield);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($settlement)), $basis);
        if (!is_numeric($days_per_year)) {
            return $days_per_year;
        }
        $days_between_issue_and_settlement = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($issue, $settlement, $basis));
        if (!is_numeric($days_between_issue_and_settlement)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_issue_and_settlement);
        }
        $days_between_issue_and_settlement *= $days_per_year;
        $days_between_issue_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($issue, $maturity, $basis));
        if (!is_numeric($days_between_issue_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_issue_and_maturity);
        }
        $days_between_issue_and_maturity *= $days_per_year;
        $days_between_settlement_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis));
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_settlement_and_maturity);
        }
        $days_between_settlement_and_maturity *= $days_per_year;
        return (100 + $days_between_issue_and_maturity / $days_per_year * $rate * 100) / (1 + $days_between_settlement_and_maturity / $days_per_year * $yield) - $days_between_issue_and_settlement / $days_per_year * $rate * 100;
    }
    /**
     * RECEIVED.
     *
     * Returns the amount received at maturity for a fully invested Security.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue date when the security
     *                                  is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $investment The amount invested in the security
     * @param mixed $discount The security's discount rate
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function received(mixed $settlement, mixed $maturity, mixed $investment, mixed $discount, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $investment = Functions::flatten_single_value($investment);
        $discount = Functions::flatten_single_value($discount);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $investment = Security_Validations::validate_float($investment);
            $discount = Security_Validations::validate_discount($discount);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($investment <= 0) {
            return Excel_Error::NAN();
        }
        $days_between_settlement_and_maturity = Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis);
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string(Functions::scalar($days_between_settlement_and_maturity));
        }
        return $investment / (1 - $discount * $days_between_settlement_and_maturity);
    }
}