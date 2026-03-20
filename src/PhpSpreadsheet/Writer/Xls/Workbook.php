<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Style;
// Original file header of PEAR::Spreadsheet_Excel_Writer_Workbook (used as the base for this class):
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
class Workbook extends Bif_Fwriter
{
    /*
     * The BIFF file size for the workbook. Not currently used.
     *
     * @see calcSheetOffsets()
     */
    //private int $biffSize;
    /**
     * XF Writers.
     *
     * @var Xf[]
     */
    private array $xf_writers = [];
    /**
     * Array containing the colour palette.
     *
     * @var array<int, array{int, int, int, int}>
     */
    private array $palette;
    /**
     * The codepage indicates the text encoding used for strings.
     */
    private readonly int $codepage;
    /**
     * The country code used for localization.
     */
    private readonly int $country_code;
    /**
     * Fonts writers.
     *
     * @var Font[]
     */
    private array $font_writers = [];
    /**
     * Added fonts. Maps from font's hash => index in workbook.
     *
     * @var int[]
     */
    private array $added_fonts = [];
    /**
     * Shared number formats.
     *
     * @var NumberFormat[]
     */
    private array $number_formats = [];
    /**
     * Added number formats. Maps from numberFormat's hash => index in workbook.
     *
     * @var int[]
     */
    private array $added_number_formats = [];
    /**
     * Sizes of the binary worksheet streams.
     *
     * @var int[]
     */
    private array $worksheet_sizes = [];
    /**
     * Offsets of the binary worksheet streams relative to the start of the global workbook stream.
     *
     * @var int[]
     */
    private array $worksheet_offsets = [];
    /**
     * Total number of shared strings in workbook.
     */
    private readonly int $string_total;
    /**
     * Number of unique shared strings in workbook.
     */
    private readonly int $string_unique;
    /**
     * Array of unique shared strings in workbook.
     *
     * @var array<string, int>
     */
    private readonly array $string_table;
    /**
     * Color cache.
     *
     * @var int[]
     */
    private array $colors;
    /**
     * Escher object corresponding to MSODRAWINGGROUP.
     */
    private ?\Php_Office\Php_Spreadsheet\Shared\Escher $escher = null;
    /**
     * Class constructor.
     *
     * @param Spreadsheet $spreadsheet The Workbook
     * @param int $str_total Total number of strings
     * @param int $str_unique Total number of unique strings
     * @param array<string, int> $str_table String Table
     * @param int[] $colors Colour Table
     * @param Parser $parser The formula parser created for the Workbook
     */
    public function __construct(private readonly Spreadsheet $spreadsheet, int &$str_total, int &$str_unique, array &$str_table, array &$colors, private readonly Parser $parser)
    {
        // It needs to call its parent's constructor explicitly
        parent::__construct();
        //$this->biffSize = 0;
        $this->palette = [];
        $this->country_code = -1;
        $this->string_total =& $str_total;
        $this->string_unique =& $str_unique;
        $this->string_table =& $str_table;
        $this->colors =& $colors;
        $this->set_palette_xl97();
        $this->codepage = 0x4b0;
        // Add empty sheets and Build color cache
        $count_sheets = $this->spreadsheet->get_sheet_count();
        for ($i = 0; $i < $count_sheets; ++$i) {
            $php_sheet = $this->spreadsheet->get_sheet($i);
            $this->parser->set_ext_sheet($php_sheet->get_title(), $i);
            // Register worksheet name with parser
            $supbook_index = 0x0;
            $ref = pack('vvv', $supbook_index, $i, $i);
            $this->parser->references[] = $ref;
            // Register reference with parser
            // Sheet tab colors?
            if ($php_sheet->is_tab_color_set()) {
                $this->add_color($php_sheet->get_tab_color()->get_rgb());
            }
        }
    }
    /**
     * Add a new XF writer.
     *
     * @param bool $isStyleXf Is it a style XF?
     *
     * @return int Index to XF record
     */
    public function add_xf_writer(Style $style, bool $is_style_xf = false): int
    {
        $xf_writer = new Xf($style);
        $xf_writer->set_is_style_xf($is_style_xf);
        // Add the font if not already added
        $font_index = $this->add_font($style->get_font());
        // Assign the font index to the xf record
        $xf_writer->set_font_index($font_index);
        // Background colors, best to treat these after the font so black will come after white in custom palette
        if ($style->get_fill()->get_start_color()->get_rgb()) {
            $xf_writer->set_fg_color($this->add_color($style->get_fill()->get_start_color()->get_rgb()));
        }
        if ($style->get_fill()->get_end_color()->get_rgb()) {
            $xf_writer->set_bg_color($this->add_color($style->get_fill()->get_end_color()->get_rgb()));
        }
        $xf_writer->set_bottom_color($this->add_color($style->get_borders()->get_bottom()->get_color()->get_rgb()));
        $xf_writer->set_top_color($this->add_color($style->get_borders()->get_top()->get_color()->get_rgb()));
        $xf_writer->set_right_color($this->add_color($style->get_borders()->get_right()->get_color()->get_rgb()));
        $xf_writer->set_left_color($this->add_color($style->get_borders()->get_left()->get_color()->get_rgb()));
        $xf_writer->set_diag_color($this->add_color($style->get_borders()->get_diagonal()->get_color()->get_rgb()));
        // Add the number format if it is not a built-in one and not already added
        if ($style->get_number_format()->get_built_in_format_code() === false) {
            $number_format_hash_code = $style->get_number_format()->get_hash_code();
            if (isset($this->added_number_formats[$number_format_hash_code])) {
                $number_format_index = $this->added_number_formats[$number_format_hash_code];
            } else {
                $number_format_index = 164 + count($this->number_formats);
                $this->number_formats[$number_format_index] = $style->get_number_format();
                $this->added_number_formats[$number_format_hash_code] = $number_format_index;
            }
        } else {
            $number_format_index = (int) $style->get_number_format()->get_built_in_format_code();
        }
        // Assign the number format index to xf record
        $xf_writer->set_number_format_index($number_format_index);
        $this->xf_writers[] = $xf_writer;
        return count($this->xf_writers) - 1;
    }
    /**
     * Add a font to added fonts.
     *
     * @return int Index to FONT record
     */
    public function add_font(\Php_Office\Php_Spreadsheet\Style\Font $font): int
    {
        $font_hash_code = $font->get_hash_code();
        if (isset($this->added_fonts[$font_hash_code])) {
            $font_index = $this->added_fonts[$font_hash_code];
        } else {
            $count_fonts = count($this->font_writers);
            $font_index = $count_fonts < 4 ? $count_fonts : $count_fonts + 1;
            $font_writer = new Font($font);
            $font_writer->set_color_index($this->add_color($font->get_color()->get_rgb()));
            $this->font_writers[] = $font_writer;
            $this->added_fonts[$font_hash_code] = $font_index;
        }
        return $font_index;
    }
    /**
     * Alter color palette adding a custom color.
     *
     * @param string $rgb E.g. 'FF00AA'
     *
     * @return int Color index
     */
    public function add_color(string $rgb, int $default = 0): int
    {
        if (!isset($this->colors[$rgb])) {
            $color = [(int) hexdec(substr($rgb, 0, 2)), (int) hexdec(substr($rgb, 2, 2)), (int) hexdec(substr($rgb, 4)), 0];
            $color_index = array_search($color, $this->palette);
            if ($color_index) {
                $this->colors[$rgb] = $color_index;
            } else {
                if (count($this->colors) === 0) {
                    $last_color = 7;
                } else {
                    $last_color = end($this->colors);
                }
                if ($last_color < 57) {
                    // then we add a custom color altering the palette
                    $color_index = $last_color + 1;
                    $this->palette[$color_index] = $color;
                    $this->colors[$rgb] = $color_index;
                } else {
                    // no room for more custom colors, just map to black
                    $color_index = $default;
                }
            }
        } else {
            // fetch already added custom color
            $color_index = $this->colors[$rgb];
        }
        return $color_index;
    }
    /**
     * Sets the colour palette to the Excel 97+ default.
     */
    private function set_palette_xl97(): void
    {
        $this->palette = [0x8 => [0x0, 0x0, 0x0, 0x0], 0x9 => [0xff, 0xff, 0xff, 0x0], 0xa => [0xff, 0x0, 0x0, 0x0], 0xb => [0x0, 0xff, 0x0, 0x0], 0xc => [0x0, 0x0, 0xff, 0x0], 0xd => [0xff, 0xff, 0x0, 0x0], 0xe => [0xff, 0x0, 0xff, 0x0], 0xf => [0x0, 0xff, 0xff, 0x0], 0x10 => [0x80, 0x0, 0x0, 0x0], 0x11 => [0x0, 0x80, 0x0, 0x0], 0x12 => [0x0, 0x0, 0x80, 0x0], 0x13 => [0x80, 0x80, 0x0, 0x0], 0x14 => [0x80, 0x0, 0x80, 0x0], 0x15 => [0x0, 0x80, 0x80, 0x0], 0x16 => [0xc0, 0xc0, 0xc0, 0x0], 0x17 => [0x80, 0x80, 0x80, 0x0], 0x18 => [0x99, 0x99, 0xff, 0x0], 0x19 => [0x99, 0x33, 0x66, 0x0], 0x1a => [0xff, 0xff, 0xcc, 0x0], 0x1b => [0xcc, 0xff, 0xff, 0x0], 0x1c => [0x66, 0x0, 0x66, 0x0], 0x1d => [0xff, 0x80, 0x80, 0x0], 0x1e => [0x0, 0x66, 0xcc, 0x0], 0x1f => [0xcc, 0xcc, 0xff, 0x0], 0x20 => [0x0, 0x0, 0x80, 0x0], 0x21 => [0xff, 0x0, 0xff, 0x0], 0x22 => [0xff, 0xff, 0x0, 0x0], 0x23 => [0x0, 0xff, 0xff, 0x0], 0x24 => [0x80, 0x0, 0x80, 0x0], 0x25 => [0x80, 0x0, 0x0, 0x0], 0x26 => [0x0, 0x80, 0x80, 0x0], 0x27 => [0x0, 0x0, 0xff, 0x0], 0x28 => [0x0, 0xcc, 0xff, 0x0], 0x29 => [0xcc, 0xff, 0xff, 0x0], 0x2a => [0xcc, 0xff, 0xcc, 0x0], 0x2b => [0xff, 0xff, 0x99, 0x0], 0x2c => [0x99, 0xcc, 0xff, 0x0], 0x2d => [0xff, 0x99, 0xcc, 0x0], 0x2e => [0xcc, 0x99, 0xff, 0x0], 0x2f => [0xff, 0xcc, 0x99, 0x0], 0x30 => [0x33, 0x66, 0xff, 0x0], 0x31 => [0x33, 0xcc, 0xcc, 0x0], 0x32 => [0x99, 0xcc, 0x0, 0x0], 0x33 => [0xff, 0xcc, 0x0, 0x0], 0x34 => [0xff, 0x99, 0x0, 0x0], 0x35 => [0xff, 0x66, 0x0, 0x0], 0x36 => [0x66, 0x66, 0x99, 0x0], 0x37 => [0x96, 0x96, 0x96, 0x0], 0x38 => [0x0, 0x33, 0x66, 0x0], 0x39 => [0x33, 0x99, 0x66, 0x0], 0x3a => [0x0, 0x33, 0x0, 0x0], 0x3b => [0x33, 0x33, 0x0, 0x0], 0x3c => [0x99, 0x33, 0x0, 0x0], 0x3d => [0x99, 0x33, 0x66, 0x0], 0x3e => [0x33, 0x33, 0x99, 0x0], 0x3f => [0x33, 0x33, 0x33, 0x0]];
    }
    /**
     * Assemble worksheets into a workbook and send the BIFF data to an OLE
     * storage.
     *
     * @param int[] $worksheetSizes The sizes in bytes of the binary worksheet streams
     *
     * @return string Binary data for workbook stream
     */
    public function write_workbook(array $worksheet_sizes): string
    {
        $this->worksheet_sizes = $worksheet_sizes;
        // Calculate the number of selected worksheet tabs and call the finalization
        // methods for each worksheet
        $total_worksheets = $this->spreadsheet->get_sheet_count();
        // Add part 1 of the Workbook globals, what goes before the SHEET records
        $this->store_bof(0x5);
        $this->write_codepage();
        $this->write_window1();
        $this->write_date_mode();
        $this->write_all_fonts();
        $this->write_all_number_formats();
        $this->write_all_xfs();
        $this->write_all_styles();
        $this->write_palette();
        // Prepare part 3 of the workbook global stream, what goes after the SHEET records
        $part3 = '';
        if ($this->country_code !== -1) {
            $part3 .= $this->write_country();
        }
        $part3 .= $this->write_recalc_id();
        $part3 .= $this->write_supbook_internal();
        /* TODO: store external SUPBOOK records and XCT and CRN records
           in case of external references for BIFF8 */
        $part3 .= $this->write_externalsheet_biff8();
        $part3 .= $this->write_all_defined_names_biff8();
        $part3 .= $this->write_mso_drawing_group();
        $part3 .= $this->write_shared_strings_table();
        $part3 .= $this->write_eof();
        // Add part 2 of the Workbook globals, the SHEET records
        $this->calc_sheet_offsets();
        for ($i = 0; $i < $total_worksheets; ++$i) {
            $this->write_bound_sheet($this->spreadsheet->get_sheet($i), $this->worksheet_offsets[$i]);
        }
        // Add part 3 of the Workbook globals
        $this->_data .= $part3;
        return $this->_data;
    }
    /**
     * Calculate offsets for Worksheet BOF records.
     */
    private function calc_sheet_offsets(): void
    {
        $boundsheet_length = 10;
        // fixed length for a BOUNDSHEET record
        // size of Workbook globals part 1 + 3
        $offset = $this->_datasize;
        // add size of Workbook globals part 2, the length of the SHEET records
        $total_worksheets = count($this->spreadsheet->get_all_sheets());
        foreach ($this->spreadsheet->get_worksheet_iterator() as $sheet) {
            $offset += $boundsheet_length + strlen(String_Helper::utf8to_biff8unicode_short($sheet->get_title()));
        }
        // add the sizes of each of the Sheet substreams, respectively
        for ($i = 0; $i < $total_worksheets; ++$i) {
            $this->worksheet_offsets[$i] = $offset;
            $offset += $this->worksheet_sizes[$i];
        }
        //$this->biffSize = $offset;
    }
    /**
     * Store the Excel FONT records.
     */
    private function write_all_fonts(): void
    {
        foreach ($this->font_writers as $font_writer) {
            $this->append($font_writer->write_font());
        }
    }
    /**
     * Store user defined numerical formats i.e. FORMAT records.
     */
    private function write_all_number_formats(): void
    {
        foreach ($this->number_formats as $number_format_index => $number_format) {
            $this->write_number_format((string) $number_format->get_format_code(), $number_format_index);
        }
    }
    /**
     * Write all XF records.
     */
    private function write_all_xfs(): void
    {
        foreach ($this->xf_writers as $xf_writer) {
            $this->append($xf_writer->write_xf());
        }
    }
    /**
     * Write all STYLE records.
     */
    private function write_all_styles(): void
    {
        $this->write_style();
    }
    private function parse_defined_name_value(Defined_Name $defined_name): string
    {
        $defined_range = $defined_name->get_value();
        $split_count = Preg::match_all_with_offsets('/' . Calculation::CALCULATION_REGEXP_CELLREF . '/mui', $defined_range, $split_ranges);
        $lengths = array_map(String_Helper::strlen_allow_null(...), array_column($split_ranges[0], 0));
        $offsets = array_column($split_ranges[0], 1);
        $worksheets = $split_ranges[2];
        $columns = $split_ranges[6];
        $rows = $split_ranges[7];
        while ($split_count > 0) {
            --$split_count;
            $length = $lengths[$split_count];
            $offset = $offsets[$split_count];
            $worksheet = $worksheets[$split_count][0];
            $column = $columns[$split_count][0];
            $row = $rows[$split_count][0];
            $new_range = '';
            if (empty($worksheet)) {
                if ($offset === 0 || $defined_range[$offset - 1] !== ':') {
                    // We should have a worksheet
                    $worksheet = $defined_name->get_worksheet()?->get_title();
                }
            } else {
                $worksheet = str_replace("''", "'", trim($worksheet, "'"));
            }
            if (!empty($worksheet)) {
                $new_range = "'" . str_replace("'", "''", $worksheet) . "'!";
            }
            if (!empty($column)) {
                $new_range .= "\${$column}";
            }
            if (!empty($row)) {
                $new_range .= "\${$row}";
            }
            $defined_range = substr($defined_range, 0, $offset) . $new_range . substr($defined_range, $offset + $length);
        }
        return $defined_range;
    }
    /**
     * Writes all the DEFINEDNAME records (BIFF8).
     * So far this is only used for repeating rows/columns (print titles) and print areas.
     */
    private function write_all_defined_names_biff8(): string
    {
        $chunk = '';
        // Named ranges
        $defined_names = $this->spreadsheet->get_defined_names();
        // Loop named ranges
        foreach ($defined_names as $defined_name) {
            $range = $this->parse_defined_name_value($defined_name);
            // parse formula
            try {
                $this->parser->parse($range);
                $formula_data = $this->parser->to_reverse_polish();
                // make sure tRef3d is of type tRef3dR (0x3A)
                if (isset($formula_data[0]) && ($formula_data[0] == "z" || $formula_data[0] == "Z")) {
                    $formula_data = ":" . substr($formula_data, 1);
                }
                if ($defined_name->get_local_only()) {
                    // local scope
                    $scope_ws = $defined_name->get_scope();
                    $scope = $scope_ws === null ? 0 : $this->spreadsheet->get_index($scope_ws) + 1;
                } else {
                    // global scope
                    $scope = 0;
                }
                $chunk .= $this->write_data($this->write_defined_name_biff8($defined_name->get_name(), $formula_data, $scope, false));
            } catch (Php_Spreadsheet_Exception) {
                // do nothing
            }
        }
        // total number of sheets
        $total_worksheets = $this->spreadsheet->get_sheet_count();
        // write the print titles (repeating rows, columns), if any
        for ($i = 0; $i < $total_worksheets; ++$i) {
            $sheet_setup = $this->spreadsheet->get_sheet($i)->get_page_setup();
            // simultaneous repeatColumns repeatRows
            if ($sheet_setup->is_columns_to_repeat_at_left_set() && $sheet_setup->is_rows_to_repeat_at_top_set()) {
                $repeat = $sheet_setup->get_columns_to_repeat_at_left();
                $colmin = Coordinate::column_index_from_string($repeat[0]) - 1;
                $colmax = Coordinate::column_index_from_string($repeat[1]) - 1;
                $repeat = $sheet_setup->get_rows_to_repeat_at_top();
                $rowmin = $repeat[0] - 1;
                $rowmax = $repeat[1] - 1;
                // construct formula data manually
                $formula_data = pack('Cv', 0x29, 0x17);
                // tMemFunc
                $formula_data .= pack('Cvvvvv', 0x3b, $i, 0, 65535, $colmin, $colmax);
                // tArea3d
                $formula_data .= pack('Cvvvvv', 0x3b, $i, $rowmin, $rowmax, 0, 255);
                // tArea3d
                $formula_data .= pack('C', 0x10);
                // tList
                // store the DEFINEDNAME record
                $chunk .= $this->write_data($this->write_defined_name_biff8(pack('C', 0x7), $formula_data, $i + 1, true));
            } elseif ($sheet_setup->is_columns_to_repeat_at_left_set() || $sheet_setup->is_rows_to_repeat_at_top_set()) {
                // (exclusive) either repeatColumns or repeatRows.
                // Columns to repeat
                if ($sheet_setup->is_columns_to_repeat_at_left_set()) {
                    $repeat = $sheet_setup->get_columns_to_repeat_at_left();
                    $colmin = Coordinate::column_index_from_string($repeat[0]) - 1;
                    $colmax = Coordinate::column_index_from_string($repeat[1]) - 1;
                } else {
                    $colmin = 0;
                    $colmax = 255;
                }
                // Rows to repeat
                if ($sheet_setup->is_rows_to_repeat_at_top_set()) {
                    $repeat = $sheet_setup->get_rows_to_repeat_at_top();
                    $rowmin = $repeat[0] - 1;
                    $rowmax = $repeat[1] - 1;
                } else {
                    $rowmin = 0;
                    $rowmax = 65535;
                }
                // construct formula data manually because parser does not recognize absolute 3d cell references
                $formula_data = pack('Cvvvvv', 0x3b, $i, $rowmin, $rowmax, $colmin, $colmax);
                // store the DEFINEDNAME record
                $chunk .= $this->write_data($this->write_defined_name_biff8(pack('C', 0x7), $formula_data, $i + 1, true));
            }
        }
        // write the print areas, if any
        for ($i = 0; $i < $total_worksheets; ++$i) {
            $sheet_setup = $this->spreadsheet->get_sheet($i)->get_page_setup();
            if ($sheet_setup->is_print_area_set()) {
                // Print area, e.g. A3:J6,H1:X20
                $print_area = Coordinate::split_range($sheet_setup->get_print_area());
                $count_print_area = count($print_area);
                $formula_data = '';
                for ($j = 0; $j < $count_print_area; ++$j) {
                    $print_area_rect = $print_area[$j];
                    // e.g. A3:J6
                    $print_area_rect[0] = Coordinate::indexes_from_string($print_area_rect[0]);
                    /** @var string */
                    $print_area_rect1 = $print_area_rect[1];
                    $print_area_rect[1] = Coordinate::indexes_from_string($print_area_rect1);
                    $print_rowmin = $print_area_rect[0][1] - 1;
                    $print_rowmax = $print_area_rect[1][1] - 1;
                    $print_colmin = $print_area_rect[0][0] - 1;
                    $print_colmax = $print_area_rect[1][0] - 1;
                    // construct formula data manually because parser does not recognize absolute 3d cell references
                    $formula_data .= pack('Cvvvvv', 0x3b, $i, $print_rowmin, $print_rowmax, $print_colmin, $print_colmax);
                    if ($j > 0) {
                        $formula_data .= pack('C', 0x10);
                        // list operator token ','
                    }
                }
                // store the DEFINEDNAME record
                $chunk .= $this->write_data($this->write_defined_name_biff8(pack('C', 0x6), $formula_data, $i + 1, true));
            }
        }
        // write autofilters, if any
        for ($i = 0; $i < $total_worksheets; ++$i) {
            $sheet_auto_filter = $this->spreadsheet->get_sheet($i)->get_auto_filter();
            $auto_filter_range = $sheet_auto_filter->get_range();
            if (!empty($auto_filter_range)) {
                $range_bounds = Coordinate::range_boundaries($auto_filter_range);
                //Autofilter built in name
                $name = pack('C', 0xd);
                $chunk .= $this->write_data($this->write_short_name_biff8($name, $i + 1, $range_bounds, true));
            }
        }
        return $chunk;
    }
    /**
     * Write a DEFINEDNAME record for BIFF8 using explicit binary formula data.
     *
     * @param string $name The name in UTF-8
     * @param string $formulaData The binary formula data
     * @param int $sheetIndex 1-based sheet index the defined name applies to. 0 = global
     * @param bool $isBuiltIn Built-in name?
     *
     * @return string Complete binary record data
     */
    private function write_defined_name_biff8(string $name, string $formula_data, int $sheet_index = 0, bool $is_built_in = false): string
    {
        $record = 0x18;
        // option flags
        $options = $is_built_in ? 0x20 : 0x0;
        // length of the name, character count
        $nlen = String_Helper::count_characters($name);
        // name with stripped length field
        $name = substr(String_Helper::utf8to_biff8unicode_long($name), 2);
        // size of the formula (in bytes)
        $sz = strlen($formula_data);
        // combine the parts
        $data = pack('vCCvvvCCCC', $options, 0, $nlen, $sz, 0, $sheet_index, 0, 0, 0, 0) . $name . $formula_data;
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        return $header . $data;
    }
    /**
     * Write a short NAME record.
     *
     * @param int $sheetIndex 1-based sheet index the defined name applies to. 0 = global
     * @param int[][] $rangeBounds range boundaries
     *
     * @return string Complete binary record data
     * */
    private function write_short_name_biff8(string $name, int $sheet_index, array $range_bounds, bool $is_hidden = false): string
    {
        $record = 0x18;
        // option flags
        $options = $is_hidden ? 0x21 : 0x0;
        $extra = pack('Cvvvvv', 0x3b, $sheet_index - 1, $range_bounds[0][1] - 1, $range_bounds[1][1] - 1, $range_bounds[0][0] - 1, $range_bounds[1][0] - 1);
        // size of the formula (in bytes)
        $sz = strlen($extra);
        // combine the parts
        $data = pack('vCCvvvCCCCC', $options, 0, 1, $sz, 0, $sheet_index, 0, 0, 0, 0, 0) . $name . $extra;
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        return $header . $data;
    }
    /**
     * Stores the CODEPAGE biff record.
     */
    private function write_codepage(): void
    {
        $record = 0x42;
        // Record identifier
        $length = 0x2;
        // Number of bytes to follow
        $cv = $this->codepage;
        // The code page
        $header = pack('vv', $record, $length);
        $data = pack('v', $cv);
        $this->append($header . $data);
    }
    /**
     * Write Excel BIFF WINDOW1 record.
     */
    private function write_window1(): void
    {
        $record = 0x3d;
        // Record identifier
        $length = 0x12;
        // Number of bytes to follow
        $x_wn = 0x0;
        // Horizontal position of window
        $y_wn = 0x0;
        // Vertical position of window
        $dx_wn = 0x25bc;
        // Width of window
        $dy_wn = 0x1572;
        // Height of window
        $grbit = 0x38;
        // Option flags
        // not supported by PhpSpreadsheet, so there is only one selected sheet, the active
        $ctabsel = 1;
        // Number of workbook tabs selected
        $w_tab_ratio = 0x258;
        // Tab to scrollbar ratio
        // not supported by PhpSpreadsheet, set to 0
        $itab_first = 0;
        // 1st displayed worksheet
        $itab_cur = $this->spreadsheet->get_active_sheet_index();
        // Active worksheet
        $header = pack('vv', $record, $length);
        $data = pack('vvvvvvvvv', $x_wn, $y_wn, $dx_wn, $dy_wn, $grbit, $itab_cur, $itab_first, $ctabsel, $w_tab_ratio);
        $this->append($header . $data);
    }
    /**
     * Writes Excel BIFF BOUNDSHEET record.
     *
     * @param int $offset Location of worksheet BOF
     */
    private function write_bound_sheet(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $sheet, int $offset): void
    {
        $sheetname = $sheet->get_title();
        $record = 0x85;
        // Record identifier
        $ss = match ($sheet->get_sheet_state()) {
            \Php_Office\Php_Spreadsheet\Worksheet\Worksheet::SHEETSTATE_VISIBLE => 0x0,
            \Php_Office\Php_Spreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN => 0x1,
            \Php_Office\Php_Spreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN => 0x2,
            default => 0x0,
        };
        // sheet type
        $st = 0x0;
        //$grbit = 0x0000; // Visibility and sheet type
        $data = pack('VCC', $offset, $ss, $st);
        $data .= String_Helper::utf8to_biff8unicode_short($sheetname);
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        $this->append($header . $data);
    }
    /**
     * Write Internal SUPBOOK record.
     */
    private function write_supbook_internal(): string
    {
        $record = 0x1ae;
        // Record identifier
        $length = 0x4;
        // Bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('vv', $this->spreadsheet->get_sheet_count(), 0x401);
        return $this->write_data($header . $data);
    }
    /**
     * Writes the Excel BIFF EXTERNSHEET record. These references are used by
     * formulas.
     */
    private function write_externalsheet_biff8(): string
    {
        $total_references = count($this->parser->references);
        $record = 0x17;
        // Record identifier
        $length = 2 + 6 * $total_references;
        // Number of bytes to follow
        //$supbook_index = 0; // FIXME: only using internal SUPBOOK record
        $header = pack('vv', $record, $length);
        $data = pack('v', $total_references);
        for ($i = 0; $i < $total_references; ++$i) {
            $data .= $this->parser->references[$i];
        }
        return $this->write_data($header . $data);
    }
    /**
     * Write Excel BIFF STYLE records.
     */
    private function write_style(): void
    {
        $record = 0x293;
        // Record identifier
        $length = 0x4;
        // Bytes to follow
        $ixfe = 0x8000;
        // Index to cell style XF
        $built_in = 0x0;
        // Built-in style
        $i_level = 0xff;
        // Outline style level
        $header = pack('vv', $record, $length);
        $data = pack('vCC', $ixfe, $built_in, $i_level);
        $this->append($header . $data);
    }
    /**
     * Writes Excel FORMAT record for non "built-in" numerical formats.
     *
     * @param string $format Custom format string
     * @param int $ifmt Format index code
     */
    private function write_number_format(string $format, int $ifmt): void
    {
        $record = 0x41e;
        // Record identifier
        $number_format_string = String_Helper::utf8to_biff8unicode_long($format);
        $length = 2 + strlen($number_format_string);
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        $data = pack('v', $ifmt) . $number_format_string;
        $this->append($header . $data);
    }
    /**
     * Write DATEMODE record to indicate the date system in use (1904 or 1900).
     */
    private function write_date_mode(): void
    {
        $record = 0x22;
        // Record identifier
        $length = 0x2;
        // Bytes to follow
        $f1904 = $this->spreadsheet->get_excel_calendar() === Date::CALENDAR_MAC_1904 ? 1 : 0;
        // Flag for 1900 date system
        $header = pack('vv', $record, $length);
        $data = pack('v', $f1904);
        $this->append($header . $data);
    }
    /**
     * Stores the COUNTRY record for localization.
     */
    private function write_country(): string
    {
        $record = 0x8c;
        // Record identifier
        $length = 4;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        // using the same country code always for simplicity
        $data = pack('vv', $this->country_code, $this->country_code);
        return $this->write_data($header . $data);
    }
    /**
     * Write the RECALCID record.
     */
    private function write_recalc_id(): string
    {
        $record = 0x1c1;
        // Record identifier
        $length = 8;
        // Number of bytes to follow
        $header = pack('vv', $record, $length);
        // by inspection of real Excel files, MS Office Excel 2007 writes this
        $data = pack('VV', 0x1c1, 0x1e667);
        return $this->write_data($header . $data);
    }
    /**
     * Stores the PALETTE biff record.
     */
    private function write_palette(): void
    {
        $aref = $this->palette;
        $record = 0x92;
        // Record identifier
        $length = 2 + 4 * count($aref);
        // Number of bytes to follow
        $ccv = count($aref);
        // Number of RGB values to follow
        $data = '';
        // The RGB data
        // Pack the RGB data
        foreach ($aref as $color) {
            foreach ($color as $byte) {
                $data .= pack('C', $byte);
            }
        }
        $header = pack('vvv', $record, $length, $ccv);
        $this->append($header . $data);
    }
    /**
     * Handling of the SST continue blocks is complicated by the need to include an
     * additional continuation byte depending on whether the string is split between
     * blocks or whether it starts at the beginning of the block. (There are also
     * additional complications that will arise later when/if Rich Strings are
     * supported).
     *
     * The Excel documentation says that the SST record should be followed by an
     * EXTSST record. The EXTSST record is a hash table that is used to optimise
     * access to SST. However, despite the documentation it doesn't seem to be
     * required so we will ignore it.
     *
     * @return string Binary data
     */
    private function write_shared_strings_table(): string
    {
        // maximum size of record data (excluding record header)
        $continue_limit = 8224;
        // initialize array of record data blocks
        $record_datas = [];
        // start SST record data block with total number of strings, total number of unique strings
        $record_data = pack('VV', $this->string_total, $this->string_unique);
        // loop through all (unique) strings in shared strings table
        foreach (array_keys($this->string_table) as $string) {
            // here $string is a BIFF8 encoded string
            // length = character count
            $headerinfo = unpack('vlength/Cencoding', $string);
            // currently, this is always 1 = uncompressed
            $encoding = $headerinfo['encoding'] ?? 1;
            // initialize finished writing current $string
            $finished = false;
            while ($finished === false) {
                // normally, there will be only one cycle, but if string cannot immediately be written as is
                // there will be need for more than one cylcle, if string longer than one record data block, there
                // may be need for even more cycles
                if (strlen($record_data) + strlen($string) <= $continue_limit) {
                    // then we can write the string (or remainder of string) without any problems
                    $record_data .= $string;
                    if (strlen($record_data) + strlen($string) == $continue_limit) {
                        // we close the record data block, and initialize a new one
                        $record_datas[] = $record_data;
                        $record_data = '';
                    }
                    // we are finished writing this string
                    $finished = true;
                } else {
                    // special treatment writing the string (or remainder of the string)
                    // If the string is very long it may need to be written in more than one CONTINUE record.
                    // check how many bytes more there is room for in the current record
                    $space_remaining = $continue_limit - strlen($record_data);
                    // minimum space needed
                    // uncompressed: 2 byte string length length field + 1 byte option flags + 2 byte character
                    // compressed:   2 byte string length length field + 1 byte option flags + 1 byte character
                    $min_space_needed = $encoding == 1 ? 5 : 4;
                    // We have two cases
                    // 1. space remaining is less than minimum space needed
                    //        here we must waste the space remaining and move to next record data block
                    // 2. space remaining is greater than or equal to minimum space needed
                    //        here we write as much as we can in the current block, then move to next record data block
                    if ($space_remaining < $min_space_needed) {
                        // 1. space remaining is less than minimum space needed.
                        // we close the block, store the block data
                        $record_datas[] = $record_data;
                        // and start new record data block where we start writing the string
                        $record_data = '';
                    } else {
                        // 2. space remaining is greater than or equal to minimum space needed.
                        // initialize effective remaining space, for Unicode strings this may need to be reduced by 1, see below
                        $effective_space_remaining = $space_remaining;
                        // for uncompressed strings, sometimes effective space remaining is reduced by 1
                        if ($encoding == 1 && (strlen($string) - $space_remaining) % 2 == 1) {
                            --$effective_space_remaining;
                        }
                        // one block fininshed, store the block data
                        $record_data .= substr($string, 0, $effective_space_remaining);
                        $string = substr($string, $effective_space_remaining);
                        // for next cycle in while loop
                        $record_datas[] = $record_data;
                        // start new record data block with the repeated option flags
                        $record_data = pack('C', $encoding);
                    }
                }
            }
        }
        // Store the last record data block unless it is empty
        // if there was no need for any continue records, this will be the for SST record data block itself
        if ($record_data !== '') {
            $record_datas[] = $record_data;
        }
        // combine into one chunk with all the blocks SST, CONTINUE,...
        $chunk = '';
        foreach ($record_datas as $i => $record_data) {
            // first block should have the SST record header, remaining should have CONTINUE header
            $record = $i == 0 ? 0xfc : 0x3c;
            $header = pack('vv', $record, strlen($record_data));
            $data = $header . $record_data;
            $chunk .= $this->write_data($data);
        }
        return $chunk;
    }
    /**
     * Writes the MSODRAWINGGROUP record if needed. Possibly split using CONTINUE records.
     */
    private function write_mso_drawing_group(): string
    {
        // write the Escher stream if necessary
        if (isset($this->escher)) {
            $writer = new Escher($this->escher);
            $data = $writer->close();
            $record = 0xeb;
            $length = strlen($data);
            $header = pack('vv', $record, $length);
            return $this->write_data($header . $data);
        }
        return '';
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
}