<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engineering;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
abstract class Convert_Base
{
    use Array_Enabled;
    protected static function validate_value(mixed $value): string
    {
        if (is_bool($value)) {
            if (Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_OPENOFFICE) {
                throw new Exception(Excel_Error::VALUE());
            }
            $value = (int) $value;
        }
        if (is_numeric($value)) {
            if (Functions::get_compatibility_mode() == Functions::COMPATIBILITY_GNUMERIC) {
                $value = floor((float) $value);
            }
        }
        return strtoupper(String_Helper::convert_to_string($value));
    }
    protected static function validate_places(mixed $places = null): ?int
    {
        if ($places === null) {
            return $places;
        }
        if (is_numeric($places)) {
            if ($places < 0 || $places > 10) {
                throw new Exception(Excel_Error::NAN());
            }
            return (int) $places;
        }
        throw new Exception(Excel_Error::VALUE());
    }
    /**
     * Formats a number base string value with leading zeroes.
     *
     * @param string $value The "number" to pad
     * @param ?int $places The length that we want to pad this value
     *
     * @return string The padded "number"
     */
    protected static function nbr_conversion_format(string $value, ?int $places): string
    {
        if ($places !== null) {
            if (strlen($value) <= $places) {
                return substr(str_pad($value, $places, '0', STR_PAD_LEFT), -10);
            }
            return Excel_Error::NAN();
        }
        return substr($value, -10);
    }
}