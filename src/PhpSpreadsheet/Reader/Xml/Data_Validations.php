<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml;

use Php_Office\Php_Spreadsheet\Cell\Address_Helper;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Simple_Xml_Element;
class Data_Validations
{
    private const OPERATOR_MAPPINGS = ['between' => Data_Validation::OPERATOR_BETWEEN, 'equal' => Data_Validation::OPERATOR_EQUAL, 'greater' => Data_Validation::OPERATOR_GREATERTHAN, 'greaterorequal' => Data_Validation::OPERATOR_GREATERTHANOREQUAL, 'less' => Data_Validation::OPERATOR_LESSTHAN, 'lessorequal' => Data_Validation::OPERATOR_LESSTHANOREQUAL, 'notbetween' => Data_Validation::OPERATOR_NOTBETWEEN, 'notequal' => Data_Validation::OPERATOR_NOTEQUAL];
    private const TYPE_MAPPINGS = ['textlength' => Data_Validation::TYPE_TEXTLENGTH];
    private int $this_row = 0;
    private int $this_column = 0;
    /** @param string[] $matches */
    private function replace_r1c1(array $matches): string
    {
        return Address_Helper::convert_to_a1($matches[0], $this->this_row, $this->this_column, false);
    }
    public function load_data_validations(Simple_Xml_Element $worksheet, Spreadsheet $spreadsheet): void
    {
        $xml_x = $worksheet->children(Namespaces::URN_EXCEL);
        $sheet = $spreadsheet->get_active_sheet();
        /** @var callable $pregCallback */
        $preg_callback = $this->replace_r1c1(...);
        foreach ($xml_x->data_validation as $data_validation) {
            $combined_cells = '';
            $separator = '';
            $validation = new Data_Validation();
            // set defaults
            $validation->set_show_drop_down(true);
            $validation->set_show_input_message(true);
            $validation->set_show_error_message(true);
            $validation->set_show_drop_down(true);
            $this->this_row = 1;
            $this->this_column = 1;
            foreach ($data_validation as $tag_name => $tag_value) {
                $tag_value = (string) $tag_value;
                $tag_value_lower = strtolower($tag_value);
                switch ($tag_name) {
                    case 'Range':
                        foreach (explode(',', $tag_value) as $range) {
                            $cell = '';
                            if (preg_match('/^R(\d+)C(\d+):R(\d+)C(\d+)$/', $range, $selection_matches) === 1) {
                                // range
                                $first_cell = Coordinate::string_from_column_index((int) $selection_matches[2]) . $selection_matches[1];
                                $cell = $first_cell . ':' . Coordinate::string_from_column_index((int) $selection_matches[4]) . $selection_matches[3];
                                $this->this_row = (int) $selection_matches[1];
                                $this->this_column = (int) $selection_matches[2];
                                $sheet->get_cell($first_cell);
                                $combined_cells .= "{$separator}{$cell}";
                                $separator = ' ';
                            } elseif (preg_match('/^R(\d+)C(\d+)$/', $range, $selection_matches) === 1) {
                                // cell
                                $cell = Coordinate::string_from_column_index((int) $selection_matches[2]) . $selection_matches[1];
                                $sheet->get_cell($cell);
                                $this->this_row = (int) $selection_matches[1];
                                $this->this_column = (int) $selection_matches[2];
                                $combined_cells .= "{$separator}{$cell}";
                                $separator = ' ';
                            } elseif (preg_match('/^C(\d+)(:C(]\d+))?$/', $range, $selection_matches) === 1) {
                                // column
                                $first_col = $selection_matches[1];
                                $first_col_string = Coordinate::string_from_column_index((int) $first_col);
                                $last_col = $selection_matches[3] ?? $first_col;
                                $last_col_string = Coordinate::string_from_column_index((int) $last_col);
                                $first_cell = "{$first_col_string}1";
                                $cell = "{$first_col_string}:{$last_col_string}";
                                $this->this_column = (int) $first_col;
                                $sheet->get_cell($first_cell);
                                $combined_cells .= "{$separator}{$cell}";
                                $separator = ' ';
                            } elseif (preg_match('/^R(\d+)(:R(]\d+))?$/', $range, $selection_matches)) {
                                // row
                                $first_row = $selection_matches[1];
                                $last_row = $selection_matches[3] ?? $first_row;
                                $first_cell = "A{$first_row}";
                                $cell = "{$first_row}:{$last_row}";
                                $this->this_row = (int) $first_row;
                                $sheet->get_cell($first_cell);
                                $combined_cells .= "{$separator}{$cell}";
                                $separator = ' ';
                            }
                        }
                        break;
                    case 'Type':
                        $validation->set_type(self::TYPE_MAPPINGS[$tag_value_lower] ?? $tag_value_lower);
                        break;
                    case 'Qualifier':
                        $validation->set_operator(self::OPERATOR_MAPPINGS[$tag_value_lower] ?? $tag_value_lower);
                        break;
                    case 'InputTitle':
                        $validation->set_prompt_title($tag_value);
                        break;
                    case 'InputMessage':
                        $validation->set_prompt($tag_value);
                        break;
                    case 'InputHide':
                        $validation->set_show_input_message(false);
                        break;
                    case 'ErrorStyle':
                        $validation->set_error_style($tag_value_lower);
                        break;
                    case 'ErrorTitle':
                        $validation->set_error_title($tag_value);
                        break;
                    case 'ErrorMessage':
                        $validation->set_error($tag_value);
                        break;
                    case 'ErrorHide':
                        $validation->set_show_error_message(false);
                        break;
                    case 'ComboHide':
                        $validation->set_show_drop_down(false);
                        break;
                    case 'UseBlank':
                        $validation->set_allow_blank(true);
                        break;
                    case 'CellRangeList':
                        // FIXME missing FIXME
                        break;
                    case 'Min':
                    case 'Value':
                        $tag_value = (string) preg_replace_callback(Address_Helper::R1C1_COORDINATE_REGEX, $preg_callback, $tag_value);
                        $validation->set_formula1($tag_value);
                        break;
                    case 'Max':
                        $tag_value = (string) preg_replace_callback(Address_Helper::R1C1_COORDINATE_REGEX, $preg_callback, $tag_value);
                        $validation->set_formula2($tag_value);
                        break;
                }
            }
            $sheet->set_data_validation($combined_cells, $validation);
        }
    }
}