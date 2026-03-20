<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
/**
 * Validate a cell value according to its validation rules.
 */
class Data_Validator
{
    /**
     * Does this cell contain valid value?
     *
     * @param Cell $cell Cell to check the value
     */
    public function is_valid(Cell $cell): bool
    {
        if (!$cell->has_data_validation() || $cell->get_data_validation()->get_type() === Data_Validation::TYPE_NONE) {
            return true;
        }
        $cell_value = $cell->get_value();
        $data_validation = $cell->get_data_validation();
        if (!$data_validation->get_allow_blank() && ($cell_value === null || $cell_value === '')) {
            return false;
        }
        $return_value = false;
        $type = $data_validation->get_type();
        if ($type === Data_Validation::TYPE_LIST) {
            $return_value = $this->is_value_in_list($cell);
        } elseif ($type === Data_Validation::TYPE_WHOLE) {
            if (!is_numeric($cell_value) || fmod((float) $cell_value, 1) != 0) {
                $return_value = false;
            } else {
                $return_value = $this->numeric_operator($data_validation, (int) $cell_value, $cell);
            }
        } elseif ($type === Data_Validation::TYPE_DECIMAL || $type === Data_Validation::TYPE_DATE || $type === Data_Validation::TYPE_TIME) {
            if (!is_numeric($cell_value)) {
                $return_value = false;
            } else {
                $return_value = $this->numeric_operator($data_validation, (float) $cell_value, $cell);
            }
        } elseif ($type === Data_Validation::TYPE_TEXTLENGTH) {
            $return_value = $this->numeric_operator($data_validation, mb_strlen($cell->get_value_string()), $cell);
        }
        return $return_value;
    }
    private const TWO_FORMULAS = [Data_Validation::OPERATOR_BETWEEN, Data_Validation::OPERATOR_NOTBETWEEN];
    private static function evaluate_numeric_formula(mixed $formula, Cell $cell): mixed
    {
        if (!is_numeric($formula)) {
            $calculation = Calculation::get_instance($cell->get_worksheet()->get_parent());
            try {
                $formula2 = String_Helper::convert_to_string($formula);
                $result = $calculation->calculate_formula("={$formula2}", $cell->get_coordinate(), $cell);
                while (is_array($result)) {
                    $result = array_pop($result);
                }
                $formula = $result;
            } catch (Exception) {
                // do nothing
            }
        }
        return $formula;
    }
    private function numeric_operator(Data_Validation $data_validation, int|float $cell_value, Cell $cell): bool
    {
        $operator = $data_validation->get_operator();
        $formula1 = self::evaluate_numeric_formula($data_validation->get_formula1(), $cell);
        $formula2 = 0;
        if (in_array($operator, self::TWO_FORMULAS, true)) {
            $formula2 = self::evaluate_numeric_formula($data_validation->get_formula2(), $cell);
        }
        return match ($operator) {
            Data_Validation::OPERATOR_BETWEEN => $cell_value >= $formula1 && $cell_value <= $formula2,
            Data_Validation::OPERATOR_NOTBETWEEN => $cell_value < $formula1 || $cell_value > $formula2,
            Data_Validation::OPERATOR_EQUAL => $cell_value == $formula1,
            Data_Validation::OPERATOR_NOTEQUAL => $cell_value != $formula1,
            Data_Validation::OPERATOR_LESSTHAN => $cell_value < $formula1,
            Data_Validation::OPERATOR_LESSTHANOREQUAL => $cell_value <= $formula1,
            Data_Validation::OPERATOR_GREATERTHAN => $cell_value > $formula1,
            Data_Validation::OPERATOR_GREATERTHANOREQUAL => $cell_value >= $formula1,
            default => false,
        };
    }
    /**
     * Does this cell contain valid value, based on list?
     *
     * @param Cell $cell Cell to check the value
     */
    private function is_value_in_list(Cell $cell): bool
    {
        $cell_value_string = $cell->get_value_string();
        $data_validation = $cell->get_data_validation();
        $formula1 = $data_validation->get_formula1();
        if (!empty($formula1)) {
            // inline values list
            if ($formula1[0] === '"') {
                return in_array(strtolower($cell_value_string), explode(',', strtolower(trim($formula1, '"'))), true);
            }
            $calculation = Calculation::get_instance($cell->get_worksheet()->get_parent());
            try {
                $result = $calculation->calculate_formula("={$formula1}", $cell->get_coordinate(), $cell);
                $result = is_array($result) ? Functions::flatten_array($result) : [$result];
                foreach ($result as $one_result) {
                    if (is_scalar($one_result) && strcasecmp((string) $one_result, $cell_value_string) === 0) {
                        return true;
                    }
                }
            } catch (Exception) {
                // do nothing
            }
            return false;
        }
        return true;
    }
}