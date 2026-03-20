<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
/**
 * @method Errors duplicates()
 * @method Errors unique()
 */
class Duplicates extends Wizard_Abstract implements Wizard_Interface
{
    protected const OPERATORS = ['duplicates' => false, 'unique' => true];
    public function __construct(string $cell_range, protected bool $inverse = false)
    {
        parent::__construct($cell_range);
    }
    protected function inverse(bool $inverse): void
    {
        $this->inverse = $inverse;
    }
    public function get_conditional(): Conditional
    {
        $conditional = new Conditional();
        $conditional->set_condition_type($this->inverse ? Conditional::CONDITION_UNIQUE : Conditional::CONDITION_DUPLICATES);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if ($conditional->get_condition_type() !== Conditional::CONDITION_DUPLICATES && $conditional->get_condition_type() !== Conditional::CONDITION_UNIQUE) {
            throw new Exception('Conditional is not a Duplicates CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        $wizard->inverse = $conditional->get_condition_type() === Conditional::CONDITION_UNIQUE;
        return $wizard;
    }
    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if (!array_key_exists($method_name, self::OPERATORS)) {
            throw new Exception('Invalid Operation for Errors CF Rule Wizard');
        }
        $this->inverse(self::OPERATORS[$method_name]);
        return $this;
    }
}