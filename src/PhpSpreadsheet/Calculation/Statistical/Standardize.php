<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Standardize extends Statistical_Validations
{
    use Array_Enabled;
    /**
     * STANDARDIZE.
     *
     * Returns a normalized value from a distribution characterized by mean and standard_dev.
     *
     * @param array<mixed>|float $value Value to normalize
     *                      Or can be an array of values
     * @param array<mixed>|float $mean Mean Value
     *                      Or can be an array of values
     * @param array<mixed>|float $stdDev Standard Deviation
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string Standardized value, or a string containing an error
     *         If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function execute($value, $mean, $std_dev): array|string|float
    {
        if (is_array($value) || is_array($mean) || is_array($std_dev)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $value, $mean, $std_dev);
        }
        try {
            $value = self::validate_float($value);
            $mean = self::validate_float($mean);
            $std_dev = self::validate_float($std_dev);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($std_dev <= 0) {
            return Excel_Error::NAN();
        }
        return ($value - $mean) / $std_dev;
    }
}