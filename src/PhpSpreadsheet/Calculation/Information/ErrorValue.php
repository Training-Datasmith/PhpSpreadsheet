<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Information;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Error_Value
{
    use Array_Enabled;
    /**
     * IS_ERR.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_err(mixed $value = ''): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return self::is_error($value) && !self::is_na($value);
    }
    /**
     * IS_ERROR.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_error(mixed $value = '', bool $try_not_implemented = false): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        if (!is_string($value)) {
            return false;
        }
        if ($try_not_implemented && $value === Functions::NOT_YET_IMPLEMENTED) {
            return true;
        }
        return in_array($value, Excel_Error::ERROR_CODES, true);
    }
    /**
     * IS_NA.
     *
     * @param mixed $value Value to check
     *                      Or can be an array of values
     *
     * @return array<mixed>|bool If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function is_na(mixed $value = ''): array|bool
    {
        if (is_array($value)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $value);
        }
        return $value === Excel_Error::NA();
    }
}