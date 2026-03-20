<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use DateTime;
use DateTimeZone;
use Php_Office\Php_Spreadsheet\Cell\Address_Helper;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Helper\Html as HelperHtml;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Reader\Xml\Page_Settings;
use Php_Office\Php_Spreadsheet\Reader\Xml\Properties;
use Php_Office\Php_Spreadsheet\Reader\Xml\Style;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Sheet_View;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
use Throwable;
/**
 * Reader for SpreadsheetML, the XML schema for Microsoft Office Excel 2003.
 */
class Xml extends Base_Reader
{
    public const NAMESPACES_SS = 'urn:schemas-microsoft-com:office:spreadsheet';
    /**
     * Formats.
     *
     * @var mixed[]
     */
    protected array $styles = [];
    /**
     * Create a new Excel2003XML Reader instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->security_scanner = Xml_Scanner::get_instance($this);
        /** @var callable */
        $unentity = self::unentity(...);
        $this->security_scanner->set_additional_callback($unentity);
    }
    public static function unentity(string $contents): string
    {
        $contents = preg_replace('/&(amp|lt|gt|quot|apos);/', "￾﻿\$1;", trim($contents)) ?? $contents;
        $contents = html_entity_decode($contents, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
        return str_replace("￾﻿", '&', $contents);
    }
    private string $file_contents = '';
    private string $xml_fail_message = '';
    /** @return mixed[] */
    public static function xml_mappings(): array
    {
        return array_merge(Style\Fill::FILL_MAPPINGS, Style\Border::BORDER_MAPPINGS);
    }
    /**
     * Can the current IReader read the file?
     */
    public function can_read(string $filename): bool
    {
        //    Office                    xmlns:o="urn:schemas-microsoft-com:office:office"
        //    Excel                    xmlns:x="urn:schemas-microsoft-com:office:excel"
        //    XML Spreadsheet            xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
        //    Spreadsheet component    xmlns:c="urn:schemas-microsoft-com:office:component:spreadsheet"
        //    XML schema                 xmlns:s="uuid:BDC6E3F0-6DA3-11d1-A2A3-00AA00C14882"
        //    XML data type            xmlns:dt="uuid:C2F41010-65B3-11d1-A29F-00AA00C14882"
        //    MS-persist recordset    xmlns:rs="urn:schemas-microsoft-com:rowset"
        //    Rowset                    xmlns:z="#RowsetSchema"
        //
        $signature = ['<?xml version="1.0"', 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet'];
        // Open file
        $data = (string) file_get_contents($filename);
        $data = $this->get_security_scanner_or_throw()->scan($data);
        // Why?
        //$data = str_replace("'", '"', $data); // fix headers with single quote
        $valid = true;
        foreach ($signature as $match) {
            // every part of the signature must be present
            if (!str_contains($data, $match)) {
                $valid = false;
                break;
            }
        }
        $this->file_contents = $data;
        return $valid;
    }
    /** @return false|SimpleXMLElement */
    private function try_simple_xml_load_string_private(string $filename, string $file_or_string = 'file'): Simple_Xml_Element|bool
    {
        $this->xml_fail_message = "Cannot load invalid XML {$file_or_string}: " . $filename;
        $xml = false;
        try {
            $data = $this->file_contents;
            $continue = true;
            if ($data === '' && $file_or_string === 'file') {
                if ($filename === '') {
                    $this->xml_fail_message = 'Cannot load empty path';
                    $continue = false;
                } else {
                    $datax = @file_get_contents($filename);
                    $data = $datax ?: '';
                    $continue = $datax !== false;
                }
            }
            if ($continue) {
                $xml = @simplexml_load_string($this->get_security_scanner_or_throw()->scan($data));
            }
        } catch (Throwable $e) {
            throw new Exception($this->xml_fail_message, 0, $e);
        }
        $this->file_contents = '';
        return $xml;
    }
    /**
     * Reads names of the worksheets from a file, without parsing the whole file to a Spreadsheet object.
     *
     * @return string[]
     */
    public function list_worksheet_names(string $filename): array
    {
        File::assert_file($filename);
        if (!$this->can_read($filename)) {
            throw new Exception($filename . ' is an Invalid Spreadsheet file.');
        }
        $worksheet_names = [];
        $xml = $this->try_simple_xml_load_string_private($filename);
        if ($xml === false) {
            throw new Exception("Problem reading {$filename}");
        }
        $xml_ss = $xml->children(self::NAMESPACES_SS);
        foreach ($xml_ss->Worksheet as $worksheet) {
            $worksheet_ss = self::get_attributes($worksheet, self::NAMESPACES_SS);
            $worksheet_names[] = (string) $worksheet_ss['Name'];
        }
        return $worksheet_names;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        File::assert_file($filename);
        if (!$this->can_read($filename)) {
            throw new Exception($filename . ' is an Invalid Spreadsheet file.');
        }
        $worksheet_info = [];
        $xml = $this->try_simple_xml_load_string_private($filename);
        if ($xml === false) {
            throw new Exception("Problem reading {$filename}");
        }
        $worksheet_id = 1;
        $xml_ss = $xml->children(self::NAMESPACES_SS);
        foreach ($xml_ss->Worksheet as $worksheet) {
            $worksheet_ss = self::get_attributes($worksheet, self::NAMESPACES_SS);
            $tmp_info = [];
            $tmp_info['worksheetName'] = '';
            $tmp_info['lastColumnLetter'] = 'A';
            $tmp_info['lastColumnIndex'] = 0;
            $tmp_info['totalRows'] = 0;
            $tmp_info['totalColumns'] = 0;
            $tmp_info['worksheetName'] = "Worksheet_{$worksheet_id}";
            if (isset($worksheet_ss['Name'])) {
                $tmp_info['worksheetName'] = (string) $worksheet_ss['Name'];
            }
            if (isset($worksheet->Table->Row)) {
                $row_index = 0;
                foreach ($worksheet->Table->Row as $row_data) {
                    $column_index = 0;
                    $row_has_data = false;
                    foreach ($row_data->Cell as $cell) {
                        if (isset($cell->Data)) {
                            $tmp_info['lastColumnIndex'] = max($tmp_info['lastColumnIndex'], $column_index);
                            $row_has_data = true;
                        }
                        ++$column_index;
                    }
                    ++$row_index;
                    if ($row_has_data) {
                        $tmp_info['totalRows'] = max($tmp_info['totalRows'], $row_index);
                    }
                }
            }
            $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['lastColumnIndex'] + 1, true);
            $tmp_info['totalColumns'] = $tmp_info['lastColumnIndex'] + 1;
            $tmp_info['sheetState'] = Worksheet::SHEETSTATE_VISIBLE;
            $worksheet_info[] = $tmp_info;
            ++$worksheet_id;
        }
        return $worksheet_info;
    }
    /**
     * Loads Spreadsheet from string.
     */
    public function load_spreadsheet_from_string(string $contents): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        $spreadsheet->remove_sheet_by_index(0);
        // Load into this instance
        return $this->load_into_existing($contents, $spreadsheet, true);
    }
    /**
     * Loads Spreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        $spreadsheet->remove_sheet_by_index(0);
        // Load into this instance
        return $this->load_into_existing($filename, $spreadsheet);
    }
    /**
     * Loads from file or contents into Spreadsheet instance.
     *
     * @param string $filename file name if useContents is false else file contents
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet, bool $use_contents = false): Spreadsheet
    {
        if ($use_contents) {
            $this->file_contents = $filename;
            $file_or_string = 'string';
        } else {
            File::assert_file($filename);
            if (!$this->can_read($filename)) {
                throw new Exception($filename . ' is an Invalid Spreadsheet file.');
            }
            $file_or_string = 'file';
        }
        $xml = $this->try_simple_xml_load_string_private($filename, $file_or_string);
        if ($xml === false) {
            throw new Exception($this->xml_fail_message);
        }
        $namespaces = $xml->get_namespaces(true);
        (new Properties($spreadsheet))->read_properties($xml, $namespaces);
        $this->styles = (new Style())->parse_styles($xml, $namespaces);
        if (isset($this->styles['Default']) && is_array($this->styles['Default'])) {
            $spreadsheet->get_cell_xf_collection()[0]->apply_from_array($this->styles['Default']);
        }
        $worksheet_id = 0;
        $xml_ss = $xml->children(self::NAMESPACES_SS);
        $sheet_created = false;
        /** @var null|SimpleXMLElement $worksheetx */
        foreach ($xml_ss->Worksheet as $worksheetx) {
            $worksheet = $worksheetx ?? new Simple_Xml_Element('<xml></xml>');
            $worksheet_ss = self::get_attributes($worksheet, self::NAMESPACES_SS);
            if (isset($this->load_sheets_only, $worksheet_ss['Name']) && !in_array($worksheet_ss['Name'], $this->load_sheets_only)) {
                continue;
            }
            // Create new Worksheet
            $spreadsheet->create_sheet();
            $sheet_created = true;
            $spreadsheet->set_active_sheet_index($worksheet_id);
            $worksheet_name = '';
            if (isset($worksheet_ss['Name'])) {
                $worksheet_name = (string) $worksheet_ss['Name'];
                //    Use false for $updateFormulaCellReferences to prevent adjustment of worksheet references in
                //        formula cells... during the load, all formulae should be correct, and we're simply bringing
                //        the worksheet name in line with the formula, not the reverse
                $spreadsheet->get_active_sheet()->set_title($worksheet_name, false, false);
            }
            if (isset($worksheet_ss['Protected'])) {
                $protection = (string) $worksheet_ss['Protected'] === '1';
                $spreadsheet->get_active_sheet()->get_protection()->set_sheet($protection);
            }
            // locally scoped defined names
            if (isset($worksheet->Names[0])) {
                foreach ($worksheet->Names[0] as $defined_name) {
                    $defined_name_ss = self::get_attributes($defined_name, self::NAMESPACES_SS);
                    $name = (string) $defined_name_ss['Name'];
                    $defined_value = (string) $defined_name_ss['RefersTo'];
                    $converted_value = Address_Helper::convert_formula_to_a1($defined_value);
                    if ($converted_value[0] === '=') {
                        $converted_value = substr($converted_value, 1);
                    }
                    $spreadsheet->add_defined_name(Defined_Name::create_instance($name, $spreadsheet->get_active_sheet(), $converted_value, true));
                }
            }
            $column_id = 'A';
            if (isset($worksheet->Table->Column)) {
                foreach ($worksheet->Table->Column as $column_data) {
                    $column_data_ss = self::get_attributes($column_data, self::NAMESPACES_SS);
                    $colspan = 0;
                    if (isset($column_data_ss['Span'])) {
                        $span_attr = (string) $column_data_ss['Span'];
                        if (is_numeric($span_attr)) {
                            $colspan = max(0, (int) $span_attr);
                        }
                    }
                    if (isset($column_data_ss['Index'])) {
                        $column_id = Coordinate::string_from_column_index((int) $column_data_ss['Index']);
                    }
                    $column_width = null;
                    if (isset($column_data_ss['Width'])) {
                        $column_width = $column_data_ss['Width'];
                    }
                    $column_visible = null;
                    if (isset($column_data_ss['Hidden'])) {
                        $column_visible = (string) $column_data_ss['Hidden'] !== '1';
                    }
                    while ($colspan >= 0) {
                        /** @var string $columnID */
                        if (isset($column_width)) {
                            $spreadsheet->get_active_sheet()->get_column_dimension($column_id)->set_width($column_width / 5.4);
                        }
                        if (isset($column_visible)) {
                            $spreadsheet->get_active_sheet()->get_column_dimension($column_id)->set_visible($column_visible);
                        }
                        String_Helper::string_increment($column_id);
                        --$colspan;
                    }
                }
            }
            $row_id = 1;
            if (isset($worksheet->Table->Row)) {
                $additional_merged_cells = 0;
                foreach ($worksheet->Table->Row as $row_data) {
                    $row_has_data = false;
                    $row_ss = self::get_attributes($row_data, self::NAMESPACES_SS);
                    if (isset($row_ss['Index'])) {
                        $row_id = (int) $row_ss['Index'];
                    }
                    if (isset($row_ss['Hidden'])) {
                        $row_visible = (string) $row_ss['Hidden'] !== '1';
                        $spreadsheet->get_active_sheet()->get_row_dimension($row_id)->set_visible($row_visible);
                    }
                    $column_id = 'A';
                    foreach ($row_data->Cell as $cell) {
                        $array_ref = '';
                        $cell_ss = self::get_attributes($cell, self::NAMESPACES_SS);
                        if (isset($cell_ss['Index'])) {
                            $column_id = Coordinate::string_from_column_index((int) $cell_ss['Index']);
                        }
                        $cell_range = $column_id . $row_id;
                        if (isset($cell_ss['ArrayRange'])) {
                            $array_range = (string) $cell_ss['ArrayRange'];
                            $array_ref = Address_Helper::convert_formula_to_a1($array_range, $row_id, Coordinate::column_index_from_string($column_id));
                        }
                        if (!$this->get_read_filter()->read_cell($column_id, $row_id, $worksheet_name)) {
                            String_Helper::string_increment($column_id);
                            continue;
                        }
                        if (isset($cell_ss['HRef'])) {
                            $spreadsheet->get_active_sheet()->get_cell($cell_range)->get_hyperlink()->set_url((string) $cell_ss['HRef']);
                        }
                        if (isset($cell_ss['MergeAcross']) || isset($cell_ss['MergeDown'])) {
                            $column_to = $column_id;
                            if (isset($cell_ss['MergeAcross'])) {
                                $additional_merged_cells += (int) $cell_ss['MergeAcross'];
                                $column_to = Coordinate::string_from_column_index((int) (Coordinate::column_index_from_string($column_id) + $cell_ss['MergeAcross']));
                            }
                            $row_to = $row_id;
                            if (isset($cell_ss['MergeDown'])) {
                                $row_to = $row_to + $cell_ss['MergeDown'];
                            }
                            $cell_range .= ':' . $column_to . $row_to;
                            $spreadsheet->get_active_sheet()->merge_cells($cell_range, Worksheet::MERGE_CELL_CONTENT_HIDE);
                        }
                        $has_calculated_value = false;
                        $cell_data_formula = '';
                        if (isset($cell_ss['Formula'])) {
                            $cell_data_formula = $cell_ss['Formula'];
                            $has_calculated_value = true;
                            if ($array_ref !== '') {
                                $spreadsheet->get_active_sheet()->get_cell($column_id . $row_id)->set_formula_attributes(['t' => 'array', 'ref' => $array_ref]);
                            }
                        }
                        if (isset($cell->Data)) {
                            $cell_data = $cell->Data;
                            $cell_value = (string) $cell_data;
                            $type = Data_Type::TYPE_NULL;
                            $cell_data_ss = self::get_attributes($cell_data, self::NAMESPACES_SS);
                            if (isset($cell_data_ss['Type'])) {
                                $cell_data_type = $cell_data_ss['Type'];
                                switch ($cell_data_type) {
                                    /*
                                    const TYPE_STRING        = 's';
                                    const TYPE_FORMULA        = 'f';
                                    const TYPE_NUMERIC        = 'n';
                                    const TYPE_BOOL            = 'b';
                                    const TYPE_NULL            = 'null';
                                    const TYPE_INLINE        = 'inlineStr';
                                    const TYPE_ERROR        = 'e';
                                    */
                                    case 'String':
                                        $type = Data_Type::TYPE_STRING;
                                        $rich = $cell_data->children('http://www.w3.org/TR/REC-html40');
                                        if ($rich) {
                                            // in case of HTML content we extract the payload
                                            // and convert it into a rich text object
                                            $content = $cell_data->as_xml() ?: '';
                                            $html = new Helper_Html();
                                            $cell_value = $html->to_rich_text_object($content, true);
                                        }
                                        break;
                                    case 'Number':
                                        $type = Data_Type::TYPE_NUMERIC;
                                        $cell_value = (float) $cell_value;
                                        if (floor($cell_value) == $cell_value) {
                                            $cell_value = (int) $cell_value;
                                        }
                                        break;
                                    case 'Boolean':
                                        $type = Data_Type::TYPE_BOOL;
                                        $cell_value = $cell_value != 0;
                                        break;
                                    case 'DateTime':
                                        $type = Data_Type::TYPE_NUMERIC;
                                        $date_time = new DateTime($cell_value, new DateTimeZone('UTC'));
                                        $cell_value = Date::php_to_excel($date_time);
                                        break;
                                    case 'Error':
                                        $type = Data_Type::TYPE_ERROR;
                                        $has_calculated_value = false;
                                        break;
                                }
                            }
                            $original_type = $type;
                            if ($has_calculated_value) {
                                $type = Data_Type::TYPE_FORMULA;
                                $column_number = Coordinate::column_index_from_string($column_id);
                                $cell_data_formula = Address_Helper::convert_formula_to_a1($cell_data_formula, $row_id, $column_number);
                            }
                            $hyperlink = null;
                            if ($spreadsheet->get_active_sheet()->hyperlink_exists($column_id . $row_id)) {
                                $hyperlink = $spreadsheet->get_active_sheet()->get_hyperlink($column_id . $row_id);
                            }
                            $spreadsheet->get_active_sheet()->get_cell($column_id . $row_id)->set_value_explicit($has_calculated_value ? $cell_data_formula : $cell_value, $type);
                            $spreadsheet->get_active_sheet()->set_hyperlink($column_id . $row_id, $hyperlink);
                            if ($has_calculated_value) {
                                $spreadsheet->get_active_sheet()->get_cell($column_id . $row_id)->set_calculated_value($cell_value, $original_type === Data_Type::TYPE_NUMERIC);
                            }
                            $row_has_data = true;
                        }
                        if (isset($cell->Comment)) {
                            $this->parse_cell_comment($cell->Comment, $spreadsheet, $column_id, $row_id);
                        }
                        if (isset($cell_ss['StyleID'])) {
                            $style = (string) $cell_ss['StyleID'];
                            if (isset($this->styles[$style]) && is_array($this->styles[$style]) && !empty($this->styles[$style])) {
                                $spreadsheet->get_active_sheet()->get_style($cell_range)->apply_from_array($this->styles[$style]);
                            }
                        }
                        String_Helper::string_increment($column_id);
                        while ($additional_merged_cells > 0) {
                            String_Helper::string_increment($column_id);
                            --$additional_merged_cells;
                        }
                    }
                    if ($row_has_data) {
                        if (isset($row_ss['Height'])) {
                            $row_height = $row_ss['Height'];
                            $spreadsheet->get_active_sheet()->get_row_dimension($row_id)->set_row_height((float) $row_height);
                        }
                    }
                    ++$row_id;
                }
            }
            $data_validations = new Xml\Data_Validations();
            $data_validations->load_data_validations($worksheet, $spreadsheet);
            $xml_x = $worksheet->children(Namespaces::URN_EXCEL);
            if (isset($xml_x->worksheet_options)) {
                if (isset($xml_x->worksheet_options->show_page_break_zoom)) {
                    $spreadsheet->get_active_sheet()->get_sheet_view()->set_view(Sheet_View::SHEETVIEW_PAGE_BREAK_PREVIEW);
                }
                if (isset($xml_x->worksheet_options->Zoom)) {
                    $zoom_scale_normal = (int) $xml_x->worksheet_options->Zoom;
                    if ($zoom_scale_normal > 0) {
                        $spreadsheet->get_active_sheet()->get_sheet_view()->set_zoom_scale_normal($zoom_scale_normal);
                        $spreadsheet->get_active_sheet()->get_sheet_view()->set_zoom_scale($zoom_scale_normal);
                    }
                }
                if (isset($xml_x->worksheet_options->page_break_zoom)) {
                    $zoom_scale_normal = (int) $xml_x->worksheet_options->page_break_zoom;
                    if ($zoom_scale_normal > 0) {
                        $spreadsheet->get_active_sheet()->get_sheet_view()->set_zoom_scale_sheet_layout_view($zoom_scale_normal);
                    }
                }
                if (isset($xml_x->worksheet_options->show_page_break_zoom)) {
                    $spreadsheet->get_active_sheet()->get_sheet_view()->set_view(Sheet_View::SHEETVIEW_PAGE_BREAK_PREVIEW);
                }
                if (isset($xml_x->worksheet_options->freeze_panes)) {
                    $freeze_row = $freeze_column = 1;
                    if (isset($xml_x->worksheet_options->split_horizontal)) {
                        $freeze_row = (int) $xml_x->worksheet_options->split_horizontal + 1;
                    }
                    if (isset($xml_x->worksheet_options->split_vertical)) {
                        $freeze_column = (int) $xml_x->worksheet_options->split_vertical + 1;
                    }
                    $left_top_row = (string) $xml_x->worksheet_options->top_row_bottom_pane;
                    $left_top_column = (string) $xml_x->worksheet_options->left_column_right_pane;
                    if (is_numeric($left_top_row) && is_numeric($left_top_column)) {
                        $left_top_coordinate = Coordinate::string_from_column_index((int) $left_top_column + 1) . ($left_top_row + 1);
                        $spreadsheet->get_active_sheet()->freeze_pane(Coordinate::string_from_column_index($freeze_column) . $freeze_row, $left_top_coordinate, !isset($xml_x->worksheet_options->frozen_no_split));
                    } else {
                        $spreadsheet->get_active_sheet()->freeze_pane(Coordinate::string_from_column_index($freeze_column) . $freeze_row, null, !isset($xml_x->worksheet_options->frozen_no_split));
                    }
                } elseif (isset($xml_x->worksheet_options->split_vertical) || isset($xml_x->worksheet_options->split_horizontal)) {
                    if (isset($xml_x->worksheet_options->split_horizontal)) {
                        $y_split = (int) $xml_x->worksheet_options->split_horizontal;
                        $spreadsheet->get_active_sheet()->set_y_split($y_split);
                    }
                    if (isset($xml_x->worksheet_options->split_vertical)) {
                        $x_split = (int) $xml_x->worksheet_options->split_vertical;
                        $spreadsheet->get_active_sheet()->set_x_split($x_split);
                    }
                    if (isset($xml_x->worksheet_options->left_column_visible) || isset($xml_x->worksheet_options->top_row_visible)) {
                        $left_top_column = $left_top_row = 1;
                        if (isset($xml_x->worksheet_options->left_column_visible)) {
                            $left_top_column = 1 + (int) $xml_x->worksheet_options->left_column_visible;
                        }
                        if (isset($xml_x->worksheet_options->top_row_visible)) {
                            $left_top_row = 1 + (int) $xml_x->worksheet_options->top_row_visible;
                        }
                        $left_top_coordinate = Coordinate::string_from_column_index($left_top_column) . "{$left_top_row}";
                        $spreadsheet->get_active_sheet()->set_top_left_cell($left_top_coordinate);
                    }
                    $left_top_column = $left_top_row = 1;
                    if (isset($xml_x->worksheet_options->left_column_right_pane)) {
                        $left_top_column = 1 + (int) $xml_x->worksheet_options->left_column_right_pane;
                    }
                    if (isset($xml_x->worksheet_options->top_row_bottom_pane)) {
                        $left_top_row = 1 + (int) $xml_x->worksheet_options->top_row_bottom_pane;
                    }
                    $left_top_coordinate = Coordinate::string_from_column_index($left_top_column) . "{$left_top_row}";
                    $spreadsheet->get_active_sheet()->set_pane_top_left_cell($left_top_coordinate);
                }
                (new Page_Settings($xml_x))->load_page_settings($spreadsheet);
                if (isset($xml_x->worksheet_options->top_row_visible, $xml_x->worksheet_options->left_column_visible)) {
                    $left_top_row = (string) $xml_x->worksheet_options->top_row_visible;
                    $left_top_column = (string) $xml_x->worksheet_options->left_column_visible;
                    if (is_numeric($left_top_row) && is_numeric($left_top_column)) {
                        $left_top_coordinate = Coordinate::string_from_column_index((int) $left_top_column + 1) . ($left_top_row + 1);
                        $spreadsheet->get_active_sheet()->set_top_left_cell($left_top_coordinate);
                    }
                }
                $range_calculated = false;
                if (isset($xml_x->worksheet_options->Panes->Pane->range_selection)) {
                    if (1 === preg_match('/^R(\d+)C(\d+):R(\d+)C(\d+)$/', (string) $xml_x->worksheet_options->Panes->Pane->range_selection, $selection_matches)) {
                        $selected_cell = Coordinate::string_from_column_index((int) $selection_matches[2]) . $selection_matches[1] . ':' . Coordinate::string_from_column_index((int) $selection_matches[4]) . $selection_matches[3];
                        $spreadsheet->get_active_sheet()->set_selected_cells($selected_cell);
                        $range_calculated = true;
                    }
                }
                if (!$range_calculated) {
                    if (isset($xml_x->worksheet_options->Panes->Pane->active_row)) {
                        $active_row = (string) $xml_x->worksheet_options->Panes->Pane->active_row;
                    } else {
                        $active_row = 0;
                    }
                    if (isset($xml_x->worksheet_options->Panes->Pane->active_col)) {
                        $active_column = (string) $xml_x->worksheet_options->Panes->Pane->active_col;
                    } else {
                        $active_column = 0;
                    }
                    if (is_numeric($active_row) && is_numeric($active_column)) {
                        $selected_cell = Coordinate::string_from_column_index((int) $active_column + 1) . ($active_row + 1);
                        $spreadsheet->get_active_sheet()->set_selected_cells($selected_cell);
                    }
                }
            }
            if (isset($xml_x->page_breaks)) {
                if (isset($xml_x->page_breaks->col_breaks)) {
                    foreach ($xml_x->page_breaks->col_breaks->col_break as $col_break) {
                        $col_break = (string) $col_break->Column;
                        $spreadsheet->get_active_sheet()->set_break([1 + (int) $col_break, 1], Worksheet::BREAK_COLUMN);
                    }
                }
                if (isset($xml_x->page_breaks->row_breaks)) {
                    foreach ($xml_x->page_breaks->row_breaks->row_break as $row_break) {
                        $row_break = (string) $row_break->Row;
                        $spreadsheet->get_active_sheet()->set_break([1, (int) $row_break], Worksheet::BREAK_ROW);
                    }
                }
            }
            ++$worksheet_id;
        }
        if ($this->create_blank_sheet_if_none_read && !$sheet_created) {
            $spreadsheet->create_sheet();
        }
        // Globally scoped defined names
        $active_sheet_index = 0;
        if (isset($xml->excel_workbook->active_sheet)) {
            $active_sheet_index = (int) (string) $xml->excel_workbook->active_sheet;
        }
        $active_worksheet = $spreadsheet->set_active_sheet_index($active_sheet_index);
        if (isset($xml->Names[0])) {
            foreach ($xml->Names[0] as $defined_name) {
                $defined_name_ss = self::get_attributes($defined_name, self::NAMESPACES_SS);
                $name = (string) $defined_name_ss['Name'];
                $defined_value = (string) $defined_name_ss['RefersTo'];
                $converted_value = Address_Helper::convert_formula_to_a1($defined_value);
                if ($converted_value[0] === '=') {
                    $converted_value = substr($converted_value, 1);
                }
                $spreadsheet->add_defined_name(Defined_Name::create_instance($name, $active_worksheet, $converted_value));
            }
        }
        // Return
        return $spreadsheet;
    }
    protected function parse_cell_comment(Simple_Xml_Element $comment, Spreadsheet $spreadsheet, string $column_id, int $row_id): void
    {
        $comment_attributes = $comment->attributes(self::NAMESPACES_SS);
        $author = 'unknown';
        if (isset($comment_attributes->Author)) {
            $author = (string) $comment_attributes->Author;
        }
        $node = $comment->Data->as_xml();
        $annotation = strip_tags((string) $node);
        $spreadsheet->get_active_sheet()->get_comment($column_id . $row_id)->set_author($author)->set_text($this->parse_rich_text($annotation));
    }
    protected function parse_rich_text(string $annotation): Rich_Text
    {
        $value = new Rich_Text();
        $value->create_text($annotation);
        return $value;
    }
    private static function get_attributes(?Simple_Xml_Element $simple, string $node): Simple_Xml_Element
    {
        return $simple === null ? new Simple_Xml_Element('<xml></xml>') : $simple->attributes($node) ?? new Simple_Xml_Element('<xml></xml>');
    }
}