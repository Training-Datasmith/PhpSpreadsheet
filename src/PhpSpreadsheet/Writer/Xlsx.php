<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Hash_Table;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing as WorksheetDrawing;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Chart;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Comments;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Content_Types;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Doc_Props;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Drawing;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Rels;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Rels_Ribbon;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Rels_Vba;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Rich_Data_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\String_Table;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Style;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Table;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Theme;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Workbook;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Worksheet;
use Zip_Archive;
use Zip_Stream\Exception\OverflowException;
use Zip_Stream\Zip_Stream;
class Xlsx extends Base_Writer
{
    /**
     * Office2003 compatibility.
     */
    private bool $office2003compatibility = false;
    /**
     * Private Spreadsheet.
     */
    private Spreadsheet $spread_sheet;
    /**
     * Private string table.
     *
     * @var string[]
     */
    private array $string_table = [];
    /**
     * Private unique Conditional HashTable.
     *
     * @var HashTable<Conditional>
     */
    private readonly Hash_Table $styles_conditional_hash_table;
    /**
     * Private unique Style HashTable.
     *
     * @var HashTable<\PhpOffice\PhpSpreadsheet\Style\Style>
     */
    private readonly Hash_Table $style_hash_table;
    /**
     * Private unique Fill HashTable.
     *
     * @var HashTable<Fill>
     */
    private readonly Hash_Table $fill_hash_table;
    /**
     * Private unique \PhpOffice\PhpSpreadsheet\Style\Font HashTable.
     *
     * @var HashTable<Font>
     */
    private readonly Hash_Table $font_hash_table;
    /**
     * Private unique Borders HashTable.
     *
     * @var HashTable<Borders>
     */
    private readonly Hash_Table $borders_hash_table;
    /**
     * Private unique NumberFormat HashTable.
     *
     * @var HashTable<NumberFormat>
     */
    private readonly Hash_Table $num_fmt_hash_table;
    /**
     * Private unique \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet\BaseDrawing HashTable.
     *
     * @var HashTable<BaseDrawing>
     */
    private readonly Hash_Table $drawing_hash_table;
    /**
     * Private handle for zip stream.
     */
    private Zip_Stream $zip;
    private readonly Chart $writer_part_chart;
    private readonly Comments $writer_part_comments;
    private readonly Content_Types $writer_part_content_types;
    private readonly Doc_Props $writer_part_doc_props;
    private readonly Drawing $writer_part_drawing;
    private readonly Rels $writer_part_rels;
    private readonly Rels_Ribbon $writer_part_rels_ribbon;
    private readonly Rels_Vba $writer_part_rels_vba;
    private readonly String_Table $writer_part_string_table;
    private readonly Style $writer_part_style;
    private readonly Theme $writer_part_theme;
    private readonly Table $writer_part_table;
    private readonly Workbook $writer_part_workbook;
    private readonly Worksheet $writer_part_worksheet;
    private bool $explicit_style0 = false;
    private bool $use_cse_arrays = false;
    private bool $use_dynamic_array = false;
    public const DEFAULT_FORCE_FULL_CALC = false;
    // Default changed from null in PhpSpreadsheet 4.0.0.
    private ?bool $force_full_calc = self::DEFAULT_FORCE_FULL_CALC;
    protected bool $restrict_max_column_width = false;
    /**
     * Create a new Xlsx Writer.
     */
    public function __construct(Spreadsheet $spreadsheet)
    {
        // Assign PhpSpreadsheet
        $this->set_spreadsheet($spreadsheet);
        $spreadsheet->set_uses_checkbox_style();
        $this->writer_part_chart = new Chart($this);
        $this->writer_part_comments = new Comments($this);
        $this->writer_part_content_types = new Content_Types($this);
        $this->writer_part_doc_props = new Doc_Props($this);
        $this->writer_part_drawing = new Drawing($this);
        $this->writer_part_rels = new Rels($this);
        $this->writer_part_rels_ribbon = new Rels_Ribbon($this);
        $this->writer_part_rels_vba = new Rels_Vba($this);
        $this->writer_part_string_table = new String_Table($this);
        $this->writer_part_style = new Style($this);
        $this->writer_part_theme = new Theme($this);
        $this->writer_part_table = new Table($this);
        $this->writer_part_workbook = new Workbook($this);
        $this->writer_part_worksheet = new Worksheet($this);
        // Set HashTable variables
        $this->borders_hash_table = new Hash_Table();
        $this->drawing_hash_table = new Hash_Table();
        $this->fill_hash_table = new Hash_Table();
        $this->font_hash_table = new Hash_Table();
        $this->num_fmt_hash_table = new Hash_Table();
        $this->style_hash_table = new Hash_Table();
        $this->styles_conditional_hash_table = new Hash_Table();
        $this->determine_use_dynamic_arrays();
    }
    public function get_writer_part_chart(): Chart
    {
        return $this->writer_part_chart;
    }
    public function get_writer_part_comments(): Comments
    {
        return $this->writer_part_comments;
    }
    public function get_writer_part_content_types(): Content_Types
    {
        return $this->writer_part_content_types;
    }
    public function get_writer_part_doc_props(): Doc_Props
    {
        return $this->writer_part_doc_props;
    }
    public function get_writer_part_drawing(): Drawing
    {
        return $this->writer_part_drawing;
    }
    public function get_writer_part_rels(): Rels
    {
        return $this->writer_part_rels;
    }
    public function get_writer_part_rels_ribbon(): Rels_Ribbon
    {
        return $this->writer_part_rels_ribbon;
    }
    public function get_writer_part_rels_vba(): Rels_Vba
    {
        return $this->writer_part_rels_vba;
    }
    public function get_writer_part_string_table(): String_Table
    {
        return $this->writer_part_string_table;
    }
    public function get_writer_part_style(): Style
    {
        return $this->writer_part_style;
    }
    public function get_writer_part_theme(): Theme
    {
        return $this->writer_part_theme;
    }
    public function get_writer_part_table(): Table
    {
        return $this->writer_part_table;
    }
    public function get_writer_part_workbook(): Workbook
    {
        return $this->writer_part_workbook;
    }
    public function get_writer_part_worksheet(): Worksheet
    {
        return $this->writer_part_worksheet;
    }
    public function create_style_dictionaries(): void
    {
        $this->style_hash_table->add_from_source($this->get_writer_part_style()->all_styles($this->spread_sheet));
        $this->styles_conditional_hash_table->add_from_source($this->get_writer_part_style()->all_conditional_styles($this->spread_sheet));
        $this->fill_hash_table->add_from_source($this->get_writer_part_style()->all_fills($this->spread_sheet));
        $this->font_hash_table->add_from_source($this->get_writer_part_style()->all_fonts($this->spread_sheet));
        $this->borders_hash_table->add_from_source($this->get_writer_part_style()->all_borders($this->spread_sheet));
        $this->num_fmt_hash_table->add_from_source($this->get_writer_part_style()->all_number_formats($this->spread_sheet));
    }
    /**
     * @return (RichText|string)[] $stringTable
     */
    public function create_string_table(): array
    {
        $this->string_table = [];
        for ($i = 0; $i < $this->spread_sheet->get_sheet_count(); ++$i) {
            $this->string_table = $this->get_writer_part_string_table()->create_string_table($this->spread_sheet->get_sheet($i), $this->string_table);
        }
        return $this->string_table;
    }
    /**
     * Save PhpSpreadsheet to file.
     *
     * @param resource|string $filename
     */
    public function save($filename, int $flags = 0): void
    {
        $this->process_flags($flags);
        $this->determine_use_dynamic_arrays();
        // garbage collect
        $this->path_names = [];
        $this->spread_sheet->garbage_collect();
        $save_debug_log = Calculation::get_instance($this->spread_sheet)->get_debug_log()->get_write_debug_log();
        Calculation::get_instance($this->spread_sheet)->get_debug_log()->set_write_debug_log(false);
        $save_date_return_type = Functions::get_return_date_type();
        Functions::set_return_date_type(Functions::RETURNDATE_EXCEL);
        // Create string lookup table
        $this->create_string_table();
        // Create styles dictionaries
        $this->create_style_dictionaries();
        // Create drawing dictionary
        $this->drawing_hash_table->add_from_source($this->get_writer_part_drawing()->all_drawings($this->spread_sheet));
        /** @var string[] */
        $zip_content = [];
        $rich_data_count = 0;
        if ($this->spread_sheet->has_in_cell_drawings()) {
            $rich_data_drawing = new Rich_Data_Drawing();
            $rich_data_files = $rich_data_drawing->generate_files($this->spread_sheet);
            $rich_data_count = count($rich_data_drawing->get_drawings());
            // Add all Rich Data files to ZIP
            foreach ($rich_data_files as $path => $content) {
                $zip_content[$path] = $content;
            }
        }
        // Add [Content_Types].xml to ZIP file
        $zip_content['[Content_Types].xml'] = $this->get_writer_part_content_types()->write_content_types($this->spread_sheet, $this->include_charts);
        $metadata_data = (new Xlsx\Metadata($this))->write_metadata($rich_data_count);
        if ($metadata_data !== '') {
            $zip_content['xl/metadata.xml'] = $metadata_data;
        }
        $property_bag_data = (new Xlsx\Feature_Property_Bag($this))->write_feature_property_bag($this->spread_sheet);
        if ($property_bag_data !== '') {
            $zip_content['xl/featurePropertyBag/featurePropertyBag.xml'] = $property_bag_data;
        }
        //if hasMacros, add the vbaProject.bin file, Certificate file(if exists)
        if ($this->spread_sheet->has_macros()) {
            $macros_code = $this->spread_sheet->get_macros_code();
            if ($macros_code !== null) {
                // we have the code ?
                $zip_content['xl/vbaProject.bin'] = $macros_code;
                //allways in 'xl', allways named vbaProject.bin
                if ($this->spread_sheet->has_macros_certificate()) {
                    //signed macros ?
                    // Yes : add the certificate file and the related rels file
                    $zip_content['xl/vbaProjectSignature.bin'] = $this->spread_sheet->get_macros_certificate();
                    $zip_content['xl/_rels/vbaProject.bin.rels'] = $this->get_writer_part_rels_vba()->write_vba_relationships();
                }
            }
        }
        //a custom UI in this workbook ? add it ("base" xml and additional objects (pictures) and rels)
        if ($this->spread_sheet->has_ribbon()) {
            $tmp_ribbon_target = $this->spread_sheet->get_ribbon_xml_data('target');
            $tmp_ribbon_target = is_string($tmp_ribbon_target) ? $tmp_ribbon_target : '';
            $zip_content[$tmp_ribbon_target] = $this->spread_sheet->get_ribbon_xml_data('data');
            if ($this->spread_sheet->has_ribbon_bin_objects()) {
                $tmp_root_path = dirname($tmp_ribbon_target) . '/';
                $ribbon_bin_objects = $this->spread_sheet->get_ribbon_bin_objects('data');
                //the files to write
                if (is_array($ribbon_bin_objects)) {
                    foreach ($ribbon_bin_objects as $a_path => $a_content) {
                        $zip_content[$tmp_root_path . $a_path] = $a_content;
                    }
                }
                //the rels for files
                $zip_content[$tmp_root_path . '_rels/' . basename($tmp_ribbon_target) . '.rels'] = $this->get_writer_part_rels_ribbon()->write_ribbon_relationships($this->spread_sheet);
            }
        }
        // Add relationships to ZIP file
        $zip_content['_rels/.rels'] = $this->get_writer_part_rels()->write_relationships($this->spread_sheet);
        $zip_content['xl/_rels/workbook.xml.rels'] = $this->get_writer_part_rels()->write_workbook_relationships($this->spread_sheet);
        // Add document properties to ZIP file
        $zip_content['docProps/app.xml'] = $this->get_writer_part_doc_props()->write_doc_props_app($this->spread_sheet);
        $zip_content['docProps/core.xml'] = $this->get_writer_part_doc_props()->write_doc_props_core($this->spread_sheet);
        $custom_properties_part = $this->get_writer_part_doc_props()->write_doc_props_custom($this->spread_sheet);
        if ($custom_properties_part !== null) {
            $zip_content['docProps/custom.xml'] = $custom_properties_part;
        }
        // Add theme to ZIP file
        $zip_content['xl/theme/theme1.xml'] = $this->get_writer_part_theme()->write_theme($this->spread_sheet);
        // Add string table to ZIP file
        $zip_content['xl/sharedStrings.xml'] = $this->get_writer_part_string_table()->write_string_table($this->string_table);
        // Add styles to ZIP file
        $zip_content['xl/styles.xml'] = $this->get_writer_part_style()->write_styles($this->spread_sheet);
        // Add workbook to ZIP file
        $zip_content['xl/workbook.xml'] = $this->get_writer_part_workbook()->write_workbook($this->spread_sheet, $this->pre_calculate_formulas, $this->force_full_calc);
        $chart_count = 0;
        // Add worksheets
        for ($i = 0; $i < $this->spread_sheet->get_sheet_count(); ++$i) {
            $zip_content['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->get_writer_part_worksheet()->write_worksheet($this->spread_sheet->get_sheet($i), $this->string_table, $this->include_charts);
            if ($this->include_charts) {
                $charts = $this->spread_sheet->get_sheet($i)->get_chart_collection();
                if (count($charts) > 0) {
                    foreach ($charts as $chart) {
                        $zip_content['xl/charts/chart' . ($chart_count + 1) . '.xml'] = $this->get_writer_part_chart()->write_chart($chart, $this->pre_calculate_formulas);
                        ++$chart_count;
                    }
                }
            }
        }
        $chart_ref1 = 0;
        $table_ref1 = 1;
        // Add worksheet relationships (drawings, ...)
        for ($i = 0; $i < $this->spread_sheet->get_sheet_count(); ++$i) {
            // Add relationships
            /** @var string[] $zipContent */
            $zip_content['xl/worksheets/_rels/sheet' . ($i + 1) . '.xml.rels'] = $this->get_writer_part_rels()->write_worksheet_relationships($this->spread_sheet->get_sheet($i), $i + 1, $this->include_charts, $table_ref1, $zip_content);
            // Add unparsedLoadedData
            $sheet_code_name = $this->spread_sheet->get_sheet($i)->get_code_name();
            /** @var mixed[][][] */
            $unparsed_loaded_data = $this->spread_sheet->get_unparsed_loaded_data();
            /** @var mixed[][] */
            $unparsed_sheet = $unparsed_loaded_data['sheets'][$sheet_code_name] ?? [];
            foreach ($unparsed_sheet['ctrlProps'] ?? [] as $ctrl_prop) {
                /** @var string[] $ctrlProp */
                $zip_content[$ctrl_prop['filePath']] = $ctrl_prop['content'];
            }
            foreach ($unparsed_sheet['printerSettings'] ?? [] as $ctrl_prop) {
                /** @var string[] $ctrlProp */
                $zip_content[$ctrl_prop['filePath']] = $ctrl_prop['content'];
            }
            $drawings = $this->spread_sheet->get_sheet($i)->get_drawing_collection();
            $drawing_count = count($drawings);
            if ($this->include_charts) {
                $chart_count = $this->spread_sheet->get_sheet($i)->get_chart_count();
            }
            // Add drawing and image relationship parts
            /** @var bool $hasPassThroughDrawing */
            $has_pass_through_drawing = $unparsed_sheet['drawingPassThroughEnabled'] ?? false;
            if ($drawing_count > 0 || $chart_count > 0 || $has_pass_through_drawing) {
                // Drawing relationships
                $zip_content['xl/drawings/_rels/drawing' . ($i + 1) . '.xml.rels'] = $this->get_writer_part_rels()->write_drawing_relationships($this->spread_sheet->get_sheet($i), $chart_ref1, $this->include_charts);
                // Drawings
                $zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'] = $this->get_writer_part_drawing()->write_drawings($this->spread_sheet->get_sheet($i), $this->include_charts);
            } elseif (isset($unparsed_sheet['drawingAlternateContents'])) {
                // Drawings
                $zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'] = $this->get_writer_part_drawing()->write_drawings($this->spread_sheet->get_sheet($i), $this->include_charts);
            }
            // Add unparsed drawings
            if (isset($unparsed_sheet['Drawings']) && !isset($zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'])) {
                foreach ($unparsed_sheet['Drawings'] as $rel_id => $drawing_xml) {
                    $drawing_file = array_search($rel_id, $unparsed_sheet['drawingOriginalIds']);
                    if ($drawing_file !== false) {
                        //$drawingFile = ltrim($drawingFile, '.');
                        //$zipContent['xl' . $drawingFile] = $drawingXml;
                        $zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'] = $drawing_xml;
                    }
                }
            }
            if (isset($unparsed_sheet['drawingOriginalIds']) && !isset($zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'])) {
                $zip_content['xl/drawings/drawing' . ($i + 1) . '.xml'] = '<xml></xml>';
            }
            // Add comment relationship parts
            /** @var mixed[][] */
            $legacy_temp = $unparsed_loaded_data['sheets'] ?? [];
            $legacy_temp = $legacy_temp[$this->spread_sheet->get_sheet($i)->get_code_name()] ?? [];
            $legacy = $legacy_temp['legacyDrawing'] ?? null;
            if (count($this->spread_sheet->get_sheet($i)->get_comments()) > 0 || $legacy !== null) {
                // VML Comments relationships
                $zip_content['xl/drawings/_rels/vmlDrawing' . ($i + 1) . '.vml.rels'] = $this->get_writer_part_rels()->write_vml_drawing_relationships($this->spread_sheet->get_sheet($i));
                // VML Comments
                $zip_content['xl/drawings/vmlDrawing' . ($i + 1) . '.vml'] = $legacy ?? $this->get_writer_part_comments()->write_vml_comments($this->spread_sheet->get_sheet($i));
            }
            // Comments
            if (count($this->spread_sheet->get_sheet($i)->get_comments()) > 0) {
                $zip_content['xl/comments' . ($i + 1) . '.xml'] = $this->get_writer_part_comments()->write_comments($this->spread_sheet->get_sheet($i));
                // Media
                foreach ($this->spread_sheet->get_sheet($i)->get_comments() as $comment) {
                    if ($comment->has_background_image()) {
                        $image = $comment->get_background_image();
                        $zip_content['xl/media/' . $image->get_media_filename()] = $this->process_drawing($image);
                    }
                }
            }
            // Add unparsed relationship parts
            if (isset($unparsed_sheet['vmlDrawings'])) {
                foreach ($unparsed_sheet['vmlDrawings'] as $vml_drawing) {
                    /** @var string[] $vmlDrawing */
                    if (!isset($zip_content[$vml_drawing['filePath']])) {
                        $zip_content[$vml_drawing['filePath']] = $vml_drawing['content'];
                    }
                }
            }
            // Add header/footer relationship parts
            if (count($this->spread_sheet->get_sheet($i)->get_header_footer()->get_images()) > 0) {
                // VML Drawings
                $zip_content['xl/drawings/vmlDrawingHF' . ($i + 1) . '.vml'] = $this->get_writer_part_drawing()->write_vml_header_footer_images($this->spread_sheet->get_sheet($i));
                // VML Drawing relationships
                $zip_content['xl/drawings/_rels/vmlDrawingHF' . ($i + 1) . '.vml.rels'] = $this->get_writer_part_rels()->write_header_footer_drawing_relationships($this->spread_sheet->get_sheet($i));
                // Media
                foreach ($this->spread_sheet->get_sheet($i)->get_header_footer()->get_images() as $image) {
                    if ($image->get_path() !== '') {
                        $zip_content['xl/media/' . $image->get_indexed_filename()] = file_get_contents($image->get_path());
                    }
                }
            }
            // Add Table parts
            $tables = $this->spread_sheet->get_sheet($i)->get_table_collection();
            foreach ($tables as $table) {
                $zip_content['xl/tables/table' . $table_ref1 . '.xml'] = $this->get_writer_part_table()->write_table($table, $table_ref1++);
            }
        }
        // Add media
        for ($i = 0; $i < $this->get_drawing_hash_table()->count(); ++$i) {
            if ($this->get_drawing_hash_table()->get_by_index($i) instanceof Worksheet_Drawing) {
                $image_contents = null;
                $image_path = $this->get_drawing_hash_table()->get_by_index($i)->get_path();
                if ($image_path === '') {
                    continue;
                }
                if (str_contains($image_path, 'zip://')) {
                    $image_path = substr($image_path, 6);
                    $image_path_splitted = explode('#', $image_path);
                    $image_zip = new Zip_Archive();
                    $image_zip->open($image_path_splitted[0]);
                    $image_contents = $image_zip->get_from_name($image_path_splitted[1]);
                    $image_zip->close();
                    unset($image_zip);
                } else {
                    $image_contents = file_get_contents($image_path);
                }
                $zip_content['xl/media/' . $this->get_drawing_hash_table()->get_by_index($i)->get_indexed_filename()] = $image_contents;
            } elseif ($this->get_drawing_hash_table()->get_by_index($i) instanceof Memory_Drawing) {
                ob_start();
                $callable = $this->get_drawing_hash_table()->get_by_index($i)->get_rendering_function();
                call_user_func($callable, $this->get_drawing_hash_table()->get_by_index($i)->get_image_resource());
                $image_contents = ob_get_contents();
                ob_end_clean();
                $zip_content['xl/media/' . $this->get_drawing_hash_table()->get_by_index($i)->get_indexed_filename()] = $image_contents;
            }
        }
        // Add pass-through media files (original media that may not be in the drawing collection)
        $this->add_pass_through_media_files($zip_content);
        // @phpstan-ignore argument.type
        Functions::set_return_date_type($save_date_return_type);
        Calculation::get_instance($this->spread_sheet)->get_debug_log()->set_write_debug_log($save_debug_log);
        $this->open_file_handle($filename);
        $this->zip = Zip_Stream0::new_zip_stream($this->file_handle);
        /** @var string[] $zipContent */
        $this->add_zip_files($zip_content);
        // Close file
        try {
            $this->zip->finish();
        } catch (OverflowException) {
            throw new Writer_Exception('Could not close resource.');
        }
        $this->maybe_close_file_handle();
    }
    /**
     * Get Spreadsheet object.
     */
    public function get_spreadsheet(): Spreadsheet
    {
        return $this->spread_sheet;
    }
    /**
     * Set Spreadsheet object.
     *
     * @param Spreadsheet $spreadsheet PhpSpreadsheet object
     *
     * @return $this
     */
    public function set_spreadsheet(Spreadsheet $spreadsheet): static
    {
        $this->spread_sheet = $spreadsheet;
        return $this;
    }
    /**
     * Get string table.
     *
     * @return string[]
     */
    public function get_string_table(): array
    {
        return $this->string_table;
    }
    /**
     * Get Style HashTable.
     *
     * @return HashTable<\PhpOffice\PhpSpreadsheet\Style\Style>
     */
    public function get_style_hash_table(): Hash_Table
    {
        return $this->style_hash_table;
    }
    /**
     * Get Conditional HashTable.
     *
     * @return HashTable<Conditional>
     */
    public function get_styles_conditional_hash_table(): Hash_Table
    {
        return $this->styles_conditional_hash_table;
    }
    /**
     * Get Fill HashTable.
     *
     * @return HashTable<Fill>
     */
    public function get_fill_hash_table(): Hash_Table
    {
        return $this->fill_hash_table;
    }
    /**
     * Get \PhpOffice\PhpSpreadsheet\Style\Font HashTable.
     *
     * @return HashTable<Font>
     */
    public function get_font_hash_table(): Hash_Table
    {
        return $this->font_hash_table;
    }
    /**
     * Get Borders HashTable.
     *
     * @return HashTable<Borders>
     */
    public function get_borders_hash_table(): Hash_Table
    {
        return $this->borders_hash_table;
    }
    /**
     * Get NumberFormat HashTable.
     *
     * @return HashTable<NumberFormat>
     */
    public function get_num_fmt_hash_table(): Hash_Table
    {
        return $this->num_fmt_hash_table;
    }
    /**
     * Get \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet\BaseDrawing HashTable.
     *
     * @return HashTable<BaseDrawing>
     */
    public function get_drawing_hash_table(): Hash_Table
    {
        return $this->drawing_hash_table;
    }
    /**
     * Get Office2003 compatibility.
     */
    public function get_office2003compatibility(): bool
    {
        return $this->office2003compatibility;
    }
    /**
     * Set Office2003 compatibility.
     *
     * @param bool $office2003compatibility Office2003 compatibility?
     *
     * @return $this
     */
    public function set_office2003compatibility(bool $office2003compatibility): static
    {
        $this->office2003compatibility = $office2003compatibility;
        return $this;
    }
    /** @var string[] */
    private array $path_names = [];
    private function add_zip_file(string $path, string $content): void
    {
        if (!in_array($path, $this->path_names)) {
            $this->path_names[] = $path;
            $this->zip->add_file($path, $content);
        }
    }
    /** @param string[] $zipContent */
    private function add_zip_files(array $zip_content): void
    {
        foreach ($zip_content as $path => $content) {
            $this->add_zip_file($path, $content);
        }
    }
    private function process_drawing(Worksheet_Drawing $drawing): string|null|false
    {
        $data = null;
        $filename = $drawing->get_path();
        if ($filename === '') {
            return null;
        }
        $image_data = getimagesize($filename);
        if (!empty($image_data)) {
            switch ($image_data[2]) {
                case 1:
                    // GIF, not supported by BIFF8, we convert to PNG
                    $image = imagecreatefromgif($filename);
                    if ($image !== false) {
                        ob_start();
                        imagepng($image);
                        $data = ob_get_contents();
                        ob_end_clean();
                    }
                    break;
                case 2:
                case 3:
                    // JPEG
                    $data = file_get_contents($filename);
                    break;
                case 6:
                    // Windows DIB (BMP), we convert to PNG
                    $image = imagecreatefrombmp($filename);
                    if ($image !== false) {
                        ob_start();
                        imagepng($image);
                        $data = ob_get_contents();
                        ob_end_clean();
                    }
                    break;
            }
        }
        return $data;
    }
    public function get_explicit_style0(): bool
    {
        return $this->explicit_style0;
    }
    /**
     * This may be useful if non-default Alignment is part of default style
     * and you think you might want to open the spreadsheet
     * with LibreOffice or Gnumeric.
     */
    public function set_explicit_style0(bool $explicit_style0): self
    {
        $this->explicit_style0 = $explicit_style0;
        return $this;
    }
    public function set_use_cse_arrays(?bool $use_cse_arrays): void
    {
        if ($use_cse_arrays !== null) {
            $this->use_cse_arrays = $use_cse_arrays;
        }
        $this->determine_use_dynamic_arrays();
    }
    public function use_dynamic_arrays(): bool
    {
        return $this->use_dynamic_array;
    }
    private function determine_use_dynamic_arrays(): void
    {
        $this->use_dynamic_array = $this->pre_calculate_formulas && Calculation::get_instance($this->spread_sheet)->get_instance_array_return_type() === Calculation::RETURN_ARRAY_AS_ARRAY && !$this->use_cse_arrays;
    }
    /**
     * If this is set when a spreadsheet is opened,
     * values may not be automatically re-calculated,
     * and a button will be available to force re-calculation.
     * This may apply to all spreadsheets open at that time.
     * If null, this will be set to the opposite of $preCalculateFormulas.
     * It is likely that false is the desired setting, although
     * cases have been reported where true is required (issue #456).
     * Nevertheless, default is set to false in PhpSpreadsheet 4.0.0.
     */
    public function set_force_full_calc(?bool $force_full_calc): self
    {
        $this->force_full_calc = $force_full_calc;
        return $this;
    }
    /**
     * Excel has a nominal width limint of 255 for a column.
     * Surprisingly, Xlsx can read and write larger values,
     * and the file will appear as desired,
     * but the User Interface does not allow you to set the width beyond 255,
     * either directly or though auto-fit width.
     * Xls sets its own value when the width is beyond 255.
     * This method gets whether PhpSpreadsheet should restrict the
     * column widths which it writes to the Excel limit, for formats
     * which allow it to exceed 255.
     */
    public function set_restrict_max_column_width(bool $restrict_max_column_width): self
    {
        $this->restrict_max_column_width = $restrict_max_column_width;
        return $this;
    }
    public function get_restrict_max_column_width(): bool
    {
        return $this->restrict_max_column_width;
    }
    /**
     * Add pass-through media files from original spreadsheet.
     * This copies media files that are referenced in pass-through drawing XML
     * but may not be in the drawing collection (e.g., unsupported formats like SVG).
     *
     * @param string[] $zipContent
     */
    private function add_pass_through_media_files(array &$zip_content): void
    {
        /** @var array<string, array<string, mixed>> $sheets */
        $sheets = $this->spread_sheet->get_unparsed_loaded_data()['sheets'] ?? [];
        foreach ($sheets as $sheet_data) {
            /** @var string[] $mediaFiles */
            $media_files = $sheet_data['drawingMediaFiles'] ?? [];
            /** @var ?string $sourceFile */
            $source_file = $sheet_data['drawingSourceFile'] ?? null;
            if (($sheet_data['drawingPassThroughEnabled'] ?? false) !== true) {
                continue;
            }
            if ($media_files === []) {
                continue;
            }
            if (!is_string($source_file)) {
                continue;
            }
            if (!file_exists($source_file)) {
                continue;
            }
            $source_zip = new Zip_Archive();
            if ($source_zip->open($source_file) !== true) {
                continue;
                // @codeCoverageIgnore
            }
            foreach ($media_files as $media_path) {
                $zip_path = 'xl/media/' . basename($media_path);
                if (!isset($zip_content[$zip_path])) {
                    $media_content = $source_zip->get_from_name($media_path);
                    if ($media_content !== false) {
                        $zip_content[$zip_path] = $media_content;
                    }
                }
            }
            $source_zip->close();
        }
    }
}