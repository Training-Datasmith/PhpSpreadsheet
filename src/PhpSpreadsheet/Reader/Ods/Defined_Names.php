<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Dom_Element;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Defined_Names extends Base_Loader
{
    public function read(Dom_Element $workbook_data): void
    {
        $this->read_defined_ranges($workbook_data);
        $this->read_defined_expressions($workbook_data);
    }
    /**
     * Read any Named Ranges that are defined in this spreadsheet.
     */
    protected function read_defined_ranges(Dom_Element $workbook_data): void
    {
        $named_ranges = $workbook_data->get_elements_by_tag_name_ns($this->table_ns, 'named-range');
        foreach ($named_ranges as $defined_name_element) {
            $defined_name = $defined_name_element->get_attribute_ns($this->table_ns, 'name');
            $base_address = $defined_name_element->get_attribute_ns($this->table_ns, 'base-cell-address');
            $range = $defined_name_element->get_attribute_ns($this->table_ns, 'cell-range-address');
            /** @var non-empty-string $baseAddress */
            $base_address = Formula_Translator::convert_to_excel_address_value($base_address);
            $range = Formula_Translator::convert_to_excel_address_value($range);
            $this->add_defined_name($base_address, $defined_name, $range);
        }
    }
    /**
     * Read any Named Formulae that are defined in this spreadsheet.
     */
    protected function read_defined_expressions(Dom_Element $workbook_data): void
    {
        $named_expressions = $workbook_data->get_elements_by_tag_name_ns($this->table_ns, 'named-expression');
        foreach ($named_expressions as $defined_name_element) {
            $defined_name = $defined_name_element->get_attribute_ns($this->table_ns, 'name');
            $base_address = $defined_name_element->get_attribute_ns($this->table_ns, 'base-cell-address');
            $expression = $defined_name_element->get_attribute_ns($this->table_ns, 'expression');
            /** @var non-empty-string $baseAddress */
            $base_address = Formula_Translator::convert_to_excel_address_value($base_address);
            $expression = substr($expression, strpos($expression, ':=') + 1);
            $expression = Formula_Translator::convert_to_excel_formula_value($expression);
            $this->add_defined_name($base_address, $defined_name, $expression);
        }
    }
    /**
     * Assess scope and store the Defined Name.
     *
     * @param non-empty-string $baseAddress
     */
    private function add_defined_name(string $base_address, string $defined_name, string $value): void
    {
        [$sheet_reference] = Worksheet::extract_sheet_title($base_address, true, true);
        $worksheet = $this->spreadsheet->get_sheet_by_name($sheet_reference);
        // Worksheet might still be null if we're only loading selected sheets rather than the full spreadsheet
        if ($worksheet !== null) {
            $this->spreadsheet->add_defined_name(Defined_Name::create_instance($defined_name, $worksheet, $value));
        }
    }
}