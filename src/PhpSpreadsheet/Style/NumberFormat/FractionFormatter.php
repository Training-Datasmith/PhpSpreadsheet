<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format;

use Php_Office\Php_Spreadsheet\Calculation\Math_Trig;
class Fraction_Formatter extends Base_Formatter
{
    /** @param null|bool|float|int|string $value  value to be formatted */
    public static function format(mixed $value, string $format): string
    {
        $format = self::strip_quotes($format);
        $value = (float) $value;
        $abs_value = abs($value);
        $sign = $value < 0.0 ? '-' : '';
        $integer_part = floor($abs_value);
        $decimal_part = self::get_decimal((string) $abs_value);
        if ($decimal_part === '0') {
            return "{$sign}{$integer_part}";
        }
        $decimal_length = strlen($decimal_part);
        $decimal_divisor = 10 ** $decimal_length;
        preg_match('/(#?.*\?)\/(\?+|\d+)/', $format, $matches);
        $format_integer_part = $matches[1] ?? '0';
        if (isset($matches[2]) && is_numeric($matches[2])) {
            $fraction_divisor = 100 / (int) $matches[2];
        } else {
            /** @var float $fractionDivisor */
            $fraction_divisor = Math_Trig\Gcd::evaluate((int) $decimal_part, $decimal_divisor);
        }
        $adjusted_decimal_part = (int) round((int) $decimal_part / $fraction_divisor, 0);
        $adjusted_decimal_divisor = $decimal_divisor / $fraction_divisor;
        if (str_contains($format_integer_part, '0')) {
            return "{$sign}{$integer_part} {$adjusted_decimal_part}/{$adjusted_decimal_divisor}";
        }
        if (str_contains($format_integer_part, '#')) {
            if ($integer_part == 0) {
                return "{$sign}{$adjusted_decimal_part}/{$adjusted_decimal_divisor}";
            }
            return "{$sign}{$integer_part} {$adjusted_decimal_part}/{$adjusted_decimal_divisor}";
        }
        if (str_starts_with($format_integer_part, '? ?')) {
            if ($integer_part == 0) {
                $integer_part = '';
            }
            return "{$sign}{$integer_part} {$adjusted_decimal_part}/{$adjusted_decimal_divisor}";
        }
        $adjusted_decimal_part += $integer_part * $adjusted_decimal_divisor;
        return "{$sign}{$adjusted_decimal_part}/{$adjusted_decimal_divisor}";
    }
    private static function get_decimal(string $value): string
    {
        if (preg_match('/^\d*[.](\d*[1-9])0*$/', $value, $matches) === 1) {
            return $matches[1];
        }
        return '0';
    }
}