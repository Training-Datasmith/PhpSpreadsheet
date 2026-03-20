<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Reader\Gnumeric\Page_Setup;
use Php_Office\Php_Spreadsheet\Reader\Gnumeric\Properties;
use Php_Office\Php_Spreadsheet\Reader\Gnumeric\Styles;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Reference_Helper;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
use Xml_Reader;
class Gnumeric extends Base_Reader
{
    public const NAMESPACE_GNM = 'http://www.gnumeric.org/v10.dtd';
    // gmr in old sheets
    public const NAMESPACE_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    public const NAMESPACE_OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    public const NAMESPACE_XLINK = 'http://www.w3.org/1999/xlink';
    public const NAMESPACE_DC = 'http://purl.org/dc/elements/1.1/';
    public const NAMESPACE_META = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    public const NAMESPACE_OOO = 'http://openoffice.org/2004/office';
    public const GNM_SHEET_VISIBILITY_VISIBLE = 'GNM_SHEET_VISIBILITY_VISIBLE';
    public const GNM_SHEET_VISIBILITY_HIDDEN = 'GNM_SHEET_VISIBILITY_HIDDEN';
    /**
     * Shared Expressions.
     *
     * @var array<array{column: int, row: int, formula:string}>
     */
    private array $expressions = [];
    /**
     * Spreadsheet shared across all functions.
     */
    private Spreadsheet $spreadsheet;
    private readonly Reference_Helper $reference_helper;
    /** @var array{'dataType': string[]} */
    public static array $mappings = ['dataType' => [
        '10' => Data_Type::TYPE_NULL,
        '20' => Data_Type::TYPE_BOOL,
        '30' => Data_Type::TYPE_NUMERIC,
        // Integer doesn't exist in Excel
        '40' => Data_Type::TYPE_NUMERIC,
        // Float
        '50' => Data_Type::TYPE_ERROR,
        '60' => Data_Type::TYPE_STRING,
    ]];
    /**
     * Create a new Gnumeric.
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
        $data = null;
        if (File::test_file_no_throw($filename)) {
            $data = $this->gzfile_get_contents($filename);
            if (!str_contains($data, self::NAMESPACE_GNM)) {
                $data = '';
            }
        }
        return !empty($data);
    }
    private static function match_xml(Xml_Reader $xml, string $expected_local_name): bool
    {
        return $xml->namespace_uri === self::NAMESPACE_GNM && $xml->local_name === $expected_local_name && $xml->node_type === Xml_Reader::ELEMENT;
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
            throw new Exception($filename . ' is an invalid Gnumeric file.');
        }
        $xml = new Xml_Reader();
        $contents = $this->get_security_scanner_or_throw()->scan($this->gzfile_get_contents($filename));
        $xml->xml($contents, null, LIBXML_NONET);
        $xml->set_parser_property(Xml_Reader::VALIDATE, false);
        $worksheet_names = [];
        while ($xml->read()) {
            if (self::match_xml($xml, 'SheetName')) {
                $xml->read();
                //    Move onto the value node
                $worksheet_names[] = (string) $xml->value;
            } elseif (self::match_xml($xml, 'Sheets')) {
                //    break out of the loop once we've got our sheet names rather than parse the entire file
                break;
            }
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
            throw new Exception($filename . ' is an invalid Gnumeric file.');
        }
        $xml = new Xml_Reader();
        $contents = $this->get_security_scanner_or_throw()->scan($this->gzfile_get_contents($filename));
        $xml->xml($contents, null, LIBXML_NONET);
        $xml->set_parser_property(Xml_Reader::VALIDATE, false);
        $worksheet_info = [];
        while ($xml->read()) {
            if (self::match_xml($xml, 'Sheet')) {
                $tmp_info = ['worksheetName' => '', 'lastColumnLetter' => 'A', 'lastColumnIndex' => 0, 'totalRows' => 0, 'totalColumns' => 0, 'sheetState' => Worksheet::SHEETSTATE_VISIBLE];
                $visibility = $xml->get_attribute('Visibility');
                if ((string) $visibility === self::GNM_SHEET_VISIBILITY_HIDDEN) {
                    $tmp_info['sheetState'] = Worksheet::SHEETSTATE_HIDDEN;
                }
                while ($xml->read()) {
                    if (self::match_xml($xml, 'Name')) {
                        $xml->read();
                        //    Move onto the value node
                        $tmp_info['worksheetName'] = (string) $xml->value;
                    } elseif (self::match_xml($xml, 'MaxCol')) {
                        $xml->read();
                        //    Move onto the value node
                        $tmp_info['lastColumnIndex'] = (int) $xml->value;
                        $tmp_info['totalColumns'] = (int) $xml->value + 1;
                    } elseif (self::match_xml($xml, 'MaxRow')) {
                        $xml->read();
                        //    Move onto the value node
                        $tmp_info['totalRows'] = (int) $xml->value + 1;
                        break;
                    }
                }
                $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['lastColumnIndex'] + 1, true);
                $worksheet_info[] = $tmp_info;
            }
        }
        return $worksheet_info;
    }
    private function gzfile_get_contents(string $filename): string
    {
        $data = '';
        $contents = @file_get_contents($filename);
        if ($contents !== false) {
            if (str_starts_with($contents, "\x1f\x8b")) {
                // Check if gzlib functions are available
                if (function_exists('gzdecode')) {
                    $contents = @gzdecode($contents);
                    if ($contents !== false) {
                        $data = $contents;
                    }
                }
            } else {
                $data = $contents;
            }
        }
        if ($data !== '') {
            return $this->get_security_scanner_or_throw()->scan($data);
        }
        return $data;
    }
    /** @return mixed[] */
    public static function gnumeric_mappings(): array
    {
        return array_merge(self::$mappings, Styles::$mappings);
    }
    private function process_comments(Simple_Xml_Element $sheet): void
    {
        if (!$this->read_data_only && isset($sheet->Objects)) {
            foreach ($sheet->Objects->children(self::NAMESPACE_GNM) as $comment) {
                $comment_attributes = $comment->attributes();
                //    Only comment objects are handled at the moment
                if ($comment_attributes && $comment_attributes->Text) {
                    $this->spreadsheet->get_active_sheet()->get_comment((string) $comment_attributes->object_bound)->set_author((string) $comment_attributes->Author)->set_text($this->parse_rich_text((string) $comment_attributes->Text));
                }
            }
        }
    }
    private static function test_simple_xml(mixed $value): Simple_Xml_Element
    {
        return $value instanceof Simple_Xml_Element ? $value : new Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8"?><root></root>');
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
     * Loads from file into Spreadsheet instance.
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet): Spreadsheet
    {
        $this->spreadsheet = $spreadsheet;
        File::assert_file($filename);
        if (!$this->can_read($filename)) {
            throw new Exception($filename . ' is an invalid Gnumeric file.');
        }
        $g_file_data = $this->gzfile_get_contents($filename);
        /** @var XmlScanner */
        $security_scanner = $this->security_scanner;
        $xml2 = simplexml_load_string($security_scanner->scan($g_file_data));
        $xml = self::test_simple_xml($xml2);
        $gnm_xml = $xml->children(self::NAMESPACE_GNM);
        (new Properties($this->spreadsheet))->read_properties($xml, $gnm_xml);
        $worksheet_id = 0;
        $sheet_created = false;
        foreach ($gnm_xml->Sheets->Sheet as $sheet_or_null) {
            $sheet = self::test_simple_xml($sheet_or_null);
            $worksheet_name = (string) $sheet->Name;
            if (is_array($this->load_sheets_only) && !in_array($worksheet_name, $this->load_sheets_only, true)) {
                continue;
            }
            $max_row = $max_col = 0;
            // Create new Worksheet
            $this->spreadsheet->create_sheet();
            $sheet_created = true;
            $this->spreadsheet->set_active_sheet_index($worksheet_id);
            //    Use false for $updateFormulaCellReferences to prevent adjustment of worksheet references in formula
            //        cells... during the load, all formulae should be correct, and we're simply bringing the worksheet
            //        name in line with the formula, not the reverse
            $this->spreadsheet->get_active_sheet()->set_title($worksheet_name, false, false);
            $visibility = $sheet->attributes()['Visibility'] ?? self::GNM_SHEET_VISIBILITY_VISIBLE;
            if ((string) $visibility !== self::GNM_SHEET_VISIBILITY_VISIBLE) {
                $this->spreadsheet->get_active_sheet()->set_sheet_state(Worksheet::SHEETSTATE_HIDDEN);
            }
            if (!$this->read_data_only) {
                (new Page_Setup($this->spreadsheet))->print_information($sheet)->sheet_margins($sheet);
            }
            foreach ($sheet->Cells->Cell as $cell_or_null) {
                $cell = self::test_simple_xml($cell_or_null);
                $cell_attributes = self::test_simple_xml($cell->attributes());
                $row = (int) $cell_attributes->Row + 1;
                $column = (int) $cell_attributes->Col;
                $max_row = max($max_row, $row);
                $max_col = max($max_col, $column);
                $column = Coordinate::string_from_column_index($column + 1);
                // Read cell?
                if (!$this->get_read_filter()->read_cell($column, $row, $worksheet_name)) {
                    continue;
                }
                $this->load_cell($cell, $worksheet_name, $cell_attributes, $column, $row);
            }
            if ($sheet->Styles !== null) {
                (new Styles($this->spreadsheet, $this->read_data_only))->read($sheet, $max_row, $max_col);
            }
            $this->process_comments($sheet);
            $this->process_column_widths($sheet, $max_col);
            $this->process_row_heights($sheet, $max_row);
            $this->process_merged_cells($sheet);
            $this->process_autofilter($sheet);
            $this->set_selected_cells($sheet);
            ++$worksheet_id;
        }
        if ($this->create_blank_sheet_if_none_read && !$sheet_created) {
            $this->spreadsheet->create_sheet();
        }
        $this->process_defined_names($gnm_xml);
        $this->set_selected_sheet($gnm_xml);
        // Return
        return $this->spreadsheet;
    }
    private function set_selected_sheet(Simple_Xml_Element $gnm_xml): void
    {
        if (isset($gnm_xml->ui_data)) {
            $attributes = self::test_simple_xml($gnm_xml->ui_data->attributes());
            $selected_sheet = (int) $attributes['SelectedTab'];
            $this->spreadsheet->set_active_sheet_index($selected_sheet);
        }
    }
    private function set_selected_cells(?Simple_Xml_Element $sheet): void
    {
        if ($sheet !== null && isset($sheet->Selections)) {
            foreach ($sheet->Selections as $selection) {
                $start_col = (int) ($selection->start_col ?? 0);
                $start_row = (int) ($selection->start_row ?? 0) + 1;
                $end_col = (int) ($selection->end_col ?? $start_col);
                $end_row = (int) ($selection->end_row ?? 0) + 1;
                $start_column = Coordinate::string_from_column_index($start_col + 1);
                $end_column = Coordinate::string_from_column_index($end_col + 1);
                $start_cell = "{$start_column}{$start_row}";
                $end_cell = "{$end_column}{$end_row}";
                $selected_range = $start_cell . ($end_cell !== $start_cell ? ':' . $end_cell : '');
                $this->spreadsheet->get_active_sheet()->set_selected_cell($selected_range);
                break;
            }
        }
    }
    private function process_merged_cells(?Simple_Xml_Element $sheet): void
    {
        //    Handle Merged Cells in this worksheet
        if ($sheet !== null && isset($sheet->merged_regions)) {
            foreach ($sheet->merged_regions->Merge as $merge_cells) {
                if (str_contains((string) $merge_cells, ':')) {
                    $this->spreadsheet->get_active_sheet()->merge_cells($merge_cells, Worksheet::MERGE_CELL_CONTENT_HIDE);
                }
            }
        }
    }
    private function process_autofilter(?Simple_Xml_Element $sheet): void
    {
        if ($sheet !== null && isset($sheet->Filters)) {
            foreach ($sheet->Filters->Filter as $autofilter) {
                $attributes = $autofilter->attributes();
                if (isset($attributes['Area'])) {
                    $this->spreadsheet->get_active_sheet()->set_auto_filter((string) $attributes['Area']);
                }
            }
        }
    }
    private function set_column_width(int $which_column, float $default_width): void
    {
        $this->spreadsheet->get_active_sheet()->get_column_dimension(Coordinate::string_from_column_index($which_column + 1))->set_width($default_width);
    }
    private function set_column_invisible(int $which_column): void
    {
        $this->spreadsheet->get_active_sheet()->get_column_dimension(Coordinate::string_from_column_index($which_column + 1))->set_visible(false);
    }
    private function process_column_loop(int $which_column, int $max_col, ?Simple_Xml_Element $column_override, float $default_width): int
    {
        $column_override = self::test_simple_xml($column_override);
        $column_attributes = self::test_simple_xml($column_override->attributes());
        $column = $column_attributes['No'];
        $column_width = (float) $column_attributes['Unit'] / 5.4;
        $hidden = isset($column_attributes['Hidden']) && (string) $column_attributes['Hidden'] == '1';
        $column_count = (int) ($column_attributes['Count'] ?? 1);
        while ($which_column < $column) {
            $this->set_column_width($which_column, $default_width);
            ++$which_column;
        }
        while ($which_column < $column + $column_count && $which_column <= $max_col) {
            $this->set_column_width($which_column, $column_width);
            if ($hidden) {
                $this->set_column_invisible($which_column);
            }
            ++$which_column;
        }
        return $which_column;
    }
    private function process_column_widths(?Simple_Xml_Element $sheet, int $max_col): void
    {
        if (!$this->read_data_only && $sheet !== null && isset($sheet->Cols)) {
            //    Column Widths
            $default_width = 0;
            $column_attributes = $sheet->Cols->attributes();
            if ($column_attributes !== null) {
                $default_width = $column_attributes['DefaultSizePts'] / 5.4;
            }
            $which_column = 0;
            foreach ($sheet->Cols->col_info as $column_override) {
                $which_column = $this->process_column_loop($which_column, $max_col, $column_override, $default_width);
            }
            while ($which_column <= $max_col) {
                $this->set_column_width($which_column, $default_width);
                ++$which_column;
            }
        }
    }
    private function set_row_height(int $which_row, float $default_height): void
    {
        $this->spreadsheet->get_active_sheet()->get_row_dimension($which_row)->set_row_height($default_height);
    }
    private function set_row_invisible(int $which_row): void
    {
        $this->spreadsheet->get_active_sheet()->get_row_dimension($which_row)->set_visible(false);
    }
    private function process_row_loop(int $which_row, int $max_row, ?Simple_Xml_Element $row_override, float $default_height): int
    {
        $row_override = self::test_simple_xml($row_override);
        $row_attributes = self::test_simple_xml($row_override->attributes());
        $row = $row_attributes['No'];
        $row_height = (float) $row_attributes['Unit'];
        $hidden = isset($row_attributes['Hidden']) && (string) $row_attributes['Hidden'] == '1';
        $row_count = (int) ($row_attributes['Count'] ?? 1);
        while ($which_row < $row) {
            ++$which_row;
            $this->set_row_height($which_row, $default_height);
        }
        while ($which_row < $row + $row_count && $which_row < $max_row) {
            ++$which_row;
            $this->set_row_height($which_row, $row_height);
            if ($hidden) {
                $this->set_row_invisible($which_row);
            }
        }
        return $which_row;
    }
    private function process_row_heights(?Simple_Xml_Element $sheet, int $max_row): void
    {
        if (!$this->read_data_only && $sheet !== null && isset($sheet->Rows)) {
            //    Row Heights
            $default_height = 0;
            $row_attributes = $sheet->Rows->attributes();
            if ($row_attributes !== null) {
                $default_height = (float) $row_attributes['DefaultSizePts'];
            }
            $which_row = 0;
            foreach ($sheet->Rows->row_info as $row_override) {
                $which_row = $this->process_row_loop($which_row, $max_row, $row_override, $default_height);
            }
            // never executed, I can't figure out any circumstances
            // under which it would be executed, and, even if
            // such exist, I'm not convinced this is needed.
            //while ($whichRow < $maxRow) {
            //    ++$whichRow;
            //    $this->spreadsheet->getActiveSheet()->getRowDimension($whichRow)->setRowHeight($defaultHeight);
            //}
        }
    }
    private function process_defined_names(?Simple_Xml_Element $gnm_xml): void
    {
        //    Loop through definedNames (global named ranges)
        if ($gnm_xml !== null && isset($gnm_xml->Names)) {
            foreach ($gnm_xml->Names->Name as $defined_name) {
                $name = (string) $defined_name->name;
                $value = (string) $defined_name->value;
                if (stripos($value, '#REF!') !== false) {
                    continue;
                }
                if (empty($value)) {
                    continue;
                }
                $value = str_replace("\\'", "''", $value);
                [$worksheet_name] = Worksheet::extract_sheet_title($value, true, true);
                $worksheet = $this->spreadsheet->get_sheet_by_name($worksheet_name);
                // Worksheet might still be null if we're only loading selected sheets rather than the full spreadsheet
                if ($worksheet !== null) {
                    $this->spreadsheet->add_defined_name(Defined_Name::create_instance($name, $worksheet, $value));
                }
            }
        }
    }
    private function parse_rich_text(string $is): Rich_Text
    {
        $value = new Rich_Text();
        $value->create_text($is);
        return $value;
    }
    private function load_cell(Simple_Xml_Element $cell, string $worksheet_name, Simple_Xml_Element $cell_attributes, string $column, int $row): void
    {
        $value_type = $cell_attributes->value_type;
        $expr_id = (string) $cell_attributes->expr_id;
        $rows = (int) ($cell_attributes->Rows ?? 0);
        $cols = (int) ($cell_attributes->Cols ?? 0);
        $type = Data_Type::TYPE_FORMULA;
        $is_array_formula = $rows > 0 && $cols > 0;
        $array_formula_range = $is_array_formula ? $this->get_array_formula_range($column, $row, $cols, $rows) : null;
        if ($expr_id > '') {
            if ((string) $cell > '') {
                // Formula
                $this->expressions[$expr_id] = ['column' => (int) $cell_attributes->Col, 'row' => (int) $cell_attributes->Row, 'formula' => (string) $cell];
            } else {
                // Shared Formula
                $expression = $this->expressions[$expr_id];
                $cell = $this->reference_helper->update_formula_references($expression['formula'], 'A1', $cell_attributes->Col - $expression['column'], $cell_attributes->Row - $expression['row'], $worksheet_name);
            }
            $type = Data_Type::TYPE_FORMULA;
        } elseif ($is_array_formula === false) {
            $vtype = (string) $value_type;
            if (array_key_exists($vtype, self::$mappings['dataType'])) {
                $type = self::$mappings['dataType'][$vtype];
            }
            if ($vtype === '20') {
                //    Boolean
                $cell = $cell == 'TRUE';
            }
        }
        $this->spreadsheet->get_active_sheet()->get_cell($column . $row)->set_value_explicit((string) $cell, $type);
        if ($array_formula_range === null) {
            $this->spreadsheet->get_active_sheet()->get_cell($column . $row)->set_formula_attributes(null);
        } else {
            $this->spreadsheet->get_active_sheet()->get_cell($column . $row)->set_formula_attributes(['t' => 'array', 'ref' => $array_formula_range]);
        }
        if (isset($cell_attributes->value_format)) {
            $this->spreadsheet->get_active_sheet()->get_cell($column . $row)->get_style()->get_number_format()->set_format_code((string) $cell_attributes->value_format);
        }
    }
    private function get_array_formula_range(string $column, int $row, int $cols, int $rows): string
    {
        $array_formula_range = $column . $row;
        return $array_formula_range . (':' . Coordinate::string_from_column_index(Coordinate::column_index_from_string($column) + $cols - 1) . ($row + $rows - 1));
    }
}