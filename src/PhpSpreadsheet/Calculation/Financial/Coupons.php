<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use DateTime;
use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Date;
class Coupons
{
    private const PERIOD_DATE_PREVIOUS = false;
    private const PERIOD_DATE_NEXT = true;
    /**
     * COUPDAYBS.
     *
     * Returns the number of days from the beginning of the coupon period to the settlement date.
     *
     * Excel Function:
     *        COUPDAYBS(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year (int).
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function COUPDAYBS(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|int|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            $basis = Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $days_per_year = Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($settlement)), $basis);
        if (is_string($days_per_year)) {
            return Excel_Error::VALUE();
        }
        $prev = self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_PREVIOUS);
        if ($basis === Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL) {
            return abs((float) Date_Time_Excel\Days::between($prev, $settlement));
        }
        return (float) Date_Time_Excel\Year_Frac::fraction($prev, $settlement, $basis) * $days_per_year;
    }
    /**
     * COUPDAYS.
     *
     * Returns the number of days in the coupon period that contains the settlement date.
     *
     * Excel Function:
     *        COUPDAYS(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function COUPDAYS(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|int|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            $basis = Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        switch ($basis) {
            case Financial_Constants::BASIS_DAYS_PER_YEAR_365:
                // Actual/365
                return 365 / $frequency;
            case Financial_Constants::BASIS_DAYS_PER_YEAR_ACTUAL:
                // Actual/actual
                if ($frequency == Financial_Constants::FREQUENCY_ANNUAL) {
                    $days_per_year = (int) Helpers::days_per_year(Functions::scalar(Date_Time_Excel\Date_Parts::year($settlement)), $basis);
                    return $days_per_year / $frequency;
                }
                $prev = self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_PREVIOUS);
                $next = self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_NEXT);
                return $next - $prev;
            default:
                // US (NASD) 30/360, Actual/360 or European 30/360
                return 360 / $frequency;
        }
    }
    /**
     * COUPDAYSNC.
     *
     * Returns the number of days from the settlement date to the next coupon date.
     *
     * Excel Function:
     *        COUPDAYSNC(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int) .
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function COUPDAYSNC(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            $basis = Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        /** @var int $daysPerYear */
        $days_per_year = Helpers::days_per_year(Functions::Scalar(Date_Time_Excel\Date_Parts::year($settlement)), $basis);
        $next = self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_NEXT);
        if ($basis === Financial_Constants::BASIS_DAYS_PER_YEAR_NASD) {
            $settlement_date = Date::excel_to_date_time_object($settlement);
            $settlement_eo_m = Helpers::is_last_day_of_month($settlement_date);
            if ($settlement_eo_m) {
                ++$settlement;
            }
        }
        return (float) Date_Time_Excel\Year_Frac::fraction($settlement, $next, $basis) * $days_per_year;
    }
    /**
     * COUPNCD.
     *
     * Returns the next coupon date after the settlement date.
     *
     * Excel Function:
     *        COUPNCD(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Excel date/time serial value or error message
     */
    public static function COUPNCD(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_NEXT);
    }
    /**
     * COUPNUM.
     *
     * Returns the number of coupons payable between the settlement date and maturity date,
     * rounded up to the nearest whole coupon.
     *
     * Excel Function:
     *        COUPNUM(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                                  date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     */
    public static function COUPNUM(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|int
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $years_between_settlement_and_maturity = Date_Time_Excel\Year_Frac::fraction($settlement, $maturity, Financial_Constants::BASIS_DAYS_PER_YEAR_NASD);
        return (int) ceil((float) $years_between_settlement_and_maturity * $frequency);
    }
    /**
     * COUPPCD.
     *
     * Returns the previous coupon date before the settlement date.
     *
     * Excel Function:
     *        COUPPCD(settlement,maturity,frequency[,basis])
     *
     * @param mixed $settlement The security's settlement date.
     *                              The security settlement date is the date after the issue
     *                              date when the security is traded to the buyer.
     * @param mixed $maturity The security's maturity date.
     *                            The maturity date is the date when the security expires.
     * @param mixed $frequency The number of coupon payments per year.
     *                             Valid frequency values are:
     *                               1    Annual
     *                               2    Semi-Annual
     *                               4    Quarterly
     * @param mixed $basis The type of day count to use (int).
     *                         0 or omitted    US (NASD) 30/360
     *                         1               Actual/actual
     *                         2               Actual/360
     *                         3               Actual/365
     *                         4               European 30/360
     *
     * @return float|string Excel date/time serial value or error message
     */
    public static function COUPPCD(mixed $settlement, mixed $maturity, mixed $frequency, mixed $basis = Financial_Constants::BASIS_DAYS_PER_YEAR_NASD): string|float
    {
        $settlement = Functions::flatten_single_value($settlement);
        $maturity = Functions::flatten_single_value($maturity);
        $frequency = Functions::flatten_single_value($frequency);
        $basis = Functions::flatten_single_value($basis) ?? Financial_Constants::BASIS_DAYS_PER_YEAR_NASD;
        try {
            $settlement = Financial_Validations::validate_settlement_date($settlement);
            $maturity = Financial_Validations::validate_maturity_date($maturity);
            self::validate_coupon_period($settlement, $maturity);
            $frequency = Financial_Validations::validate_frequency($frequency);
            Financial_Validations::validate_basis($basis);
        } catch (Exception $e) {
            return $e->get_message();
        }
        return self::coupon_first_period_date($settlement, $maturity, $frequency, self::PERIOD_DATE_PREVIOUS);
    }
    private static function months_diff(DateTime $result, int $months, string $plus_or_minus, int $day, bool $last_day_flag): void
    {
        $result->set_date((int) $result->format('Y'), (int) $result->format('m'), 1);
        $result->modify("{$plus_or_minus} {$months} months");
        $days_in_month = (int) $result->format('t');
        $result->set_date((int) $result->format('Y'), (int) $result->format('m'), $last_day_flag ? $days_in_month : min($day, $days_in_month));
    }
    private static function coupon_first_period_date(float $settlement, float $maturity, int $frequency, bool $next): float
    {
        $months = 12 / $frequency;
        $result = Date::excel_to_date_time_object($maturity);
        $day = (int) $result->format('d');
        $last_day_flag = Helpers::is_last_day_of_month($result);
        while ($settlement < Date::php_to_excel($result)) {
            self::months_diff($result, $months, '-', $day, $last_day_flag);
        }
        if ($next === true) {
            self::months_diff($result, $months, '+', $day, $last_day_flag);
        }
        return (float) Date::php_to_excel($result);
    }
    private static function validate_coupon_period(float $settlement, float $maturity): void
    {
        if ($settlement >= $maturity) {
            throw new Exception(Excel_Error::NAN());
        }
    }
}