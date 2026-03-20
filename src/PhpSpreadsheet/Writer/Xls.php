<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Rich_Text\Run;
use Php_Office\Php_Spreadsheet\Shared\Escher;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container\Sp_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE\Blip;
use Php_Office\Php_Spreadsheet\Shared\OLE;
use Php_Office\Php_Spreadsheet\Shared\OLE\PPS\File;
use Php_Office\Php_Spreadsheet\Shared\OLE\PPS\Root;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Xls\Parser;
use Php_Office\Php_Spreadsheet\Writer\Xls\Workbook;
use Php_Office\Php_Spreadsheet\Writer\Xls\Worksheet;
class Xls extends Base_Writer
{
    /**
     * Total number of shared strings in workbook.
     */
    private int $str_total = 0;
    /**
     * Number of unique shared strings in workbook.
     */
    private int $str_unique = 0;
    /**
     * Array of unique shared strings in workbook.
     *
     * @var array<string, int>
     */
    private array $str_table = [];
    /**
     * Color cache. Mapping between RGB value and color index.
     *
     * @var mixed[]
     */
    private array $colors;
    /**
     * Formula parser.
     */
    private readonly Parser $parser;
    /**
     * Identifier clusters for drawings. Used in MSODRAWINGGROUP record.
     *
     * @var mixed[]
     */
    private array $idc_ls;
    /**
     * Basic OLE object summary information.
     */
    private string $summary_information;
    /**
     * Extended OLE object document summary information.
     */
    private string $document_summary_information;
    private Workbook $writer_workbook;
    /**
     * @var Worksheet[]
     */
    private array $writer_worksheets;
    /**
     * Create a new Xls Writer.
     *
     * @param Spreadsheet $spreadsheet PhpSpreadsheet object
     */
    public function __construct(private readonly Spreadsheet $spreadsheet)
    {
        $this->parser = new Parser($this->spreadsheet);
    }
    /**
     * Save Spreadsheet to file.
     *
     * @param resource|string $filename
     */
    public function save($filename, int $flags = 0): void
    {
        $this->process_flags($flags);
        // garbage collect
        $this->spreadsheet->garbage_collect();
        $save_debug_log = Calculation::get_instance($this->spreadsheet)->get_debug_log()->get_write_debug_log();
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log(false);
        $save_date_return_type = Functions::get_return_date_type();
        Functions::set_return_date_type(Functions::RETURNDATE_EXCEL);
        // initialize colors array
        $this->colors = [];
        // Initialise workbook writer
        $this->writer_workbook = new Workbook($this->spreadsheet, $this->str_total, $this->str_unique, $this->str_table, $this->colors, $this->parser);
        // Initialise worksheet writers
        $count_sheets = $this->spreadsheet->get_sheet_count();
        for ($i = 0; $i < $count_sheets; ++$i) {
            $this->writer_worksheets[$i] = new Worksheet($this->str_total, $this->str_unique, $this->str_table, $this->colors, $this->parser, $this->pre_calculate_formulas, $this->spreadsheet->get_sheet($i), $this->writer_workbook);
        }
        // build Escher objects. Escher objects for worksheets need to be built before Escher object for workbook.
        $this->build_worksheet_eschers();
        $this->build_workbook_escher();
        // add 15 identical cell style Xfs
        // for now, we use the first cellXf instead of cellStyleXf
        $cell_xf_collection = $this->spreadsheet->get_cell_xf_collection();
        for ($i = 0; $i < 15; ++$i) {
            $this->writer_workbook->add_xf_writer($cell_xf_collection[0], true);
        }
        // add all the cell Xfs
        foreach ($this->spreadsheet->get_cell_xf_collection() as $style) {
            $this->writer_workbook->add_xf_writer($style, false);
        }
        // add fonts from rich text elements
        for ($i = 0; $i < $count_sheets; ++$i) {
            foreach ($this->writer_worksheets[$i]->php_sheet->get_cell_collection()->get_coordinates() as $coordinate) {
                /** @var Cell $cell */
                $cell = $this->writer_worksheets[$i]->php_sheet->get_cell_collection()->get($coordinate);
                $c_val = $cell->get_value();
                if ($c_val instanceof Rich_Text && (string) $c_val === '') {
                    $c_val = '';
                }
                if ($c_val instanceof Rich_Text) {
                    $active = $this->spreadsheet->get_active_sheet_index();
                    $sheet = $cell->get_worksheet();
                    $selected = $sheet->get_selected_cells();
                    $font = $cell->get_style()->get_font();
                    $this->writer_worksheets[$i]->font_hash_index[$font->get_hash_code()] = $this->writer_workbook->add_font($font);
                    $sheet->set_selected_cells($selected);
                    if ($active > -1) {
                        $this->spreadsheet->set_active_sheet_index($active);
                    }
                    $elements = $c_val->get_rich_text_elements();
                    foreach ($elements as $element) {
                        if ($element instanceof Run) {
                            $font = $element->get_font();
                            if ($font !== null) {
                                $this->writer_worksheets[$i]->font_hash_index[$font->get_hash_code()] = $this->writer_workbook->add_font($font);
                            }
                        }
                    }
                }
            }
        }
        // initialize OLE file
        $workbook_stream_name = 'Workbook';
        $OLE = new File(OLE::asc_to_ucs($workbook_stream_name));
        // Write the worksheet streams before the global workbook stream,
        // because the byte sizes of these are needed in the global workbook stream
        $worksheet_sizes = [];
        for ($i = 0; $i < $count_sheets; ++$i) {
            $this->writer_worksheets[$i]->close();
            $worksheet_sizes[] = $this->writer_worksheets[$i]->_datasize;
        }
        // add binary data for global workbook stream
        $OLE->append($this->writer_workbook->write_workbook($worksheet_sizes));
        // add binary data for sheet streams
        for ($i = 0; $i < $count_sheets; ++$i) {
            $OLE->append($this->writer_worksheets[$i]->get_data());
        }
        $this->document_summary_information = $this->write_document_summary_information();
        // initialize OLE Document Summary Information
        if (!empty($this->document_summary_information)) {
            $ole_document_summary_information = new File(OLE::asc_to_ucs(chr(5) . 'DocumentSummaryInformation'));
            $ole_document_summary_information->append($this->document_summary_information);
        }
        $this->summary_information = $this->write_summary_information();
        // initialize OLE Summary Information
        if (!empty($this->summary_information)) {
            $ole_summary_information = new File(OLE::asc_to_ucs(chr(5) . 'SummaryInformation'));
            $ole_summary_information->append($this->summary_information);
        }
        // define OLE Parts
        $arr_root_data = [$OLE];
        // initialize OLE Properties file
        if (isset($ole_summary_information)) {
            $arr_root_data[] = $ole_summary_information;
        }
        // initialize OLE Extended Properties file
        if (isset($ole_document_summary_information)) {
            $arr_root_data[] = $ole_document_summary_information;
        }
        $time = $this->spreadsheet->get_properties()->get_modified();
        $root = new Root($time, $time, $arr_root_data);
        // save the OLE file
        $this->open_file_handle($filename);
        $root->save($this->file_handle);
        $this->maybe_close_file_handle();
        Functions::set_return_date_type($save_date_return_type);
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log($save_debug_log);
    }
    /**
     * Build the Worksheet Escher objects.
     */
    private function build_worksheet_eschers(): void
    {
        // 1-based index to BstoreContainer
        $blip_index = 0;
        $last_reduced_sp_id = 0;
        $last_sp_id = 0;
        foreach ($this->spreadsheet->get_allsheets() as $sheet) {
            // sheet index
            $sheet_index = $sheet->get_parent_or_throw()->get_index($sheet);
            // check if there are any shapes for this sheet
            $filter_range = $sheet->get_auto_filter()->get_range();
            if (count($sheet->get_drawing_collection()) == 0 && empty($filter_range)) {
                continue;
            }
            // create intermediate Escher object
            $escher = new Escher();
            // dgContainer
            $dg_container = new Dg_Container();
            // set the drawing index (we use sheet index + 1)
            $dg_id = $sheet->get_parent_or_throw()->get_index($sheet) + 1;
            $dg_container->set_dg_id($dg_id);
            $escher->set_dg_container($dg_container);
            // spgrContainer
            $spgr_container = new Spgr_Container();
            $dg_container->set_spgr_container($spgr_container);
            // add one shape which is the group shape
            $sp_container = new Sp_Container();
            $sp_container->set_spgr(true);
            $sp_container->set_sp_type(0);
            $sp_container->set_sp_id($sheet->get_parent_or_throw()->get_index($sheet) + 1 << 10);
            $spgr_container->add_child($sp_container);
            // add the shapes
            $count_shapes[$sheet_index] = 0;
            // count number of shapes (minus group shape), in sheet
            foreach ($sheet->get_drawing_collection() as $drawing) {
                ++$blip_index;
                ++$count_shapes[$sheet_index];
                // add the shape
                $sp_container = new Sp_Container();
                // set the shape type
                $sp_container->set_sp_type(0x4b);
                // set the shape flag
                $sp_container->set_sp_flag(0x2);
                // set the shape index (we combine 1-based sheet index and $countShapes to create unique shape index)
                $reduced_sp_id = $count_shapes[$sheet_index];
                $sp_id = $reduced_sp_id | $sheet->get_parent_or_throw()->get_index($sheet) + 1 << 10;
                $sp_container->set_sp_id($sp_id);
                // keep track of last reducedSpId
                $last_reduced_sp_id = $reduced_sp_id;
                // keep track of last spId
                $last_sp_id = $sp_id;
                // set the BLIP index
                $sp_container->set_opt(0x4104, $blip_index);
                // set coordinates and offsets, client anchor
                $coordinates = $drawing->get_coordinates();
                $offset_x = $drawing->get_offset_x();
                $offset_y = $drawing->get_offset_y();
                $width = $drawing->get_width();
                $height = $drawing->get_height();
                $two_anchor = \Php_Office\Php_Spreadsheet\Shared\Xls::one_anchor2two_anchor($sheet, $coordinates, $offset_x, $offset_y, $width, $height);
                if (is_array($two_anchor)) {
                    /** @var array{startCoordinates: string, startOffsetX: float|int, startOffsetY: float|int, endCoordinates: string, endOffsetX: float|int, endOffsetY: float|int} $twoAnchor */
                    $sp_container->set_start_coordinates($two_anchor['startCoordinates']);
                    $sp_container->set_start_offset_x($two_anchor['startOffsetX']);
                    $sp_container->set_start_offset_y($two_anchor['startOffsetY']);
                    $sp_container->set_end_coordinates($two_anchor['endCoordinates']);
                    $sp_container->set_end_offset_x($two_anchor['endOffsetX']);
                    $sp_container->set_end_offset_y($two_anchor['endOffsetY']);
                    $spgr_container->add_child($sp_container);
                }
            }
            // AutoFilters
            if (!empty($filter_range)) {
                $range_bounds = Coordinate::range_boundaries($filter_range);
                $i_num_col_start = $range_bounds[0][0];
                $i_num_col_end = $range_bounds[1][0];
                $i_inc = $i_num_col_start;
                while ($i_inc <= $i_num_col_end) {
                    ++$count_shapes[$sheet_index];
                    // create a Drawing Object for the dropdown
                    $o_drawing = new Base_Drawing();
                    // get the coordinates of drawing
                    $c_drawing = Coordinate::string_from_column_index($i_inc) . $range_bounds[0][1];
                    $o_drawing->set_coordinates($c_drawing);
                    $o_drawing->set_worksheet($sheet);
                    // add the shape
                    $sp_container = new Sp_Container();
                    // set the shape type
                    $sp_container->set_sp_type(0xc9);
                    // set the shape flag
                    $sp_container->set_sp_flag(0x1);
                    // set the shape index (we combine 1-based sheet index and $countShapes to create unique shape index)
                    $reduced_sp_id = $count_shapes[$sheet_index];
                    $sp_id = $reduced_sp_id | $sheet->get_parent_or_throw()->get_index($sheet) + 1 << 10;
                    $sp_container->set_sp_id($sp_id);
                    // keep track of last reducedSpId
                    $last_reduced_sp_id = $reduced_sp_id;
                    // keep track of last spId
                    $last_sp_id = $sp_id;
                    $sp_container->set_opt(0x7f, 0x1040104);
                    // Protection -> fLockAgainstGrouping
                    $sp_container->set_opt(0xbf, 0x80008);
                    // Text -> fFitTextToShape
                    $sp_container->set_opt(0x1bf, 0x10000);
                    // Fill Style -> fNoFillHitTest
                    $sp_container->set_opt(0x1ff, 0x80000);
                    // Line Style -> fNoLineDrawDash
                    $sp_container->set_opt(0x3bf, 0xa0000);
                    // Group Shape -> fPrint
                    // set coordinates and offsets, client anchor
                    $end_coordinates = Coordinate::string_from_column_index($i_inc);
                    $end_coordinates .= $range_bounds[0][1] + 1;
                    $sp_container->set_start_coordinates($c_drawing);
                    $sp_container->set_start_offset_x(0);
                    $sp_container->set_start_offset_y(0);
                    $sp_container->set_end_coordinates($end_coordinates);
                    $sp_container->set_end_offset_x(0);
                    $sp_container->set_end_offset_y(0);
                    $spgr_container->add_child($sp_container);
                    ++$i_inc;
                }
            }
            // identifier clusters, used for workbook Escher object
            $this->idc_ls[$dg_id] = $last_reduced_sp_id;
            // set last shape index
            $dg_container->set_last_sp_id($last_sp_id);
            // set the Escher object
            $this->writer_worksheets[$sheet_index]->set_escher($escher);
        }
    }
    private function process_memory_drawing(Bstore_Container &$bstore_container, Memory_Drawing $drawing, string $rendering_functionx): void
    {
        switch ($rendering_functionx) {
            case Memory_Drawing::RENDERING_JPEG:
                $blip_type = BSE::BLIPTYPE_JPEG;
                $rendering_function = 'imagejpeg';
                break;
            default:
                $blip_type = BSE::BLIPTYPE_PNG;
                $rendering_function = 'imagepng';
                break;
        }
        ob_start();
        call_user_func($rendering_function, $drawing->get_image_resource());
        // @phpstan-ignore-line
        $blip_data = ob_get_contents();
        ob_end_clean();
        $blip = new Blip();
        $blip->set_data("{$blip_data}");
        $BSE = new BSE();
        $BSE->set_blip_type($blip_type);
        $BSE->set_blip($blip);
        $bstore_container->add_bse($BSE);
    }
    private static int $two = 2;
    // phpstan silliness
    private function process_drawing(Bstore_Container &$bstore_container, Drawing $drawing): void
    {
        $blip_type = 0;
        $blip_data = '';
        $filename = $drawing->get_path();
        $image_size = getimagesize($filename);
        $image_format = empty($image_size) ? 0 : $image_size[self::$two] ?? 0;
        switch ($image_format) {
            case 1:
                // GIF, not supported by BIFF8, we convert to PNG
                $blip_type = BSE::BLIPTYPE_PNG;
                $new_image = @imagecreatefromgif($filename);
                if ($new_image === false) {
                    throw new Exception("Unable to create image from {$filename}");
                }
                ob_start();
                imagepng($new_image);
                $blip_data = ob_get_contents();
                ob_end_clean();
                break;
            case 2:
                // JPEG
                $blip_type = BSE::BLIPTYPE_JPEG;
                $blip_data = file_get_contents($filename);
                break;
            case 3:
                // PNG
                $blip_type = BSE::BLIPTYPE_PNG;
                $blip_data = file_get_contents($filename);
                break;
            case 6:
                // Windows DIB (BMP), we convert to PNG
                $blip_type = BSE::BLIPTYPE_PNG;
                $new_image = @imagecreatefrombmp($filename);
                if ($new_image === false) {
                    throw new Exception("Unable to create image from {$filename}");
                }
                ob_start();
                imagepng($new_image);
                $blip_data = ob_get_contents();
                ob_end_clean();
                break;
        }
        if ($blip_data) {
            $blip = new Blip();
            $blip->set_data($blip_data);
            $BSE = new BSE();
            $BSE->set_blip_type($blip_type);
            $BSE->set_blip($blip);
            $bstore_container->add_bse($BSE);
        }
    }
    private function process_base_drawing(Bstore_Container &$bstore_container, Base_Drawing $drawing): void
    {
        if ($drawing instanceof Drawing && $drawing->get_path() !== '') {
            $this->process_drawing($bstore_container, $drawing);
        } elseif ($drawing instanceof Memory_Drawing) {
            $this->process_memory_drawing($bstore_container, $drawing, $drawing->get_rendering_function());
        }
    }
    private function check_for_drawings(): bool
    {
        // any drawings in this workbook?
        $found = false;
        foreach ($this->spreadsheet->get_all_sheets() as $sheet) {
            if (count($sheet->get_drawing_collection()) > 0) {
                $found = true;
                break;
            }
        }
        return $found;
    }
    /**
     * Build the Escher object corresponding to the MSODRAWINGGROUP record.
     */
    private function build_workbook_escher(): void
    {
        // nothing to do if there are no drawings
        if (!$this->check_for_drawings()) {
            return;
        }
        // if we reach here, then there are drawings in the workbook
        $escher = new Escher();
        // dggContainer
        $dgg_container = new Dgg_Container();
        $escher->set_dgg_container($dgg_container);
        // set IDCLs (identifier clusters)
        $dgg_container->set_idc_ls($this->idc_ls);
        // this loop is for determining maximum shape identifier of all drawing
        $sp_id_max = 0;
        $total_count_shapes = 0;
        $count_drawings = 0;
        foreach ($this->spreadsheet->get_allsheets() as $sheet) {
            $sheet_count_shapes = 0;
            // count number of shapes (minus group shape), in sheet
            $add_count = 0;
            foreach ($sheet->get_drawing_collection() as $drawing) {
                $add_count = 1;
                ++$sheet_count_shapes;
                ++$total_count_shapes;
                $sp_id = $sheet_count_shapes | $this->spreadsheet->get_index($sheet) + 1 << 10;
                $sp_id_max = max($sp_id, $sp_id_max);
            }
            $count_drawings += $add_count;
        }
        $dgg_container->set_sp_id_max($sp_id_max + 1);
        $dgg_container->set_c_dg_saved($count_drawings);
        $dgg_container->set_c_sp_saved($total_count_shapes + $count_drawings);
        // total number of shapes incl. one group shapes per drawing
        // bstoreContainer
        $bstore_container = new Bstore_Container();
        $dgg_container->set_bstore_container($bstore_container);
        // the BSE's (all the images)
        foreach ($this->spreadsheet->get_allsheets() as $sheet) {
            foreach ($sheet->get_drawing_collection() as $drawing) {
                $this->process_base_drawing($bstore_container, $drawing);
            }
        }
        // Set the Escher object
        $this->writer_workbook->set_escher($escher);
    }
    /**
     * Build the OLE Part for DocumentSummary Information.
     */
    private function write_document_summary_information(): string
    {
        // offset: 0; size: 2; must be 0xFE 0xFF (UTF-16 LE byte order mark)
        $data = pack('v', 0xfffe);
        // offset: 2; size: 2;
        $data .= pack('v', 0x0);
        // offset: 4; size: 2; OS version
        $data .= pack('v', 0x106);
        // offset: 6; size: 2; OS indicator
        $data .= pack('v', 0x2);
        // offset: 8; size: 16
        $data .= pack('VVVV', 0x0, 0x0, 0x0, 0x0);
        // offset: 24; size: 4; section count
        $data .= pack('V', 0x1);
        // offset: 28; size: 16; first section's class id: 02 d5 cd d5 9c 2e 1b 10 93 97 08 00 2b 2c f9 ae
        $data .= pack('vvvvvvvv', 0xd502, 0xd5cd, 0x2e9c, 0x101b, 0x9793, 0x8, 0x2c2b, 0xaef9);
        // offset: 44; size: 4; offset of the start
        $data .= pack('V', 0x30);
        // SECTION
        $data_section = [];
        $data_section_num_props = 0;
        $data_section_summary = '';
        $data_section_content = '';
        // GKPIDDSI_CODEPAGE: CodePage
        $data_section[] = [
            'summary' => ['pack' => 'V', 'data' => 0x1],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x2],
            // 2 byte signed integer
            'data' => ['data' => 1252],
        ];
        ++$data_section_num_props;
        // GKPIDDSI_CATEGORY : Category
        $data_prop = $this->spreadsheet->get_properties()->get_category();
        if ($data_prop) {
            $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0x2], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x1e], 'data' => ['data' => $data_prop, 'length' => strlen($data_prop)]];
            ++$data_section_num_props;
        }
        // GKPIDDSI_VERSION :Version of the application that wrote the property storage
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0x17], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x3], 'data' => ['pack' => 'V', 'data' => 0xc0000]];
        ++$data_section_num_props;
        // GKPIDDSI_SCALE : FALSE
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0xb], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => false]];
        ++$data_section_num_props;
        // GKPIDDSI_LINKSDIRTY : True if any of the values for the linked properties have changed outside of the application
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0x10], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => false]];
        ++$data_section_num_props;
        // GKPIDDSI_SHAREDOC : FALSE
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0x13], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => false]];
        ++$data_section_num_props;
        // GKPIDDSI_HYPERLINKSCHANGED : True if any of the values for the _PID_LINKS (hyperlink text) have changed outside of the application
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0x16], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => false]];
        ++$data_section_num_props;
        // GKPIDDSI_DOCSPARTS
        // MS-OSHARED p75 (2.3.3.2.2.1)
        // Structure is VtVecUnalignedLpstrValue (2.3.3.1.9)
        // cElements
        $data_prop = pack('v', 0x1);
        $data_prop .= pack('v', 0x0);
        // array of UnalignedLpstr
        // cch
        $data_prop .= pack('v', 0xa);
        $data_prop .= pack('v', 0x0);
        // value
        $data_prop .= 'Worksheet' . chr(0);
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0xd], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x101e], 'data' => ['data' => $data_prop, 'length' => strlen($data_prop)]];
        ++$data_section_num_props;
        // GKPIDDSI_HEADINGPAIR
        // VtVecHeadingPairValue
        // cElements
        $data_prop = pack('v', 0x2);
        $data_prop .= pack('v', 0x0);
        // Array of vtHeadingPair
        // vtUnalignedString - headingString
        // stringType
        $data_prop .= pack('v', 0x1e);
        // padding
        $data_prop .= pack('v', 0x0);
        // UnalignedLpstr
        // cch
        $data_prop .= pack('v', 0x13);
        $data_prop .= pack('v', 0x0);
        // value
        $data_prop .= 'Feuilles de calcul';
        // vtUnalignedString - headingParts
        // wType : 0x0003 = 32-bit signed integer
        $data_prop .= pack('v', 0x300);
        // padding
        $data_prop .= pack('v', 0x0);
        // value
        $data_prop .= pack('v', 0x100);
        $data_prop .= pack('v', 0x0);
        $data_prop .= pack('v', 0x0);
        $data_prop .= pack('v', 0x0);
        $data_section[] = ['summary' => ['pack' => 'V', 'data' => 0xc], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x100c], 'data' => ['data' => $data_prop, 'length' => strlen($data_prop)]];
        ++$data_section_num_props;
        //         4     Section Length
        //        4     Property count
        //        8 * $dataSection_NumProps (8 =  ID (4) + OffSet(4))
        $data_section_content_offset = 8 + $data_section_num_props * 8;
        foreach ($data_section as $data_prop) {
            // Summary
            $data_section_summary .= pack($data_prop['summary']['pack'], $data_prop['summary']['data']);
            // Offset
            $data_section_summary .= pack($data_prop['offset']['pack'], $data_section_content_offset);
            // DataType
            $data_section_content .= pack($data_prop['type']['pack'], $data_prop['type']['data']);
            // Data
            if ($data_prop['type']['data'] == 0x2) {
                // 2 byte signed integer
                $data_section_content .= pack('V', $data_prop['data']['data']);
                $data_section_content_offset += 4 + 4;
            } elseif ($data_prop['type']['data'] == 0x3) {
                // 4 byte signed integer
                $data_section_content .= pack('V', $data_prop['data']['data']);
                $data_section_content_offset += 4 + 4;
            } elseif ($data_prop['type']['data'] == 0xb) {
                // Boolean
                $data_section_content .= pack('V', (int) $data_prop['data']['data']);
                $data_section_content_offset += 4 + 4;
            } elseif ($data_prop['type']['data'] == 0x1e) {
                // null-terminated string prepended by dword string length
                // Null-terminated string
                $data_prop['data']['data'] .= chr(0);
                ++$data_prop['data']['length'];
                // Complete the string with null string for being a %4
                $data_prop['data']['length'] = $data_prop['data']['length'] + (4 - $data_prop['data']['length'] % 4 == 4 ? 0 : 4 - $data_prop['data']['length'] % 4);
                $data_prop['data']['data'] = str_pad($data_prop['data']['data'], $data_prop['data']['length'], chr(0), STR_PAD_RIGHT);
                $data_section_content .= pack('V', $data_prop['data']['length']);
                $data_section_content .= $data_prop['data']['data'];
                $data_section_content_offset += 4 + 4 + strlen($data_prop['data']['data']);
            } else {
                $data_section_content .= $data_prop['data']['data'];
                $data_section_content_offset += 4 + $data_prop['data']['length'];
            }
        }
        // Now $dataSection_Content_Offset contains the size of the content
        // section header
        // offset: $secOffset; size: 4; section length
        //         + x  Size of the content (summary + content)
        $data .= pack('V', $data_section_content_offset);
        // offset: $secOffset+4; size: 4; property count
        $data .= pack('V', $data_section_num_props);
        // Section Summary
        $data .= $data_section_summary;
        // Section Content
        $data .= $data_section_content;
        return $data;
    }
    /** @param array<int, array{summary: array{pack: string, data: mixed}, offset: array{pack: string}, type: array{pack: string, data: int}, data: array{data: mixed}}> $dataSection */
    private function write_summary_prop_ole(float|int $data_prop, int &$data_section_num_props, array &$data_section, int $sumdata, int $typdata): void
    {
        if ($data_prop) {
            $data_section[] = [
                'summary' => ['pack' => 'V', 'data' => $sumdata],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => $typdata],
                // null-terminated string prepended by dword string length
                'data' => ['data' => OLE::local_date_to_ole($data_prop)],
            ];
            ++$data_section_num_props;
        }
    }
    /** @param array<int, array{summary: array{pack: string, data: mixed}, offset: array{pack: string}, type: array{pack: string, data: int}, data: array{data: mixed}}> $dataSection */
    private function write_summary_prop(string $data_prop, int &$data_section_num_props, array &$data_section, int $sumdata, int $typdata): void
    {
        if ($data_prop) {
            $data_section[] = [
                'summary' => ['pack' => 'V', 'data' => $sumdata],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => $typdata],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $data_prop, 'length' => strlen($data_prop)],
            ];
            ++$data_section_num_props;
        }
    }
    /**
     * Build the OLE Part for Summary Information.
     */
    private function write_summary_information(): string
    {
        // offset: 0; size: 2; must be 0xFE 0xFF (UTF-16 LE byte order mark)
        $data = pack('v', 0xfffe);
        // offset: 2; size: 2;
        $data .= pack('v', 0x0);
        // offset: 4; size: 2; OS version
        $data .= pack('v', 0x106);
        // offset: 6; size: 2; OS indicator
        $data .= pack('v', 0x2);
        // offset: 8; size: 16
        $data .= pack('VVVV', 0x0, 0x0, 0x0, 0x0);
        // offset: 24; size: 4; section count
        $data .= pack('V', 0x1);
        // offset: 28; size: 16; first section's class id: e0 85 9f f2 f9 4f 68 10 ab 91 08 00 2b 27 b3 d9
        $data .= pack('vvvvvvvv', 0x85e0, 0xf29f, 0x4ff9, 0x1068, 0x91ab, 0x8, 0x272b, 0xd9b3);
        // offset: 44; size: 4; offset of the start
        $data .= pack('V', 0x30);
        // SECTION
        $data_section = [];
        $data_section_num_props = 0;
        $data_section_summary = '';
        $data_section_content = '';
        // CodePage : CP-1252
        $data_section[] = [
            'summary' => ['pack' => 'V', 'data' => 0x1],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x2],
            // 2 byte signed integer
            'data' => ['data' => 1252],
        ];
        ++$data_section_num_props;
        $props = $this->spreadsheet->get_properties();
        $this->write_summary_prop($props->get_title(), $data_section_num_props, $data_section, 0x2, 0x1e);
        $this->write_summary_prop($props->get_subject(), $data_section_num_props, $data_section, 0x3, 0x1e);
        $this->write_summary_prop($props->get_creator(), $data_section_num_props, $data_section, 0x4, 0x1e);
        $this->write_summary_prop($props->get_keywords(), $data_section_num_props, $data_section, 0x5, 0x1e);
        $this->write_summary_prop($props->get_description(), $data_section_num_props, $data_section, 0x6, 0x1e);
        $this->write_summary_prop($props->get_last_modified_by(), $data_section_num_props, $data_section, 0x8, 0x1e);
        $this->write_summary_prop_ole($props->get_created(), $data_section_num_props, $data_section, 0xc, 0x40);
        $this->write_summary_prop_ole($props->get_modified(), $data_section_num_props, $data_section, 0xd, 0x40);
        //    Security
        $data_section[] = [
            'summary' => ['pack' => 'V', 'data' => 0x13],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x3],
            // 4 byte signed integer
            'data' => ['data' => 0x0],
        ];
        ++$data_section_num_props;
        //         4     Section Length
        //        4     Property count
        //        8 * $dataSection_NumProps (8 =  ID (4) + OffSet(4))
        $data_section_content_offset = 8 + $data_section_num_props * 8;
        foreach ($data_section as $data_prop) {
            /** @var array{data: array{data: string, length: int}, summary: array{pack: string, data: string}, offset: array{pack: string}, type: array{data: int, pack: string}} $dataProp */
            // Summary
            $data_section_summary .= pack($data_prop['summary']['pack'], $data_prop['summary']['data']);
            // Offset
            $data_section_summary .= pack($data_prop['offset']['pack'], $data_section_content_offset);
            // DataType
            $data_section_content .= pack($data_prop['type']['pack'], $data_prop['type']['data']);
            // Data
            if ($data_prop['type']['data'] == 0x2) {
                // 2 byte signed integer
                $data_section_content .= pack('V', $data_prop['data']['data']);
                $data_section_content_offset += 4 + 4;
            } elseif ($data_prop['type']['data'] == 0x3) {
                // 4 byte signed integer
                $data_section_content .= pack('V', $data_prop['data']['data']);
                $data_section_content_offset += 4 + 4;
            } elseif ($data_prop['type']['data'] == 0x1e) {
                // null-terminated string prepended by dword string length
                // Null-terminated string
                $data_prop['data']['data'] .= chr(0);
                ++$data_prop['data']['length'];
                // Complete the string with null string for being a %4
                $data_prop['data']['length'] = $data_prop['data']['length'] + (4 - $data_prop['data']['length'] % 4 == 4 ? 0 : 4 - $data_prop['data']['length'] % 4);
                $data_prop['data']['data'] = str_pad($data_prop['data']['data'], $data_prop['data']['length'], chr(0), STR_PAD_RIGHT);
                $data_section_content .= pack('V', $data_prop['data']['length']);
                $data_section_content .= $data_prop['data']['data'];
                $data_section_content_offset += 4 + 4 + strlen($data_prop['data']['data']);
            } elseif ($data_prop['type']['data'] == 0x40) {
                // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                $data_section_content .= $data_prop['data']['data'];
                $data_section_content_offset += 4 + 8;
            }
            // Data Type Not Used at the moment
        }
        // Now $dataSection_Content_Offset contains the size of the content
        // section header
        // offset: $secOffset; size: 4; section length
        //         + x  Size of the content (summary + content)
        $data .= pack('V', $data_section_content_offset);
        // offset: $secOffset+4; size: 4; property count
        $data .= pack('V', $data_section_num_props);
        // Section Summary
        $data .= $data_section_summary;
        // Section Content
        $data .= $data_section_content;
        return $data;
    }
}