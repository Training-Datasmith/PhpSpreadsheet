<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Cell\Hyperlink;
use Php_Office\Php_Spreadsheet\Comment;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Auto_Filter;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Chart;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Column_And_Row_Attributes;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Conditional_Styles;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Data_Validations;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Hyperlinks;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Page_Setup;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Properties as PropertyReader;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Shared_Formula;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Sheet_View_Options;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Sheet_Views;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Styles;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Table_Reader;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Theme;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Workbook_View;
use Php_Office\Php_Spreadsheet\Reference_Helper;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Drawing;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\Font;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Font as StyleFont;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Header_Footer_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Table\Table_Dxfs_Style;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
use Throwable;
use Xml_Reader;
use Zip_Archive;
class Xlsx extends Base_Reader
{
    public const INITIAL_FILE = '_rels/.rels';
    /**
     * ReferenceHelper instance.
     */
    private readonly Reference_Helper $reference_helper;
    private Zip_Archive $zip;
    private Styles $style_reader;
    /** @var SharedFormula[] */
    private array $shared_formulae = [];
    private bool $parse_huge = false;
    /**
     * Allow use of LIBXML_PARSEHUGE.
     * This option can lead to memory leaks and failures,
     * and is not recommended. But some very large spreadsheets
     * seem to require it.
     */
    public function set_parse_huge(bool $parse_huge): void
    {
        $this->parse_huge = $parse_huge;
    }
    /**
     * Create a new Xlsx Reader instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->reference_helper = Reference_Helper::get_instance();
        $this->security_scanner = Xml_Scanner::get_instance($this);
    }
    /**
     * Can the current IReader read the file?
     */
    public function can_read(string $filename): bool
    {
        if (!File::test_file_no_throw($filename, self::INITIAL_FILE)) {
            return false;
        }
        $result = false;
        $this->zip = $zip = new Zip_Archive();
        if ($zip->open($filename) === true) {
            [$workbook_basename] = $this->get_workbook_base_name();
            $result = !empty($workbook_basename);
            $zip->close();
        }
        return $result;
    }
    public static function test_simple_xml(mixed $value): Simple_Xml_Element
    {
        return $value instanceof Simple_Xml_Element ? $value : new Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8"?><root></root>');
    }
    public static function get_attributes(?Simple_Xml_Element $value, string $ns = ''): Simple_Xml_Element
    {
        return self::test_simple_xml($value === null ? $value : $value->attributes($ns));
    }
    // Phpstan thinks, correctly, that xpath can return false.
    /** @return mixed[] */
    private static function xpath_no_false(Simple_Xml_Element $sxml, string $path): array
    {
        return self::false_to_array($sxml->xpath($path));
    }
    /** @return mixed[] */
    public static function false_to_array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
    private function load_zip(string $filename, string $ns = '', bool $replace_unclosed_br = false): Simple_Xml_Element
    {
        $contents = $this->get_from_zip_archive($this->zip, $filename);
        if ($replace_unclosed_br) {
            $contents = str_replace('<br>', '<br/>', $contents);
        }
        $rels = @simplexml_load_string($this->get_security_scanner_or_throw()->scan($contents), Simple_Xml_Element::class, $this->parse_huge ? LIBXML_PARSEHUGE : 0, $ns);
        return self::test_simple_xml($rels);
    }
    // This function is just to identify cases where I'm not sure
    // why empty namespace is required.
    private function load_zip_nonamespace(string $filename, string $ns): Simple_Xml_Element
    {
        $contents = $this->get_from_zip_archive($this->zip, $filename);
        $rels = simplexml_load_string($this->get_security_scanner_or_throw()->scan($contents), Simple_Xml_Element::class, $this->parse_huge ? LIBXML_PARSEHUGE : 0, $ns === '' ? $ns : '');
        return self::test_simple_xml($rels);
    }
    private const REL_TO_MAIN = [Namespaces::PURL_OFFICE_DOCUMENT => Namespaces::PURL_MAIN, Namespaces::THUMBNAIL => ''];
    private const REL_TO_DRAWING = [Namespaces::PURL_RELATIONSHIPS => Namespaces::PURL_DRAWING];
    private const REL_TO_CHART = [Namespaces::PURL_RELATIONSHIPS => Namespaces::PURL_CHART];
    /**
     * Reads names of the worksheets from a file, without parsing the whole file to a Spreadsheet object.
     *
     * @return string[]
     */
    public function list_worksheet_names(string $filename): array
    {
        File::assert_file($filename, self::INITIAL_FILE);
        $worksheet_names = [];
        $this->zip = $zip = new Zip_Archive();
        $zip->open($filename);
        //    The files we're looking at here are small enough that simpleXML is more efficient than XMLReader
        $rels = $this->load_zip(self::INITIAL_FILE, Namespaces::RELATIONSHIPS);
        foreach ($rels->Relationship as $relx) {
            $rel = self::get_attributes($relx);
            $rel_type = (string) $rel['Type'];
            $main_ns = self::REL_TO_MAIN[$rel_type] ?? Namespaces::MAIN;
            if ($main_ns !== '') {
                $xml_workbook = $this->load_zip((string) $rel['Target'], $main_ns);
                if ($xml_workbook->sheets) {
                    foreach ($xml_workbook->sheets->sheet as $ele_sheet) {
                        // Check if sheet should be skipped
                        $worksheet_names[] = (string) self::get_attributes($ele_sheet)['name'];
                    }
                }
            }
        }
        $zip->close();
        return $worksheet_names;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        File::assert_file($filename, self::INITIAL_FILE);
        $worksheet_info = [];
        $this->zip = $zip = new Zip_Archive();
        $zip->open($filename);
        $rels = $this->load_zip(self::INITIAL_FILE, Namespaces::RELATIONSHIPS);
        foreach ($rels->Relationship as $relx) {
            $rel = self::get_attributes($relx);
            $rel_type = (string) $rel['Type'];
            $main_ns = self::REL_TO_MAIN[$rel_type] ?? Namespaces::MAIN;
            if ($main_ns !== '') {
                $rel_target = (string) $rel['Target'];
                $dir = dirname($rel_target);
                $namespace = dirname($rel_type);
                $rels_workbook = $this->load_zip("{$dir}/_rels/" . basename($rel_target) . '.rels', Namespaces::RELATIONSHIPS);
                $worksheets = [];
                foreach ($rels_workbook->Relationship as $elex) {
                    $ele = self::get_attributes($elex);
                    if ((string) $ele['Type'] === "{$namespace}/worksheet" || (string) $ele['Type'] === "{$namespace}/chartsheet") {
                        $worksheets[(string) $ele['Id']] = $ele['Target'];
                    }
                }
                $xml_workbook = $this->load_zip($rel_target, $main_ns);
                if ($xml_workbook->sheets) {
                    $dir = dirname($rel_target);
                    foreach ($xml_workbook->sheets->sheet as $ele_sheet) {
                        $tmp_info = ['worksheetName' => (string) self::get_attributes($ele_sheet)['name'], 'lastColumnLetter' => 'A', 'lastColumnIndex' => 0, 'totalRows' => 0, 'totalColumns' => 0];
                        $sheet_state = (string) (self::get_attributes($ele_sheet)['state'] ?? Worksheet::SHEETSTATE_VISIBLE);
                        $tmp_info['sheetState'] = $sheet_state;
                        $file_worksheet = (string) $worksheets[self::get_array_item_string(self::get_attributes($ele_sheet, $namespace), 'id')];
                        $file_worksheet_path = str_starts_with($file_worksheet, '/') ? substr($file_worksheet, 1) : "{$dir}/{$file_worksheet}";
                        $xml = new Xml_Reader();
                        $xml->xml($this->get_security_scanner_or_throw()->scan($this->get_from_zip_archive($this->zip, $file_worksheet_path)), null, $this->parse_huge ? LIBXML_PARSEHUGE : 0);
                        $xml->set_parser_property(2, true);
                        $curr_cells = 0;
                        $curr_row = 0;
                        while ($xml->read()) {
                            if ($xml->local_name == 'row' && $xml->node_type == Xml_Reader::ELEMENT && $xml->namespace_uri === $main_ns) {
                                $row = (int) $xml->get_attribute('r');
                                if ($this->read_empty_cells) {
                                    $tmp_info['totalRows'] = $row;
                                } else {
                                    $curr_row = $row;
                                }
                                $tmp_info['totalColumns'] = max($tmp_info['totalColumns'], $curr_cells);
                                $curr_cells = 0;
                            } elseif ($xml->local_name == 'c' && $xml->node_type == Xml_Reader::ELEMENT && $xml->namespace_uri === $main_ns) {
                                if ($this->read_empty_cells || !$xml->is_empty_element) {
                                    if ($curr_row !== 0) {
                                        $tmp_info['totalRows'] = $curr_row;
                                        $curr_row = 0;
                                    }
                                    $cell = $xml->get_attribute('r');
                                    $curr_cells = $cell ? max($curr_cells, Coordinate::indexes_from_string($cell)[0]) : $curr_cells + 1;
                                }
                            }
                        }
                        $tmp_info['totalColumns'] = max($tmp_info['totalColumns'], $curr_cells);
                        $xml->close();
                        $tmp_info['lastColumnIndex'] = $tmp_info['totalColumns'] - 1;
                        $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['lastColumnIndex'] + 1, true);
                        $worksheet_info[] = $tmp_info;
                    }
                }
            }
        }
        $zip->close();
        return $worksheet_info;
    }
    private static function cast_to_boolean(Simple_Xml_Element $c): bool
    {
        $value = isset($c->v) ? (string) $c->v : null;
        if ($value == '0') {
            return false;
        }
        if ($value == '1') {
            return true;
        }
        return (bool) $c->v;
    }
    private static function cast_to_error(?Simple_Xml_Element $c): ?string
    {
        return isset($c, $c->v) ? (string) $c->v : null;
    }
    private static function cast_to_string(?Simple_Xml_Element $c): ?string
    {
        return isset($c, $c->v) ? (string) $c->v : null;
    }
    public static function replace_prefixes(string $formula): string
    {
        return str_replace(['_xlfn.', '_xlws.'], '', $formula);
    }
    private function cast_to_formula(?Simple_Xml_Element $c, string $r, string &$cell_data_type, mixed &$value, mixed &$calculated_value, string $cast_base_type, bool $update_shared_cells = true): void
    {
        if ($c === null) {
            return;
        }
        $attr = $c->f->attributes();
        $cell_data_type = Data_Type::TYPE_FORMULA;
        $formula = self::replace_prefixes((string) $c->f);
        $value = "={$formula}";
        $calculated_value = self::$cast_base_type($c);
        // Shared formula?
        if (isset($attr['t']) && strtolower((string) $attr['t']) == 'shared') {
            $instance = (string) $attr['si'];
            if (!isset($this->shared_formulae[(string) $attr['si']])) {
                $this->shared_formulae[$instance] = new Shared_Formula($r, $value);
            } elseif ($update_shared_cells === true) {
                // It's only worth the overhead of adjusting the shared formula for this cell if we're actually loading
                //     the cell, which may not be the case if we're using a read filter.
                $master = Coordinate::indexes_from_string($this->shared_formulae[$instance]->master());
                $current = Coordinate::indexes_from_string($r);
                $difference = [0, 0];
                $difference[0] = $current[0] - $master[0];
                $difference[1] = $current[1] - $master[1];
                $value = $this->reference_helper->update_formula_references($this->shared_formulae[$instance]->formula(), 'A1', $difference[0], $difference[1]);
            }
        }
    }
    private function file_exists_in_archive(Zip_Archive $archive, string $file_name = ''): bool
    {
        // Root-relative paths
        if (str_contains($file_name, '//')) {
            $file_name = substr($file_name, strpos($file_name, '//') + 1);
        }
        $file_name = File::realpath($file_name);
        // Sadly, some 3rd party xlsx generators don't use consistent case for filenaming
        //    so we need to load case-insensitively from the zip file
        // Apache POI fixes
        $contents = $archive->locate_name($file_name, Zip_Archive::FL_NOCASE);
        if ($contents === false) {
            $contents = $archive->locate_name(substr($file_name, 1), Zip_Archive::FL_NOCASE);
        }
        return $contents !== false;
    }
    private function get_from_zip_archive(Zip_Archive $archive, string $file_name = ''): string
    {
        // Root-relative paths
        if (str_contains($file_name, '//')) {
            $file_name = substr($file_name, strpos($file_name, '//') + 1);
        }
        // Relative paths generated by dirname($filename) when $filename
        // has no path (i.e.files in root of the zip archive)
        $file_name = Preg::replace('/^\.\//', '', $file_name);
        $file_name = File::realpath($file_name);
        // Sadly, some 3rd party xlsx generators don't use consistent case for filenaming
        //    so we need to load case-insensitively from the zip file
        $contents = $archive->get_from_name($file_name, 0, Zip_Archive::FL_NOCASE);
        // Apache POI fixes
        if ($contents === false) {
            $contents = $archive->get_from_name(substr($file_name, 1), 0, Zip_Archive::FL_NOCASE);
        }
        // Has the file been saved with Windoze directory separators rather than unix?
        if ($contents === false) {
            $contents = $archive->get_from_name(str_replace('/', '\\', $file_name), 0, Zip_Archive::FL_NOCASE);
        }
        return $contents === false ? '' : $contents;
    }
    /**
     * Loads Spreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        File::assert_file($filename, self::INITIAL_FILE);
        // Initialisations
        $excel = $this->new_spreadsheet();
        $excel->set_value_binder($this->value_binder);
        $excel->remove_sheet_by_index(0);
        $adding_first_cell_style_xf = true;
        $adding_first_cell_xf = true;
        /** @var mixed[][][][] */
        $unparsed_loaded_data = [];
        $this->zip = $zip = new Zip_Archive();
        $zip->open($filename);
        //    Read the theme first, because we need the colour scheme when reading the styles
        [$workbook_basename, $xml_namespace_base] = $this->get_workbook_base_name();
        $drawing_ns = self::REL_TO_DRAWING[$xml_namespace_base] ?? Namespaces::DRAWINGML;
        $chart_ns = self::REL_TO_CHART[$xml_namespace_base] ?? Namespaces::CHART;
        $wb_rels = $this->load_zip("xl/_rels/{$workbook_basename}.rels", Namespaces::RELATIONSHIPS);
        $theme = null;
        $this->style_reader = new Styles();
        foreach ($wb_rels->Relationship as $relx) {
            $rel = self::get_attributes($relx);
            $rel_target = (string) $rel['Target'];
            if (str_starts_with($rel_target, '/xl/')) {
                $rel_target = substr($rel_target, 4);
            }
            switch ($rel['Type']) {
                case "{$xml_namespace_base}/sheetMetadata":
                    if ($this->file_exists_in_archive($zip, "xl/{$rel_target}")) {
                        $excel->return_array_as_array();
                    }
                    break;
                case "{$xml_namespace_base}/theme":
                    if (!$this->file_exists_in_archive($zip, "xl/{$rel_target}")) {
                        break;
                        // issue3770
                    }
                    $theme_order_array = ['lt1', 'dk1', 'lt2', 'dk2'];
                    $theme_order_additional = count($theme_order_array);
                    $xml_theme = $this->load_zip("xl/{$rel_target}", $drawing_ns);
                    $xml_theme_name = self::get_attributes($xml_theme);
                    $xml_theme = $xml_theme->children($drawing_ns);
                    $theme_name = (string) $xml_theme_name['name'];
                    $colour_scheme = self::get_attributes($xml_theme->theme_elements->clr_scheme);
                    $colour_scheme_name = (string) $colour_scheme['name'];
                    $excel->get_theme()->set_theme_color_name($colour_scheme_name);
                    $colour_scheme = $xml_theme->theme_elements->clr_scheme->children($drawing_ns);
                    $theme_colours = [];
                    foreach ($colour_scheme as $k => $xml_colour) {
                        $theme_pos = array_search($k, $theme_order_array);
                        if ($theme_pos === false) {
                            $theme_pos = $theme_order_additional++;
                        }
                        if (isset($xml_colour->sys_clr)) {
                            $xml_colour_data = self::get_attributes($xml_colour->sys_clr);
                            $theme_colours[$theme_pos] = (string) $xml_colour_data['lastClr'];
                            $excel->get_theme()->set_theme_color($k, (string) $xml_colour_data['lastClr']);
                        } elseif (isset($xml_colour->srgb_clr)) {
                            $xml_colour_data = self::get_attributes($xml_colour->srgb_clr);
                            $theme_colours[$theme_pos] = (string) $xml_colour_data['val'];
                            $excel->get_theme()->set_theme_color($k, (string) $xml_colour_data['val']);
                        }
                    }
                    $theme = new Theme($theme_name, $colour_scheme_name, $theme_colours);
                    $this->style_reader->set_theme($theme);
                    $font_scheme = self::get_attributes($xml_theme->theme_elements->font_scheme);
                    $font_scheme_name = (string) $font_scheme['name'];
                    $excel->get_theme()->set_theme_font_name($font_scheme_name);
                    $major_fonts = [];
                    $minor_fonts = [];
                    $font_scheme = $xml_theme->theme_elements->font_scheme->children($drawing_ns);
                    $major_latin = self::get_attributes($font_scheme->major_font->latin)['typeface'] ?? '';
                    $major_east_asian = self::get_attributes($font_scheme->major_font->ea)['typeface'] ?? '';
                    $major_complex_script = self::get_attributes($font_scheme->major_font->cs)['typeface'] ?? '';
                    $minor_latin = self::get_attributes($font_scheme->minor_font->latin)['typeface'] ?? '';
                    $minor_east_asian = self::get_attributes($font_scheme->minor_font->ea)['typeface'] ?? '';
                    $minor_complex_script = self::get_attributes($font_scheme->minor_font->cs)['typeface'] ?? '';
                    foreach ($font_scheme->major_font->font as $xml_font) {
                        $font_attributes = self::get_attributes($xml_font);
                        $script = (string) ($font_attributes['script'] ?? '');
                        if (!empty($script)) {
                            $major_fonts[$script] = (string) ($font_attributes['typeface'] ?? '');
                        }
                    }
                    foreach ($font_scheme->minor_font->font as $xml_font) {
                        $font_attributes = self::get_attributes($xml_font);
                        $script = (string) ($font_attributes['script'] ?? '');
                        if (!empty($script)) {
                            $minor_fonts[$script] = (string) ($font_attributes['typeface'] ?? '');
                        }
                    }
                    $excel->get_theme()->set_major_font_values($major_latin, $major_east_asian, $major_complex_script, $major_fonts);
                    $excel->get_theme()->set_minor_font_values($minor_latin, $minor_east_asian, $minor_complex_script, $minor_fonts);
                    break;
            }
        }
        $rels = $this->load_zip(self::INITIAL_FILE, Namespaces::RELATIONSHIPS);
        $property_reader = new Property_Reader($this->get_security_scanner_or_throw(), $excel->get_properties());
        $charts = $chart_details = [];
        foreach ($rels->Relationship as $relx) {
            $rel = self::get_attributes($relx);
            $rel_target = (string) $rel['Target'];
            // issue 3553
            if ($rel_target[0] === '/') {
                $rel_target = substr($rel_target, 1);
            }
            $rel_type = (string) $rel['Type'];
            $main_ns = self::REL_TO_MAIN[$rel_type] ?? Namespaces::MAIN;
            switch ($rel_type) {
                case Namespaces::CORE_PROPERTIES:
                    $property_reader->read_core_properties($this->get_from_zip_archive($zip, $rel_target));
                    break;
                case "{$xml_namespace_base}/extended-properties":
                    $property_reader->read_extended_properties($this->get_from_zip_archive($zip, $rel_target));
                    break;
                case "{$xml_namespace_base}/custom-properties":
                    $property_reader->read_custom_properties($this->get_from_zip_archive($zip, $rel_target));
                    break;
                //Ribbon
                case Namespaces::EXTENSIBILITY:
                    $custom_ui = $rel_target;
                    if ($custom_ui) {
                        $this->read_ribbon($excel, $custom_ui, $zip);
                    }
                    break;
                case "{$xml_namespace_base}/officeDocument":
                    $dir = dirname($rel_target);
                    // Do not specify namespace in next stmt - do it in Xpath
                    $rels_workbook = $this->load_zip("{$dir}/_rels/" . basename($rel_target) . '.rels', Namespaces::RELATIONSHIPS);
                    $rels_workbook->register_x_path_namespace('rel', Namespaces::RELATIONSHIPS);
                    $worksheets = [];
                    $macros = $custom_ui = null;
                    foreach ($rels_workbook->Relationship as $elex) {
                        $ele = self::get_attributes($elex);
                        switch ($ele['Type']) {
                            case Namespaces::WORKSHEET:
                            case Namespaces::PURL_WORKSHEET:
                                $worksheets[(string) $ele['Id']] = $ele['Target'];
                                break;
                            case Namespaces::CHARTSHEET:
                                if ($this->include_charts === true) {
                                    $worksheets[(string) $ele['Id']] = $ele['Target'];
                                }
                                break;
                            // a vbaProject ? (: some macros)
                            case Namespaces::VBA:
                                $macros = $ele['Target'];
                                break;
                        }
                    }
                    if ($macros !== null) {
                        $macros_code = $this->get_from_zip_archive($zip, 'xl/vbaProject.bin');
                        //vbaProject.bin always in 'xl' dir and always named vbaProject.bin
                        if (!empty($macros_code)) {
                            $excel->set_macros_code($macros_code);
                            $excel->set_has_macros(true);
                            //short-circuit : not reading vbaProject.bin.rel to get Signature =>allways vbaProjectSignature.bin in 'xl' dir
                            $Certificate = $this->get_from_zip_archive($zip, 'xl/vbaProjectSignature.bin');
                            $excel->set_macros_certificate($Certificate);
                        }
                    }
                    $rel_type = "rel:Relationship[@Type='" . "{$xml_namespace_base}/styles" . "']";
                    /** @var ?SimpleXMLElement */
                    $xpath = self::get_array_item(self::xpath_no_false($rels_workbook, $rel_type));
                    if ($xpath === null) {
                        $xml_styles = self::test_simple_xml(null);
                    } else {
                        $styles_target = (string) $xpath['Target'];
                        $styles_target = str_starts_with($styles_target, '/') ? substr($styles_target, 1) : "{$dir}/{$styles_target}";
                        $xml_styles = $this->load_zip($styles_target, $main_ns);
                    }
                    $palette = self::extract_palette($xml_styles);
                    $this->style_reader->set_workbook_palette($palette);
                    $fills = self::extract_styles($xml_styles, 'fills', 'fill');
                    $fonts = self::extract_styles($xml_styles, 'fonts', 'font');
                    $borders = self::extract_styles($xml_styles, 'borders', 'border');
                    $xf_tags = self::extract_styles($xml_styles, 'cellXfs', 'xf');
                    $cell_xf_tags = self::extract_styles($xml_styles, 'cellStyleXfs', 'xf');
                    $styles = [];
                    $cell_styles = [];
                    $num_fmts = null;
                    if ($xml_styles->num_fmts[0]) {
                        $num_fmts = $xml_styles->num_fmts[0];
                    }
                    if (isset($num_fmts)) {
                        /** @var SimpleXMLElement $numFmts */
                        $num_fmts->register_x_path_namespace('sml', $main_ns);
                    }
                    $this->style_reader->set_namespace($main_ns);
                    if (!$this->read_data_only) {
                        foreach ($xf_tags as $xf_tag) {
                            $xf = self::get_attributes($xf_tag);
                            $num_fmt = null;
                            if ($xf['numFmtId']) {
                                if (isset($num_fmts)) {
                                    /** @var ?SimpleXMLElement */
                                    $tmp_num_fmt = self::get_array_item($num_fmts->xpath("sml:numFmt[@numFmtId={$xf['numFmtId']}]"));
                                    if (isset($tmp_num_fmt['formatCode'])) {
                                        $num_fmt = (string) $tmp_num_fmt['formatCode'];
                                    }
                                }
                                // We shouldn't override any of the built-in MS Excel values (values below id 164)
                                //  But there's a lot of naughty homebrew xlsx writers that do use "reserved" id values that aren't actually used
                                //  So we make allowance for them rather than lose formatting masks
                                if ($num_fmt === null && (int) $xf['numFmtId'] < 164 && Number_Format::built_in_format_code((int) $xf['numFmtId']) !== '') {
                                    $num_fmt = Number_Format::built_in_format_code((int) $xf['numFmtId']);
                                }
                            }
                            $quote_prefix = (bool) (string) ($xf['quotePrefix'] ?? '');
                            $style = (object) ['numFmt' => $num_fmt ?? Number_Format::FORMAT_GENERAL, 'font' => $fonts[(int) $xf['fontId']], 'fill' => $fills[(int) $xf['fillId']], 'border' => $borders[(int) $xf['borderId']], 'alignment' => $xf_tag->alignment, 'protection' => $xf_tag->protection, 'quotePrefix' => $quote_prefix];
                            $styles[] = $style;
                            // add style to cellXf collection
                            $obj_style = new Style();
                            $this->style_reader->read_style($obj_style, $style);
                            if (isset($xf_tag->ext_lst)) {
                                foreach ($xf_tag->ext_lst->ext as $ext_tag) {
                                    $attributes = $ext_tag->attributes();
                                    if (isset($attributes['uri'])) {
                                        if ((string) $attributes['uri'] === Namespaces::STYLE_CHECKBOX_URI) {
                                            $obj_style->set_check_box(true);
                                        }
                                    }
                                }
                            }
                            foreach ($this->style_reader->get_font_charsets() as $font_name => $charset) {
                                $excel->add_font_charset($font_name, $charset);
                            }
                            if ($adding_first_cell_xf) {
                                $excel->remove_cell_xf_by_index(0);
                                // remove the default style
                                $adding_first_cell_xf = false;
                            }
                            $excel->add_cell_xf($obj_style);
                        }
                        foreach ($cell_xf_tags as $xf_tag) {
                            $xf = self::get_attributes($xf_tag);
                            $num_fmt = Number_Format::FORMAT_GENERAL;
                            if ($num_fmts && $xf['numFmtId']) {
                                /** @var ?SimpleXMLElement */
                                $tmp_num_fmt = self::get_array_item($num_fmts->xpath("sml:numFmt[@numFmtId={$xf['numFmtId']}]"));
                                if (isset($tmp_num_fmt['formatCode'])) {
                                    $num_fmt = (string) $tmp_num_fmt['formatCode'];
                                } elseif ((int) $xf['numFmtId'] < 165) {
                                    $num_fmt = Number_Format::built_in_format_code((int) $xf['numFmtId']);
                                }
                            }
                            $quote_prefix = (bool) (string) ($xf['quotePrefix'] ?? '');
                            $cell_style = (object) ['numFmt' => $num_fmt, 'font' => $fonts[(int) $xf['fontId']], 'fill' => $fills[(int) $xf['fillId']], 'border' => $borders[(int) $xf['borderId']], 'alignment' => $xf_tag->alignment, 'protection' => $xf_tag->protection, 'quotePrefix' => $quote_prefix];
                            $cell_styles[] = $cell_style;
                            // add style to cellStyleXf collection
                            $obj_style = new Style();
                            $this->style_reader->read_style($obj_style, $cell_style);
                            if ($adding_first_cell_style_xf) {
                                $excel->remove_cell_style_xf_by_index(0);
                                // remove the default style
                                $adding_first_cell_style_xf = false;
                            }
                            $excel->add_cell_style_xf($obj_style);
                        }
                    }
                    $this->style_reader->set_style_xml($xml_styles);
                    $this->style_reader->set_namespace($main_ns);
                    $this->style_reader->set_style_base_data($theme, $styles, $cell_styles);
                    $dxfs = $this->style_reader->dxfs($this->read_data_only);
                    $table_styles = $this->style_reader->table_styles($this->read_data_only);
                    $styles = $this->style_reader->styles();
                    // Read content after setting the styles
                    $shared_strings = [];
                    $rel_type = "rel:Relationship[@Type='" . "{$xml_namespace_base}/sharedStrings" . "']";
                    /** @var ?SimpleXMLElement */
                    $xpath = self::get_array_item($rels_workbook->xpath($rel_type));
                    if ($xpath) {
                        $shared_strings_target = (string) $xpath['Target'];
                        $shared_strings_target = str_starts_with($shared_strings_target, '/') ? substr($shared_strings_target, 1) : "{$dir}/{$shared_strings_target}";
                        $xml_strings = $this->load_zip($shared_strings_target, $main_ns);
                        if (isset($xml_strings->si)) {
                            foreach ($xml_strings->si as $val) {
                                if (isset($val->t)) {
                                    $shared_strings[] = String_Helper::control_character_ooxml2php((string) $val->t);
                                } elseif (isset($val->r)) {
                                    $shared_strings[] = $this->parse_rich_text($val);
                                } else {
                                    $shared_strings[] = '';
                                }
                            }
                        }
                    }
                    $xml_workbook = $this->load_zip_no_namespace($rel_target, $main_ns);
                    $xml_workbook_ns = $this->load_zip($rel_target, $main_ns);
                    // Set base date
                    $excel->set_excel_calendar(Date::CALENDAR_WINDOWS_1900);
                    if ($xml_workbook_ns->workbook_pr) {
                        Date::set_excel_calendar(Date::CALENDAR_WINDOWS_1900);
                        $attrs1904 = self::get_attributes($xml_workbook_ns->workbook_pr);
                        if (isset($attrs1904['date1904'])) {
                            if (self::boolean((string) $attrs1904['date1904'])) {
                                Date::set_excel_calendar(Date::CALENDAR_MAC_1904);
                                $excel->set_excel_calendar(Date::CALENDAR_MAC_1904);
                            }
                        }
                    }
                    // Set protection
                    $this->read_protection($excel, $xml_workbook);
                    $sheet_id = 0;
                    // keep track of new sheet id in final workbook
                    $old_sheet_id = -1;
                    // keep track of old sheet id in final workbook
                    $count_skipped_sheets = 0;
                    // keep track of number of skipped sheets
                    $map_sheet_id = [];
                    // mapping of sheet ids from old to new
                    $charts = $chart_details = [];
                    // Add richData (contains relation of in-cell images)
                    $rich_data = [];
                    $relations_file_name = $dir . '/richData/_rels/richValueRel.xml.rels';
                    if ($zip->locate_name($relations_file_name)) {
                        $rels_worksheet = $this->load_zip($relations_file_name, Namespaces::RELATIONSHIPS);
                        foreach ($rels_worksheet->Relationship as $elex) {
                            $ele = self::get_attributes($elex);
                            if ($ele['Type'] == Namespaces::IMAGE) {
                                $rich_data['image'][(string) $ele['Id']] = (string) $ele['Target'];
                            }
                        }
                    }
                    $sheet_created = false;
                    if ($xml_workbook_ns->sheets) {
                        foreach ($xml_workbook_ns->sheets->sheet as $ele_sheet) {
                            $ele_sheet_attr = self::get_attributes($ele_sheet);
                            ++$old_sheet_id;
                            // Check if sheet should be skipped
                            if (is_array($this->load_sheets_only) && !in_array((string) $ele_sheet_attr['name'], $this->load_sheets_only)) {
                                ++$count_skipped_sheets;
                                $map_sheet_id[$old_sheet_id] = null;
                                continue;
                            }
                            $sheet_reference_id = self::get_array_item_string(self::get_attributes($ele_sheet, $xml_namespace_base), 'id');
                            if (isset($worksheets[$sheet_reference_id]) === false) {
                                ++$count_skipped_sheets;
                                $map_sheet_id[$old_sheet_id] = null;
                                continue;
                            }
                            // Map old sheet id in original workbook to new sheet id.
                            // They will differ if loadSheetsOnly() is being used
                            $map_sheet_id[$old_sheet_id] = $old_sheet_id - $count_skipped_sheets;
                            // Load sheet
                            $doc_sheet = $excel->create_sheet();
                            $sheet_created = true;
                            //    Use false for $updateFormulaCellReferences to prevent adjustment of worksheet
                            //        references in formula cells... during the load, all formulae should be correct,
                            //        and we're simply bringing the worksheet name in line with the formula, not the
                            //        reverse
                            $doc_sheet->set_title((string) $ele_sheet_attr['name'], false, false);
                            $file_worksheet = (string) $worksheets[$sheet_reference_id];
                            // issue 3665 adds test for /.
                            // This broke XlsxRootZipFilesTest,
                            //  but Excel reports an error with that file.
                            //  Testing dir for . avoids this problem.
                            //  It might be better just to drop the test.
                            if ($file_worksheet[0] == '/' && $dir !== '.') {
                                $file_worksheet = substr($file_worksheet, strlen($dir) + 2);
                            }
                            $xml_sheet = $this->load_zip_no_namespace("{$dir}/{$file_worksheet}", $main_ns);
                            $xml_sheet_ns = $this->load_zip("{$dir}/{$file_worksheet}", $main_ns);
                            // Shared Formula table is unique to each Worksheet, so we need to reset it here
                            $this->shared_formulae = [];
                            if (isset($ele_sheet_attr['state']) && (string) $ele_sheet_attr['state'] != '') {
                                $doc_sheet->set_sheet_state((string) $ele_sheet_attr['state']);
                            }
                            if ($xml_sheet_ns) {
                                $xml_sheet_main = $xml_sheet_ns->children($main_ns);
                                // Setting Conditional Styles adjusts selected cells, so we need to execute this
                                //    before reading the sheet view data to get the actual selected cells
                                if (!$this->read_data_only && $xml_sheet->conditional_formatting) {
                                    (new Conditional_Styles($doc_sheet, $xml_sheet, $dxfs, $this->style_reader))->load();
                                }
                                if (!$this->read_data_only && $xml_sheet->ext_lst) {
                                    (new Conditional_Styles($doc_sheet, $xml_sheet, $dxfs, $this->style_reader))->load_from_ext();
                                }
                                if (isset($xml_sheet_main->sheet_views, $xml_sheet_main->sheet_views->sheet_view)) {
                                    $sheet_views = new Sheet_Views($xml_sheet_main->sheet_views->sheet_view, $doc_sheet);
                                    $sheet_views->load();
                                }
                                $sheet_view_options = new Sheet_View_Options($doc_sheet, $xml_sheet_ns);
                                $sheet_view_options->load($this->read_data_only, $this->style_reader);
                                (new Column_And_Row_Attributes($doc_sheet, $xml_sheet_ns))->load($this->get_read_filter(), $this->read_data_only, $this->ignore_rows_with_no_cells);
                            }
                            $hold_selected_cells = $doc_sheet->get_selected_cells();
                            if ($xml_sheet_ns && $xml_sheet_ns->sheet_data && $xml_sheet_ns->sheet_data->row) {
                                $c_index = 1;
                                // Cell Start from 1
                                foreach ($xml_sheet_ns->sheet_data->row as $row) {
                                    $row_index = 1;
                                    foreach ($row->c as $c) {
                                        $c_attr = self::get_attributes($c);
                                        $r = (string) $c_attr['r'];
                                        if ($r == '') {
                                            $r = Coordinate::string_from_column_index($row_index) . $c_index;
                                        }
                                        $cell_data_type = (string) $c_attr['t'];
                                        $original_cell_data_type_numeric = $cell_data_type === '';
                                        $value = null;
                                        $calculated_value = null;
                                        // Read cell?
                                        $coordinates = Coordinate::coordinate_from_string($r);
                                        if (!$this->get_read_filter()->read_cell($coordinates[0], (int) $coordinates[1], $doc_sheet->get_title())) {
                                            // Normally, just testing for the f attribute should identify this cell as containing a formula
                                            // that we need to read, even though it is outside of the filter range, in case it is a shared formula.
                                            // But in some cases, this attribute isn't set; so we need to delve a level deeper and look at
                                            // whether or not the cell has a child formula element that is shared.
                                            if (isset($c_attr->f) || isset($c->f, $c->f->attributes()['t']) && strtolower((string) $c->f->attributes()['t']) === 'shared') {
                                                $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToError', false);
                                            }
                                            ++$row_index;
                                            continue;
                                        }
                                        // Read cell!
                                        $use_formula = isset($c->f) && ((string) $c->f !== '' || isset($c->f->attributes()['t']) && strtolower((string) $c->f->attributes()['t']) === 'shared');
                                        switch ($cell_data_type) {
                                            case Data_Type::TYPE_STRING:
                                                if ((string) $c->v != '') {
                                                    $value = $shared_strings[(int) $c->v];
                                                    if ($value instanceof Rich_Text) {
                                                        $value = clone $value;
                                                    }
                                                } else {
                                                    $value = '';
                                                }
                                                break;
                                            case Data_Type::TYPE_BOOL:
                                                if (!$use_formula) {
                                                    if (isset($c->v)) {
                                                        $value = self::cast_to_boolean($c);
                                                    } else {
                                                        $value = null;
                                                        $cell_data_type = Data_Type::TYPE_NULL;
                                                    }
                                                } else {
                                                    // Formula
                                                    $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToBoolean');
                                                    self::store_formula_attributes($c->f, $doc_sheet, $r);
                                                }
                                                break;
                                            case Data_Type::TYPE_STRING2:
                                                if ($use_formula) {
                                                    $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToString');
                                                    self::store_formula_attributes($c->f, $doc_sheet, $r);
                                                } else {
                                                    $value = self::cast_to_string($c);
                                                }
                                                break;
                                            case Data_Type::TYPE_INLINE:
                                                if ($use_formula) {
                                                    $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToError');
                                                    self::store_formula_attributes($c->f, $doc_sheet, $r);
                                                } else {
                                                    $value = $this->parse_rich_text($c->is);
                                                }
                                                break;
                                            case Data_Type::TYPE_ERROR:
                                                if (isset($c_attr->vm, $rich_data['image']['rId' . $c_attr->vm]) && !$use_formula) {
                                                    $image_path = $dir . '/' . str_replace('../', '', $rich_data['image']['rId' . $c_attr->vm]);
                                                    $obj_drawing = new \Php_Office\Php_Spreadsheet\Worksheet\Drawing();
                                                    $obj_drawing->set_path('zip://' . File::realpath($filename) . '#' . $image_path, false, $zip);
                                                    $obj_drawing->set_coordinates($r);
                                                    $obj_drawing->set_resize_proportional(false);
                                                    $obj_drawing->set_in_cell(true);
                                                    $obj_drawing->set_worksheet($doc_sheet);
                                                    $value = $obj_drawing;
                                                    $cell_data_type = Data_Type::TYPE_DRAWING_IN_CELL;
                                                    $c->t = Data_Type::TYPE_ERROR;
                                                    break;
                                                }
                                                if (!$use_formula) {
                                                    $value = self::cast_to_error($c);
                                                } else {
                                                    // Formula
                                                    $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToError');
                                                    $eattr = $c->attributes();
                                                    if (isset($eattr['vm'])) {
                                                        if ($calculated_value === Excel_Error::VALUE()) {
                                                            $calculated_value = Excel_Error::SPILL();
                                                        }
                                                    }
                                                }
                                                break;
                                            default:
                                                if (!$use_formula) {
                                                    $value = self::cast_to_string($c);
                                                    if (is_numeric($value)) {
                                                        $value += 0;
                                                        $cell_data_type = Data_Type::TYPE_NUMERIC;
                                                    }
                                                } else {
                                                    // Formula
                                                    $this->cast_to_formula($c, $r, $cell_data_type, $value, $calculated_value, 'castToString');
                                                    if (is_numeric($calculated_value)) {
                                                        $calculated_value += 0;
                                                    }
                                                    self::store_formula_attributes($c->f, $doc_sheet, $r);
                                                }
                                                break;
                                        }
                                        // read empty cells or the cells are not empty
                                        if ($this->read_empty_cells || $value !== null && $value !== '') {
                                            // Rich text?
                                            if ($value instanceof Rich_Text && $this->read_data_only) {
                                                $value = $value->get_plain_text();
                                            }
                                            $cell = $doc_sheet->get_cell($r);
                                            // Assign value
                                            if ($cell_data_type != '') {
                                                // it is possible, that datatype is numeric but with an empty string, which result in an error
                                                if ($cell_data_type === Data_Type::TYPE_NUMERIC && ($value === '' || $value === null)) {
                                                    $cell_data_type = Data_Type::TYPE_NULL;
                                                }
                                                if ($cell_data_type !== Data_Type::TYPE_NULL) {
                                                    $cell->set_value_explicit($value, $cell_data_type);
                                                }
                                            } else {
                                                $cell->set_value($value);
                                            }
                                            if ($calculated_value !== null) {
                                                $cell->set_calculated_value($calculated_value, $original_cell_data_type_numeric);
                                            }
                                            // Style information?
                                            if (!$this->read_data_only) {
                                                $c_attr_s = (int) ($c_attr['s'] ?? 0);
                                                // no style index means 0, it seems
                                                $c_attr_s = isset($styles[$c_attr_s]) ? $c_attr_s : 0;
                                                $cell->set_xf_index($c_attr_s);
                                                // issue 3495
                                                if ($cell_data_type === Data_Type::TYPE_FORMULA && $styles[$c_attr_s]->quote_prefix === true) {
                                                    //* @phpstan-ignore-line
                                                    $hold_selected = $doc_sheet->get_selected_cells();
                                                    $cell->get_style()->set_quote_prefix(false);
                                                    $doc_sheet->set_selected_cells($hold_selected);
                                                }
                                            }
                                        }
                                        ++$row_index;
                                    }
                                    ++$c_index;
                                }
                            }
                            $doc_sheet->set_selected_cells($hold_selected_cells);
                            if (!$this->read_data_only && $xml_sheet_ns && $xml_sheet_ns->ignored_errors) {
                                foreach ($xml_sheet_ns->ignored_errors->ignored_error as $ignored_error) {
                                    $this->process_ignored_errors($ignored_error, $doc_sheet);
                                }
                            }
                            if (!$this->read_data_only && $xml_sheet_ns && $xml_sheet_ns->sheet_protection) {
                                $prot_attr = $xml_sheet_ns->sheet_protection->attributes() ?? [];
                                foreach ($prot_attr as $key => $value) {
                                    $method = 'set' . ucfirst($key);
                                    $doc_sheet->get_protection()->{$method}(self::boolean((string) $value));
                                }
                            }
                            if ($xml_sheet) {
                                $this->read_sheet_protection($doc_sheet, $xml_sheet);
                            }
                            if ($this->read_data_only === false) {
                                $this->read_auto_filter($xml_sheet_ns, $doc_sheet);
                                $this->read_background_image($xml_sheet_ns, $doc_sheet, dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels');
                            }
                            $this->read_tables($xml_sheet_ns, $doc_sheet, $dir, $file_worksheet, $zip, $main_ns, $table_styles, $dxfs);
                            if ($xml_sheet_ns && $xml_sheet_ns->merge_cells && $xml_sheet_ns->merge_cells->merge_cell && !$this->read_data_only) {
                                foreach ($xml_sheet_ns->merge_cells->merge_cell as $merge_cellx) {
                                    $merge_cell = $merge_cellx->attributes();
                                    $merge_ref = (string) ($merge_cell['ref'] ?? '');
                                    if (str_contains($merge_ref, ':')) {
                                        $doc_sheet->merge_cells($merge_ref, Worksheet::MERGE_CELL_CONTENT_HIDE);
                                    }
                                }
                            }
                            if ($xml_sheet && !$this->read_data_only) {
                                $unparsed_loaded_data = (new Page_Setup($doc_sheet, $xml_sheet))->load($unparsed_loaded_data);
                            }
                            if (isset($xml_sheet->ext_lst->ext)) {
                                foreach ($xml_sheet->ext_lst->ext as $extlst) {
                                    $ext_attrs = $extlst->attributes() ?? [];
                                    $ext_uri = (string) ($ext_attrs['uri'] ?? '');
                                    if ($ext_uri !== '{CCE6A557-97BC-4b89-ADB6-D9C93CAAB3DF}') {
                                        continue;
                                    }
                                    // Create dataValidations node if does not exists, maybe is better inside the foreach ?
                                    if (!$xml_sheet->data_validations) {
                                        $xml_sheet->add_child('dataValidations');
                                    }
                                    foreach ($extlst->children(Namespaces::DATA_VALIDATIONS1)->data_validations->data_validation as $item) {
                                        $item = self::test_simple_xml($item);
                                        $node = self::test_simple_xml($xml_sheet->data_validations)->add_child('dataValidation');
                                        foreach ($item->attributes() ?? [] as $attr) {
                                            $node->add_attribute($attr->get_name(), $attr);
                                        }
                                        $node->add_attribute('sqref', $item->children(Namespaces::DATA_VALIDATIONS2)->sqref);
                                        if (isset($item->formula1)) {
                                            $child_node = $node->add_child('formula1');
                                            if ($child_node !== null) {
                                                // null should never happen
                                                // see https://github.com/phpstan/phpstan/issues/8236
                                                // resolved with Phpstan 2.1.23
                                                $child_node[0] = (string) $item->formula1->children(Namespaces::DATA_VALIDATIONS2)->f;
                                            }
                                        }
                                    }
                                }
                            }
                            if ($xml_sheet && $xml_sheet->data_validations && !$this->read_data_only) {
                                (new Data_Validations($doc_sheet, $xml_sheet))->load();
                            }
                            // unparsed sheet AlternateContent
                            if ($xml_sheet && !$this->read_data_only) {
                                $mc = $xml_sheet->children(Namespaces::COMPATIBILITY);
                                if ($mc->alternate_content) {
                                    foreach ($mc->alternate_content as $alternate_content) {
                                        $alternate_content = self::test_simple_xml($alternate_content);
                                        /** @var mixed[][][][] $unparsedLoadedData */
                                        $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['AlternateContents'][] = $alternate_content->as_xml();
                                    }
                                }
                            }
                            // Add hyperlinks
                            if (!$this->read_data_only) {
                                $hyperlink_reader = new Hyperlinks($doc_sheet);
                                // Locate hyperlink relations
                                $relations_file_name = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
                                if ($zip->locate_name($relations_file_name) !== false) {
                                    $rels_worksheet = $this->load_zip($relations_file_name, Namespaces::RELATIONSHIPS);
                                    $hyperlink_reader->read_hyperlinks($rels_worksheet);
                                }
                                // Loop through hyperlinks
                                if ($xml_sheet_ns && $xml_sheet_ns->children($main_ns)->hyperlinks) {
                                    $hyperlink_reader->set_hyperlinks($xml_sheet_ns->children($main_ns)->hyperlinks);
                                }
                            }
                            // Add comments
                            $comments = [];
                            $vml_comments = [];
                            if (!$this->read_data_only) {
                                // Locate comment relations
                                $comment_relations = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
                                if ($zip->locate_name($comment_relations) !== false) {
                                    $rels_worksheet = $this->load_zip($comment_relations, Namespaces::RELATIONSHIPS);
                                    foreach ($rels_worksheet->Relationship as $elex) {
                                        $ele = self::get_attributes($elex);
                                        if ($ele['Type'] == Namespaces::COMMENTS) {
                                            $comments[(string) $ele['Id']] = (string) $ele['Target'];
                                        }
                                        if ($ele['Type'] == Namespaces::VML) {
                                            $vml_comments[(string) $ele['Id']] = (string) $ele['Target'];
                                        }
                                    }
                                }
                                // Loop through comments
                                foreach ($comments as $rel_name => $rel_path) {
                                    // Load comments file
                                    $rel_path = File::realpath(dirname("{$dir}/{$file_worksheet}") . '/' . $rel_path);
                                    // okay to ignore namespace - using xpath
                                    $comments_file = $this->load_zip($rel_path, '');
                                    // Utility variables
                                    $authors = [];
                                    $comments_file->register_xpath_namespace('com', $main_ns);
                                    $author_path = self::xpath_no_false($comments_file, 'com:authors/com:author');
                                    foreach ($author_path as $author) {
                                        /** @var SimpleXMLElement $author */
                                        $authors[] = (string) $author;
                                    }
                                    // Loop through contents
                                    $content_path = self::xpath_no_false($comments_file, 'com:commentList/com:comment');
                                    foreach ($content_path as $comment) {
                                        /** @var SimpleXMLElement $comment */
                                        $commentx = $comment->attributes();
                                        /** @var array{ref: scalar, authorId?: scalar}  $commentx */
                                        $comment_model = $doc_sheet->get_comment((string) $commentx['ref']);
                                        if (isset($commentx['authorId'])) {
                                            $comment_model->set_author($authors[(int) $commentx['authorId']]);
                                        }
                                        /** @var SimpleXMLElement */
                                        $temp = $comment->children($main_ns);
                                        $comment_model->set_text($this->parse_rich_text($temp->text));
                                    }
                                }
                                // later we will remove from it real vmlComments
                                $unparsed_vml_drawings = $vml_comments;
                                $vml_drawing_contents = [];
                                // Loop through VML comments
                                foreach ($vml_comments as $rel_name => $rel_path) {
                                    // Load VML comments file
                                    $rel_path = File::realpath(dirname("{$dir}/{$file_worksheet}") . '/' . $rel_path);
                                    try {
                                        // no namespace okay - processed with Xpath
                                        $vml_comments_file = $this->load_zip($rel_path, '', true);
                                        $vml_comments_file->register_x_path_namespace('v', Namespaces::URN_VML);
                                    } catch (Throwable) {
                                        //Ignore unparsable vmlDrawings. Later they will be moved from $unparsedVmlDrawings to $unparsedLoadedData
                                        continue;
                                    }
                                    // Locate VML drawings image relations
                                    $drowing_images = [];
                                    $vml_drawings_relations = dirname($rel_path) . '/_rels/' . basename($rel_path) . '.rels';
                                    $vml_drawing_contents[$rel_name] = $this->get_security_scanner_or_throw()->scan($this->get_from_zip_archive($zip, $rel_path));
                                    if ($zip->locate_name($vml_drawings_relations) !== false) {
                                        $rels_vml_drawing = $this->load_zip($vml_drawings_relations, Namespaces::RELATIONSHIPS);
                                        foreach ($rels_vml_drawing->Relationship as $elex) {
                                            $ele = self::get_attributes($elex);
                                            if ($ele['Type'] == Namespaces::IMAGE) {
                                                $drowing_images[(string) $ele['Id']] = (string) $ele['Target'];
                                            }
                                        }
                                    }
                                    $shapes = self::xpath_no_false($vml_comments_file, '//v:shape');
                                    foreach ($shapes as $shape) {
                                        /** @var SimpleXMLElement $shape */
                                        $vml_namespaces = $shape->get_namespaces();
                                        $shape->register_x_path_namespace('v', $vml_namespaces['v'] ?? Namespaces::URN_VML);
                                        $shape->register_x_path_namespace('x', $vml_namespaces['x'] ?? Namespaces::URN_EXCEL);
                                        $shape->register_x_path_namespace('o', $vml_namespaces['o'] ?? Namespaces::URN_MSOFFICE);
                                        if (isset($shape['style'])) {
                                            $style = (string) $shape['style'];
                                            $fill_color = strtoupper(substr((string) $shape['fillcolor'], 1));
                                            $column = null;
                                            $row = null;
                                            $text_h_align = null;
                                            $fill_image_rel_id = null;
                                            $fill_image_title = '';
                                            $client_data = $shape->xpath('.//x:ClientData');
                                            $textbox_direction = '';
                                            $textbox_path = $shape->xpath('.//v:textbox');
                                            $textbox = (string) ($textbox_path[0]['style'] ?? '');
                                            if (Preg::is_match('/rtl/i', $textbox)) {
                                                $textbox_direction = Comment::TEXTBOX_DIRECTION_RTL;
                                            } elseif (Preg::is_match('/ltr/i', $textbox)) {
                                                $textbox_direction = Comment::TEXTBOX_DIRECTION_LTR;
                                            }
                                            if (is_array($client_data) && !empty($client_data)) {
                                                /** @var SimpleXMLElement */
                                                $client_data = $client_data[0];
                                                if (isset($client_data['ObjectType']) && (string) $client_data['ObjectType'] == 'Note') {
                                                    $client_data->register_x_path_namespace('x', $vml_namespaces['x'] ?? Namespaces::URN_EXCEL);
                                                    $temp = $client_data->xpath('.//x:Row');
                                                    if (is_array($temp)) {
                                                        $row = $temp[0];
                                                    }
                                                    $temp = $client_data->xpath('.//x:Column');
                                                    if (is_array($temp)) {
                                                        $column = $temp[0];
                                                    }
                                                    $temp = $client_data->xpath('.//x:TextHAlign');
                                                    if (!empty($temp)) {
                                                        $text_h_align = strtolower($temp[0]);
                                                    }
                                                }
                                            }
                                            $rowx = (string) $row;
                                            $colx = (string) $column;
                                            if (is_numeric($rowx) && is_numeric($colx) && $text_h_align !== null) {
                                                $doc_sheet->get_comment([1 + (int) $colx, 1 + (int) $rowx], false)->set_alignment((string) $text_h_align);
                                            }
                                            if (is_numeric($rowx) && is_numeric($colx) && $textbox_direction !== '') {
                                                $doc_sheet->get_comment([1 + (int) $colx, 1 + (int) $rowx], false)->set_textbox_direction($textbox_direction);
                                            }
                                            $fill_image_rel_node = $shape->xpath('.//v:fill/@o:relid');
                                            if (is_array($fill_image_rel_node) && !empty($fill_image_rel_node)) {
                                                /** @var SimpleXMLElement */
                                                $fill_image_rel_node = $fill_image_rel_node[0];
                                                if (isset($fill_image_rel_node['relid'])) {
                                                    $fill_image_rel_id = (string) $fill_image_rel_node['relid'];
                                                }
                                            }
                                            $fill_image_title_node = $shape->xpath('.//v:fill/@o:title');
                                            if (is_array($fill_image_title_node) && !empty($fill_image_title_node)) {
                                                /** @var SimpleXMLElement */
                                                $fill_image_title_node = $fill_image_title_node[0];
                                                if (isset($fill_image_title_node['title'])) {
                                                    $fill_image_title = (string) $fill_image_title_node['title'];
                                                }
                                            }
                                            if ($column !== null && $row !== null) {
                                                // Set comment properties
                                                $comment = $doc_sheet->get_comment([(int) $column + 1, (int) $row + 1]);
                                                $comment->get_fill_color()->set_rgb($fill_color);
                                                if (isset($fill_image_rel_id, $drowing_images[$fill_image_rel_id])) {
                                                    $obj_drawing = new \Php_Office\Php_Spreadsheet\Worksheet\Drawing();
                                                    $obj_drawing->set_name($fill_image_title);
                                                    $image_path = str_replace(['../', '/xl/'], 'xl/', $drowing_images[$fill_image_rel_id]);
                                                    $obj_drawing->set_path('zip://' . File::realpath($filename) . '#' . $image_path, true, $zip);
                                                    $comment->set_background_image($obj_drawing);
                                                }
                                                // Parse style
                                                $style_array = explode(';', str_replace(' ', '', $style));
                                                foreach ($style_array as $style_pair) {
                                                    $style_pair = explode(':', $style_pair);
                                                    if ($style_pair[0] == 'margin-left') {
                                                        $comment->set_margin_left($style_pair[1]);
                                                    }
                                                    if ($style_pair[0] == 'margin-top') {
                                                        $comment->set_margin_top($style_pair[1]);
                                                    }
                                                    if ($style_pair[0] == 'width') {
                                                        $comment->set_width($style_pair[1]);
                                                    }
                                                    if ($style_pair[0] == 'height') {
                                                        $comment->set_height($style_pair[1]);
                                                    }
                                                    if ($style_pair[0] == 'visibility') {
                                                        $comment->set_visible($style_pair[1] == 'visible');
                                                    }
                                                }
                                                unset($unparsed_vml_drawings[$rel_name]);
                                            }
                                        }
                                    }
                                }
                                // unparsed vmlDrawing
                                foreach ($unparsed_vml_drawings as $r_id => $rel_path) {
                                    /** @var mixed[][][] $unparsedLoadedData */
                                    $r_id = substr($r_id, 3);
                                    // rIdXXX
                                    /** @var mixed[][] */
                                    $unparsed_vml_drawing =& $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['vmlDrawings'];
                                    $unparsed_vml_drawing[$r_id] = [];
                                    $unparsed_vml_drawing[$r_id]['filePath'] = self::dir_add("{$dir}/{$file_worksheet}", $rel_path);
                                    $unparsed_vml_drawing[$r_id]['relFilePath'] = $rel_path;
                                    $unparsed_vml_drawing[$r_id]['content'] = $this->get_security_scanner_or_throw()->scan($this->get_from_zip_archive($zip, $unparsed_vml_drawing[$r_id]['filePath']));
                                    unset($unparsed_vml_drawing);
                                }
                                // Header/footer images
                                if ($xml_sheet_ns && $xml_sheet_ns->legacy_drawing_hf) {
                                    $vml_hf_rid = '';
                                    $vml_hf_rid_attr = $xml_sheet_ns->legacy_drawing_hf->attributes(Namespaces::SCHEMA_OFFICE_DOCUMENT);
                                    if ($vml_hf_rid_attr !== null && isset($vml_hf_rid_attr['id'])) {
                                        $vml_hf_rid = (string) $vml_hf_rid_attr['id'][0];
                                    }
                                    if ($zip->locate_name(dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels') !== false) {
                                        $rels_worksheet = $this->load_zip_no_namespace(dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels', Namespaces::RELATIONSHIPS);
                                        $vml_relationship = '';
                                        foreach ($rels_worksheet->Relationship as $ele) {
                                            if ((string) $ele['Type'] == Namespaces::VML && (string) $ele['Id'] === $vml_hf_rid) {
                                                $vml_relationship = self::dir_add("{$dir}/{$file_worksheet}", $ele['Target']);
                                                break;
                                            }
                                        }
                                        if ($vml_relationship != '') {
                                            // Fetch linked images
                                            $rels_vml = $this->load_zip_no_namespace(dirname($vml_relationship) . '/_rels/' . basename($vml_relationship) . '.rels', Namespaces::RELATIONSHIPS);
                                            $drawings = [];
                                            if (isset($rels_vml->Relationship)) {
                                                foreach ($rels_vml->Relationship as $ele) {
                                                    if ($ele['Type'] == Namespaces::IMAGE) {
                                                        $drawings[(string) $ele['Id']] = self::dir_add($vml_relationship, $ele['Target']);
                                                    }
                                                }
                                            }
                                            // Fetch VML document
                                            $vml_drawing = $this->load_zip_no_namespace($vml_relationship, '');
                                            $vml_drawing->register_x_path_namespace('v', Namespaces::URN_VML);
                                            $hf_images = [];
                                            $shapes = self::xpath_no_false($vml_drawing, '//v:shape');
                                            foreach ($shapes as $idx => $shape) {
                                                /** @var SimpleXMLElement $shape */
                                                $shape->register_x_path_namespace('v', Namespaces::URN_VML);
                                                $image_data = $shape->xpath('//v:imagedata');
                                                if (empty($image_data)) {
                                                    continue;
                                                }
                                                $image_data = $image_data[$idx];
                                                $image_data = self::get_attributes($image_data, Namespaces::URN_MSOFFICE);
                                                /** @var array{width: int, height: int, margin-left?: int, margin-top: int} */
                                                $style = self::to_css_array((string) $shape['style']);
                                                if (array_key_exists((string) $image_data['relid'], $drawings)) {
                                                    $shape_id = (string) $shape['id'];
                                                    $hf_images[$shape_id] = new Header_Footer_Drawing();
                                                    if (isset($image_data['title'])) {
                                                        $hf_images[$shape_id]->set_name((string) $image_data['title']);
                                                    }
                                                    $hf_images[$shape_id]->set_path('zip://' . File::realpath($filename) . '#' . $drawings[(string) $image_data['relid']], false, $zip);
                                                    $hf_images[$shape_id]->set_resize_proportional(false);
                                                    $hf_images[$shape_id]->set_width($style['width']);
                                                    $hf_images[$shape_id]->set_height($style['height']);
                                                    if (isset($style['margin-left'])) {
                                                        $hf_images[$shape_id]->set_offset_x($style['margin-left']);
                                                    }
                                                    $hf_images[$shape_id]->set_offset_y($style['margin-top']);
                                                    $hf_images[$shape_id]->set_resize_proportional(true);
                                                }
                                            }
                                            $doc_sheet->get_header_footer()->set_images($hf_images);
                                        }
                                    }
                                }
                            }
                            // TODO: Autoshapes from twoCellAnchors!
                            $drawing_filename = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
                            if (str_starts_with($drawing_filename, 'xl//xl/')) {
                                $drawing_filename = substr($drawing_filename, 4);
                            }
                            if (str_starts_with($drawing_filename, '/xl//xl/')) {
                                $drawing_filename = substr($drawing_filename, 5);
                            }
                            if ($zip->locate_name($drawing_filename) !== false) {
                                $rels_worksheet = $this->load_zip($drawing_filename, Namespaces::RELATIONSHIPS);
                                $drawings = [];
                                foreach ($rels_worksheet->Relationship as $elex) {
                                    $ele = self::get_attributes($elex);
                                    if ((string) $ele['Type'] === "{$xml_namespace_base}/drawing") {
                                        $ele_target = (string) $ele['Target'];
                                        if (str_starts_with($ele_target, '/xl/')) {
                                            $drawings[(string) $ele['Id']] = substr($ele_target, 1);
                                        } else {
                                            $drawings[(string) $ele['Id']] = self::dir_add("{$dir}/{$file_worksheet}", $ele['Target']);
                                        }
                                    }
                                }
                                if ($xml_sheet_ns->drawing && !$this->read_data_only) {
                                    $unparsed_drawings = [];
                                    $file_drawing = null;
                                    foreach ($xml_sheet_ns->drawing as $drawing) {
                                        $drawing_rel_id = self::get_array_item_string(self::get_attributes($drawing, $xml_namespace_base), 'id');
                                        $file_drawing = $drawings[$drawing_rel_id];
                                        $drawing_filename = dirname($file_drawing) . '/_rels/' . basename($file_drawing) . '.rels';
                                        $rels_drawing = $this->load_zip($drawing_filename, Namespaces::RELATIONSHIPS);
                                        $images = [];
                                        $hyperlinks = [];
                                        if ($rels_drawing && $rels_drawing->Relationship) {
                                            foreach ($rels_drawing->Relationship as $elex) {
                                                $ele = self::get_attributes($elex);
                                                $ele_type = (string) $ele['Type'];
                                                if ($ele_type === Namespaces::HYPERLINK) {
                                                    $hyperlinks[(string) $ele['Id']] = (string) $ele['Target'];
                                                }
                                                if ($ele_type === "{$xml_namespace_base}/image") {
                                                    $ele_target = (string) $ele['Target'];
                                                    if (str_starts_with($ele_target, '/xl/')) {
                                                        $ele_target = substr($ele_target, 1);
                                                        $images[(string) $ele['Id']] = $ele_target;
                                                    } else {
                                                        $images[(string) $ele['Id']] = self::dir_add($file_drawing, $ele_target);
                                                    }
                                                } elseif ($ele_type === "{$xml_namespace_base}/chart") {
                                                    if ($this->include_charts) {
                                                        $ele_target = (string) $ele['Target'];
                                                        if (str_starts_with($ele_target, '/xl/')) {
                                                            $index = substr($ele_target, 1);
                                                        } else {
                                                            $index = self::dir_add($file_drawing, $ele_target);
                                                        }
                                                        $charts[$index] = ['id' => (string) $ele['Id'], 'sheet' => $doc_sheet->get_title()];
                                                    }
                                                }
                                            }
                                        }
                                        $xml_drawing = $this->load_zip_no_namespace($file_drawing, '');
                                        $xml_drawing_children = $xml_drawing->children(Namespaces::SPREADSHEET_DRAWING);
                                        // Store drawing XML for pass-through if enabled
                                        if ($this->enable_drawing_pass_through) {
                                            $unparsed_drawings[$drawing_rel_id] = $xml_drawing->as_xml();
                                            // Mark that pass-through is enabled for this sheet
                                            $sheet_code_name = $doc_sheet->get_code_name();
                                            if (!isset($unparsed_loaded_data['sheets']) || !is_array($unparsed_loaded_data['sheets'])) {
                                                $unparsed_loaded_data['sheets'] = [];
                                            }
                                            if (!isset($unparsed_loaded_data['sheets'][$sheet_code_name]) || !is_array($unparsed_loaded_data['sheets'][$sheet_code_name])) {
                                                $unparsed_loaded_data['sheets'][$sheet_code_name] = [];
                                            }
                                            /** @var array<string, mixed> $sheetUnparsedData */
                                            $sheet_unparsed_data =& $unparsed_loaded_data['sheets'][$sheet_code_name];
                                            $sheet_unparsed_data['drawingPassThroughEnabled'] = true;
                                            // Store original drawing relationships for pass-through
                                            if ($rels_drawing) {
                                                $sheet_unparsed_data['drawingRelationships'] = $rels_drawing->as_xml();
                                            }
                                            // Store original media files paths and source file for pass-through
                                            $sheet_unparsed_data['drawingMediaFiles'] = $images;
                                            $sheet_unparsed_data['drawingSourceFile'] = File::realpath($filename);
                                        }
                                        if ($xml_drawing_children->one_cell_anchor) {
                                            foreach ($xml_drawing_children->one_cell_anchor as $one_cell_anchor) {
                                                $one_cell_anchor = self::test_simple_xml($one_cell_anchor);
                                                if ($one_cell_anchor->pic->blip_fill) {
                                                    $obj_drawing = new \Php_Office\Php_Spreadsheet\Worksheet\Drawing();
                                                    $blip = $one_cell_anchor->pic->blip_fill->children(Namespaces::DRAWINGML)->blip;
                                                    if (isset($blip, $blip->alpha_mod_fix)) {
                                                        $temp = (string) $blip->alpha_mod_fix->attributes()->amt;
                                                        if (is_numeric($temp)) {
                                                            $obj_drawing->set_opacity((int) $temp);
                                                        }
                                                    }
                                                    $xfrm = $one_cell_anchor->pic->sp_pr->children(Namespaces::DRAWINGML)->xfrm;
                                                    $outer_shdw = $one_cell_anchor->pic->sp_pr->children(Namespaces::DRAWINGML)->effect_lst->outer_shdw;
                                                    $obj_drawing->set_name(self::get_array_item_string(self::get_attributes($one_cell_anchor->pic->nv_pic_pr->c_nv_pr), 'name'));
                                                    $obj_drawing->set_description(self::get_array_item_string(self::get_attributes($one_cell_anchor->pic->nv_pic_pr->c_nv_pr), 'descr'));
                                                    $embed_image_key = self::get_array_item_string(self::get_attributes($blip, $xml_namespace_base), 'embed');
                                                    if (isset($images[$embed_image_key])) {
                                                        $obj_drawing->set_path('zip://' . File::realpath($filename) . '#' . $images[$embed_image_key], false, $zip);
                                                    } else {
                                                        $link_image_key = self::get_array_item_string($blip->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships'), 'link');
                                                        if (isset($images[$link_image_key])) {
                                                            $url = str_replace('xl/drawings/', '', $images[$link_image_key]);
                                                            $obj_drawing->set_path($url, false, allowExternal: $this->allow_external_images, isWhitelisted: $this->is_whitelisted);
                                                        }
                                                        if ($obj_drawing->get_path() === '') {
                                                            continue;
                                                        }
                                                    }
                                                    $obj_drawing->set_coordinates(Coordinate::string_from_column_index((int) $one_cell_anchor->from->col + 1) . ($one_cell_anchor->from->row + 1));
                                                    $obj_drawing->set_offset_x(Drawing::emu_to_pixels($one_cell_anchor->from->col_off));
                                                    $obj_drawing->set_offset_y(Drawing::emu_to_pixels($one_cell_anchor->from->row_off));
                                                    $obj_drawing->set_resize_proportional(false);
                                                    $obj_drawing->set_width(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($one_cell_anchor->ext), 'cx')));
                                                    $obj_drawing->set_height(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($one_cell_anchor->ext), 'cy')));
                                                    if ($xfrm) {
                                                        $obj_drawing->set_rotation(Drawing::angle_to_degrees(self::get_array_item_int_or_sxml(self::get_attributes($xfrm), 'rot')));
                                                        $obj_drawing->set_flip_vertical((bool) self::get_array_item(self::get_attributes($xfrm), 'flipV'));
                                                        $obj_drawing->set_flip_horizontal((bool) self::get_array_item(self::get_attributes($xfrm), 'flipH'));
                                                    }
                                                    if ($outer_shdw) {
                                                        $shadow = $obj_drawing->get_shadow();
                                                        $shadow->set_visible(true);
                                                        $shadow->set_blur_radius(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'blurRad')));
                                                        $shadow->set_distance(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'dist')));
                                                        $shadow->set_direction(Drawing::angle_to_degrees(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'dir')));
                                                        $shadow->set_alignment(self::get_array_item_string(self::get_attributes($outer_shdw), 'algn'));
                                                        $clr = $outer_shdw->srgb_clr ?? $outer_shdw->prst_clr;
                                                        $shadow->get_color()->set_rgb(self::get_array_item_string(self::get_attributes($clr), 'val'));
                                                        if ($clr->alpha) {
                                                            $alpha = String_Helper::convert_to_string(self::get_array_item(self::get_attributes($clr->alpha), 'val'));
                                                            if (is_numeric($alpha)) {
                                                                $alpha = (int) ($alpha / 1000);
                                                                $shadow->set_alpha($alpha);
                                                            }
                                                        }
                                                    }
                                                    $this->read_hyper_link_drawing($obj_drawing, $one_cell_anchor, $hyperlinks);
                                                    $obj_drawing->set_worksheet($doc_sheet);
                                                } elseif ($this->include_charts && $one_cell_anchor->graphic_frame) {
                                                    // Exported XLSX from Google Sheets positions charts with a oneCellAnchor
                                                    $coordinates = Coordinate::string_from_column_index((int) $one_cell_anchor->from->col + 1) . ($one_cell_anchor->from->row + 1);
                                                    $offset_x = Drawing::emu_to_pixels($one_cell_anchor->from->col_off);
                                                    $offset_y = Drawing::emu_to_pixels($one_cell_anchor->from->row_off);
                                                    $width = Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($one_cell_anchor->ext), 'cx'));
                                                    $height = Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($one_cell_anchor->ext), 'cy'));
                                                    $graphic = $one_cell_anchor->graphic_frame->children(Namespaces::DRAWINGML)->graphic;
                                                    $chart_ref = $graphic->graphic_data->children(Namespaces::CHART)->chart;
                                                    $this_chart = (string) self::get_attributes($chart_ref, $xml_namespace_base);
                                                    $chart_details[$doc_sheet->get_title() . '!' . $this_chart] = ['fromCoordinate' => $coordinates, 'fromOffsetX' => $offset_x, 'fromOffsetY' => $offset_y, 'width' => $width, 'height' => $height, 'worksheetTitle' => $doc_sheet->get_title(), 'oneCellAnchor' => true];
                                                }
                                            }
                                        }
                                        if ($xml_drawing_children->two_cell_anchor) {
                                            foreach ($xml_drawing_children->two_cell_anchor as $two_cell_anchor) {
                                                $two_cell_anchor = self::test_simple_xml($two_cell_anchor);
                                                if ($two_cell_anchor->pic->blip_fill) {
                                                    $obj_drawing = new \Php_Office\Php_Spreadsheet\Worksheet\Drawing();
                                                    $blip = $two_cell_anchor->pic->blip_fill->children(Namespaces::DRAWINGML)->blip;
                                                    if (isset($blip, $blip->alpha_mod_fix)) {
                                                        $temp = (string) $blip->alpha_mod_fix->attributes()->amt;
                                                        if (is_numeric($temp)) {
                                                            $obj_drawing->set_opacity((int) $temp);
                                                        }
                                                    }
                                                    if (isset($two_cell_anchor->pic->blip_fill->children(Namespaces::DRAWINGML)->src_rect)) {
                                                        $obj_drawing->set_src_rect($two_cell_anchor->pic->blip_fill->children(Namespaces::DRAWINGML)->src_rect->attributes());
                                                    }
                                                    $xfrm = $two_cell_anchor->pic->sp_pr->children(Namespaces::DRAWINGML)->xfrm;
                                                    $outer_shdw = $two_cell_anchor->pic->sp_pr->children(Namespaces::DRAWINGML)->effect_lst->outer_shdw;
                                                    $edit_as = $two_cell_anchor->attributes();
                                                    if (isset($edit_as, $edit_as['editAs'])) {
                                                        $obj_drawing->set_edit_as($edit_as['editAs']);
                                                    }
                                                    $obj_drawing->set_name(self::get_array_item_string(self::get_attributes($two_cell_anchor->pic->nv_pic_pr->c_nv_pr), 'name'));
                                                    $obj_drawing->set_description(self::get_array_item_string(self::get_attributes($two_cell_anchor->pic->nv_pic_pr->c_nv_pr), 'descr'));
                                                    $embed_image_key = self::get_array_item_string(self::get_attributes($blip, $xml_namespace_base), 'embed');
                                                    if (isset($images[$embed_image_key])) {
                                                        $obj_drawing->set_path('zip://' . File::realpath($filename) . '#' . $images[$embed_image_key], false, $zip);
                                                    } else {
                                                        $link_image_key = self::get_array_item_string($blip->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships'), 'link');
                                                        if (isset($images[$link_image_key])) {
                                                            $url = str_replace('xl/drawings/', '', $images[$link_image_key]);
                                                            $obj_drawing->set_path($url, false, allowExternal: $this->allow_external_images, isWhitelisted: $this->is_whitelisted);
                                                        }
                                                        if ($obj_drawing->get_path() === '') {
                                                            continue;
                                                        }
                                                    }
                                                    $obj_drawing->set_coordinates(Coordinate::string_from_column_index((int) $two_cell_anchor->from->col + 1) . ($two_cell_anchor->from->row + 1));
                                                    $obj_drawing->set_offset_x(Drawing::emu_to_pixels($two_cell_anchor->from->col_off));
                                                    $obj_drawing->set_offset_y(Drawing::emu_to_pixels($two_cell_anchor->from->row_off));
                                                    $obj_drawing->set_coordinates2(Coordinate::string_from_column_index((int) $two_cell_anchor->to->col + 1) . ($two_cell_anchor->to->row + 1));
                                                    $obj_drawing->set_offset_x2(Drawing::emu_to_pixels($two_cell_anchor->to->col_off));
                                                    $obj_drawing->set_offset_y2(Drawing::emu_to_pixels($two_cell_anchor->to->row_off));
                                                    $obj_drawing->set_resize_proportional(false);
                                                    if ($xfrm) {
                                                        $obj_drawing->set_width(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($xfrm->ext), 'cx')));
                                                        $obj_drawing->set_height(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($xfrm->ext), 'cy')));
                                                        $obj_drawing->set_rotation(Drawing::angle_to_degrees(self::get_array_item_int_or_sxml(self::get_attributes($xfrm), 'rot')));
                                                        $obj_drawing->set_flip_vertical((bool) self::get_array_item(self::get_attributes($xfrm), 'flipV'));
                                                        $obj_drawing->set_flip_horizontal((bool) self::get_array_item(self::get_attributes($xfrm), 'flipH'));
                                                    }
                                                    if ($outer_shdw) {
                                                        $shadow = $obj_drawing->get_shadow();
                                                        $shadow->set_visible(true);
                                                        $shadow->set_blur_radius(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'blurRad')));
                                                        $shadow->set_distance(Drawing::emu_to_pixels(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'dist')));
                                                        $shadow->set_direction(Drawing::angle_to_degrees(self::get_array_item_int_or_sxml(self::get_attributes($outer_shdw), 'dir')));
                                                        $shadow->set_alignment(self::get_array_item_string(self::get_attributes($outer_shdw), 'algn'));
                                                        $clr = $outer_shdw->srgb_clr ?? $outer_shdw->prst_clr;
                                                        $shadow->get_color()->set_rgb(self::get_array_item_string(self::get_attributes($clr), 'val'));
                                                        if ($clr->alpha) {
                                                            $alpha = String_Helper::convert_to_string(self::get_array_item(self::get_attributes($clr->alpha), 'val'));
                                                            if (is_numeric($alpha)) {
                                                                $alpha = (int) ($alpha / 1000);
                                                                $shadow->set_alpha($alpha);
                                                            }
                                                        }
                                                    }
                                                    $this->read_hyper_link_drawing($obj_drawing, $two_cell_anchor, $hyperlinks);
                                                    $obj_drawing->set_worksheet($doc_sheet);
                                                } elseif ($this->include_charts && $two_cell_anchor->graphic_frame) {
                                                    $from_coordinate = Coordinate::string_from_column_index((int) $two_cell_anchor->from->col + 1) . ($two_cell_anchor->from->row + 1);
                                                    $from_offset_x = Drawing::emu_to_pixels($two_cell_anchor->from->col_off);
                                                    $from_offset_y = Drawing::emu_to_pixels($two_cell_anchor->from->row_off);
                                                    $to_coordinate = Coordinate::string_from_column_index((int) $two_cell_anchor->to->col + 1) . ($two_cell_anchor->to->row + 1);
                                                    $to_offset_x = Drawing::emu_to_pixels($two_cell_anchor->to->col_off);
                                                    $to_offset_y = Drawing::emu_to_pixels($two_cell_anchor->to->row_off);
                                                    $graphic = $two_cell_anchor->graphic_frame->children(Namespaces::DRAWINGML)->graphic;
                                                    $chart_ref = $graphic->graphic_data->children(Namespaces::CHART)->chart;
                                                    $this_chart = (string) self::get_attributes($chart_ref, $xml_namespace_base);
                                                    $chart_details[$doc_sheet->get_title() . '!' . $this_chart] = ['fromCoordinate' => $from_coordinate, 'fromOffsetX' => $from_offset_x, 'fromOffsetY' => $from_offset_y, 'toCoordinate' => $to_coordinate, 'toOffsetX' => $to_offset_x, 'toOffsetY' => $to_offset_y, 'worksheetTitle' => $doc_sheet->get_title()];
                                                }
                                            }
                                        }
                                        if ($xml_drawing_children->absolute_anchor) {
                                            foreach ($xml_drawing_children->absolute_anchor as $absolute_anchor) {
                                                if ($this->include_charts && $absolute_anchor->graphic_frame) {
                                                    $graphic = $absolute_anchor->graphic_frame->children(Namespaces::DRAWINGML)->graphic;
                                                    $chart_ref = $graphic->graphic_data->children(Namespaces::CHART)->chart;
                                                    $this_chart = (string) self::get_attributes($chart_ref, $xml_namespace_base);
                                                    $width = Drawing::emu_to_pixels((int) self::get_array_item_string(self::get_attributes($absolute_anchor->ext), 'cx')[0]);
                                                    $height = Drawing::emu_to_pixels((int) self::get_array_item_string(self::get_attributes($absolute_anchor->ext), 'cy')[0]);
                                                    $chart_details[$doc_sheet->get_title() . '!' . $this_chart] = ['fromCoordinate' => 'A1', 'fromOffsetX' => 0, 'fromOffsetY' => 0, 'width' => $width, 'height' => $height, 'worksheetTitle' => $doc_sheet->get_title()];
                                                }
                                            }
                                        }
                                        if (empty($rels_drawing) && $xml_drawing->count() == 0) {
                                            // Save Drawing without rels and children as unparsed
                                            $unparsed_drawings[$drawing_rel_id] = $xml_drawing->as_xml();
                                        }
                                    }
                                    // store original rId of drawing files
                                    /** @var mixed[][][][] $unparsedLoadedData */
                                    $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['drawingOriginalIds'] = [];
                                    foreach ($rels_worksheet->Relationship as $elex) {
                                        $ele = self::get_attributes($elex);
                                        if ((string) $ele['Type'] === "{$xml_namespace_base}/drawing") {
                                            $drawing_rel_id = (string) $ele['Id'];
                                            $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['drawingOriginalIds'][(string) $ele['Target']] = $drawing_rel_id;
                                            if (isset($unparsed_drawings[$drawing_rel_id])) {
                                                $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['Drawings'][$drawing_rel_id] = $unparsed_drawings[$drawing_rel_id];
                                            }
                                        }
                                    }
                                    if ($xml_sheet->legacy_drawing && !$this->read_data_only) {
                                        foreach ($xml_sheet->legacy_drawing as $drawing) {
                                            $drawing_rel_id = self::get_array_item_string(self::get_attributes($drawing, $xml_namespace_base), 'id');
                                            if (isset($vml_drawing_contents[$drawing_rel_id])) {
                                                if (self::only_note_vml($vml_drawing_contents[$drawing_rel_id]) === false) {
                                                    $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['legacyDrawing'] = $vml_drawing_contents[$drawing_rel_id];
                                                }
                                            }
                                        }
                                    }
                                    // unparsed drawing AlternateContent
                                    $xml_alt_drawing = $this->load_zip((string) $file_drawing, Namespaces::COMPATIBILITY);
                                    if ($xml_alt_drawing->alternate_content) {
                                        foreach ($xml_alt_drawing->alternate_content as $alternate_content) {
                                            $alternate_content = self::test_simple_xml($alternate_content);
                                            /** @var mixed[][][][][] $unparsedLoadedData */
                                            $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['drawingAlternateContents'][] = $alternate_content->as_xml();
                                        }
                                    }
                                }
                            }
                            /** @var mixed[][][][] $unparsedLoadedData */
                            $this->read_form_control_properties($dir, $file_worksheet, $doc_sheet, $unparsed_loaded_data);
                            $this->read_printer_settings($dir, $file_worksheet, $doc_sheet, $unparsed_loaded_data);
                            // Loop through definedNames
                            if ($xml_workbook->defined_names) {
                                foreach ($xml_workbook->defined_names->defined_name as $defined_name) {
                                    // Extract range
                                    $extracted_range = (string) $defined_name;
                                    if (($spos = strpos($extracted_range, '!')) !== false) {
                                        $extracted_range = substr($extracted_range, 0, $spos) . str_replace('$', '', substr($extracted_range, $spos));
                                    } else {
                                        $extracted_range = str_replace('$', '', $extracted_range);
                                    }
                                    // Valid range?
                                    if ($extracted_range == '') {
                                        continue;
                                    }
                                    // Some definedNames are only applicable if we are on the same sheet...
                                    if ((string) $defined_name['localSheetId'] != '' && (string) $defined_name['localSheetId'] == $old_sheet_id) {
                                        // Switch on type
                                        switch ((string) $defined_name['name']) {
                                            case '_xlnm._FilterDatabase':
                                                if ((string) $defined_name['hidden'] !== '1') {
                                                    $extracted_range = explode(',', $extracted_range);
                                                    foreach ($extracted_range as $range) {
                                                        $auto_filter_range = $range;
                                                        if (str_contains($auto_filter_range, ':')) {
                                                            $doc_sheet->get_auto_filter()->set_range($auto_filter_range);
                                                        }
                                                    }
                                                }
                                                break;
                                            case '_xlnm.Print_Titles':
                                                // Split $extractedRange
                                                $extracted_range = explode(',', $extracted_range);
                                                // Set print titles
                                                foreach ($extracted_range as $range) {
                                                    $matches = [];
                                                    $range = str_replace('$', '', $range);
                                                    // check for repeating columns, e g. 'A:A' or 'A:D'
                                                    if (Preg::is_match('/!?([A-Z]+)\:([A-Z]+)$/', $range, $matches)) {
                                                        $doc_sheet->get_page_setup()->set_columns_to_repeat_at_left([$matches[1], $matches[2]]);
                                                    } elseif (Preg::is_match('/!?(\d+)\:(\d+)$/', $range, $matches)) {
                                                        // check for repeating rows, e.g. '1:1' or '1:5'
                                                        $doc_sheet->get_page_setup()->set_rows_to_repeat_at_top([(int) $matches[1], (int) $matches[2]]);
                                                    }
                                                }
                                                break;
                                            case '_xlnm.Print_Area':
                                                $range_sets = Preg::split("/('?(?:.*?)'?(?:![A-Z0-9]+:[A-Z0-9]+)),?/", $extracted_range, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) ?: [];
                                                $new_range_sets = [];
                                                foreach ($range_sets as $range_set) {
                                                    [, $range_set] = Worksheet::extract_sheet_title($range_set, true);
                                                    if (empty($range_set)) {
                                                        continue;
                                                    }
                                                    if (!str_contains($range_set, ':')) {
                                                        $range_set = $range_set . ':' . $range_set;
                                                    }
                                                    $new_range_sets[] = str_replace('$', '', $range_set);
                                                }
                                                if (count($new_range_sets) > 0) {
                                                    $doc_sheet->get_page_setup()->set_print_area(implode(',', $new_range_sets));
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }
                                }
                            }
                            // Next sheet id
                            ++$sheet_id;
                        }
                        // Loop through definedNames
                        if ($xml_workbook->defined_names) {
                            foreach ($xml_workbook->defined_names->defined_name as $defined_name) {
                                // Extract range
                                $extracted_range = (string) $defined_name;
                                // Valid range?
                                if ($extracted_range == '') {
                                    continue;
                                }
                                // Some definedNames are only applicable if we are on the same sheet...
                                if ((string) $defined_name['localSheetId'] != '') {
                                    // Local defined name
                                    // Switch on type
                                    switch ((string) $defined_name['name']) {
                                        case '_xlnm._FilterDatabase':
                                        case '_xlnm.Print_Titles':
                                        case '_xlnm.Print_Area':
                                            break;
                                        default:
                                            if ($map_sheet_id[(int) $defined_name['localSheetId']] !== null) {
                                                $range = Worksheet::extract_sheet_title($extracted_range, true);
                                                $scope = $excel->get_sheet($map_sheet_id[(int) $defined_name['localSheetId']]);
                                                if (str_contains((string) $defined_name, '!')) {
                                                    $range[0] = str_replace("''", "'", $range[0]);
                                                    $range[0] = str_replace("'", '', $range[0]);
                                                    if ($worksheet = $excel->get_sheet_by_name($range[0])) {
                                                        $excel->add_defined_name(Defined_Name::create_instance((string) $defined_name['name'], $worksheet, $extracted_range, true, $scope));
                                                    } else {
                                                        $excel->add_defined_name(Defined_Name::create_instance((string) $defined_name['name'], $scope, $extracted_range, true, $scope));
                                                    }
                                                } else {
                                                    $excel->add_defined_name(Defined_Name::create_instance((string) $defined_name['name'], $scope, $extracted_range, true));
                                                }
                                            }
                                            break;
                                    }
                                } elseif (!isset($defined_name['localSheetId'])) {
                                    // "Global" definedNames
                                    $located_sheet = null;
                                    if (str_contains((string) $defined_name, '!')) {
                                        // Modify range, and extract the first worksheet reference
                                        // Need to split on a comma or a space if not in quotes, and extract the first part.
                                        $defined_name_value_parts = Preg::split("/[ ,](?=([^']*'[^']*')*[^']*\$)/miuU", $extracted_range);
                                        // Extract sheet name
                                        [$extracted_sheet_name] = Worksheet::extract_sheet_title((string) $defined_name_value_parts[0], true, true);
                                        // Locate sheet
                                        $located_sheet = $excel->get_sheet_by_name("{$extracted_sheet_name}");
                                    }
                                    if ($located_sheet === null && !Defined_Name::test_if_formula($extracted_range)) {
                                        $extracted_range = '#REF!';
                                    }
                                    $excel->add_defined_name(Defined_Name::create_instance((string) $defined_name['name'], $located_sheet, $extracted_range, false));
                                }
                            }
                        }
                    }
                    if ($this->create_blank_sheet_if_none_read && !$sheet_created) {
                        $excel->create_sheet();
                    }
                    (new Workbook_View($excel))->view_settings($xml_workbook, $main_ns, $map_sheet_id, $this->read_data_only);
                    break;
            }
        }
        if (!$this->read_data_only) {
            $content_types = $this->load_zip('[Content_Types].xml');
            // Default content types
            foreach ($content_types->Default as $content_type) {
                switch ($content_type['ContentType']) {
                    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings':
                        $unparsed_loaded_data['default_content_types'][(string) $content_type['Extension']] = (string) $content_type['ContentType'];
                        break;
                }
            }
            // Override content types
            foreach ($content_types->Override as $content_type) {
                switch ($content_type['ContentType']) {
                    case 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml':
                        if ($this->include_charts) {
                            $chart_entry_ref = ltrim((string) $content_type['PartName'], '/');
                            $chart_elements = $this->load_zip($chart_entry_ref);
                            $chart_reader = new Chart($chart_ns, $drawing_ns);
                            $obj_chart = $chart_reader->read_chart($chart_elements, basename($chart_entry_ref, '.xml'));
                            if (isset($charts[$chart_entry_ref])) {
                                $chart_position_ref = $charts[$chart_entry_ref]['sheet'] . '!' . $charts[$chart_entry_ref]['id'];
                                if (isset($chart_details[$chart_position_ref]) && $excel->get_sheet_by_name($charts[$chart_entry_ref]['sheet']) !== null) {
                                    $excel->get_sheet_by_name($charts[$chart_entry_ref]['sheet'])->add_chart($obj_chart);
                                    $obj_chart->set_worksheet($excel->get_sheet_by_name($charts[$chart_entry_ref]['sheet']));
                                    // For oneCellAnchor or absoluteAnchor positioned charts,
                                    //     toCoordinate is not in the data. Does it need to be calculated?
                                    if (array_key_exists('toCoordinate', $chart_details[$chart_position_ref])) {
                                        // twoCellAnchor
                                        $obj_chart->set_top_left_position($chart_details[$chart_position_ref]['fromCoordinate'], $chart_details[$chart_position_ref]['fromOffsetX'], $chart_details[$chart_position_ref]['fromOffsetY']);
                                        $obj_chart->set_bottom_right_position($chart_details[$chart_position_ref]['toCoordinate'], $chart_details[$chart_position_ref]['toOffsetX'], $chart_details[$chart_position_ref]['toOffsetY']);
                                    } else {
                                        // oneCellAnchor or absoluteAnchor (e.g. Chart sheet)
                                        $obj_chart->set_top_left_position($chart_details[$chart_position_ref]['fromCoordinate'], $chart_details[$chart_position_ref]['fromOffsetX'], $chart_details[$chart_position_ref]['fromOffsetY']);
                                        $obj_chart->set_bottom_right_position('', $chart_details[$chart_position_ref]['width'], $chart_details[$chart_position_ref]['height']);
                                        if (array_key_exists('oneCellAnchor', $chart_details[$chart_position_ref])) {
                                            $obj_chart->set_one_cell_anchor($chart_details[$chart_position_ref]['oneCellAnchor']);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    // unparsed
                    case 'application/vnd.ms-excel.controlproperties+xml':
                        $unparsed_loaded_data['override_content_types'][(string) $content_type['PartName']] = (string) $content_type['ContentType'];
                        break;
                }
            }
        }
        /** @var array<array<array<array<string>|string>>> $unparsedLoadedData */
        $excel->set_unparsed_loaded_data($unparsed_loaded_data);
        $zip->close();
        return $excel;
    }
    private function parse_rich_text(?Simple_Xml_Element $is): Rich_Text
    {
        $value = new Rich_Text();
        if (isset($is->t)) {
            $value->create_text(String_Helper::control_character_ooxml2php((string) $is->t));
        } elseif ($is !== null) {
            if (is_object($is->r)) {
                foreach ($is->r as $run) {
                    if (!isset($run->r_pr)) {
                        $value->create_text(String_Helper::control_character_ooxml2php((string) $run->t));
                    } else {
                        $obj_text = $value->create_text_run(String_Helper::control_character_ooxml2php((string) $run->t));
                        $obj_font = $obj_text->get_font() ?? new Style_Font();
                        if (isset($run->r_pr->r_font)) {
                            $attr = $run->r_pr->r_font->attributes();
                            if (isset($attr['val'])) {
                                $obj_font->set_name((string) $attr['val']);
                            }
                        }
                        if (isset($run->r_pr->sz)) {
                            $attr = $run->r_pr->sz->attributes();
                            if (isset($attr['val'])) {
                                $obj_font->set_size((float) $attr['val']);
                            }
                        }
                        if (isset($run->r_pr->color)) {
                            $obj_font->set_color(new Color($this->style_reader->read_color($run->r_pr->color)));
                        }
                        if (isset($run->r_pr->b)) {
                            $attr = $run->r_pr->b->attributes();
                            if (isset($attr['val']) && self::boolean((string) $attr['val']) || !isset($attr['val'])) {
                                $obj_font->set_bold(true);
                            }
                        }
                        if (isset($run->r_pr->i)) {
                            $attr = $run->r_pr->i->attributes();
                            if (isset($attr['val']) && self::boolean((string) $attr['val']) || !isset($attr['val'])) {
                                $obj_font->set_italic(true);
                            }
                        }
                        if (isset($run->r_pr->vert_align)) {
                            $attr = $run->r_pr->vert_align->attributes();
                            if (isset($attr['val'])) {
                                $vert_align = strtolower((string) $attr['val']);
                                if ($vert_align == 'superscript') {
                                    $obj_font->set_superscript(true);
                                }
                                if ($vert_align == 'subscript') {
                                    $obj_font->set_subscript(true);
                                }
                            }
                        }
                        if (isset($run->r_pr->u)) {
                            $attr = $run->r_pr->u->attributes();
                            if (!isset($attr['val'])) {
                                $obj_font->set_underline(Style_Font::UNDERLINE_SINGLE);
                            } else {
                                $obj_font->set_underline((string) $attr['val']);
                            }
                        }
                        if (isset($run->r_pr->strike)) {
                            $attr = $run->r_pr->strike->attributes();
                            if (isset($attr['val']) && self::boolean((string) $attr['val']) || !isset($attr['val'])) {
                                $obj_font->set_strikethrough(true);
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }
    private function read_ribbon(Spreadsheet $excel, string $custom_ui_target, Zip_Archive $zip): void
    {
        $base_dir = dirname($custom_ui_target);
        $name_custom_ui = basename($custom_ui_target);
        // get the xml file (ribbon)
        $local_ribbon = $this->get_from_zip_archive($zip, $custom_ui_target);
        $custom_ui_images_names = [];
        $custom_ui_images_binaries = [];
        // something like customUI/_rels/customUI.xml.rels
        $path_rels = $base_dir . '/_rels/' . $name_custom_ui . '.rels';
        $data_rels = $this->get_from_zip_archive($zip, $path_rels);
        if ($data_rels) {
            // exists and not empty if the ribbon have some pictures (other than internal MSO)
            $ui_rels = simplexml_load_string($this->get_security_scanner_or_throw()->scan($data_rels), Simple_Xml_Element::class, $this->parse_huge ? LIBXML_PARSEHUGE : 0);
            if (false !== $ui_rels) {
                // we need to save id and target to avoid parsing customUI.xml and "guess" if it's a pseudo callback who load the image
                foreach ($ui_rels->Relationship as $ele) {
                    if ((string) $ele['Type'] === Namespaces::SCHEMA_OFFICE_DOCUMENT . '/image') {
                        // an image ?
                        $custom_ui_images_names[(string) $ele['Id']] = (string) $ele['Target'];
                        $custom_ui_images_binaries[(string) $ele['Target']] = $this->get_from_zip_archive($zip, $base_dir . '/' . $ele['Target']);
                    }
                }
            }
        }
        if ($local_ribbon) {
            $excel->set_ribbon_xml_data($custom_ui_target, $local_ribbon);
            if (count($custom_ui_images_names) > 0 && count($custom_ui_images_binaries) > 0) {
                $excel->set_ribbon_bin_objects($custom_ui_images_names, $custom_ui_images_binaries);
            } else {
                $excel->set_ribbon_bin_objects(null, null);
            }
        } else {
            $excel->set_ribbon_xml_data(null, null);
            $excel->set_ribbon_bin_objects(null, null);
        }
    }
    /** @param null|bool|mixed[]|SimpleXMLElement $array */
    private static function get_array_item(null|array|bool|Simple_Xml_Element $array, int|string $key = 0): mixed
    {
        return $array === null || is_bool($array) ? null : $array[$key] ?? null;
    }
    /** @param null|bool|mixed[]|SimpleXMLElement $array */
    private static function get_array_item_string(null|array|bool|Simple_Xml_Element $array, int|string $key = 0): string
    {
        $ret_val = self::get_array_item($array, $key);
        return String_Helper::convert_to_string($ret_val, false);
    }
    /** @param null|bool|mixed[]|SimpleXMLElement $array */
    private static function get_array_item_int_or_sxml(null|array|bool|Simple_Xml_Element $array, int|string $key = 0): int|Simple_Xml_Element
    {
        $ret_val = self::get_array_item($array, $key);
        return is_int($ret_val) || $ret_val instanceof Simple_Xml_Element ? $ret_val : 0;
    }
    private static function dir_add(null|Simple_Xml_Element|string $base, null|Simple_Xml_Element|string $add): string
    {
        $base = (string) $base;
        $add = (string) $add;
        return Preg::replace('~[^/]+/\.\./~', '', dirname($base) . "/{$add}");
    }
    /** @return mixed[] */
    private static function to_css_array(string $style): array
    {
        $style = self::strip_white_space_from_style_string($style);
        $temp = explode(';', $style);
        $style = [];
        foreach ($temp as $item) {
            $item = explode(':', $item);
            if (str_contains($item[1], 'px')) {
                $item[1] = str_replace('px', '', $item[1]);
            } elseif (str_contains($item[1], 'pt')) {
                $item[1] = str_replace('pt', '', $item[1]);
                $item[1] = Font::font_size_to_pixels((float) $item[1]);
            } elseif (str_contains($item[1], 'in')) {
                $item[1] = str_replace('in', '', $item[1]);
                $item[1] = (int) Font::inch_size_to_pixels((float) $item[1]);
            } elseif (str_contains($item[1], 'cm')) {
                $item[1] = str_replace('cm', '', $item[1]);
                $item[1] = (int) Font::centimeter_size_to_pixels((float) $item[1]);
            } elseif (str_contains($item[1], 'mm')) {
                $item[1] = str_replace('mm', '', $item[1]);
                $item[1] = (int) Font::centimeter_size_to_pixels((float) $item[1] / 10);
            }
            $style[$item[0]] = $item[1];
        }
        return $style;
    }
    public static function strip_white_space_from_style_string(string $string): string
    {
        return trim(str_replace(["\r", "\n", ' '], '', $string), ';');
    }
    private static function boolean(string $value): bool
    {
        if (is_numeric($value)) {
            return (bool) $value;
        }
        return $value === 'true' || $value === 'TRUE';
    }
    /** @param string[] $hyperlinks */
    private function read_hyper_link_drawing(\Php_Office\Php_Spreadsheet\Worksheet\Drawing $obj_drawing, Simple_Xml_Element $cell_anchor, array $hyperlinks): void
    {
        $hlink_click = $cell_anchor->pic->nv_pic_pr->c_nv_pr->children(Namespaces::DRAWINGML)->hlink_click;
        if ($hlink_click->count() === 0) {
            return;
        }
        $hlink_id = (string) self::get_attributes($hlink_click, Namespaces::SCHEMA_OFFICE_DOCUMENT)['id'];
        $hyperlink = new Hyperlink(Preg::replace('/^#/', 'sheet://', $hyperlinks[$hlink_id]), self::get_array_item_string(self::get_attributes($cell_anchor->pic->nv_pic_pr->c_nv_pr), 'name'));
        $obj_drawing->set_hyperlink($hyperlink);
    }
    private function read_protection(Spreadsheet $excel, Simple_Xml_Element $xml_workbook): void
    {
        if (!$xml_workbook->workbook_protection) {
            return;
        }
        $security = $excel->get_security();
        $security->set_lock_revision(self::get_lock_value($xml_workbook->workbook_protection, 'lockRevision'));
        $security->set_lock_structure(self::get_lock_value($xml_workbook->workbook_protection, 'lockStructure'));
        $security->set_lock_windows(self::get_lock_value($xml_workbook->workbook_protection, 'lockWindows'));
        if ($xml_workbook->workbook_protection['revisionsPassword']) {
            $security->set_revisions_password((string) $xml_workbook->workbook_protection['revisionsPassword'], true);
        }
        if ($xml_workbook->workbook_protection['revisionsAlgorithmName']) {
            $security->set_revisions_algorithm_name((string) $xml_workbook->workbook_protection['revisionsAlgorithmName']);
        }
        if ($xml_workbook->workbook_protection['revisionsSaltValue']) {
            $security->set_revisions_salt_value((string) $xml_workbook->workbook_protection['revisionsSaltValue'], false);
        }
        if ($xml_workbook->workbook_protection['revisionsSpinCount']) {
            $security->set_revisions_spin_count((int) $xml_workbook->workbook_protection['revisionsSpinCount']);
        }
        if ($xml_workbook->workbook_protection['revisionsHashValue']) {
            if ($security->advanced_revisions_password()) {
                $security->set_revisions_password((string) $xml_workbook->workbook_protection['revisionsHashValue'], true);
            }
        }
        if ($xml_workbook->workbook_protection['workbookPassword']) {
            $security->set_workbook_password((string) $xml_workbook->workbook_protection['workbookPassword'], true);
        }
        if ($xml_workbook->workbook_protection['workbookAlgorithmName']) {
            $security->set_workbook_algorithm_name((string) $xml_workbook->workbook_protection['workbookAlgorithmName']);
        }
        if ($xml_workbook->workbook_protection['workbookSaltValue']) {
            $security->set_workbook_salt_value((string) $xml_workbook->workbook_protection['workbookSaltValue'], false);
        }
        if ($xml_workbook->workbook_protection['workbookSpinCount']) {
            $security->set_workbook_spin_count((int) $xml_workbook->workbook_protection['workbookSpinCount']);
        }
        if ($xml_workbook->workbook_protection['workbookHashValue']) {
            if ($security->advanced_password()) {
                $security->set_workbook_password((string) $xml_workbook->workbook_protection['workbookHashValue'], true);
            }
        }
    }
    private static function get_lock_value(Simple_Xml_Element $protection, string $key): ?bool
    {
        $return_value = null;
        $protect_key = $protection[$key];
        if (!empty($protect_key)) {
            $protect_key = (string) $protect_key;
            $return_value = $protect_key !== 'false' && (bool) $protect_key;
        }
        return $return_value;
    }
    /** @param mixed[][][][] $unparsedLoadedData */
    private function read_form_control_properties(string $dir, string $file_worksheet, Worksheet $doc_sheet, array &$unparsed_loaded_data): void
    {
        $zip = $this->zip;
        if ($zip->locate_name(dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels') === false) {
            return;
        }
        $filename = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
        $rels_worksheet = $this->load_zip_no_namespace($filename, Namespaces::RELATIONSHIPS);
        $ctrl_props = [];
        foreach ($rels_worksheet->Relationship as $ele) {
            if ((string) $ele['Type'] === Namespaces::SCHEMA_OFFICE_DOCUMENT . '/ctrlProp') {
                $ctrl_props[(string) $ele['Id']] = $ele;
            }
        }
        $unparsed_ctrl_props =& $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['ctrlProps'];
        foreach ($ctrl_props as $r_id => $ctrl_prop) {
            $r_id = substr($r_id, 3);
            // rIdXXX
            $unparsed_ctrl_props[$r_id] = [];
            $unparsed_ctrl_props[$r_id]['filePath'] = self::dir_add("{$dir}/{$file_worksheet}", $ctrl_prop['Target']);
            $unparsed_ctrl_props[$r_id]['relFilePath'] = (string) $ctrl_prop['Target'];
            $unparsed_ctrl_props[$r_id]['content'] = $this->get_security_scanner_or_throw()->scan($this->get_from_zip_archive($zip, $unparsed_ctrl_props[$r_id]['filePath']));
        }
        unset($unparsed_ctrl_props);
    }
    /** @param mixed[][][][] $unparsedLoadedData */
    private function read_printer_settings(string $dir, string $file_worksheet, Worksheet $doc_sheet, array &$unparsed_loaded_data): void
    {
        if ($this->read_data_only) {
            return;
        }
        $zip = $this->zip;
        if ($zip->locate_name(dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels') === false) {
            return;
        }
        $filename = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
        $rels_worksheet = $this->load_zip_no_namespace($filename, Namespaces::RELATIONSHIPS);
        $sheet_printer_settings = [];
        foreach ($rels_worksheet->Relationship as $ele) {
            if ((string) $ele['Type'] === Namespaces::SCHEMA_OFFICE_DOCUMENT . '/printerSettings') {
                $sheet_printer_settings[(string) $ele['Id']] = $ele;
            }
        }
        $unparsed_printer_settings =& $unparsed_loaded_data['sheets'][$doc_sheet->get_code_name()]['printerSettings'];
        foreach ($sheet_printer_settings as $r_id => $printer_settings) {
            $r_id = substr($r_id, 3);
            // rIdXXX
            if (!str_ends_with($r_id, 'ps')) {
                $r_id = $r_id . 'ps';
                // rIdXXX, add 'ps' suffix to avoid identical resource identifier collision with unparsed vmlDrawing
            }
            $unparsed_printer_settings[$r_id] = [];
            $target = str_replace('/xl/', '../', (string) $printer_settings['Target']);
            $unparsed_printer_settings[$r_id]['filePath'] = self::dir_add("{$dir}/{$file_worksheet}", $target);
            $unparsed_printer_settings[$r_id]['relFilePath'] = $target;
            $unparsed_printer_settings[$r_id]['content'] = $this->get_security_scanner_or_throw()->scan($this->get_from_zip_archive($zip, $unparsed_printer_settings[$r_id]['filePath']));
        }
        unset($unparsed_printer_settings);
    }
    /** @return array{string, string} */
    private function get_workbook_base_name(): array
    {
        $workbook_basename = '';
        $xml_namespace_base = '';
        // check if it is an OOXML archive
        $rels = $this->load_zip(self::INITIAL_FILE);
        foreach ($rels->children(Namespaces::RELATIONSHIPS)->Relationship as $rel) {
            $rel = self::get_attributes($rel);
            $type = (string) $rel['Type'];
            switch ($type) {
                case Namespaces::OFFICE_DOCUMENT:
                case Namespaces::PURL_OFFICE_DOCUMENT:
                    $basename = basename((string) $rel['Target']);
                    $xml_namespace_base = dirname($type);
                    if (Preg::is_match('/workbook.*\.xml/', $basename)) {
                        $workbook_basename = $basename;
                    }
                    break;
            }
        }
        return [$workbook_basename, $xml_namespace_base];
    }
    private function read_sheet_protection(Worksheet $doc_sheet, Simple_Xml_Element $xml_sheet): void
    {
        if ($this->read_data_only || !$xml_sheet->sheet_protection) {
            return;
        }
        $algorithm_name = (string) $xml_sheet->sheet_protection['algorithmName'];
        $protection = $doc_sheet->get_protection();
        $protection->set_algorithm($algorithm_name);
        if ($algorithm_name) {
            $protection->set_password((string) $xml_sheet->sheet_protection['hashValue'], true);
            $protection->set_salt((string) $xml_sheet->sheet_protection['saltValue']);
            $protection->set_spin_count((int) $xml_sheet->sheet_protection['spinCount']);
        } else {
            $protection->set_password((string) $xml_sheet->sheet_protection['password'], true);
        }
        if ($xml_sheet->protected_ranges->protected_range) {
            foreach ($xml_sheet->protected_ranges->protected_range as $protected_range) {
                $doc_sheet->protect_cells((string) $protected_range['sqref'], (string) $protected_range['password'], true, (string) $protected_range['name'], (string) $protected_range['securityDescriptor']);
            }
        }
    }
    private function read_auto_filter(Simple_Xml_Element $xml_sheet, Worksheet $doc_sheet): void
    {
        if ($xml_sheet && $xml_sheet->auto_filter) {
            (new Auto_Filter($doc_sheet, $xml_sheet))->load();
        }
    }
    private function read_background_image(Simple_Xml_Element $xml_sheet, Worksheet $doc_sheet, string $rels_name): void
    {
        if ($xml_sheet && $xml_sheet->picture) {
            $id = self::get_array_item_string(self::get_attributes($xml_sheet->picture, Namespaces::SCHEMA_OFFICE_DOCUMENT), 'id');
            $rels = $this->load_zip($rels_name);
            foreach ($rels->Relationship as $rel) {
                $attrs = $rel->attributes() ?? [];
                $rid = (string) ($attrs['Id'] ?? '');
                $target = (string) ($attrs['Target'] ?? '');
                if ($rid === $id && str_starts_with($target, '..')) {
                    $target = 'xl' . substr($target, 2);
                    $content = $this->get_from_zip_archive($this->zip, $target);
                    $doc_sheet->set_background_image($content);
                }
            }
        }
    }
    /**
     * @param TableDxfsStyle[] $tableStyles
     * @param Style[] $dxfs
     */
    private function read_tables(Simple_Xml_Element $xml_sheet, Worksheet $doc_sheet, string $dir, string $file_worksheet, Zip_Archive $zip, string $namespace_table, array $table_styles, array $dxfs): void
    {
        if ($xml_sheet && $xml_sheet->table_parts) {
            /** @var array{count: scalar} */
            $attributes = $xml_sheet->table_parts->attributes() ?? ['count' => 0];
            if ((int) $attributes['count'] > 0) {
                $this->read_tables_in_tables_file($xml_sheet, $dir, $file_worksheet, $zip, $doc_sheet, $namespace_table, $table_styles, $dxfs);
            }
        }
    }
    /**
     * @param TableDxfsStyle[] $tableStyles
     * @param Style[] $dxfs
     */
    private function read_tables_in_tables_file(Simple_Xml_Element $xml_sheet, string $dir, string $file_worksheet, Zip_Archive $zip, Worksheet $doc_sheet, string $namespace_table, array $table_styles, array $dxfs): void
    {
        foreach ($xml_sheet->table_parts->table_part as $table_part) {
            $relation = self::get_attributes($table_part, Namespaces::SCHEMA_OFFICE_DOCUMENT);
            $table_part_rel = (string) $relation['id'];
            $relations_file_name = dirname("{$dir}/{$file_worksheet}") . '/_rels/' . basename($file_worksheet) . '.rels';
            if ($zip->locate_name($relations_file_name) !== false) {
                $rels_table_references = $this->load_zip($relations_file_name, Namespaces::RELATIONSHIPS);
                foreach ($rels_table_references->Relationship as $relationship) {
                    $relationship_attributes = self::get_attributes($relationship, '');
                    if ((string) $relationship_attributes['Id'] === $table_part_rel) {
                        $relationship_file_name = (string) $relationship_attributes['Target'];
                        $relationship_file_path = dirname("{$dir}/{$file_worksheet}") . '/' . $relationship_file_name;
                        $relationship_file_path = File::realpath($relationship_file_path);
                        if ($this->file_exists_in_archive($this->zip, $relationship_file_path)) {
                            $table_xml = $this->load_zip($relationship_file_path, $namespace_table);
                            (new Table_Reader($doc_sheet, $table_xml))->load($table_styles, $dxfs);
                        }
                    }
                }
            }
        }
    }
    /** @return mixed[] */
    private static function extract_styles(?Simple_Xml_Element $sxml, string $node1, string $node2): array
    {
        $array = [];
        if ($sxml && $sxml->{$node1}->{$node2}) {
            /** @var SimpleXMLElement */
            $temp = $sxml->{$node1}->{$node2};
            foreach ($temp as $node) {
                $array[] = $node;
            }
        }
        return $array;
    }
    /** @return string[] */
    private static function extract_palette(?Simple_Xml_Element $sxml): array
    {
        $array = [];
        if ($sxml && $sxml->colors->indexed_colors) {
            foreach ($sxml->colors->indexed_colors->rgb_color as $node) {
                $attr = $node->attributes();
                if (isset($attr['rgb'])) {
                    $array[] = (string) $attr['rgb'];
                }
            }
        }
        return $array;
    }
    private function process_ignored_errors(Simple_Xml_Element $xml, Worksheet $sheet): void
    {
        $cell_collection = $sheet->get_cell_collection();
        $attributes = self::get_attributes($xml);
        $sqref = (string) ($attributes['sqref'] ?? '');
        $number_stored_as_text = (string) ($attributes['numberStoredAsText'] ?? '');
        $formula = (string) ($attributes['formula'] ?? '');
        $formula_range = (string) ($attributes['formulaRange'] ?? '');
        $two_digit_text_year = (string) ($attributes['twoDigitTextYear'] ?? '');
        $eval_error = (string) ($attributes['evalError'] ?? '');
        if (!empty($sqref)) {
            $exploded_sqref = explode(' ', $sqref);
            $pattern1 = '/^([A-Z]{1,3})([0-9]{1,7})(:([A-Z]{1,3})([0-9]{1,7}))?$/';
            foreach ($exploded_sqref as $sqref1) {
                if (Preg::is_match($pattern1, $sqref1, $matches)) {
                    $first_row = $matches[2];
                    $first_col = $matches[1];
                    if ($matches[3] !== null) {
                        $last_col = (string) $matches[4];
                        $last_row = (string) $matches[5];
                    } else {
                        $last_col = $first_col;
                        $last_row = $first_row;
                    }
                    String_Helper::string_increment($last_col);
                    for ($row = $first_row; $row <= $last_row; ++$row) {
                        for ($col = $first_col; $col !== $last_col; String_Helper::string_increment($col)) {
                            if (!$cell_collection->has2("{$col}{$row}")) {
                                continue;
                            }
                            if ($number_stored_as_text === '1') {
                                $sheet->get_cell("{$col}{$row}")->get_ignored_errors()->set_number_stored_as_text(true);
                            }
                            if ($formula === '1') {
                                $sheet->get_cell("{$col}{$row}")->get_ignored_errors()->set_formula(true);
                            }
                            if ($formula_range === '1') {
                                $sheet->get_cell("{$col}{$row}")->get_ignored_errors()->set_formula_range(true);
                            }
                            if ($two_digit_text_year === '1') {
                                $sheet->get_cell("{$col}{$row}")->get_ignored_errors()->set_two_digit_text_year(true);
                            }
                            if ($eval_error === '1') {
                                $sheet->get_cell("{$col}{$row}")->get_ignored_errors()->set_eval_error(true);
                            }
                        }
                    }
                }
            }
        }
    }
    private static function store_formula_attributes(Simple_Xml_Element $f, Worksheet $doc_sheet, string $r): void
    {
        $formula_attributes = [];
        $attributes = $f->attributes();
        if (isset($attributes['t'])) {
            $formula_attributes['t'] = (string) $attributes['t'];
        }
        if (isset($attributes['ref'])) {
            $formula_attributes['ref'] = (string) $attributes['ref'];
        }
        if (!empty($formula_attributes)) {
            $doc_sheet->get_cell($r)->set_formula_attributes($formula_attributes);
        }
    }
    private static function only_note_vml(string $data): bool
    {
        $data = str_replace('<br>', '<br/>', $data);
        try {
            $sxml = @simplexml_load_string($data);
        } catch (Throwable) {
            $sxml = false;
        }
        if ($sxml === false) {
            return false;
        }
        $shapes = $sxml->children(Namespaces::URN_VML);
        foreach ($shapes->shape as $shape) {
            $client_data = $shape->children(Namespaces::URN_EXCEL);
            if (!isset($client_data->client_data)) {
                return false;
            }
            $attrs = $client_data->client_data->attributes();
            if (!isset($attrs['ObjectType'])) {
                return false;
            }
            $object_type = (string) $attrs['ObjectType'];
            if ($object_type !== 'Note') {
                return false;
            }
        }
        return true;
    }
}