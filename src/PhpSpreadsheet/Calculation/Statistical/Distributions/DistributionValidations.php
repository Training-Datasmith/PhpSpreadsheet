<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Statistical\Statistical_Validations;
class Distribution_Validations extends Statistical_Validations
{
    public static function validate_probability(mixed $probability): float
    {
        $probability = self::validate_float($probability);
        if ($probability < 0.0 || $probability > 1.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $probability;
    }
}