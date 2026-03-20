<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Financial_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Cash_Flow_Validations extends Financial_Validations
{
    public static function validate_rate(mixed $rate): float
    {
        return self::validate_float($rate);
    }
    public static function validate_period_type(mixed $type): int
    {
        $rate = self::validate_int($type);
        if ($type !== Financial_Constants::PAYMENT_END_OF_PERIOD && $type !== Financial_Constants::PAYMENT_BEGINNING_OF_PERIOD) {
            throw new Exception(Excel_Error::NAN());
        }
        return $rate;
    }
    public static function validate_present_value(mixed $present_value): float
    {
        return self::validate_float($present_value);
    }
    public static function validate_future_value(mixed $future_value): float
    {
        return self::validate_float($future_value);
    }
}