<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format;

use Php_Office\Php_Spreadsheet\Style\Number_Format;
class Percentage_Formatter extends Base_Formatter
{
    /** @param float|int $value */
    public static function format($value, string $format): string
    {
        if ($format === Number_Format::FORMAT_PERCENTAGE) {
            return round(100 * $value, 0) . '%';
        }
        $value *= 100;
        $format = self::strip_quotes($format);
        [, $v_decimals] = explode('.', $value . '.');
        $v_decimal_count = strlen(rtrim($v_decimals, '0'));
        $format = str_replace('%', '%%', $format);
        $whole_part_size = strlen((string) floor(abs($value)));
        $decimal_part_size = 0;
        $place_holders = '';
        // Number of decimals
        if (preg_match('/\.([?0]+)/u', $format, $matches)) {
            $decimal_part_size = strlen($matches[1]);
            $v_min_decimal_count = strlen(rtrim($matches[1], '?'));
            $decimal_part_size = min(max($v_min_decimal_count, $v_decimal_count), $decimal_part_size);
            $place_holders = str_repeat(' ', strlen($matches[1]) - $decimal_part_size);
        }
        // Number of digits to display before the decimal
        if (preg_match('/([#0,]+)\.?/u', $format, $matches)) {
            $first_zero = ltrim($matches[1], '#,');
            $whole_part_size = max($whole_part_size, strlen($first_zero));
        }
        $whole_part_size += $decimal_part_size + (int) ($decimal_part_size > 0);
        $replacement = "0{$whole_part_size}.{$decimal_part_size}";
        $mask = (string) preg_replace('/[#0,]+\.?[?#0,]*/ui', "%{$replacement}F{$place_holders}", $format);
        /** @var float $valueFloat */
        $value_float = $value;
        return self::adjust_separators(sprintf($mask, round($value_float, $decimal_part_size)));
    }
}