<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Pdf;

use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Writer\Pdf;
class Dompdf extends Pdf
{
    /**
     * embed images, or link to images.
     */
    protected bool $embed_images = true;
    /**
     * Gets the implementation of external PDF library that should be used.
     *
     * @return \Dompdf\Dompdf implementation
     */
    protected function create_external_writer_instance(): \Dompdf\Dompdf
    {
        return new \Dompdf\Dompdf();
    }
    /**
     * Save Spreadsheet to file.
     *
     * @param string $filename Name of the file to save as
     */
    public function save($filename, int $flags = 0): void
    {
        $file_handle = parent::prepare_for_save($filename);
        //  Check for paper size and page orientation
        $setup = $this->spreadsheet->get_sheet($this->get_sheet_index() ?? 0)->get_page_setup();
        $orientation = $this->get_orientation() ?? $setup->get_orientation();
        $orientation = $orientation === Page_Setup::ORIENTATION_LANDSCAPE ? 'L' : 'P';
        $print_paper_size = $this->get_paper_size() ?? $setup->get_paper_size();
        $paper_size = self::$paper_sizes[$print_paper_size] ?? self::$paper_sizes[Page_Setup::get_paper_size_default()] ?? 'LETTER';
        if (is_array($paper_size) && count($paper_size) === 2) {
            $paper_size = [0.0, 0.0, $paper_size[0], $paper_size[1]];
        }
        $orientation = $orientation == 'L' ? 'landscape' : 'portrait';
        //  Create PDF
        $pdf = $this->create_external_writer_instance();
        $pdf->set_paper($paper_size, $orientation);
        $pdf->load_html($this->generate_html_all());
        $pdf->render();
        $this->call_page_script($pdf);
        //  Write to file
        fwrite($file_handle, $pdf->output());
        parent::restore_state_after_save();
    }
    protected function call_page_script(\Dompdf\Dompdf $pdf): void
    {
    }
}