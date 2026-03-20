<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Binary_Comparison
{
    /**
     * Epsilon Precision used for comparisons in calculations.
     */
    private const DELTA = 1.0E-13;
    /**
     * Compare two strings in the same way as strcmp() except that lowercase come before uppercase letters.
     *
     * @param mixed $str1 First string value for the comparison, expect ?string
     * @param mixed $str2 Second string value for the comparison, expect ?string
     */
    private static function strcmp_lowercase_first(mixed $str1, mixed $str2): int
    {
        $str1 = String_Helper::convert_to_string($str1);
        $str2 = String_Helper::convert_to_string($str2);
        $inversed_str1 = String_Helper::str_case_reverse($str1);
        $inversed_str2 = String_Helper::str_case_reverse($str2);
        return strcmp($inversed_str1, $inversed_str2);
    }
    /**
     * PHP8.1 deprecates passing null to strcmp.
     *
     * @param mixed $str1 First string value for the comparison, expect ?string
     * @param mixed $str2 Second string value for the comparison, expect ?string
     */
    private static function strcmp_allow_null(mixed $str1, mixed $str2): int
    {
        $str1 = String_Helper::convert_to_string($str1);
        $str2 = String_Helper::convert_to_string($str2);
        return strcmp($str1, $str2);
    }
    public static function compare(mixed $operand1, mixed $operand2, string $operator): bool|string
    {
        //    Simple validate the two operands if they are string values
        if (is_string($operand1) && $operand1 > '' && $operand1[0] == Calculation::FORMULA_STRING_QUOTE) {
            $operand1 = Calculation::unwrap_result($operand1);
        }
        if (Error_Value::is_error($operand1, true)) {
            /** @var string $operand1 */
            return $operand1;
        }
        if (is_string($operand2) && $operand2 > '' && $operand2[0] == Calculation::FORMULA_STRING_QUOTE) {
            $operand2 = Calculation::unwrap_result($operand2);
        }
        if (Error_Value::is_error($operand2, true)) {
            /** @var string $operand2 */
            return $operand2;
        }
        // Use case-insensitive comparison if not OpenOffice mode
        if (Functions::get_compatibility_mode() != Functions::COMPATIBILITY_OPENOFFICE) {
            if (is_string($operand1)) {
                $operand1 = String_Helper::str_to_upper($operand1);
            }
            if (is_string($operand2)) {
                $operand2 = String_Helper::str_to_upper($operand2);
            }
        }
        $use_lowercase_first_comparison = is_string($operand1) && is_string($operand2) && Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE;
        return self::evaluate_comparison($operand1, $operand2, $operator, $use_lowercase_first_comparison);
    }
    private static function evaluate_comparison(mixed $operand1, mixed $operand2, string $operator, bool $use_lowercase_first_comparison): bool
    {
        return match ($operator) {
            '=' => self::equal($operand1, $operand2),
            '>' => self::greater_than($operand1, $operand2, $use_lowercase_first_comparison),
            '<' => self::less_than($operand1, $operand2, $use_lowercase_first_comparison),
            '>=' => self::greater_than_or_equal($operand1, $operand2, $use_lowercase_first_comparison),
            '<=' => self::less_than_or_equal($operand1, $operand2, $use_lowercase_first_comparison),
            '<>' => self::not_equal($operand1, $operand2),
            default => throw new Exception('Unsupported binary comparison operator'),
        };
    }
    private static function equal(mixed $operand1, mixed $operand2): bool
    {
        if (is_numeric($operand1) && is_numeric($operand2)) {
            $result = abs($operand1 - $operand2) < self::DELTA;
        } elseif ($operand1 === null && is_numeric($operand2) || $operand2 === null && is_numeric($operand1)) {
            $result = $operand1 == $operand2;
        } else {
            $result = self::strcmp_allow_null($operand1, $operand2) == 0;
        }
        return $result;
    }
    private static function greater_than_or_equal(mixed $operand1, mixed $operand2, bool $use_lowercase_first_comparison): bool
    {
        if (is_numeric($operand1) && is_numeric($operand2)) {
            $result = abs($operand1 - $operand2) < self::DELTA || $operand1 > $operand2;
        } elseif ($operand1 === null && is_numeric($operand2) || $operand2 === null && is_numeric($operand1)) {
            $result = $operand1 >= $operand2;
        } elseif ($use_lowercase_first_comparison) {
            $result = self::strcmp_lowercase_first($operand1, $operand2) >= 0;
        } else {
            $result = self::strcmp_allow_null($operand1, $operand2) >= 0;
        }
        return $result;
    }
    private static function less_than_or_equal(mixed $operand1, mixed $operand2, bool $use_lowercase_first_comparison): bool
    {
        if (is_numeric($operand1) && is_numeric($operand2)) {
            $result = abs($operand1 - $operand2) < self::DELTA || $operand1 < $operand2;
        } elseif ($operand1 === null && is_numeric($operand2) || $operand2 === null && is_numeric($operand1)) {
            $result = $operand1 <= $operand2;
        } elseif ($use_lowercase_first_comparison) {
            $result = self::strcmp_lowercase_first($operand1, $operand2) <= 0;
        } else {
            $result = self::strcmp_allow_null($operand1, $operand2) <= 0;
        }
        return $result;
    }
    private static function greater_than(mixed $operand1, mixed $operand2, bool $use_lowercase_first_comparison): bool
    {
        return self::less_than_or_equal($operand1, $operand2, $use_lowercase_first_comparison) !== true;
    }
    private static function less_than(mixed $operand1, mixed $operand2, bool $use_lowercase_first_comparison): bool
    {
        return self::greater_than_or_equal($operand1, $operand2, $use_lowercase_first_comparison) !== true;
    }
    private static function not_equal(mixed $operand1, mixed $operand2): bool
    {
        return self::equal($operand1, $operand2) !== true;
    }
}