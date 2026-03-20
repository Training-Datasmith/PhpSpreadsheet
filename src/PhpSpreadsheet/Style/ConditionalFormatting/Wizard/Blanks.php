<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
/**
 * @method Blanks notBlank()
 * @method Blanks notEmpty()
 * @method Blanks isBlank()
 * @method Blanks isEmpty()
 */
class Blanks extends Wizard_Abstract implements Wizard_Interface
{
    protected const OPERATORS = ['notBlank' => false, 'isBlank' => true, 'notEmpty' => false, 'empty' => true];
    protected const EXPRESSIONS = [Wizard::NOT_BLANKS => 'LEN(TRIM(%s))>0', Wizard::BLANKS => 'LEN(TRIM(%s))=0'];
    public function __construct(string $cell_range, protected bool $inverse = false)
    {
        parent::__construct($cell_range);
    }
    protected function inverse(bool $inverse): void
    {
        $this->inverse = $inverse;
    }
    protected function get_expression(): void
    {
        $this->expression = sprintf(self::EXPRESSIONS[$this->inverse ? Wizard::BLANKS : Wizard::NOT_BLANKS], $this->reference_cell);
    }
    public function get_conditional(): Conditional
    {
        $this->get_expression();
        $conditional = new Conditional();
        $conditional->set_condition_type($this->inverse ? Conditional::CONDITION_CONTAINSBLANKS : Conditional::CONDITION_NOTCONTAINSBLANKS);
        $conditional->set_conditions([$this->expression]);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if ($conditional->get_condition_type() !== Conditional::CONDITION_CONTAINSBLANKS && $conditional->get_condition_type() !== Conditional::CONDITION_NOTCONTAINSBLANKS) {
            throw new Exception('Conditional is not a Blanks CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        $wizard->inverse = $conditional->get_condition_type() === Conditional::CONDITION_CONTAINSBLANKS;
        return $wizard;
    }
    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if (!array_key_exists($method_name, self::OPERATORS)) {
            throw new Exception('Invalid Operation for Blanks CF Rule Wizard');
        }
        $this->inverse(self::OPERATORS[$method_name]);
        return $this;
    }
}