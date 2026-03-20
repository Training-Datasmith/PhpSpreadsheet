<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Amortization
{
    /**
     * AMORDEGRC.
     *
     * Returns the depreciation for each accounting period.
     * This function is provided for the French accounting system. If an asset is purchased in
     * the middle of the accounting period, the prorated depreciation is taken into account.
     * The function is similar to AMORLINC, except that a depreciation coefficient is applied in
     * the calculation depending on the life of the assets.
     * This function will return the depreciation until the last period of the life of the assets
     * or until the cumulated value of depreciation is greater than the cost of the assets minus
     * the salvage value.
     *
     * Excel Function:
     *        AMORDEGRC(cost,purchased,firstPeriod,salvage,period,rate[,basis])
     *
     * @param mixed $cost The float cost of the asset
     * @param mixed $purchased Date of the purchase of the asset
     * @param mixed $firstPeriod Date of the end of the first period
     * @param mixed $salvage The salvage value at the end of the life of the asset
     * @param mixed $period the period (float)
     * @param mixed $rate rate of depreciation (float)
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string (string containing the error type if there is an error)
     */
    public static function AMORDEGRC(mixed $cost, mixed $purchased, mixed $first_period, mixed $salvage, mixed $period, mixed $rate, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $cost = Functions::flatten_single_value($cost);
        $purchased = Functions::flatten_single_value($purchased);
        $first_period = Functions::flatten_single_value($first_period);
        $salvage = Functions::flatten_single_value($salvage);
        $period = Functions::flatten_single_value($period);
        $rate = Functions::flatten_single_value($rate);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $cost = Financial_Validations::validate_float($cost);
            $purchased = Financial_Validations::validate_date($purchased);
            $first_period = Financial_Validations::validate_date($first_period);
            $salvage = Financial_Validations::validate_float($salvage);
            $period = Financial_Validations::validate_int($period);
            $rate = Financial_Validations::validate_float($rate);
            $basis = Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $year_fracx = Date_Time_Excel\Year_Frac::fraction($purchased, $first_period, $basis);
        if (is_string($year_fracx)) {
            return $year_fracx;
        }
        /** @var float $yearFrac */
        $year_frac = $year_fracx;
        $amortise_coeff = self::get_amortization_coefficient($rate);
        $rate *= $amortise_coeff;
        $rate = (float) (string) $rate;
        // ugly way to avoid rounding problem
        $f_n_rate = round($year_frac * $rate * $cost, 0);
        $cost -= $f_n_rate;
        $f_rest = $cost - $salvage;
        for ($n = 0; $n < $period; ++$n) {
            $f_n_rate = round($rate * $cost, 0);
            $f_rest -= $f_n_rate;
            if ($f_rest < 0.0) {
                return match ($period - $n) {
                    1 => round($cost * 0.5, 0),
                    default => 0.0,
                };
            }
            $cost -= $f_n_rate;
        }
        return $f_n_rate;
    }
    /**
     * AMORLINC.
     *
     * Returns the depreciation for each accounting period.
     * This function is provided for the French accounting system. If an asset is purchased in
     * the middle of the accounting period, the prorated depreciation is taken into account.
     *
     * Excel Function:
     *        AMORLINC(cost,purchased,firstPeriod,salvage,period,rate[,basis])
     *
     * @param mixed $cost The cost of the asset as a float
     * @param mixed $purchased Date of the purchase of the asset
     * @param mixed $firstPeriod Date of the end of the first period
     * @param mixed $salvage The salvage value at the end of the life of the asset
     * @param mixed $period The period as a float
     * @param mixed $rate Rate of depreciation as  float
     * @param mixed $basis Integer indicating the type of day count to use.
     *                             0 or omitted    US (NASD) 30/360
     *                             1               Actual/actual
     *                             2               Actual/360
     *                             3               Actual/365
     *                             4               European 30/360
     *
     * @return float|string (string containing the error type if there is an error)
     */
    public static function AMORLINC(mixed $cost, mixed $purchased, mixed $first_period, mixed $salvage, mixed $period, mixed $rate, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $cost = Functions::flatten_single_value($cost);
        $purchased = Functions::flatten_single_value($purchased);
        $first_period = Functions::flatten_single_value($first_period);
        $salvage = Functions::flatten_single_value($salvage);
        $period = Functions::flatten_single_value($period);
        $rate = Functions::flatten_single_value($rate);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $cost = Financial_Validations::validate_float($cost);
            $purchased = Financial_Validations::validate_date($purchased);
            $first_period = Financial_Validations::validate_date($first_period);
            $salvage = Financial_Validations::validate_float($salvage);
            $period = Financial_Validations::validate_float($period);
            $rate = Financial_Validations::validate_float($rate);
            $basis = Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $f_one_rate = $cost * $rate;
        $f_cost_delta = $cost - $salvage;
        //    Note, quirky variation for leap years on the YEARFRAC for this function
        $purchased_year = Date_Time_Excel\Date_Parts::year($purchased);
        $year_fracx = Date_Time_Excel\Year_Frac::fraction($purchased, $first_period, $basis);
        if (is_string($year_fracx)) {
            return $year_fracx;
        }
        /** @var float $yearFrac */
        $year_frac = $year_fracx;
        if ($basis == Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL && $year_frac < 1) {
            $temp = Functions::scalar($purchased_year);
            if (is_int($temp) || is_string($temp)) {
                if (Date_Time_Excel\Helpers::is_leap_year($temp)) {
                    $year_frac *= 365 / 366;
                }
            }
        }
        $f0Rate = $year_frac * $rate * $cost;
        $n_num_of_full_periods = (int) (($cost - $salvage - $f0Rate) / $f_one_rate);
        if ($period == 0) {
            return $f0Rate;
        }
        if ($period <= $n_num_of_full_periods) {
            return $f_one_rate;
        }
        if ($period == $n_num_of_full_periods + 1) {
            return $f_cost_delta - $f_one_rate * $n_num_of_full_periods - $f0Rate;
        }
        return 0.0;
    }
    private static function get_amortization_coefficient(float $rate): float
    {
        //    The depreciation coefficients are:
        //    Life of assets (1/rate)        Depreciation coefficient
        //    Less than 3 years            1
        //    Between 3 and 4 years        1.5
        //    Between 5 and 6 years        2
        //    More than 6 years            2.5
        $f_use_per = 1.0 / $rate;
        if ($f_use_per < 3.0) {
            return 1.0;
        }
        if ($f_use_per < 4.0) {
            return 1.5;
        }
        if ($f_use_per <= 6.0) {
            return 2.0;
        }
        return 2.5;
    }
}