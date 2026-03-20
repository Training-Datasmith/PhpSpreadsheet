<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Arabic
{
    use Array_Enabled;
    private const ROMAN_LOOKUP = ['M' => 1000, 'D' => 500, 'C' => 100, 'L' => 50, 'X' => 10, 'V' => 5, 'I' => 1];
    /**
     * Recursively calculate the arabic value of a roman numeral.
     *
     * @param string[] $roman
     */
    private static function calculate_arabic(array $roman, int &$sum = 0, int $subtract = 0): int
    {
        $numeral = array_shift($roman);
        if (!isset(self::ROMAN_LOOKUP[$numeral])) {
            throw new Exception('Invalid character detected');
        }
        $arabic = self::ROMAN_LOOKUP[$numeral];
        if (count($roman) > 0 && isset(self::ROMAN_LOOKUP[$roman[0]]) && $arabic < self::ROMAN_LOOKUP[$roman[0]]) {
            $subtract += $arabic;
        } else {
            $sum += $arabic - $subtract;
            $subtract = 0;
        }
        if (count($roman) > 0) {
            self::calculate_arabic($roman, $sum, $subtract);
        }
        return $sum;
    }
    /**
     * ARABIC.
     *
     * Converts a Roman numeral to an Arabic numeral.
     *
     * Excel Function:
     *        ARABIC(text)
     *
     * @param string|string[] $roman Should be a string, or can be an array of strings
     *
     * @return array<mixed>|int|string the arabic numeral contrived from the roman numeral
     *         If an array of numbers is passed as the argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function evaluate(mixed $roman): array|int|string
    {
        if (is_array($roman)) {
            return self::evaluate_single_argument_array([self::class, __FUNCTION__], $roman);
        }
        // An empty string should return 0
        $roman = substr(trim(strtoupper((string) $roman)), 0, 255);
        if ($roman === '') {
            return 0;
        }
        // Convert the roman numeral to an arabic number
        $negative_number = $roman[0] === '-';
        if ($negative_number) {
            $roman = trim(substr($roman, 1));
            if ($roman === '') {
                return Excel_Error::NAN();
            }
        }
        try {
            $arabic = self::calculate_arabic(mb_str_split($roman, 1, 'UTF-8'));
        } catch (Exception) {
            return Excel_Error::VALUE();
            // Invalid character detected
        }
        if ($negative_number) {
            $arabic *= -1;
            // The number should be negative
        }
        return $arabic;
    }
}