<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Cell_Matcher
{
    public const COMPARISON_OPERATORS = [Conditional::OPERATOR_EQUAL => '=', Conditional::OPERATOR_GREATERTHAN => '>', Conditional::OPERATOR_GREATERTHANOREQUAL => '>=', Conditional::OPERATOR_LESSTHAN => '<', Conditional::OPERATOR_LESSTHANOREQUAL => '<=', Conditional::OPERATOR_NOTEQUAL => '<>'];
    public const COMPARISON_RANGE_OPERATORS = [Conditional::OPERATOR_BETWEEN => 'IF(AND(A1>=%s,A1<=%s),TRUE,FALSE)', Conditional::OPERATOR_NOTBETWEEN => 'IF(AND(A1>=%s,A1<=%s),FALSE,TRUE)'];
    public const COMPARISON_DUPLICATES_OPERATORS = [Conditional::CONDITION_DUPLICATES => "COUNTIF('%s'!%s,%s)>1", Conditional::CONDITION_UNIQUE => "COUNTIF('%s'!%s,%s)=1"];
    protected int $cell_row;
    protected Worksheet $worksheet;
    protected int $cell_column;
    protected string $conditional_range;
    protected string $reference_cell;
    protected int $reference_row;
    protected int $reference_column;
    protected Calculation $engine;
    public function __construct(protected Cell $cell, string $conditional_range)
    {
        $this->worksheet = $this->cell->get_worksheet();
        [$this->cell_column, $this->cell_row] = Coordinate::indexes_from_string($this->cell->get_coordinate());
        $this->set_reference_cell_for_expressions($conditional_range);
        $this->engine = Calculation::get_instance($this->worksheet->get_parent());
    }
    protected function set_reference_cell_for_expressions(string $conditional_range): void
    {
        $conditional_range = Coordinate::split_range(str_replace('$', '', strtoupper($conditional_range)));
        [$this->reference_cell] = $conditional_range[0];
        [$this->reference_column, $this->reference_row] = Coordinate::indexes_from_string($this->reference_cell);
        // Convert our conditional range to an absolute conditional range, so it can be used  "pinned" in formulae
        $range_sets = [];
        foreach ($conditional_range as $range_set) {
            $absolute_range_set = array_map(Coordinate::absolute_coordinate(...), $range_set);
            $range_sets[] = implode(':', $absolute_range_set);
        }
        $this->conditional_range = implode(',', $range_sets);
    }
    public function evaluate_conditional(Conditional $conditional): bool
    {
        // Some calculations may modify the stored cell; so reset it before every evaluation.
        $cell_column = Coordinate::string_from_column_index($this->cell_column);
        $cell_address = "{$cell_column}{$this->cell_row}";
        $this->cell = $this->worksheet->get_cell($cell_address);
        return match ($conditional->get_condition_type()) {
            Conditional::CONDITION_CELLIS => $this->process_operator_comparison($conditional),
            Conditional::CONDITION_DUPLICATES, Conditional::CONDITION_UNIQUE => $this->process_duplicates_comparison($conditional),
            // Expression is NOT(ISERROR(SEARCH("<TEXT>",<Cell Reference>)))
            Conditional::CONDITION_CONTAINSTEXT, Conditional::CONDITION_NOTCONTAINSTEXT, Conditional::CONDITION_BEGINSWITH, Conditional::CONDITION_ENDSWITH, Conditional::CONDITION_CONTAINSBLANKS, Conditional::CONDITION_NOTCONTAINSBLANKS, Conditional::CONDITION_CONTAINSERRORS, Conditional::CONDITION_NOTCONTAINSERRORS, Conditional::CONDITION_TIMEPERIOD, Conditional::CONDITION_EXPRESSION => $this->process_expression($conditional),
            Conditional::CONDITION_COLORSCALE => $this->process_color_scale($conditional),
            default => false,
        };
    }
    protected function wrap_value(mixed $value): float|int|string
    {
        if (!is_numeric($value)) {
            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }
            if ($value === null) {
                return 'NULL';
            }
            return '"' . String_Helper::convert_to_string($value) . '"';
        }
        return $value;
    }
    protected function wrap_cell_value(): float|int|string
    {
        $this->cell = $this->worksheet->get_cell([$this->cell_column, $this->cell_row]);
        return $this->wrap_value($this->cell->get_calculated_value());
    }
    /** @param string[] $matches */
    protected function condition_cell_adjustment(array $matches): float|int|string
    {
        $column = $matches[6];
        $row = $matches[7];
        if (!str_contains($column, '$')) {
            //            $column = Coordinate::stringFromColumnIndex($this->cellColumn);
            $column = Coordinate::column_index_from_string($column);
            $column += $this->cell_column - $this->reference_column;
            $column = Coordinate::string_from_column_index($column);
        }
        if (!str_contains($row, '$')) {
            $row = (int) $row + $this->cell_row - $this->reference_row;
        }
        if (!empty($matches[4])) {
            $worksheet = $this->worksheet->get_parent_or_throw()->get_sheet_by_name(trim($matches[4], "'"));
            if ($worksheet === null) {
                return $this->wrap_value(null);
            }
            return $this->wrap_value($worksheet->get_cell(str_replace('$', '', "{$column}{$row}"))->get_calculated_value());
        }
        return $this->wrap_value($this->worksheet->get_cell(str_replace('$', '', "{$column}{$row}"))->get_calculated_value());
    }
    protected function cell_condition_check(string $condition): string
    {
        $split_condition = explode(Calculation::FORMULA_STRING_QUOTE, $condition);
        $i = false;
        foreach ($split_condition as &$value) {
            //    Only count/replace in alternating array entries (ie. not in quoted strings)
            $i = $i === false;
            if ($i) {
                $value = (string) preg_replace_callback('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/i', $this->condition_cell_adjustment(...), $value);
            }
        }
        unset($value);
        //    Then rebuild the condition string to return it
        return implode(Calculation::FORMULA_STRING_QUOTE, $split_condition);
    }
    /**
     * @param mixed[] $conditions
     *
     * @return mixed[]
     */
    protected function adjust_conditions_for_cell_references(array $conditions): array
    {
        return array_map($this->cell_condition_check(...), $conditions);
    }
    protected function process_operator_comparison(Conditional $conditional): bool
    {
        if (array_key_exists($conditional->get_operator_type(), self::COMPARISON_RANGE_OPERATORS)) {
            return $this->process_range_operator($conditional);
        }
        $operator = self::COMPARISON_OPERATORS[$conditional->get_operator_type()];
        $conditions = $this->adjust_conditions_for_cell_references($conditional->get_conditions());
        $temp1 = $this->wrap_cell_value();
        /** @var scalar */
        $temp2 = array_pop($conditions);
        $expression = sprintf('%s%s%s', (string) $temp1, $operator, (string) $temp2);
        return $this->evaluate_expression($expression);
    }
    protected function process_color_scale(Conditional $conditional): bool
    {
        if (is_numeric($this->wrap_cell_value()) && $conditional->get_color_scale()?->color_scale_ready_for_use()) {
            return true;
        }
        return false;
    }
    protected function process_range_operator(Conditional $conditional): bool
    {
        $conditions = $this->adjust_conditions_for_cell_references($conditional->get_conditions());
        sort($conditions);
        $expression = sprintf((string) preg_replace('/\bA1\b/i', (string) $this->wrap_cell_value(), self::COMPARISON_RANGE_OPERATORS[$conditional->get_operator_type()]), ...$conditions);
        return $this->evaluate_expression($expression);
    }
    protected function process_duplicates_comparison(Conditional $conditional): bool
    {
        $worksheet_name = $this->cell->get_worksheet()->get_title();
        $expression = sprintf(self::COMPARISON_DUPLICATES_OPERATORS[$conditional->get_condition_type()], $worksheet_name, $this->conditional_range, $this->cell_condition_check($this->cell->get_calculated_value_string()));
        return $this->evaluate_expression($expression);
    }
    protected function process_expression(Conditional $conditional): bool
    {
        $conditions = $this->adjust_conditions_for_cell_references($conditional->get_conditions());
        /** @var string */
        $expression = array_pop($conditions);
        $temp = $this->wrap_cell_value();
        $expression = (string) preg_replace('/\b' . $this->reference_cell . '\b/i', (string) $temp, $expression);
        return $this->evaluate_expression($expression);
    }
    protected function evaluate_expression(string $expression): bool
    {
        $expression = "={$expression}";
        try {
            $this->engine->flush_instance();
            $result = (bool) $this->engine->calculate_formula($expression);
        } catch (Exception) {
            return false;
        }
        return $result;
    }
}