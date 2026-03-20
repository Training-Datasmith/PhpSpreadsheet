<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Gnumeric;

use Php_Office\Php_Spreadsheet\Reader\Gnumeric;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Margins;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup as WorksheetPageSetup;
use Simple_Xml_Element;
class Page_Setup
{
    public function __construct(private readonly Spreadsheet $spreadsheet)
    {
    }
    public function print_information(Simple_Xml_Element $sheet): self
    {
        if (isset($sheet->print_information, $sheet->print_information[0])) {
            $print_information = $sheet->print_information[0];
            $setup = $this->spreadsheet->get_active_sheet()->get_page_setup();
            $attributes = $print_information->Scale->attributes();
            if (isset($attributes['percentage'])) {
                $setup->set_scale((int) $attributes['percentage']);
            }
            $page_order = (string) $print_information->order;
            if ($page_order === 'r_then_d') {
                $setup->set_page_order(Worksheet_Page_Setup::PAGEORDER_OVER_THEN_DOWN);
            } elseif ($page_order === 'd_then_r') {
                $setup->set_page_order(Worksheet_Page_Setup::PAGEORDER_DOWN_THEN_OVER);
            }
            $orientation = (string) $print_information->orientation;
            if ($orientation !== '') {
                $setup->set_orientation($orientation);
            }
            $attributes = $print_information->hcenter->attributes();
            if (isset($attributes['value'])) {
                $setup->set_horizontal_centered((bool) (string) $attributes['value']);
            }
            $attributes = $print_information->vcenter->attributes();
            if (isset($attributes['value'])) {
                $setup->set_vertical_centered((bool) (string) $attributes['value']);
            }
        }
        return $this;
    }
    public function sheet_margins(Simple_Xml_Element $sheet): self
    {
        if (isset($sheet->print_information, $sheet->print_information->Margins)) {
            $margin_set = [
                // Default Settings
                'top' => 0.75,
                'header' => 0.3,
                'left' => 0.7,
                'right' => 0.7,
                'bottom' => 0.75,
                'footer' => 0.3,
            ];
            $margin_set = $this->build_margin_set($sheet, $margin_set);
            $this->adjust_margins($margin_set);
        }
        return $this;
    }
    /**
     * @param float[] $marginSet
     *
     * @return float[]
     */
    private function build_margin_set(Simple_Xml_Element $sheet, array $margin_set): array
    {
        foreach ($sheet->print_information->Margins->children(Gnumeric::NAMESPACE_GNM) as $key => $margin) {
            $margin_attributes = $margin->attributes();
            $margin_size = $margin_attributes['Points'] ?? 72;
            //    Default is 72pt
            // Convert value in points to inches
            $margin_size = Page_Margins::from_points((float) $margin_size);
            $margin_set[$key] = $margin_size;
        }
        return $margin_set;
    }
    /** @param float[] $marginSet */
    private function adjust_margins(array $margin_set): void
    {
        foreach ($margin_set as $key => $margin_size) {
            // Gnumeric is quirky in the way it displays the header/footer values:
            //    header is actually the sum of top and header; footer is actually the sum of bottom and footer
            //    then top is actually the header value, and bottom is actually the footer value
            switch ($key) {
                case 'left':
                case 'right':
                    $this->sheet_margin($key, $margin_size);
                    break;
                case 'top':
                    $this->sheet_margin($key, $margin_set['header'] ?? 0);
                    break;
                case 'bottom':
                    $this->sheet_margin($key, $margin_set['footer'] ?? 0);
                    break;
                case 'header':
                    $this->sheet_margin($key, ($margin_set['top'] ?? 0) - $margin_size);
                    break;
                case 'footer':
                    $this->sheet_margin($key, ($margin_set['bottom'] ?? 0) - $margin_size);
                    break;
            }
        }
    }
    private function sheet_margin(string $key, float $margin_size): void
    {
        switch ($key) {
            case 'top':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_top($margin_size);
                break;
            case 'bottom':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_bottom($margin_size);
                break;
            case 'left':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_left($margin_size);
                break;
            case 'right':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_right($margin_size);
                break;
            case 'header':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_header($margin_size);
                break;
            case 'footer':
                $this->spreadsheet->get_active_sheet()->get_page_margins()->set_footer($margin_size);
                break;
        }
    }
}