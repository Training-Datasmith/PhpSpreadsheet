<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Dom_Element;
use Dom_Node;
class Auto_Filter extends Base_Loader
{
    public function read(Dom_Element $workbook_data): void
    {
        $this->read_auto_filters($workbook_data);
    }
    protected function read_auto_filters(Dom_Element $workbook_data): void
    {
        $databases = $workbook_data->get_elements_by_tag_name_ns($this->table_ns, 'database-ranges');
        foreach ($databases as $autofilters) {
            foreach ($autofilters->child_nodes as $autofilter) {
                $autofilter_range = $this->get_attribute_value($autofilter, 'target-range-address');
                if ($autofilter_range !== null) {
                    $base_address = Formula_Translator::convert_to_excel_address_value($autofilter_range);
                    $this->spreadsheet->get_active_sheet()->set_auto_filter($base_address);
                }
            }
        }
    }
    protected function get_attribute_value(?Dom_Node $node, string $attribute_name): ?string
    {
        if ($node !== null && $node->attributes !== null) {
            $attribute = $node->attributes->get_named_item_ns($this->table_ns, $attribute_name);
            if ($attribute !== null) {
                return $attribute->node_value;
            }
        }
        return null;
    }
}