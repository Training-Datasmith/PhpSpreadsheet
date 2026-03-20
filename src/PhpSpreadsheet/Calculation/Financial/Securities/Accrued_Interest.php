<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Securities;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel\Year_Frac;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Accrued_Interest
{
    public const ACCRINT_CALCMODE_ISSUE_TO_SETTLEMENT = true;
    public const ACCRINT_CALCMODE_FIRST_INTEREST_TO_SETTLEMENT = false;
    /**
     * ACCRINT.
     *
     * Returns the accrued interest for a security that pays periodic interest.
     *
     * Excel Function:
     *        ACCRINT(issue,firstinterest,settlement,rate,par,frequency[,basis][,calc_method])
     *
     * @param mixed $issue the security's issue date
     * @param mixed $firstInterest the security's first interest date
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue date
     *                                  when the security is traded to the buyer.
     * @param mixed $rate The security's annual coupon rate
     * @param mixed $parValue The security's par value.
     *                            If you omit par, ACCRINT uses $1,000.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     * @param mixed $calcMethod Unused by PhpSpreadsheet, and apparently by Excel (https://exceljet.net/functions/accrint-function)
     *
     * @return float|string Result, or a string containing an error
     */
    public static function periodic(mixed $issue, mixed $first_interest, mixed $settlement, mixed $rate, mixed $par_value = 1000, mixed $frequency = Financial_Constants::FREQUENCY_ANNUAL, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD, mixed $calc_method = self::ACCRINT_CALCMODE_ISSUE_TO_SETTLEMENT): string|float
    {
        $issue = Functions::flatten_single_value($issue);
        $first_interest = Functions::flatten_single_value($first_interest);
        $settlement = Functions::flatten_single_value($settlement);
        $rate = Functions::flatten_single_value($rate);
        $par_value = $par_value === null ? 1000 : Functions::flatten_single_value($par_value);
        $frequency = Functions::flatten_single_value($frequency) ?? Financial_Constants::FREQUENCY_ANNUAL;
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $issue = Security_Validations::validate_issue_date($issue);
            $settlement = Security_Validations::validate_settlement_date($settlement);
            Security_Validations::validate_security_period($issue, $settlement);
            $rate = Security_Validations::validate_rate($rate);
            $par_value = Security_Validations::validate_par_value($par_value);
            Security_Validations::validate_frequency($frequency);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_between_issue_and_settlement = Functions::scalar(Year_Frac::fraction($issue, $settlement, $basis));
        if (!is_numeric($days_between_issue_and_settlement)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_issue_and_settlement);
        }
        $days_between_first_interest_and_settlement = Functions::scalar(Year_Frac::fraction($first_interest, $settlement, $basis));
        if (!is_numeric($days_between_first_interest_and_settlement)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_first_interest_and_settlement);
        }
        return $par_value * $rate * $days_between_issue_and_settlement;
    }
    /**
     * ACCRINTM.
     *
     * Returns the accrued interest for a security that pays interest at maturity.
     *
     * Excel Function:
     *        ACCRINTM(issue,settlement,rate[,par[,basis]])
     *
     * @param mixed $issue The security's issue date
     * @param mixed $settlement The security's settlement (or maturity) date
     * @param mixed $rate The security's annual coupon rate
     * @param mixed $parValue The security's par value.
     *                            If you omit parValue, ACCRINT uses $1,000.
     * @param mixed $basis The type of day count to use.
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Result, or a string containing an error
     */
    public static function at_maturity(mixed $issue, mixed $settlement, mixed $rate, mixed $par_value = 1000, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $issue = Functions::flatten_single_value($issue);
        $settlement = Functions::flatten_single_value($settlement);
        $rate = Functions::flatten_single_value($rate);
        $par_value = $par_value === null ? 1000 : Functions::flatten_single_value($par_value);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $issue = Security_Validations::validate_issue_date($issue);
            $settlement = Security_Validations::validate_settlement_date($settlement);
            Security_Validations::validate_security_period($issue, $settlement);
            $rate = Security_Validations::validate_rate($rate);
            $par_value = Security_Validations::validate_par_value($par_value);
            $basis = Security_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_between_issue_and_settlement = Functions::scalar(Year_Frac::fraction($issue, $settlement, $basis));
        if (!is_numeric($days_between_issue_and_settlement)) {
            //    return date error
            return String_Helper::convert_to_string($days_between_issue_and_settlement);
        }
        return $par_value * $rate * $days_between_issue_and_settlement;
    }
}