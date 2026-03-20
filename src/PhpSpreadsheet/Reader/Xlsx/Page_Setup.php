<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Page_Setup extends Base_Parser_Class
{
    public function __construct(private readonly Worksheet $worksheet, private readonly ?Simple_Xml_Element $worksheet_xml = null)
    {
    }
    /**
     * @param mixed[] $unparsedLoadedData
     *
     * @return mixed[]
     */
    public function load(array $unparsed_loaded_data): array
    {
        $worksheet_xml = $this->worksheet_xml;
        if ($worksheet_xml === null) {
            return $unparsed_loaded_data;
        }
        $this->margins($worksheet_xml, $this->worksheet);
        $unparsed_loaded_data = $this->page_setup($worksheet_xml, $this->worksheet, $unparsed_loaded_data);
        $this->header_footer($worksheet_xml, $this->worksheet);
        $this->page_breaks($worksheet_xml, $this->worksheet);
        return $unparsed_loaded_data;
    }
    private function margins(Simple_Xml_Element $xml_sheet, Worksheet $worksheet): void
    {
        if ($xml_sheet->page_margins) {
            $doc_page_margins = $worksheet->get_page_margins();
            $doc_page_margins->set_left((float) $xml_sheet->page_margins['left']);
            $doc_page_margins->set_right((float) $xml_sheet->page_margins['right']);
            $doc_page_margins->set_top((float) $xml_sheet->page_margins['top']);
            $doc_page_margins->set_bottom((float) $xml_sheet->page_margins['bottom']);
            $doc_page_margins->set_header((float) $xml_sheet->page_margins['header']);
            $doc_page_margins->set_footer((float) $xml_sheet->page_margins['footer']);
        }
    }
    /**
     * @param mixed[] $unparsedLoadedData
     *
     * @return mixed[]
     */
    private function page_setup(Simple_Xml_Element $xml_sheet, Worksheet $worksheet, array $unparsed_loaded_data): array
    {
        if ($xml_sheet->page_setup) {
            $doc_page_setup = $worksheet->get_page_setup();
            if (isset($xml_sheet->page_setup['orientation'])) {
                $doc_page_setup->set_orientation((string) $xml_sheet->page_setup['orientation']);
            }
            if (isset($xml_sheet->page_setup['paperSize'])) {
                $doc_page_setup->set_paper_size((int) $xml_sheet->page_setup['paperSize']);
            }
            if (isset($xml_sheet->page_setup['scale'])) {
                $doc_page_setup->set_scale((int) $xml_sheet->page_setup['scale'], false);
            }
            if (isset($xml_sheet->page_setup['fitToHeight']) && (int) $xml_sheet->page_setup['fitToHeight'] >= 0) {
                $doc_page_setup->set_fit_to_height((int) $xml_sheet->page_setup['fitToHeight'], false);
            }
            if (isset($xml_sheet->page_setup['fitToWidth']) && (int) $xml_sheet->page_setup['fitToWidth'] >= 0) {
                $doc_page_setup->set_fit_to_width((int) $xml_sheet->page_setup['fitToWidth'], false);
            }
            if (isset($xml_sheet->page_setup['firstPageNumber'], $xml_sheet->page_setup['useFirstPageNumber']) && self::boolean((string) $xml_sheet->page_setup['useFirstPageNumber'])) {
                $doc_page_setup->set_first_page_number((int) $xml_sheet->page_setup['firstPageNumber']);
            }
            if (isset($xml_sheet->page_setup['pageOrder'])) {
                $doc_page_setup->set_page_order((string) $xml_sheet->page_setup['pageOrder']);
            }
            $rel_attributes = $xml_sheet->page_setup->attributes(Namespaces::SCHEMA_OFFICE_DOCUMENT);
            if (isset($rel_attributes['id'])) {
                $relid = (string) $rel_attributes['id'];
                if (!str_ends_with($relid, 'ps')) {
                    $relid .= 'ps';
                }
                /** @var mixed[][][] $unparsedLoadedData */
                $unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['pageSetupRelId'] = $relid;
            }
        }
        return $unparsed_loaded_data;
    }
    private function header_footer(Simple_Xml_Element $xml_sheet, Worksheet $worksheet): void
    {
        if ($xml_sheet->header_footer) {
            $doc_header_footer = $worksheet->get_header_footer();
            if (isset($xml_sheet->header_footer['differentOddEven']) && self::boolean((string) $xml_sheet->header_footer['differentOddEven'])) {
                $doc_header_footer->set_different_odd_even(true);
            } else {
                $doc_header_footer->set_different_odd_even(false);
            }
            if (isset($xml_sheet->header_footer['differentFirst']) && self::boolean((string) $xml_sheet->header_footer['differentFirst'])) {
                $doc_header_footer->set_different_first(true);
            } else {
                $doc_header_footer->set_different_first(false);
            }
            if (isset($xml_sheet->header_footer['scaleWithDoc']) && !self::boolean((string) $xml_sheet->header_footer['scaleWithDoc'])) {
                $doc_header_footer->set_scale_with_document(false);
            } else {
                $doc_header_footer->set_scale_with_document(true);
            }
            if (isset($xml_sheet->header_footer['alignWithMargins']) && !self::boolean((string) $xml_sheet->header_footer['alignWithMargins'])) {
                $doc_header_footer->set_align_with_margins(false);
            } else {
                $doc_header_footer->set_align_with_margins(true);
            }
            $doc_header_footer->set_odd_header((string) $xml_sheet->header_footer->odd_header);
            $doc_header_footer->set_odd_footer((string) $xml_sheet->header_footer->odd_footer);
            $doc_header_footer->set_even_header((string) $xml_sheet->header_footer->even_header);
            $doc_header_footer->set_even_footer((string) $xml_sheet->header_footer->even_footer);
            $doc_header_footer->set_first_header((string) $xml_sheet->header_footer->first_header);
            $doc_header_footer->set_first_footer((string) $xml_sheet->header_footer->first_footer);
        }
    }
    private function page_breaks(Simple_Xml_Element $xml_sheet, Worksheet $worksheet): void
    {
        if ($xml_sheet->row_breaks && $xml_sheet->row_breaks->brk) {
            $this->row_breaks($xml_sheet, $worksheet);
        }
        if ($xml_sheet->col_breaks && $xml_sheet->col_breaks->brk) {
            $this->column_breaks($xml_sheet, $worksheet);
        }
    }
    private function row_breaks(Simple_Xml_Element $xml_sheet, Worksheet $worksheet): void
    {
        foreach ($xml_sheet->row_breaks->brk as $brk) {
            $row_break_max = -1;
            if ($brk['man']) {
                $worksheet->set_break("A{$brk['id']}", Worksheet::BREAK_ROW, $row_break_max);
            }
        }
    }
    private function column_breaks(Simple_Xml_Element $xml_sheet, Worksheet $worksheet): void
    {
        foreach ($xml_sheet->col_breaks->brk as $brk) {
            if ($brk['man']) {
                $worksheet->set_break(Coordinate::string_from_column_index((int) $brk['id'] + 1) . '1', Worksheet::BREAK_COLUMN);
            }
        }
    }
}