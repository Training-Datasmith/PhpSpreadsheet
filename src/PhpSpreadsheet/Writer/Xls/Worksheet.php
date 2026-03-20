<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Composer\Pcre\Preg;
use Gd_Image;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Rich_Text\Run;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xls;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Worksheet\Sheet_View;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
// Original file header of PEAR::Spreadsheet_Excel_Writer_Worksheet (used as the base for this class):
// -----------------------------------------------------------------------------------------
// /*
// *  Module written/ported by Xavier Noguer <xnoguer@rezebra.com>
// *
// *  The majority of this is _NOT_ my code.  I simply ported it from the
// *  PERL Spreadsheet::WriteExcel module.
// *
// *  The author of the Spreadsheet::WriteExcel module is John McNamara
// *  <jmcnamara@cpan.org>
// *
// *  I _DO_ maintain this code, and John McNamara has nothing to do with the
// *  porting of this code to PHP.  Any questions directly related to this
// *  class library should be directed to me.
// *
// *  License Information:
// *
// *    Spreadsheet_Excel_Writer:  A library for generating Excel Spreadsheets
// *    Copyright (c) 2002-2003 Xavier Noguer xnoguer@rezebra.com
// *
// *    This library is free software; you can redistribute it and/or
// *    modify it under the terms of the GNU Lesser General Public
// *    License as published by the Free Software Foundation; either
// *    version 2.1 of the License, or (at your option) any later version.
// *
// *    This library is distributed in the hope that it will be useful,
// *    but WITHOUT ANY WARRANTY; without even the implied warranty of
// *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// *    Lesser General Public License for more details.
// *
// *    You should have received a copy of the GNU Lesser General Public
// *    License along with this library; if not, write to the Free Software
// *    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
// */
class Worksheet extends Bif_Fwriter
{
    private static int $always0 = 0;
    private static int $always1 = 1;
    /**
     * Array containing format information for columns.
     *
     * @var array<array{int, int, float, int, int, int}>
     */
    private array $column_info;
    /**
     * The active pane for the worksheet.
     */
    private int $active_pane;
    /**
     * Whether to use outline.
     */
    private bool $outline_on;
    /**
     * Auto outline styles.
     */
    private bool $outline_style;
    //* @phpstan-ignore-line
    /**
     * Reference to the total number of strings in the workbook.
     */
    private int $string_total;
    /**
     * Reference to the number of unique strings in the workbook.
     */
    private int $string_unique;
    /**
     * Reference to the array containing all the unique strings in the workbook.
     *
     * @var array<string, int>
     */
    private array $string_table;
    /**
     * Color cache.
     *
     * @var mixed[]
     */
    private array $colors;
    /**
     * Index of first used row (at least 0).
     */
    private readonly int $first_row_index;
    /**
     * Index of last used row. (no used rows means -1).
     */
    private readonly int $last_row_index;
    /**
     * Index of first used column (at least 0).
     */
    private readonly int $first_column_index;
    /**
     * Index of last used column (no used columns means -1).
     */
    private readonly int $last_column_index;
    /**
     * Escher object corresponding to MSODRAWING.
     */
    private ?\Php_Office\Php_Spreadsheet\Shared\Escher $escher = null;
    /**
     * Array of font hashes associated to FONT records index.
     *
     * @var array<int|string>
     */
    public array $font_hash_index;
    private int $print_headers;
    /**
     * Constructor.
     *
     * @param int $str_total Total number of strings
     * @param int $str_unique Total number of unique strings
     * @param array<string, int> $str_table String Table
     * @param mixed[] $colors Colour Table
     * @param Parser $parser The formula parser created for the Workbook
     * @param bool $preCalculateFormulas Flag indicating whether formulas should be calculated or just written
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $phpSheet The worksheet to write
     */
    public function __construct(int &$str_total, int &$str_unique, array &$str_table, array &$colors, private readonly Parser $parser, private readonly bool $pre_calculate_formulas, public \Php_Office\Php_Spreadsheet\Worksheet\Worksheet $php_sheet, private readonly ?Workbook $writer_workbook = null)
    {
        // It needs to call its parent's constructor explicitly
        parent::__construct();
        $this->string_total =& $str_total;
        $this->string_unique =& $str_unique;
        $this->string_table =& $str_table;
        $this->colors =& $colors;
        $this->column_info = [];
        $this->active_pane = 3;
        $this->print_headers = 0;
        $this->outline_style = false;
        $this->outline_on = true;
        $this->font_hash_index = [];
        // calculate values for DIMENSIONS record
        $min_r = 1;
        $min_c = 'A';
        $max_r = $this->php_sheet->get_highest_row();
        $max_c = $this->php_sheet->get_highest_column();
        // Determine lowest and highest column and row
        // BIFF8 DIMENSIONS record requires 0-based indices for both rows and columns
        // Row methods return 1-based values (Excel UI), so subtract 1 to convert to 0-based
        $this->first_row_index = $min_r - 1;
        $this->last_row_index = $max_r > Address_Range::MAX_ROW_XLS ? Address_Range::MAX_ROW_XLS - 1 : $max_r - 1;
        // Column methods return 1-based values (columnIndexFromString('A') = 1), so subtract 1
        $this->first_column_index = Coordinate::column_index_from_string($min_c) - 1;
        $this->last_column_index = min(255, Coordinate::column_index_from_string($max_c) - 1);
    }
    /**
     * Add data to the beginning of the workbook (note the reverse order)
     * and to the end of the workbook.
     *
     * @see Workbook::storeWorkbook
     */
    public function close(): void
    {
        $php_sheet = $this->php_sheet;
        // Storing selected cells and active sheet because it changes while parsing cells with formulas.
        $selected_cells = $this->php_sheet->get_selected_cells();
        $active_sheet_index = $this->php_sheet->get_parent_or_throw()->get_active_sheet_index();
        // Write BOF record
        $this->store_bof(0x10);
        // Write PRINTHEADERS
        $this->write_print_headers();
        // Write PRINTGRIDLINES
        $this->write_print_gridlines();
        // Write GRIDSET
        $this->write_gridset();
        // Calculate column widths
        $php_sheet->calculate_column_widths();
        // Column dimensions
        if (($default_width = $php_sheet->get_default_column_dimension()->get_width()) < 0) {
            $default_width = \Php_Office\Php_Spreadsheet\Shared\Font::get_default_column_width_by_font($php_sheet->get_parent_or_throw()->get_default_style()->get_font());
        }
        $column_dimensions = $php_sheet->get_column_dimensions();
        // lastColumnIndex is now 0-based, so no need to subtract 1
        $max_col = $this->last_column_index;
        for ($i = 0; $i <= $max_col; ++$i) {
            $hidden = 0;
            $level = 0;
            $xf_index = 15;
            // there are 15 cell style Xfs
            $width = $default_width;
            $column_letter = Coordinate::string_from_column_index($i + 1);
            if (isset($column_dimensions[$column_letter])) {
                $column_dimension = $column_dimensions[$column_letter];
                if ($column_dimension->get_width() >= 0) {
                    $width = $column_dimension->get_width_for_output(true);
                }
                $hidden = $column_dimension->get_visible() ? 0 : 1;
                $level = $column_dimension->get_outline_level();
                $xf_index = $column_dimension->get_xf_index() + 15;
                // there are 15 cell style Xfs
            }
            // Components of columnInfo:
            // $firstcol first column on the range
            // $lastcol  last column on the range
            // $width    width to set
            // $xfIndex  The optional cell style Xf index to apply to the columns
            // $hidden   The optional hidden attribute
            // $level    The optional outline level
            $this->column_info[] = [$i, $i, $width, $xf_index, $hidden, $level];
        }
        // Write GUTS
        $this->write_guts();
        // Write DEFAULTROWHEIGHT
        $this->write_default_row_height();
        // Write WSBOOL
        $this->write_wsbool();
        // Write horizontal and vertical page breaks
        $this->write_breaks();
        // Write page header
        $this->write_header();
        // Write page footer
        $this->write_footer();
        // Write page horizontal centering
        $this->write_hcenter();
        // Write page vertical centering
        $this->write_vcenter();
        // Write left margin
        $this->write_margin_left();
        // Write right margin
        $this->write_margin_right();
        // Write top margin
        $this->write_margin_top();
        // Write bottom margin
        $this->write_margin_bottom();
        // Write page setup
        $this->write_setup();
        // Write sheet protection
        $this->write_protect();
        // Write SCENPROTECT
        $this->write_scen_protect();
        // Write OBJECTPROTECT
        $this->write_object_protect();
        // Write sheet password
        $this->write_password();
        // Write DEFCOLWIDTH record
        $this->write_defcol();
        // Write the COLINFO records if they exist
        if (!empty($this->column_info)) {
            $colcount = count($this->column_info);
            for ($i = 0; $i < $colcount; ++$i) {
                $this->write_colinfo($this->column_info[$i]);
            }
        }
        $auto_filter_range = $php_sheet->get_auto_filter()->get_range();
        if (!empty($auto_filter_range)) {
            // Write AUTOFILTERINFO
            $this->write_auto_filter_info();
        }
        // Write sheet dimensions
        $this->write_dimensions();
        // Row dimensions
        foreach ($php_sheet->get_row_dimensions() as $row_dimension) {
            $xf_index = $row_dimension->get_xf_index() + 15;
            // there are 15 cellXfs
            $this->write_row($row_dimension->get_row_index() - 1, (int) $row_dimension->get_row_height(), $xf_index, !$row_dimension->get_visible(), $row_dimension->get_outline_level());
        }
        // Write Cells
        foreach ($php_sheet->get_cell_collection()->get_sorted_coordinates() as $coordinate) {
            /** @var Cell $cell */
            $cell = $php_sheet->get_cell_collection()->get($coordinate);
            $row = $cell->get_row() - 1;
            $column = Coordinate::column_index_from_string($cell->get_column()) - 1;
            // Don't break Excel break the code!
            if ($row > 65535 || $column > 255) {
                throw new Writer_Exception('Rows or columns overflow! Excel5 has limit to 65535 rows and 255 columns. Use XLSX instead.');
            }
            // Write cell value
            $xf_index = $cell->get_xf_index() + 15;
            // there are 15 cell style Xfs
            $c_val = $cell->get_value();
            if ($c_val instanceof Rich_Text && (string) $c_val === '') {
                $c_val = '';
            }
            if ($c_val instanceof Rich_Text) {
                $arrc_run = [];
                $str_pos = 0;
                $elements = $c_val->get_rich_text_elements();
                foreach ($elements as $element) {
                    // FONT Index
                    $str_fontidx = 0;
                    if ($element instanceof Run) {
                        $get_font = $element->get_font();
                        if ($get_font !== null) {
                            $str_fontidx = $this->font_hash_index[$get_font->get_hash_code()];
                        }
                    } else {
                        $style_array = $this->php_sheet->get_parent()?->get_cell_xf_collection();
                        if ($style_array !== null) {
                            $font = $style_array[$xf_index - 15] ?? null;
                            if ($font !== null) {
                                $font = $font->get_font();
                            }
                            if ($font !== null) {
                                $str_fontidx = $this->font_hash_index[$font->get_hash_code()];
                            }
                        }
                    }
                    $arrc_run[] = ['strlen' => $str_pos, 'fontidx' => $str_fontidx];
                    // Position FROM
                    $str_pos += String_Helper::count_characters($element->get_text(), 'UTF-8');
                }
                /** @var array<int, array{strlen: int, fontidx: int}> $arrcRun */
                $this->write_rich_text_string($row, $column, $c_val->get_plain_text(), $xf_index, $arrc_run);
            } else {
                switch ($cell->get_datatype()) {
                    case Data_Type::TYPE_STRING:
                    case Data_Type::TYPE_INLINE:
                    case Data_Type::TYPE_NULL:
                        if ($c_val === '' || $c_val === null) {
                            $this->write_blank($row, $column, $xf_index);
                        } else {
                            $this->write_string($row, $column, $cell->get_value_string(), $xf_index);
                        }
                        break;
                    case Data_Type::TYPE_NUMERIC:
                        $this->write_number($row, $column, is_numeric($c_val) ? $c_val + 0 : 0, $xf_index);
                        break;
                    case Data_Type::TYPE_FORMULA:
                        $calculated_value = $this->pre_calculate_formulas ? $cell->get_calculated_value() : null;
                        $calculated_value_string = $this->pre_calculate_formulas ? $cell->get_calculated_value_string() : '';
                        if (self::WRITE_FORMULA_EXCEPTION == $this->write_formula($row, $column, $cell->get_value_string(), $xf_index, $calculated_value)) {
                            if ($calculated_value === null) {
                                $calculated_value = $cell->get_calculated_value();
                            }
                            $calctype = gettype($calculated_value);
                            match ($calctype) {
                                'integer', 'double' => $this->write_number($row, $column, is_numeric($calculated_value) ? (float) $calculated_value : 0.0, $xf_index),
                                'string' => $this->write_string($row, $column, $calculated_value_string, $xf_index),
                                'boolean' => $this->write_bool_err($row, $column, (int) $calculated_value_string, 0, $xf_index),
                                default => $this->write_string($row, $column, $cell->get_value_string(), $xf_index),
                            };
                        }
                        break;
                    case Data_Type::TYPE_BOOL:
                        $this->write_bool_err($row, $column, (int) $cell->get_value_string(), 0, $xf_index);
                        break;
                    case Data_Type::TYPE_ERROR:
                        $this->write_bool_err($row, $column, Error_Code::error($cell->get_value_string()), 1, $xf_index);
                        break;
                }
            }
        }
        // Append
        $this->write_mso_drawing();
        // Restoring active sheet.
        $this->php_sheet->get_parent_or_throw()->set_active_sheet_index($active_sheet_index);
        // Write WINDOW2 record
        $this->write_window2();
        // Write PLV record
        $this->write_page_layout_view();
        // Write ZOOM record
        $this->write_zoom();
        if ($php_sheet->get_freeze_pane()) {
            $this->write_panes();
        }
        // Restoring selected cells.
        $this->php_sheet->set_selected_cells($selected_cells);
        // Write SELECTION record
        $this->write_selection();
        // Write MergedCellsTable Record
        $this->write_merged_cells();
        // Hyperlinks
        $php_parent = $php_sheet->get_parent();
        $hyperlinkbase = $php_parent === null ? '' : $php_parent->get_properties()->get_hyperlink_base();
        foreach ($php_sheet->get_hyper_link_collection() as $coordinate => $hyperlink) {
            [$column, $row] = Coordinate::indexes_from_string($coordinate);
            $url = $hyperlink->get_url();
            if ($url[0] === '#') {
                $url = "internal:{$url}";
            } elseif (str_starts_with($url, 'sheet://')) {
                // internal to current workbook
                $url = str_replace('sheet://', 'internal:', $url);
            } elseif (Preg::is_match('/^(http:|https:|ftp:|mailto:)/', $url)) {
                // URL
            } elseif (!empty($hyperlinkbase) && !Preg::is_match('~^([A-Za-z]:)?[/\\\\]~', $url)) {
                $url = "{$hyperlinkbase}{$url}";
                if (!Preg::is_match('/^(http:|https:|ftp:|mailto:)/', $url)) {
                    $url = 'external:' . $url;
                }
            } else {
                // external (local file)
                $url = 'external:' . $url;
            }
            $this->write_url($row - 1, $column - 1, $url);
        }
        $this->write_data_validity();
        $this->write_sheet_layout();
        // Write SHEETPROTECTION record
        $this->write_sheet_protection();
        $this->write_range_protection();
        // Write Conditional Formatting Rules and Styles
        $this->write_conditional_formatting();
        $this->store_eof();
    }
    /** @deprecated 5.6.0 Use AddressRange::MAX_COLUMN_INT_XLS */
    public const MAX_XLS_COLUMN = Address_Range::MAX_COLUMN_INT_XLS;
    /** @deprecated 5.6.0 Use AddressRange::MAX_COLUMN_XLS */
    public const MAX_XLS_COLUMN_STRING = Address_Range::MAX_COLUMN_XLS;
    /** @deprecated 5.6.0 Use AddressRange::MAX_ROW_XLS */
    public const MAX_XLS_ROW = Address_Range::MAX_ROW_XLS;
    private static function limit_range(string $exploded): string
    {
        $ret_val = '';
        $ranges = Coordinate::get_range_boundaries($exploded);
        $first_col = Coordinate::column_index_from_string($ranges[0][0]);
        $first_row = (int) $ranges[0][1];
        if ($first_col <= Address_Range::MAX_COLUMN_INT_XLS && $first_row <= Address_Range::MAX_ROW_XLS) {
            $ret_val = $exploded;
            if (str_contains($exploded, ':')) {
                $last_col = Coordinate::column_index_from_string($ranges[1][0]);
                $ranges[1][1] = min(Address_Range::MAX_ROW_XLS, (int) $ranges[1][1]);
                if ($last_col > Address_Range::MAX_COLUMN_INT_XLS) {
                    $ranges[1][0] = Address_Range::MAX_COLUMN_XLS;
                }
                $ret_val = "{$ranges[0][0]}{$ranges[0][1]}:{$ranges[1][0]}{$ranges[1][1]}";
            }
        }
        return $ret_val;
    }
    private function write_conditional_formatting(): void
    {
        $conditional_formula_helper = new Conditional_Helper($this->parser);
        $arr_conditional_styles = [];
        foreach ($this->php_sheet->get_conditional_styles_collection() as $key => $value) {
            $key_explode = explode(',', Coordinate::resolve_union_and_intersection($key));
            foreach ($key_explode as $exploded) {
                $range = self::limit_range($exploded);
                if ($range !== '') {
                    $arr_conditional_styles[$range] = $value;
                }
            }
        }
        // Write ConditionalFormattingTable records
        foreach ($arr_conditional_styles as $cell_coordinate => $conditional_styles) {
            $cf_header_written = false;
            foreach ($conditional_styles as $conditional) {
                /** @var Conditional $conditional */
                if ($conditional->get_condition_type() === Conditional::CONDITION_EXPRESSION || $conditional->get_condition_type() === Conditional::CONDITION_CELLIS) {
                    // Write CFHEADER record (only if there are Conditional Styles that we are able to write)
                    if ($cf_header_written === false) {
                        $cf_header_written = $this->write_cf_header($cell_coordinate, $conditional_styles);
                    }
                    if ($cf_header_written === true) {
                        // Write CFRULE record
                        $this->write_cf_rule($conditional_formula_helper, $conditional, $cell_coordinate);
                    }
                }
            }
        }
    }
    /**
     * Write a cell range address in BIFF8
     * always fixed range
     * See section 2.5.14 in OpenOffice.org's Documentation of the Microsoft Excel File Format.
     *
     * @param string $range E.g. 'A1' or 'A1:B6'
     *
     * @return string Binary data
     */
    private function write_biff8cell_range_address_fixed(string $range): string
    {
        $explodes = explode(':', $range);
        // extract first cell, e.g. 'A1'
        $first_cell = $explodes[0];
        if (ctype_alpha($first_cell)) {
            $first_cell .= '1';
        } elseif (ctype_digit($first_cell)) {
            $first_cell = "A{$first_cell}";
        }
        // extract last cell, e.g. 'B6'
        if (count($explodes) == 1) {
            $last_cell = $first_cell;
        } else {
            $last_cell = $explodes[1];
        }
        if (ctype_alpha($last_cell)) {
            $last_cell .= (string) Address_Range::MAX_ROW_XLS;
        } elseif (ctype_digit($last_cell)) {
            $last_cell = Address_Range::MAX_COLUMN_XLS . $last_cell;
        }
        $first_cell_coordinates = Coordinate::indexes_from_string($first_cell);
        // e.g. [0, 1]
        $last_cell_coordinates = Coordinate::indexes_from_string($last_cell);
        // e.g. [1, 6]
        return pack('vvvv', $first_cell_coordinates[1] - 1, $last_cell_coordinates[1] - 1, $first_cell_coordinates[0] - 1, $last_cell_coordinates[0] - 1);
    }
    /**
     * Retrieves data from memory in one chunk, or from disk
     * sized chunks.
     *
     * @return string The data
     */
    public function get_data(): string
    {
        // Return data stored in memory
        if (isset($this->_data)) {
            $tmp = $this->_data;
            $this->_data = null;
            return $tmp;
        }
        // No data to return
        return '';
    }
    /**
     * Set the option to print the row and column headers on the printed page.
     *
     * @param int $print Whether to print the headers or not. Defaults to 1 (print).
     */
    public function print_row_col_headers(int $print = 1): void
    {
        $this->print_headers = $print;
    }
    /**
     * This method sets the properties for outlining and grouping. The defaults
     * correspond to Excel's defaults.
     */
    public function set_outline(bool $visible = true, bool $symbols_below = true, bool $symbols_right = true, bool $auto_style = false): void
    {
        $this->outline_on = $visible;
        $this->outline_style = $auto_style;
    }
    /**
     * Write a double to the specified row and column (zero indexed).
     * An integer can be written as a double. Excel will display an
     * integer. $format is optional.
     *
     * Returns  0 : normal termination
     *         -2 : row or column out of range
     *
     * @param int $row Zero indexed row
     * @param int $col Zero indexed column
     * @param float $num The number to write
     * @param int $xfIndex The optional XF format
     */
    private function write_number(int $row, int $col, float $num, int $xf_index): int
    {
        $record = 0x203;
        // Record identifier
        $length = 0xe;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('vvv', $row, $col, $xf_index);
        $xl_double = pack('d', $num);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $xl_double = strrev($xl_double);
        }
        $this->append($header . $data . $xl_double);
        return 0;
    }
    /**
     * Write a LABELSST record or a LABEL record. Which one depends on BIFF version.
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @param string $str The string
     * @param int $xfIndex Index to XF record
     */
    private function write_string(int $row, int $col, string $str, int $xf_index): void
    {
        $this->write_label_sst($row, $col, $str, $xf_index);
    }
    /**
     * Write a LABELSST record or a LABEL record. Which one depends on BIFF version
     * It differs from writeString by the writing of rich text strings.
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @param string $str The string
     * @param int $xfIndex The XF format index for the cell
     * @param array<int, array{strlen: int, fontidx: int}> $arrcRun Index to Font record and characters beginning
     */
    private function write_rich_text_string(int $row, int $col, string $str, int $xf_index, array $arrc_run): void
    {
        $record = 0xfd;
        // Record identifier
        $length = 0xa;
        // Bytes to follow
        $str = String_Helper::utf8to_biff8unicode_short($str, $arrc_run);
        // check if string is already present
        if (!isset($this->string_table[$str])) {
            $this->string_table[$str] = $this->string_unique++;
        }
        ++$this->string_total;
        $header = pack('vv', $record, $length);
        $data = pack('vvvV', $row, $col, $xf_index, $this->string_table[$str]);
        $this->append($header . $data);
    }
    /**
     * Write a string to the specified row and column (zero indexed).
     * This is the BIFF8 version (no 255 chars limit).
     * $format is optional.
     *
     * @param int $row Zero indexed row
     * @param int $col Zero indexed column
     * @param string $str The string to write
     * @param int $xfIndex The XF format index for the cell
     */
    private function write_label_sst(int $row, int $col, string $str, int $xf_index): void
    {
        $record = 0xfd;
        // Record identifier
        $length = 0xa;
        // Bytes to follow
        $str = String_Helper::utf8to_biff8unicode_long($str);
        // check if string is already present
        if (!isset($this->string_table[$str])) {
            $this->string_table[$str] = $this->string_unique++;
        }
        ++$this->string_total;
        $header = pack('vv', $record, $length);
        $data = pack('vvvV', $row, $col, $xf_index, $this->string_table[$str]);
        $this->append($header . $data);
    }
    /**
     * Write a blank cell to the specified row and column (zero indexed).
     * A blank cell is used to specify formatting without adding a string
     * or a number.
     *
     * A blank cell without a format serves no purpose. Therefore, we don't write
     * a BLANK record unless a format is specified.
     *
     * Returns  0 : normal termination (including no format)
     *         -1 : insufficient number of arguments
     *         -2 : row or column out of range
     *
     * @param int $row Zero indexed row
     * @param int $col Zero indexed column
     * @param int $xfIndex The XF format index
     */
    public function write_blank(int $row, int $col, int $xf_index): int
    {
        $record = 0x201;
        // Record identifier
        $length = 0x6;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('vvv', $row, $col, $xf_index);
        $this->append($header . $data);
        return 0;
    }
    /**
     * Write a boolean or an error type to the specified row and column (zero indexed).
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @param int $isError Error or Boolean?
     */
    private function write_bool_err(int $row, int $col, int $value, int $is_error, int $xf_index): int
    {
        $record = 0x205;
        $length = 8;
        $header = pack('vv', $record, $length);
        $data = pack('vvvCC', $row, $col, $xf_index, $value, $is_error);
        $this->append($header . $data);
        return 0;
    }
    public const WRITE_FORMULA_NORMAL = 0;
    public const WRITE_FORMULA_ERRORS = -1;
    public const WRITE_FORMULA_RANGE = -2;
    public const WRITE_FORMULA_EXCEPTION = -3;
    private static bool $allow_throw = false;
    public static function set_allow_throw(bool $allow_throw): void
    {
        self::$allow_throw = $allow_throw;
    }
    public static function get_allow_throw(): bool
    {
        return self::$allow_throw;
    }
    /**
     * Write a formula to the specified row and column (zero indexed).
     * The textual representation of the formula is passed to the parser in
     * Parser.php which returns a packed binary string.
     *
     * Returns  0 : WRITE_FORMULA_NORMAL  normal termination
     *         -1 : WRITE_FORMULA_ERRORS formula errors (bad formula)
     *         -2 : WRITE_FORMULA_RANGE  row or column out of range
     *         -3 : WRITE_FORMULA_EXCEPTION parse raised exception, probably due to definedname
     *
     * @param int $row Zero indexed row
     * @param int $col Zero indexed column
     * @param string $formula The formula text string
     * @param int $xfIndex The XF format index
     * @param mixed $calculatedValue Calculated value
     */
    private function write_formula(int $row, int $col, string $formula, int $xf_index, mixed $calculated_value): int
    {
        $record = 0x6;
        // Record identifier
        // Initialize possible additional value for STRING record that should be written after the FORMULA record?
        $string_value = null;
        // calculated value
        if (isset($calculated_value)) {
            // Since we can't yet get the data type of the calculated value,
            // we use best effort to determine data type
            if (is_bool($calculated_value)) {
                // Boolean value
                $num = pack('CCCvCv', 0x1, 0x0, (int) $calculated_value, 0x0, 0x0, 0xffff);
            } elseif (is_int($calculated_value) || is_float($calculated_value)) {
                // Numeric value
                $num = pack('d', $calculated_value);
            } elseif (is_string($calculated_value)) {
                $error_codes = Data_Type::get_error_codes();
                if (isset($error_codes[$calculated_value])) {
                    // Error value
                    $num = pack('CCCvCv', 0x2, 0x0, Error_Code::error($calculated_value), 0x0, 0x0, 0xffff);
                } elseif ($calculated_value === '') {
                    // Empty string (and BIFF8)
                    $num = pack('CCCvCv', 0x3, 0x0, 0x0, 0x0, 0x0, 0xffff);
                } else {
                    // Non-empty string value (or empty string BIFF5)
                    $string_value = $calculated_value;
                    $num = pack('CCCvCv', 0x0, 0x0, 0x0, 0x0, 0x0, 0xffff);
                }
            } else {
                // We are really not supposed to reach here
                $num = pack('d', 0x0);
            }
        } else {
            $num = pack('d', 0x0);
        }
        $grbit = 0x3;
        // Option flags
        $unknown = 0x0;
        // Must be zero
        // Strip the '=' or '@' sign at the beginning of the formula string
        if ($formula[0] == '=') {
            $formula = substr($formula, 1);
        } else {
            // Error handling
            $this->write_string($row, $col, 'Unrecognised character for formula', 0);
            return self::WRITE_FORMULA_ERRORS;
        }
        // Parse the formula using the parser in Parser.php
        try {
            $this->parser->parse($formula);
            $formula = $this->parser->to_reverse_polish();
            $formlen = strlen($formula);
            // Length of the binary string
            $length = 0x16 + $formlen;
            // Length of the record data
            $header = pack('vv', $record, $length);
            $data = pack('vvv', $row, $col, $xf_index) . $num . pack('vVv', $grbit, $unknown, $formlen);
            $this->append($header . $data . $formula);
            // Append also a STRING record if necessary
            if ($string_value !== null) {
                $this->write_string_record($string_value);
            }
            return self::WRITE_FORMULA_NORMAL;
        } catch (Php_Spreadsheet_Exception $e) {
            if (self::$allow_throw) {
                throw $e;
            }
            return self::WRITE_FORMULA_EXCEPTION;
        }
    }
    /**
     * Write a STRING record. This.
     */
    private function write_string_record(string $string_value): void
    {
        $record = 0x207;
        // Record identifier
        $data = String_Helper::utf8to_biff8unicode_long($string_value);
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        $this->append($header . $data);
    }
    /**
     * Write a hyperlink.
     * This is comprised of two elements: the visible label and
     * the invisible link. The visible label is the same as the link unless an
     * alternative string is specified. The label is written using the
     * writeString() method. Therefore the 255 characters string limit applies.
     * $string and $format are optional.
     *
     * The hyperlink can be to a http, ftp, mail, internal sheet (not yet), or external
     * directory url.
     *
     * @param int $row Row
     * @param int $col Column
     * @param string $url URL string
     */
    private function write_url(int $row, int $col, string $url): void
    {
        // Add start row and col to arg list
        $this->write_url_range($row, $col, $row, $col, $url);
    }
    /**
     * This is the more general form of writeUrl(). It allows a hyperlink to be
     * written to a range of cells. This function also decides the type of hyperlink
     * to be written. These are either, Web (http, ftp, mailto), Internal
     * (Sheet1!A1) or external ('c:\temp\foo.xls#Sheet1!A1').
     *
     * @param int $row1 Start row
     * @param int $col1 Start column
     * @param int $row2 End row
     * @param int $col2 End column
     * @param string $url URL string
     *
     * @see writeUrl()
     */
    private function write_url_range(int $row1, int $col1, int $row2, int $col2, string $url): void
    {
        // Check for internal/external sheet links or default to web link
        if (Preg::is_match('[^internal:]', $url)) {
            $this->write_url_internal($row1, $col1, $row2, $col2, $url);
        } elseif (Preg::is_match('[^external:]', $url)) {
            $this->write_url_external($row1, $col1, $row2, $col2, $url);
        } else {
            $this->write_url_web($row1, $col1, $row2, $col2, $url);
        }
    }
    /**
     * Used to write http, ftp and mailto hyperlinks.
     * The link type ($options) is 0x03 is the same as absolute dir ref without
     * sheet. However it is differentiated by the $unknown2 data stream.
     *
     * @param int $row1 Start row
     * @param int $col1 Start column
     * @param int $row2 End row
     * @param int $col2 End column
     * @param string $url URL string
     *
     * @see writeUrl()
     */
    public function write_url_web(int $row1, int $col1, int $row2, int $col2, string $url): void
    {
        $record = 0x1b8;
        // Record identifier
        // Pack the undocumented parts of the hyperlink stream
        $unknown1 = pack('H*', 'D0C9EA79F9BACE118C8200AA004BA90B02000000');
        $unknown2 = pack('H*', 'E0C9EA79F9BACE118C8200AA004BA90B');
        // Pack the option flags
        $options = pack('V', 0x3);
        // Convert URL to a null terminated wchar string
        $url = implode("\x00", Preg::split("''", $url, -1, PREG_SPLIT_NO_EMPTY));
        $url = $url . "\x00\x00\x00";
        // Pack the length of the URL
        $url_len = pack('V', strlen($url));
        // Calculate the data length
        $length = 0x34 + strlen($url);
        // Pack the header data
        $header = pack('vv', $record, $length);
        $data = pack('vvvv', $row1, $row2, $col1, $col2);
        // Write the packed data
        $this->append($header . $data . $unknown1 . $options . $unknown2 . $url_len . $url);
    }
    /**
     * Used to write internal reference hyperlinks such as "Sheet1!A1".
     *
     * @param int $row1 Start row
     * @param int $col1 Start column
     * @param int $row2 End row
     * @param int $col2 End column
     * @param string $url URL string
     *
     * @see writeUrl()
     */
    private function write_url_internal(int $row1, int $col1, int $row2, int $col2, string $url): void
    {
        $record = 0x1b8;
        // Record identifier
        // Strip URL type
        $url = Preg::replace('/^internal:/', '', $url);
        // Pack the undocumented parts of the hyperlink stream
        $unknown1 = pack('H*', 'D0C9EA79F9BACE118C8200AA004BA90B02000000');
        // Pack the option flags
        $options = pack('V', 0x8);
        // Convert the URL type and to a null terminated wchar string
        $url .= "\x00";
        // character count
        $url_len = String_Helper::count_characters($url);
        $url_len = pack('V', $url_len);
        $url = String_Helper::convert_encoding($url, 'UTF-16LE', 'UTF-8');
        // Calculate the data length
        $length = 0x24 + strlen($url);
        // Pack the header data
        $header = pack('vv', $record, $length);
        $data = pack('vvvv', $row1, $row2, $col1, $col2);
        // Write the packed data
        $this->append($header . $data . $unknown1 . $options . $url_len . $url);
    }
    /**
     * Write links to external directory names such as 'c:\foo.xls',
     * c:\foo.xls#Sheet1!A1', '../../foo.xls'. and '../../foo.xls#Sheet1!A1'.
     *
     * Note: Excel writes some relative links with the $dir_long string. We ignore
     * these cases for the sake of simpler code.
     *
     * @param int $row1 Start row
     * @param int $col1 Start column
     * @param int $row2 End row
     * @param int $col2 End column
     * @param string $url URL string
     *
     * @see writeUrl()
     */
    private function write_url_external(int $row1, int $col1, int $row2, int $col2, string $url): void
    {
        // Network drives are different. We will handle them separately
        // MS/Novell network drives and shares start with \\
        if (Preg::is_match('[^external:\\\\]', $url)) {
            return;
        }
        $record = 0x1b8;
        // Record identifier
        // Strip URL type and change Unix dir separator to Dos style (if needed)
        //
        $url = Preg::replace(['/^external:/', '/\//'], ['', '\\'], $url);
        // Determine if the link is relative or absolute:
        //   relative if link contains no dir separator, "somefile.xls"
        //   relative if link starts with up-dir, "..\..\somefile.xls"
        //   otherwise, absolute
        $absolute = 0x0;
        // relative path
        if (Preg::is_match('/^[A-Z]:/', $url)) {
            $absolute = 0x2;
            // absolute path on Windows, e.g. C:\...
        }
        $link_type = 0x1 | $absolute;
        // Determine if the link contains a sheet reference and change some of the
        // parameters accordingly.
        // Split the dir name and sheet name (if it exists)
        $dir_long = $url;
        if (Preg::is_match('/\#/', $url)) {
            $link_type |= 0x8;
        }
        // Pack the link type
        $link_type = pack('V', $link_type);
        // Calculate the up-level dir count e.g.. (..\..\..\ == 3)
        $up_count = Preg::is_match_all('/\.\.\\\\/', $dir_long, $useless);
        $up_count = pack('v', $up_count);
        // Store the short dos dir name (null terminated)
        $dir_short = Preg::replace('/\.\.\\\\/', '', $dir_long) . "\x00";
        // Store the long dir name as a wchar string (non-null terminated)
        //$dir_long = $dir_long . "\0";
        // Pack the lengths of the dir strings
        $dir_short_len = pack('V', strlen($dir_short));
        //$dir_long_len = pack('V', strlen($dir_long));
        $stream_len = pack('V', 0);
        //strlen($dir_long) + 0x06);
        // Pack the undocumented parts of the hyperlink stream
        $unknown1 = pack('H*', 'D0C9EA79F9BACE118C8200AA004BA90B02000000');
        $unknown2 = pack('H*', '0303000000000000C000000000000046');
        $unknown3 = pack('H*', 'FFFFADDE000000000000000000000000000000000000000');
        //$unknown4 = pack('v', 0x03);
        // Pack the main data stream
        $data = pack('vvvv', $row1, $row2, $col1, $col2) . $unknown1 . $link_type . $unknown2 . $up_count . $dir_short_len . $dir_short . $unknown3 . $stream_len;
        /*.
          $dir_long_len .
          $unknown4     .
          $dir_long     .
          $sheet_len    .
          $sheet        ;*/
        // Pack the header data
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        // Write the packed data
        $this->append($header . $data);
    }
    /**
     * This method is used to set the height and format for a row.
     *
     * @param int $row The row to set
     * @param int $height Height we are giving to the row.
     *                        Use null to set XF without setting height
     * @param int $xfIndex The optional cell style Xf index to apply to the columns
     * @param bool $hidden The optional hidden attribute
     * @param int $level The optional outline level for row, in range [0,7]
     */
    private function write_row(int $row, int $height, int $xf_index, bool $hidden = false, int $level = 0): void
    {
        $record = 0x208;
        // Record identifier
        $length = 0x10;
        // Number of bytes to follow
        $col_mic = 0x0;
        // First defined column
        $col_mac = 0x0;
        // Last defined column
        $irw_mac = 0x0;
        // Used by Excel to optimise loading
        $reserved = 0x0;
        // Reserved
        $grbit = 0x0;
        // Option flags
        $ixfe = $xf_index;
        if ($height < 0) {
            $height = null;
        }
        // Use writeRow($row, null, $XF) to set XF format without setting height
        if ($height !== null) {
            $miy_rw = $height * 20;
            // row height
        } else {
            $miy_rw = 0xff;
            // default row height is 256
        }
        // Set the options flags. fUnsynced is used to show that the font and row
        // heights are not compatible. This is usually the case for WriteExcel.
        // The collapsed flag 0x10 doesn't seem to be used to indicate that a row
        // is collapsed. Instead it is used to indicate that the previous row is
        // collapsed. The zero height flag, 0x20, is used to collapse a row.
        $grbit |= $level;
        if ($hidden === true) {
            $grbit |= 0x30;
        }
        if ($height !== null) {
            $grbit |= 0x40;
            // fUnsynced
        }
        if ($xf_index !== 0xf) {
            $grbit |= 0x80;
        }
        $grbit |= 0x100;
        $header = pack('vv', $record, $length);
        $data = pack('vvvvvvvv', $row, $col_mic, $col_mac, $miy_rw, $irw_mac, $reserved, $grbit, $ixfe);
        $this->append($header . $data);
    }
    /**
     * Writes Excel DIMENSIONS to define the area in which there is data.
     */
    private function write_dimensions(): void
    {
        $record = 0x200;
        // Record identifier
        $length = 0xe;
        $data = pack('VVvvv', $this->first_row_index, $this->last_row_index + 1, $this->first_column_index, $this->last_column_index + 1, 0x0);
        // reserved
        $header = pack('vv', $record, $length);
        $this->append($header . $data);
    }
    /**
     * Write BIFF record Window2.
     */
    private function write_window2(): void
    {
        $record = 0x23e;
        // Record identifier
        $length = 0x12;
        $rw_top = 0x0;
        // Top row visible in window
        $col_left = 0x0;
        // Leftmost column visible in window
        // The options flags that comprise $grbit
        $f_dsp_fmla = 0;
        // 0 - bit
        $f_dsp_grid = $this->php_sheet->get_show_gridlines() ? 1 : 0;
        // 1
        $f_dsp_rw_col = $this->php_sheet->get_show_row_col_headers() ? 1 : 0;
        // 2
        $f_frozen = $this->php_sheet->get_freeze_pane() ? 1 : 0;
        // 3
        $f_dsp_zeros = 1;
        // 4
        $f_default_hdr = 1;
        // 5
        $f_arabic = $this->php_sheet->get_right_to_left() ? 1 : 0;
        // 6
        $f_dsp_guts = $this->outline_on;
        // 7
        $f_frozen_no_split = 0;
        // 0 - bit
        // no support in PhpSpreadsheet for selected sheet, therefore sheet is only selected if it is the active sheet
        $f_selected = $this->php_sheet === $this->php_sheet->get_parent_or_throw()->get_active_sheet() ? 1 : 0;
        $f_page_break_preview = $this->php_sheet->get_sheet_view()->get_view() === Sheet_View::SHEETVIEW_PAGE_BREAK_PREVIEW;
        $grbit = $f_dsp_fmla;
        $grbit |= $f_dsp_grid << 1;
        $grbit |= $f_dsp_rw_col << 2;
        $grbit |= $f_frozen << 3;
        $grbit |= $f_dsp_zeros << 4;
        $grbit |= $f_default_hdr << 5;
        $grbit |= $f_arabic << 6;
        $grbit |= $f_dsp_guts << 7;
        $grbit |= $f_frozen_no_split << 8;
        $grbit |= $f_selected << 9;
        // Selected sheets.
        $grbit |= $f_selected << 10;
        // Active sheet.
        $grbit |= $f_page_break_preview << 11;
        $header = pack('vv', $record, $length);
        $data = pack('vvv', $grbit, $rw_top, $col_left);
        // FIXME !!!
        $rgb_hdr = 0x40;
        // Row/column heading and gridline color index
        $zoom_factor_page_break = $f_page_break_preview ? $this->php_sheet->get_sheet_view()->get_zoom_scale() : 0x0;
        $zoom_factor_normal = $this->php_sheet->get_sheet_view()->get_zoom_scale_normal();
        $data .= pack('vvvvV', $rgb_hdr, 0x0, $zoom_factor_page_break, $zoom_factor_normal, 0x0);
        $this->append($header . $data);
    }
    /**
     * Write BIFF record DEFAULTROWHEIGHT.
     */
    private function write_default_row_height(): void
    {
        $default_row_height = $this->php_sheet->get_default_row_dimension()->get_row_height();
        if ($default_row_height < 0) {
            return;
        }
        // convert to twips
        $default_row_height = 20 * $default_row_height;
        $record = 0x225;
        // Record identifier
        $length = 0x4;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('vv', 1, $default_row_height);
        $this->append($header . $data);
    }
    /**
     * Write BIFF record DEFCOLWIDTH if COLINFO records are in use.
     */
    private function write_defcol(): void
    {
        $default_col_width = 8;
        $record = 0x55;
        // Record identifier
        $length = 0x2;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('v', $default_col_width);
        $this->append($header . $data);
    }
    /**
     * Write BIFF record COLINFO to define column widths.
     *
     * Note: The SDK says the record length is 0x0B but Excel writes a 0x0C
     * length record.
     *
     * @param array{?int, ?int, ?float, ?int, ?int, ?int} $col_array This is the only parameter received and is composed of the following:
     *                0 => First formatted column,
     *                1 => Last formatted column,
     *                2 => Col width (8.43 is Excel default),
     *                3 => The optional XF format of the column,
     *                4 => Option flags.
     *                5 => Optional outline level
     */
    private function write_colinfo(array $col_array): void
    {
        $col_first = $col_array[0] ?? null;
        $col_last = $col_array[1] ?? null;
        $coldx = $col_array[2] ?? 8.43;
        $xf_index = $col_array[3] ?? 15;
        $grbit = $col_array[4] ?? 0;
        $level = $col_array[5] ?? 0;
        $record = 0x7d;
        // Record identifier
        $length = 0xc;
        // Number of bytes to follow
        $coldx *= 256;
        // Convert to units of 1/256 of a char
        $ixfe = $xf_index;
        $reserved = 0x0;
        // Reserved
        $level = max(0, min($level, 7));
        $grbit |= $level << 8;
        $header = pack('vv', $record, $length);
        $data = pack('vvvvvv', $col_first, $col_last, $coldx, $ixfe, $grbit, $reserved);
        $this->append($header . $data);
    }
    /**
     * Write BIFF record SELECTION.
     */
    private function write_selection(): void
    {
        // look up the selected cell range
        $selected_cells = Coordinate::split_range($this->php_sheet->get_selected_cells());
        $selected_cells = $selected_cells[0];
        if (count($selected_cells) == 2) {
            [$first, $last] = $selected_cells;
        } else {
            $first = $selected_cells[0];
            $last = $selected_cells[0];
        }
        [$col_first, $rw_first] = Coordinate::coordinate_from_string($first);
        $col_first = Coordinate::column_index_from_string($col_first) - 1;
        // base 0 column index
        --$rw_first;
        // base 0 row index
        [$col_last, $rw_last] = Coordinate::coordinate_from_string($last);
        $col_last = Coordinate::column_index_from_string($col_last) - 1;
        // base 0 column index
        --$rw_last;
        // base 0 row index
        // make sure we are not out of bounds
        $col_first = min($col_first, 255);
        $col_last = min($col_last, 255);
        $rw_first = min($rw_first, 65535);
        $rw_last = min($rw_last, 65535);
        $record = 0x1d;
        // Record identifier
        $length = 0xf;
        // Number of bytes to follow
        $pnn = $this->active_pane;
        // Pane position
        $rw_act = $rw_first;
        // Active row
        $col_act = $col_first;
        // Active column
        $iref_act = 0;
        // Active cell ref
        $cref = 1;
        // Number of refs
        // Swap last row/col for first row/col as necessary
        if ($rw_first > $rw_last) {
            [$rw_first, $rw_last] = [$rw_last, $rw_first];
        }
        if ($col_first > $col_last) {
            [$col_first, $col_last] = [$col_last, $col_first];
        }
        $header = pack('vv', $record, $length);
        $data = pack('CvvvvvvCC', $pnn, $rw_act, $col_act, $iref_act, $cref, $rw_first, $rw_last, $col_first, $col_last);
        $this->append($header . $data);
    }
    /**
     * Store the MERGEDCELLS records for all ranges of merged cells.
     */
    private function write_merged_cells(): void
    {
        $merge_cells = $this->php_sheet->get_merge_cells();
        $count_merge_cells = count($merge_cells);
        if ($count_merge_cells == 0) {
            return;
        }
        // maximum allowed number of merged cells per record
        $max_count_merge_cells_per_record = 1027;
        // record identifier
        $record = 0xe5;
        // counter for total number of merged cells treated so far by the writer
        $i = 0;
        // counter for number of merged cells written in record currently being written
        $j = 0;
        // initialize record data
        $record_data = '';
        // loop through the merged cells
        foreach ($merge_cells as $merge_cell) {
            ++$i;
            ++$j;
            // extract the row and column indexes
            $range = Coordinate::split_range($merge_cell);
            [$first, $last] = $range[0];
            [$first_column, $first_row] = Coordinate::indexes_from_string($first);
            [$last_column, $last_row] = Coordinate::indexes_from_string($last);
            $record_data .= pack('vvvv', $first_row - 1, $last_row - 1, $first_column - 1, $last_column - 1);
            // flush record if we have reached limit for number of merged cells, or reached final merged cell
            if ($j == $max_count_merge_cells_per_record || $i == $count_merge_cells) {
                $record_data = pack('v', $j) . $record_data;
                $length = strlen($record_data);
                $header = pack('vv', $record, $length);
                $this->append($header . $record_data);
                // initialize for next record, if any
                $record_data = '';
                $j = 0;
            }
        }
    }
    /**
     * Write SHEETLAYOUT record.
     */
    private function write_sheet_layout(): void
    {
        if (!$this->php_sheet->is_tab_color_set()) {
            return;
        }
        $record_data = pack(
            'vvVVVvv',
            0x862,
            0x0,
            // unused
            0x0,
            // unused
            0x0,
            // unused
            0x14,
            // size of record data
            $this->colors[$this->php_sheet->get_tab_color()->get_rgb()],
            // color index
            0x0
        );
        $length = strlen($record_data);
        $record = 0x862;
        // Record identifier
        $header = pack('vv', $record, $length);
        $this->append($header . $record_data);
    }
    private static function protection_bits_default_false(?bool $value, int $shift): int
    {
        if ($value === false) {
            return 1 << $shift;
        }
        return 0;
    }
    private static function protection_bits_default_true(?bool $value, int $shift): int
    {
        if ($value !== false) {
            return 1 << $shift;
        }
        return 0;
    }
    /**
     * Write SHEETPROTECTION.
     */
    private function write_sheet_protection(): void
    {
        // record identifier
        $record = 0x867;
        // prepare options
        $protection = $this->php_sheet->get_protection();
        $options = self::protection_bits_default_true($protection->get_objects(), 0) | self::protection_bits_default_true($protection->get_scenarios(), 1) | self::protection_bits_default_false($protection->get_format_cells(), 2) | self::protection_bits_default_false($protection->get_format_columns(), 3) | self::protection_bits_default_false($protection->get_format_rows(), 4) | self::protection_bits_default_false($protection->get_insert_columns(), 5) | self::protection_bits_default_false($protection->get_insert_rows(), 6) | self::protection_bits_default_false($protection->get_insert_hyperlinks(), 7) | self::protection_bits_default_false($protection->get_delete_columns(), 8) | self::protection_bits_default_false($protection->get_delete_rows(), 9) | self::protection_bits_default_true($protection->get_select_locked_cells(), 10) | self::protection_bits_default_false($protection->get_sort(), 11) | self::protection_bits_default_false($protection->get_auto_filter(), 12) | self::protection_bits_default_false($protection->get_pivot_tables(), 13) | self::protection_bits_default_true($protection->get_select_unlocked_cells(), 14);
        // record data
        $record_data = pack(
            'vVVCVVvv',
            0x867,
            // repeated record identifier
            0x0,
            // not used
            0x0,
            // not used
            0x0,
            // not used
            0x1000200,
            // unknown data
            0xffffffff,
            // unknown data
            $options,
            // options
            0x0
        );
        $length = strlen($record_data);
        $header = pack('vv', $record, $length);
        $this->append($header . $record_data);
    }
    /**
     * Write BIFF record RANGEPROTECTION.
     *
     * Openoffice.org's Documentation of the Microsoft Excel File Format uses term RANGEPROTECTION for these records
     * Microsoft Office Excel 97-2007 Binary File Format Specification uses term FEAT for these records
     */
    private function write_range_protection(): void
    {
        foreach ($this->php_sheet->get_protected_cell_ranges() as $range => $protected_cells) {
            $password = $protected_cells->get_password();
            // number of ranges, e.g. 'A1:B3 C20:D25'
            $cell_ranges = explode(' ', (string) $range);
            $cref = count($cell_ranges);
            $record_data = pack('vvVVvCVvVv', 0x868, 0x0, 0x0, 0x0, 0x2, 0x0, 0x0, $cref, 0x0, 0x0);
            foreach ($cell_ranges as $cell_range) {
                $record_data .= $this->write_biff8cell_range_address_fixed($cell_range);
            }
            // the rgbFeat structure
            $record_data .= pack('VV', 0x0, hexdec($password));
            $record_data .= String_Helper::utf8to_biff8unicode_long('p' . md5($record_data));
            $length = strlen($record_data);
            $record = 0x868;
            // Record identifier
            $header = pack('vv', $record, $length);
            $this->append($header . $record_data);
        }
    }
    /**
     * Writes the Excel BIFF PANE record.
     * The panes can either be frozen or thawed (unfrozen).
     * Frozen panes are specified in terms of an integer number of rows and columns.
     * Thawed panes are specified in terms of Excel's units for rows and columns.
     */
    private function write_panes(): void
    {
        if (!$this->php_sheet->get_freeze_pane()) {
            // thaw panes
            return;
        }
        [$column, $row] = Coordinate::indexes_from_string($this->php_sheet->get_freeze_pane());
        $x = $column - 1;
        $y = $row - 1;
        [$left_most_column, $top_row] = Coordinate::indexes_from_string($this->php_sheet->get_top_left_cell() ?? '');
        //Coordinates are zero-based in xls files
        $rw_top = $top_row - 1;
        $col_left = $left_most_column - 1;
        $record = 0x41;
        // Record identifier
        $length = 0xa;
        // Number of bytes to follow
        // Determine which pane should be active. There is also the undocumented
        // option to override this should it be necessary: may be removed later.
        $pnn_act = 0;
        if ($x != 0 && $y != 0) {
            $pnn_act = 0;
            // Bottom right
        }
        if ($x != 0 && $y == 0) {
            $pnn_act = 1;
            // Top right
        }
        if ($x == 0 && $y != 0) {
            $pnn_act = 2;
            // Bottom left
        }
        if ($x == 0 && $y == 0) {
            $pnn_act = 3;
            // Top left
        }
        $this->active_pane = $pnn_act;
        // Used in writeSelection
        $header = pack('vv', $record, $length);
        $data = pack('vvvvv', $x, $y, $rw_top, $col_left, $pnn_act);
        $this->append($header . $data);
    }
    /**
     * Store the page setup SETUP BIFF record.
     */
    private function write_setup(): void
    {
        $record = 0xa1;
        // Record identifier
        $length = 0x22;
        // Number of bytes to follow
        $i_paper_size = $this->php_sheet->get_page_setup()->get_paper_size();
        // Paper size
        $i_scale = $this->php_sheet->get_page_setup()->get_scale() ?: 100;
        // Print scaling factor
        $i_page_start = 0x1;
        // Starting page number
        $i_fit_width = (int) $this->php_sheet->get_page_setup()->get_fit_to_width();
        // Fit to number of pages wide
        $i_fit_height = (int) $this->php_sheet->get_page_setup()->get_fit_to_height();
        // Fit to number of pages high
        $i_res = 0x258;
        // Print resolution
        $i_v_res = 0x258;
        // Vertical print resolution
        $num_hdr = $this->php_sheet->get_page_margins()->get_header();
        // Header Margin
        $num_ftr = $this->php_sheet->get_page_margins()->get_footer();
        // Footer Margin
        $i_copies = 0x1;
        // Number of copies
        // Order of printing pages
        $f_left_to_right = $this->php_sheet->get_page_setup()->get_page_order() === Page_Setup::PAGEORDER_DOWN_THEN_OVER ? 0x0 : 0x1;
        // Page orientation
        $f_landscape = $this->php_sheet->get_page_setup()->get_orientation() == Page_Setup::ORIENTATION_LANDSCAPE ? 0x0 : 0x1;
        $f_no_pls = 0x0;
        // Setup not read from printer
        $f_no_color = 0x0;
        // Print black and white
        $f_draft = 0x0;
        // Print draft quality
        $f_notes = 0x0;
        // Print notes
        $f_no_orient = 0x0;
        // Orientation not set
        $f_use_page = 0x0;
        // Use custom starting page
        $grbit = $f_left_to_right;
        $grbit |= $f_landscape << 1;
        $grbit |= $f_no_pls << 2;
        $grbit |= $f_no_color << 3;
        $grbit |= $f_draft << 4;
        $grbit |= $f_notes << 5;
        $grbit |= $f_no_orient << 6;
        $grbit |= $f_use_page << 7;
        $num_hdr = pack('d', $num_hdr);
        $num_ftr = pack('d', $num_ftr);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $num_hdr = strrev($num_hdr);
            $num_ftr = strrev($num_ftr);
        }
        $header = pack('vv', $record, $length);
        $data1 = pack('vvvvvvvv', $i_paper_size, $i_scale, $i_page_start, $i_fit_width, $i_fit_height, $grbit, $i_res, $i_v_res);
        $data2 = $num_hdr . $num_ftr;
        $data3 = pack('v', $i_copies);
        $this->append($header . $data1 . $data2 . $data3);
    }
    /**
     * Store the header caption BIFF record.
     */
    private function write_header(): void
    {
        $record = 0x14;
        // Record identifier
        /* removing for now
           // need to fix character count (multibyte!)
           if (strlen($this->phpSheet->getHeaderFooter()->getOddHeader()) <= 255) {
               $str      = $this->phpSheet->getHeaderFooter()->getOddHeader();       // header string
           } else {
               $str = '';
           }
           */
        $record_data = String_Helper::utf8to_biff8unicode_long($this->php_sheet->get_header_footer()->get_odd_header());
        $length = strlen($record_data);
        $header = pack('vv', $record, $length);
        $this->append($header . $record_data);
    }
    /**
     * Store the footer caption BIFF record.
     */
    private function write_footer(): void
    {
        $record = 0x15;
        // Record identifier
        /* removing for now
           // need to fix character count (multibyte!)
           if (strlen($this->phpSheet->getHeaderFooter()->getOddFooter()) <= 255) {
               $str = $this->phpSheet->getHeaderFooter()->getOddFooter();
           } else {
               $str = '';
           }
           */
        $record_data = String_Helper::utf8to_biff8unicode_long($this->php_sheet->get_header_footer()->get_odd_footer());
        $length = strlen($record_data);
        $header = pack('vv', $record, $length);
        $this->append($header . $record_data);
    }
    /**
     * Store the horizontal centering HCENTER BIFF record.
     */
    private function write_hcenter(): void
    {
        $record = 0x83;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_h_center = $this->php_sheet->get_page_setup()->get_horizontal_centered() ? 1 : 0;
        // Horizontal centering
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_h_center);
        $this->append($header . $data);
    }
    /**
     * Store the vertical centering VCENTER BIFF record.
     */
    private function write_vcenter(): void
    {
        $record = 0x84;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_v_center = $this->php_sheet->get_page_setup()->get_vertical_centered() ? 1 : 0;
        // Horizontal centering
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_v_center);
        $this->append($header . $data);
    }
    /**
     * Store the LEFTMARGIN BIFF record.
     */
    private function write_margin_left(): void
    {
        $record = 0x26;
        // Record identifier
        $length = 0x8;
        // Bytes to follow
        $margin = $this->php_sheet->get_page_margins()->get_left();
        // Margin in inches
        $header = pack('vv', $record, $length);
        $data = pack('d', $margin);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $data = strrev($data);
        }
        $this->append($header . $data);
    }
    /**
     * Store the RIGHTMARGIN BIFF record.
     */
    private function write_margin_right(): void
    {
        $record = 0x27;
        // Record identifier
        $length = 0x8;
        // Bytes to follow
        $margin = $this->php_sheet->get_page_margins()->get_right();
        // Margin in inches
        $header = pack('vv', $record, $length);
        $data = pack('d', $margin);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $data = strrev($data);
        }
        $this->append($header . $data);
    }
    /**
     * Store the TOPMARGIN BIFF record.
     */
    private function write_margin_top(): void
    {
        $record = 0x28;
        // Record identifier
        $length = 0x8;
        // Bytes to follow
        $margin = $this->php_sheet->get_page_margins()->get_top();
        // Margin in inches
        $header = pack('vv', $record, $length);
        $data = pack('d', $margin);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $data = strrev($data);
        }
        $this->append($header . $data);
    }
    /**
     * Store the BOTTOMMARGIN BIFF record.
     */
    private function write_margin_bottom(): void
    {
        $record = 0x29;
        // Record identifier
        $length = 0x8;
        // Bytes to follow
        $margin = $this->php_sheet->get_page_margins()->get_bottom();
        // Margin in inches
        $header = pack('vv', $record, $length);
        $data = pack('d', $margin);
        if (self::get_byte_order()) {
            // if it's Big Endian
            $data = strrev($data);
        }
        $this->append($header . $data);
    }
    /**
     * Write the PRINTHEADERS BIFF record.
     */
    private function write_print_headers(): void
    {
        $record = 0x2a;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_print_rw_col = $this->print_headers;
        // Boolean flag
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_print_rw_col);
        $this->append($header . $data);
    }
    /**
     * Write the PRINTGRIDLINES BIFF record. Must be used in conjunction with the
     * GRIDSET record.
     */
    private function write_print_gridlines(): void
    {
        $record = 0x2b;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_print_grid = $this->php_sheet->get_print_gridlines() ? 1 : 0;
        // Boolean flag
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_print_grid);
        $this->append($header . $data);
    }
    /**
     * Write the GRIDSET BIFF record. Must be used in conjunction with the
     * PRINTGRIDLINES record.
     */
    private function write_gridset(): void
    {
        $record = 0x82;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_grid_set = !$this->php_sheet->get_print_gridlines();
        // Boolean flag
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_grid_set);
        $this->append($header . $data);
    }
    /**
     * Write the AUTOFILTERINFO BIFF record. This is used to configure the number of autofilter select used in the sheet.
     */
    private function write_auto_filter_info(): void
    {
        $record = 0x9d;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $range_bounds = Coordinate::range_boundaries($this->php_sheet->get_auto_filter()->get_range());
        $i_num_filters = 1 + $range_bounds[1][0] - $range_bounds[0][0];
        $header = pack('vv', $record, $length);
        $data = pack('v', $i_num_filters);
        $this->append($header . $data);
    }
    /**
     * Write the GUTS BIFF record. This is used to configure the gutter margins
     * where Excel outline symbols are displayed. The visibility of the gutters is
     * controlled by a flag in WSBOOL.
     *
     * @see writeWsbool()
     */
    private function write_guts(): void
    {
        $record = 0x80;
        // Record identifier
        $length = 0x8;
        // Bytes to follow
        $dx_rw_gut = 0x0;
        // Size of row gutter
        $dx_col_gut = 0x0;
        // Size of col gutter
        // determine maximum row outline level
        $max_row_outline_level = 0;
        foreach ($this->php_sheet->get_row_dimensions() as $row_dimension) {
            $max_row_outline_level = max($max_row_outline_level, $row_dimension->get_outline_level());
        }
        $col_level = 0;
        // Calculate the maximum column outline level. The equivalent calculation
        // for the row outline level is carried out in writeRow().
        $colcount = count($this->column_info);
        for ($i = 0; $i < $colcount; ++$i) {
            $col_level = max($this->column_info[$i][5], $col_level);
        }
        // Set the limits for the outline levels (0 <= x <= 7).
        $col_level = max(0, min($col_level, 7));
        // The displayed level is one greater than the max outline levels
        if ($max_row_outline_level) {
            ++$max_row_outline_level;
        }
        if ($col_level) {
            ++$col_level;
        }
        $header = pack('vv', $record, $length);
        $data = pack('vvvv', $dx_rw_gut, $dx_col_gut, $max_row_outline_level, $col_level);
        $this->append($header . $data);
    }
    /**
     * Write the WSBOOL BIFF record, mainly for fit-to-page. Used in conjunction
     * with the SETUP record.
     */
    private function write_wsbool(): void
    {
        $record = 0x81;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $grbit = 0x0;
        // The only option that is of interest is the flag for fit to page. So we
        // set all the options in one go.
        //
        // Set the option flags
        $grbit |= 0x1;
        // Auto page breaks visible
        if ($this->outline_style) {
            $grbit |= 0x20;
            // Auto outline styles
        }
        if ($this->php_sheet->get_show_summary_below()) {
            $grbit |= 0x40;
            // Outline summary below
        }
        if ($this->php_sheet->get_show_summary_right()) {
            $grbit |= 0x80;
            // Outline summary right
        }
        if ($this->php_sheet->get_page_setup()->get_fit_to_page()) {
            $grbit |= 0x100;
            // Page setup fit to page
        }
        if ($this->outline_on) {
            $grbit |= 0x400;
            // Outline symbols displayed
        }
        $header = pack('vv', $record, $length);
        $data = pack('v', $grbit);
        $this->append($header . $data);
    }
    /**
     * Write the HORIZONTALPAGEBREAKS and VERTICALPAGEBREAKS BIFF records.
     */
    private function write_breaks(): void
    {
        // initialize
        $vbreaks = [];
        $hbreaks = [];
        foreach ($this->php_sheet->get_row_breaks() as $cell => $break) {
            // Fetch coordinates
            $coordinates = Coordinate::coordinate_from_string($cell);
            $hbreaks[] = $coordinates[1];
        }
        foreach ($this->php_sheet->get_column_breaks() as $cell => $break) {
            // Fetch coordinates
            $coordinates = Coordinate::indexes_from_string($cell);
            $vbreaks[] = $coordinates[0] - 1;
        }
        //horizontal page breaks
        if (!empty($hbreaks)) {
            // Sort and filter array of page breaks
            sort($hbreaks, SORT_NUMERIC);
            if ($hbreaks[0] == 0) {
                // don't use first break if it's 0
                array_shift($hbreaks);
            }
            $record = 0x1b;
            // Record identifier
            $cbrk = count($hbreaks);
            // Number of page breaks
            $length = 2 + 6 * $cbrk;
            // Bytes to follow
            $header = pack('vv', $record, $length);
            $data = pack('v', $cbrk);
            // Append each page break
            foreach ($hbreaks as $hbreak) {
                $data .= pack('vvv', $hbreak, 0x0, 0xff);
            }
            $this->append($header . $data);
        }
        // vertical page breaks
        if (!empty($vbreaks)) {
            // 1000 vertical pagebreaks appears to be an internal Excel 5 limit.
            // It is slightly higher in Excel 97/200, approx. 1026
            $vbreaks = array_slice($vbreaks, 0, 1000);
            // Sort and filter array of page breaks
            sort($vbreaks, SORT_NUMERIC);
            if ($vbreaks[0] == 0) {
                // don't use first break if it's 0
                array_shift($vbreaks);
            }
            $record = 0x1a;
            // Record identifier
            $cbrk = count($vbreaks);
            // Number of page breaks
            $length = 2 + 6 * $cbrk;
            // Bytes to follow
            $header = pack('vv', $record, $length);
            $data = pack('v', $cbrk);
            // Append each page break
            foreach ($vbreaks as $vbreak) {
                $data .= pack('vvv', $vbreak, 0x0, 0xffff);
            }
            $this->append($header . $data);
        }
    }
    /**
     * Set the Biff PROTECT record to indicate that the worksheet is protected.
     */
    private function write_protect(): void
    {
        // Exit unless sheet protection has been specified
        if ($this->php_sheet->get_protection()->get_sheet() !== true) {
            return;
        }
        $record = 0x12;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f_lock = 1;
        // Worksheet is protected
        $header = pack('vv', $record, $length);
        $data = pack('v', $f_lock);
        $this->append($header . $data);
    }
    /**
     * Write SCENPROTECT.
     */
    private function write_scen_protect(): void
    {
        // Exit if sheet protection is not active
        if ($this->php_sheet->get_protection()->get_sheet() !== true) {
            return;
        }
        // Exit if scenarios are not protected
        if ($this->php_sheet->get_protection()->get_scenarios() !== true) {
            return;
        }
        $record = 0xdd;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('v', 1);
        $this->append($header . $data);
    }
    /**
     * Write OBJECTPROTECT.
     */
    private function write_object_protect(): void
    {
        // Exit if sheet protection is not active
        if ($this->php_sheet->get_protection()->get_sheet() !== true) {
            return;
        }
        // Exit if objects are not protected
        if ($this->php_sheet->get_protection()->get_objects() !== true) {
            return;
        }
        $record = 0x63;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('v', 1);
        $this->append($header . $data);
    }
    /**
     * Write the worksheet PASSWORD record.
     */
    private function write_password(): void
    {
        // Exit unless sheet protection and password have been specified
        if ($this->php_sheet->get_protection()->get_sheet() !== true || !$this->php_sheet->get_protection()->get_password() || $this->php_sheet->get_protection()->get_algorithm() !== '') {
            return;
        }
        $record = 0x13;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $w_password = hexdec($this->php_sheet->get_protection()->get_password());
        // Encoded password
        $header = pack('vv', $record, $length);
        $data = pack('v', $w_password);
        $this->append($header . $data);
    }
    /**
     * Insert a 24bit bitmap image in a worksheet.
     *
     * @deprecated 5.5.0 No replacement.
     *
     * @param int $row The row we are going to insert the bitmap into
     * @param int $col The column we are going to insert the bitmap into
     * @param GdImage|string $bitmap The bitmap filename or GD-image resource
     * @param int $x the horizontal position (offset) of the image inside the cell
     * @param int $y the vertical position (offset) of the image inside the cell
     * @param float $scale_x The horizontal scale
     * @param float $scale_y The vertical scale
     *
     * @codeCoverageIgnore
     */
    public function insert_bitmap(int $row, int $col, Gd_Image|string $bitmap, int $x = 0, int $y = 0, float $scale_x = 1, float $scale_y = 1): void
    {
        $bitmap_array = $bitmap instanceof Gd_Image ? $this->process_bitmap_gd($bitmap) : $this->process_bitmap($bitmap);
        [$width, $height, $size, $data] = $bitmap_array;
        // Scale the frame of the image.
        $width *= $scale_x;
        $height *= $scale_y;
        // Calculate the vertices of the image and write the OBJ record
        $this->position_image($col, $row, $x, $y, (int) $width, (int) $height);
        // Write the IMDATA record to store the bitmap data
        $record = 0x7f;
        $length = 8 + $size;
        $cf = 0x9;
        $env = 0x1;
        $lcb = $size;
        $header = pack('vvvvV', $record, $length, $cf, $env, $lcb);
        $this->append($header . $data);
    }
    /**
     * Calculate the vertices that define the position of the image as required by
     * the OBJ record.
     *
     *         +------------+------------+
     *         |     A      |      B     |
     *   +-----+------------+------------+
     *   |     |(x1,y1)     |            |
     *   |  1  |(A1)._______|______      |
     *   |     |    |              |     |
     *   |     |    |              |     |
     *   +-----+----|    BITMAP    |-----+
     *   |     |    |              |     |
     *   |  2  |    |______________.     |
     *   |     |            |        (B2)|
     *   |     |            |     (x2,y2)|
     *   +---- +------------+------------+
     *
     * Example of a bitmap that covers some of the area from cell A1 to cell B2.
     *
     * Based on the width and height of the bitmap we need to calculate 8 vars:
     *     $col_start, $row_start, $col_end, $row_end, $x1, $y1, $x2, $y2.
     * The width and height of the cells are also variable and have to be taken into
     * account.
     * The values of $col_start and $row_start are passed in from the calling
     * function. The values of $col_end and $row_end are calculated by subtracting
     * the width and height of the bitmap from the width and height of the
     * underlying cells.
     * The vertices are expressed as a percentage of the underlying cell width as
     * follows (rhs values are in pixels):
     *
     *       x1 = X / W *1024
     *       y1 = Y / H *256
     *       x2 = (X-1) / W *1024
     *       y2 = (Y-1) / H *256
     *
     *       Where:  X is distance from the left side of the underlying cell
     *               Y is distance from the top of the underlying cell
     *               W is the width of the cell
     *               H is the height of the cell
     * The SDK incorrectly states that the height should be expressed as a
     *        percentage of 1024.
     *
     * @deprecated 5.5.0 No replacement.
     *
     * @param int $col_start Col containing upper left corner of object
     * @param int $row_start Row containing top left corner of object
     * @param int $x1 Distance to left side of object
     * @param int $y1 Distance to top of object
     * @param int $width Width of image frame
     * @param int $height Height of image frame
     *
     * @codeCoverageIgnore
     */
    public function position_image(int $col_start, int $row_start, int $x1, int $y1, int $width, int $height): void
    {
        // Initialise end cell to the same as the start cell
        $col_end = $col_start;
        // Col containing lower right corner of object
        $row_end = $row_start;
        // Row containing bottom right corner of object
        // Zero the specified offset if greater than the cell dimensions
        if ($x1 >= Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_start + 1))) {
            $x1 = 0;
        }
        if ($y1 >= Xls::size_row($this->php_sheet, $row_start + 1)) {
            $y1 = 0;
        }
        $width = $width + $x1 - 1;
        $height = $height + $y1 - 1;
        // Subtract the underlying cell widths to find the end cell of the image
        while ($width >= Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_end + 1))) {
            $width -= Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_end + 1));
            ++$col_end;
        }
        // Subtract the underlying cell heights to find the end cell of the image
        while ($height >= Xls::size_row($this->php_sheet, $row_end + 1)) {
            $height -= Xls::size_row($this->php_sheet, $row_end + 1);
            ++$row_end;
        }
        // Bitmap isn't allowed to start or finish in a hidden cell, i.e. a cell
        // with zero eight or width.
        //
        if (Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_start + 1)) == 0) {
            return;
        }
        if (Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_end + 1)) == 0) {
            return;
        }
        if (Xls::size_row($this->php_sheet, $row_start + 1) == 0) {
            return;
        }
        if (Xls::size_row($this->php_sheet, $row_end + 1) == 0) {
            return;
        }
        // Convert the pixel values to the percentage value expected by Excel
        $x1 = $x1 / Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_start + 1)) * 1024;
        $y1 = $y1 / Xls::size_row($this->php_sheet, $row_start + 1) * 256;
        $x2 = $width / Xls::size_col($this->php_sheet, Coordinate::string_from_column_index($col_end + 1)) * 1024;
        // Distance to right side of object
        $y2 = $height / Xls::size_row($this->php_sheet, $row_end + 1) * 256;
        // Distance to bottom of object
        $this->write_obj_picture($col_start, $x1, $row_start, $y1, $col_end, $x2, $row_end, $y2);
    }
    /**
     * Store the OBJ record that precedes an IMDATA record. This could be generalised
     * to support other Excel objects.
     *
     * @deprecated 5.5.0 No replacement.
     *
     * @param int $colL Column containing upper left corner of object
     * @param int $dxL Distance from left side of cell
     * @param int $rwT Row containing top left corner of object
     * @param float|int $dyT Distance from top of cell
     * @param int $colR Column containing lower right corner of object
     * @param int $dxR Distance from right of cell
     * @param int $rwB Row containing bottom right corner of object
     * @param int $dyB Distance from bottom of cell
     *
     * @codeCoverageIgnore
     */
    private function write_obj_picture(int $col_l, int $dx_l, int $rw_t, int|float $dy_t, int $col_r, int $dx_r, int $rw_b, int $dy_b): void
    {
        $record = 0x5d;
        // Record identifier
        $length = 0x3c;
        // Bytes to follow
        $c_obj = 0x1;
        // Count of objects in file (set to 1)
        $OT = 0x8;
        // Object type. 8 = Picture
        $id = 0x1;
        // Object ID
        $grbit = 0x614;
        // Option flags
        $cb_macro = 0x0;
        // Length of FMLA structure
        $Reserved1 = 0x0;
        // Reserved
        $Reserved2 = 0x0;
        // Reserved
        $icv_back = 0x9;
        // Background colour
        $icv_fore = 0x9;
        // Foreground colour
        $fls = 0x0;
        // Fill pattern
        $f_auto = 0x0;
        // Automatic fill
        $icv = 0x8;
        // Line colour
        $lns = 0xff;
        // Line style
        $lnw = 0x1;
        // Line weight
        $f_auto_b = 0x0;
        // Automatic border
        $frs = 0x0;
        // Frame style
        $cf = 0x9;
        // Image format, 9 = bitmap
        $Reserved3 = 0x0;
        // Reserved
        $cb_pict_fmla = 0x0;
        // Length of FMLA structure
        $Reserved4 = 0x0;
        // Reserved
        $grbit2 = 0x1;
        // Option flags
        $Reserved5 = 0x0;
        // Reserved
        $header = pack('vv', $record, $length);
        $data = pack('V', $c_obj);
        $data .= pack('v', $OT);
        $data .= pack('v', $id);
        $data .= pack('v', $grbit);
        $data .= pack('v', $col_l);
        $data .= pack('v', $dx_l);
        $data .= pack('v', $rw_t);
        $data .= pack('v', $dy_t);
        $data .= pack('v', $col_r);
        $data .= pack('v', $dx_r);
        $data .= pack('v', $rw_b);
        $data .= pack('v', $dy_b);
        $data .= pack('v', $cb_macro);
        $data .= pack('V', $Reserved1);
        $data .= pack('v', $Reserved2);
        $data .= pack('C', $icv_back);
        $data .= pack('C', $icv_fore);
        $data .= pack('C', $fls);
        $data .= pack('C', $f_auto);
        $data .= pack('C', $icv);
        $data .= pack('C', $lns);
        $data .= pack('C', $lnw);
        $data .= pack('C', $f_auto_b);
        $data .= pack('v', $frs);
        $data .= pack('V', $cf);
        $data .= pack('v', $Reserved3);
        $data .= pack('v', $cb_pict_fmla);
        $data .= pack('v', $Reserved4);
        $data .= pack('v', $grbit2);
        $data .= pack('V', $Reserved5);
        $this->append($header . $data);
    }
    /**
     * Convert a GD-image into the internal format.
     *
     * @deprecated 5.5.0 No replacement.
     *
     * @param GdImage $image The image to process
     *
     * @return array{0: int, 1: int, 2: int, 3: string} Data and properties of the bitmap
     *
     * @codeCoverageIgnore
     */
    public function process_bitmap_gd(Gd_Image $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $data = pack('Vvvvv', 0xc, $width, $height, 0x1, 0x18);
        for ($j = $height; --$j;) {
            for ($i = 0; $i < $width; ++$i) {
                $color_at = imagecolorat($image, $i, $j);
                if ($color_at !== false) {
                    $color = imagecolorsforindex($image, $color_at);
                    foreach (['red', 'green', 'blue'] as $key) {
                        $color[$key] = $color[$key] + (int) round((255 - $color[$key]) * $color['alpha'] / 127);
                    }
                    $data .= chr($color['blue']) . chr($color['green']) . chr($color['red']);
                }
            }
            if (3 * $width % 4) {
                $data .= str_repeat("\x00", 4 - 3 * $width % 4);
            }
        }
        return [$width, $height, strlen($data), $data];
    }
    /**
     * Convert a 24 bit bitmap into the modified internal format used by Windows.
     * This is described in BITMAPCOREHEADER and BITMAPCOREINFO structures in the
     * MSDN library.
     *
     * @deprecated 5.5.0 No replacement.
     *
     * @param string $bitmap The bitmap to process
     *
     * @return array{0: int, 1: int, 2: int, 3: string} Data and properties of the bitmap
     *
     * @codeCoverageIgnore
     */
    public function process_bitmap(string $bitmap): array
    {
        // Open file.
        $bmp_fd = @fopen($bitmap, 'rb');
        if ($bmp_fd === false || 0 === (int) filesize($bitmap)) {
            throw new Writer_Exception("Couldn't import {$bitmap}");
        }
        // Slurp the file into a string.
        $data = (string) fread($bmp_fd, (int) filesize($bitmap));
        // Check that the file is big enough to be a bitmap.
        if (strlen($data) <= 0x36) {
            throw new Writer_Exception("{$bitmap} doesn't contain enough data.\n");
        }
        // The first 2 bytes are used to identify the bitmap.
        $identity = unpack('A2ident', $data);
        if ($identity === false || $identity['ident'] != 'BM') {
            throw new Writer_Exception("{$bitmap} doesn't appear to be a valid bitmap image.\n");
        }
        // Remove bitmap data: ID.
        $data = substr($data, 2);
        // Read and remove the bitmap size. This is more reliable than reading
        // the data size at offset 0x22.
        //
        $size_array = unpack('Vsa', substr($data, 0, 4)) ?: [];
        /** @var int */
        $size = $size_array['sa'];
        $data = substr($data, 4);
        $size -= 0x36;
        // Subtract size of bitmap header.
        $size += 0xc;
        // Add size of BIFF header.
        // Remove bitmap data: reserved, offset, header length.
        $data = substr($data, 12);
        // Read and remove the bitmap width and height. Verify the sizes.
        $width_and_height = unpack('V2', substr($data, 0, 8)) ?: [];
        /** @var int */
        $width = $width_and_height[1];
        /** @var int */
        $height = $width_and_height[2];
        $data = substr($data, 8);
        if ($width > 0xffff) {
            throw new Writer_Exception("{$bitmap}: largest image width supported is 65k.\n");
        }
        if ($height > 0xffff) {
            throw new Writer_Exception("{$bitmap}: largest image height supported is 65k.\n");
        }
        // Read and remove the bitmap planes and bpp data. Verify them.
        $planes_and_bitcount = unpack('v2', substr($data, 0, 4));
        $data = substr($data, 4);
        if ($planes_and_bitcount === false || $planes_and_bitcount[2] != 24) {
            // Bitcount
            throw new Writer_Exception("{$bitmap} isn't a 24bit true color bitmap.\n");
        }
        if ($planes_and_bitcount[1] != 1) {
            throw new Writer_Exception("{$bitmap}: only 1 plane supported in bitmap image.\n");
        }
        // Read and remove the bitmap compression. Verify compression.
        $compression = unpack('Vcomp', substr($data, 0, 4));
        $data = substr($data, 4);
        if ($compression === false || $compression['comp'] != 0) {
            throw new Writer_Exception("{$bitmap}: compression not supported in bitmap image.\n");
        }
        // Remove bitmap data: data size, hres, vres, colours, imp. colours.
        $data = substr($data, 20);
        // Add the BITMAPCOREHEADER data
        $header = pack('Vvvvv', 0xc, $width, $height, 0x1, 0x18);
        $data = $header . $data;
        return [$width, $height, $size, $data];
    }
    /**
     * Store the window zoom factor. This should be a reduced fraction but for
     * simplicity we will store all fractions with a numerator of 100.
     */
    private function write_zoom(): void
    {
        // If scale is 100 we don't need to write a record
        if ($this->php_sheet->get_sheet_view()->get_zoom_scale() == 100) {
            return;
        }
        $record = 0xa0;
        // Record identifier
        $length = 0x4;
        // Bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('vv', $this->php_sheet->get_sheet_view()->get_zoom_scale(), 100);
        $this->append($header . $data);
    }
    /**
     * Get Escher object.
     */
    public function get_escher(): ?\Php_Office\Php_Spreadsheet\Shared\Escher
    {
        return $this->escher;
    }
    /**
     * Set Escher object.
     */
    public function set_escher(?\Php_Office\Php_Spreadsheet\Shared\Escher $escher): void
    {
        $this->escher = $escher;
    }
    /**
     * Write MSODRAWING record.
     */
    private function write_mso_drawing(): void
    {
        // write the Escher stream if necessary
        if (isset($this->escher)) {
            $writer = new Escher($this->escher);
            $data = $writer->close();
            $sp_offsets = $writer->get_sp_offsets();
            $sp_types = $writer->get_sp_types();
            // write the neccesary MSODRAWING, OBJ records
            // split the Escher stream
            $sp_offsets[0] = 0;
            $nm = count($sp_offsets) - 1;
            // number of shapes excluding first shape
            for ($i = 1; $i <= $nm; ++$i) {
                // MSODRAWING record
                $record = 0xec;
                // Record identifier
                // chunk of Escher stream for one shape
                $data_chunk = substr($data, $sp_offsets[$i - 1], $sp_offsets[$i] - $sp_offsets[$i - 1]);
                $length = strlen($data_chunk);
                $header = pack('vv', $record, $length);
                $this->append($header . $data_chunk);
                // OBJ record
                $record = 0x5d;
                // record identifier
                $obj_data = '';
                // ftCmo
                if ($sp_types[$i] == 0xc9) {
                    // Add ftCmo (common object data) subobject
                    $obj_data .= pack(
                        'vvvvvVVV',
                        0x15,
                        // 0x0015 = ftCmo
                        0x12,
                        // length of ftCmo data
                        0x14,
                        // object type, 0x0014 = filter
                        $i,
                        // object id number, Excel seems to use 1-based index, local for the sheet
                        0x2101,
                        // option flags, 0x2001 is what OpenOffice.org uses
                        0,
                        // reserved
                        0,
                        // reserved
                        0
                    );
                    // Add ftSbs Scroll bar subobject
                    $obj_data .= pack('vv', 0xc, 0x14);
                    $obj_data .= pack('H*', '0000000000000000640001000A00000010000100');
                    // Add ftLbsData (List box data) subobject
                    $obj_data .= pack('vv', 0x13, 0x1fee);
                    $obj_data .= pack('H*', '00000000010001030000020008005700');
                } else {
                    // Add ftCmo (common object data) subobject
                    $obj_data .= pack(
                        'vvvvvVVV',
                        0x15,
                        // 0x0015 = ftCmo
                        0x12,
                        // length of ftCmo data
                        0x8,
                        // object type, 0x0008 = picture
                        $i,
                        // object id number, Excel seems to use 1-based index, local for the sheet
                        0x6011,
                        // option flags, 0x6011 is what OpenOffice.org uses
                        0,
                        // reserved
                        0,
                        // reserved
                        0
                    );
                }
                // ftEnd
                $obj_data .= pack(
                    'vv',
                    0x0,
                    // 0x0000 = ftEnd
                    0x0
                );
                $length = strlen($obj_data);
                $header = pack('vv', $record, $length);
                $this->append($header . $obj_data);
            }
        }
    }
    /**
     * Store the DATAVALIDATIONS and DATAVALIDATION records.
     */
    private function write_data_validity(): void
    {
        // Datavalidation collection
        $data_validation_collection1 = $this->php_sheet->get_data_validation_collection();
        $data_validation_collection = [];
        foreach ($data_validation_collection1 as $key => $data_validation) {
            $key_parts = explode(' ', (string) $key);
            foreach ($key_parts as $key_part) {
                $data_validation_collection[$key_part] = $data_validation;
            }
        }
        // Write data validations?
        if (!empty($data_validation_collection)) {
            // DATAVALIDATIONS record
            $record = 0x1b2;
            // Record identifier
            $length = 0x12;
            // Bytes to follow
            $grbit = 0x0;
            // Prompt box at cell, no cached validity data at DV records
            $hor_pos = 0x0;
            // Horizontal position of prompt box, if fixed position
            $ver_pos = 0x0;
            // Vertical position of prompt box, if fixed position
            $obj_id = 0xffffffff;
            // Object identifier of drop down arrow object, or -1 if not visible
            $header = pack('vv', $record, $length);
            $data = pack('vVVVV', $grbit, $hor_pos, $ver_pos, $obj_id, count($data_validation_collection));
            $this->append($header . $data);
            // DATAVALIDATION records
            $record = 0x1be;
            // Record identifier
            foreach ($data_validation_collection as $cell_coordinate => $data_validation) {
                // options
                $options = 0x0;
                // data type
                $type = Cell_Data_Validation::type($data_validation);
                $options |= $type << 0;
                // error style
                $error_style = Cell_Data_Validation::error_style($data_validation);
                $options |= $error_style << 4;
                // explicit formula?
                if ($type == 0x3 && Preg::is_match('/^\".*\"$/', $data_validation->get_formula1())) {
                    $options |= 0x1 << 7;
                }
                // empty cells allowed
                $options |= $data_validation->get_allow_blank() << 8;
                // show drop down
                $options |= !$data_validation->get_show_drop_down() << 9;
                // show input message
                $options |= $data_validation->get_show_input_message() << 18;
                // show error message
                $options |= $data_validation->get_show_error_message() << 19;
                // condition operator
                $operator = Cell_Data_Validation::operator($data_validation);
                $options |= $operator << 20;
                $data = pack('V', $options);
                // prompt title
                $prompt_title = $data_validation->get_prompt_title() !== '' ? $data_validation->get_prompt_title() : chr(0);
                $data .= String_Helper::utf8to_biff8unicode_long($prompt_title);
                // error title
                $error_title = $data_validation->get_error_title() !== '' ? $data_validation->get_error_title() : chr(0);
                $data .= String_Helper::utf8to_biff8unicode_long($error_title);
                // prompt text
                $prompt = $data_validation->get_prompt() !== '' ? $data_validation->get_prompt() : chr(0);
                $data .= String_Helper::utf8to_biff8unicode_long($prompt);
                // error text
                $error = $data_validation->get_error() !== '' ? $data_validation->get_error() : chr(0);
                $data .= String_Helper::utf8to_biff8unicode_long($error);
                // formula 1
                try {
                    $formula1 = $data_validation->get_formula1();
                    if ($type == 0x3) {
                        // list type
                        $formula1 = str_replace(',', chr(0), $formula1);
                    }
                    $this->parser->parse($formula1);
                    $formula1 = $this->parser->to_reverse_polish();
                    $sz1 = strlen($formula1);
                } catch (Php_Spreadsheet_Exception) {
                    $sz1 = 0;
                    $formula1 = '';
                }
                $data .= pack('vv', $sz1, 0x0);
                $data .= $formula1;
                // formula 2
                try {
                    $formula2 = $data_validation->get_formula2();
                    if ($formula2 === '') {
                        throw new Writer_Exception('No formula2');
                    }
                    $this->parser->parse($formula2);
                    $formula2 = $this->parser->to_reverse_polish();
                    $sz2 = strlen($formula2);
                } catch (Php_Spreadsheet_Exception) {
                    $sz2 = 0;
                    $formula2 = '';
                }
                $data .= pack('vv', $sz2, 0x0);
                $data .= $formula2;
                // cell range address list
                $data .= pack('v', 0x1);
                $data .= $this->write_biff8cell_range_address_fixed($cell_coordinate);
                $length = strlen($data);
                $header = pack('vv', $record, $length);
                $this->append($header . $data);
            }
        }
    }
    /**
     * Write PLV Record.
     */
    private function write_page_layout_view(): void
    {
        $record = 0x88b;
        // Record identifier
        $length = 0x10;
        // Bytes to follow
        $rt = 0x88b;
        // 2
        $grbit_frt = 0x0;
        // 2
        //$reserved = 0x0000000000000000; // 8
        $w_scalve_plv = $this->php_sheet->get_sheet_view()->get_zoom_scale();
        // 2
        // The options flags that comprise $grbit
        if ($this->php_sheet->get_sheet_view()->get_view() == Sheet_View::SHEETVIEW_PAGE_LAYOUT) {
            $f_page_layout_view = 1;
        } else {
            $f_page_layout_view = 0;
        }
        $f_ruler_visible = 0;
        $f_whitespace_hidden = 0;
        $grbit = $f_page_layout_view;
        // 2
        $grbit |= $f_ruler_visible << 1;
        $grbit |= $f_whitespace_hidden << 3;
        $header = pack('vv', $record, $length);
        $data = pack('vvVVvv', $rt, $grbit_frt, 0x0, 0x0, $w_scalve_plv, $grbit);
        $this->append($header . $data);
    }
    /**
     * Write CFRule Record.
     *
     * @see https://www.openoffice.org/sc/excelfileformat.pdf Search for CFHEADER followed by CFRULE
     */
    private function write_cf_rule(Conditional_Helper $conditional_formula_helper, Conditional $conditional, string $cell_range): void
    {
        $record = 0x1b1;
        // Record identifier
        $type = null;
        // Type of the CF
        $operator_type = null;
        // Comparison operator
        if ($conditional->get_condition_type() == Conditional::CONDITION_EXPRESSION) {
            $type = 0x2;
            $operator_type = 0x0;
        } elseif ($conditional->get_condition_type() == Conditional::CONDITION_CELLIS) {
            $type = 0x1;
            switch ($conditional->get_operator_type()) {
                case Conditional::OPERATOR_NONE:
                    $operator_type = 0x0;
                    break;
                case Conditional::OPERATOR_EQUAL:
                    $operator_type = 0x3;
                    break;
                case Conditional::OPERATOR_GREATERTHAN:
                    $operator_type = 0x5;
                    break;
                case Conditional::OPERATOR_GREATERTHANOREQUAL:
                    $operator_type = 0x7;
                    break;
                case Conditional::OPERATOR_LESSTHAN:
                    $operator_type = 0x6;
                    break;
                case Conditional::OPERATOR_LESSTHANOREQUAL:
                    $operator_type = 0x8;
                    break;
                case Conditional::OPERATOR_NOTEQUAL:
                    $operator_type = 0x4;
                    break;
                case Conditional::OPERATOR_BETWEEN:
                    $operator_type = 0x1;
                    break;
            }
        }
        // $szValue1 : size of the formula data for first value or formula
        // $szValue2 : size of the formula data for second value or formula
        $arr_conditions = $conditional->get_conditions();
        $num_conditions = count($arr_conditions);
        $sz_value1 = 0x0;
        $sz_value2 = 0x0;
        $operand1 = null;
        $operand2 = null;
        if ($num_conditions === 1) {
            $conditional_formula_helper->process_condition($arr_conditions[0], $cell_range);
            $sz_value1 = $conditional_formula_helper->size();
            $operand1 = $conditional_formula_helper->tokens();
        } elseif ($num_conditions === 2 && $conditional->get_operator_type() === Conditional::OPERATOR_BETWEEN) {
            $conditional_formula_helper->process_condition($arr_conditions[0], $cell_range);
            $sz_value1 = $conditional_formula_helper->size();
            $operand1 = $conditional_formula_helper->tokens();
            $conditional_formula_helper->process_condition($arr_conditions[1], $cell_range);
            $sz_value2 = $conditional_formula_helper->size();
            $operand2 = $conditional_formula_helper->tokens();
        }
        // $flags : Option flags
        // Alignment
        /*$bAlignHz = ($conditional->getStyle()->getAlignment()->getHorizontal() === null ? 1 : 0);
          $bAlignVt = ($conditional->getStyle()->getAlignment()->getVertical() === null ? 1 : 0);
          $bAlignWrapTx = ($conditional->getStyle()->getAlignment()->getWrapText() === false ? 1 : 0);
          $bTxRotation = ($conditional->getStyle()->getAlignment()->getTextRotation() === null ? 1 : 0);
          $bIndent = ($conditional->getStyle()->getAlignment()->getIndent() === 0 ? 1 : 0);
          $bShrinkToFit = ($conditional->getStyle()->getAlignment()->getShrinkToFit() === false ? 1 : 0);
          if ($bAlignHz == 0 || $bAlignVt == 0 || $bAlignWrapTx == 0 || $bTxRotation == 0 || $bIndent == 0 || $bShrinkToFit == 0) {
              $bFormatAlign = 1;
          } else {
              $bFormatAlign = 0;
          }*/
        // Protection
        /*$bProtLocked = ($conditional->getStyle()->getProtection()->getLocked() === null ? 1 : 0);
          $bProtHidden = ($conditional->getStyle()->getProtection()->getHidden() === null ? 1 : 0);
          if ($bProtLocked == 0 || $bProtHidden == 0) {
              $bFormatProt = 1;
          } else {
              $bFormatProt = 0;
          }*/
        // Border
        $b_border_left = $conditional->get_style()->get_borders()->get_left()->get_border_style() !== Border::BORDER_OMIT ? 1 : 0;
        $b_border_right = $conditional->get_style()->get_borders()->get_right()->get_border_style() !== Border::BORDER_OMIT ? 1 : 0;
        $b_border_top = $conditional->get_style()->get_borders()->get_top()->get_border_style() !== Border::BORDER_OMIT ? 1 : 0;
        $b_border_bottom = $conditional->get_style()->get_borders()->get_bottom()->get_border_style() !== Border::BORDER_OMIT ? 1 : 0;
        //$diagonalDirection = $conditional->getStyle()->getBorders()->getDiagonalDirection();
        // Excel does not support conditional diagonal border even for xlsx
        $b_border_diag_top = self::$always0;
        //$diagonalDirection === Borders::DIAGONAL_DOWN || $diagonalDirection === Borders::DIAGONAL_BOTH;
        $b_border_diag_bottom = self::$always0;
        //$diagonalDirection === Borders::DIAGONAL_UP || $diagonalDirection === Borders::DIAGONAL_BOTH;
        if ($b_border_left === 1 || $b_border_right === 1 || $b_border_top === 1 || $b_border_bottom === 1 || $b_border_diag_top === 1 || $b_border_diag_bottom === 1) {
            $b_format_border = 1;
        } else {
            $b_format_border = 0;
        }
        // Pattern
        $b_fill_style = $conditional->get_style()->get_fill()->get_fill_type() ? 1 : 0;
        $b_fill_color = $conditional->get_style()->get_fill()->get_start_color()->get_argb() ? 1 : 0;
        $b_fill_color_bg = $conditional->get_style()->get_fill()->get_end_color()->get_argb() ? 1 : 0;
        if ($b_fill_style == 1 || $b_fill_color == 1 || $b_fill_color_bg == 1) {
            $b_format_fill = 1;
        } else {
            $b_format_fill = 0;
        }
        // Font
        if ($conditional->get_style()->get_font()->get_name() !== null || $conditional->get_style()->get_font()->get_size() !== null || $conditional->get_style()->get_font()->get_bold() !== null || $conditional->get_style()->get_font()->get_italic() !== null || $conditional->get_style()->get_font()->get_superscript() !== null || $conditional->get_style()->get_font()->get_subscript() !== null || $conditional->get_style()->get_font()->get_underline() !== null || $conditional->get_style()->get_font()->get_strikethrough() !== null || $conditional->get_style()->get_font()->get_color()->get_argb() !== null) {
            $b_format_font = 1;
        } else {
            $b_format_font = 0;
        }
        // Alignment
        $flags = 0;
        //$flags |= (1 == $bAlignHz ? 0x00000001 : 0);
        //$flags |= (1 == $bAlignVt ? 0x00000002 : 0);
        //$flags |= (1 == $bAlignWrapTx ? 0x00000004 : 0);
        //$flags |= (1 == $bTxRotation ? 0x00000008 : 0);
        // Justify last line flag
        $flags |= 1 == self::$always1 ? 0x10 : 0;
        //$flags |= (1 == $bIndent ? 0x00000020 : 0);
        //$flags |= (1 == $bShrinkToFit ? 0x00000040 : 0);
        // Default
        $flags |= 1 == self::$always1 ? 0x80 : 0;
        // Protection
        //$flags |= (1 == $bProtLocked ? 0x00000100 : 0);
        //$flags |= (1 == $bProtHidden ? 0x00000200 : 0);
        // Border, note that flags are opposite of what you might expect
        $flags |= 0 == $b_border_left ? 0x400 : 0;
        $flags |= 0 == $b_border_right ? 0x800 : 0;
        $flags |= 0 == $b_border_top ? 0x1000 : 0;
        $flags |= 0 == $b_border_bottom ? 0x2000 : 0;
        $flags |= 0 === $b_border_diag_top ? 0x4000 : 0;
        // Top left to Bottom right border
        $flags |= 0 === $b_border_diag_bottom ? 0x8000 : 0;
        // Bottom left to Top right border
        // Pattern
        $flags |= 1 == $b_fill_style ? 0x10000 : 0;
        $flags |= 1 == $b_fill_color ? 0x20000 : 0;
        $flags |= 1 == $b_fill_color_bg ? 0x40000 : 0;
        $flags |= 1 == self::$always1 ? 0x380000 : 0;
        // Font
        $flags |= 1 == $b_format_font ? 0x4000000 : 0;
        // Alignment:
        //$flags |= (1 == $bFormatAlign ? 0x08000000 : 0);
        // Border
        $flags |= 1 == $b_format_border ? 0x10000000 : 0;
        // Pattern
        $flags |= 1 == $b_format_fill ? 0x20000000 : 0;
        // Protection
        //$flags |= (1 == $bFormatProt ? 0x40000000 : 0);
        // Text direction
        $flags |= 1 == self::$always0 ? 0x80000000 : 0;
        $data_block_font = null;
        //$dataBlockAlign = null;
        $data_block_border = null;
        $data_block_fill = null;
        // Data Blocks
        if ($b_format_font == 1) {
            // Font Name
            if ($conditional->get_style()->get_font()->get_name() === null) {
                $data_block_font = pack('VVVVVVVV', 0x0, 0x0, 0x0, 0x0, 0x0, 0x0, 0x0, 0x0);
                $data_block_font .= pack('VVVVVVVV', 0x0, 0x0, 0x0, 0x0, 0x0, 0x0, 0x0, 0x0);
            } else {
                $data_block_font = String_Helper::utf8to_biff8unicode_long($conditional->get_style()->get_font()->get_name());
            }
            // Font Size
            if ($conditional->get_style()->get_font()->get_size() === null) {
                $data_block_font .= pack('V', 20 * 11);
            } else {
                $data_block_font .= pack('V', 20 * $conditional->get_style()->get_font()->get_size());
            }
            // Font Options
            $italic_strike = 0;
            if ($conditional->get_style()->get_font()->get_italic() === true) {
                $italic_strike |= 2;
            }
            if ($conditional->get_style()->get_font()->get_strikethrough() === true) {
                $italic_strike |= 0x80;
            }
            $data_block_font .= pack('V', $italic_strike);
            // Font weight
            if ($conditional->get_style()->get_font()->get_bold() === true) {
                $data_block_font .= pack('v', 0x2bc);
            } elseif ($conditional->get_style()->get_font()->get_bold() === null) {
                $data_block_font .= pack('v', 0x0);
            } else {
                $data_block_font .= pack('v', 0x190);
            }
            // Escapement type
            if ($conditional->get_style()->get_font()->get_subscript() === true) {
                $data_block_font .= pack('v', 0x2);
                $font_escapement = 0;
            } elseif ($conditional->get_style()->get_font()->get_superscript() === true) {
                $data_block_font .= pack('v', 0x1);
                $font_escapement = 0;
            } else {
                $data_block_font .= pack('v', 0x0);
                $font_escapement = 1;
            }
            // Underline type
            switch ($conditional->get_style()->get_font()->get_underline()) {
                case \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_NONE:
                    $data_block_font .= pack('C', 0x0);
                    $font_underline = 0;
                    break;
                case \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_DOUBLE:
                    $data_block_font .= pack('C', 0x2);
                    $font_underline = 0;
                    break;
                case \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_DOUBLEACCOUNTING:
                    $data_block_font .= pack('C', 0x22);
                    $font_underline = 0;
                    break;
                case \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_SINGLE:
                    $data_block_font .= pack('C', 0x1);
                    $font_underline = 0;
                    break;
                case \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_SINGLEACCOUNTING:
                    $data_block_font .= pack('C', 0x21);
                    $font_underline = 0;
                    break;
                default:
                    $data_block_font .= pack('C', 0x0);
                    $font_underline = 1;
                    break;
            }
            // Not used (3)
            $data_block_font .= pack('vC', 0x0, 0x0);
            // Font color index
            $color_idx = $this->workbook_color_index($conditional->get_style()->get_font()->get_color()->get_rgb(), 0);
            $data_block_font .= pack('V', $color_idx);
            // Not used (4)
            $data_block_font .= pack('V', 0x0);
            // Options flags for modified font attributes
            $options_flags = 0;
            $options_flags |= $conditional->get_style()->get_font()->get_bold() === null && $conditional->get_style()->get_font()->get_italic() === null ? 2 : 0;
            $options_flags |= 1 == self::$always1 ? 0x8 : 0;
            $options_flags |= 1 == self::$always1 ? 0x10 : 0;
            $options_flags |= 1 == self::$always0 ? 0x20 : 0;
            $options_flags |= $conditional->get_style()->get_font()->get_strikethrough() === null ? 0x80 : 0;
            $data_block_font .= pack('V', $options_flags);
            // Escapement type
            $data_block_font .= pack('V', $font_escapement);
            // Underline type
            $data_block_font .= pack('V', $font_underline);
            // Always
            $data_block_font .= pack('V', 0x0);
            // Always
            $data_block_font .= pack('V', 0x0);
            // Not used (8)
            $data_block_font .= pack('VV', 0x0, 0x0);
            // Always
            $data_block_font .= pack('v', 0x1);
        }
        /*if ($bFormatAlign === 1) {
                    // Alignment and text break
                    $blockAlign = Style\CellAlignment::horizontal($conditional->getStyle()->getAlignment());
                    $blockAlign |= Style\CellAlignment::wrap($conditional->getStyle()->getAlignment()) << 3;
                    $blockAlign |= Style\CellAlignment::vertical($conditional->getStyle()->getAlignment()) << 4;
                    $blockAlign |= 0 << 7;
        
                    // Text rotation angle
                    $blockRotation = $conditional->getStyle()->getAlignment()->getTextRotation();
        
                    // Indentation
                    $blockIndent = $conditional->getStyle()->getAlignment()->getIndent();
                    if ($conditional->getStyle()->getAlignment()->getShrinkToFit() === true) {
                        $blockIndent |= 1 << 4;
                    } else {
                        $blockIndent |= 0 << 4;
                    }
                    $blockIndent |= 0 << 6;
        
                    // Relative indentation
                    $blockIndentRelative = 255;
        
                    $dataBlockAlign = pack('CCvvv', $blockAlign, $blockRotation, $blockIndent, $blockIndentRelative, 0x0000);
                }*/
        if ($b_format_border === 1) {
            $block_line_style = Style\Cell_Border::style($conditional->get_style()->get_borders()->get_left());
            $block_line_style |= Style\Cell_Border::style($conditional->get_style()->get_borders()->get_right()) << 4;
            $block_line_style |= Style\Cell_Border::style($conditional->get_style()->get_borders()->get_top()) << 8;
            $block_line_style |= Style\Cell_Border::style($conditional->get_style()->get_borders()->get_bottom()) << 12;
            if ($b_border_left !== 0) {
                $color_idx = $this->workbook_color_index($conditional->get_style()->get_borders()->get_left()->get_color()->get_rgb(), 0);
                $block_line_style |= $color_idx << 16;
            }
            if ($b_border_right !== 0) {
                $color_idx = $this->workbook_color_index($conditional->get_style()->get_borders()->get_right()->get_color()->get_rgb(), 0);
                $block_line_style |= $color_idx << 23;
            }
            $block_color = 0;
            if ($b_border_top !== 0) {
                $color_idx = $this->workbook_color_index($conditional->get_style()->get_borders()->get_top()->get_color()->get_rgb(), 0);
                $block_color |= $color_idx;
            }
            if ($b_border_bottom !== 0) {
                $color_idx = $this->workbook_color_index($conditional->get_style()->get_borders()->get_bottom()->get_color()->get_rgb(), 0);
                $block_color |= $color_idx << 7;
            }
            /* Excel does not support condtional diagonal borders even for xlsx
               if ($bBorderDiagTop !== 0 || $bBorderDiagBottom !== 0) {
                   $colorIdx = $this->workbookColorIndex($conditional->getStyle()->getBorders()->getDiagonal()->getColor()->getRgb(), 0);
                   $blockColor |= $colorIdx << 14;
                   $blockColor |= Style\CellBorder::style($conditional->getStyle()->getBorders()->getDiagonal()) << 21;
                   if ($bBorderDiagTop !== 0) {
                       $blockLineStyle |= 1 << 30;
                   }
                   if ($bBorderDiagBottom !== 0) {
                       $blockLineStyle |= 1 << 31;
                   }
               }
               */
            $data_block_border = pack('VV', $block_line_style, $block_color);
        }
        if ($b_format_fill === 1) {
            // Fill Pattern Style
            $block_fill_pattern_style = Style\Cell_Fill::style($conditional->get_style()->get_fill());
            // Background Color
            $color_idx_bg = $this->workbook_color_index($conditional->get_style()->get_fill()->get_start_color()->get_rgb(), 0x41);
            // Foreground Color
            $color_idx_fg = $this->workbook_color_index($conditional->get_style()->get_fill()->get_end_color()->get_rgb(), 0x40);
            $data_block_fill = pack('v', $block_fill_pattern_style);
            $data_block_fill .= pack('v', $color_idx_fg | $color_idx_bg << 7);
        }
        $data = pack('CCvvVv', $type, $operator_type, $sz_value1, $sz_value2, $flags, 0x0);
        if ($b_format_font === 1) {
            // Block Formatting : OK
            $data .= $data_block_font;
        }
        //if ($bFormatAlign === 1) {
        //    $data .= $dataBlockAlign;
        //}
        if ($b_format_border === 1) {
            $data .= $data_block_border;
        }
        if ($b_format_fill === 1) {
            // Block Formatting : OK
            $data .= $data_block_fill;
        }
        //if ($bFormatProt == 1) {
        //    $data .= $this->getDataBlockProtection($conditional);
        //}
        if ($operand1 !== null) {
            $data .= $operand1;
        }
        if ($operand2 !== null) {
            $data .= $operand2;
        }
        $header = pack('vv', $record, strlen($data));
        $this->append($header . $data);
    }
    /**
     * Write CFHeader record.
     *
     * @param Conditional[] $conditionalStyles
     */
    private function write_cf_header(string $cell_coordinate, array $conditional_styles): bool
    {
        $record = 0x1b0;
        // Record identifier
        $length = 0x16;
        // Bytes to follow
        $num_column_min = null;
        $num_column_max = null;
        $num_row_min = null;
        $num_row_max = null;
        $arr_conditional = [];
        foreach ($conditional_styles as $conditional) {
            if (!in_array($conditional->get_hash_code(), $arr_conditional)) {
                $arr_conditional[] = $conditional->get_hash_code();
            }
            // Cells
            $range_coordinates = Coordinate::range_boundaries($cell_coordinate);
            if ($num_column_min === null || $num_column_min > $range_coordinates[0][0]) {
                $num_column_min = $range_coordinates[0][0];
            }
            if ($num_column_max === null || $num_column_max < $range_coordinates[1][0]) {
                $num_column_max = $range_coordinates[1][0];
            }
            if ($num_row_min === null || $num_row_min > $range_coordinates[0][1]) {
                $num_row_min = (int) $range_coordinates[0][1];
            }
            if ($num_row_max === null || $num_row_max < $range_coordinates[1][1]) {
                $num_row_max = (int) $range_coordinates[1][1];
            }
        }
        if (count($arr_conditional) === 0) {
            return false;
        }
        $need_redraw = 1;
        $cell_range = pack('vvvv', $num_row_min - 1, $num_row_max - 1, $num_column_min - 1, $num_column_max - 1);
        $header = pack('vv', $record, $length);
        $data = pack('vv', count($arr_conditional), $need_redraw);
        $data .= $cell_range;
        $data .= pack('v', 0x1);
        $data .= $cell_range;
        $this->append($header . $data);
        return true;
    }
    /*private function getDataBlockProtection(Conditional $conditional): int
        {
            $dataBlockProtection = 0;
            if ($conditional->getStyle()->getProtection()->getLocked() == Protection::PROTECTION_PROTECTED) {
                $dataBlockProtection = 1;
            }
            if ($conditional->getStyle()->getProtection()->getHidden() == Protection::PROTECTION_PROTECTED) {
                $dataBlockProtection = 1 << 1;
            }
    
            return $dataBlockProtection;
        }*/
    private function workbook_color_index(?string $rgb, int $default): int
    {
        return empty($rgb) || $this->writer_workbook === null ? $default : $this->writer_workbook->add_color($rgb, $default);
    }
}