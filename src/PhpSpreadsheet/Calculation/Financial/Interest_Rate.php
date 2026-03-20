<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Interest_Rate
{
    /**
     * EFFECT.
     *
     * Returns the effective interest rate given the nominal rate and the number of
     *        compounding payments per year.
     *
     * Excel Function:
     *        EFFECT(nominal_rate,npery)
     *
     * @param mixed $nominalRate Nominal interest rate as a float
     * @param mixed $periodsPerYear Integer number of compounding payments per year
     */
    public static function effective(mixed $nominal_rate = 0, mixed $periods_per_year = 0): string|float
    {
        $nominal_rate = Functions::flatten_single_value($nominal_rate);
        $periods_per_year = Functions::flatten_single_value($periods_per_year);
        try {
            $nominal_rate = Financial_Validations::validate_float($nominal_rate);
            $periods_per_year = Financial_Validations::validate_int($periods_per_year);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($nominal_rate <= 0 || $periods_per_year < 1) {
            return Excel_Error::NAN();
        }
        return (1 + $nominal_rate / $periods_per_year) ** $periods_per_year - 1;
    }
    /**
     * NOMINAL.
     *
     * Returns the nominal interest rate given the effective rate and the number of compounding payments per year.
     *
     * @param mixed $effectiveRate Effective interest rate as a float
     * @param mixed $periodsPerYear Integer number of compounding payments per year
     *
     * @return float|string Result, or a string containing an error
     */
    public static function nominal(mixed $effective_rate = 0, mixed $periods_per_year = 0): string|float
    {
        $effective_rate = Functions::flatten_single_value($effective_rate);
        $periods_per_year = Functions::flatten_single_value($periods_per_year);
        try {
            $effective_rate = Financial_Validations::validate_float($effective_rate);
            $periods_per_year = Financial_Validations::validate_int($periods_per_year);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($effective_rate <= 0 || $periods_per_year < 1) {
            return Excel_Error::NAN();
        }
        // Calculate
        return $periods_per_year * (($effective_rate + 1) ** (1 / $periods_per_year) - 1);
    }
}