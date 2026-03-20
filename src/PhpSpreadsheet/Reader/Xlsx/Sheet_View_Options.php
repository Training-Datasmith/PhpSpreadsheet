<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Sheet_View_Options extends Base_Parser_Class
{
    public function __construct(private readonly Worksheet $worksheet, private readonly ?Simple_Xml_Element $worksheet_xml = null)
    {
    }
    public function load(bool $read_data_only, Styles $style_reader): void
    {
        if ($this->worksheet_xml === null) {
            return;
        }
        if (isset($this->worksheet_xml->sheet_pr)) {
            $sheet_pr = $this->worksheet_xml->sheet_pr;
            $this->tab_color($sheet_pr, $style_reader);
            $this->code_name($sheet_pr);
            $this->outlines($sheet_pr);
            $this->page_setup($sheet_pr);
        }
        if (isset($this->worksheet_xml->sheet_format_pr)) {
            $this->sheet_format($this->worksheet_xml->sheet_format_pr);
        }
        if (!$read_data_only && isset($this->worksheet_xml->print_options)) {
            $this->print_options($this->worksheet_xml->print_options);
        }
    }
    private function tab_color(Simple_Xml_Element $sheet_pr, Styles $style_reader): void
    {
        if (isset($sheet_pr->tab_color)) {
            $this->worksheet->get_tab_color()->set_argb($style_reader->read_color($sheet_pr->tab_color));
        }
    }
    private function code_name(Simple_Xml_Element $sheet_prx): void
    {
        $sheet_pr = $sheet_prx->attributes() ?? [];
        if (isset($sheet_pr['codeName'])) {
            $this->worksheet->set_code_name((string) $sheet_pr['codeName'], false);
        }
    }
    private function outlines(Simple_Xml_Element $sheet_pr): void
    {
        if (isset($sheet_pr->outline_pr)) {
            $attr = $sheet_pr->outline_pr->attributes() ?? [];
            if (isset($attr['summaryRight']) && !self::boolean((string) $attr['summaryRight'])) {
                $this->worksheet->set_show_summary_right(false);
            } else {
                $this->worksheet->set_show_summary_right(true);
            }
            if (isset($attr['summaryBelow']) && !self::boolean((string) $attr['summaryBelow'])) {
                $this->worksheet->set_show_summary_below(false);
            } else {
                $this->worksheet->set_show_summary_below(true);
            }
        }
    }
    private function page_setup(Simple_Xml_Element $sheet_pr): void
    {
        if (isset($sheet_pr->page_set_up_pr)) {
            $attr = $sheet_pr->page_set_up_pr->attributes() ?? [];
            if (isset($attr['fitToPage']) && !self::boolean((string) $attr['fitToPage'])) {
                $this->worksheet->get_page_setup()->set_fit_to_page(false);
            } else {
                $this->worksheet->get_page_setup()->set_fit_to_page(true);
            }
        }
    }
    private function sheet_format(Simple_Xml_Element $sheet_format_prx): void
    {
        $sheet_format_pr = $sheet_format_prx->attributes() ?? [];
        if (isset($sheet_format_pr['customHeight']) && self::boolean((string) $sheet_format_pr['customHeight']) && isset($sheet_format_pr['defaultRowHeight'])) {
            $this->worksheet->get_default_row_dimension()->set_row_height((float) $sheet_format_pr['defaultRowHeight']);
        }
        if (isset($sheet_format_pr['defaultColWidth'])) {
            $this->worksheet->get_default_column_dimension()->set_width((float) $sheet_format_pr['defaultColWidth']);
        }
        if (isset($sheet_format_pr['zeroHeight']) && (string) $sheet_format_pr['zeroHeight'] === '1') {
            $this->worksheet->get_default_row_dimension()->set_zero_height(true);
        }
    }
    private function print_options(Simple_Xml_Element $print_optionsx): void
    {
        $print_options = $print_optionsx->attributes() ?? [];
        // Spec is weird. gridLines (default false)
        // and gridLinesSet (default true) must both be true.
        if (isset($print_options['gridLines']) && self::boolean((string) $print_options['gridLines'])) {
            if (!isset($print_options['gridLinesSet']) || self::boolean((string) $print_options['gridLinesSet'])) {
                $this->worksheet->set_print_gridlines(true);
            }
        }
        if (isset($print_options['horizontalCentered']) && self::boolean((string) $print_options['horizontalCentered'])) {
            $this->worksheet->get_page_setup()->set_horizontal_centered(true);
        }
        if (isset($print_options['verticalCentered']) && self::boolean((string) $print_options['verticalCentered'])) {
            $this->worksheet->get_page_setup()->set_vertical_centered(true);
        }
    }
}