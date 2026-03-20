<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Pdf;

use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Writer\Pdf;
class Tcpdf extends Pdf
{
    protected bool $write_header = false;
    protected bool $write_footer = false;
    /**
     * Create a new PDF Writer instance.
     *
     * @param Spreadsheet $spreadsheet Spreadsheet object
     */
    public function __construct(Spreadsheet $spreadsheet)
    {
        parent::__construct($spreadsheet);
        $this->set_use_inline_css(true);
    }
    /**
     * Gets the implementation of external PDF library that should be used.
     *
     * @param string $orientation Page orientation
     * @param string $unit Unit measure
     * @param float[]|string $paperSize Paper size
     *
     * @return \TCPDF implementation
     */
    protected function create_external_writer_instance(string $orientation, string $unit, $paper_size): \TCPDF
    {
        $this->defines();
        return new \TCPDF($orientation, $unit, $paper_size);
    }
    protected function defines(): void
    {
    }
    /**
     * Save Spreadsheet to file.
     *
     * @param string $filename Name of the file to save as
     */
    public function save($filename, int $flags = 0): void
    {
        $file_handle = parent::prepare_for_save($filename);
        //  Default PDF paper size
        $paper_size = 'LETTER';
        //    Letter    (8.5 in. by 11 in.)
        //  Check for paper size and page orientation
        $setup = $this->spreadsheet->get_sheet($this->get_sheet_index() ?? 0)->get_page_setup();
        $orientation = $this->get_orientation() ?? $setup->get_orientation();
        $orientation = $orientation === Page_Setup::ORIENTATION_LANDSCAPE ? 'L' : 'P';
        $print_paper_size = $this->get_paper_size() ?? $setup->get_paper_size();
        $paper_size = self::$paper_sizes[$print_paper_size] ?? self::$paper_sizes[Page_Setup::get_paper_size_default()] ?? 'LETTER';
        $print_margins = $this->spreadsheet->get_sheet($this->get_sheet_index() ?? 0)->get_page_margins();
        //  Create PDF
        $pdf = $this->create_external_writer_instance($orientation, 'pt', $paper_size);
        $pdf->set_font_subsetting(false);
        //    Set margins, converting inches to points (using 72 dpi)
        $pdf->set_margins($print_margins->get_left() * 72, $print_margins->get_top() * 72, $print_margins->get_right() * 72);
        $pdf->set_auto_page_break(true, $print_margins->get_bottom() * 72);
        $pdf->set_print_header($this->write_header);
        $pdf->set_print_footer($this->write_footer);
        $pdf->add_page();
        //  Set the appropriate font
        $pdf->set_font($this->get_font());
        $this->check_rtl_and_ltr();
        if ($this->rtl_sheets && !$this->ltr_sheets) {
            $pdf->set_rtl(true);
        }
        $pdf->write_html($this->generate_html_all());
        //  Document info
        $pdf->set_title($this->spreadsheet->get_properties()->get_title());
        $pdf->set_author($this->spreadsheet->get_properties()->get_creator());
        $pdf->set_subject($this->spreadsheet->get_properties()->get_subject());
        $pdf->set_keywords($this->spreadsheet->get_properties()->get_keywords());
        $pdf->set_creator($this->spreadsheet->get_properties()->get_creator());
        //  Write to file
        fwrite($file_handle, $pdf->output('', 'S'));
        parent::restore_state_after_save();
    }
}