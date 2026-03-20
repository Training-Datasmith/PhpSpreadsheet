<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column\Rule;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet as ActualWorksheet;
class Auto_Filter extends Writer_Part
{
    /**
     * Write AutoFilter.
     */
    public static function write_auto_filter(Xml_Writer $obj_writer, Actual_Worksheet $worksheet): void
    {
        $auto_filter_range = $worksheet->get_auto_filter()->get_range();
        if (!empty($auto_filter_range)) {
            // autoFilter
            $obj_writer->start_element('autoFilter');
            // Strip any worksheet reference from the filter coordinates
            $range = Coordinate::split_range($auto_filter_range);
            $range = $range[0];
            //    Strip any worksheet ref
            [, $range[0]] = Actual_Worksheet::extract_sheet_title($range[0], true);
            $range = implode(':', $range);
            $obj_writer->write_attribute('ref', str_replace('$', '', $range));
            $columns = $worksheet->get_auto_filter()->get_columns();
            foreach ($columns as $column_id => $column) {
                $col_id = $worksheet->get_auto_filter()->get_column_offset($column_id);
                self::write_auto_filter_column($obj_writer, $column, $col_id);
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write AutoFilter's filterColumn.
     */
    public static function write_auto_filter_column(Xml_Writer $obj_writer, Column $column, int $col_id): void
    {
        $rules = $column->get_rules();
        if (count($rules) > 0) {
            $obj_writer->start_element('filterColumn');
            $obj_writer->write_attribute('colId', "{$col_id}");
            $obj_writer->start_element($column->get_filter_type());
            if ($column->get_join() == Column::AUTOFILTER_COLUMN_JOIN_AND) {
                $obj_writer->write_attribute('and', '1');
            }
            foreach ($rules as $rule) {
                self::write_auto_filter_column_rule($column, $rule, $obj_writer);
            }
            $obj_writer->end_element();
            $obj_writer->end_element();
        }
    }
    /**
     * Write AutoFilter's filterColumn Rule.
     */
    private static function write_auto_filter_column_rule(Column $column, Rule $rule, Xml_Writer $obj_writer): void
    {
        if ($column->get_filter_type() === Column::AUTOFILTER_FILTERTYPE_FILTER && $rule->get_operator() === Rule::AUTOFILTER_COLUMN_RULE_EQUAL && $rule->get_value() === '') {
            //    Filter rule for Blanks
            $obj_writer->write_attribute('blank', '1');
        } elseif ($rule->get_rule_type() === Rule::AUTOFILTER_RULETYPE_DYNAMICFILTER) {
            //    Dynamic Filter Rule
            $obj_writer->write_attribute('type', $rule->get_grouping());
            $val = $column->get_attribute('val');
            if ($val !== null) {
                $obj_writer->write_attribute('val', "{$val}");
            }
            $max_val = $column->get_attribute('maxVal');
            if ($max_val !== null) {
                $obj_writer->write_attribute('maxVal', "{$max_val}");
            }
        } elseif ($rule->get_rule_type() === Rule::AUTOFILTER_RULETYPE_TOPTENFILTER) {
            //    Top 10 Filter Rule
            $rule_value = $rule->get_value();
            if (!is_array($rule_value)) {
                $obj_writer->write_attribute('val', "{$rule_value}");
            }
            $obj_writer->write_attribute('percent', $rule->get_operator() === Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_PERCENT ? '1' : '0');
            $obj_writer->write_attribute('top', $rule->get_grouping() === Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_TOP ? '1' : '0');
        } else {
            //    Filter, DateGroupItem or CustomFilter
            $obj_writer->start_element($rule->get_rule_type());
            if ($rule->get_operator() !== Rule::AUTOFILTER_COLUMN_RULE_EQUAL) {
                $obj_writer->write_attribute('operator', $rule->get_operator());
            }
            if ($rule->get_rule_type() === Rule::AUTOFILTER_RULETYPE_DATEGROUP) {
                // Date Group filters
                $rule_value = $rule->get_value();
                if (is_array($rule_value)) {
                    foreach ($rule_value as $key => $value) {
                        $obj_writer->write_attribute($key, "{$value}");
                    }
                }
                $obj_writer->write_attribute('dateTimeGrouping', $rule->get_grouping());
            } else {
                $rule_value = $rule->get_value();
                if (!is_array($rule_value)) {
                    $obj_writer->write_attribute('val', "{$rule_value}");
                }
            }
            $obj_writer->end_element();
        }
    }
}