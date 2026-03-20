<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Internal\Excel_Array_Pseudo_Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Concatenate
{
    use Array_Enabled;
    /**
     * This implements the CONCAT function, *not* CONCATENATE.
     *
     * @param mixed $args data to be concatenated
     */
    public static function CONCATENATE(...$args): string
    {
        $return_value = '';
        // Loop through arguments
        $a_args = Functions::flatten_array($args);
        foreach ($a_args as $arg) {
            $value = Helpers::extract_string($arg);
            if (Error_Value::is_error($value, true)) {
                $return_value = $value;
                break;
            }
            $return_value .= Helpers::extract_string($arg);
            if (String_Helper::count_characters($return_value) > Data_Type::MAX_STRING_LENGTH) {
                $return_value = Excel_Error::CALC();
                break;
            }
        }
        return $return_value;
    }
    /**
     * This implements the CONCATENATE function.
     *
     * @param mixed $args data to be concatenated
     *
     * @return array<string>|string
     */
    public static function actual_concatenate(...$args): array|string
    {
        $use_single = false;
        $cell = null;
        $count = count($args);
        if ($args[$count - 1] instanceof Cell) {
            /** @var Cell */
            $cell = array_pop($args);
            $type = $cell->get_worksheet()->get_parent()?->get_calculation_engine()->get_instance_array_return_type() ?? Calculation::get_array_return_type();
            $use_single = $type === Calculation::RETURN_ARRAY_AS_VALUE;
        }
        if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_GNUMERIC) {
            return self::CONCATENATE(...$args);
        }
        $result = '';
        foreach ($args as $operand2) {
            if ($use_single && $cell instanceof Cell && is_array($operand2)) {
                $temp = Functions::convert_array_to_cell_range($operand2);
                if ($temp !== '') {
                    $operand2 = Excel_Array_Pseudo_Functions::single($temp, $cell);
                }
            }
            /** @var null|array<mixed>|bool|float|int|string $operand2 */
            $result = self::concatenate2Args($result, $operand2);
            if (Error_Value::is_error($result, true) === true) {
                break;
            }
        }
        return $result;
    }
    /**
     * @param array<string>|string $operand1
     * @param null|array<mixed>|bool|float|int|string $operand2
     *
     * @return array<string>|string
     */
    private static function concatenate2Args(array|string $operand1, null|array|bool|float|int|string $operand2): array|string
    {
        if (is_array($operand1) || is_array($operand2)) {
            $operand1 = Calculation::bool_to_string($operand1);
            $operand2 = Calculation::bool_to_string($operand2);
            [$rows, $columns] = Calculation::check_matrix_operands($operand1, $operand2, 2);
            $error_found = false;
            for ($row = 0; $row < $rows && !$error_found; ++$row) {
                for ($column = 0; $column < $columns; ++$column) {
                    /** @var string[][] $operand2 */
                    if (Error_Value::is_error($operand2[$row][$column])) {
                        return $operand2[$row][$column];
                    }
                    /** @var string[][] $operand1 */
                    $operand1[$row][$column] = String_Helper::convert_to_string($operand1[$row][$column], convertBool: true) . String_Helper::convert_to_string($operand2[$row][$column], convertBool: true);
                    if (mb_strlen($operand1[$row][$column]) > Data_Type::MAX_STRING_LENGTH) {
                        $operand1 = Excel_Error::CALC();
                        $error_found = true;
                        break;
                    }
                }
            }
        } elseif (Error_Value::is_error($operand2, true) === true) {
            $operand1 = (string) $operand2;
        } else {
            $operand1 .= String_Helper::convert_to_string($operand2, convertBool: true);
            if (mb_strlen($operand1) > Data_Type::MAX_STRING_LENGTH) {
                $operand1 = Excel_Error::CALC();
            }
        }
        /** @var array<string>|string $operand1 */
        return $operand1;
    }
    /**
     * TEXTJOIN.
     *
     * @param null|string|string[] $delimiter The delimiter to use between the joined arguments
     *                         Or can be an array of values
     * @param null|bool|bool[] $ignoreEmpty true/false Flag indicating whether empty arguments should be skipped
     *                         Or can be an array of values
     * @param mixed $args The values to join
     *
     * @return array<mixed>|string The joined string
     *         If an array of values is passed for the $delimiter or $ignoreEmpty arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function TEXTJOIN($delimiter = '', $ignore_empty = true, mixed ...$args): array|string
    {
        if (is_array($delimiter) || is_array($ignore_empty)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 2, $delimiter, $ignore_empty, ...$args);
        }
        $delimiter ??= '';
        $ignore_empty ??= true;
        /** @var mixed[] */
        $a_args = Functions::flatten_array($args);
        $return_value = self::evaluate_text_join_array($ignore_empty, $a_args);
        $return_value ??= implode($delimiter, $a_args);
        if (String_Helper::count_characters($return_value) > Data_Type::MAX_STRING_LENGTH) {
            return Excel_Error::CALC();
        }
        return $return_value;
    }
    /** @param mixed[] $aArgs */
    private static function evaluate_text_join_array(bool $ignore_empty, array &$a_args): ?string
    {
        foreach ($a_args as $key => &$arg) {
            $value = Helpers::extract_string($arg);
            if (Error_Value::is_error($value, true)) {
                return $value;
            }
            if ($ignore_empty === true && (is_string($arg) && trim($arg) === '' || $arg === null)) {
                unset($a_args[$key]);
            } elseif (is_bool($arg)) {
                $arg = Helpers::convert_boolean_value($arg);
            }
        }
        return null;
    }
    /**
     * REPT.
     *
     * Returns the result of builtin function round after validating args.
     *
     * @param mixed $stringValue The value to repeat
     *                         Or can be an array of values
     * @param mixed $repeatCount The number of times the string value should be repeated
     *                         Or can be an array of values
     *
     * @return array<mixed>|string The repeated string
     *         If an array of values is passed for the $stringValue or $repeatCount arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function builtin_rept(mixed $string_value, mixed $repeat_count): array|string
    {
        if (is_array($string_value) || is_array($repeat_count)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $string_value, $repeat_count);
        }
        $string_value = Helpers::extract_string($string_value);
        if (!is_numeric($repeat_count) || $repeat_count < 0) {
            $return_value = Excel_Error::VALUE();
        } elseif (Error_Value::is_error($string_value, true)) {
            $return_value = $string_value;
        } else {
            $return_value = str_repeat($string_value, (int) $repeat_count);
            if (String_Helper::count_characters($return_value) > Data_Type::MAX_STRING_LENGTH) {
                $return_value = Excel_Error::VALUE();
                // note VALUE not CALC
            }
        }
        return $return_value;
    }
}