<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Dom_Document;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use stdClass;
class Page_Settings
{
    private string $office_ns = '';
    private string $styles_ns = '';
    private string $styles_fo = '';
    private string $table_ns = '';
    /**
     * @var string[]
     */
    private array $table_styles_cross_reference = [];
    /** @var mixed[] */
    private array $page_layout_styles = [];
    /**
     * @var string[]
     */
    private array $master_styles_cross_reference = [];
    /**
     * @var string[]
     */
    private array $master_print_styles_cross_reference = [];
    public function __construct(Dom_Document $style_dom)
    {
        $this->set_dom_name_spaces($style_dom);
        $this->read_page_setting_styles($style_dom);
        $this->read_style_master_lookup($style_dom);
    }
    private function set_dom_name_spaces(Dom_Document $style_dom): void
    {
        $this->office_ns = (string) $style_dom->lookup_namespace_uri('office');
        $this->styles_ns = (string) $style_dom->lookup_namespace_uri('style');
        $this->styles_fo = (string) $style_dom->lookup_namespace_uri('fo');
        $this->table_ns = (string) $style_dom->lookup_namespace_uri('table');
    }
    private function read_page_setting_styles(Dom_Document $style_dom): void
    {
        $item0 = $style_dom->get_elements_by_tag_name_ns($this->office_ns, 'automatic-styles')->item(0);
        $styles = $item0 === null ? [] : $item0->get_elements_by_tag_name_ns($this->styles_ns, 'page-layout');
        foreach ($styles as $style_set) {
            $style_name = $style_set->get_attribute_ns($this->styles_ns, 'name');
            $page_layout_properties = $style_set->get_elements_by_tag_name_ns($this->styles_ns, 'page-layout-properties')->item(0);
            $style_orientation = $page_layout_properties?->get_attribute_ns($this->styles_ns, 'print-orientation');
            $style_scale = $page_layout_properties?->get_attribute_ns($this->styles_ns, 'scale-to');
            $style_print_order = $page_layout_properties?->get_attribute_ns($this->styles_ns, 'print-page-order');
            $centered = $page_layout_properties?->get_attribute_ns($this->styles_ns, 'table-centering');
            $margin_left = $page_layout_properties?->get_attribute_ns($this->styles_fo, 'margin-left');
            $margin_right = $page_layout_properties?->get_attribute_ns($this->styles_fo, 'margin-right');
            $margin_top = $page_layout_properties?->get_attribute_ns($this->styles_fo, 'margin-top');
            $margin_bottom = $page_layout_properties?->get_attribute_ns($this->styles_fo, 'margin-bottom');
            $header = $style_set->get_elements_by_tag_name_ns($this->styles_ns, 'header-style')->item(0);
            $header_properties = $header?->get_elements_by_tag_name_ns($this->styles_ns, 'header-footer-properties')?->item(0);
            $margin_header = $header_properties?->get_attribute_ns($this->styles_fo, 'min-height');
            $footer = $style_set->get_elements_by_tag_name_ns($this->styles_ns, 'footer-style')->item(0);
            $footer_properties = $footer?->get_elements_by_tag_name_ns($this->styles_ns, 'header-footer-properties')?->item(0);
            $margin_footer = $footer_properties?->get_attribute_ns($this->styles_fo, 'min-height');
            $this->page_layout_styles[$style_name] = (object) [
                'orientation' => $style_orientation ?: Page_Setup::ORIENTATION_DEFAULT,
                'scale' => $style_scale ?: 100,
                'printOrder' => $style_print_order,
                'horizontalCentered' => $centered === 'horizontal' || $centered === 'both',
                'verticalCentered' => $centered === 'vertical' || $centered === 'both',
                // margin size is already stored in inches, so no UOM conversion is required
                'marginLeft' => (float) ($margin_left ?? 0.7),
                'marginRight' => (float) ($margin_right ?? 0.7),
                'marginTop' => (float) ($margin_top ?? 0.3),
                'marginBottom' => (float) ($margin_bottom ?? 0.3),
                'marginHeader' => (float) ($margin_header ?? 0.45),
                'marginFooter' => (float) ($margin_footer ?? 0.45),
            ];
        }
    }
    private function read_style_master_lookup(Dom_Document $style_dom): void
    {
        $item0 = $style_dom->get_elements_by_tag_name_ns($this->office_ns, 'master-styles')->item(0);
        $style_master_lookup = $item0 === null ? [] : $item0->get_elements_by_tag_name_ns($this->styles_ns, 'master-page');
        foreach ($style_master_lookup as $style_master_set) {
            $style_master_name = $style_master_set->get_attribute_ns($this->styles_ns, 'name');
            $page_layout_name = $style_master_set->get_attribute_ns($this->styles_ns, 'page-layout-name');
            $this->master_print_styles_cross_reference[$style_master_name] = $page_layout_name;
        }
    }
    public function read_style_cross_references(Dom_Document $content_dom): void
    {
        $item0 = $content_dom->get_elements_by_tag_name_ns($this->office_ns, 'automatic-styles')->item(0);
        $style_x_references = $item0 === null ? [] : $item0->get_elements_by_tag_name_ns($this->styles_ns, 'style');
        foreach ($style_x_references as $style_xreference_set) {
            $style_x_ref_name = $style_xreference_set->get_attribute_ns($this->styles_ns, 'name');
            $style_page_layout_name = $style_xreference_set->get_attribute_ns($this->styles_ns, 'master-page-name');
            $style_family_name = $style_xreference_set->get_attribute_ns($this->styles_ns, 'family');
            if (!empty($style_family_name) && $style_family_name === 'table') {
                $style_visibility = 'true';
                foreach ($style_xreference_set->get_elements_by_tag_name_ns($this->styles_ns, 'table-properties') as $table_properties) {
                    $style_visibility = $table_properties->get_attribute_ns($this->table_ns, 'display');
                }
                $this->table_styles_cross_reference[$style_x_ref_name] = $style_visibility;
            }
            if (!empty($style_page_layout_name)) {
                $this->master_styles_cross_reference[$style_x_ref_name] = $style_page_layout_name;
            }
        }
    }
    public function set_visibility_for_worksheet(Worksheet $worksheet, string $style_name): void
    {
        if (!array_key_exists($style_name, $this->table_styles_cross_reference)) {
            return;
        }
        $worksheet->set_sheet_state($this->table_styles_cross_reference[$style_name] === 'false' ? Worksheet::SHEETSTATE_HIDDEN : Worksheet::SHEETSTATE_VISIBLE);
    }
    public function set_print_settings_for_worksheet(Worksheet $worksheet, string $style_name): void
    {
        if (!array_key_exists($style_name, $this->master_styles_cross_reference)) {
            return;
        }
        $master_style_name = $this->master_styles_cross_reference[$style_name];
        if (!array_key_exists($master_style_name, $this->master_print_styles_cross_reference)) {
            return;
        }
        $print_settings_index = $this->master_print_styles_cross_reference[$master_style_name];
        if (!array_key_exists($print_settings_index, $this->page_layout_styles)) {
            return;
        }
        /** @var (object{orientation: string, scale: int|string, printOrder: ?string,
         * horizontalCentered: bool, verticalCentered: bool, marginLeft: float, marginRight: float, marginTop: float,
         * marginBottom: float, marginHeader: float, marginFooter: float}&stdClass) */
        $print_settings = $this->page_layout_styles[$print_settings_index];
        $worksheet->get_page_setup()->set_orientation($print_settings->orientation ?? Page_Setup::ORIENTATION_DEFAULT)->set_page_order($print_settings->print_order === 'ltr' ? Page_Setup::PAGEORDER_OVER_THEN_DOWN : Page_Setup::PAGEORDER_DOWN_THEN_OVER)->set_scale((int) trim((string) $print_settings->scale, '%'))->set_horizontal_centered($print_settings->horizontal_centered)->set_vertical_centered($print_settings->vertical_centered);
        $worksheet->get_page_margins()->set_left($print_settings->margin_left)->set_right($print_settings->margin_right)->set_top($print_settings->margin_top)->set_bottom($print_settings->margin_bottom)->set_header($print_settings->margin_header)->set_footer($print_settings->margin_footer);
    }
}