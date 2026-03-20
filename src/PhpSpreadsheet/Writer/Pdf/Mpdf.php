<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Pdf;

use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Writer\Pdf;
class Mpdf extends Pdf
{
    public const SIMULATED_BODY_START = '<!-- simulated body start -->';
    private const BODY_TAG = '<body>';
    /**
     * Gets the implementation of external PDF library that should be used.
     *
     * @param mixed[] $config Configuration array
     *
     * @return \Mpdf\Mpdf implementation
     */
    protected function create_external_writer_instance(array $config): \Mpdf\Mpdf
    {
        return new \Mpdf\Mpdf($config);
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
        $paper_size = self::$paper_sizes[$print_paper_size] ?? Page_Setup::get_paper_size_default();
        //  Create PDF
        $config = ['tempDir' => $this->temp_dir . '/mpdf'];
        $pdf = $this->create_external_writer_instance($config);
        $ortmp = $orientation;
        $pdf->_set_page_size($paper_size, $ortmp);
        $pdf->def_orientation = $orientation;
        $pdf->add_page_by_array(['orientation' => $orientation, 'margin-left' => $this->inches_to_mm($this->spreadsheet->get_active_sheet()->get_page_margins()->get_left()), 'margin-right' => $this->inches_to_mm($this->spreadsheet->get_active_sheet()->get_page_margins()->get_right()), 'margin-top' => $this->inches_to_mm($this->spreadsheet->get_active_sheet()->get_page_margins()->get_top()), 'margin-bottom' => $this->inches_to_mm($this->spreadsheet->get_active_sheet()->get_page_margins()->get_bottom())]);
        //  Document info
        $pdf->set_title($this->spreadsheet->get_properties()->get_title());
        $pdf->set_author($this->spreadsheet->get_properties()->get_creator());
        $pdf->set_subject($this->spreadsheet->get_properties()->get_subject());
        $pdf->set_keywords($this->spreadsheet->get_properties()->get_keywords());
        $pdf->set_creator($this->spreadsheet->get_properties()->get_creator());
        $html = $this->generate_html_all();
        $body_location = strpos($html, self::SIMULATED_BODY_START);
        if ($body_location === false) {
            $body_location = strpos($html, self::BODY_TAG);
            if ($body_location !== false) {
                $body_location += strlen(self::BODY_TAG);
            }
        }
        // Make sure first data presented to Mpdf includes body tag
        //   (and any htmlpageheader/htmlpagefooter tags)
        //   so that Mpdf doesn't parse it as content. Issue 2432.
        if ($body_location !== false) {
            $pdf->write_html(substr($html, 0, $body_location));
            $html = substr($html, $body_location);
        }
        foreach (explode("\n", $html) as $line) {
            $pdf->write_html("{$line}\n");
        }
        //  Write to file
        /** @var string */
        $str = $pdf->Output('', 'S');
        fwrite($file_handle, $str);
        parent::restore_state_after_save();
    }
    /**
     * Convert inches to mm.
     */
    private function inches_to_mm(float $inches): float
    {
        return $inches * 25.4;
    }
}