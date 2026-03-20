<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use DateTimeInterface;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Stringable;
class String_Value_Binder extends Default_Value_Binder implements I_Value_Binder
{
    protected bool $convert_null = true;
    protected bool $convert_boolean = true;
    protected bool $convert_numeric = true;
    protected bool $convert_formula = true;
    protected bool $set_ignored_errors = false;
    public function set_set_ignored_errors(bool $set_ignored_errors = false): self
    {
        $this->set_ignored_errors = $set_ignored_errors;
        return $this;
    }
    public function set_null_conversion(bool $suppress_conversion = false): self
    {
        $this->convert_null = $suppress_conversion;
        return $this;
    }
    public function set_boolean_conversion(bool $suppress_conversion = false): self
    {
        $this->convert_boolean = $suppress_conversion;
        return $this;
    }
    public function get_boolean_conversion(): bool
    {
        return $this->convert_boolean;
    }
    public function set_numeric_conversion(bool $suppress_conversion = false): self
    {
        $this->convert_numeric = $suppress_conversion;
        return $this;
    }
    public function set_formula_conversion(bool $suppress_conversion = false): self
    {
        $this->convert_formula = $suppress_conversion;
        return $this;
    }
    public function set_conversion_for_all_value_types(bool $suppress_conversion = false): self
    {
        $this->convert_null = $suppress_conversion;
        $this->convert_boolean = $suppress_conversion;
        $this->convert_numeric = $suppress_conversion;
        $this->convert_formula = $suppress_conversion;
        return $this;
    }
    /**
     * Bind value to a cell.
     *
     * @param Cell $cell Cell to bind value to
     * @param mixed $value Value to bind in cell
     */
    public function bind_value(Cell $cell, mixed $value): bool
    {
        if (is_object($value)) {
            return $this->bind_object_value($cell, $value);
        }
        if ($value !== null && !is_scalar($value)) {
            throw new Spreadsheet_Exception('Unable to bind unstringable ' . gettype($value));
        }
        // sanitize UTF-8 strings
        if (is_string($value)) {
            $value = String_Helper::sanitize_utf8($value);
        }
        $ignored_errors = false;
        if ($value === null && $this->convert_null === false) {
            $cell->set_value_explicit($value, Data_Type::TYPE_NULL);
        } elseif (is_bool($value) && $this->convert_boolean === false) {
            $cell->set_value_explicit($value, Data_Type::TYPE_BOOL);
        } elseif ((is_int($value) || is_float($value)) && $this->convert_numeric === false) {
            $cell->set_value_explicit($value, Data_Type::TYPE_NUMERIC);
        } elseif (is_string($value) && strlen($value) > 1 && $value[0] === '=' && $this->convert_formula === false && parent::data_type_for_value($value) === Data_Type::TYPE_FORMULA) {
            $cell->set_value_explicit($value, Data_Type::TYPE_FORMULA);
        } else {
            $ignored_errors = is_numeric($value);
            $cell->set_value_explicit((string) $value, Data_Type::TYPE_STRING);
        }
        if ($this->set_ignored_errors) {
            $cell->get_ignored_errors()->set_number_stored_as_text($ignored_errors);
        }
        return true;
    }
    protected function bind_object_value(Cell $cell, object $value): bool
    {
        // Handle any objects that might be injected
        $ignored_errors = false;
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
            $cell->set_value_explicit($value, Data_Type::TYPE_STRING);
        } elseif ($value instanceof Rich_Text) {
            $cell->set_value_explicit($value, Data_Type::TYPE_INLINE);
            $ignored_errors = is_numeric($value->get_plain_text());
        } elseif ($value instanceof Stringable) {
            $cell->set_value_explicit((string) $value, Data_Type::TYPE_STRING);
            $ignored_errors = is_numeric((string) $value);
        } else {
            throw new Spreadsheet_Exception('Unable to bind unstringable object of type ' . $value::class);
        }
        if ($this->set_ignored_errors) {
            $cell->get_ignored_errors()->set_number_stored_as_text($ignored_errors);
        }
        return true;
    }
}