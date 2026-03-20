<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
/**
 * @method TextValue contains(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method TextValue doesNotContain(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method TextValue doesntContain(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method TextValue beginsWith(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method TextValue startsWith(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 * @method TextValue endsWith(string $value, string $operandValueType = Wizard::VALUE_TYPE_LITERAL)
 */
class Text_Value extends Wizard_Abstract implements Wizard_Interface
{
    protected const MAGIC_OPERATIONS = ['contains' => Conditional::OPERATOR_CONTAINSTEXT, 'doesntContain' => Conditional::OPERATOR_NOTCONTAINS, 'doesNotContain' => Conditional::OPERATOR_NOTCONTAINS, 'beginsWith' => Conditional::OPERATOR_BEGINSWITH, 'startsWith' => Conditional::OPERATOR_BEGINSWITH, 'endsWith' => Conditional::OPERATOR_ENDSWITH];
    protected const OPERATORS = [Conditional::OPERATOR_CONTAINSTEXT => Conditional::CONDITION_CONTAINSTEXT, Conditional::OPERATOR_NOTCONTAINS => Conditional::CONDITION_NOTCONTAINSTEXT, Conditional::OPERATOR_BEGINSWITH => Conditional::CONDITION_BEGINSWITH, Conditional::OPERATOR_ENDSWITH => Conditional::CONDITION_ENDSWITH];
    protected const EXPRESSIONS = [Conditional::OPERATOR_CONTAINSTEXT => 'NOT(ISERROR(SEARCH(%s,%s)))', Conditional::OPERATOR_NOTCONTAINS => 'ISERROR(SEARCH(%s,%s))', Conditional::OPERATOR_BEGINSWITH => 'LEFT(%s,LEN(%s))=%s', Conditional::OPERATOR_ENDSWITH => 'RIGHT(%s,LEN(%s))=%s'];
    protected string $operator;
    protected string $operand;
    protected string $operand_value_type;
    protected function operator(string $operator): void
    {
        if (!isset(self::OPERATORS[$operator])) {
            throw new Exception('Invalid Operator for Text Value CF Rule Wizard');
        }
        $this->operator = $operator;
    }
    protected function operand(string $operand, string $operand_value_type = Wizard::VALUE_TYPE_LITERAL): void
    {
        $operand = $this->validate_operand($operand, $operand_value_type);
        $this->operand = $operand;
        $this->operand_value_type = $operand_value_type;
    }
    protected function wrap_value(string $value): string
    {
        return '"' . $value . '"';
    }
    protected function set_expression(): void
    {
        $operand = $this->operand_value_type === Wizard::VALUE_TYPE_LITERAL ? $this->wrap_value(str_replace('"', '""', $this->operand)) : $this->cell_condition_check($this->operand);
        if ($this->operator === Conditional::OPERATOR_CONTAINSTEXT || $this->operator === Conditional::OPERATOR_NOTCONTAINS) {
            $this->expression = sprintf(self::EXPRESSIONS[$this->operator], $operand, $this->reference_cell);
        } else {
            $this->expression = sprintf(self::EXPRESSIONS[$this->operator], $this->reference_cell, $operand, $operand);
        }
    }
    public function get_conditional(): Conditional
    {
        $this->set_expression();
        $conditional = new Conditional();
        $conditional->set_condition_type(self::OPERATORS[$this->operator]);
        $conditional->set_operator_type($this->operator);
        $conditional->set_text($this->operand_value_type !== Wizard::VALUE_TYPE_LITERAL ? $this->cell_condition_check($this->operand) : $this->operand);
        $conditional->set_conditions([$this->expression]);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if (!in_array($conditional->get_condition_type(), self::OPERATORS, true)) {
            throw new Exception('Conditional is not a Text Value CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->operator = (string) array_search($conditional->get_condition_type(), self::OPERATORS, true);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        // Best-guess to try and identify if the text is a string literal, a cell reference or a formula?
        $wizard->operand_value_type = Wizard::VALUE_TYPE_LITERAL;
        $condition = $conditional->get_text();
        if (preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '$/i', $condition)) {
            $wizard->operand_value_type = Wizard::VALUE_TYPE_CELL;
            $condition = self::reverse_adjust_cell_ref($condition, $cell_range);
        } elseif (preg_match('/\(\)/', $condition) || preg_match('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/i', $condition)) {
            $wizard->operand_value_type = Wizard::VALUE_TYPE_FORMULA;
        }
        $wizard->operand = $condition;
        return $wizard;
    }
    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if (!isset(self::MAGIC_OPERATIONS[$method_name])) {
            throw new Exception('Invalid Operation for Text Value CF Rule Wizard');
        }
        $this->operator(self::MAGIC_OPERATIONS[$method_name]);
        //$this->operand(...$arguments);
        if (count($arguments) < 2) {
            /** @var string */
            $arg0 = $arguments[0];
            $this->operand($arg0);
        } else {
            /** @var string */
            $arg0 = $arguments[0];
            /** @var string */
            $arg1 = $arguments[1];
            $this->operand($arg0, $arg1);
        }
        return $this;
    }
}