<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Text_Data;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalcExp;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Replace
{
    use Array_Enabled;
    /**
     * REPLACE.
     *
     * @param mixed $oldText The text string value to modify
     *                         Or can be an array of values
     * @param mixed $start Integer offset for start character of the replacement
     *                         Or can be an array of values
     * @param mixed $chars Integer number of characters to replace from the start offset
     *                         Or can be an array of values
     * @param mixed $newText String to replace in the defined position
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function replace(mixed $old_text, mixed $start, mixed $chars, mixed $new_text): array|string
    {
        if (is_array($old_text) || is_array($start) || is_array($chars) || is_array($new_text)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $old_text, $start, $chars, $new_text);
        }
        try {
            $start = Helpers::extract_int($start, 1, 0, true);
            $chars = Helpers::extract_int($chars, 0, 0, true);
            $old_text = Helpers::extract_string($old_text, true);
            $new_text = Helpers::extract_string($new_text, true);
            $left = String_Helper::substring($old_text, 0, $start - 1);
            $right = String_Helper::substring($old_text, $start + $chars - 1, null);
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        $return_value = $left . $new_text . $right;
        if (String_Helper::count_characters($return_value) > Data_Type::MAX_STRING_LENGTH) {
            return Excel_Error::VALUE();
        }
        return $return_value;
    }
    /**
     * SUBSTITUTE.
     *
     * @param mixed $text The text string value to modify
     *                         Or can be an array of values
     * @param mixed $fromText The string value that we want to replace in $text
     *                         Or can be an array of values
     * @param mixed $toText The string value that we want to replace with in $text
     *                         Or can be an array of values
     * @param mixed $instance Integer instance Number for the occurrence of frmText to change
     *                         Or can be an array of values
     *
     * @return array<mixed>|string If an array of values is passed for either of the arguments, then the returned result
     *            will also be an array with matching dimensions
     */
    public static function substitute(mixed $text = '', mixed $from_text = '', mixed $to_text = '', mixed $instance = null): array|string
    {
        if (is_array($text) || is_array($from_text) || is_array($to_text) || is_array($instance)) {
            return self::evaluate_array_arguments([self::class, __FUNCTION__], $text, $from_text, $to_text, $instance);
        }
        try {
            $text = Helpers::extract_string($text, true);
            $from_text = Helpers::extract_string($from_text, true);
            $to_text = Helpers::extract_string($to_text, true);
            if ($instance === null) {
                $return_value = str_replace($from_text, $to_text, $text);
            } else {
                if (is_bool($instance)) {
                    if ($instance === false || Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_OPENOFFICE) {
                        return Excel_Error::Value();
                    }
                    $instance = 1;
                }
                $instance = Helpers::extract_int($instance, 1, 0, true);
                $return_value = self::execute_substitution($text, $from_text, $to_text, $instance);
            }
        } catch (Calc_Exp $e) {
            return $e->get_message();
        }
        if (String_Helper::count_characters($return_value) > Data_Type::MAX_STRING_LENGTH) {
            return Excel_Error::VALUE();
        }
        return $return_value;
    }
    private static function execute_substitution(string $text, string $from_text, string $to_text, int $instance): string
    {
        $pos = -1;
        while ($instance > 0) {
            $pos = mb_strpos($text, $from_text, $pos + 1, 'UTF-8');
            if ($pos === false) {
                return $text;
            }
            --$instance;
        }
        return String_Helper::convert_to_string(Functions::scalar(self::REPLACE($text, ++$pos, String_Helper::count_characters($from_text), $to_text)));
    }
}