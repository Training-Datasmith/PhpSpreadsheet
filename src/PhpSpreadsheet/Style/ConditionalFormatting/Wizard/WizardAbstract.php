<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
use Php_Office\Php_Spreadsheet\Style\Style;
abstract class Wizard_Abstract
{
    protected ?Style $style = null;
    protected string $expression;
    protected string $cell_range;
    protected string $reference_cell;
    protected int $reference_row;
    protected bool $stop_if_true = false;
    protected int $reference_column;
    public function __construct(string $cell_range)
    {
        $this->set_cell_range($cell_range);
    }
    public function get_cell_range(): string
    {
        return $this->cell_range;
    }
    public function set_cell_range(string $cell_range): void
    {
        $this->cell_range = $cell_range;
        $this->set_reference_cell_for_expressions($cell_range);
    }
    protected function set_reference_cell_for_expressions(string $conditional_range): void
    {
        $conditional_range = Coordinate::split_range(str_replace('$', '', strtoupper($conditional_range)));
        [$this->reference_cell] = $conditional_range[0];
        [$this->reference_column, $this->reference_row] = Coordinate::indexes_from_string($this->reference_cell);
    }
    public function get_stop_if_true(): bool
    {
        return $this->stop_if_true;
    }
    public function set_stop_if_true(bool $stop_if_true): void
    {
        $this->stop_if_true = $stop_if_true;
    }
    public function get_style(): Style
    {
        return $this->style ?? new Style(false, true);
    }
    public function set_style(Style $style): void
    {
        $this->style = $style;
    }
    protected function validate_operand(string $operand, string $operand_value_type = Wizard::VALUE_TYPE_LITERAL): string
    {
        if ($operand_value_type === Wizard::VALUE_TYPE_LITERAL && str_starts_with($operand, '"') && str_ends_with($operand, '"')) {
            $operand = str_replace('""', '"', substr($operand, 1, -1));
        } elseif ($operand_value_type === Wizard::VALUE_TYPE_FORMULA && str_starts_with($operand, '=')) {
            $operand = substr($operand, 1);
        }
        return $operand;
    }
    /** @param string[] $matches */
    protected static function reverse_cell_adjustment(array $matches, int $reference_column, int $reference_row): string
    {
        $worksheet = $matches[1];
        $column = $matches[6];
        $row = $matches[7];
        if (!str_contains($column, '$')) {
            $column = Coordinate::column_index_from_string($column);
            $column -= $reference_column - 1;
            $column = Coordinate::string_from_column_index($column);
        }
        if (!str_contains($row, '$')) {
            $row = (int) $row - ($reference_row - 1);
        }
        return "{$worksheet}{$column}{$row}";
    }
    public static function reverse_adjust_cell_ref(string $condition, string $cell_range): string
    {
        $conditional_range = Coordinate::split_range(str_replace('$', '', strtoupper($cell_range)));
        [$reference_cell] = $conditional_range[0];
        [$reference_column_index, $reference_row] = Coordinate::indexes_from_string($reference_cell);
        $split_condition = explode(Calculation::FORMULA_STRING_QUOTE, $condition);
        $i = false;
        foreach ($split_condition as &$value) {
            //    Only count/replace in alternating array entries (ie. not in quoted strings)
            $i = $i === false;
            if ($i) {
                $value = (string) preg_replace_callback('/' . Calculation::CALCULATION_REGEXP_CELLREF_RELATIVE . '/i', fn(array $matches): string => self::reverse_cell_adjustment($matches, $reference_column_index, $reference_row), $value);
            }
        }
        unset($value);
        //    Then rebuild the condition string to return it
        return implode(Calculation::FORMULA_STRING_QUOTE, $split_condition);
    }
    /** @param string[] $matches */
    protected function condition_cell_adjustment(array $matches): string
    {
        $worksheet = $matches[1];
        $column = $matches[6];
        $row = $matches[7];
        if (!str_contains($column, '$')) {
            $column = Coordinate::column_index_from_string($column);
            $column += $this->reference_column - 1;
            $column = Coordinate::string_from_column_index($column);
        }
        if (!str_contains($row, '$')) {
            $row = (int) $row + ($this->reference_row - 1);
        }
        return "{$worksheet}{$column}{$row}";
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
}