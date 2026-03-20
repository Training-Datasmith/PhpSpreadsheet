<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Confidence
{
    use Array_Enabled;
    /**
     * CONFIDENCE.
     *
     * Returns the confidence interval for a population mean
     *
     * @param mixed $alpha As a float
     *                      Or can be an array of values
     * @param mixed $stdDev Standard Deviation as a float
     *                      Or can be an array of values
     * @param mixed $size As an integer
     *                      Or can be an array of values
     *
     * @return array<mixed>|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function CONFIDENCE(mixed $alpha, mixed $std_dev, mixed $size)
    {
        if (is_array($alpha) || is_array($std_dev) || is_array($size)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $alpha, $std_dev, $size);
        }
        try {
            $alpha = Statistical_Validations::validate_float($alpha);
            $std_dev = Statistical_Validations::validate_float($std_dev);
            $size = Statistical_Validations::validate_int($size);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($alpha <= 0 || $alpha >= 1 || $std_dev <= 0 || $size < 1) {
            return Excel_Error::NAN();
        }
        /** @var float $temp */
        $temp = Distributions\Standard_Normal::inverse(1 - $alpha / 2);
        /** @var float */
        $result = Functions::scalar($temp * $std_dev / sqrt($size));
        return $result;
    }
}