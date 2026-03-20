<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Cell_Matcher;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
/**
 * @method CellValue equals($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue notEquals($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue greaterThan($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue greaterThanOrEqual($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue lessThan($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue lessThanOrEqual($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue between($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue notBetween($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method CellValue and($value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 */
class Cell_Value extends Wizard_Abstract implements Wizard_Interface
{
    protected const MAGIC_OPERATIONS = ['equals' => Conditional::OPERATOR_EQUAL, 'notEquals' => Conditional::OPERATOR_NOTEQUAL, 'greaterThan' => Conditional::OPERATOR_GREATERTHAN, 'greaterThanOrEqual' => Conditional::OPERATOR_GREATERTHANOREQUAL, 'lessThan' => Conditional::OPERATOR_LESSTHAN, 'lessThanOrEqual' => Conditional::OPERATOR_LESSTHANOREQUAL, 'between' => Conditional::OPERATOR_BETWEEN, 'notBetween' => Conditional::OPERATOR_NOTBETWEEN];
    protected const SINGLE_OPERATORS = Cell_Matcher::COMPARISON_OPERATORS;
    protected const RANGE_OPERATORS = Cell_Matcher::COMPARISON_RANGE_OPERATORS;
    protected string $operator = Conditional::OPERATOR_EQUAL;
    /** @var array<int|string> */
    protected array $operand = [0];
    /**
     * @var string[]
     */
    protected array $operand_value_type = [];
    protected function operator(string $operator): void
    {
        if (!isset(self::SINGLE_OPERATORS[$operator]) && !isset(self::RANGE_OPERATORS[$operator])) {
            throw new Exception('Invalid Operator for Cell Value CF Rule Wizard');
        }
        $this->operator = $operator;
    }
    protected function operand(int $index, mixed $operand, string $operand_value_type = Wizard::VALUE_TYPE_LITERAL): void
    {
        if (is_string($operand)) {
            $operand = $this->validate_operand($operand, $operand_value_type);
        }
        $this->operand[$index] = $operand;
        //* @phpstan-ignore-line
        $this->operand_value_type[$index] = $operand_value_type;
    }
    /** @param null|bool|float|int|string $value value to be wrapped */
    protected function wrap_value(mixed $value, string $operand_value_type): float|int|string
    {
        if (!is_numeric($value) && !is_bool($value) && null !== $value) {
            if ($operand_value_type === Wizard::VALUE_TYPE_LITERAL) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $this->cell_condition_check($value);
        }
        if (null === $value) {
            $value = 'NULL';
        } elseif (is_bool($value)) {
            $value = $value ? 'TRUE' : 'FALSE';
        }
        return $value;
    }
    public function get_conditional(): Conditional
    {
        if (!isset(self::RANGE_OPERATORS[$this->operator])) {
            unset($this->operand[1], $this->operand_value_type[1]);
        }
        $values = array_map($this->wrap_value(...), $this->operand, $this->operand_value_type);
        $conditional = new Conditional();
        $conditional->set_condition_type(Conditional::CONDITION_CELLIS);
        $conditional->set_operator_type($this->operator);
        $conditional->set_conditions($values);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    protected static function unwrap_string(string $condition): string
    {
        if (str_starts_with($condition, '"') && str_starts_with(strrev($condition), '"')) {
            $condition = substr($condition, 1, -1);
        }
        return str_replace('""', '"', $condition);
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if ($conditional->get_condition_type() !== Conditional::CONDITION_CELLIS) {
            throw new Exception('Conditional is not a Cell Value CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        $wizard->operator = $conditional->get_operator_type();
        $conditions = $conditional->get_conditions();
        foreach ($conditions as $index => $condition) {
            // Best-guess to try and identify if the text is a string literal, a cell reference or a formula?
            $operand_value_type = Wizard::VALUE_TYPE_LITERAL;
            if (is_string($condition)) {
                if (Calculation::key_in_excel_constants($condition)) {
                    $condition = Calculation::get_excel_constants($condition);
                } elseif (preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '$/i', $condition)) {
                    $operand_value_type = Wizard::VALUE_TYPE_CELL;
                    $condition = self::reverse_adjust_cell_ref($condition, $cell_range);
                } elseif (preg_match('/\(\)/', $condition) || preg_match('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/i', $condition)) {
                    $operand_value_type = Wizard::VALUE_TYPE_FORMULA;
                    $condition = self::reverse_adjust_cell_ref($condition, $cell_range);
                } else {
                    $condition = self::unwrap_string($condition);
                }
            }
            $wizard->operand($index, $condition, $operand_value_type);
        }
        return $wizard;
    }
    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if (!isset(self::MAGIC_OPERATIONS[$method_name]) && $method_name !== 'and') {
            throw new Exception('Invalid Operator for Cell Value CF Rule Wizard');
        }
        if ($method_name === 'and') {
            if (!isset(self::RANGE_OPERATORS[$this->operator])) {
                throw new Exception('AND Value is only appropriate for range operators');
            }
            $this->operand(1, ...$arguments);
            return $this;
        }
        $this->operator(self::MAGIC_OPERATIONS[$method_name]);
        //$this->operand(0, ...$arguments);
        if (count($arguments) < 2) {
            $this->operand(0, $arguments[0]);
        } else {
            /** @var string */
            $arg1 = $arguments[1];
            $this->operand(0, $arguments[0], $arg1);
        }
        return $this;
    }
}