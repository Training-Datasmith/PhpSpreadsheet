<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Simple_Xml_Element;
use stdClass;
class Page_Settings
{
    /** @var (object{orientation: string, scale: ?int, printOrder: ?string,
     * paperSize: int,
     * horizontalCentered: bool, verticalCentered: bool, leftMargin: float, rightMargin: float, topMargin: float,
     * bottomMargin: float, headerMargin: float, footerMargin: float}&stdClass) */
    private readonly stdClass $print_settings;
    public function __construct(Simple_Xml_Element $xml_x)
    {
        $print_settings = $this->page_setup($xml_x, $this->get_print_defaults());
        $this->print_settings = $this->print_setup($xml_x, $print_settings);
        //* @phpstan-ignore-line
    }
    public function load_page_settings(Spreadsheet $spreadsheet): void
    {
        $spreadsheet->get_active_sheet()->get_page_setup()->set_paper_size($this->print_settings->paper_size)->set_orientation($this->print_settings->orientation)->set_scale($this->print_settings->scale)->set_vertical_centered($this->print_settings->vertical_centered)->set_horizontal_centered($this->print_settings->horizontal_centered)->set_page_order($this->print_settings->print_order);
        $spreadsheet->get_active_sheet()->get_page_margins()->set_top($this->print_settings->top_margin)->set_header($this->print_settings->header_margin)->set_left($this->print_settings->left_margin)->set_right($this->print_settings->right_margin)->set_bottom($this->print_settings->bottom_margin)->set_footer($this->print_settings->footer_margin);
    }
    private function get_print_defaults(): stdClass
    {
        return (object) ['paperSize' => 9, 'orientation' => Page_Setup::ORIENTATION_DEFAULT, 'scale' => 100, 'horizontalCentered' => false, 'verticalCentered' => false, 'printOrder' => Page_Setup::PAGEORDER_DOWN_THEN_OVER, 'topMargin' => 0.75, 'headerMargin' => 0.3, 'leftMargin' => 0.7, 'rightMargin' => 0.7, 'bottomMargin' => 0.75, 'footerMargin' => 0.3];
    }
    private function page_setup(Simple_Xml_Element $xml_x, stdClass $print_defaults): stdClass
    {
        if (isset($xml_x->worksheet_options->page_setup)) {
            foreach ($xml_x->worksheet_options->page_setup as $page_setup_data) {
                foreach ($page_setup_data as $page_setup_key => $page_setup_value) {
                    $page_setup_attributes = $page_setup_value->attributes(Namespaces::URN_EXCEL);
                    if ($page_setup_attributes !== null) {
                        switch ($page_setup_key) {
                            case 'Layout':
                                $this->set_layout($print_defaults, $page_setup_attributes);
                                break;
                            case 'Header':
                                $print_defaults->header_margin = (float) $page_setup_attributes->Margin ?: 1.0;
                                break;
                            case 'Footer':
                                $print_defaults->footer_margin = (float) $page_setup_attributes->Margin ?: 1.0;
                                break;
                            case 'PageMargins':
                                $this->set_margins($print_defaults, $page_setup_attributes);
                                break;
                        }
                    }
                }
            }
        }
        return $print_defaults;
    }
    private function print_setup(Simple_Xml_Element $xml_x, stdClass $print_defaults): stdClass
    {
        if (isset($xml_x->worksheet_options->Print)) {
            foreach ($xml_x->worksheet_options->Print as $print_data) {
                foreach ($print_data as $print_key => $print_value) {
                    switch ($print_key) {
                        case 'LeftToRight':
                            $print_defaults->print_order = Page_Setup::PAGEORDER_OVER_THEN_DOWN;
                            break;
                        case 'PaperSizeIndex':
                            $print_defaults->paper_size = (int) $print_value ?: 9;
                            break;
                        case 'Scale':
                            $print_defaults->scale = (int) $print_value ?: 100;
                            break;
                    }
                }
            }
        }
        return $print_defaults;
    }
    private function set_layout(stdClass $print_defaults, Simple_Xml_Element $page_setup_attributes): void
    {
        $print_defaults->orientation = (string) strtolower($page_setup_attributes->Orientation ?? '') ?: Page_Setup::ORIENTATION_PORTRAIT;
        $print_defaults->horizontal_centered = (bool) $page_setup_attributes->center_horizontal ?: false;
        $print_defaults->vertical_centered = (bool) $page_setup_attributes->center_vertical ?: false;
    }
    private function set_margins(stdClass $print_defaults, Simple_Xml_Element $page_setup_attributes): void
    {
        $print_defaults->left_margin = (float) $page_setup_attributes->Left ?: 1.0;
        $print_defaults->right_margin = (float) $page_setup_attributes->Right ?: 1.0;
        $print_defaults->top_margin = (float) $page_setup_attributes->Top ?: 1.0;
        $print_defaults->bottom_margin = (float) $page_setup_attributes->Bottom ?: 1.0;
    }
}