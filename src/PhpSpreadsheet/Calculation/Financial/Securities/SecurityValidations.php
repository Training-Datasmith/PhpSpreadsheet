<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Securities;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Financial\Financial_Validations;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Security_Validations extends Financial_Validations
{
    public static function validate_issue_date(mixed $issue): float
    {
        return self::validate_date($issue);
    }
    public static function validate_security_period(mixed $settlement, mixed $maturity): void
    {
        if ($settlement >= $maturity) {
            throw new Exception(Excel_Error::NAN());
        }
    }
    public static function validate_redemption(mixed $redemption): float
    {
        $redemption = self::validate_float($redemption);
        if ($redemption <= 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $redemption;
    }
}