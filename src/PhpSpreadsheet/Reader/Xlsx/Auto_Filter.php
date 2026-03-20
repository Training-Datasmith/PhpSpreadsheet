<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column\Rule;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Auto_Filter
{
    public function __construct(private readonly Table|Worksheet $parent, private readonly Simple_Xml_Element $worksheet_xml)
    {
    }
    public function load(): void
    {
        // Remove all "$" in the auto filter range
        $attrs = $this->worksheet_xml->auto_filter->attributes() ?? [];
        $auto_filter_range = (string) preg_replace('/\$/', '', $attrs['ref'] ?? '');
        if (str_contains($auto_filter_range, ':')) {
            $this->read_auto_filter($auto_filter_range);
        }
    }
    private function read_auto_filter(string $auto_filter_range): void
    {
        $auto_filter = $this->parent->get_auto_filter();
        $auto_filter->set_range($auto_filter_range);
        foreach ($this->worksheet_xml->auto_filter->filter_column as $filter_column) {
            $attributes = $filter_column->attributes() ?? [];
            $column = $auto_filter->get_column_by_offset((int) ($attributes['colId'] ?? 0));
            //    Check for standard filters
            if ($filter_column->filters) {
                $column->set_filter_type(Column::AUTOFILTER_FILTERTYPE_FILTER);
                $filters = Xlsx::test_simple_xml($filter_column->filters->attributes());
                if (isset($filters['blank']) && (int) $filters['blank'] == 1) {
                    //    Operator is undefined, but always treated as EQUAL
                    $column->create_rule()->set_rule('', '')->set_rule_type(Rule::AUTOFILTER_RULETYPE_FILTER);
                }
                //    Standard filters are always an OR join, so no join rule needs to be set
                //    Entries can be either filter elements
                foreach ($filter_column->filters->filter as $filter_rule) {
                    //    Operator is undefined, but always treated as EQUAL
                    /** @var SimpleXMLElement */
                    $attr2 = $filter_rule->attributes() ?? ['val' => ''];
                    $column->create_rule()->set_rule('', (string) $attr2['val'])->set_rule_type(Rule::AUTOFILTER_RULETYPE_FILTER);
                }
                //    Or Date Group elements
                $this->read_date_range_auto_filter($filter_column->filters, $column);
            }
            //    Check for custom filters
            $this->read_custom_auto_filter($filter_column, $column);
            //    Check for dynamic filters
            $this->read_dynamic_auto_filter($filter_column, $column);
            //    Check for dynamic filters
            $this->read_top_ten_auto_filter($filter_column, $column);
        }
        $auto_filter->set_evaluated(true);
    }
    private function read_date_range_auto_filter(Simple_Xml_Element $filters, Column $column): void
    {
        foreach ($filters->date_group_item as $date_group_itemx) {
            //    Operator is undefined, but always treated as EQUAL
            $date_group_item = $date_group_itemx->attributes();
            if ($date_group_item !== null) {
                $column->create_rule()->set_rule('', ['year' => (string) $date_group_item['year'], 'month' => (string) $date_group_item['month'], 'day' => (string) $date_group_item['day'], 'hour' => (string) $date_group_item['hour'], 'minute' => (string) $date_group_item['minute'], 'second' => (string) $date_group_item['second']], (string) $date_group_item['dateTimeGrouping'])->set_rule_type(Rule::AUTOFILTER_RULETYPE_DATEGROUP);
            }
        }
    }
    private function read_custom_auto_filter(?Simple_Xml_Element $filter_column, Column $column): void
    {
        if (isset($filter_column, $filter_column->custom_filters)) {
            $column->set_filter_type(Column::AUTOFILTER_FILTERTYPE_CUSTOMFILTER);
            $custom_filters = $filter_column->custom_filters;
            $attributes = $custom_filters->attributes();
            //    Custom filters can an AND or an OR join;
            //        and there should only ever be one or two entries
            if (isset($attributes['and']) && (string) $attributes['and'] === '1') {
                $column->set_join(Column::AUTOFILTER_COLUMN_JOIN_AND);
            }
            foreach ($custom_filters->custom_filter as $filter_rule) {
                /** @var SimpleXMLElement */
                $attr2 = $filter_rule->attributes() ?? ['operator' => '', 'val' => ''];
                $column->create_rule()->set_rule((string) $attr2['operator'], (string) $attr2['val'])->set_rule_type(Rule::AUTOFILTER_RULETYPE_CUSTOMFILTER);
            }
        }
    }
    private function read_dynamic_auto_filter(?Simple_Xml_Element $filter_column, Column $column): void
    {
        if (isset($filter_column, $filter_column->dynamic_filter)) {
            $column->set_filter_type(Column::AUTOFILTER_FILTERTYPE_DYNAMICFILTER);
            //    We should only ever have one dynamic filter
            foreach ($filter_column->dynamic_filter as $filter_rule) {
                //    Operator is undefined, but always treated as EQUAL
                $attr2 = $filter_rule->attributes() ?? [];
                $column->create_rule()->set_rule('', (string) ($attr2['val'] ?? ''), (string) ($attr2['type'] ?? ''))->set_rule_type(Rule::AUTOFILTER_RULETYPE_DYNAMICFILTER);
                if (isset($attr2['val'])) {
                    $column->set_attribute('val', (string) $attr2['val']);
                }
                if (isset($attr2['maxVal'])) {
                    $column->set_attribute('maxVal', (string) $attr2['maxVal']);
                }
            }
        }
    }
    private function read_top_ten_auto_filter(?Simple_Xml_Element $filter_column, Column $column): void
    {
        if (isset($filter_column, $filter_column->top10)) {
            $column->set_filter_type(Column::AUTOFILTER_FILTERTYPE_TOPTENFILTER);
            //    We should only ever have one top10 filter
            foreach ($filter_column->top10 as $filter_rule) {
                $attr2 = $filter_rule->attributes() ?? [];
                $column->create_rule()->set_rule(isset($attr2['percent']) && (string) $attr2['percent'] === '1' ? Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_PERCENT : Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_BY_VALUE, (string) ($attr2['val'] ?? ''), isset($attr2['top']) && (string) $attr2['top'] === '1' ? Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_TOP : Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_BOTTOM)->set_rule_type(Rule::AUTOFILTER_RULETYPE_TOPTENFILTER);
            }
        }
    }
}