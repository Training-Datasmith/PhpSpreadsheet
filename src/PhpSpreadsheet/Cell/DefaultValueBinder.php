<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Composer\Pcre\Preg;
use DateTimeInterface;
use Php_Office\Php_Spreadsheet\Calculation\Calculation_Parser_Only;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalculationException;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Stringable;
class Default_Value_Binder implements I_Value_Binder
{
    //                            123 456 789 012 345
    private const FIFTEEN_NINES = 999999999999999;
    /**
     * Bind value to a cell.
     *
     * @param Cell $cell Cell to bind value to
     * @param mixed $value Value to bind in cell
     */
    public function bind_value(Cell $cell, mixed $value): bool
    {
        // sanitize UTF-8 strings
        if (is_string($value)) {
            $value = String_Helper::sanitize_utf8($value);
        } elseif ($value === null || is_scalar($value) || $value instanceof Rich_Text) {
            // No need to do anything
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif ($value instanceof Stringable) {
            $value = (string) $value;
        } elseif ($value instanceof Base_Drawing) {
            $value->set_coordinates($cell->get_coordinate());
            $value->set_resize_proportional(false);
            $value->set_in_cell(true);
            $value->set_worksheet($cell->get_worksheet(), true);
        } else {
            throw new Spreadsheet_Exception('Unable to bind unstringable ' . gettype($value));
        }
        // Set value explicit
        $cell->set_value_explicit($value, static::data_type_for_value($value));
        // Done!
        return true;
    }
    /**
     * DataType for value.
     */
    public static function data_type_for_value(mixed $value): string
    {
        // Match the value against a few data types
        if ($value === null) {
            return Data_Type::TYPE_NULL;
        }
        if (is_int($value) && abs($value) > self::FIFTEEN_NINES) {
            return Data_Type::TYPE_STRING;
        }
        if (is_float($value) || is_int($value)) {
            return Data_Type::TYPE_NUMERIC;
        }
        if (is_bool($value)) {
            return Data_Type::TYPE_BOOL;
        }
        if ($value === '') {
            return Data_Type::TYPE_STRING;
        }
        if ($value instanceof Rich_Text) {
            return Data_Type::TYPE_INLINE;
        }
        if ($value instanceof Base_Drawing) {
            return Data_Type::TYPE_DRAWING_IN_CELL;
        }
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $gettype = get_debug_type($value);
            throw new Spreadsheet_Exception("unusable type {$gettype}");
        }
        if (strlen($value) > 1 && $value[0] === '=') {
            $calculation = Calculation_Parser_Only::get_parser_instance();
            try {
                if (empty($calculation->parse_formula($value))) {
                    return Data_Type::TYPE_STRING;
                }
            } catch (Calculation_Exception $e) {
                $message = $e->get_message();
                if ($message === 'Formula Error: An unexpected error occurred' || str_contains($message, 'has no operands')) {
                    return Data_Type::TYPE_STRING;
                }
            }
            return Data_Type::TYPE_FORMULA;
        }
        if (Preg::is_match('/^[\+\-]?(\d+\.?\d*|\d*\.?\d+)([Ee][\-\+]?[0-2]?\d{1,3})?$/', $value)) {
            $t_value = ltrim($value, '+-');
            if (strlen($t_value) > 1 && $t_value[0] === '0' && $t_value[1] !== '.') {
                return Data_Type::TYPE_STRING;
            }
            if (!Preg::is_match('/[eE.]/', $value)) {
                $a_value = abs((float) $value);
                if ($a_value > self::FIFTEEN_NINES) {
                    return Data_Type::TYPE_STRING;
                }
            }
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return Data_Type::TYPE_STRING;
            }
            return Data_Type::TYPE_NUMERIC;
        }
        $error_codes = Data_Type::get_error_codes();
        if (isset($error_codes[$value])) {
            return Data_Type::TYPE_ERROR;
        }
        return Data_Type::TYPE_STRING;
    }
    protected bool $preserve_cr = false;
    public function get_preserve_cr(): bool
    {
        return $this->preserve_cr;
    }
    public function set_preserve_cr(bool $preserve_cr): self
    {
        $this->preserve_cr = $preserve_cr;
        return $this;
    }
}