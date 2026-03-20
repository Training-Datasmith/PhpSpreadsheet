<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
/**
 * @method Expression formula(string $expression)
 */
class Expression extends Wizard_Abstract implements Wizard_Interface
{
    protected string $expression;
    public function expression(string $expression): self
    {
        $expression = $this->validate_operand($expression, Wizard::VALUE_TYPE_FORMULA);
        $this->expression = $expression;
        return $this;
    }
    public function get_conditional(): Conditional
    {
        /** @var string[] */
        $expression = $this->adjust_conditions_for_cell_references([$this->expression]);
        $conditional = new Conditional();
        $conditional->set_condition_type(Conditional::CONDITION_EXPRESSION);
        $conditional->set_conditions($expression);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if ($conditional->get_condition_type() !== Conditional::CONDITION_EXPRESSION) {
            throw new Exception('Conditional is not an Expression CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        $wizard->expression = self::reverse_adjust_cell_ref((string) $conditional->get_conditions()[0], $cell_range);
        return $wizard;
    }
    /**
     * @param string[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if ($method_name !== 'formula') {
            throw new Exception('Invalid Operation for Expression CF Rule Wizard');
        }
        $this->expression(...$arguments);
        return $this;
    }
}