<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Data_Validations
{
    public function __construct(private readonly Worksheet $worksheet, private readonly Simple_Xml_Element $worksheet_xml)
    {
    }
    public function load(): void
    {
        foreach ($this->worksheet_xml->data_validations->data_validation as $data_validation) {
            // Uppercase coordinate
            $range = strtoupper((string) $data_validation['sqref']);
            $range_set = explode(' ', $range);
            foreach ($range_set as $range) {
                if (preg_match('/^[A-Z]{1,3}\d{1,7}/', $range, $matches) === 1) {
                    // Ensure left/top row of range exists, thereby
                    // adjusting high row/column.
                    $this->worksheet->get_cell($matches[0]);
                }
            }
        }
        foreach ($this->worksheet_xml->data_validations->data_validation as $data_validation) {
            // Uppercase coordinate
            $range = strtoupper((string) $data_validation['sqref']);
            $doc_validation = new Data_Validation();
            $doc_validation->set_type((string) $data_validation['type']);
            $doc_validation->set_error_style((string) $data_validation['errorStyle']);
            $doc_validation->set_operator((string) $data_validation['operator']);
            $doc_validation->set_allow_blank(filter_var($data_validation['allowBlank'], FILTER_VALIDATE_BOOLEAN));
            // showDropDown is inverted (works as hideDropDown if true)
            $doc_validation->set_show_drop_down(!filter_var($data_validation['showDropDown'], FILTER_VALIDATE_BOOLEAN));
            $doc_validation->set_show_input_message(filter_var($data_validation['showInputMessage'], FILTER_VALIDATE_BOOLEAN));
            $doc_validation->set_show_error_message(filter_var($data_validation['showErrorMessage'], FILTER_VALIDATE_BOOLEAN));
            $doc_validation->set_error_title((string) $data_validation['errorTitle']);
            $doc_validation->set_error((string) $data_validation['error']);
            $doc_validation->set_prompt_title((string) $data_validation['promptTitle']);
            $doc_validation->set_prompt((string) $data_validation['prompt']);
            $doc_validation->set_formula1(Xlsx::replace_prefixes((string) $data_validation->formula1));
            $doc_validation->set_formula2(Xlsx::replace_prefixes((string) $data_validation->formula2));
            $this->worksheet->set_data_validation($range, $doc_validation);
        }
    }
}