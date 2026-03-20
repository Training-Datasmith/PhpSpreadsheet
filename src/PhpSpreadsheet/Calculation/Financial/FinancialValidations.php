<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Date_Time_Excel;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Financial_Validations
{
    public static function validate_date(mixed $date): float
    {
        return Date_Time_Excel\Helpers::get_date_value($date);
    }
    public static function validate_settlement_date(mixed $settlement): float
    {
        return self::validate_date($settlement);
    }
    public static function validate_maturity_date(mixed $maturity): float
    {
        return self::validate_date($maturity);
    }
    public static function validate_float(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (float) $value;
    }
    public static function validate_int(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new Exception(Excel_Error::VALUE());
        }
        return (int) floor((float) $value);
    }
    public static function validate_rate(mixed $rate): float
    {
        $rate = self::validate_float($rate);
        if ($rate < 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $rate;
    }
    public static function validate_frequency(mixed $frequency): int
    {
        $frequency = self::validate_int($frequency);
        if ($frequency !== Financial_Constants::FREQUENCY_ANNUAL && $frequency !== Financial_Constants::FREQUENCY_SEMI_ANNUAL && $frequency !== Financial_Constants::FREQUENCY_QUARTERLY) {
            throw new Exception(Excel_Error::NAN());
        }
        return $frequency;
    }
    public static function validate_basis(mixed $basis): int
    {
        if (!is_numeric($basis)) {
            throw new Exception(Excel_Error::VALUE());
        }
        $basis = (int) $basis;
        if ($basis < 0 || $basis > 4) {
            throw new Exception(Excel_Error::NAN());
        }
        return $basis;
    }
    public static function validate_price(mixed $price): float
    {
        $price = self::validate_float($price);
        if ($price < 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $price;
    }
    public static function validate_par_value(mixed $par_value): float
    {
        $par_value = self::validate_float($par_value);
        if ($par_value < 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $par_value;
    }
    public static function validate_yield(mixed $yield): float
    {
        $yield = self::validate_float($yield);
        if ($yield < 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $yield;
    }
    public static function validate_discount(mixed $discount): float
    {
        $discount = self::validate_float($discount);
        if ($discount <= 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $discount;
    }
}