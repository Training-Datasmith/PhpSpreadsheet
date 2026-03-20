<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Treasury_Bill
{
    /**
     * TBILLEQ.
     *
     * Returns the bond-equivalent yield for a Treasury bill.
     *
     * @param mixed $settlement The Treasury bill's settlement date.
     *                                The Treasury bill's settlement date is the date after the issue date
     *                                    when the Treasury bill is traded to the buyer.
     * @param mixed $maturity The Treasury bill's maturity date.
     *                                The maturity date is the date when the Treasury bill expires.
     * @param mixed $discount The Treasury bill's discount rate
     *
     * @return float|string Result, or a string containing an error
     */
    public static function bond_equivalent_yield(mixed $settlement, mixed $maturity, mixed $discount): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $discount = Functions::flatten_single_value($discount);
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            $discount = Financial_Validations::validate_float($discount);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($discount <= 0) {
            return Excel_Error::NAN();
        }
        $days_between_settlement_and_maturity = $maturity - $settlement;
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($maturity)), Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL);
        if ($days_between_settlement_and_maturity > $days_per_year || $days_between_settlement_and_maturity < 0) {
            return Excel_Error::NAN();
        }
        return 365 * $discount / (360 - $discount * $days_between_settlement_and_maturity);
    }
    /**
     * TBILLPRICE.
     *
     * Returns the price per $100 face value for a Treasury bill.
     *
     * @param mixed $settlement The Treasury bill's settlement date.
     *                                The Treasury bill's settlement date is the date after the issue date
     *                                    when the Treasury bill is traded to the buyer.
     * @param mixed $maturity The Treasury bill's maturity date.
     *                                The maturity date is the date when the Treasury bill expires.
     * @param mixed $discount The Treasury bill's discount rate
     *
     * @return float|string Result, or a string containing an error
     */
    public static function price(mixed $settlement, mixed $maturity, mixed $discount): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $discount = Functions::flatten_single_value($discount);
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            $discount = Financial_Validations::validate_float($discount);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($discount <= 0) {
            return Excel_Error::NAN();
        }
        $days_between_settlement_and_maturity = $maturity - $settlement;
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($maturity)), Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL);
        if ($days_between_settlement_and_maturity > $days_per_year || $days_between_settlement_and_maturity < 0) {
            return Excel_Error::NAN();
        }
        $price = 100 * (1 - $discount * $days_between_settlement_and_maturity / 360);
        if ($price < 0.0) {
            return Excel_Error::NAN();
        }
        return $price;
    }
    /**
     * TBILLYIELD.
     *
     * Returns the yield for a Treasury bill.
     *
     * @param mixed $settlement The Treasury bill's settlement date.
     *                                The Treasury bill's settlement date is the date after the issue date when
     *                                    the Treasury bill is traded to the buyer.
     * @param mixed $maturity The Treasury bill's maturity date.
     *                                The maturity date is the date when the Treasury bill expires.
     * @param float|string $price The Treasury bill's price per $100 face value
     */
    public static function yield(mixed $settlement, mixed $maturity, $price): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $price = Functions::flatten_single_value($price);
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            $price = Financial_Validations::validate_price($price);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_between_settlement_and_maturity = $maturity - $settlement;
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($maturity)), Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL);
        if ($days_between_settlement_and_maturity > $days_per_year || $days_between_settlement_and_maturity < 0) {
            return Excel_Error::NAN();
        }
        return (100 - $price) / $price * (360 / $days_between_settlement_and_maturity);
    }
}