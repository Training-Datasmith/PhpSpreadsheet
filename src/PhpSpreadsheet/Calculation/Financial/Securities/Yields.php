<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Securities;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Helpers;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Yields
{
    /**
     * YIELDDISC.
     *
     * Returns the annual yield of a security that pays interest at maturity.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security's settlement date is the date after the issue date when the security
     *                              is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $price The security's price per $100 face value
     * @param mixed $redemption The security's redemption value per $100 face value
     * @param mixed $basis The type of day count to use.
     *                       0 or omitted    US (NASD) 30/360
     *                       1               Actual/actual
     *                       2               Actual/360
     *                       3               Actual/365
     *                       4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function yield_discounted(mixed $settlement, mixed $maturity, mixed $price, mixed $redemption, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
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
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($settlement)), $basis);
        if (!is_numeric($days_per_year)) {
            return $days_per_year;
        }
        $days_between_settlement_and_maturity = Functions::scalar(Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, $basis));
        if (!is_numeric($days_between_settlement_and_maturity)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_settlement_and_maturity);
        }
        $days_between_settlement_and_maturity *= $days_per_year;
        return ($redemption - $price) / $price * ($days_per_year / $days_between_settlement_and_maturity);
    }
    /**
     * YIELDMAT.
     *
     * Returns the annual yield of a security that pays interest at maturity.
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security's settlement date is the date after the issue date when the security
     *                              is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $issue The security's issue date
     * @param mixed $rate The security's interest rate at date of issue
     * @param mixed $price The security's price per $100 face value
     * @param mixed $basis The type of day count to use.
     *                       0 or omitted    US (NASD) 30/360
     *                       1               Actual/actual
     *                       2               Actual/360
     *                       3               Actual/365
     *                       4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function yield_at_maturity(mixed $settlement, mixed $maturity, mixed $issue, mixed $rate, mixed $price, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $issue = Functions::flatten_single_value($issue);
        $rate = Functions::flatten_single_value($rate);
        $price = Functions::flatten_single_value($price);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Security_Validations::validate_settlement_date($settlement);
            $maturity = Security_Validations::validate_maturity_date($maturity);
            Security_Validations::validate_security_period($settlement, $maturity);
            $issue = Security_Validations::validate_issue_date($issue);
            $rate = Security_Validations::validate_rate($rate);
            $price = Security_Validations::validate_price($price);
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
        return (1 + $days_between_issue_and_maturity / $days_per_year * $rate - ($price / 100 + $days_between_issue_and_settlement / $days_per_year * $rate)) / ($price / 100 + $days_between_issue_and_settlement / $days_per_year * $rate) * ($days_per_year / $days_between_settlement_and_maturity);
    }
}