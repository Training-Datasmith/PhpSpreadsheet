<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Spreadsheet;
use Simple_Xml_Element;
class Workbook_View
{
    public function __construct(private readonly Spreadsheet $spreadsheet)
    {
    }
    /** @param array<int, ?int> $mapSheetId */
    public function view_settings(Simple_Xml_Element $xml_workbook, string $main_ns, array $map_sheet_id, bool $read_data_only): void
    {
        // Default active sheet index to the first loaded worksheet from the file
        $this->spreadsheet->set_active_sheet_index(0);
        $workbook_view = $xml_workbook->children($main_ns)->book_views->workbook_view;
        if ($read_data_only !== true && !empty($workbook_view)) {
            $workbook_view_attributes = self::test_simple_xml(self::get_attributes($workbook_view));
            // active sheet index
            $active_tab = (int) $workbook_view_attributes->active_tab;
            // refers to old sheet index
            // keep active sheet index if sheet is still loaded, else first sheet is set as the active worksheet
            if (isset($map_sheet_id[$active_tab])) {
                $this->spreadsheet->set_active_sheet_index($map_sheet_id[$active_tab]);
            }
            $this->horizontal_scroll($workbook_view_attributes);
            $this->vertical_scroll($workbook_view_attributes);
            $this->sheet_tabs($workbook_view_attributes);
            $this->minimized($workbook_view_attributes);
            $this->auto_filter_date_grouping($workbook_view_attributes);
            $this->first_sheet($workbook_view_attributes);
            $this->visibility($workbook_view_attributes);
            $this->tab_ratio($workbook_view_attributes);
        }
    }
    public static function test_simple_xml(mixed $value): Simple_Xml_Element
    {
        return $value instanceof Simple_Xml_Element ? $value : new Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8"?><root></root>');
    }
    public static function get_attributes(?Simple_Xml_Element $value, string $ns = ''): Simple_Xml_Element
    {
        return self::test_simple_xml($value === null ? $value : $value->attributes($ns));
    }
    /**
     * Convert an 'xsd:boolean' XML value to a PHP boolean value.
     * A valid 'xsd:boolean' XML value can be one of the following
     * four values: 'true', 'false', '1', '0'.  It is case-sensitive.
     *
     * Note that just doing '(bool) $xsdBoolean' is not safe,
     * since '(bool) "false"' returns true.
     *
     * @see https://www.w3.org/TR/xmlschema11-2/#boolean
     *
     * @param string $xsdBoolean An XML string value of type 'xsd:boolean'
     *
     * @return bool  Boolean value
     */
    private function cast_xsd_boolean_to_bool(string $xsd_boolean): bool
    {
        if ($xsd_boolean === 'false') {
            return false;
        }
        return (bool) $xsd_boolean;
    }
    private function horizontal_scroll(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->show_horizontal_scroll)) {
            $show_horizontal_scroll = (string) $workbook_view_attributes->show_horizontal_scroll;
            $this->spreadsheet->set_show_horizontal_scroll($this->cast_xsd_boolean_to_bool($show_horizontal_scroll));
        }
    }
    private function vertical_scroll(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->show_vertical_scroll)) {
            $show_vertical_scroll = (string) $workbook_view_attributes->show_vertical_scroll;
            $this->spreadsheet->set_show_vertical_scroll($this->cast_xsd_boolean_to_bool($show_vertical_scroll));
        }
    }
    private function sheet_tabs(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->show_sheet_tabs)) {
            $show_sheet_tabs = (string) $workbook_view_attributes->show_sheet_tabs;
            $this->spreadsheet->set_show_sheet_tabs($this->cast_xsd_boolean_to_bool($show_sheet_tabs));
        }
    }
    private function minimized(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->minimized)) {
            $minimized = (string) $workbook_view_attributes->minimized;
            $this->spreadsheet->set_minimized($this->cast_xsd_boolean_to_bool($minimized));
        }
    }
    private function auto_filter_date_grouping(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->auto_filter_date_grouping)) {
            $auto_filter_date_grouping = (string) $workbook_view_attributes->auto_filter_date_grouping;
            $this->spreadsheet->set_auto_filter_date_grouping($this->cast_xsd_boolean_to_bool($auto_filter_date_grouping));
        }
    }
    private function first_sheet(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->first_sheet)) {
            $first_sheet = (string) $workbook_view_attributes->first_sheet;
            $this->spreadsheet->set_first_sheet_index((int) $first_sheet);
        }
    }
    private function visibility(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->visibility)) {
            $visibility = (string) $workbook_view_attributes->visibility;
            $this->spreadsheet->set_visibility($visibility);
        }
    }
    private function tab_ratio(Simple_Xml_Element $workbook_view_attributes): void
    {
        if (isset($workbook_view_attributes->tab_ratio)) {
            $tab_ratio = (string) $workbook_view_attributes->tab_ratio;
            $this->spreadsheet->set_tab_ratio((int) $tab_ratio);
        }
    }
}