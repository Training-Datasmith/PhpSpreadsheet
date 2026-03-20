<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
class Formula_Translator
{
    private static function replace_quoted_period(string $value): string
    {
        $value2 = '';
        $quoted = false;
        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            if ($char === "'") {
                $quoted = !$quoted;
            } elseif ($char === '.' && $quoted) {
                $char = "￾";
            }
            $value2 .= $char;
        }
        return $value2;
    }
    public static function convert_to_excel_address_value(string $open_office_address): string
    {
        // Cell range 3-d reference
        // As we don't support 3-d ranges, we're just going to take a quick and dirty approach
        //  and assume that the second worksheet reference is the same as the first
        $excel_address = Preg::replace([
            '/\$?([^\.]+)\.([^\.]+):\$?([^\.]+)\.([^\.]+)/miu',
            '/\$?([^\.]+)\.([^\.]+):\.([^\.]+)/miu',
            // Cell range reference in another sheet
            '/\$?([^\.]+)\.([^\.]+)/miu',
            // Cell reference in another sheet
            '/\.([^\.]+):\.([^\.]+)/miu',
            // Cell range reference
            '/\.([^\.]+)/miu',
            // Simple cell reference
            '/\x{FFFE}/miu',
        ], ['$1!$2:$4', '$1!$2:$3', '$1!$2', '$1:$2', '$1', '.'], self::replace_quoted_period($open_office_address));
        return $excel_address;
    }
    public static function convert_to_excel_formula_value(string $open_office_formula): string
    {
        $temp = explode(Calculation::FORMULA_STRING_QUOTE, $open_office_formula);
        $t_key = false;
        $in_matrix_braces_level = 0;
        $in_function_braces_level = 0;
        foreach ($temp as &$value) {
            // @var string $value
            // Only replace in alternate array entries (i.e. non-quoted blocks)
            //      so that conversion isn't done in string values
            $t_key = $t_key === false;
            if ($t_key) {
                $value = Preg::replace([
                    '/\[\$?([^\.]+)\.([^\.]+):\.([^\.]+)\]/miu',
                    // Cell range reference in another sheet
                    '/\[\$?([^\.]+)\.([^\.]+)\]/miu',
                    // Cell reference in another sheet
                    '/\[\.([^\.]+):\.([^\.]+)\]/miu',
                    // Cell range reference
                    '/\[\.([^\.]+)\]/miu',
                    // Simple cell reference
                    '/\x{FFFE}/miu',
                ], ['$1!$2:$3', '$1!$2', '$1:$2', '$1', '.'], self::replace_quoted_period($value));
                // Convert references to defined names/formulae
                $value = str_replace('$$', '', $value);
                // Convert ODS function argument separators to Excel function argument separators
                $value = Calculation::translate_separator(';', ',', $value, $in_function_braces_level);
                // Convert ODS matrix separators to Excel matrix separators
                $value = Calculation::translate_separator(';', ',', $value, $in_matrix_braces_level, Calculation::FORMULA_OPEN_MATRIX_BRACE, Calculation::FORMULA_CLOSE_MATRIX_BRACE);
                $value = Calculation::translate_separator('|', ';', $value, $in_matrix_braces_level, Calculation::FORMULA_OPEN_MATRIX_BRACE, Calculation::FORMULA_CLOSE_MATRIX_BRACE);
                $value = Preg::replace(['/\b(?<!com[.]microsoft[.])' . '(floor|ceiling)\s*[(]/ui', '/COM\.MICROSOFT\./ui'], ['$1.ODS(', ''], $value);
            }
        }
        // Then rebuild the formula string
        $excel_formula = implode('"', $temp);
        return $excel_formula;
    }
}