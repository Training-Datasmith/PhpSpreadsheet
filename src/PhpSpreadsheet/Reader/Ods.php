<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Closure;
use Composer\Pcre\Preg;
use DateTime;
use DateTimeZone;
use Dom_Attr;
use Dom_Document;
use Dom_Element;
use Dom_Node;
use Dom_Text;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Helper\Dimension as HelperDimension;
use Php_Office\Php_Spreadsheet\Reader\Ods\Auto_Filter;
use Php_Office\Php_Spreadsheet\Reader\Ods\Defined_Names;
use Php_Office\Php_Spreadsheet\Reader\Ods\Formula_Translator;
use Php_Office\Php_Spreadsheet\Reader\Ods\Page_Settings;
use Php_Office\Php_Spreadsheet\Reader\Ods\Properties as DocumentProperties;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Throwable;
use Xml_Reader;
use Zip_Archive;
class Ods extends Base_Reader
{
    public const INITIAL_FILE = 'content.xml';
    /**
     * Create a new Ods Reader instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->security_scanner = Xml_Scanner::get_instance($this);
    }
    /**
     * Can the current IReader read the file?
     */
    public function can_read(string $filename): bool
    {
        $mime_type = 'UNKNOWN';
        // Load file
        if (File::test_file_no_throw($filename, '')) {
            $zip = new Zip_Archive();
            if ($zip->open($filename) === true) {
                // check if it is an OOXML archive
                $stat = $zip->stat_name('mimetype');
                if (!empty($stat) && $stat['size'] <= 255) {
                    $mime_type = $zip->get_from_name($stat['name']);
                } elseif ($zip->stat_name('META-INF/manifest.xml')) {
                    $xml = simplexml_load_string($this->get_security_scanner_or_throw()->scan($zip->get_from_name('META-INF/manifest.xml')));
                    if ($xml !== false) {
                        $namespaces_content = $xml->get_namespaces(true);
                        if (isset($namespaces_content['manifest'])) {
                            $manifest = $xml->children($namespaces_content['manifest']);
                            foreach ($manifest as $manifest_data_set) {
                                $manifest_attributes = $manifest_data_set->attributes($namespaces_content['manifest']);
                                if ($manifest_attributes && $manifest_attributes->{'full-path'} == '/') {
                                    $mime_type = (string) $manifest_attributes->{'media-type'};
                                    break;
                                }
                            }
                        }
                    }
                }
                $zip->close();
            }
        }
        return $mime_type === 'application/vnd.oasis.opendocument.spreadsheet';
    }
    /**
     * Reads names of the worksheets from a file, without parsing the whole file to a PhpSpreadsheet object.
     *
     * @return string[]
     */
    public function list_worksheet_names(string $filename): array
    {
        File::assert_file($filename, self::INITIAL_FILE);
        $worksheet_names = [];
        $xml = new Xml_Reader();
        $xml->xml($this->get_security_scanner_or_throw()->scan_file('zip://' . realpath($filename) . '#' . self::INITIAL_FILE));
        $xml->set_parser_property(2, true);
        // Step into the first level of content of the XML
        $xml->read();
        while ($xml->read()) {
            // Quickly jump through to the office:body node
            while ($xml->name !== 'office:body') {
                if ($xml->is_empty_element) {
                    $xml->read();
                } else {
                    $xml->next();
                }
            }
            // Now read each node until we find our first table:table node
            while ($xml->read()) {
                $xml_name = $xml->name;
                if ($xml_name == 'table:table' && $xml->node_type == Xml_Reader::ELEMENT) {
                    // Loop through each table:table node reading the table:name attribute for each worksheet name
                    do {
                        $worksheet_name = $xml->get_attribute('table:name');
                        if (!empty($worksheet_name)) {
                            $worksheet_names[] = $worksheet_name;
                        }
                        $xml->next();
                    } while ($xml->name == 'table:table' && $xml->node_type == Xml_Reader::ELEMENT);
                }
            }
        }
        return $worksheet_names;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{
     *   worksheetName: string,
     *   lastColumnLetter: string,
     *   lastColumnIndex: int,
     *   totalRows: int,
     *   totalColumns: int,
     *   sheetState: string
     * }>
     */
    public function list_worksheet_info(string $filename): array
    {
        File::assert_file($filename, self::INITIAL_FILE);
        $worksheet_info = [];
        $xml = new Xml_Reader();
        $xml->xml($this->get_security_scanner_or_throw()->scan_file('zip://' . realpath($filename) . '#' . self::INITIAL_FILE));
        $xml->set_parser_property(2, true);
        // Step into the first level of content of the XML
        $xml->read();
        $table_visibility = [];
        $last_table_style = '';
        while ($xml->read()) {
            if ($xml->name === 'style:style') {
                $style_type = $xml->get_attribute('style:family');
                if ($style_type === 'table') {
                    $last_table_style = $xml->get_attribute('style:name');
                }
            } elseif ($xml->name === 'style:table-properties') {
                $visibility = $xml->get_attribute('table:display');
                $table_visibility[$last_table_style] = $visibility === 'false' ? Worksheet::SHEETSTATE_HIDDEN : Worksheet::SHEETSTATE_VISIBLE;
            } elseif ($xml->name == 'table:table' && $xml->node_type == Xml_Reader::ELEMENT) {
                $worksheet_names[] = $xml->get_attribute('table:name');
                $style_name = $xml->get_attribute('table:style-name') ?? '';
                $visibility = $table_visibility[$style_name] ?? '';
                $tmp_info = ['worksheetName' => (string) $xml->get_attribute('table:name'), 'lastColumnLetter' => 'A', 'lastColumnIndex' => 0, 'totalRows' => 0, 'totalColumns' => 0, 'sheetState' => $visibility];
                // Loop through each child node of the table:table element reading
                $curr_row = 0;
                do {
                    $xml->read();
                    if ($xml->name == 'table:table-row' && $xml->node_type == Xml_Reader::ELEMENT) {
                        $rowspan = $xml->get_attribute('table:number-rows-repeated');
                        $rowspan = empty($rowspan) ? 1 : (int) $rowspan;
                        $curr_row += $rowspan;
                        $curr_col = 0;
                        // Step into the row
                        $xml->read();
                        do {
                            $doread = true;
                            if ($xml->name == 'table:table-cell' && $xml->node_type == Xml_Reader::ELEMENT) {
                                $merge_size = $xml->get_attribute('table:number-columns-repeated');
                                $merge_size = empty($merge_size) ? 1 : (int) $merge_size;
                                $curr_col += $merge_size;
                                if (!$xml->is_empty_element) {
                                    $tmp_info['totalColumns'] = max($tmp_info['totalColumns'], $curr_col);
                                    $tmp_info['totalRows'] = $curr_row;
                                    $xml->next();
                                    $doread = false;
                                }
                            } elseif ($xml->name == 'table:covered-table-cell' && $xml->node_type == Xml_Reader::ELEMENT) {
                                $merge_size = $xml->get_attribute('table:number-columns-repeated');
                                $curr_col += (int) $merge_size;
                            }
                            if ($doread) {
                                $xml->read();
                            }
                        } while ($xml->name != 'table:table-row');
                    }
                } while ($xml->name != 'table:table');
                $tmp_info['lastColumnIndex'] = $tmp_info['totalColumns'] - 1;
                $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['lastColumnIndex'] + 1, true);
                $worksheet_info[] = $tmp_info;
            }
        }
        return $worksheet_info;
    }
    /**
     * Loads PhpSpreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        $spreadsheet->remove_sheet_by_index(0);
        // Load into this instance
        return $this->load_into_existing($filename, $spreadsheet);
    }
    /** @var array<string,
     *  array{
     *     font?:array{
     *       autoColor?: true,
     *       bold?: true,
     *       color?: array{rgb: string},
     *       italic?: true,
     *       name?: non-empty-string,
     *       size?: float|int,
     *       strikethrough?: true,
     *       underline?: 'double'|'single',
     *    },
     *    fill?:array{
     *      fillType?: string,
     *      startColor?: array{rgb: string},
     *    },
     *    alignment?:array{
     *      horizontal?: string,
     *      readOrder?: int,
     *      shrinkToFit?: bool,
     *      textRotation?: int,
     *      vertical?: string,
     *      wrapText?: bool,
     *    },
     *    protection?:array{
     *      locked?: string,
     *      hidden?: string,
     *    },
     *    borders?:array{
     *      bottom?: array{borderStyle:string, color:array{rgb: string}},
     *      left?: array{borderStyle:string, color:array{rgb: string}},
     *      right?: array{borderStyle:string, color:array{rgb: string}},
     *      top?: array{borderStyle:string, color:array{rgb: string}},
     *      diagonal?: array{borderStyle:string, color:array{rgb: string}},
     *      diagonalDirection?: int,
     *    },
     *  }>
     */
    private array $all_styles;
    private int $highest_data_index;
    /**
     * Loads PhpSpreadsheet from file into PhpSpreadsheet instance.
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet): Spreadsheet
    {
        File::assert_file($filename, self::INITIAL_FILE);
        $zip = new Zip_Archive();
        $zip->open($filename);
        // Meta
        $xml = @simplexml_load_string($this->get_security_scanner_or_throw()->scan($zip->get_from_name('meta.xml')));
        if ($xml === false) {
            throw new Exception('Unable to read data from {$pFilename}');
        }
        /** @var array{meta?: string, office?: string, dc?: string} */
        $namespaces_meta = $xml->get_namespaces(true);
        (new Document_Properties($spreadsheet))->load($xml, $namespaces_meta);
        // Styles
        $this->all_styles = [];
        $dom = new Dom_Document('1.01', 'UTF-8');
        $dom->load_xml($this->get_security_scanner_or_throw()->scan($zip->get_from_name('styles.xml')));
        $office_ns = (string) $dom->lookup_namespace_uri('office');
        $style_ns = (string) $dom->lookup_namespace_uri('style');
        $font_ns = (string) $dom->lookup_namespace_uri('fo');
        $automatic_style0 = $this->read_data_only ? null : $dom->get_elements_by_tag_name_ns($office_ns, 'styles')->item(0);
        $automatic_styles = $automatic_style0 === null ? [] : $automatic_style0->get_elements_by_tag_name_ns($style_ns, 'default-style');
        foreach ($automatic_styles as $automatic_style) {
            $style_family = $automatic_style->get_attribute_ns($style_ns, 'family');
            if ($style_family === 'table-cell') {
                $fonts = [];
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'text-properties') as $text_property) {
                    $fonts = $this->get_font_styles($text_property, $style_ns, $font_ns);
                }
                if (!empty($fonts)) {
                    $spreadsheet->get_default_style()->get_font()->apply_from_array($fonts);
                }
            }
        }
        $automatic_styles = $automatic_style0 === null ? [] : $automatic_style0->get_elements_by_tag_name_ns($style_ns, 'style');
        foreach ($automatic_styles as $automatic_style) {
            $style_name = $automatic_style->get_attribute_ns($style_ns, 'name');
            $style_family = $automatic_style->get_attribute_ns($style_ns, 'family');
            if ($style_family === 'table-cell') {
                $fills = $fonts = [];
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'text-properties') as $text_property) {
                    $fonts = $this->get_font_styles($text_property, $style_ns, $font_ns);
                }
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'table-cell-properties') as $table_cell_property) {
                    $fills = $this->get_fill_styles($table_cell_property, $font_ns);
                }
                if ($style_name !== '') {
                    if (!empty($fonts)) {
                        $this->all_styles[$style_name]['font'] = $fonts;
                        if ($style_name === 'Default') {
                            $spreadsheet->get_default_style()->get_font()->apply_from_array($fonts);
                        }
                    }
                    if (!empty($fills)) {
                        $this->all_styles[$style_name]['fill'] = $fills;
                        if ($style_name === 'Default') {
                            $spreadsheet->get_default_style()->get_fill()->apply_from_array($fills);
                        }
                    }
                }
            }
        }
        $page_settings = new Page_Settings($dom);
        // Main Content
        $dom = new Dom_Document('1.01', 'UTF-8');
        $dom->load_xml($this->get_security_scanner_or_throw()->scan($zip->get_from_name(self::INITIAL_FILE)));
        $table_ns = (string) $dom->lookup_namespace_uri('table');
        $text_ns = (string) $dom->lookup_namespace_uri('text');
        $xlink_ns = (string) $dom->lookup_namespace_uri('xlink');
        $page_settings->read_style_cross_references($dom);
        $auto_filter_reader = new Auto_Filter($spreadsheet, $table_ns);
        $defined_name_reader = new Defined_Names($spreadsheet, $table_ns);
        $column_widths = [];
        $automatic_style0 = $this->read_data_only ? null : $dom->get_elements_by_tag_name_ns($office_ns, 'automatic-styles')->item(0);
        $automatic_styles = $automatic_style0 === null ? [] : $automatic_style0->get_elements_by_tag_name_ns($style_ns, 'style');
        foreach ($automatic_styles as $automatic_style) {
            $style_name = $automatic_style->get_attribute_ns($style_ns, 'name');
            $style_family = $automatic_style->get_attribute_ns($style_ns, 'family');
            if ($style_family === 'table-column') {
                $tcprops = $automatic_style->get_elements_by_tag_name_ns($style_ns, 'table-column-properties');
                $tcprop = $tcprops->item(0);
                if ($tcprop !== null) {
                    $column_width = $tcprop->get_attribute_ns($style_ns, 'column-width');
                    $column_widths[$style_name] = $column_width;
                }
            }
            if ($style_family === 'table-cell') {
                $fonts = $fills = $alignment1 = $alignment2 = $protection = $borders = [];
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'text-properties') as $text_property) {
                    $fonts = $this->get_font_styles($text_property, $style_ns, $font_ns);
                }
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'table-cell-properties') as $table_cell_property) {
                    $fills = $this->get_fill_styles($table_cell_property, $font_ns);
                    $borders = $this->get_border_styles($table_cell_property, $font_ns, $style_ns);
                    $protection = $this->get_protection_styles($table_cell_property, $style_ns);
                }
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'table-cell-properties') as $table_cell_property) {
                    $alignment1 = $this->get_alignment1styles($table_cell_property, $style_ns, $font_ns);
                }
                foreach ($automatic_style->get_elements_by_tag_name_ns($style_ns, 'paragraph-properties') as $paragraph_property) {
                    $alignment2 = $this->get_alignment2styles($paragraph_property, $style_ns, $font_ns);
                }
                if ($style_name !== '') {
                    if (!empty($fonts)) {
                        $this->all_styles[$style_name]['font'] = $fonts;
                    }
                    if (!empty($fills)) {
                        $this->all_styles[$style_name]['fill'] = $fills;
                    }
                    $alignment = array_merge($alignment1, $alignment2);
                    if (!empty($alignment)) {
                        $this->all_styles[$style_name]['alignment'] = $alignment;
                    }
                    if (!empty($protection)) {
                        $this->all_styles[$style_name]['protection'] = $protection;
                    }
                    if (!empty($borders)) {
                        $this->all_styles[$style_name]['borders'] = $borders;
                    }
                }
            }
        }
        // Content
        $item0 = $dom->get_elements_by_tag_name_ns($office_ns, 'body')->item(0);
        $spreadsheets = $item0 === null ? [] : $item0->get_elements_by_tag_name_ns($office_ns, 'spreadsheet');
        foreach ($spreadsheets as $workbook_data) {
            /** @var DOMElement $workbookData */
            $tables = $workbook_data->get_elements_by_tag_name_ns($table_ns, 'table');
            $worksheet_id = 0;
            $sheet_created = false;
            foreach ($tables as $worksheet_data_set) {
                /** @var DOMElement $worksheetDataSet */
                $worksheet_name = $worksheet_data_set->get_attribute_ns($table_ns, 'name');
                // Check loadSheetsOnly
                if ($this->load_sheets_only !== null && $worksheet_name && !in_array($worksheet_name, $this->load_sheets_only)) {
                    continue;
                }
                $worksheet_style_name = $worksheet_data_set->get_attribute_ns($table_ns, 'style-name');
                // Create sheet
                $spreadsheet->create_sheet();
                $sheet_created = true;
                $spreadsheet->set_active_sheet_index($worksheet_id);
                if ($worksheet_name || is_numeric($worksheet_name)) {
                    // Use false for $updateFormulaCellReferences to prevent adjustment of worksheet references in
                    // formula cells... during the load, all formulae should be correct, and we're simply
                    // bringing the worksheet name in line with the formula, not the reverse
                    $spreadsheet->get_active_sheet()->set_title((string) $worksheet_name, false, false);
                }
                // Go through every child of table element
                $row_id = 1;
                $table_column_index = 1;
                $this->highest_data_index = Address_Range::MAX_COLUMN_INT;
                foreach ($worksheet_data_set->child_nodes as $child_node) {
                    /** @var DOMElement $childNode */
                    // Filter elements which are not under the "table" ns
                    if ($child_node->namespace_uri != $table_ns) {
                        continue;
                    }
                    $key = self::extract_node_name($child_node->node_name);
                    switch ($key) {
                        case 'table-header-rows':
                        case 'table-rows':
                            $this->process_table_header_rows($child_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                            break;
                        case 'table-row-group':
                            $this->process_table_row_group($child_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                            break;
                        case 'table-row':
                            $this->process_table_row($child_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                            break;
                        case 'table-header-columns':
                        case 'table-columns':
                            $this->process_table_column_header($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $this->read_empty_cells, true);
                            break;
                        case 'table-column-group':
                            $this->process_table_column_group($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $this->read_empty_cells, true);
                            break;
                        case 'table-column':
                            $this->process_table_column($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $this->read_empty_cells, true);
                            break;
                    }
                }
                $page_settings->set_visibility_for_worksheet($spreadsheet->get_active_sheet(), $worksheet_style_name);
                $page_settings->set_print_settings_for_worksheet($spreadsheet->get_active_sheet(), $worksheet_style_name);
                ++$worksheet_id;
            }
            if ($this->create_blank_sheet_if_none_read && !$sheet_created) {
                $spreadsheet->create_sheet();
            }
        }
        foreach ($spreadsheets as $workbook_data) {
            /** @var DOMElement $workbookData */
            $tables = $workbook_data->get_elements_by_tag_name_ns($table_ns, 'table');
            $worksheet_id = 0;
            foreach ($tables as $worksheet_data_set) {
                /** @var DOMElement $worksheetDataSet */
                $worksheet_name = $worksheet_data_set->get_attribute_ns($table_ns, 'name');
                // Check loadSheetsOnly
                if ($this->load_sheets_only !== null && $worksheet_name && !in_array($worksheet_name, $this->load_sheets_only)) {
                    continue;
                }
                // Create sheet
                $spreadsheet->set_active_sheet_index($worksheet_id);
                $highest_data_column = $spreadsheet->get_active_sheet()->get_highest_data_column();
                $this->highest_data_index = Coordinate::column_index_from_string($highest_data_column);
                // Go through every child of table element processing column widths
                $row_id = 1;
                $table_column_index = 1;
                foreach ($worksheet_data_set->child_nodes as $child_node) {
                    /** @var DOMElement $childNode */
                    if (empty($column_widths) || $this->read_empty_cells) {
                        break;
                    }
                    // Filter elements which are not under the "table" ns
                    if ($child_node->namespace_uri != $table_ns) {
                        continue;
                    }
                    $key = self::extract_node_name($child_node->node_name);
                    switch ($key) {
                        case 'table-header-columns':
                        case 'table-columns':
                            $this->process_table_column_header($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, true, false);
                            break;
                        case 'table-column-group':
                            $this->process_table_column_group($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, true, false);
                            break;
                        case 'table-column':
                            $this->process_table_column($child_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, true, false);
                            break;
                    }
                }
                ++$worksheet_id;
            }
            $auto_filter_reader->read($workbook_data);
            $defined_name_reader->read($workbook_data);
        }
        $spreadsheet->set_active_sheet_index(0);
        if ($zip->locate_name('settings.xml') !== false) {
            $this->process_settings($zip, $spreadsheet);
        }
        // Return
        return $spreadsheet;
    }
    private function process_table_header_rows(Dom_Element $child_node, string $table_ns, int &$row_id, string $worksheet_name, string $office_ns, string $text_ns, string $xlink_ns, Spreadsheet $spreadsheet): void
    {
        foreach ($child_node->child_nodes as $grandchild_node) {
            /** @var DOMElement $grandchildNode */
            $grandkey = self::extract_node_name($grandchild_node->node_name);
            switch ($grandkey) {
                case 'table-row':
                    $this->process_table_row($grandchild_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                    break;
            }
        }
    }
    private function process_table_row_group(Dom_Element $child_node, string $table_ns, int &$row_id, string $worksheet_name, string $office_ns, string $text_ns, string $xlink_ns, Spreadsheet $spreadsheet): void
    {
        foreach ($child_node->child_nodes as $grandchild_node) {
            /** @var DOMElement $grandchildNode */
            $grandkey = self::extract_node_name($grandchild_node->node_name);
            switch ($grandkey) {
                case 'table-row':
                    $this->process_table_row($grandchild_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                    break;
                case 'table-header-rows':
                case 'table-rows':
                    $this->process_table_header_rows($grandchild_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                    break;
                case 'table-row-group':
                    $this->process_table_row_group($grandchild_node, $table_ns, $row_id, $worksheet_name, $office_ns, $text_ns, $xlink_ns, $spreadsheet);
                    break;
            }
        }
    }
    private function process_table_row(Dom_Element $child_node, string $table_ns, int &$row_id, string $worksheet_name, string $office_ns, string $text_ns, string $xlink_ns, Spreadsheet $spreadsheet): void
    {
        if ($child_node->has_attribute_ns($table_ns, 'number-rows-repeated')) {
            $row_repeats = (int) $child_node->get_attribute_ns($table_ns, 'number-rows-repeated');
        } else {
            $row_repeats = 1;
        }
        $worksheet = $spreadsheet->get_sheet_by_name($worksheet_name);
        $column_id = 'A';
        /** @var DOMElement|DOMText $cellData */
        foreach ($child_node->child_nodes as $cell_data) {
            if ($cell_data instanceof Dom_Text) {
                continue;
                // should just be whitespace
            }
            if ($cell_data->has_attribute_ns($table_ns, 'number-columns-repeated')) {
                $col_repeats = (int) $cell_data->get_attribute_ns($table_ns, 'number-columns-repeated');
            } else {
                $col_repeats = 1;
            }
            $style_name = $cell_data->get_attribute_ns($table_ns, 'style-name');
            // When a cell has number-columns-repeated, check if ANY column in the
            // repeated range passes the read filter. If not, skip the entire group.
            // If some columns pass, we need to fall through to the processing block
            // which will handle per-column filtering.
            if (!$this->get_read_filter()->read_cell($column_id, $row_id, $worksheet_name)) {
                if ($col_repeats <= 1) {
                    String_Helper::string_increment($column_id);
                    continue;
                }
                // Check if any column within this repeated group passes the filter
                $any_column_passes = false;
                $temp_col = $column_id;
                for ($i = 0; $i < $col_repeats; ++$i) {
                    if ($i > 0) {
                        String_Helper::string_increment($temp_col);
                    }
                    if ($this->get_read_filter()->read_cell($temp_col, $row_id, $worksheet_name)) {
                        $any_column_passes = true;
                        break;
                    }
                }
                if (!$any_column_passes) {
                    for ($i = 0; $i < $col_repeats; ++$i) {
                        String_Helper::string_increment($column_id);
                    }
                    continue;
                }
                // Fall through to process the cell, with per-column filter checks
            }
            if ($worksheet !== null && ($cell_data->has_child_nodes() || $cell_data->next_sibling !== null) && isset($this->all_styles[$style_name])) {
                $spanned_range = "{$column_id}{$row_id}";
                // the following is sufficient for ods,
                // and does no harm for xlsx/xls.
                $worksheet->get_style($spanned_range)->apply_from_array($this->all_styles[$style_name]);
                // the rest of this block is needed for xlsx/xls,
                // and does no harm for ods.
                if (isset($this->all_styles[$style_name]['borders'])) {
                    $spanned_rows = $cell_data->get_attribute_ns($table_ns, 'number-columns-spanned');
                    $spanned_columns = $cell_data->get_attribute_ns($table_ns, 'number-rows-spanned');
                    $spanned_rows = max((int) $spanned_rows, 1);
                    $spanned_columns = max((int) $spanned_columns, 1);
                    if ($spanned_rows > 1 || $spanned_columns > 1) {
                        $end_row = $row_id + $spanned_rows - 1;
                        $end_col = $column_id;
                        while ($spanned_columns > 1) {
                            String_Helper::string_increment($end_col);
                            --$spanned_columns;
                        }
                        $spanned_range .= ":{$end_col}{$end_row}";
                        $worksheet->get_style($spanned_range)->get_borders()->apply_from_array($this->all_styles[$style_name]['borders']);
                    }
                }
            }
            // Initialize variables
            $formatting = $hyperlink = null;
            $has_calculated_value = false;
            $cell_data_formula = '';
            $cell_data_type = '';
            $cell_data_ref = '';
            if ($cell_data->has_attribute_ns($table_ns, 'formula')) {
                $cell_data_formula = $cell_data->get_attribute_ns($table_ns, 'formula');
                $has_calculated_value = true;
            }
            if ($cell_data->has_attribute_ns($table_ns, 'number-matrix-columns-spanned')) {
                if ($cell_data->has_attribute_ns($table_ns, 'number-matrix-rows-spanned')) {
                    $cell_data_type = 'array';
                    $array_row = (int) $cell_data->get_attribute_ns($table_ns, 'number-matrix-rows-spanned');
                    $array_col = (int) $cell_data->get_attribute_ns($table_ns, 'number-matrix-columns-spanned');
                    $last_row = $row_id + $array_row - 1;
                    $last_col = $column_id;
                    while ($array_col > 1) {
                        String_Helper::string_increment($last_col);
                        --$array_col;
                    }
                    $cell_data_ref = "{$column_id}{$row_id}:{$last_col}{$last_row}";
                }
            }
            // Annotations
            $annotation = $cell_data->get_elements_by_tag_name_ns($office_ns, 'annotation');
            if ($annotation->length > 0 && $annotation->item(0) !== null) {
                $text_node = $annotation->item(0)->get_elements_by_tag_name_ns($text_ns, 'p');
                $text_node_length = $text_node->length;
                $new_line_owed = false;
                for ($text_node_index = 0; $text_node_index < $text_node_length; ++$text_node_index) {
                    $text_node_item = $text_node->item($text_node_index);
                    if ($text_node_item !== null) {
                        $text = $this->scan_element_for_text($text_node_item);
                        if ($new_line_owed) {
                            $spreadsheet->get_active_sheet()->get_comment($column_id . $row_id)->get_text()->create_text("\n");
                        }
                        $new_line_owed = true;
                        $spreadsheet->get_active_sheet()->get_comment($column_id . $row_id)->get_text()->create_text($this->parse_rich_text($text));
                    }
                }
            }
            // Content
            /** @var DOMElement[] $paragraphs */
            $paragraphs = [];
            foreach ($cell_data->child_nodes as $item) {
                /** @var DOMElement $item */
                // Filter text:p elements
                if ($item->node_name == 'text:p') {
                    $paragraphs[] = $item;
                }
            }
            if (count($paragraphs) > 0) {
                $data_value = null;
                // Consolidate if there are multiple p records (maybe with spans as well)
                $data_array = [];
                // Text can have multiple text:p and within those, multiple text:span.
                // text:p newlines, but text:span does not.
                // Also, here we assume there is no text data is span fields are specified, since
                // we have no way of knowing proper positioning anyway.
                foreach ($paragraphs as $p_data) {
                    $data_array[] = $this->scan_element_for_text($p_data);
                }
                $all_cell_data_text = implode("\n", $data_array);
                $type = $cell_data->get_attribute_ns($office_ns, 'value-type');
                $symbol = '';
                $left_hand_currency = Preg::is_match('/\$|£|￥/', $all_cell_data_text, $matches);
                if ($left_hand_currency) {
                    $type = str_replace('float', 'currency', $type);
                    $symbol = (string) $matches[0];
                }
                $custom_formatting = '';
                if ($this->format_callback !== null) {
                    $temp = ($this->format_callback)($type, $all_cell_data_text);
                    if ($temp !== '') {
                        $custom_formatting = $temp;
                    }
                }
                switch ($type) {
                    case 'string':
                        $type = Data_Type::TYPE_STRING;
                        $data_value = $all_cell_data_text;
                        foreach ($paragraphs as $paragraph) {
                            $link = $paragraph->get_elements_by_tag_name_ns($text_ns, 'a');
                            if ($link->length > 0 && $link->item(0) !== null) {
                                $hyperlink = $link->item(0)->get_attribute_ns($xlink_ns, 'href');
                            }
                        }
                        break;
                    case 'boolean':
                        $type = Data_Type::TYPE_BOOL;
                        $data_value = $cell_data->get_attribute_ns($office_ns, 'boolean-value') === 'true' ? true : false;
                        break;
                    case 'percentage':
                        if (!str_contains($all_cell_data_text, '.')) {
                            $formatting = Number_Format::FORMAT_PERCENTAGE;
                        } elseif (substr($all_cell_data_text, -3, 1) === '.') {
                            $formatting = Number_Format::FORMAT_PERCENTAGE_0;
                        } else {
                            $formatting = Number_Format::FORMAT_PERCENTAGE_00;
                        }
                        $type = Data_Type::TYPE_NUMERIC;
                        $data_value = (float) $cell_data->get_attribute_ns($office_ns, 'value');
                        break;
                    case 'currency':
                        $type = Data_Type::TYPE_NUMERIC;
                        $data_value = (float) $cell_data->get_attribute_ns($office_ns, 'value');
                        $currency = $cell_data->get_attribute_ns($office_ns, 'currency');
                        if ($left_hand_currency) {
                            $type_value = 'currency';
                            $formatting = str_contains($all_cell_data_text, '.') ? Number_Format::FORMAT_CURRENCY_USD : Number_Format::FORMAT_CURRENCY_USD_INTEGER;
                            if ($symbol !== '$') {
                                $formatting = str_replace('$', $symbol, $formatting);
                            }
                        } elseif (str_contains($all_cell_data_text, '€')) {
                            $type_value = 'currency';
                            $formatting = str_contains($all_cell_data_text, '.') ? Number_Format::FORMAT_CURRENCY_EUR : Number_Format::FORMAT_CURRENCY_EUR_INTEGER;
                        }
                        break;
                    case 'float':
                        $type = Data_Type::TYPE_NUMERIC;
                        $data_value = (float) $cell_data->get_attribute_ns($office_ns, 'value');
                        if ($data_value !== floor($data_value)) {
                            // do nothing
                        } elseif (substr($all_cell_data_text, -2, 1) === '.') {
                            $formatting = Number_Format::FORMAT_NUMBER_0;
                        } elseif (substr($all_cell_data_text, -3, 1) === '.') {
                            $formatting = Number_Format::FORMAT_NUMBER_00;
                        }
                        if (floor($data_value) == $data_value) {
                            if ($data_value == (int) $data_value) {
                                $data_value = (int) $data_value;
                            }
                        }
                        break;
                    case 'date':
                        $type = Data_Type::TYPE_NUMERIC;
                        $value = $cell_data->get_attribute_ns($office_ns, 'date-value');
                        $data_value = Date::convert_iso_date($value);
                        if (Preg::is_match('/^\d\d\d\d-\d\d-\d\d$/', $all_cell_data_text)) {
                            $formatting = 'yyyy-mm-dd';
                        } elseif (Preg::is_match('/^\d\d?-[a-zA-Z]+-\d\d\d\d$/', $all_cell_data_text)) {
                            $formatting = 'd-mmm-yyyy';
                        } elseif ($data_value != floor($data_value)) {
                            $formatting = Number_Format::FORMAT_DATE_XLSX15 . ' ' . Number_Format::FORMAT_DATE_TIME4;
                        } else {
                            $formatting = Number_Format::FORMAT_DATE_XLSX15;
                        }
                        break;
                    case 'time':
                        $type = Data_Type::TYPE_NUMERIC;
                        $time_value = $cell_data->get_attribute_ns($office_ns, 'time-value');
                        $minus = '';
                        if (str_starts_with($time_value, '-')) {
                            $minus = '-';
                            $time_value = substr($time_value, 1);
                        }
                        $time_array = sscanf($time_value, 'PT%dH%dM%dS');
                        if (is_array($time_array)) {
                            /** @var array{int, int, int} $timeArray */
                            $days = intdiv($time_array[0], 24);
                            $hours = $time_array[0] % 24;
                            $dt = new DateTime("1899-12-30 {$hours}:{$time_array[1]}:{$time_array[2]}", new DateTimeZone('UTC'));
                            $dt->modify("+{$days} days");
                            $data_value = Date::php_to_excel($dt);
                            if ($minus === '-') {
                                $data_value *= -1;
                                $formatting = '[hh]:mm:ss';
                            } else {
                                $formatting = Number_Format::FORMAT_DATE_TIME4;
                            }
                        }
                        break;
                    default:
                        $data_value = null;
                }
                if ($custom_formatting !== '') {
                    $formatting = $custom_formatting;
                }
            } else {
                $type = Data_Type::TYPE_NULL;
                $data_value = null;
            }
            if ($has_calculated_value) {
                $type = Data_Type::TYPE_FORMULA;
                $cell_data_formula = substr($cell_data_formula, strpos($cell_data_formula, ':=') + 1);
                $cell_data_formula = Formula_Translator::convert_to_excel_formula_value($cell_data_formula);
            }
            for ($i = 0; $i < $col_repeats; ++$i) {
                if ($i > 0) {
                    String_Helper::string_increment($column_id);
                }
                if (!$this->get_read_filter()->read_cell($column_id, $row_id, $worksheet_name)) {
                    continue;
                }
                if ($type !== Data_Type::TYPE_NULL) {
                    for ($row_adjust = 0; $row_adjust < $row_repeats; ++$row_adjust) {
                        $r_id = $row_id + $row_adjust;
                        $cell = $spreadsheet->get_active_sheet()->get_cell($column_id . $r_id);
                        // Set value
                        if ($has_calculated_value) {
                            $cell->set_value_explicit($cell_data_formula, $type);
                            if ($cell_data_type === 'array') {
                                $cell->set_formula_attributes(['t' => 'array', 'ref' => $cell_data_ref]);
                            }
                        } elseif ($type !== '' || $data_value !== null) {
                            $cell->set_value_explicit($data_value, $type);
                        }
                        if ($has_calculated_value) {
                            $cell->set_calculated_value($data_value, $type === Data_Type::TYPE_NUMERIC);
                        }
                        // Set other properties
                        if ($formatting !== null) {
                            $spreadsheet->get_active_sheet()->get_style($column_id . $r_id)->get_number_format()->set_format_code($formatting);
                        } else {
                            $spreadsheet->get_active_sheet()->get_style($column_id . $r_id)->get_number_format()->set_format_code(Number_Format::FORMAT_GENERAL);
                        }
                        if ($hyperlink !== null) {
                            if ($hyperlink[0] === '#') {
                                $hyperlink = 'sheet://' . substr($hyperlink, 1);
                            }
                            $cell->get_hyperlink()->set_url($hyperlink);
                        }
                    }
                }
            }
            // Merged cells
            $this->process_merged_cells($cell_data, $table_ns, $type, $column_id, $row_id, $spreadsheet);
            String_Helper::string_increment($column_id);
        }
        $row_id += $row_repeats;
    }
    private static function extract_node_name(string $key): string
    {
        // Remove ns from node name
        if (str_contains($key, ':')) {
            $key_chunks = explode(':', $key);
            $key = array_pop($key_chunks);
        }
        return $key;
    }
    /**
     * @param string[] $columnWidths
     */
    private function process_table_column_header(Dom_Element $child_node, string $table_ns, array $column_widths, int &$table_column_index, Spreadsheet $spreadsheet, bool $process_widths = true, bool $process_styles = true): void
    {
        foreach ($child_node->child_nodes as $grandchild_node) {
            /** @var DOMElement $grandchildNode */
            $grandkey = self::extract_node_name($grandchild_node->node_name);
            switch ($grandkey) {
                case 'table-column':
                    $this->process_table_column($grandchild_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $process_widths, $process_styles);
                    break;
            }
        }
    }
    /**
     * @param string[] $columnWidths
     */
    private function process_table_column_group(Dom_Element $child_node, string $table_ns, array $column_widths, int &$table_column_index, Spreadsheet $spreadsheet, bool $process_widths = true, bool $process_styles = true): void
    {
        foreach ($child_node->child_nodes as $grandchild_node) {
            /** @var DOMElement $grandchildNode */
            $grandkey = self::extract_node_name($grandchild_node->node_name);
            switch ($grandkey) {
                case 'table-column':
                    $this->process_table_column($grandchild_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $process_widths, $process_styles);
                    break;
                case 'table-header-columns':
                case 'table-columns':
                    $this->process_table_column_header($grandchild_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $process_widths, $process_styles);
                    break;
                case 'table-column-group':
                    $this->process_table_column_group($grandchild_node, $table_ns, $column_widths, $table_column_index, $spreadsheet, $process_widths, $process_styles);
                    break;
            }
        }
    }
    /**
     * @param string[] $columnWidths
     */
    private function process_table_column(Dom_Element $child_node, string $table_ns, array $column_widths, int &$table_column_index, Spreadsheet $spreadsheet, bool $process_widths = true, bool $process_styles = true): void
    {
        if ($child_node->has_attribute_ns($table_ns, 'number-columns-repeated')) {
            $row_repeats = (int) $child_node->get_attribute_ns($table_ns, 'number-columns-repeated');
        } else {
            $row_repeats = 1;
        }
        $table_style_name = $child_node->get_attribute_ns($table_ns, 'style-name');
        if ($process_widths) {
            if (isset($column_widths[$table_style_name])) {
                $column_width = new Helper_Dimension($column_widths[$table_style_name]);
                $table_column_index2 = $table_column_index;
                $table_column_string = Coordinate::string_from_column_index($table_column_index2);
                for ($row_repeats2 = $row_repeats; $row_repeats2 > 0 && $table_column_index2 <= Address_Range::MAX_COLUMN_INT; --$row_repeats2) {
                    if (!$this->read_empty_cells && $table_column_index2 > $this->highest_data_index) {
                        break;
                    }
                    $spreadsheet->get_active_sheet()->get_column_dimension($table_column_string)->set_width($column_width->to_unit('cm'), 'cm');
                    String_Helper::string_increment($table_column_string);
                    ++$table_column_index2;
                }
            }
        }
        if ($process_styles) {
            $default_style_name = $child_node->get_attribute_ns($table_ns, 'default-cell-style-name');
            if ($default_style_name !== 'Default' && isset($this->all_styles[$default_style_name])) {
                $table_column_index2 = $table_column_index;
                $table_column_string = Coordinate::string_from_column_index($table_column_index2);
                for ($row_repeats2 = $row_repeats; $row_repeats2 > 0 && $table_column_index2 <= Address_Range::MAX_COLUMN_INT; --$row_repeats2) {
                    $spreadsheet->get_active_sheet()->get_style($table_column_string)->apply_from_array($this->all_styles[$default_style_name]);
                    String_Helper::string_increment($table_column_string);
                    ++$table_column_index2;
                }
            }
        }
        $table_column_index += $row_repeats;
    }
    private function process_settings(Zip_Archive $zip, Spreadsheet $spreadsheet): void
    {
        $dom = new Dom_Document('1.01', 'UTF-8');
        $dom->load_xml($this->get_security_scanner_or_throw()->scan($zip->get_from_name('settings.xml')));
        $config_ns = (string) $dom->lookup_namespace_uri('config');
        $office_ns = (string) $dom->lookup_namespace_uri('office');
        $settings = $dom->get_elements_by_tag_name_ns($office_ns, 'settings')->item(0);
        if ($settings !== null) {
            $this->look_for_active_sheet($settings, $spreadsheet, $config_ns);
            $this->look_for_selected_cells($settings, $spreadsheet, $config_ns);
        }
    }
    private function look_for_active_sheet(Dom_Element $settings, Spreadsheet $spreadsheet, string $config_ns): void
    {
        /** @var DOMElement $t */
        foreach ($settings->get_elements_by_tag_name_ns($config_ns, 'config-item') as $t) {
            if ($t->get_attribute_ns($config_ns, 'name') === 'ActiveTable') {
                try {
                    $spreadsheet->set_active_sheet_index_by_name($t->node_value ?? '');
                } catch (Throwable) {
                    // do nothing
                }
                break;
            }
        }
    }
    private function look_for_selected_cells(Dom_Element $settings, Spreadsheet $spreadsheet, string $config_ns): void
    {
        /** @var DOMElement $t */
        foreach ($settings->get_elements_by_tag_name_ns($config_ns, 'config-item-map-named') as $t) {
            if ($t->get_attribute_ns($config_ns, 'name') === 'Tables') {
                foreach ($t->get_elements_by_tag_name_ns($config_ns, 'config-item-map-entry') as $ws) {
                    $set_row = $set_col = '';
                    $wsname = $ws->get_attribute_ns($config_ns, 'name');
                    foreach ($ws->get_elements_by_tag_name_ns($config_ns, 'config-item') as $config_item) {
                        $attr_name = $config_item->get_attribute_ns($config_ns, 'name');
                        if ($attr_name === 'CursorPositionX') {
                            $set_col = $config_item->node_value;
                        }
                        if ($attr_name === 'CursorPositionY') {
                            $set_row = $config_item->node_value;
                        }
                    }
                    $this->set_selected($spreadsheet, $wsname, "{$set_col}", "{$set_row}");
                }
                break;
            }
        }
    }
    private function set_selected(Spreadsheet $spreadsheet, string $wsname, string $set_col, string $set_row): void
    {
        if (is_numeric($set_col) && is_numeric($set_row)) {
            $sheet = $spreadsheet->get_sheet_by_name($wsname);
            if ($sheet !== null) {
                $sheet->set_selected_cells([(int) $set_col + 1, (int) $set_row + 1]);
            }
        }
    }
    /**
     * Recursively scan element.
     */
    protected function scan_element_for_text(Dom_Node $element): string
    {
        $str = '';
        foreach ($element->child_nodes as $child) {
            /** @var DOMNode $child */
            if ($child->node_type == XML_TEXT_NODE) {
                $str .= $child->node_value;
            } elseif ($child->node_type == XML_ELEMENT_NODE && $child->node_name == 'text:line-break') {
                $str .= "\n";
            } elseif ($child->node_type == XML_ELEMENT_NODE && $child->node_name == 'text:s') {
                // It's a space
                // Multiple spaces?
                $attributes = $child->attributes;
                /** @var ?DOMAttr $cAttr */
                $c_attr = $attributes === null ? null : $attributes->get_named_item('c');
                $multiplier = self::get_multiplier($c_attr);
                $str .= str_repeat(' ', $multiplier);
            }
            if ($child->has_child_nodes()) {
                $str .= $this->scan_element_for_text($child);
            }
        }
        return $str;
    }
    private static function get_multiplier(?Dom_Attr $c_attr): int
    {
        if ($c_attr) {
            return (int) $c_attr->node_value;
        }
        return 1;
    }
    private function parse_rich_text(string $is): Rich_Text
    {
        $value = new Rich_Text();
        $value->create_text($is);
        return $value;
    }
    private function process_merged_cells(Dom_Element $cell_data, string $table_ns, string $type, string $column_id, int $row_id, Spreadsheet $spreadsheet): void
    {
        if ($cell_data->has_attribute_ns($table_ns, 'number-columns-spanned') || $cell_data->has_attribute_ns($table_ns, 'number-rows-spanned')) {
            if ($type !== Data_Type::TYPE_NULL || $this->read_data_only === false) {
                $column_to = $column_id;
                if ($cell_data->has_attribute_ns($table_ns, 'number-columns-spanned')) {
                    $column_index = Coordinate::column_index_from_string($column_id);
                    $column_index += (int) $cell_data->get_attribute_ns($table_ns, 'number-columns-spanned');
                    $column_index -= 2;
                    $column_to = Coordinate::string_from_column_index($column_index + 1);
                }
                $row_to = $row_id;
                if ($cell_data->has_attribute_ns($table_ns, 'number-rows-spanned')) {
                    $row_to = $row_to + (int) $cell_data->get_attribute_ns($table_ns, 'number-rows-spanned') - 1;
                }
                $cell_range = $column_id . $row_id . ':' . $column_to . $row_to;
                $spreadsheet->get_active_sheet()->merge_cells($cell_range, Worksheet::MERGE_CELL_CONTENT_HIDE);
            }
        }
    }
    /** @var null|Closure(string, string):string */
    private ?Closure $format_callback = null;
    /** @param Closure(string, string):string $formatCallback */
    public function set_format_callback(Closure $format_callback): void
    {
        $this->format_callback = $format_callback;
    }
    /** @return array{
     *   autoColor?: true,
     *   bold?: true,
     *   color?: array{rgb: string},
     *   italic?: true,
     *   name?: non-empty-string,
     *   size?: float|int,
     *   strikethrough?: true,
     *   underline?: 'double'|'single',
     * }
     */
    protected function get_font_styles(Dom_Element $text_property, string $style_ns, string $font_ns): array
    {
        $fonts = [];
        $temp = $text_property->get_attribute_ns($style_ns, 'font-name') ?: $text_property->get_attribute_ns($font_ns, 'font-family');
        if ($temp !== '') {
            $fonts['name'] = $temp;
        }
        $temp = $text_property->get_attribute_ns($font_ns, 'font-size');
        if ($temp !== '' && str_ends_with($temp, 'pt')) {
            $fonts['size'] = (float) substr($temp, 0, -2);
        }
        $temp = $text_property->get_attribute_ns($font_ns, 'font-style');
        if ($temp === 'italic') {
            $fonts['italic'] = true;
        }
        $temp = $text_property->get_attribute_ns($font_ns, 'font-weight');
        if ($temp === 'bold') {
            $fonts['bold'] = true;
        }
        $temp = $text_property->get_attribute_ns($font_ns, 'color');
        if (Preg::is_match('/^#[a-f0-9]{6}$/i', $temp)) {
            $fonts['color'] = ['rgb' => substr($temp, 1)];
        }
        $temp = $text_property->get_attribute_ns($style_ns, 'use-window-font-color');
        if ($temp === 'true') {
            $fonts['autoColor'] = true;
        }
        $temp = $text_property->get_attribute_ns($style_ns, 'text-underline-type');
        if ($temp === '') {
            $temp = $text_property->get_attribute_ns($style_ns, 'text-underline-style');
            if ($temp !== '' && $temp !== 'none') {
                $temp = 'single';
            }
        }
        if ($temp === 'single' || $temp === 'double') {
            $fonts['underline'] = $temp;
        }
        $temp = $text_property->get_attribute_ns($style_ns, 'text-line-through-type');
        if ($temp !== '' && $temp !== 'none') {
            $fonts['strikethrough'] = true;
        }
        return $fonts;
    }
    /** @return array{
     *   fillType?: string,
     *   startColor?: array{rgb: string},
     * }
     */
    protected function get_fill_styles(Dom_Element $table_cell_properties, string $font_ns): array
    {
        $fills = [];
        $temp = $table_cell_properties->get_attribute_ns($font_ns, 'background-color');
        if (Preg::is_match('/^#[a-f0-9]{6}$/i', $temp)) {
            $fills['fillType'] = Fill::FILL_SOLID;
            $fills['startColor'] = ['rgb' => substr($temp, 1)];
        } elseif ($temp === 'transparent') {
            $fills['fillType'] = Fill::FILL_NONE;
        }
        return $fills;
    }
    private const MAP_VERTICAL = ['top' => Alignment::VERTICAL_TOP, 'middle' => Alignment::VERTICAL_CENTER, 'automatic' => Alignment::VERTICAL_JUSTIFY, 'bottom' => Alignment::VERTICAL_BOTTOM];
    private const MAP_HORIZONTAL = ['center' => Alignment::HORIZONTAL_CENTER, 'end' => Alignment::HORIZONTAL_RIGHT, 'justify' => Alignment::HORIZONTAL_FILL, 'start' => Alignment::HORIZONTAL_LEFT];
    /** @return array{
     *   shrinkToFit?: bool,
     *   textRotation?: int,
     *   vertical?: string,
     *   wrapText?: bool,
     * }
     */
    protected function get_alignment1styles(Dom_Element $table_cell_properties, string $style_ns, string $font_ns): array
    {
        $alignment1 = [];
        $temp = $table_cell_properties->get_attribute_ns($style_ns, 'rotation-angle');
        if (is_numeric($temp)) {
            $temp2 = (int) $temp;
            if ($temp2 > 90) {
                $temp2 -= 360;
            }
            if ($temp2 >= -90 && $temp2 <= 90) {
                $alignment1['textRotation'] = $temp2;
            }
        }
        $temp = $table_cell_properties->get_attribute_ns($style_ns, 'vertical-align');
        $temp2 = self::MAP_VERTICAL[$temp] ?? '';
        if ($temp2 !== '') {
            $alignment1['vertical'] = $temp2;
        }
        $temp = $table_cell_properties->get_attribute_ns($font_ns, 'wrap-option');
        if ($temp === 'wrap') {
            $alignment1['wrapText'] = true;
        } elseif ($temp === 'no-wrap') {
            $alignment1['wrapText'] = false;
        }
        $temp = $table_cell_properties->get_attribute_ns($style_ns, 'shrink-to-fit');
        if ($temp === 'true' || $temp === 'false') {
            $alignment1['shrinkToFit'] = $temp === 'true';
        }
        return $alignment1;
    }
    /** @return array{
     *   horizontal?: string,
     *   readOrder?: int,
     * }
     */
    protected function get_alignment2styles(Dom_Element $paragraph_properties, string $style_ns, string $font_ns): array
    {
        $alignment2 = [];
        $temp = $paragraph_properties->get_attribute_ns($font_ns, 'text-align');
        $temp2 = self::MAP_HORIZONTAL[$temp] ?? '';
        if ($temp2 !== '') {
            $alignment2['horizontal'] = $temp2;
        }
        $temp = $paragraph_properties->get_attribute_ns($font_ns, 'margin-left') ?: $paragraph_properties->get_attribute_ns($font_ns, 'margin-right');
        if (Preg::is_match('/^\d+([.]\d+)?(cm|in|mm|pt)$/', $temp)) {
            $dimension = new Helper_Dimension($temp);
            $alignment2['indent'] = (int) round($dimension->to_unit('px') / Alignment::INDENT_UNITS_TO_PIXELS);
        }
        $temp = $paragraph_properties->get_attribute_ns($style_ns, 'writing-mode');
        if ($temp === 'rl-tb') {
            $alignment2['readOrder'] = Alignment::READORDER_RTL;
        } elseif ($temp === 'lr-tb') {
            $alignment2['readOrder'] = Alignment::READORDER_LTR;
        }
        return $alignment2;
    }
    /** @return array{
     *   locked?: string,
     *   hidden?: string,
     * }
     */
    protected function get_protection_styles(Dom_Element $table_cell_properties, string $style_ns): array
    {
        $protection = [];
        $temp = $table_cell_properties->get_attribute_ns($style_ns, 'cell-protect');
        switch ($temp) {
            case 'protected formula-hidden':
                $protection['locked'] = Protection::PROTECTION_PROTECTED;
                $protection['hidden'] = Protection::PROTECTION_PROTECTED;
                break;
            case 'formula-hidden':
                $protection['locked'] = Protection::PROTECTION_UNPROTECTED;
                $protection['hidden'] = Protection::PROTECTION_PROTECTED;
                break;
            case 'protected':
                $protection['locked'] = Protection::PROTECTION_PROTECTED;
                $protection['hidden'] = Protection::PROTECTION_UNPROTECTED;
                break;
            case 'none':
                $protection['locked'] = Protection::PROTECTION_UNPROTECTED;
                $protection['hidden'] = Protection::PROTECTION_UNPROTECTED;
                break;
        }
        return $protection;
    }
    private const MAP_BORDER_STYLE = [
        // default BORDER_THIN
        'none' => Border::BORDER_NONE,
        'hidden' => Border::BORDER_NONE,
        'dotted' => Border::BORDER_DOTTED,
        'dash-dot' => Border::BORDER_DASHDOT,
        'dash-dot-dot' => Border::BORDER_DASHDOTDOT,
        'dashed' => Border::BORDER_DASHED,
        'double' => Border::BORDER_DOUBLE,
    ];
    private const MAP_BORDER_MEDIUM = [Border::BORDER_THIN => Border::BORDER_MEDIUM, Border::BORDER_DASHDOT => Border::BORDER_MEDIUMDASHDOT, Border::BORDER_DASHDOTDOT => Border::BORDER_MEDIUMDASHDOTDOT, Border::BORDER_DASHED => Border::BORDER_MEDIUMDASHED];
    private const MAP_BORDER_THICK = [Border::BORDER_THIN => Border::BORDER_THICK, Border::BORDER_DASHDOT => Border::BORDER_MEDIUMDASHDOT, Border::BORDER_DASHDOTDOT => Border::BORDER_MEDIUMDASHDOTDOT, Border::BORDER_DASHED => Border::BORDER_MEDIUMDASHED];
    /** @return array{
     *   bottom?: array{borderStyle:string, color:array{rgb: string}},
     *   top?: array{borderStyle:string, color:array{rgb: string}},
     *   left?: array{borderStyle:string, color:array{rgb: string}},
     *   right?: array{borderStyle:string, color:array{rgb: string}},
     *   diagonal?: array{borderStyle:string, color:array{rgb: string}},
     *   diagonalDirection?: int,
     * }
     */
    protected function get_border_styles(Dom_Element $table_cell_properties, string $font_ns, string $style_ns): array
    {
        $borders = [];
        $temp = $table_cell_properties->get_attribute_ns($font_ns, 'border');
        $diagonal_index = Borders::DIAGONAL_NONE;
        foreach (['bottom', 'left', 'right', 'top', 'diagonal-tl-br', 'diagonal-bl-tr'] as $direction) {
            if (str_starts_with($direction, 'diagonal')) {
                $direction_index = 'diagonal';
                $temp = $table_cell_properties->get_attribute_ns($style_ns, $direction);
            } else {
                $direction_index = $direction;
                $temp = $table_cell_properties->get_attribute_ns($font_ns, "border-{$direction}");
            }
            if (Preg::is_match('/^(\d+(?:[.]\d+)?)pt\s+([-\w]+)\s+#([0-9a-fA-F]{6})$/', $temp, $matches)) {
                $style = self::MAP_BORDER_STYLE[$matches[2]] ?? Border::BORDER_THIN;
                $width = (float) $matches[1];
                if ($width >= 2.5) {
                    $style = self::MAP_BORDER_THICK[$style] ?? $style;
                } elseif ($width >= 1.75) {
                    $style = self::MAP_BORDER_MEDIUM[$style] ?? $style;
                }
                $color = $matches[3];
                $borders[$direction_index] = ['borderStyle' => $style, 'color' => ['rgb' => $matches[3]]];
                if ($direction === 'diagonal-tl-br') {
                    $diagonal_index = Borders::DIAGONAL_DOWN;
                } elseif ($direction === 'diagonal-bl-tr') {
                    $diagonal_index = $diagonal_index === Borders::DIAGONAL_NONE ? Borders::DIAGONAL_UP : Borders::DIAGONAL_BOTH;
                }
            }
        }
        if ($diagonal_index !== Borders::DIAGONAL_NONE) {
            $borders['diagonalDirection'] = $diagonal_index;
        }
        return $borders;
        // @phpstan-ignore-line
    }
}