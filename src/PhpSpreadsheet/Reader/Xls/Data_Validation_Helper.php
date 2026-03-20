<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Reader\Xls;
class Data_Validation_Helper extends Xls
{
    /**
     * @var array<int, string>
     */
    private static array $types = [0x0 => Data_Validation::TYPE_NONE, 0x1 => Data_Validation::TYPE_WHOLE, 0x2 => Data_Validation::TYPE_DECIMAL, 0x3 => Data_Validation::TYPE_LIST, 0x4 => Data_Validation::TYPE_DATE, 0x5 => Data_Validation::TYPE_TIME, 0x6 => Data_Validation::TYPE_TEXTLENGTH, 0x7 => Data_Validation::TYPE_CUSTOM];
    /**
     * @var array<int, string>
     */
    private static array $error_styles = [0x0 => Data_Validation::STYLE_STOP, 0x1 => Data_Validation::STYLE_WARNING, 0x2 => Data_Validation::STYLE_INFORMATION];
    /**
     * @var array<int, string>
     */
    private static array $operators = [0x0 => Data_Validation::OPERATOR_BETWEEN, 0x1 => Data_Validation::OPERATOR_NOTBETWEEN, 0x2 => Data_Validation::OPERATOR_EQUAL, 0x3 => Data_Validation::OPERATOR_NOTEQUAL, 0x4 => Data_Validation::OPERATOR_GREATERTHAN, 0x5 => Data_Validation::OPERATOR_LESSTHAN, 0x6 => Data_Validation::OPERATOR_GREATERTHANOREQUAL, 0x7 => Data_Validation::OPERATOR_LESSTHANOREQUAL];
    public static function type(int $type): ?string
    {
        return self::$types[$type] ?? null;
    }
    public static function error_style(int $error_style): ?string
    {
        return self::$error_styles[$error_style] ?? null;
    }
    public static function operator(int $operator): ?string
    {
        return self::$operators[$operator] ?? null;
    }
    /**
     * Read DATAVALIDATION record.
     */
    protected function read_data_validation2(Xls $xls): void
    {
        $length = self::get_u_int2d($xls->data, $xls->pos + 2);
        $record_data = $xls->read_record_data($xls->data, $xls->pos + 4, $length);
        // move stream pointer forward to next record
        $xls->pos += 4 + $length;
        if ($xls->read_data_only) {
            return;
        }
        // offset: 0; size: 4; Options
        $options = self::get_int4d($record_data, 0);
        // bit: 0-3; mask: 0x0000000F; type
        $type = (0xf & $options) >> 0;
        $type = self::type($type);
        // bit: 4-6; mask: 0x00000070; error type
        $error_style = (0x70 & $options) >> 4;
        $error_style = self::error_style($error_style);
        // bit: 7; mask: 0x00000080; 1= formula is explicit (only applies to list)
        // I have only seen cases where this is 1
        //$explicitFormula = (0x00000080 & $options) >> 7;
        // bit: 8; mask: 0x00000100; 1= empty cells allowed
        $allow_blank = (0x100 & $options) >> 8;
        // bit: 9; mask: 0x00000200; 1= suppress drop down arrow in list type validity
        $suppress_drop_down = (0x200 & $options) >> 9;
        // bit: 18; mask: 0x00040000; 1= show prompt box if cell selected
        $show_input_message = (0x40000 & $options) >> 18;
        // bit: 19; mask: 0x00080000; 1= show error box if invalid values entered
        $show_error_message = (0x80000 & $options) >> 19;
        // bit: 20-23; mask: 0x00F00000; condition operator
        $operator = (0xf00000 & $options) >> 20;
        $operator = self::operator($operator);
        if ($type === null || $error_style === null || $operator === null) {
            return;
        }
        // offset: 4; size: var; title of the prompt box
        $offset = 4;
        $string = self::read_unicode_string_long(substr($record_data, $offset));
        $prompt_title = $string['value'] !== chr(0) ? $string['value'] : '';
        $offset += $string['size'];
        // offset: var; size: var; title of the error box
        $string = self::read_unicode_string_long(substr($record_data, $offset));
        $error_title = $string['value'] !== chr(0) ? $string['value'] : '';
        $offset += $string['size'];
        // offset: var; size: var; text of the prompt box
        $string = self::read_unicode_string_long(substr($record_data, $offset));
        $prompt = $string['value'] !== chr(0) ? $string['value'] : '';
        $offset += $string['size'];
        // offset: var; size: var; text of the error box
        $string = self::read_unicode_string_long(substr($record_data, $offset));
        $error = $string['value'] !== chr(0) ? $string['value'] : '';
        $offset += $string['size'];
        // offset: var; size: 2; size of the formula data for the first condition
        $sz1 = self::get_u_int2d($record_data, $offset);
        $offset += 2;
        // offset: var; size: 2; not used
        $offset += 2;
        // offset: var; size: $sz1; formula data for first condition (without size field)
        $formula1 = substr($record_data, $offset, $sz1);
        $formula1 = pack('v', $sz1) . $formula1;
        // prepend the length
        try {
            $formula1 = $xls->get_formula_from_structure($formula1);
            // in list type validity, null characters are used as item separators
            if ($type == Data_Validation::TYPE_LIST) {
                $formula1 = str_replace(chr(0), ',', $formula1);
            }
        } catch (Php_Spreadsheet_Exception) {
            return;
        }
        $offset += $sz1;
        // offset: var; size: 2; size of the formula data for the first condition
        $sz2 = self::get_u_int2d($record_data, $offset);
        $offset += 2;
        // offset: var; size: 2; not used
        $offset += 2;
        // offset: var; size: $sz2; formula data for second condition (without size field)
        $formula2 = substr($record_data, $offset, $sz2);
        $formula2 = pack('v', $sz2) . $formula2;
        // prepend the length
        try {
            $formula2 = $xls->get_formula_from_structure($formula2);
        } catch (Php_Spreadsheet_Exception) {
            return;
        }
        $offset += $sz2;
        // offset: var; size: var; cell range address list with
        $cell_range_address_list = Biff8::read_biff8cell_range_address_list(substr($record_data, $offset));
        /** @var string[] */
        $cell_range_addresses = $cell_range_address_list['cellRangeAddresses'];
        $max_row = (string) Address_Range::MAX_ROW;
        $max_col = Address_Range::MAX_COLUMN;
        $max_xls_row = (string) Address_Range::MAX_ROW_XLS;
        $max_xls_column_string = Address_Range::MAX_COLUMN_XLS;
        foreach ($cell_range_addresses as $cell_range) {
            $cell_range = preg_replace(["/([a-z]+)1:([a-z]+){$max_xls_row}/i", "/([a-z]+\\d+):([a-z]+){$max_xls_row}/i", "/A(\\d+):{$max_xls_column_string}(\\d+)/i", "/([a-z]+\\d+):{$max_xls_column_string}(\\d+)/i"], ['$1:$2', '$1:${2}' . $max_row, '$1:$2', '$1:' . $max_col . '$2'], $cell_range) ?? $cell_range;
            $obj_validation = new Data_Validation();
            $obj_validation->set_type($type);
            $obj_validation->set_error_style($error_style);
            $obj_validation->set_allow_blank((bool) $allow_blank);
            $obj_validation->set_show_input_message((bool) $show_input_message);
            $obj_validation->set_show_error_message((bool) $show_error_message);
            $obj_validation->set_show_drop_down(!$suppress_drop_down);
            $obj_validation->set_operator($operator);
            $obj_validation->set_error_title($error_title);
            $obj_validation->set_error($error);
            $obj_validation->set_prompt_title($prompt_title);
            $obj_validation->set_prompt($prompt);
            $obj_validation->set_formula1($formula1);
            $obj_validation->set_formula2($formula2);
            $xls->php_sheet->set_data_validation($cell_range, $obj_validation);
        }
    }
}