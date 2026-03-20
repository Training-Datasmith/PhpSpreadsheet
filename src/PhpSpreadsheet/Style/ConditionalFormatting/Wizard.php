<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard\Wizard_Interface;
class Wizard
{
    public const CELL_VALUE = 'cellValue';
    public const TEXT_VALUE = 'textValue';
    public const BLANKS = Conditional::CONDITION_CONTAINSBLANKS;
    public const NOT_BLANKS = Conditional::CONDITION_NOTCONTAINSBLANKS;
    public const ERRORS = Conditional::CONDITION_CONTAINSERRORS;
    public const NOT_ERRORS = Conditional::CONDITION_NOTCONTAINSERRORS;
    public const EXPRESSION = Conditional::CONDITION_EXPRESSION;
    public const FORMULA = Conditional::CONDITION_EXPRESSION;
    public const DATES_OCCURRING = 'DateValue';
    public const DUPLICATES = Conditional::CONDITION_DUPLICATES;
    public const UNIQUE = Conditional::CONDITION_UNIQUE;
    public const VALUE_TYPE_LITERAL = 'value';
    public const VALUE_TYPE_CELL = 'cell';
    public const VALUE_TYPE_FORMULA = 'formula';
    public function __construct(protected string $cell_range)
    {
    }
    public function new_rule(string $rule_type): Wizard_Interface
    {
        return match ($rule_type) {
            self::CELL_VALUE => new Wizard\Cell_Value($this->cell_range),
            self::TEXT_VALUE => new Wizard\Text_Value($this->cell_range),
            self::BLANKS => new Wizard\Blanks($this->cell_range, true),
            self::NOT_BLANKS => new Wizard\Blanks($this->cell_range, false),
            self::ERRORS => new Wizard\Errors($this->cell_range, true),
            self::NOT_ERRORS => new Wizard\Errors($this->cell_range, false),
            self::EXPRESSION, self::FORMULA => new Wizard\Expression($this->cell_range),
            self::DATES_OCCURRING => new Wizard\Date_Value($this->cell_range),
            self::DUPLICATES => new Wizard\Duplicates($this->cell_range, false),
            self::UNIQUE => new Wizard\Duplicates($this->cell_range, true),
            default => throw new Exception('No wizard exists for this CF rule type'),
        };
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        $conditional_type = $conditional->get_condition_type();
        return match ($conditional_type) {
            Conditional::CONDITION_CELLIS => Wizard\Cell_Value::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_CONTAINSTEXT, Conditional::CONDITION_NOTCONTAINSTEXT, Conditional::CONDITION_BEGINSWITH, Conditional::CONDITION_ENDSWITH => Wizard\Text_Value::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_CONTAINSBLANKS, Conditional::CONDITION_NOTCONTAINSBLANKS => Wizard\Blanks::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_CONTAINSERRORS, Conditional::CONDITION_NOTCONTAINSERRORS => Wizard\Errors::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_TIMEPERIOD => Wizard\Date_Value::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_EXPRESSION => Wizard\Expression::from_conditional($conditional, $cell_range),
            Conditional::CONDITION_DUPLICATES, Conditional::CONDITION_UNIQUE => Wizard\Duplicates::from_conditional($conditional, $cell_range),
            default => throw new Exception('No wizard exists for this CF rule type'),
        };
    }
}