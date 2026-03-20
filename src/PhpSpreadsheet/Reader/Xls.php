<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Reader\Xls\Style\Cell_Font;
use Php_Office\Php_Spreadsheet\Reader\Xls\Style\Fill_Pattern;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Code_Page;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Escher;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\OLE;
use Php_Office\Php_Spreadsheet\Shared\Ole_Read;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Worksheet\Sheet_View;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
// Original file header of ParseXL (used as the base for this class):
// --------------------------------------------------------------------------------
// Adapted from Excel_Spreadsheet_Reader developed by users bizon153,
// trex005, and mmp11 (SourceForge.net)
// https://sourceforge.net/projects/phpexcelreader/
// Primary changes made by canyoncasa (dvc) for ParseXL 1.00 ...
//     Modelled moreso after Perl Excel Parse/Write modules
//     Added Parse_Excel_Spreadsheet object
//         Reads a whole worksheet or tab as row,column array or as
//         associated hash of indexed rows and named column fields
//     Added variables for worksheet (tab) indexes and names
//     Added an object call for loading individual woorksheets
//     Changed default indexing defaults to 0 based arrays
//     Fixed date/time and percent formats
//     Includes patches found at SourceForge...
//         unicode patch by nobody
//         unpack("d") machine depedency patch by matchy
//         boundsheet utf16 patch by bjaenichen
//     Renamed functions for shorter names
//     General code cleanup and rigor, including <80 column width
//     Included a testcase Excel file and PHP example calls
//     Code works for PHP 5.x
// Primary changes made by canyoncasa (dvc) for ParseXL 1.10 ...
// http://sourceforge.net/tracker/index.php?func=detail&aid=1466964&group_id=99160&atid=623334
//     Decoding of formula conditions, results, and tokens.
//     Support for user-defined named cells added as an array "namedcells"
//         Patch code for user-defined named cells supports single cells only.
//         NOTE: this patch only works for BIFF8 as BIFF5-7 use a different
//         external sheet reference structure
class Xls extends Xls_Base
{
    /**
     * Summary Information stream data.
     */
    protected ?string $summary_information = null;
    /**
     * Extended Summary Information stream data.
     */
    protected ?string $document_summary_information = null;
    /**
     * Workbook stream data. (Includes workbook globals substream as well as sheet substreams).
     */
    protected string $data;
    /**
     * Size in bytes of $this->data.
     */
    protected int $data_size;
    /**
     * Current position in stream.
     */
    protected int $pos;
    /**
     * Workbook to be returned by the reader.
     */
    protected Spreadsheet $spreadsheet;
    /**
     * Worksheet that is currently being built by the reader.
     */
    protected Worksheet $php_sheet;
    /**
     * BIFF version.
     */
    protected int $version = 0;
    /**
     * Shared formats.
     *
     * @var mixed[]
     */
    protected array $formats;
    /**
     * Shared fonts.
     *
     * @var Font[]
     */
    protected array $obj_fonts;
    /**
     * Color palette.
     *
     * @var string[][]
     */
    protected array $palette;
    /**
     * Worksheets.
     *
     * @var array<array{name: string, offset: int, sheetState: string, sheetType: int|string}>
     */
    protected array $sheets;
    /**
     * External books.
     *
     * @var mixed[][]
     */
    protected array $external_books;
    /**
     * REF structures. Only applies to BIFF8.
     *
     * @var array<int, array{'externalBookIndex': int, 'firstSheetIndex': int, 'lastSheetIndex': int}>
     */
    protected array $ref;
    /**
     * External names.
     *
     * @var array<array<string, mixed>|string>
     */
    protected array $external_names;
    /**
     * Defined names.
     *
     * @var array{isBuiltInName: int, name: string, formula: string, scope: int}
     */
    protected array $definedname;
    /**
     * Shared strings. Only applies to BIFF8.
     *
     * @var array<array{value: string, fmtRuns: mixed[]}>
     */
    protected array $sst;
    /**
     * Panes are frozen? (in sheet currently being read). See WINDOW2 record.
     */
    protected bool $frozen;
    /**
     * Fit printout to number of pages? (in sheet currently being read). See SHEETPR record.
     */
    protected bool $is_fit_to_pages;
    /**
     * Objects. One OBJ record contributes with one entry.
     *
     * @var mixed[]
     */
    protected array $objs;
    /**
     * Text Objects. One TXO record corresponds with one entry.
     *
     * @var array<array{text: string, format: string, alignment: int, rotation: int}>
     */
    protected array $text_objects;
    /**
     * Cell Annotations (BIFF8).
     *
     * @var mixed[]
     */
    protected array $cell_notes;
    /**
     * The combined MSODRAWINGGROUP data.
     */
    protected string $drawing_group_data;
    /**
     * The combined MSODRAWING data (per sheet).
     */
    protected string $drawing_data;
    /**
     * Keep track of XF index.
     */
    protected int $xf_index;
    /**
     * Mapping of XF index (that is a cell XF) to final index in cellXf collection.
     *
     * @var int[]
     */
    protected array $map_cell_xf_index;
    /**
     * Mapping of XF index (that is a style XF) to final index in cellStyleXf collection.
     *
     * @var int[]
     */
    protected array $map_cell_style_xf_index;
    /**
     * The shared formulas in a sheet. One SHAREDFMLA record contributes with one value.
     *
     * @var mixed[]
     */
    protected array $shared_formulas;
    /**
     * The shared formula parts in a sheet. One FORMULA record contributes with one value if it
     * refers to a shared formula.
     *
     * @var mixed[]
     */
    protected array $shared_formula_parts;
    /**
     * The type of encryption in use.
     */
    protected int $encryption = 0;
    /**
     * The position in the stream after which contents are encrypted.
     */
    protected int $encryption_start_pos = 0;
    protected string $encryption_password = 'VelvetSweatshop';
    /**
     * The current RC4 decryption object.
     */
    protected ?Xls\RC4 $rc4Key = null;
    /**
     * The position in the stream that the RC4 decryption object was left at.
     */
    protected int $rc4Pos = 0;
    /**
     * The current MD5 context state.
     * It is set via call-by-reference to verifyPassword.
     */
    private string $md5Ctxt = '';
    protected int $text_obj_ref;
    protected string $base_cell;
    protected bool $active_sheet_set = false;
    /**
     * Reads names of the worksheets from a file, without parsing the whole file to a PhpSpreadsheet object.
     *
     * @return string[]
     */
    public function list_worksheet_names(string $filename): array
    {
        return (new Xls\List_Functions())->list_worksheet_names2($filename, $this);
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        return (new Xls\List_Functions())->list_worksheet_info2($filename, $this);
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, dimensionsMinR: int, dimensionsMinC: int, dimensionsMaxR: int, dimensionsMaxC: int, lastColumnLetter: string}>
     */
    public function list_worksheet_dimensions(string $filename): array
    {
        return (new Xls\List_Functions())->list_worksheet_dimensions2($filename, $this);
    }
    /**
     * Loads PhpSpreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        return (new Xls\Load_Spreadsheet())->load_spreadsheet_from_file2($filename, $this);
    }
    /**
     * Read record data from stream, decrypting as required.
     *
     * @param string $data Data stream to read from
     * @param int $pos Position to start reading from
     * @param int $len Record data length
     *
     * @return string Record data
     */
    protected function read_record_data(string $data, int $pos, int $len): string
    {
        $data = substr($data, $pos, $len);
        // File not encrypted, or record before encryption start point
        if ($this->encryption == self::MS_BIFF_CRYPTO_NONE || $pos < $this->encryption_start_pos) {
            return $data;
        }
        $record_data = '';
        if ($this->encryption == self::MS_BIFF_CRYPTO_RC4) {
            $old_block = floor($this->rc4Pos / self::REKEY_BLOCK);
            $block = (int) floor($pos / self::REKEY_BLOCK);
            $end_block = (int) floor(($pos + $len) / self::REKEY_BLOCK);
            // Spin an RC4 decryptor to the right spot. If we have a decryptor sitting
            // at a point earlier in the current block, re-use it as we can save some time.
            if ($block != $old_block || $pos < $this->rc4Pos || !$this->rc4Key) {
                $this->rc4Key = $this->make_key($block, $this->md5Ctxt);
                $step = $pos % self::REKEY_BLOCK;
            } else {
                $step = $pos - $this->rc4Pos;
            }
            $this->rc4Key->RC4(str_repeat("\x00", $step));
            // Decrypt record data (re-keying at the end of every block)
            while ($block != $end_block) {
                $step = self::REKEY_BLOCK - $pos % self::REKEY_BLOCK;
                $record_data .= $this->rc4Key->RC4(substr($data, 0, $step));
                $data = substr($data, $step);
                $pos += $step;
                $len -= $step;
                ++$block;
                $this->rc4Key = $this->make_key($block, $this->md5Ctxt);
            }
            $record_data .= $this->rc4Key->RC4(substr($data, 0, $len));
            // Keep track of the position of this decryptor.
            // We'll try and re-use it later if we can to speed things up
            $this->rc4Pos = $pos + $len;
        } elseif ($this->encryption == self::MS_BIFF_CRYPTO_XOR) {
            throw new Exception('XOr encryption not supported');
        }
        return $record_data;
    }
    /**
     * Use OLE reader to extract the relevant data streams from the OLE file.
     */
    protected function load_ole(string $filename): void
    {
        // OLE reader
        $ole = new Ole_Read();
        // get excel data,
        $ole->read($filename);
        // Get workbook data: workbook stream + sheet streams
        $this->data = $ole->get_stream($ole->wrkbook);
        // @phpstan-ignore-line
        // Get summary information data
        $this->summary_information = $ole->get_stream($ole->summary_information);
        // Get additional document summary information data
        $this->document_summary_information = $ole->get_stream($ole->document_summary_information);
    }
    /**
     * Read summary information.
     */
    protected function read_summary_information(): void
    {
        if (!isset($this->summary_information)) {
            return;
        }
        // offset: 0; size: 2; must be 0xFE 0xFF (UTF-16 LE byte order mark)
        // offset: 2; size: 2;
        // offset: 4; size: 2; OS version
        // offset: 6; size: 2; OS indicator
        // offset: 8; size: 16
        // offset: 24; size: 4; section count
        //$secCount = self::getInt4d($this->summaryInformation, 24);
        // offset: 28; size: 16; first section's class id: e0 85 9f f2 f9 4f 68 10 ab 91 08 00 2b 27 b3 d9
        // offset: 44; size: 4
        $sec_offset = self::get_int4d($this->summary_information, 44);
        // section header
        // offset: $secOffset; size: 4; section length
        //$secLength = self::getInt4d($this->summaryInformation, $secOffset);
        // offset: $secOffset+4; size: 4; property count
        $count_properties = self::get_int4d($this->summary_information, $sec_offset + 4);
        // initialize code page (used to resolve string values)
        $code_page = 'CP1252';
        // offset: ($secOffset+8); size: var
        // loop through property decarations and properties
        for ($i = 0; $i < $count_properties; ++$i) {
            // offset: ($secOffset+8) + (8 * $i); size: 4; property ID
            $id = self::get_int4d($this->summary_information, $sec_offset + 8 + 8 * $i);
            // Use value of property id as appropriate
            // offset: ($secOffset+12) + (8 * $i); size: 4; offset from beginning of section (48)
            $offset = self::get_int4d($this->summary_information, $sec_offset + 12 + 8 * $i);
            $type = self::get_int4d($this->summary_information, $sec_offset + $offset);
            // initialize property value
            $value = null;
            // extract property value based on property type
            switch ($type) {
                case 0x2:
                    // 2 byte signed integer
                    $value = self::get_u_int2d($this->summary_information, $sec_offset + 4 + $offset);
                    break;
                case 0x3:
                    // 4 byte signed integer
                    $value = self::get_int4d($this->summary_information, $sec_offset + 4 + $offset);
                    break;
                case 0x13:
                    // 4 byte unsigned integer
                    // not needed yet, fix later if necessary
                    break;
                case 0x1e:
                    // null-terminated string prepended by dword string length
                    $byte_length = self::get_int4d($this->summary_information, $sec_offset + 4 + $offset);
                    $value = substr($this->summary_information, $sec_offset + 8 + $offset, $byte_length);
                    $value = String_Helper::convert_encoding($value, 'UTF-8', $code_page);
                    $value = rtrim($value);
                    break;
                case 0x40:
                    // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                    // PHP-time
                    $value = OLE::ole2local_date(substr($this->summary_information, $sec_offset + 4 + $offset, 8));
                    break;
                case 0x47:
                    // Clipboard format
                    // not needed yet, fix later if necessary
                    break;
            }
            switch ($id) {
                case 0x1:
                    //    Code Page
                    $code_page = Code_Page::number_to_name((int) $value);
                    break;
                case 0x2:
                    //    Title
                    $this->spreadsheet->get_properties()->set_title("{$value}");
                    break;
                case 0x3:
                    //    Subject
                    $this->spreadsheet->get_properties()->set_subject("{$value}");
                    break;
                case 0x4:
                    //    Author (Creator)
                    $this->spreadsheet->get_properties()->set_creator("{$value}");
                    break;
                case 0x5:
                    //    Keywords
                    $this->spreadsheet->get_properties()->set_keywords("{$value}");
                    break;
                case 0x6:
                    //    Comments (Description)
                    $this->spreadsheet->get_properties()->set_description("{$value}");
                    break;
                case 0x7:
                    //    Template
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x8:
                    //    Last Saved By (LastModifiedBy)
                    $this->spreadsheet->get_properties()->set_last_modified_by("{$value}");
                    break;
                case 0x9:
                    //    Revision
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xa:
                    //    Total Editing Time
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xb:
                    //    Last Printed
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xc:
                    //    Created Date/Time
                    $this->spreadsheet->get_properties()->set_created($value);
                    break;
                case 0xd:
                    //    Modified Date/Time
                    $this->spreadsheet->get_properties()->set_modified($value);
                    break;
                case 0xe:
                    //    Number of Pages
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xf:
                    //    Number of Words
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x10:
                    //    Number of Characters
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x11:
                    //    Thumbnail
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x12:
                    //    Name of creating application
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x13:
                    //    Security
                    //    Not supported by PhpSpreadsheet
                    break;
            }
        }
    }
    /**
     * Read additional document summary information.
     */
    protected function read_document_summary_information(): void
    {
        if (!isset($this->document_summary_information)) {
            return;
        }
        //    offset: 0;    size: 2;    must be 0xFE 0xFF (UTF-16 LE byte order mark)
        //    offset: 2;    size: 2;
        //    offset: 4;    size: 2;    OS version
        //    offset: 6;    size: 2;    OS indicator
        //    offset: 8;    size: 16
        //    offset: 24;    size: 4;    section count
        //$secCount = self::getInt4d($this->documentSummaryInformation, 24);
        // offset: 28;    size: 16;    first section's class id: 02 d5 cd d5 9c 2e 1b 10 93 97 08 00 2b 2c f9 ae
        // offset: 44;    size: 4;    first section offset
        $sec_offset = self::get_int4d($this->document_summary_information, 44);
        //    section header
        //    offset: $secOffset;    size: 4;    section length
        //$secLength = self::getInt4d($this->documentSummaryInformation, $secOffset);
        //    offset: $secOffset+4;    size: 4;    property count
        $count_properties = self::get_int4d($this->document_summary_information, $sec_offset + 4);
        // initialize code page (used to resolve string values)
        $code_page = 'CP1252';
        //    offset: ($secOffset+8);    size: var
        //    loop through property decarations and properties
        for ($i = 0; $i < $count_properties; ++$i) {
            //    offset: ($secOffset+8) + (8 * $i);    size: 4;    property ID
            $id = self::get_int4d($this->document_summary_information, $sec_offset + 8 + 8 * $i);
            // Use value of property id as appropriate
            // offset: 60 + 8 * $i;    size: 4;    offset from beginning of section (48)
            $offset = self::get_int4d($this->document_summary_information, $sec_offset + 12 + 8 * $i);
            $type = self::get_int4d($this->document_summary_information, $sec_offset + $offset);
            // initialize property value
            $value = null;
            // extract property value based on property type
            switch ($type) {
                case 0x2:
                    //    2 byte signed integer
                    $value = self::get_u_int2d($this->document_summary_information, $sec_offset + 4 + $offset);
                    break;
                case 0x3:
                    //    4 byte signed integer
                    $value = self::get_int4d($this->document_summary_information, $sec_offset + 4 + $offset);
                    break;
                case 0xb:
                    // Boolean
                    $value = self::get_u_int2d($this->document_summary_information, $sec_offset + 4 + $offset);
                    $value = $value == 0 ? false : true;
                    break;
                case 0x13:
                    //    4 byte unsigned integer
                    // not needed yet, fix later if necessary
                    break;
                case 0x1e:
                    //    null-terminated string prepended by dword string length
                    $byte_length = self::get_int4d($this->document_summary_information, $sec_offset + 4 + $offset);
                    $value = substr($this->document_summary_information, $sec_offset + 8 + $offset, $byte_length);
                    $value = String_Helper::convert_encoding($value, 'UTF-8', $code_page);
                    $value = rtrim($value);
                    break;
                case 0x40:
                    //    Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                    // PHP-Time
                    $value = OLE::ole2local_date(substr($this->document_summary_information, $sec_offset + 4 + $offset, 8));
                    break;
                case 0x47:
                    //    Clipboard format
                    // not needed yet, fix later if necessary
                    break;
            }
            switch ($id) {
                case 0x1:
                    //    Code Page
                    $code_page = Code_Page::number_to_name((int) $value);
                    break;
                case 0x2:
                    //    Category
                    $this->spreadsheet->get_properties()->set_category("{$value}");
                    break;
                case 0x3:
                    //    Presentation Target
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x4:
                    //    Bytes
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x5:
                    //    Lines
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x6:
                    //    Paragraphs
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x7:
                    //    Slides
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x8:
                    //    Notes
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0x9:
                    //    Hidden Slides
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xa:
                    //    MM Clips
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xb:
                    //    Scale Crop
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xc:
                    //    Heading Pairs
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xd:
                    //    Titles of Parts
                    //    Not supported by PhpSpreadsheet
                    break;
                case 0xe:
                    //    Manager
                    $this->spreadsheet->get_properties()->set_manager("{$value}");
                    break;
                case 0xf:
                    //    Company
                    $this->spreadsheet->get_properties()->set_company("{$value}");
                    break;
                case 0x10:
                    //    Links up-to-date
                    //    Not supported by PhpSpreadsheet
                    break;
            }
        }
    }
    /**
     * Reads a general type of BIFF record. Does nothing except for moving stream pointer forward to next record.
     */
    protected function read_default(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        // move stream pointer to next record
        $this->pos += 4 + $length;
    }
    /**
     *    The NOTE record specifies a comment associated with a particular cell. In Excel 95 (BIFF7) and earlier versions,
     *        this record stores a note (cell note). This feature was significantly enhanced in Excel 97.
     */
    protected function read_note(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        $cell_address = Xls\Biff8::read_biff8cell_address(substr($record_data, 0, 4));
        if ($this->version == self::XLS_BIFF8) {
            $note_obj_id = self::get_u_int2d($record_data, 6);
            $note_author = self::read_unicode_string_long(substr($record_data, 8));
            $note_author = $note_author['value'];
            $this->cell_notes[$note_obj_id] = ['cellRef' => $cell_address, 'objectID' => $note_obj_id, 'author' => $note_author];
        } else {
            $extension = false;
            if ($cell_address === '$B$' . Address_Range::MAX_ROW_XLS) {
                //    If the address row is -1 and the column is 0, (which translates as $B$65536) then this is a continuation
                //        note from the previous cell annotation. We're not yet handling this, so annotations longer than the
                //        max 2048 bytes will probably throw a wobbly.
                //$row = self::getUInt2d($recordData, 0);
                $extension = true;
                $array_keys = array_keys($this->php_sheet->get_comments());
                $cell_address = array_pop($array_keys);
            }
            $cell_address = str_replace('$', '', (string) $cell_address);
            //$noteLength = self::getUInt2d($recordData, 4);
            $note_text = trim(substr($record_data, 6));
            if ($extension) {
                //    Concatenate this extension with the currently set comment for the cell
                $comment = $this->php_sheet->get_comment($cell_address);
                $comment_text = $comment->get_text()->get_plain_text();
                $comment->set_text($this->parse_rich_text($comment_text . $note_text));
            } else {
                //    Set comment for the cell
                $this->php_sheet->get_comment($cell_address)->set_text($this->parse_rich_text($note_text));
                //                                                    ->setAuthor($author)
            }
        }
    }
    /**
     * The TEXT Object record contains the text associated with a cell annotation.
     */
    protected function read_text_object(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        // recordData consists of an array of subrecords looking like this:
        //    grbit: 2 bytes; Option Flags
        //    rot: 2 bytes; rotation
        //    cchText: 2 bytes; length of the text (in the first continue record)
        //    cbRuns: 2 bytes; length of the formatting (in the second continue record)
        // followed by the continuation records containing the actual text and formatting
        $grbit_opts = self::get_u_int2d($record_data, 0);
        $rot = self::get_u_int2d($record_data, 2);
        //$cchText = self::getUInt2d($recordData, 10);
        $cb_runs = self::get_u_int2d($record_data, 12);
        $text = $this->get_spliced_record_data();
        /** @var int[] */
        $temp_splice = $text['spliceOffsets'];
        /** @var int */
        $temp = $temp_splice[0];
        /** @var int */
        $temp1 = $temp_splice[1];
        $text_byte = $temp1 - $temp - 1;
        /** @var string */
        $text_record_data = $text['recordData'];
        $text_str = substr($text_record_data, $temp + 1, $text_byte);
        // get 1 byte
        $is16Bit = ord($text_record_data[0]);
        // it is possible to use a compressed format,
        // which omits the high bytes of all characters, if they are all zero
        if (($is16Bit & 0x1) === 0) {
            $text_str = String_Helper::convert_encoding($text_str, 'UTF-8', 'ISO-8859-1');
        } else {
            $text_str = $this->decode_codepage($text_str);
        }
        $this->text_objects[$this->text_obj_ref] = ['text' => $text_str, 'format' => substr($text_record_data, $temp_splice[1], $cb_runs), 'alignment' => $grbit_opts, 'rotation' => $rot];
    }
    /**
     * Read BOF.
     */
    protected function read_bof(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = substr($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 2; size: 2; type of the following data
        $substream_type = self::get_u_int2d($record_data, 2);
        switch ($substream_type) {
            case self::XLS_WORKBOOKGLOBALS:
                $version = self::get_u_int2d($record_data, 0);
                if ($version != self::XLS_BIFF8 && $version != self::XLS_BIFF7) {
                    throw new Exception('Cannot read this Excel file. Version is too old.');
                }
                $this->version = $version;
                break;
            case self::XLS_WORKSHEET:
                // do not use this version information for anything
                // it is unreliable (OpenOffice doc, 5.8), use only version information from the global stream
                break;
            default:
                // substream, e.g. chart
                // just skip the entire substream
                do {
                    $code = self::get_u_int2d($this->data, $this->pos);
                    $this->read_default();
                } while ($code != self::XLS_TYPE_EOF && $this->pos < $this->data_size);
                break;
        }
    }
    public function set_encryption_password(string $encryption_password): self
    {
        $this->encryption_password = $encryption_password;
        return $this;
    }
    /**
     * FILEPASS.
     *
     * This record is part of the File Protection Block. It
     * contains information about the read/write password of the
     * file. All record contents following this record will be
     * encrypted.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     *
     * The decryption functions and objects used from here on in
     * are based on the source of Spreadsheet-ParseExcel:
     * https://metacpan.org/release/Spreadsheet-ParseExcel
     */
    protected function read_filepass(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        if ($length < 54) {
            throw new Exception('Unexpected file pass record length');
        }
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!str_starts_with($record_data, "\x01\x00") || substr($record_data, 4, 2) !== "\x01\x00") {
            throw new Exception('Unsupported encryption algorithm');
        }
        if (!$this->verify_password($this->encryption_password, substr($record_data, 6, 16), substr($record_data, 22, 16), substr($record_data, 38, 16), $this->md5Ctxt)) {
            throw new Exception('Decryption password incorrect');
        }
        $this->encryption = self::MS_BIFF_CRYPTO_RC4;
        // Decryption required from the record after next onwards
        $this->encryption_start_pos = $this->pos + self::get_u_int2d($this->data, $this->pos + 2);
    }
    /**
     * Make an RC4 decryptor for the given block.
     *
     * @param int $block Block for which to create decrypto
     * @param string $valContext MD5 context state
     */
    private function make_key(int $block, string $val_context): Xls\RC4
    {
        $pwarray = str_repeat("\x00", 64);
        for ($i = 0; $i < 5; ++$i) {
            $pwarray[$i] = $val_context[$i];
        }
        $pwarray[5] = chr($block & 0xff);
        $pwarray[6] = chr($block >> 8 & 0xff);
        $pwarray[7] = chr($block >> 16 & 0xff);
        $pwarray[8] = chr($block >> 24 & 0xff);
        $pwarray[9] = "\x80";
        $pwarray[56] = "H";
        $md5 = new Xls\MD5();
        $md5->add($pwarray);
        $s = $md5->get_context();
        return new Xls\RC4($s);
    }
    /**
     * Verify RC4 file password.
     *
     * @param string $password Password to check
     * @param string $docid Document id
     * @param string $salt_data Salt data
     * @param string $hashedsalt_data Hashed salt data
     * @param string $valContext Set to the MD5 context of the value
     *
     * @return bool Success
     */
    private function verify_password(string $password, string $docid, string $salt_data, string $hashedsalt_data, string &$val_context): bool
    {
        $pwarray = str_repeat("\x00", 64);
        $i_max = strlen($password);
        for ($i = 0; $i < $i_max; ++$i) {
            $o = ord(substr($password, $i, 1));
            $pwarray[2 * $i] = chr($o & 0xff);
            $pwarray[2 * $i + 1] = chr($o >> 8 & 0xff);
        }
        $pwarray[2 * $i] = chr(0x80);
        $pwarray[56] = chr($i << 4 & 0xff);
        $md5 = new Xls\MD5();
        $md5->add($pwarray);
        $md_context1 = $md5->get_context();
        $offset = 0;
        $keyoffset = 0;
        $tocopy = 5;
        $md5->reset();
        while ($offset != 16) {
            if (64 - $offset < 5) {
                $tocopy = 64 - $offset;
            }
            for ($i = 0; $i <= $tocopy; ++$i) {
                $pwarray[$offset + $i] = $md_context1[$keyoffset + $i];
            }
            $offset += $tocopy;
            if ($offset == 64) {
                $md5->add($pwarray);
                $keyoffset = $tocopy;
                $tocopy = 5 - $tocopy;
                $offset = 0;
                continue;
            }
            $keyoffset = 0;
            $tocopy = 5;
            for ($i = 0; $i < 16; ++$i) {
                $pwarray[$offset + $i] = $docid[$i];
            }
            $offset += 16;
        }
        $pwarray[16] = "\x80";
        for ($i = 0; $i < 47; ++$i) {
            $pwarray[17 + $i] = "\x00";
        }
        $pwarray[56] = "\x80";
        $pwarray[57] = "\n";
        $md5->add($pwarray);
        $val_context = $md5->get_context();
        $key = $this->make_key(0, $val_context);
        $salt = $key->RC4($salt_data);
        $hashedsalt = $key->RC4($hashedsalt_data);
        $salt .= "\x80" . str_repeat("\x00", 47);
        $salt[56] = "\x80";
        $md5->reset();
        $md5->add($salt);
        $md_context2 = $md5->get_context();
        return $md_context2 == $hashedsalt;
    }
    /**
     * CODEPAGE.
     *
     * This record stores the text encoding used to write byte
     * strings, stored as MS Windows code page identifier.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_codepage(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; code page identifier
        $codepage = self::get_u_int2d($record_data, 0);
        $this->codepage = Code_Page::number_to_name($codepage);
    }
    /**
     * DATEMODE.
     *
     * This record specifies the base date for displaying date
     * values. All dates are stored as count of days past this
     * base date. In BIFF2-BIFF4 this record is part of the
     * Calculation Settings Block. In BIFF5-BIFF8 it is
     * stored in the Workbook Globals Substream.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_date_mode(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; 0 = base 1900, 1 = base 1904
        Date::set_excel_calendar(Date::CALENDAR_WINDOWS_1900);
        $this->spreadsheet->set_excel_calendar(Date::CALENDAR_WINDOWS_1900);
        if (ord($record_data[0]) == 1) {
            Date::set_excel_calendar(Date::CALENDAR_MAC_1904);
            $this->spreadsheet->set_excel_calendar(Date::CALENDAR_MAC_1904);
        }
    }
    /**
     * Read a FONT record.
     */
    protected function read_font(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            $obj_font = new Font();
            // offset: 0; size: 2; height of the font (in twips = 1/20 of a point)
            $size = self::get_u_int2d($record_data, 0);
            $obj_font->set_size($size / 20);
            // offset: 2; size: 2; option flags
            // bit: 0; mask 0x0001; bold (redundant in BIFF5-BIFF8)
            // bit: 1; mask 0x0002; italic
            $is_italic = (0x2 & self::get_u_int2d($record_data, 2)) >> 1;
            if ($is_italic) {
                $obj_font->set_italic(true);
            }
            // bit: 2; mask 0x0004; underlined (redundant in BIFF5-BIFF8)
            // bit: 3; mask 0x0008; strikethrough
            $is_strike = (0x8 & self::get_u_int2d($record_data, 2)) >> 3;
            if ($is_strike) {
                $obj_font->set_strikethrough(true);
            }
            // offset: 4; size: 2; colour index
            $color_index = self::get_u_int2d($record_data, 4);
            $obj_font->color_index = $color_index;
            // offset: 6; size: 2; font weight
            $weight = self::get_u_int2d($record_data, 6);
            // regular=400 bold=700
            if ($weight >= 550) {
                $obj_font->set_bold(true);
            }
            // offset: 8; size: 2; escapement type
            $escapement = self::get_u_int2d($record_data, 8);
            Cell_Font::escapement($obj_font, $escapement);
            // offset: 10; size: 1; underline type
            $underline_type = ord($record_data[10]);
            Cell_Font::underline($obj_font, $underline_type);
            // offset: 11; size: 1; font family
            // offset: 12; size: 1; character set
            // offset: 13; size: 1; not used
            // offset: 14; size: var; font name
            if ($this->version == self::XLS_BIFF8) {
                $string = self::read_unicode_string_short(substr($record_data, 14));
            } else {
                $string = $this->read_byte_string_short(substr($record_data, 14));
            }
            /** @var string[] $string */
            $obj_font->set_name($string['value']);
            $this->obj_fonts[] = $obj_font;
        }
    }
    /**
     * FORMAT.
     *
     * This record contains information about a number format.
     * All FORMAT records occur together in a sequential list.
     *
     * In BIFF2-BIFF4 other records referencing a FORMAT record
     * contain a zero-based index into this list. From BIFF5 on
     * the FORMAT record contains the index itself that will be
     * used by other records.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_format(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            $index_code = self::get_u_int2d($record_data, 0);
            if ($this->version == self::XLS_BIFF8) {
                $string = self::read_unicode_string_long(substr($record_data, 2));
            } else {
                // BIFF7
                $string = $this->read_byte_string_short(substr($record_data, 2));
            }
            $format_string = $string['value'];
            // Apache Open Office sets wrong case writing to xls - issue 2239
            if ($format_string === 'GENERAL') {
                $format_string = Number_Format::FORMAT_GENERAL;
            }
            $this->formats[$index_code] = $format_string;
        }
    }
    /**
     * XF - Extended Format.
     *
     * This record contains formatting information for cells, rows, columns or styles.
     * According to https://support.microsoft.com/en-us/help/147732 there are always at least 15 cell style XF
     * and 1 cell XF.
     * Inspection of Excel files generated by MS Office Excel shows that XF records 0-14 are cell style XF
     * and XF record 15 is a cell XF
     * We only read the first cell style XF and skip the remaining cell style XF records
     * We read all cell XF records.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_xf(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        $obj_style = new Style();
        if (!$this->read_data_only) {
            // offset:  0; size: 2; Index to FONT record
            if (self::get_u_int2d($record_data, 0) < 4) {
                $font_index = self::get_u_int2d($record_data, 0);
            } else {
                // this has to do with that index 4 is omitted in all BIFF versions for some strange reason
                // check the OpenOffice documentation of the FONT record
                $font_index = self::get_u_int2d($record_data, 0) - 1;
            }
            if (isset($this->obj_fonts[$font_index])) {
                $obj_style->set_font($this->obj_fonts[$font_index]);
            }
            // offset:  2; size: 2; Index to FORMAT record
            $number_format_index = self::get_u_int2d($record_data, 2);
            if (isset($this->formats[$number_format_index])) {
                // then we have user-defined format code
                $number_format = ['formatCode' => $this->formats[$number_format_index]];
            } elseif (($code = Number_Format::built_in_format_code($number_format_index)) !== '') {
                // then we have built-in format code
                $number_format = ['formatCode' => $code];
            } else {
                // we set the general format code
                $number_format = ['formatCode' => Number_Format::FORMAT_GENERAL];
            }
            /** @var string[] $numberFormat */
            $obj_style->get_number_format()->set_format_code($number_format['formatCode']);
            // offset:  4; size: 2; XF type, cell protection, and parent style XF
            // bit 2-0; mask 0x0007; XF_TYPE_PROT
            $xf_type_prot = self::get_u_int2d($record_data, 4);
            // bit 0; mask 0x01; 1 = cell is locked
            $is_locked = (0x1 & $xf_type_prot) >> 0;
            $obj_style->get_protection()->set_locked($is_locked ? Protection::PROTECTION_INHERIT : Protection::PROTECTION_UNPROTECTED);
            // bit 1; mask 0x02; 1 = Formula is hidden
            $is_hidden = (0x2 & $xf_type_prot) >> 1;
            $obj_style->get_protection()->set_hidden($is_hidden ? Protection::PROTECTION_PROTECTED : Protection::PROTECTION_UNPROTECTED);
            // bit 2; mask 0x04; 0 = Cell XF, 1 = Cell Style XF
            $is_cell_style_xf = (0x4 & $xf_type_prot) >> 2;
            // offset:  6; size: 1; Alignment and text break
            // bit 2-0, mask 0x07; horizontal alignment
            $hor_align = (0x7 & ord($record_data[6])) >> 0;
            Xls\Style\Cell_Alignment::horizontal($obj_style->get_alignment(), $hor_align);
            // bit 3, mask 0x08; wrap text
            $wrap_text = (0x8 & ord($record_data[6])) >> 3;
            Xls\Style\Cell_Alignment::wrap($obj_style->get_alignment(), $wrap_text);
            // bit 6-4, mask 0x70; vertical alignment
            $vert_align = (0x70 & ord($record_data[6])) >> 4;
            Xls\Style\Cell_Alignment::vertical($obj_style->get_alignment(), $vert_align);
            if ($this->version == self::XLS_BIFF8) {
                // offset:  7; size: 1; XF_ROTATION: Text rotation angle
                $angle = ord($record_data[7]);
                $rotation = 0;
                if ($angle <= 90) {
                    $rotation = $angle;
                } elseif ($angle <= 180) {
                    $rotation = 90 - $angle;
                } elseif ($angle == Alignment::TEXTROTATION_STACK_EXCEL) {
                    $rotation = Alignment::TEXTROTATION_STACK_PHPSPREADSHEET;
                }
                $obj_style->get_alignment()->set_text_rotation($rotation);
                // offset:  8; size: 1; Indentation, shrink to cell size, and text direction
                // bit: 3-0; mask: 0x0F; indent level
                $indent = (0xf & ord($record_data[8])) >> 0;
                $obj_style->get_alignment()->set_indent($indent);
                // bit: 4; mask: 0x10; 1 = shrink content to fit into cell
                $shrink_to_fit = (0x10 & ord($record_data[8])) >> 4;
                switch ($shrink_to_fit) {
                    case 0:
                        $obj_style->get_alignment()->set_shrink_to_fit(false);
                        break;
                    case 1:
                        $obj_style->get_alignment()->set_shrink_to_fit(true);
                        break;
                }
                $read_order = (0xc0 & ord($record_data[8])) >> 6;
                $obj_style->get_alignment()->set_read_order($read_order);
                // offset:  9; size: 1; Flags used for attribute groups
                // offset: 10; size: 4; Cell border lines and background area
                // bit: 3-0; mask: 0x0000000F; left style
                if ($borders_left_style = Xls\Style\Border::lookup((0xf & self::get_int4d($record_data, 10)) >> 0)) {
                    $obj_style->get_borders()->get_left()->set_border_style($borders_left_style);
                }
                // bit: 7-4; mask: 0x000000F0; right style
                if ($borders_right_style = Xls\Style\Border::lookup((0xf0 & self::get_int4d($record_data, 10)) >> 4)) {
                    $obj_style->get_borders()->get_right()->set_border_style($borders_right_style);
                }
                // bit: 11-8; mask: 0x00000F00; top style
                if ($borders_top_style = Xls\Style\Border::lookup((0xf00 & self::get_int4d($record_data, 10)) >> 8)) {
                    $obj_style->get_borders()->get_top()->set_border_style($borders_top_style);
                }
                // bit: 15-12; mask: 0x0000F000; bottom style
                if ($borders_bottom_style = Xls\Style\Border::lookup((0xf000 & self::get_int4d($record_data, 10)) >> 12)) {
                    $obj_style->get_borders()->get_bottom()->set_border_style($borders_bottom_style);
                }
                // bit: 22-16; mask: 0x007F0000; left color
                $obj_style->get_borders()->get_left()->color_index = (0x7f0000 & self::get_int4d($record_data, 10)) >> 16;
                // bit: 29-23; mask: 0x3F800000; right color
                $obj_style->get_borders()->get_right()->color_index = (0x3f800000 & self::get_int4d($record_data, 10)) >> 23;
                // bit: 30; mask: 0x40000000; 1 = diagonal line from top left to right bottom
                $diagonal_down = (0x40000000 & self::get_int4d($record_data, 10)) >> 30 ? true : false;
                // bit: 31; mask: 0x800000; 1 = diagonal line from bottom left to top right
                $diagonal_up = (self::HIGH_ORDER_BIT & self::get_int4d($record_data, 10)) >> 31 ? true : false;
                if ($diagonal_up === false) {
                    if ($diagonal_down === false) {
                        $obj_style->get_borders()->set_diagonal_direction(Borders::DIAGONAL_NONE);
                    } else {
                        $obj_style->get_borders()->set_diagonal_direction(Borders::DIAGONAL_DOWN);
                    }
                } elseif ($diagonal_down === false) {
                    $obj_style->get_borders()->set_diagonal_direction(Borders::DIAGONAL_UP);
                } else {
                    $obj_style->get_borders()->set_diagonal_direction(Borders::DIAGONAL_BOTH);
                }
                // offset: 14; size: 4;
                // bit: 6-0; mask: 0x0000007F; top color
                $obj_style->get_borders()->get_top()->color_index = (0x7f & self::get_int4d($record_data, 14)) >> 0;
                // bit: 13-7; mask: 0x00003F80; bottom color
                $obj_style->get_borders()->get_bottom()->color_index = (0x3f80 & self::get_int4d($record_data, 14)) >> 7;
                // bit: 20-14; mask: 0x001FC000; diagonal color
                $obj_style->get_borders()->get_diagonal()->color_index = (0x1fc000 & self::get_int4d($record_data, 14)) >> 14;
                // bit: 24-21; mask: 0x01E00000; diagonal style
                if ($borders_diagonal_style = Xls\Style\Border::lookup((0x1e00000 & self::get_int4d($record_data, 14)) >> 21)) {
                    $obj_style->get_borders()->get_diagonal()->set_border_style($borders_diagonal_style);
                }
                // bit: 31-26; mask: 0xFC000000 fill pattern
                if ($fill_type = Fill_Pattern::lookup((self::FC000000 & self::get_int4d($record_data, 14)) >> 26)) {
                    $obj_style->get_fill()->set_fill_type($fill_type);
                }
                // offset: 18; size: 2; pattern and background colour
                // bit: 6-0; mask: 0x007F; color index for pattern color
                $obj_style->get_fill()->startcolor_index = (0x7f & self::get_u_int2d($record_data, 18)) >> 0;
                // bit: 13-7; mask: 0x3F80; color index for pattern background
                $obj_style->get_fill()->endcolor_index = (0x3f80 & self::get_u_int2d($record_data, 18)) >> 7;
            } else {
                // BIFF5
                // offset: 7; size: 1; Text orientation and flags
                $orientation_and_flags = ord($record_data[7]);
                // bit: 1-0; mask: 0x03; XF_ORIENTATION: Text orientation
                $xf_orientation = (0x3 & $orientation_and_flags) >> 0;
                switch ($xf_orientation) {
                    case 0:
                        $obj_style->get_alignment()->set_text_rotation(0);
                        break;
                    case 1:
                        $obj_style->get_alignment()->set_text_rotation(Alignment::TEXTROTATION_STACK_PHPSPREADSHEET);
                        break;
                    case 2:
                        $obj_style->get_alignment()->set_text_rotation(90);
                        break;
                    case 3:
                        $obj_style->get_alignment()->set_text_rotation(-90);
                        break;
                }
                // offset: 8; size: 4; cell border lines and background area
                $border_and_background = self::get_int4d($record_data, 8);
                // bit: 6-0; mask: 0x0000007F; color index for pattern color
                $obj_style->get_fill()->startcolor_index = (0x7f & $border_and_background) >> 0;
                // bit: 13-7; mask: 0x00003F80; color index for pattern background
                $obj_style->get_fill()->endcolor_index = (0x3f80 & $border_and_background) >> 7;
                // bit: 21-16; mask: 0x003F0000; fill pattern
                $obj_style->get_fill()->set_fill_type(Fill_Pattern::lookup((0x3f0000 & $border_and_background) >> 16));
                // bit: 24-22; mask: 0x01C00000; bottom line style
                $obj_style->get_borders()->get_bottom()->set_border_style(Xls\Style\Border::lookup((0x1c00000 & $border_and_background) >> 22));
                // bit: 31-25; mask: 0xFE000000; bottom line color
                $obj_style->get_borders()->get_bottom()->color_index = (self::FE000000 & $border_and_background) >> 25;
                // offset: 12; size: 4; cell border lines
                $border_lines = self::get_int4d($record_data, 12);
                // bit: 2-0; mask: 0x00000007; top line style
                $obj_style->get_borders()->get_top()->set_border_style(Xls\Style\Border::lookup((0x7 & $border_lines) >> 0));
                // bit: 5-3; mask: 0x00000038; left line style
                $obj_style->get_borders()->get_left()->set_border_style(Xls\Style\Border::lookup((0x38 & $border_lines) >> 3));
                // bit: 8-6; mask: 0x000001C0; right line style
                $obj_style->get_borders()->get_right()->set_border_style(Xls\Style\Border::lookup((0x1c0 & $border_lines) >> 6));
                // bit: 15-9; mask: 0x0000FE00; top line color index
                $obj_style->get_borders()->get_top()->color_index = (0xfe00 & $border_lines) >> 9;
                // bit: 22-16; mask: 0x007F0000; left line color index
                $obj_style->get_borders()->get_left()->color_index = (0x7f0000 & $border_lines) >> 16;
                // bit: 29-23; mask: 0x3F800000; right line color index
                $obj_style->get_borders()->get_right()->color_index = (0x3f800000 & $border_lines) >> 23;
            }
            // add cellStyleXf or cellXf and update mapping
            if ($is_cell_style_xf) {
                // we only read one style XF record which is always the first
                if ($this->xf_index == 0) {
                    $this->spreadsheet->add_cell_style_xf($obj_style);
                    $this->map_cell_style_xf_index[$this->xf_index] = 0;
                }
            } else {
                // we read all cell XF records
                $this->spreadsheet->add_cell_xf($obj_style);
                $this->map_cell_xf_index[$this->xf_index] = count($this->spreadsheet->get_cell_xf_collection()) - 1;
            }
            // update XF index for when we read next record
            ++$this->xf_index;
        }
    }
    protected function read_xf_ext(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; 0x087D = repeated header
            // offset: 2; size: 2
            // offset: 4; size: 8; not used
            // offset: 12; size: 2; record version
            // offset: 14; size: 2; index to XF record which this record modifies
            $ixfe = self::get_u_int2d($record_data, 14);
            // offset: 16; size: 2; not used
            // offset: 18; size: 2; number of extension properties that follow
            //$cexts = self::getUInt2d($recordData, 18);
            // start reading the actual extension data
            $offset = 20;
            while ($offset < $length) {
                // extension type
                $ext_type = self::get_u_int2d($record_data, $offset);
                // extension length
                $cb = self::get_u_int2d($record_data, $offset + 2);
                // extension data
                $ext_data = substr($record_data, $offset + 4, $cb);
                switch ($ext_type) {
                    case 4:
                        // fill start color
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $fill = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_fill();
                                $fill->get_start_color()->set_rgb($rgb);
                                $fill->startcolor_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 5:
                        // fill end color
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $fill = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_fill();
                                $fill->get_end_color()->set_rgb($rgb);
                                $fill->endcolor_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 7:
                        // border color top
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $top = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_borders()->get_top();
                                $top->get_color()->set_rgb($rgb);
                                $top->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 8:
                        // border color bottom
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $bottom = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_borders()->get_bottom();
                                $bottom->get_color()->set_rgb($rgb);
                                $bottom->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 9:
                        // border color left
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $left = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_borders()->get_left();
                                $left->get_color()->set_rgb($rgb);
                                $left->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 10:
                        // border color right
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $right = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_borders()->get_right();
                                $right->get_color()->set_rgb($rgb);
                                $right->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 11:
                        // border color diagonal
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $diagonal = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_borders()->get_diagonal();
                                $diagonal->get_color()->set_rgb($rgb);
                                $diagonal->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                    case 13:
                        // font color
                        $xclf_type = self::get_u_int2d($ext_data, 0);
                        // color type
                        $xclr_value = substr($ext_data, 4, 4);
                        // color value (value based on color type)
                        if ($xclf_type == 2) {
                            $rgb = sprintf('%02X%02X%02X', ord($xclr_value[0]), ord($xclr_value[1]), ord($xclr_value[2]));
                            // modify the relevant style property
                            if (isset($this->map_cell_xf_index[$ixfe])) {
                                $font = $this->spreadsheet->get_cell_xf_by_index($this->map_cell_xf_index[$ixfe])->get_font();
                                $font->get_color()->set_rgb($rgb);
                                $font->color_index = null;
                                // normal color index does not apply, discard
                            }
                        }
                        break;
                }
                $offset += $cb;
            }
        }
    }
    /**
     * Read STYLE record.
     */
    protected function read_style(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; index to XF record and flag for built-in style
            $ixfe = self::get_u_int2d($record_data, 0);
            // bit: 11-0; mask 0x0FFF; index to XF record
            //$xfIndex = (0x0FFF & $ixfe) >> 0;
            // bit: 15; mask 0x8000; 0 = user-defined style, 1 = built-in style
            $is_built_in = (bool) ((0x8000 & $ixfe) >> 15);
            if ($is_built_in) {
                // offset: 2; size: 1; identifier for built-in style
                $built_in_id = ord($record_data[2]);
                switch ($built_in_id) {
                    case 0x0:
                        // currently, we are not using this for anything
                        break;
                    default:
                        break;
                }
            }
            // user-defined; not supported by PhpSpreadsheet
        }
    }
    /**
     * Read PALETTE record.
     */
    protected function read_palette(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; number of following colors
            $nm = self::get_u_int2d($record_data, 0);
            // list of RGB colors
            for ($i = 0; $i < $nm; ++$i) {
                $rgb = substr($record_data, 2 + 4 * $i, 4);
                $this->palette[] = self::read_rgb($rgb);
            }
        }
    }
    /**
     * SHEET.
     *
     * This record is  located in the  Workbook Globals
     * Substream  and represents a sheet inside the workbook.
     * One SHEET record is written for each sheet. It stores the
     * sheet name and a stream offset to the BOF record of the
     * respective Sheet Substream within the Workbook Stream.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_sheet(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // offset: 0; size: 4; absolute stream position of the BOF record of the sheet
        // NOTE: not encrypted
        $rec_offset = self::get_int4d($this->data, $this->pos + 4);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 4; size: 1; sheet state
        $sheet_state = match (ord($record_data[4])) {
            0x0 => Worksheet::SHEETSTATE_VISIBLE,
            0x1 => Worksheet::SHEETSTATE_HIDDEN,
            0x2 => Worksheet::SHEETSTATE_VERYHIDDEN,
            default => Worksheet::SHEETSTATE_VISIBLE,
        };
        // offset: 5; size: 1; sheet type
        $sheet_type = ord($record_data[5]);
        // offset: 6; size: var; sheet name
        $rec_name = null;
        if ($this->version == self::XLS_BIFF8) {
            $string = self::read_unicode_string_short(substr($record_data, 6));
            $rec_name = $string['value'];
        } elseif ($this->version == self::XLS_BIFF7) {
            $string = $this->read_byte_string_short(substr($record_data, 6));
            $rec_name = $string['value'];
        }
        /** @var string $rec_name */
        $this->sheets[] = ['name' => $rec_name, 'offset' => $rec_offset, 'sheetState' => $sheet_state, 'sheetType' => $sheet_type];
    }
    /**
     * Read EXTERNALBOOK record.
     */
    protected function read_external_book(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset within record data
        $offset = 0;
        // there are 4 types of records
        if (strlen($record_data) > 4) {
            // external reference
            // offset: 0; size: 2; number of sheet names ($nm)
            $nm = self::get_u_int2d($record_data, 0);
            $offset += 2;
            // offset: 2; size: var; encoded URL without sheet name (Unicode string, 16-bit length)
            $encoded_url_string = self::read_unicode_string_long(substr($record_data, 2));
            $offset += $encoded_url_string['size'];
            // offset: var; size: var; list of $nm sheet names (Unicode strings, 16-bit length)
            $external_sheet_names = [];
            for ($i = 0; $i < $nm; ++$i) {
                $external_sheet_name_string = self::read_unicode_string_long(substr($record_data, $offset));
                $external_sheet_names[] = $external_sheet_name_string['value'];
                $offset += $external_sheet_name_string['size'];
            }
            // store the record data
            $this->external_books[] = ['type' => 'external', 'encodedUrl' => $encoded_url_string['value'], 'externalSheetNames' => $external_sheet_names];
        } elseif (substr($record_data, 2, 2) == pack('CC', 0x1, 0x4)) {
            // internal reference
            // offset: 0; size: 2; number of sheet in this document
            // offset: 2; size: 2; 0x01 0x04
            $this->external_books[] = ['type' => 'internal'];
        } elseif (substr($record_data, 0, 4) == pack('vCC', 0x1, 0x1, 0x3a)) {
            // add-in function
            // offset: 0; size: 2; 0x0001
            $this->external_books[] = ['type' => 'addInFunction'];
        } elseif (substr($record_data, 0, 2) == pack('v', 0x0)) {
            // DDE links, OLE links
            // offset: 0; size: 2; 0x0000
            // offset: 2; size: var; encoded source document name
            $this->external_books[] = ['type' => 'DDEorOLE'];
        }
    }
    /**
     * Read EXTERNNAME record.
     */
    protected function read_extern_name(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // external sheet references provided for named cells
        if ($this->version == self::XLS_BIFF8) {
            // offset: 0; size: 2; options
            //$options = self::getUInt2d($recordData, 0);
            // offset: 2; size: 2;
            // offset: 4; size: 2; not used
            // offset: 6; size: var
            $name_string = self::read_unicode_string_short(substr($record_data, 6));
            // offset: var; size: var; formula data
            $offset = 6 + $name_string['size'];
            $formula = $this->get_formula_from_structure(substr($record_data, $offset));
            $this->external_names[] = ['name' => $name_string['value'], 'formula' => $formula];
        }
    }
    /**
     * Read EXTERNSHEET record.
     */
    protected function read_extern_sheet(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // external sheet references provided for named cells
        if ($this->version == self::XLS_BIFF8) {
            // offset: 0; size: 2; number of following ref structures
            $nm = self::get_u_int2d($record_data, 0);
            for ($i = 0; $i < $nm; ++$i) {
                $this->ref[] = [
                    // offset: 2 + 6 * $i; index to EXTERNALBOOK record
                    'externalBookIndex' => self::get_u_int2d($record_data, 2 + 6 * $i),
                    // offset: 4 + 6 * $i; index to first sheet in EXTERNALBOOK record
                    'firstSheetIndex' => self::get_u_int2d($record_data, 4 + 6 * $i),
                    // offset: 6 + 6 * $i; index to last sheet in EXTERNALBOOK record
                    'lastSheetIndex' => self::get_u_int2d($record_data, 6 + 6 * $i),
                ];
            }
        }
    }
    /**
     * DEFINEDNAME.
     *
     * This record is part of a Link Table. It contains the name
     * and the token array of an internal defined name. Token
     * arrays of defined names contain tokens with aberrant
     * token classes.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_defined_name(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8) {
            // retrieves named cells
            // offset: 0; size: 2; option flags
            $opts = self::get_u_int2d($record_data, 0);
            // bit: 5; mask: 0x0020; 0 = user-defined name, 1 = built-in-name
            $is_built_in_name = (0x20 & $opts) >> 5;
            // offset: 2; size: 1; keyboard shortcut
            // offset: 3; size: 1; length of the name (character count)
            $nlen = ord($record_data[3]);
            // offset: 4; size: 2; size of the formula data (it can happen that this is zero)
            // note: there can also be additional data, this is not included in $flen
            $flen = self::get_u_int2d($record_data, 4);
            // offset: 8; size: 2; 0=Global name, otherwise index to sheet (1-based)
            $scope = self::get_u_int2d($record_data, 8);
            // offset: 14; size: var; Name (Unicode string without length field)
            $string = self::read_unicode_string(substr($record_data, 14), $nlen);
            // offset: var; size: $flen; formula data
            $offset = 14 + $string['size'];
            $formula_structure = pack('v', $flen) . substr($record_data, $offset);
            try {
                $formula = $this->get_formula_from_structure($formula_structure);
            } catch (Php_Spreadsheet_Exception) {
                $formula = '';
                $is_built_in_name = 0;
            }
            $this->definedname[] = ['isBuiltInName' => $is_built_in_name, 'name' => $string['value'], 'formula' => $formula, 'scope' => $scope];
        }
    }
    /**
     * Read MSODRAWINGGROUP record.
     */
    protected function read_mso_drawing_group(): void
    {
        //$length = self::getUInt2d($this->data, $this->pos + 2);
        // get spliced record data
        $spliced_record_data = $this->get_spliced_record_data();
        /** @var string */
        $record_data = $spliced_record_data['recordData'];
        $this->drawing_group_data .= $record_data;
    }
    /**
     * SST - Shared String Table.
     *
     * This record contains a list of all strings used anywhere
     * in the workbook. Each string occurs only once. The
     * workbook uses indexes into the list to reference the
     * strings.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_sst(): void
    {
        // offset within (spliced) record data
        $pos = 0;
        // Limit global SST position, further control for bad SST Length in BIFF8 data
        $limitpos_sst = 0;
        // get spliced record data
        $spliced_record_data = $this->get_spliced_record_data();
        $record_data = $spliced_record_data['recordData'];
        /** @var mixed[] */
        $splice_offsets = $spliced_record_data['spliceOffsets'];
        // offset: 0; size: 4; total number of strings in the workbook
        $pos += 4;
        // offset: 4; size: 4; number of following strings ($nm)
        /** @var string $recordData */
        $nm = self::get_int4d($record_data, 4);
        $pos += 4;
        // look up limit position
        foreach ($splice_offsets as $splice_offset) {
            // it can happen that the string is empty, therefore we need
            // <= and not just <
            if ($pos <= $splice_offset) {
                $limitpos_sst = $splice_offset;
            }
        }
        // loop through the Unicode strings (16-bit length)
        for ($i = 0; $i < $nm && $pos < $limitpos_sst; ++$i) {
            // number of characters in the Unicode string
            $num_chars = self::get_u_int2d($record_data, $pos);
            /** @var int $pos */
            $pos += 2;
            // option flags
            /** @var string $recordData */
            $option_flags = ord($record_data[$pos]);
            ++$pos;
            // bit: 0; mask: 0x01; 0 = compressed; 1 = uncompressed
            $is_compressed = ($option_flags & 0x1) == 0;
            // bit: 2; mask: 0x02; 0 = ordinary; 1 = Asian phonetic
            $has_asian = ($option_flags & 0x4) != 0;
            // bit: 3; mask: 0x03; 0 = ordinary; 1 = Rich-Text
            $has_rich_text = ($option_flags & 0x8) != 0;
            $formatting_runs = 0;
            if ($has_rich_text) {
                // number of Rich-Text formatting runs
                $formatting_runs = self::get_u_int2d($record_data, $pos);
                $pos += 2;
            }
            $extended_run_length = 0;
            if ($has_asian) {
                // size of Asian phonetic setting
                $extended_run_length = self::get_int4d($record_data, $pos);
                $pos += 4;
            }
            // expected byte length of character array if not split
            $len = $is_compressed ? $num_chars : $num_chars * 2;
            // look up limit position - Check it again to be sure that no error occurs when parsing SST structure
            $limitpos = null;
            foreach ($splice_offsets as $splice_offset) {
                // it can happen that the string is empty, therefore we need
                // <= and not just <
                if ($pos <= $splice_offset) {
                    $limitpos = $splice_offset;
                    break;
                }
            }
            /** @var int $limitpos */
            if ($pos + $len <= $limitpos) {
                // character array is not split between records
                $retstr = substr($record_data, $pos, $len);
                $pos += $len;
            } else {
                // character array is split between records
                // first part of character array
                $retstr = substr($record_data, $pos, $limitpos - $pos);
                $bytes_read = $limitpos - $pos;
                // remaining characters in Unicode string
                $chars_left = $num_chars - ($is_compressed ? $bytes_read : $bytes_read / 2);
                $pos = $limitpos;
                // keep reading the characters
                while ($chars_left > 0) {
                    // look up next limit position, in case the string span more than one continue record
                    foreach ($splice_offsets as $splice_offset) {
                        if ($pos < $splice_offset) {
                            $limitpos = $splice_offset;
                            break;
                        }
                    }
                    // repeated option flags
                    // OpenOffice.org documentation 5.21
                    /** @var int $pos */
                    $option = ord($record_data[$pos]);
                    ++$pos;
                    /** @var int $limitpos */
                    if ($is_compressed && $option == 0) {
                        // 1st fragment compressed
                        // this fragment compressed
                        /** @var int */
                        $len = min($chars_left, $limitpos - $pos);
                        $retstr .= substr($record_data, $pos, $len);
                        $chars_left -= $len;
                        $is_compressed = true;
                    } elseif (!$is_compressed && $option != 0) {
                        // 1st fragment uncompressed
                        // this fragment uncompressed
                        /** @var int */
                        $len = min($chars_left * 2, $limitpos - $pos);
                        $retstr .= substr($record_data, $pos, $len);
                        $chars_left -= $len / 2;
                        $is_compressed = false;
                    } elseif (!$is_compressed && $option == 0) {
                        // 1st fragment uncompressed
                        // this fragment compressed
                        $len = min($chars_left, $limitpos - $pos);
                        for ($j = 0; $j < $len; ++$j) {
                            $retstr .= $record_data[$pos + $j] . chr(0);
                        }
                        $chars_left -= $len;
                        $is_compressed = false;
                    } else {
                        // 1st fragment compressed
                        // this fragment uncompressed
                        $newstr = '';
                        $j_max = strlen($retstr);
                        for ($j = 0; $j < $j_max; ++$j) {
                            $newstr .= $retstr[$j] . chr(0);
                        }
                        $retstr = $newstr;
                        /** @var int */
                        $len = min($chars_left * 2, $limitpos - $pos);
                        $retstr .= substr($record_data, $pos, $len);
                        $chars_left -= $len / 2;
                        $is_compressed = false;
                    }
                    $pos += $len;
                }
            }
            // convert to UTF-8
            $retstr = self::encode_utf16($retstr, $is_compressed);
            // read additional Rich-Text information, if any
            $fmt_runs = [];
            if ($has_rich_text) {
                // list of formatting runs
                for ($j = 0; $j < $formatting_runs; ++$j) {
                    // first formatted character; zero-based
                    $char_pos = self::get_u_int2d($record_data, $pos + $j * 4);
                    // index to font record
                    $font_index = self::get_u_int2d($record_data, $pos + 2 + $j * 4);
                    $fmt_runs[] = ['charPos' => $char_pos, 'fontIndex' => $font_index];
                }
                $pos += 4 * $formatting_runs;
            }
            // read additional Asian phonetics information, if any
            if ($has_asian) {
                // For Asian phonetic settings, we skip the extended string data
                $pos += $extended_run_length;
            }
            // store the shared sting
            $this->sst[] = ['value' => $retstr, 'fmtRuns' => $fmt_runs];
        }
        // getSplicedRecordData() takes care of moving current position in data stream
    }
    /**
     * Read PRINTGRIDLINES record.
     */
    protected function read_print_gridlines(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8 && !$this->read_data_only) {
            // offset: 0; size: 2; 0 = do not print sheet grid lines; 1 = print sheet gridlines
            $print_gridlines = (bool) self::get_u_int2d($record_data, 0);
            $this->php_sheet->set_print_gridlines($print_gridlines);
        }
    }
    /**
     * Read DEFAULTROWHEIGHT record.
     */
    protected function read_default_row_height(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; option flags
        // offset: 2; size: 2; default height for unused rows, (twips 1/20 point)
        $height = self::get_u_int2d($record_data, 2);
        $this->php_sheet->get_default_row_dimension()->set_row_height($height / 20);
    }
    /**
     * Read SHEETPR record.
     */
    protected function read_sheet_pr(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2
        // bit: 6; mask: 0x0040; 0 = outline buttons above outline group
        $is_summary_below = (0x40 & self::get_u_int2d($record_data, 0)) >> 6;
        $this->php_sheet->set_show_summary_below((bool) $is_summary_below);
        // bit: 7; mask: 0x0080; 0 = outline buttons left of outline group
        $is_summary_right = (0x80 & self::get_u_int2d($record_data, 0)) >> 7;
        $this->php_sheet->set_show_summary_right((bool) $is_summary_right);
        // bit: 8; mask: 0x100; 0 = scale printout in percent, 1 = fit printout to number of pages
        // this corresponds to radio button setting in page setup dialog in Excel
        $this->is_fit_to_pages = (bool) ((0x100 & self::get_u_int2d($record_data, 0)) >> 8);
    }
    /**
     * Read HORIZONTALPAGEBREAKS record.
     */
    protected function read_horizontal_page_breaks(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8 && !$this->read_data_only) {
            // offset: 0; size: 2; number of the following row index structures
            $nm = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 6 * $nm; list of $nm row index structures
            for ($i = 0; $i < $nm; ++$i) {
                $r = self::get_u_int2d($record_data, 2 + 6 * $i);
                $cf = self::get_u_int2d($record_data, 2 + 6 * $i + 2);
                //$cl = self::getUInt2d($recordData, 2 + 6 * $i + 4);
                // not sure why two column indexes are necessary?
                $this->php_sheet->set_break([$cf + 1, $r], Worksheet::BREAK_ROW);
            }
        }
    }
    /**
     * Read VERTICALPAGEBREAKS record.
     */
    protected function read_vertical_page_breaks(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8 && !$this->read_data_only) {
            // offset: 0; size: 2; number of the following column index structures
            $nm = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 6 * $nm; list of $nm row index structures
            for ($i = 0; $i < $nm; ++$i) {
                $c = self::get_u_int2d($record_data, 2 + 6 * $i);
                $rf = self::get_u_int2d($record_data, 2 + 6 * $i + 2);
                //$rl = self::getUInt2d($recordData, 2 + 6 * $i + 4);
                // not sure why two row indexes are necessary?
                $this->php_sheet->set_break([$c + 1, $rf > 0 ? $rf : 1], Worksheet::BREAK_COLUMN);
            }
        }
    }
    /**
     * Read HEADER record.
     */
    protected function read_header(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: var
            // realized that $recordData can be empty even when record exists
            if ($record_data) {
                if ($this->version == self::XLS_BIFF8) {
                    $string = self::read_unicode_string_long($record_data);
                } else {
                    $string = $this->read_byte_string_short($record_data);
                }
                /** @var string[] $string */
                $this->php_sheet->get_header_footer()->set_odd_header($string['value']);
                $this->php_sheet->get_header_footer()->set_even_header($string['value']);
            }
        }
    }
    /**
     * Read FOOTER record.
     */
    protected function read_footer(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: var
            // realized that $recordData can be empty even when record exists
            if ($record_data) {
                if ($this->version == self::XLS_BIFF8) {
                    $string = self::read_unicode_string_long($record_data);
                } else {
                    $string = $this->read_byte_string_short($record_data);
                }
                /** @var string */
                $temp = $string['value'];
                $this->php_sheet->get_header_footer()->set_odd_footer($temp);
                $this->php_sheet->get_header_footer()->set_even_footer($temp);
            }
        }
    }
    /**
     * Read HCENTER record.
     */
    protected function read_hcenter(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; 0 = print sheet left aligned, 1 = print sheet centered horizontally
            $is_horizontal_centered = (bool) self::get_u_int2d($record_data, 0);
            $this->php_sheet->get_page_setup()->set_horizontal_centered($is_horizontal_centered);
        }
    }
    /**
     * Read VCENTER record.
     */
    protected function read_vcenter(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; 0 = print sheet aligned at top page border, 1 = print sheet vertically centered
            $is_vertical_centered = (bool) self::get_u_int2d($record_data, 0);
            $this->php_sheet->get_page_setup()->set_vertical_centered($is_vertical_centered);
        }
    }
    /**
     * Read LEFTMARGIN record.
     */
    protected function read_left_margin(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 8
            $this->php_sheet->get_page_margins()->set_left(self::extract_number($record_data));
        }
    }
    /**
     * Read RIGHTMARGIN record.
     */
    protected function read_right_margin(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 8
            $this->php_sheet->get_page_margins()->set_right(self::extract_number($record_data));
        }
    }
    /**
     * Read TOPMARGIN record.
     */
    protected function read_top_margin(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 8
            $this->php_sheet->get_page_margins()->set_top(self::extract_number($record_data));
        }
    }
    /**
     * Read BOTTOMMARGIN record.
     */
    protected function read_bottom_margin(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 8
            $this->php_sheet->get_page_margins()->set_bottom(self::extract_number($record_data));
        }
    }
    /**
     * Read PAGESETUP record.
     */
    protected function read_page_setup(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; paper size
            $paper_size = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 2; scaling factor
            $scale = self::get_u_int2d($record_data, 2);
            // offset: 6; size: 2; fit worksheet width to this number of pages, 0 = use as many as needed
            $fit_to_width = self::get_u_int2d($record_data, 6);
            // offset: 8; size: 2; fit worksheet height to this number of pages, 0 = use as many as needed
            $fit_to_height = self::get_u_int2d($record_data, 8);
            // offset: 10; size: 2; option flags
            // bit: 0; mask: 0x0001; 0=down then over, 1=over then down
            $is_over_then_down = 0x1 & self::get_u_int2d($record_data, 10);
            // bit: 1; mask: 0x0002; 0=landscape, 1=portrait
            $is_portrait = (0x2 & self::get_u_int2d($record_data, 10)) >> 1;
            // bit: 2; mask: 0x0004; 1= paper size, scaling factor, paper orient. not init
            // when this bit is set, do not use flags for those properties
            $is_not_init = (0x4 & self::get_u_int2d($record_data, 10)) >> 2;
            if (!$is_not_init) {
                $this->php_sheet->get_page_setup()->set_paper_size($paper_size);
                $this->php_sheet->get_page_setup()->set_page_order((bool) $is_over_then_down ? Page_Setup::PAGEORDER_OVER_THEN_DOWN : Page_Setup::PAGEORDER_DOWN_THEN_OVER);
                $this->php_sheet->get_page_setup()->set_orientation((bool) $is_portrait ? Page_Setup::ORIENTATION_PORTRAIT : Page_Setup::ORIENTATION_LANDSCAPE);
                $this->php_sheet->get_page_setup()->set_scale($scale, false);
                $this->php_sheet->get_page_setup()->set_fit_to_page($this->is_fit_to_pages);
                $this->php_sheet->get_page_setup()->set_fit_to_width($fit_to_width, false);
                $this->php_sheet->get_page_setup()->set_fit_to_height($fit_to_height, false);
            }
            // offset: 16; size: 8; header margin (IEEE 754 floating-point value)
            $margin_header = self::extract_number(substr($record_data, 16, 8));
            $this->php_sheet->get_page_margins()->set_header($margin_header);
            // offset: 24; size: 8; footer margin (IEEE 754 floating-point value)
            $margin_footer = self::extract_number(substr($record_data, 24, 8));
            $this->php_sheet->get_page_margins()->set_footer($margin_footer);
        }
    }
    /**
     * PROTECT - Sheet protection (BIFF2 through BIFF8)
     *   if this record is omitted, then it also means no sheet protection.
     */
    protected function read_protect(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        // offset: 0; size: 2;
        // bit 0, mask 0x01; 1 = sheet is protected
        $bool = (0x1 & self::get_u_int2d($record_data, 0)) >> 0;
        $this->php_sheet->get_protection()->set_sheet((bool) $bool);
    }
    /**
     * SCENPROTECT.
     */
    protected function read_scen_protect(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        // offset: 0; size: 2;
        // bit: 0, mask 0x01; 1 = scenarios are protected
        $bool = (0x1 & self::get_u_int2d($record_data, 0)) >> 0;
        $this->php_sheet->get_protection()->set_scenarios((bool) $bool);
    }
    /**
     * OBJECTPROTECT.
     */
    protected function read_object_protect(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        // offset: 0; size: 2;
        // bit: 0, mask 0x01; 1 = objects are protected
        $bool = (0x1 & self::get_u_int2d($record_data, 0)) >> 0;
        $this->php_sheet->get_protection()->set_objects((bool) $bool);
    }
    /**
     * PASSWORD - Sheet protection (hashed) password (BIFF2 through BIFF8).
     */
    protected function read_password(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; 16-bit hash value of password
            $password = strtoupper(dechex(self::get_u_int2d($record_data, 0)));
            // the hashed password
            $this->php_sheet->get_protection()->set_password($password, true);
        }
    }
    /**
     * Read DEFCOLWIDTH record.
     */
    protected function read_def_col_width(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; default column width
        $width = self::get_u_int2d($record_data, 0);
        if ($width != 8) {
            $this->php_sheet->get_default_column_dimension()->set_width($width);
        }
    }
    /**
     * Read COLINFO record.
     */
    protected function read_col_info(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; index to first column in range
            $first_column_index = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 2; index to last column in range
            $last_column_index = self::get_u_int2d($record_data, 2);
            // offset: 4; size: 2; width of the column in 1/256 of the width of the zero character
            $width = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 2; index to XF record for default column formatting
            $xf_index = self::get_u_int2d($record_data, 6);
            // offset: 8; size: 2; option flags
            // bit: 0; mask: 0x0001; 1= columns are hidden
            $is_hidden = (0x1 & self::get_u_int2d($record_data, 8)) >> 0;
            // bit: 10-8; mask: 0x0700; outline level of the columns (0 = no outline)
            $level = (0x700 & self::get_u_int2d($record_data, 8)) >> 8;
            // bit: 12; mask: 0x1000; 1 = collapsed
            $is_collapsed = (bool) ((0x1000 & self::get_u_int2d($record_data, 8)) >> 12);
            // offset: 10; size: 2; not used
            for ($i = $first_column_index + 1; $i <= $last_column_index + 1; ++$i) {
                if ($last_column_index == Address_Range::MAX_COLUMN_INT_XLS - 1 || $last_column_index == Address_Range::MAX_COLUMN_INT) {
                    $this->php_sheet->get_default_column_dimension()->set_width($width / 256);
                    break;
                }
                $this->php_sheet->get_column_dimension_by_column($i)->set_width($width / 256);
                $this->php_sheet->get_column_dimension_by_column($i)->set_visible(!$is_hidden);
                $this->php_sheet->get_column_dimension_by_column($i)->set_outline_level($level);
                $this->php_sheet->get_column_dimension_by_column($i)->set_collapsed($is_collapsed);
                if (isset($this->map_cell_xf_index[$xf_index])) {
                    $this->php_sheet->get_column_dimension_by_column($i)->set_xf_index($this->map_cell_xf_index[$xf_index]);
                }
            }
        }
    }
    /**
     * ROW.
     *
     * This record contains the properties of a single row in a
     * sheet. Rows and cells in a sheet are divided into blocks
     * of 32 rows.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_row(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; index of this row
            $r = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 2; index to column of the first cell which is described by a cell record
            // offset: 4; size: 2; index to column of the last cell which is described by a cell record, increased by 1
            // offset: 6; size: 2;
            // bit: 14-0; mask: 0x7FFF; height of the row, in twips = 1/20 of a point
            $height = (0x7fff & self::get_u_int2d($record_data, 6)) >> 0;
            // bit: 15: mask: 0x8000; 0 = row has custom height; 1= row has default height
            $use_default_height = (0x8000 & self::get_u_int2d($record_data, 6)) >> 15;
            if (!$use_default_height) {
                if ($this->php_sheet->get_default_row_dimension()->get_row_height() > 0) {
                    $this->php_sheet->get_row_dimension($r + 1)->set_custom_format(true, $height === 255 ? -1 : $height / 20);
                } else {
                    $this->php_sheet->get_row_dimension($r + 1)->set_row_height($height / 20);
                }
            }
            // offset: 8; size: 2; not used
            // offset: 10; size: 2; not used in BIFF5-BIFF8
            // offset: 12; size: 4; option flags and default row formatting
            // bit: 2-0: mask: 0x00000007; outline level of the row
            $level = (0x7 & self::get_int4d($record_data, 12)) >> 0;
            $this->php_sheet->get_row_dimension($r + 1)->set_outline_level($level);
            // bit: 4; mask: 0x00000010; 1 = outline group start or ends here... and is collapsed
            $is_collapsed = (bool) ((0x10 & self::get_int4d($record_data, 12)) >> 4);
            $this->php_sheet->get_row_dimension($r + 1)->set_collapsed($is_collapsed);
            // bit: 5; mask: 0x00000020; 1 = row is hidden
            $is_hidden = (0x20 & self::get_int4d($record_data, 12)) >> 5;
            $this->php_sheet->get_row_dimension($r + 1)->set_visible(!$is_hidden);
            // bit: 7; mask: 0x00000080; 1 = row has explicit format
            $has_explicit_format = (0x80 & self::get_int4d($record_data, 12)) >> 7;
            // bit: 27-16; mask: 0x0FFF0000; only applies when hasExplicitFormat = 1; index to XF record
            $xf_index = (0xfff0000 & self::get_int4d($record_data, 12)) >> 16;
            if ($has_explicit_format && isset($this->map_cell_xf_index[$xf_index])) {
                $this->php_sheet->get_row_dimension($r + 1)->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
        }
    }
    /**
     * Read RK record
     * This record represents a cell that contains an RK value
     * (encoded integer or floating-point value). If a
     * floating-point value cannot be encoded to an RK value,
     * a NUMBER record will be written. This record replaces the
     * record INTEGER written in BIFF2.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_rk(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to column
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset: 4; size: 2; index to XF record
            $xf_index = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 4; RK value
            $rknum = self::get_int4d($record_data, 6);
            $num_value = self::get_ieee754($rknum);
            $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
            if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                // add style information
                $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
            // add cell
            $cell->set_value_explicit($num_value, Data_Type::TYPE_NUMERIC);
        }
    }
    /**
     * Read LABELSST record
     * This record represents a cell that contains a string. It
     * replaces the LABEL record and RSTRING record used in
     * BIFF2-BIFF5.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_label_sst(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to column
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        $cell = null;
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset: 4; size: 2; index to XF record
            $xf_index = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 4; index to SST record
            $index = self::get_int4d($record_data, 6);
            // add cell
            if (($fmt_runs = $this->sst[$index]['fmtRuns']) && !$this->read_data_only) {
                // then we should treat as rich text
                $rich_text = new Rich_Text();
                $char_pos = 0;
                $sst_count = count($this->sst[$index]['fmtRuns']);
                for ($i = 0; $i <= $sst_count; ++$i) {
                    /** @var mixed[][] $fmtRuns */
                    if (isset($fmt_runs[$i])) {
                        /** @var int[] */
                        $temp = $fmt_runs[$i];
                        $temp = $temp['charPos'];
                        /** @var int $charPos */
                        $text = String_Helper::substring($this->sst[$index]['value'], $char_pos, $temp - $char_pos);
                        $char_pos = $temp;
                    } else {
                        $text = String_Helper::substring($this->sst[$index]['value'], $char_pos, String_Helper::count_characters($this->sst[$index]['value']));
                    }
                    if (String_Helper::count_characters($text) > 0) {
                        if ($i == 0) {
                            // first text run, no style
                            $rich_text->create_text($text);
                        } else {
                            $text_run = $rich_text->create_text_run($text);
                            /** @var int[][] $fmtRuns */
                            if (isset($fmt_runs[$i - 1])) {
                                if ($fmt_runs[$i - 1]['fontIndex'] < 4) {
                                    $font_index = $fmt_runs[$i - 1]['fontIndex'];
                                } else {
                                    // this has to do with that index 4 is omitted in all BIFF versions for some stra          nge reason
                                    // check the OpenOffice documentation of the FONT record
                                    /** @var int */
                                    $temp = $fmt_runs[$i - 1]['fontIndex'];
                                    $font_index = $temp - 1;
                                }
                                if (array_key_exists($font_index, $this->obj_fonts) === false) {
                                    $font_index = count($this->obj_fonts) - 1;
                                }
                                $text_run->set_font(clone $this->obj_fonts[$font_index]);
                            }
                        }
                    }
                }
                if ($this->read_empty_cells || trim($rich_text->get_plain_text()) !== '') {
                    $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
                    $cell->set_value_explicit($rich_text, Data_Type::TYPE_STRING);
                }
            } else if ($this->read_empty_cells || trim($this->sst[$index]['value']) !== '') {
                $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
                $cell->set_value_explicit($this->sst[$index]['value'], Data_Type::TYPE_STRING);
            }
            if (!$this->read_data_only && $cell !== null && isset($this->map_cell_xf_index[$xf_index])) {
                // add style information
                $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
        }
    }
    /**
     * Read MULRK record
     * This record represents a cell range containing RK value
     * cells. All cells are located in the same row.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_mul_rk(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to first column
        $col_first = self::get_u_int2d($record_data, 2);
        // offset: var; size: 2; index to last column
        $col_last = self::get_u_int2d($record_data, $length - 2);
        $columns = $col_last - $col_first + 1;
        // offset within record data
        $offset = 4;
        for ($i = 1; $i <= $columns; ++$i) {
            $column_string = Coordinate::string_from_column_index($col_first + $i);
            // Read cell?
            if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
                // offset: var; size: 2; index to XF record
                $xf_index = self::get_u_int2d($record_data, $offset);
                // offset: var; size: 4; RK value
                $num_value = self::get_ieee754(self::get_int4d($record_data, $offset + 2));
                $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
                if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                    // add style
                    $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
                }
                // add cell value
                $cell->set_value_explicit($num_value, Data_Type::TYPE_NUMERIC);
            }
            $offset += 6;
        }
    }
    /**
     * Read NUMBER record
     * This record represents a cell that contains a
     * floating-point value.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_number(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size 2; index to column
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset 4; size: 2; index to XF record
            $xf_index = self::get_u_int2d($record_data, 4);
            $num_value = self::extract_number(substr($record_data, 6, 8));
            $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
            if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                // add cell style
                $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
            // add cell value
            $cell->set_value_explicit($num_value, Data_Type::TYPE_NUMERIC);
        }
    }
    /**
     * Read FORMULA record + perhaps a following STRING record if formula result is a string
     * This record contains the token array and the result of a
     * formula cell.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_formula(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; row index
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; col index
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        // offset: 20: size: variable; formula structure
        $formula_structure = substr($record_data, 20);
        // offset: 14: size: 2; option flags, recalculate always, recalculate on open etc.
        $options = self::get_u_int2d($record_data, 14);
        // bit: 0; mask: 0x0001; 1 = recalculate always
        // bit: 1; mask: 0x0002; 1 = calculate on open
        // bit: 2; mask: 0x0008; 1 = part of a shared formula
        $is_part_of_shared_formula = (bool) (0x8 & $options);
        // WARNING:
        // We can apparently not rely on $isPartOfSharedFormula. Even when $isPartOfSharedFormula = true
        // the formula data may be ordinary formula data, therefore we need to check
        // explicitly for the tExp token (0x01)
        $is_part_of_shared_formula = $is_part_of_shared_formula && ord($formula_structure[2]) == 0x1;
        if ($is_part_of_shared_formula) {
            // part of shared formula which means there will be a formula with a tExp token and nothing else
            // get the base cell, grab tExp token
            $base_row = self::get_u_int2d($formula_structure, 3);
            $base_col = self::get_u_int2d($formula_structure, 5);
            $this->base_cell = Coordinate::string_from_column_index($base_col + 1) . ($base_row + 1);
        }
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            if ($is_part_of_shared_formula) {
                // formula is added to this cell after the sheet has been read
                $this->shared_formula_parts[$column_string . ($row + 1)] = $this->base_cell;
            }
            // offset: 16: size: 4; not used
            // offset: 4; size: 2; XF index
            $xf_index = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 8; result of the formula
            if (ord($record_data[6]) == 0 && ord($record_data[12]) == 255 && ord($record_data[13]) == 255) {
                // String formula. Result follows in appended STRING record
                $data_type = Data_Type::TYPE_STRING;
                // read possible SHAREDFMLA record
                $code = self::get_u_int2d($this->data, $this->pos);
                if ($code == self::XLS_TYPE_SHAREDFMLA) {
                    $this->read_shared_fmla();
                }
                // read STRING record
                $value = $this->read_string();
            } elseif (ord($record_data[6]) == 1 && ord($record_data[12]) == 255 && ord($record_data[13]) == 255) {
                // Boolean formula. Result is in +2; 0=false, 1=true
                $data_type = Data_Type::TYPE_BOOL;
                $value = (bool) ord($record_data[8]);
            } elseif (ord($record_data[6]) == 2 && ord($record_data[12]) == 255 && ord($record_data[13]) == 255) {
                // Error formula. Error code is in +2
                $data_type = Data_Type::TYPE_ERROR;
                $value = Xls\Error_Code::lookup(ord($record_data[8]));
            } elseif (ord($record_data[6]) == 3 && ord($record_data[12]) == 255 && ord($record_data[13]) == 255) {
                // Formula result is a null string
                $data_type = Data_Type::TYPE_NULL;
                $value = '';
            } else {
                // forumla result is a number, first 14 bytes like _NUMBER record
                $data_type = Data_Type::TYPE_NUMERIC;
                $value = self::extract_number(substr($record_data, 6, 8));
            }
            $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
            if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                // add cell style
                $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
            // store the formula
            if (!$is_part_of_shared_formula) {
                // not part of shared formula
                // add cell value. If we can read formula, populate with formula, otherwise just used cached value
                try {
                    if ($this->version != self::XLS_BIFF8) {
                        throw new Exception('Not BIFF8. Can only read BIFF8 formulas');
                    }
                    $formula = $this->get_formula_from_structure($formula_structure);
                    // get formula in human language
                    $cell->set_value_explicit('=' . $formula, Data_Type::TYPE_FORMULA);
                } catch (Php_Spreadsheet_Exception) {
                    $cell->set_value_explicit($value, $data_type);
                }
            } else if ($this->version == self::XLS_BIFF8) {
                // do nothing at this point, formula id added later in the code
            } else {
                $cell->set_value_explicit($value, $data_type);
            }
            // store the cached calculated value
            $cell->set_calculated_value($value, $data_type === Data_Type::TYPE_NUMERIC);
        }
    }
    /**
     * Read a SHAREDFMLA record. This function just stores the binary shared formula in the reader,
     * which usually contains relative references.
     * These will be used to construct the formula in each shared formula part after the sheet is read.
     */
    protected function read_shared_fmla(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0, size: 6; cell range address of the area used by the shared formula, not used for anything
        //$cellRange = substr($recordData, 0, 6);
        //$cellRange = Xls\Biff5::readBIFF5CellRangeAddressFixed($cellRange); // note: even BIFF8 uses BIFF5 syntax
        // offset: 6, size: 1; not used
        // offset: 7, size: 1; number of existing FORMULA records for this shared formula
        //$no = ord($recordData[7]);
        // offset: 8, size: var; Binary token array of the shared formula
        $formula = substr($record_data, 8);
        // at this point we only store the shared formula for later use
        $this->shared_formulas[$this->base_cell] = $formula;
    }
    /**
     * Read a STRING record from current stream position and advance the stream pointer to next record
     * This record is used for storing result from FORMULA record when it is a string, and
     * it occurs directly after the FORMULA record.
     *
     * @return string The string contents as UTF-8
     */
    protected function read_string(): string
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8) {
            $string = self::read_unicode_string_long($record_data);
            return $string['value'];
        }
        $string = $this->read_byte_string_long($record_data);
        return $string['value'];
    }
    /**
     * Read BOOLERR record
     * This record represents a Boolean value or error value
     * cell.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_bool_err(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; row index
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; column index
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset: 4; size: 2; index to XF record
            $xf_index = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 1; the boolean value or error value
            $bool_err = ord($record_data[6]);
            // offset: 7; size: 1; 0=boolean; 1=error
            $is_error = ord($record_data[7]);
            $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
            switch ($is_error) {
                case 0:
                    // boolean
                    $value = (bool) $bool_err;
                    // add cell value
                    $cell->set_value_explicit($value, Data_Type::TYPE_BOOL);
                    break;
                case 1:
                    // error type
                    $value = Xls\Error_Code::lookup($bool_err);
                    // add cell value
                    $cell->set_value_explicit($value, Data_Type::TYPE_ERROR);
                    break;
            }
            if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                // add cell style
                $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
        }
    }
    /**
     * Read MULBLANK record
     * This record represents a cell range of empty cells. All
     * cells are located in the same row.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_mul_blank(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to first column
        $fc = self::get_u_int2d($record_data, 2);
        // offset: 4; size: 2 x nc; list of indexes to XF records
        // add style information
        if (!$this->read_data_only && $this->read_empty_cells) {
            for ($i = 0; $i < $length / 2 - 3; ++$i) {
                $column_string = Coordinate::string_from_column_index($fc + $i + 1);
                // Read cell?
                if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
                    $xf_index = self::get_u_int2d($record_data, 4 + 2 * $i);
                    if (isset($this->map_cell_xf_index[$xf_index])) {
                        $this->php_sheet->get_cell($column_string . ($row + 1))->set_xf_index($this->map_cell_xf_index[$xf_index]);
                    }
                }
            }
        }
        // offset: 6; size 2; index to last column (not needed)
    }
    /**
     * Read LABEL record
     * This record represents a cell that contains a string. In
     * BIFF8 it is usually replaced by the LABELSST record.
     * Excel still uses this record, if it copies unformatted
     * text cells to the clipboard.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_label(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; index to row
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to column
        $column = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($column + 1);
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset: 4; size: 2; XF index
            $xf_index = self::get_u_int2d($record_data, 4);
            // add cell value
            // todo: what if string is very long? continue record
            if ($this->version == self::XLS_BIFF8) {
                $string = self::read_unicode_string_long(substr($record_data, 6));
                $value = $string['value'];
            } else {
                $string = $this->read_byte_string_long(substr($record_data, 6));
                $value = $string['value'];
            }
            /** @var string $value */
            if ($this->read_empty_cells || trim($value) !== '') {
                $cell = $this->php_sheet->get_cell($column_string . ($row + 1));
                $cell->set_value_explicit($value, Data_Type::TYPE_STRING);
                if (!$this->read_data_only && isset($this->map_cell_xf_index[$xf_index])) {
                    // add cell style
                    $cell->set_xf_index($this->map_cell_xf_index[$xf_index]);
                }
            }
        }
    }
    /**
     * Read BLANK record.
     */
    protected function read_blank(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; row index
        $row = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; col index
        $col = self::get_u_int2d($record_data, 2);
        $column_string = Coordinate::string_from_column_index($col + 1);
        // Read cell?
        if ($this->get_read_filter()->read_cell($column_string, $row + 1, $this->php_sheet->get_title())) {
            // offset: 4; size: 2; XF index
            $xf_index = self::get_u_int2d($record_data, 4);
            // add style information
            if (!$this->read_data_only && $this->read_empty_cells && isset($this->map_cell_xf_index[$xf_index])) {
                $this->php_sheet->get_cell($column_string . ($row + 1))->set_xf_index($this->map_cell_xf_index[$xf_index]);
            }
        }
    }
    /**
     * Read MSODRAWING record.
     */
    protected function read_mso_drawing(): void
    {
        //$length = self::getUInt2d($this->data, $this->pos + 2);
        // get spliced record data
        $spliced_record_data = $this->get_spliced_record_data();
        $record_data = $spliced_record_data['recordData'];
        $this->drawing_data .= String_Helper::convert_to_string($record_data);
    }
    /**
     * Read OBJ record.
     */
    protected function read_obj(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only || $this->version != self::XLS_BIFF8) {
            return;
        }
        // recordData consists of an array of subrecords looking like this:
        //    ft: 2 bytes; ftCmo type (0x15)
        //    cb: 2 bytes; size in bytes of ftCmo data
        //    ot: 2 bytes; Object Type
        //    id: 2 bytes; Object id number
        //    grbit: 2 bytes; Option Flags
        //    data: var; subrecord data
        // for now, we are just interested in the second subrecord containing the object type
        $ft_cmo_type = self::get_u_int2d($record_data, 0);
        $cb_cmo_size = self::get_u_int2d($record_data, 2);
        $ot_obj_type = self::get_u_int2d($record_data, 4);
        $id_obj_id = self::get_u_int2d($record_data, 6);
        $grbit_opts = self::get_u_int2d($record_data, 6);
        $this->objs[] = ['ftCmoType' => $ft_cmo_type, 'cbCmoSize' => $cb_cmo_size, 'otObjType' => $ot_obj_type, 'idObjID' => $id_obj_id, 'grbitOpts' => $grbit_opts];
        $this->text_obj_ref = $id_obj_id;
    }
    /**
     * Read WINDOW2 record.
     */
    protected function read_window2(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; option flags
        $options = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; index to first visible row
        //$firstVisibleRow = self::getUInt2d($recordData, 2);
        // offset: 4; size: 2; index to first visible colum
        //$firstVisibleColumn = self::getUInt2d($recordData, 4);
        $zoomscale_in_page_break_preview = 0;
        $zoomscale_in_normal_view = 0;
        if ($this->version === self::XLS_BIFF8) {
            // offset:  8; size: 2; not used
            // offset: 10; size: 2; cached magnification factor in page break preview (in percent); 0 = Default (60%)
            // offset: 12; size: 2; cached magnification factor in normal view (in percent); 0 = Default (100%)
            // offset: 14; size: 4; not used
            if (!isset($record_data[10])) {
                $zoomscale_in_page_break_preview = 0;
            } else {
                $zoomscale_in_page_break_preview = self::get_u_int2d($record_data, 10);
            }
            if ($zoomscale_in_page_break_preview === 0) {
                $zoomscale_in_page_break_preview = 60;
            }
            if (!isset($record_data[12])) {
                $zoomscale_in_normal_view = 0;
            } else {
                $zoomscale_in_normal_view = self::get_u_int2d($record_data, 12);
            }
            if ($zoomscale_in_normal_view === 0) {
                $zoomscale_in_normal_view = 100;
            }
        }
        // bit: 1; mask: 0x0002; 0 = do not show gridlines, 1 = show gridlines
        $show_gridlines = (bool) ((0x2 & $options) >> 1);
        $this->php_sheet->set_show_gridlines($show_gridlines);
        // bit: 2; mask: 0x0004; 0 = do not show headers, 1 = show headers
        $show_row_col_headers = (bool) ((0x4 & $options) >> 2);
        $this->php_sheet->set_show_row_col_headers($show_row_col_headers);
        // bit: 3; mask: 0x0008; 0 = panes are not frozen, 1 = panes are frozen
        $this->frozen = (bool) ((0x8 & $options) >> 3);
        // bit: 6; mask: 0x0040; 0 = columns from left to right, 1 = columns from right to left
        $this->php_sheet->set_right_to_left((bool) ((0x40 & $options) >> 6));
        // bit: 10; mask: 0x0400; 0 = sheet not active, 1 = sheet active
        $is_active = (bool) ((0x400 & $options) >> 10);
        if ($is_active) {
            $this->spreadsheet->set_active_sheet_index($this->spreadsheet->get_index($this->php_sheet));
            $this->active_sheet_set = true;
        }
        // bit: 11; mask: 0x0800; 0 = normal view, 1 = page break view
        $is_page_break_preview = (bool) ((0x800 & $options) >> 11);
        //FIXME: set $firstVisibleRow and $firstVisibleColumn
        if ($this->php_sheet->get_sheet_view()->get_view() !== Sheet_View::SHEETVIEW_PAGE_LAYOUT) {
            //NOTE: this setting is inferior to page layout view(Excel2007-)
            $view = $is_page_break_preview ? Sheet_View::SHEETVIEW_PAGE_BREAK_PREVIEW : Sheet_View::SHEETVIEW_NORMAL;
            $this->php_sheet->get_sheet_view()->set_view($view);
            if ($this->version === self::XLS_BIFF8) {
                $zoom_scale = $is_page_break_preview ? $zoomscale_in_page_break_preview : $zoomscale_in_normal_view;
                $this->php_sheet->get_sheet_view()->set_zoom_scale($zoom_scale);
                $this->php_sheet->get_sheet_view()->set_zoom_scale_normal($zoomscale_in_normal_view);
            }
        }
    }
    /**
     * Read PLV Record(Created by Excel2007 or upper).
     */
    protected function read_page_layout_view(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; rt
        //->ignore
        //$rt = self::getUInt2d($recordData, 0);
        // offset: 2; size: 2; grbitfr
        //->ignore
        //$grbitFrt = self::getUInt2d($recordData, 2);
        // offset: 4; size: 8; reserved
        //->ignore
        // offset: 12; size 2; zoom scale
        $w_scale_plv = self::get_u_int2d($record_data, 12);
        // offset: 14; size 2; grbit
        $grbit = self::get_u_int2d($record_data, 14);
        // decomprise grbit
        $f_page_layout_view = $grbit & 0x1;
        //$fRulerVisible = ($grbit >> 1) & 0x01; //no support
        //$fWhitespaceHidden = ($grbit >> 3) & 0x01; //no support
        if ($f_page_layout_view === 1) {
            $this->php_sheet->get_sheet_view()->set_view(Sheet_View::SHEETVIEW_PAGE_LAYOUT);
            $this->php_sheet->get_sheet_view()->set_zoom_scale($w_scale_plv);
            //set by Excel2007 only if SHEETVIEW_PAGE_LAYOUT
        }
        //otherwise, we cannot know whether SHEETVIEW_PAGE_LAYOUT or SHEETVIEW_PAGE_BREAK_PREVIEW.
    }
    /**
     * Read SCL record.
     */
    protected function read_scl(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // offset: 0; size: 2; numerator of the view magnification
        $numerator = self::get_u_int2d($record_data, 0);
        // offset: 2; size: 2; numerator of the view magnification
        $denumerator = self::get_u_int2d($record_data, 2);
        // set the zoom scale (in percent)
        $this->php_sheet->get_sheet_view()->set_zoom_scale($numerator * 100 / $denumerator);
    }
    /**
     * Read PANE record.
     */
    protected function read_pane(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; position of vertical split
            $px = self::get_u_int2d($record_data, 0);
            // offset: 2; size: 2; position of horizontal split
            $py = self::get_u_int2d($record_data, 2);
            // offset: 4; size: 2; top most visible row in the bottom pane
            $rw_top = self::get_u_int2d($record_data, 4);
            // offset: 6; size: 2; first visible left column in the right pane
            $col_left = self::get_u_int2d($record_data, 6);
            if ($this->frozen) {
                // frozen panes
                $cell = Coordinate::string_from_column_index($px + 1) . ($py + 1);
                $top_left_cell = Coordinate::string_from_column_index($col_left + 1) . ($rw_top + 1);
                $this->php_sheet->freeze_pane($cell, $top_left_cell);
            }
            // unfrozen panes; split windows; not supported by PhpSpreadsheet core
        }
    }
    private const REGEX_WHOLE_COLUMN = '/^([A-Z]+1\:[A-Z]+)' . '(' . Address_Range::MAX_ROW_XLS_OLD . '|' . Address_Range::MAX_ROW_XLS . ')' . '$/';
    private const REGEX_WHOLE_COLUMN_REPLACE = '${1}' . Address_Range::MAX_ROW;
    private const REGEX_WHOLE_ROW = '/^(A\d+\:)' . Address_Range::MAX_COLUMN_XLS . '(\d+)$/';
    private const REGEX_WHOLE_ROW_REPLACE = '${1}' . Address_Range::MAX_COLUMN . '${2}';
    /**
     * Read SELECTION record. There is one such record for each pane in the sheet.
     */
    protected function read_selection(): string
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        $selected_cells = '';
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 1; pane identifier
            //$paneId = ord($recordData[0]);
            // offset: 1; size: 2; index to row of the active cell
            //$r = self::getUInt2d($recordData, 1);
            // offset: 3; size: 2; index to column of the active cell
            //$c = self::getUInt2d($recordData, 3);
            // offset: 5; size: 2; index into the following cell range list to the
            //  entry that contains the active cell
            //$index = self::getUInt2d($recordData, 5);
            // offset: 7; size: var; cell range address list containing all selected cell ranges
            $data = substr($record_data, 7);
            $cell_range_address_list = Xls\Biff5::read_biff5cell_range_address_list($data);
            // note: also BIFF8 uses BIFF5 syntax
            $selected_cells = $cell_range_address_list['cellRangeAddresses'][0];
            // first row '1' + last row '16384' or '65536' indicates that full column is selected (apparently also in BIFF8!)
            if (Preg::is_match(self::REGEX_WHOLE_COLUMN, $selected_cells)) {
                $selected_cells = Preg::replace(self::REGEX_WHOLE_COLUMN, self::REGEX_WHOLE_COLUMN_REPLACE, $selected_cells);
            }
            // first column 'A' + last column 'IV' indicates that full row is selected
            if (Preg::is_match(self::REGEX_WHOLE_ROW, $selected_cells)) {
                $selected_cells = Preg::replace(self::REGEX_WHOLE_ROW, self::REGEX_WHOLE_ROW_REPLACE, $selected_cells);
            }
            $this->php_sheet->set_selected_cells($selected_cells);
        }
        return $selected_cells;
    }
    private function include_cell_range_filtered(string $cell_range_address): bool
    {
        $include_cell_range = false;
        $range_boundaries = Coordinate::get_range_boundaries($cell_range_address);
        String_Helper::string_increment($range_boundaries[1][0]);
        for ($row = $range_boundaries[0][1]; $row <= $range_boundaries[1][1]; ++$row) {
            for ($column = $range_boundaries[0][0]; $column != $range_boundaries[1][0]; String_Helper::string_increment($column)) {
                if ($this->get_read_filter()->read_cell($column, $row, $this->php_sheet->get_title())) {
                    $include_cell_range = true;
                    break 2;
                }
            }
        }
        return $include_cell_range;
    }
    /**
     * MERGEDCELLS.
     *
     * This record contains the addresses of merged cell ranges
     * in the current sheet.
     *
     * --    "OpenOffice.org's Documentation of the Microsoft
     *         Excel File Format"
     */
    protected function read_merged_cells(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->version == self::XLS_BIFF8 && !$this->read_data_only) {
            $cell_range_address_list = Xls\Biff8::read_biff8cell_range_address_list($record_data);
            foreach ($cell_range_address_list['cellRangeAddresses'] as $cell_range_address) {
                /** @var string $cellRangeAddress */
                if (str_contains($cell_range_address, ':') && $this->include_cell_range_filtered($cell_range_address)) {
                    $this->php_sheet->merge_cells($cell_range_address, Worksheet::MERGE_CELL_CONTENT_HIDE);
                }
            }
        }
    }
    /**
     * Read HYPERLINK record.
     */
    protected function read_hyper_link(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer forward to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 8; cell range address of all cells containing this hyperlink
            try {
                $cell_range = Xls\Biff8::read_biff8cell_range_address_fixed($record_data);
            } catch (Php_Spreadsheet_Exception) {
                return;
            }
            // offset: 8, size: 16; GUID of StdLink
            // offset: 24, size: 4; unknown value
            // offset: 28, size: 4; option flags
            // bit: 0; mask: 0x00000001; 0 = no link or extant, 1 = file link or URL
            $is_file_link_or_url = (0x1 & self::get_u_int2d($record_data, 28)) >> 0;
            // bit: 1; mask: 0x00000002; 0 = relative path, 1 = absolute path or URL
            //$isAbsPathOrUrl = (0x00000001 & self::getUInt2d($recordData, 28)) >> 1;
            // bit: 2 (and 4); mask: 0x00000014; 0 = no description
            $has_desc = (0x14 & self::get_u_int2d($record_data, 28)) >> 2;
            // bit: 3; mask: 0x00000008; 0 = no text, 1 = has text
            $has_text = (0x8 & self::get_u_int2d($record_data, 28)) >> 3;
            // bit: 7; mask: 0x00000080; 0 = no target frame, 1 = has target frame
            $has_frame = (0x80 & self::get_u_int2d($record_data, 28)) >> 7;
            // bit: 8; mask: 0x00000100; 0 = file link or URL, 1 = UNC path (inc. server name)
            $is_unc = (0x100 & self::get_u_int2d($record_data, 28)) >> 8;
            // offset within record data
            $offset = 32;
            if ($has_desc) {
                // offset: 32; size: var; character count of description text
                $dl = self::get_int4d($record_data, 32);
                // offset: 36; size: var; character array of description text, no Unicode string header, always 16-bit characters, zero terminated
                //$desc = self::encodeUTF16(substr($recordData, 36, 2 * ($dl - 1)), false);
                $offset += 4 + 2 * $dl;
            }
            if ($has_frame) {
                $fl = self::get_int4d($record_data, $offset);
                $offset += 4 + 2 * $fl;
            }
            // detect type of hyperlink (there are 4 types)
            $hyperlink_type = null;
            if ($is_unc) {
                $hyperlink_type = 'UNC';
            } elseif (!$is_file_link_or_url) {
                $hyperlink_type = 'workbook';
            } elseif (ord($record_data[$offset]) == 0x3) {
                $hyperlink_type = 'local';
            } elseif (ord($record_data[$offset]) == 0xe0) {
                $hyperlink_type = 'URL';
            }
            switch ($hyperlink_type) {
                case 'URL':
                    // section 5.58.2: Hyperlink containing a URL
                    // e.g. http://example.org/index.php
                    // offset: var; size: 16; GUID of URL Moniker
                    $offset += 16;
                    // offset: var; size: 4; size (in bytes) of character array of the URL including trailing zero word
                    $us = self::get_int4d($record_data, $offset);
                    $offset += 4;
                    // offset: var; size: $us; character array of the URL, no Unicode string header, always 16-bit characters, zero-terminated
                    $url = self::encode_utf16(substr($record_data, $offset, $us - 2), false);
                    $null_offset = strpos($url, chr(0x0));
                    if ($null_offset) {
                        $url = substr($url, 0, $null_offset);
                    }
                    $url .= $has_text ? '#' : '';
                    $offset += $us;
                    break;
                case 'local':
                    // section 5.58.3: Hyperlink to local file
                    // examples:
                    //   mydoc.txt
                    //   ../../somedoc.xls#Sheet!A1
                    // offset: var; size: 16; GUI of File Moniker
                    $offset += 16;
                    // offset: var; size: 2; directory up-level count.
                    $up_level_count = self::get_u_int2d($record_data, $offset);
                    $offset += 2;
                    // offset: var; size: 4; character count of the shortened file path and name, including trailing zero word
                    $sl = self::get_int4d($record_data, $offset);
                    $offset += 4;
                    // offset: var; size: sl; character array of the shortened file path and name in 8.3-DOS-format (compressed Unicode string)
                    $shortened_file_path = substr($record_data, $offset, $sl);
                    $shortened_file_path = self::encode_utf16($shortened_file_path, true);
                    $shortened_file_path = substr($shortened_file_path, 0, -1);
                    // remove trailing zero
                    $offset += $sl;
                    // offset: var; size: 24; unknown sequence
                    $offset += 24;
                    // extended file path
                    // offset: var; size: 4; size of the following file link field including string lenth mark
                    $sz = self::get_int4d($record_data, $offset);
                    $offset += 4;
                    $extended_file_path = '';
                    // only present if $sz > 0
                    if ($sz > 0) {
                        // offset: var; size: 4; size of the character array of the extended file path and name
                        $xl = self::get_int4d($record_data, $offset);
                        $offset += 4;
                        // offset: var; size 2; unknown
                        $offset += 2;
                        // offset: var; size $xl; character array of the extended file path and name.
                        $extended_file_path = substr($record_data, $offset, $xl);
                        $extended_file_path = self::encode_utf16($extended_file_path, false);
                        $offset += $xl;
                    }
                    // construct the path
                    $url = str_repeat('..\\', $up_level_count);
                    $url .= $sz > 0 ? $extended_file_path : $shortened_file_path;
                    // use extended path if available
                    $url .= $has_text ? '#' : '';
                    break;
                case 'UNC':
                default:
                    // section 5.58.4: Hyperlink to a File with UNC (Universal Naming Convention) Path
                    // todo: implement
                    return;
                case 'workbook':
                    // section 5.58.5: Hyperlink to the Current Workbook
                    // e.g. Sheet2!B1:C2, stored in text mark field
                    $url = 'sheet://';
                    break;
            }
            if ($has_text) {
                // offset: var; size: 4; character count of text mark including trailing zero word
                $tl = self::get_int4d($record_data, $offset);
                $offset += 4;
                // offset: var; size: var; character array of the text mark without the # sign, no Unicode header, always 16-bit characters, zero-terminated
                $text = self::encode_utf16(substr($record_data, $offset, 2 * ($tl - 1)), false);
                $url .= $text;
            }
            // apply the hyperlink to all the relevant cells
            foreach (Coordinate::extract_all_cell_references_in_range($cell_range) as $coordinate) {
                $this->php_sheet->get_cell($coordinate)->get_hyper_link()->set_url($url);
            }
        }
    }
    /**
     * Read DATAVALIDATIONS record.
     */
    protected function read_data_validations(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        //$recordData = $this->readRecordData($this->data, $this->pos + 4, $length);
        // move stream pointer forward to next record
        $this->pos += 4 + $length;
    }
    /**
     * Read DATAVALIDATION record.
     */
    protected function read_data_validation(): void
    {
        (new Xls\Data_Validation_Helper())->read_data_validation2($this);
    }
    /**
     * Read SHEETLAYOUT record. Stores sheet tab color information.
     */
    protected function read_sheet_layout(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if (!$this->read_data_only) {
            // offset: 0; size: 2; repeated record identifier 0x0862
            // offset: 2; size: 10; not used
            // offset: 12; size: 4; size of record data
            // Excel 2003 uses size of 0x14 (documented), Excel 2007 uses size of 0x28 (not documented?)
            $sz = self::get_int4d($record_data, 12);
            switch ($sz) {
                case 0x14:
                    // offset: 16; size: 2; color index for sheet tab
                    $color_index = self::get_u_int2d($record_data, 16);
                    /** @var string[] */
                    $color = Xls\Color::map($color_index, $this->palette, $this->version);
                    $this->php_sheet->get_tab_color()->set_rgb($color['rgb']);
                    break;
                case 0x28:
                    // TODO: Investigate structure for .xls SHEETLAYOUT record as saved by MS Office Excel 2007
                    return;
            }
        }
    }
    /**
     * Read SHEETPROTECTION record (FEATHEADR).
     */
    protected function read_sheet_protection(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        if ($this->read_data_only) {
            return;
        }
        // offset: 0; size: 2; repeated record header
        // offset: 2; size: 2; FRT cell reference flag (=0 currently)
        // offset: 4; size: 8; Currently not used and set to 0
        // offset: 12; size: 2; Shared feature type index (2=Enhanced Protetion, 4=SmartTag)
        $isf = self::get_u_int2d($record_data, 12);
        if ($isf != 2) {
            return;
        }
        // offset: 14; size: 1; =1 since this is a feat header
        // offset: 15; size: 4; size of rgbHdrSData
        // rgbHdrSData, assume "Enhanced Protection"
        // offset: 19; size: 2; option flags
        $options = self::get_u_int2d($record_data, 19);
        // bit: 0; mask 0x0001; 1 = user may edit objects, 0 = users must not edit objects
        // Note - do not negate $bool
        $bool = (0x1 & $options) >> 0;
        $this->php_sheet->get_protection()->set_objects((bool) $bool);
        // bit: 1; mask 0x0002; edit scenarios
        // Note - do not negate $bool
        $bool = (0x2 & $options) >> 1;
        $this->php_sheet->get_protection()->set_scenarios((bool) $bool);
        // bit: 2; mask 0x0004; format cells
        $bool = (0x4 & $options) >> 2;
        $this->php_sheet->get_protection()->set_format_cells(!$bool);
        // bit: 3; mask 0x0008; format columns
        $bool = (0x8 & $options) >> 3;
        $this->php_sheet->get_protection()->set_format_columns(!$bool);
        // bit: 4; mask 0x0010; format rows
        $bool = (0x10 & $options) >> 4;
        $this->php_sheet->get_protection()->set_format_rows(!$bool);
        // bit: 5; mask 0x0020; insert columns
        $bool = (0x20 & $options) >> 5;
        $this->php_sheet->get_protection()->set_insert_columns(!$bool);
        // bit: 6; mask 0x0040; insert rows
        $bool = (0x40 & $options) >> 6;
        $this->php_sheet->get_protection()->set_insert_rows(!$bool);
        // bit: 7; mask 0x0080; insert hyperlinks
        $bool = (0x80 & $options) >> 7;
        $this->php_sheet->get_protection()->set_insert_hyperlinks(!$bool);
        // bit: 8; mask 0x0100; delete columns
        $bool = (0x100 & $options) >> 8;
        $this->php_sheet->get_protection()->set_delete_columns(!$bool);
        // bit: 9; mask 0x0200; delete rows
        $bool = (0x200 & $options) >> 9;
        $this->php_sheet->get_protection()->set_delete_rows(!$bool);
        // bit: 10; mask 0x0400; select locked cells
        // Note that this is opposite of most of above.
        $bool = (0x400 & $options) >> 10;
        $this->php_sheet->get_protection()->set_select_locked_cells((bool) $bool);
        // bit: 11; mask 0x0800; sort cell range
        $bool = (0x800 & $options) >> 11;
        $this->php_sheet->get_protection()->set_sort(!$bool);
        // bit: 12; mask 0x1000; auto filter
        $bool = (0x1000 & $options) >> 12;
        $this->php_sheet->get_protection()->set_auto_filter(!$bool);
        // bit: 13; mask 0x2000; pivot tables
        $bool = (0x2000 & $options) >> 13;
        $this->php_sheet->get_protection()->set_pivot_tables(!$bool);
        // bit: 14; mask 0x4000; select unlocked cells
        // Note that this is opposite of most of above.
        $bool = (0x4000 & $options) >> 14;
        $this->php_sheet->get_protection()->set_select_unlocked_cells((bool) $bool);
        // offset: 21; size: 2; not used
    }
    /**
     * Read RANGEPROTECTION record
     * Reading of this record is based on Microsoft Office Excel 97-2000 Binary File Format Specification,
     * where it is referred to as FEAT record.
     */
    protected function read_range_protection(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // move stream pointer to next record
        $this->pos += 4 + $length;
        // local pointer in record data
        $offset = 0;
        if (!$this->read_data_only) {
            $offset += 12;
            // offset: 12; size: 2; shared feature type, 2 = enhanced protection, 4 = smart tag
            $isf = self::get_u_int2d($record_data, 12);
            if ($isf != 2) {
                // we only read FEAT records of type 2
                return;
            }
            $offset += 2;
            $offset += 5;
            // offset: 19; size: 2; count of ref ranges this feature is on
            $cref = self::get_u_int2d($record_data, 19);
            $offset += 2;
            $offset += 6;
            // offset: 27; size: 8 * $cref; list of cell ranges (like in hyperlink record)
            $cell_ranges = [];
            for ($i = 0; $i < $cref; ++$i) {
                try {
                    $cell_range = Xls\Biff8::read_biff8cell_range_address_fixed(substr($record_data, 27 + 8 * $i, 8));
                } catch (Php_Spreadsheet_Exception) {
                    return;
                }
                $cell_ranges[] = $cell_range;
                $offset += 8;
            }
            // offset: var; size: var; variable length of feature specific data
            //$rgbFeat = substr($recordData, $offset);
            $offset += 4;
            // offset: var; size: 4; the encrypted password (only 16-bit although field is 32-bit)
            $w_password = self::get_int4d($record_data, $offset);
            $offset += 4;
            // Apply range protection to sheet
            if ($cell_ranges) {
                $this->php_sheet->protect_cells(implode(' ', $cell_ranges), $w_password === 0 ? '' : strtoupper(dechex($w_password)), true);
            }
        }
    }
    /**
     * Read a free CONTINUE record. Free CONTINUE record may be a camouflaged MSODRAWING record
     * When MSODRAWING data on a sheet exceeds 8224 bytes, CONTINUE records are used instead. Undocumented.
     * In this case, we must treat the CONTINUE record as a MSODRAWING record.
     */
    protected function read_continue(): void
    {
        $length = self::get_u_int2d($this->data, $this->pos + 2);
        $record_data = $this->read_record_data($this->data, $this->pos + 4, $length);
        // check if we are reading drawing data
        // this is in case a free CONTINUE record occurs in other circumstances we are unaware of
        if ($this->drawing_data == '') {
            // move stream pointer to next record
            $this->pos += 4 + $length;
            return;
        }
        // check if record data is at least 4 bytes long, otherwise there is no chance this is MSODRAWING data
        if ($length < 4) {
            // move stream pointer to next record
            $this->pos += 4 + $length;
            return;
        }
        // dirty check to see if CONTINUE record could be a camouflaged MSODRAWING record
        // look inside CONTINUE record to see if it looks like a part of an Escher stream
        // we know that Escher stream may be split at least at
        //        0xF003 MsofbtSpgrContainer
        //        0xF004 MsofbtSpContainer
        //        0xF00D MsofbtClientTextbox
        $valid_split_points = [0xf003, 0xf004, 0xf00d];
        // add identifiers if we find more
        $split_point = self::get_u_int2d($record_data, 2);
        if (in_array($split_point, $valid_split_points)) {
            // get spliced record data (and move pointer to next record)
            $spliced_record_data = $this->get_spliced_record_data();
            $this->drawing_data .= String_Helper::convert_to_string($spliced_record_data['recordData']);
            return;
        }
        // move stream pointer to next record
        $this->pos += 4 + $length;
    }
    /**
     * Reads a record from current position in data stream and continues reading data as long as CONTINUE
     * records are found. Splices the record data pieces and returns the combined string as if record data
     * is in one piece.
     * Moves to next current position in data stream to start of next record different from a CONtINUE record.
     *
     * @return mixed[]
     */
    private function get_spliced_record_data(): array
    {
        $data = '';
        $splice_offsets = [];
        $i = 0;
        $splice_offsets[0] = 0;
        do {
            ++$i;
            // offset: 0; size: 2; identifier
            //$identifier = self::getUInt2d($this->data, $this->pos);
            // offset: 2; size: 2; length
            $length = self::get_u_int2d($this->data, $this->pos + 2);
            $data .= $this->read_record_data($this->data, $this->pos + 4, $length);
            $splice_offsets[$i] = $splice_offsets[$i - 1] + $length;
            $this->pos += 4 + $length;
            $next_identifier = self::get_u_int2d($this->data, $this->pos);
        } while ($next_identifier == self::XLS_TYPE_CONTINUE);
        return ['recordData' => $data, 'spliceOffsets' => $splice_offsets];
    }
    /**
     * Convert formula structure into human readable Excel formula like 'A3+A5*5'.
     *
     * @param string $formulaStructure The complete binary data for the formula
     * @param string $baseCell Base cell, only needed when formula contains tRefN tokens, e.g. with shared formulas
     *
     * @return string Human readable formula
     */
    protected function get_formula_from_structure(string $formula_structure, string $base_cell = 'A1'): string
    {
        // offset: 0; size: 2; size of the following formula data
        $sz = self::get_u_int2d($formula_structure, 0);
        // offset: 2; size: sz
        $formula_data = substr($formula_structure, 2, $sz);
        // offset: 2 + sz; size: variable (optional)
        if (strlen($formula_structure) > 2 + $sz) {
            $additional_data = substr($formula_structure, 2 + $sz);
        } else {
            $additional_data = '';
        }
        return $this->get_formula_from_data($formula_data, $additional_data, $base_cell);
    }
    /**
     * Take formula data and additional data for formula and return human readable formula.
     *
     * @param string $formulaData The binary data for the formula itself
     * @param string $additionalData Additional binary data going with the formula
     * @param string $baseCell Base cell, only needed when formula contains tRefN tokens, e.g. with shared formulas
     *
     * @return string Human readable formula
     */
    private function get_formula_from_data(string $formula_data, string $additional_data = '', string $base_cell = 'A1'): string
    {
        // start parsing the formula data
        $tokens = [];
        while ($formula_data !== '' && $token = $this->get_next_token($formula_data, $base_cell)) {
            $tokens[] = $token;
            /** @var int[] $token */
            $formula_data = substr($formula_data, $token['size']);
        }
        return $this->create_formula_from_tokens($tokens, $additional_data);
    }
    /**
     * Take array of tokens together with additional data for formula and return human readable formula.
     *
     * @param mixed[][] $tokens
     * @param string $additionalData Additional binary data going with the formula
     *
     * @return string Human readable formula
     */
    private function create_formula_from_tokens(array $tokens, string $additional_data): string
    {
        // empty formula?
        if (empty($tokens)) {
            return '';
        }
        $formula_strings = [];
        foreach ($tokens as $token) {
            // initialize spaces
            $space0 ??= '';
            // spaces before next token, not tParen
            $space1 ??= '';
            // carriage returns before next token, not tParen
            $space2 ??= '';
            // spaces before opening parenthesis
            $space3 ??= '';
            // carriage returns before opening parenthesis
            $space4 ??= '';
            // spaces before closing parenthesis
            $space5 ??= '';
            // carriage returns before closing parenthesis
            /** @var string */
            $token_data = $token['data'] ?? '';
            switch ($token['name']) {
                case 'tAdd':
                // addition
                case 'tConcat':
                // addition
                case 'tDiv':
                // division
                case 'tEQ':
                // equality
                case 'tGE':
                // greater than or equal
                case 'tGT':
                // greater than
                case 'tIsect':
                // intersection
                case 'tLE':
                // less than or equal
                case 'tList':
                // less than or equal
                case 'tLT':
                // less than
                case 'tMul':
                // multiplication
                case 'tNE':
                // multiplication
                case 'tPower':
                // power
                case 'tRange':
                // range
                case 'tSub':
                    // subtraction
                    $op2 = array_pop($formula_strings);
                    $op1 = array_pop($formula_strings);
                    $formula_strings[] = "{$op1}{$space1}{$space0}{$token_data}{$op2}";
                    unset($space0, $space1);
                    break;
                case 'tUplus':
                // unary plus
                case 'tUminus':
                    // unary minus
                    $op = array_pop($formula_strings);
                    $formula_strings[] = "{$space1}{$space0}{$token_data}{$op}";
                    unset($space0, $space1);
                    break;
                case 'tPercent':
                    // percent sign
                    $op = array_pop($formula_strings);
                    $formula_strings[] = "{$op}{$space1}{$space0}{$token_data}";
                    unset($space0, $space1);
                    break;
                case 'tAttrVolatile':
                // indicates volatile function
                case 'tAttrIf':
                case 'tAttrSkip':
                case 'tAttrChoose':
                    // token is only important for Excel formula evaluator
                    // do nothing
                    break;
                case 'tAttrSpace':
                    // space / carriage return
                    // space will be used when next token arrives, do not alter formulaString stack
                    /** @var string[][] $token */
                    switch ($token['data']['spacetype']) {
                        case 'type0':
                            $space0 = str_repeat(' ', (int) $token['data']['spacecount']);
                            break;
                        case 'type1':
                            $space1 = str_repeat("\n", (int) $token['data']['spacecount']);
                            break;
                        case 'type2':
                            $space2 = str_repeat(' ', (int) $token['data']['spacecount']);
                            break;
                        case 'type3':
                            $space3 = str_repeat("\n", (int) $token['data']['spacecount']);
                            break;
                        case 'type4':
                            $space4 = str_repeat(' ', (int) $token['data']['spacecount']);
                            break;
                        case 'type5':
                            $space5 = str_repeat("\n", (int) $token['data']['spacecount']);
                            break;
                    }
                    break;
                case 'tAttrSum':
                    // SUM function with one parameter
                    $op = array_pop($formula_strings);
                    $formula_strings[] = "{$space1}{$space0}SUM({$op})";
                    unset($space0, $space1);
                    break;
                case 'tFunc':
                // function with fixed number of arguments
                case 'tFuncV':
                    // function with variable number of arguments
                    /** @var string[] */
                    $temp1 = $token['data'];
                    $temp2 = $temp1['function'];
                    if ($temp2 != '') {
                        // normal function
                        $ops = [];
                        // array of operators
                        $temp3 = (int) $temp1['args'];
                        for ($i = 0; $i < $temp3; ++$i) {
                            $ops[] = array_pop($formula_strings);
                        }
                        $ops = array_reverse($ops);
                        $formula_strings[] = "{$space1}{$space0}{$temp2}(" . implode(',', $ops) . ')';
                        unset($space0, $space1);
                    } else {
                        // add-in function
                        $ops = [];
                        // array of operators
                        /** @var int[] */
                        $temp = $token['data'];
                        for ($i = 0; $i < $temp['args'] - 1; ++$i) {
                            $ops[] = array_pop($formula_strings);
                        }
                        $ops = array_reverse($ops);
                        $function = array_pop($formula_strings);
                        $formula_strings[] = "{$space1}{$space0}{$function}(" . implode(',', $ops) . ')';
                        unset($space0, $space1);
                    }
                    break;
                case 'tParen':
                    // parenthesis
                    $expression = array_pop($formula_strings);
                    $formula_strings[] = "{$space3}{$space2}({$expression}{$space5}{$space4})";
                    unset($space2, $space3, $space4, $space5);
                    break;
                case 'tArray':
                    // array constant
                    $constant_array = Xls\Biff8::read_biff8constant_array($additional_data);
                    $formula_strings[] = $space1 . $space0 . $constant_array['value'];
                    $additional_data = substr($additional_data, $constant_array['size']);
                    // bite of chunk of additional data
                    unset($space0, $space1);
                    break;
                case 'tMemArea':
                    // bite off chunk of additional data
                    $cell_range_address_list = Xls\Biff8::read_biff8cell_range_address_list($additional_data);
                    $additional_data = substr($additional_data, $cell_range_address_list['size']);
                    $formula_strings[] = "{$space1}{$space0}{$token_data}";
                    unset($space0, $space1);
                    break;
                case 'tArea':
                // cell range address
                case 'tBool':
                // boolean
                case 'tErr':
                // error code
                case 'tInt':
                // integer
                case 'tMemErr':
                case 'tMemFunc':
                case 'tMissArg':
                case 'tName':
                case 'tNameX':
                case 'tNum':
                // number
                case 'tRef':
                // single cell reference
                case 'tRef3d':
                // 3d cell reference
                case 'tArea3d':
                // 3d cell range reference
                case 'tRefN':
                case 'tAreaN':
                case 'tStr':
                    // string
                    $formula_strings[] = "{$space1}{$space0}{$token_data}";
                    unset($space0, $space1);
                    break;
            }
        }
        return $formula_strings[0];
    }
    /**
     * Fetch next token from binary formula data.
     *
     * @param string $formulaData Formula data
     * @param string $baseCell Base cell, only needed when formula contains tRefN tokens, e.g. with shared formulas
     *
     * @return mixed[]
     */
    private function get_next_token(string $formula_data, string $base_cell = 'A1'): array
    {
        // offset: 0; size: 1; token id
        $id = ord($formula_data[0]);
        // token id
        $name = false;
        // initialize token name
        switch ($id) {
            case 0x3:
                $name = 'tAdd';
                $size = 1;
                $data = '+';
                break;
            case 0x4:
                $name = 'tSub';
                $size = 1;
                $data = '-';
                break;
            case 0x5:
                $name = 'tMul';
                $size = 1;
                $data = '*';
                break;
            case 0x6:
                $name = 'tDiv';
                $size = 1;
                $data = '/';
                break;
            case 0x7:
                $name = 'tPower';
                $size = 1;
                $data = '^';
                break;
            case 0x8:
                $name = 'tConcat';
                $size = 1;
                $data = '&';
                break;
            case 0x9:
                $name = 'tLT';
                $size = 1;
                $data = '<';
                break;
            case 0xa:
                $name = 'tLE';
                $size = 1;
                $data = '<=';
                break;
            case 0xb:
                $name = 'tEQ';
                $size = 1;
                $data = '=';
                break;
            case 0xc:
                $name = 'tGE';
                $size = 1;
                $data = '>=';
                break;
            case 0xd:
                $name = 'tGT';
                $size = 1;
                $data = '>';
                break;
            case 0xe:
                $name = 'tNE';
                $size = 1;
                $data = '<>';
                break;
            case 0xf:
                $name = 'tIsect';
                $size = 1;
                $data = ' ';
                break;
            case 0x10:
                $name = 'tList';
                $size = 1;
                $data = ',';
                break;
            case 0x11:
                $name = 'tRange';
                $size = 1;
                $data = ':';
                break;
            case 0x12:
                $name = 'tUplus';
                $size = 1;
                $data = '+';
                break;
            case 0x13:
                $name = 'tUminus';
                $size = 1;
                $data = '-';
                break;
            case 0x14:
                $name = 'tPercent';
                $size = 1;
                $data = '%';
                break;
            case 0x15:
                //    parenthesis
                $name = 'tParen';
                $size = 1;
                $data = null;
                break;
            case 0x16:
                //    missing argument
                $name = 'tMissArg';
                $size = 1;
                $data = '';
                break;
            case 0x17:
                //    string
                $name = 'tStr';
                // offset: 1; size: var; Unicode string, 8-bit string length
                $string = self::read_unicode_string_short(substr($formula_data, 1));
                $size = 1 + $string['size'];
                $data = self::utf8to_excel_double_quoted($string['value']);
                break;
            case 0x19:
                //    Special attribute
                // offset: 1; size: 1; attribute type flags:
                switch (ord($formula_data[1])) {
                    case 0x1:
                        $name = 'tAttrVolatile';
                        $size = 4;
                        $data = null;
                        break;
                    case 0x2:
                        $name = 'tAttrIf';
                        $size = 4;
                        $data = null;
                        break;
                    case 0x4:
                        $name = 'tAttrChoose';
                        // offset: 2; size: 2; number of choices in the CHOOSE function ($nc, number of parameters decreased by 1)
                        $nc = self::get_u_int2d($formula_data, 2);
                        // offset: 4; size: 2 * $nc
                        // offset: 4 + 2 * $nc; size: 2
                        $size = 2 * $nc + 6;
                        $data = null;
                        break;
                    case 0x8:
                        $name = 'tAttrSkip';
                        $size = 4;
                        $data = null;
                        break;
                    case 0x10:
                        $name = 'tAttrSum';
                        $size = 4;
                        $data = null;
                        break;
                    case 0x40:
                    case 0x41:
                        $name = 'tAttrSpace';
                        $size = 4;
                        // offset: 2; size: 2; space type and position
                        $spacetype = match (ord($formula_data[2])) {
                            0x0 => 'type0',
                            0x1 => 'type1',
                            0x2 => 'type2',
                            0x3 => 'type3',
                            0x4 => 'type4',
                            0x5 => 'type5',
                            default => throw new Exception('Unrecognized space type in tAttrSpace token'),
                        };
                        // offset: 3; size: 1; number of inserted spaces/carriage returns
                        $spacecount = ord($formula_data[3]);
                        $data = ['spacetype' => $spacetype, 'spacecount' => $spacecount];
                        break;
                    default:
                        throw new Exception('Unrecognized attribute flag in tAttr token');
                }
                break;
            case 0x1c:
                //    error code
                // offset: 1; size: 1; error code
                $name = 'tErr';
                $size = 2;
                $data = Xls\Error_Code::lookup(ord($formula_data[1]));
                break;
            case 0x1d:
                //    boolean
                // offset: 1; size: 1; 0 = false, 1 = true;
                $name = 'tBool';
                $size = 2;
                $data = ord($formula_data[1]) ? 'TRUE' : 'FALSE';
                break;
            case 0x1e:
                //    integer
                // offset: 1; size: 2; unsigned 16-bit integer
                $name = 'tInt';
                $size = 3;
                $data = self::get_u_int2d($formula_data, 1);
                break;
            case 0x1f:
                //    number
                // offset: 1; size: 8;
                $name = 'tNum';
                $size = 9;
                $data = self::extract_number(substr($formula_data, 1));
                $data = str_replace(',', '.', (string) $data);
                // in case non-English locale
                break;
            case 0x20:
            //    array constant
            case 0x40:
            case 0x60:
                // offset: 1; size: 7; not used
                $name = 'tArray';
                $size = 8;
                $data = null;
                break;
            case 0x21:
            //    function with fixed number of arguments
            case 0x41:
            case 0x61:
                $name = 'tFunc';
                $size = 3;
                // offset: 1; size: 2; index to built-in sheet function
                $mapping = Xls\Mappings::TFUNC_MAPPINGS[self::get_u_int2d($formula_data, 1)] ?? null;
                if ($mapping === null) {
                    throw new Exception('Unrecognized function in formula');
                }
                $data = ['function' => $mapping[0], 'args' => $mapping[1]];
                break;
            case 0x22:
            //    function with variable number of arguments
            case 0x42:
            case 0x62:
                $name = 'tFuncV';
                $size = 4;
                // offset: 1; size: 1; number of arguments
                $args = ord($formula_data[1]);
                // offset: 2: size: 2; index to built-in sheet function
                $index = self::get_u_int2d($formula_data, 2);
                $function = Xls\Mappings::TFUNCV_MAPPINGS[$index] ?? null;
                if ($function === null) {
                    throw new Exception('Unrecognized function in formula');
                }
                $data = ['function' => $function, 'args' => $args];
                break;
            case 0x23:
            //    index to defined name
            case 0x43:
            case 0x63:
                $name = 'tName';
                $size = 5;
                // offset: 1; size: 2; one-based index to definedname record
                $defined_name_index = self::get_u_int2d($formula_data, 1) - 1;
                // offset: 2; size: 2; not used
                /** @var string[] */
                $data = $this->definedname[$defined_name_index]['name'] ?? '';
                //* @phpstan-ignore-line
                break;
            case 0x24:
            //    single cell reference e.g. A5
            case 0x44:
            case 0x64:
                $name = 'tRef';
                $size = 5;
                $data = Xls\Biff8::read_biff8cell_address(substr($formula_data, 1, 4));
                break;
            case 0x25:
            //    cell range reference to cells in the same sheet (2d)
            case 0x45:
            case 0x65:
                $name = 'tArea';
                $size = 9;
                $data = Xls\Biff8::read_biff8cell_range_address(substr($formula_data, 1, 8));
                break;
            case 0x26:
            //    Constant reference sub-expression
            case 0x46:
            case 0x66:
                $name = 'tMemArea';
                // offset: 1; size: 4; not used
                // offset: 5; size: 2; size of the following subexpression
                $sub_size = self::get_u_int2d($formula_data, 5);
                $size = 7 + $sub_size;
                $data = $this->get_formula_from_data(substr($formula_data, 7, $sub_size));
                break;
            case 0x27:
            //    Deleted constant reference sub-expression
            case 0x47:
            case 0x67:
                $name = 'tMemErr';
                // offset: 1; size: 4; not used
                // offset: 5; size: 2; size of the following subexpression
                $sub_size = self::get_u_int2d($formula_data, 5);
                $size = 7 + $sub_size;
                $data = $this->get_formula_from_data(substr($formula_data, 7, $sub_size));
                break;
            case 0x29:
            //    Variable reference sub-expression
            case 0x49:
            case 0x69:
                $name = 'tMemFunc';
                // offset: 1; size: 2; size of the following sub-expression
                $sub_size = self::get_u_int2d($formula_data, 1);
                $size = 3 + $sub_size;
                $data = $this->get_formula_from_data(substr($formula_data, 3, $sub_size));
                break;
            case 0x2c:
            // Relative 2d cell reference reference, used in shared formulas and some other places
            case 0x4c:
            case 0x6c:
                $name = 'tRefN';
                $size = 5;
                $data = Xls\Biff8::read_biff8cell_address_b(substr($formula_data, 1, 4), $base_cell);
                break;
            case 0x2d:
            //    Relative 2d range reference
            case 0x4d:
            case 0x6d:
                $name = 'tAreaN';
                $size = 9;
                $data = Xls\Biff8::read_biff8cell_range_address_b(substr($formula_data, 1, 8), $base_cell);
                break;
            case 0x39:
            //    External name
            case 0x59:
            case 0x79:
                $name = 'tNameX';
                $size = 7;
                // offset: 1; size: 2; index to REF entry in EXTERNSHEET record
                // offset: 3; size: 2; one-based index to DEFINEDNAME or EXTERNNAME record
                $index = self::get_u_int2d($formula_data, 3);
                // assume index is to EXTERNNAME record
                $data = $this->external_names[$index - 1]['name'] ?? '';
                // offset: 5; size: 2; not used
                break;
            case 0x3a:
            //    3d reference to cell
            case 0x5a:
            case 0x7a:
                $name = 'tRef3d';
                $size = 7;
                try {
                    // offset: 1; size: 2; index to REF entry
                    $sheet_range = $this->read_sheet_range_by_ref_index(self::get_u_int2d($formula_data, 1));
                    // offset: 3; size: 4; cell address
                    $cell_address = Xls\Biff8::read_biff8cell_address(substr($formula_data, 3, 4));
                    $data = "{$sheet_range}!{$cell_address}";
                } catch (Php_Spreadsheet_Exception) {
                    // deleted sheet reference
                    $data = '#REF!';
                }
                break;
            case 0x3b:
            //    3d reference to cell range
            case 0x5b:
            case 0x7b:
                $name = 'tArea3d';
                $size = 11;
                try {
                    // offset: 1; size: 2; index to REF entry
                    $sheet_range = $this->read_sheet_range_by_ref_index(self::get_u_int2d($formula_data, 1));
                    // offset: 3; size: 8; cell address
                    $cell_range_address = Xls\Biff8::read_biff8cell_range_address(substr($formula_data, 3, 8));
                    $data = "{$sheet_range}!{$cell_range_address}";
                } catch (Php_Spreadsheet_Exception) {
                    // deleted sheet reference
                    $data = '#REF!';
                }
                break;
            // Unknown cases    // don't know how to deal with
            default:
                throw new Exception('Unrecognized token ' . sprintf('%02X', $id) . ' in formula');
        }
        return ['id' => $id, 'name' => $name, 'size' => $size, 'data' => $data];
    }
    /**
     * Get a sheet range like Sheet1:Sheet3 from REF index
     * Note: If there is only one sheet in the range, one gets e.g Sheet1
     * It can also happen that the REF structure uses the -1 (FFFF) code to indicate deleted sheets,
     * in which case an Exception is thrown.
     */
    protected function read_sheet_range_by_ref_index(int $index): string|false
    {
        if (isset($this->ref[$index])) {
            $type = $this->external_books[$this->ref[$index]['externalBookIndex']]['type'];
            switch ($type) {
                case 'internal':
                    // check if we have a deleted 3d reference
                    if ($this->ref[$index]['firstSheetIndex'] == 0xffff || $this->ref[$index]['lastSheetIndex'] == 0xffff) {
                        throw new Exception('Deleted sheet reference');
                    }
                    // we have normal sheet range (collapsed or uncollapsed)
                    $first_sheet_name = $this->sheets[$this->ref[$index]['firstSheetIndex']]['name'];
                    $last_sheet_name = $this->sheets[$this->ref[$index]['lastSheetIndex']]['name'];
                    if ($first_sheet_name == $last_sheet_name) {
                        // collapsed sheet range
                        $sheet_range = $first_sheet_name;
                    } else {
                        $sheet_range = "{$first_sheet_name}:{$last_sheet_name}";
                    }
                    // escape the single-quotes
                    $sheet_range = str_replace("'", "''", $sheet_range);
                    // if there are special characters, we need to enclose the range in single-quotes
                    // todo: check if we have identified the whole set of special characters
                    // it seems that the following characters are not accepted for sheet names
                    // and we may assume that they are not present: []*/:\?
                    // 'u' qualifier makes it risky to use Preg::isMatch here
                    if (preg_match("/[ !\"@#£\$%&{()}<>=+'|^,;-]/u", $sheet_range)) {
                        return "'{$sheet_range}'";
                    }
                    return $sheet_range;
                default:
                    // TODO: external sheet support
                    throw new Exception('Xls reader only supports internal sheets in formulas');
            }
        }
        return false;
    }
    /**
     * Read byte string (8-bit string length)
     * OpenOffice documentation: 2.5.2.
     *
     * @return array{value: mixed, size: int}
     */
    protected function read_byte_string_short(string $sub_data): array
    {
        // offset: 0; size: 1; length of the string (character count)
        $ln = ord($sub_data[0]);
        // offset: 1: size: var; character array (8-bit characters)
        $value = $this->decode_codepage(substr($sub_data, 1, $ln));
        return ['value' => $value, 'size' => 1 + $ln];
    }
    /**
     * Read byte string (16-bit string length)
     * OpenOffice documentation: 2.5.2.
     *
     * @return array{value: mixed, size: int}
     */
    protected function read_byte_string_long(string $sub_data): array
    {
        // offset: 0; size: 2; length of the string (character count)
        $ln = self::get_u_int2d($sub_data, 0);
        // offset: 2: size: var; character array (8-bit characters)
        $value = $this->decode_codepage(substr($sub_data, 2));
        //return $string;
        return ['value' => $value, 'size' => 2 + $ln];
    }
    protected function parse_rich_text(string $is): Rich_Text
    {
        $value = new Rich_Text();
        $value->create_text($is);
        return $value;
    }
    /**
     * Phpstan 1.4.4 complains that this property is never read.
     * So, we might be able to get rid of it altogether.
     * For now, however, this function makes it readable,
     * which satisfies Phpstan.
     *
     * @return mixed[]
     *
     * @codeCoverageIgnore
     */
    public function get_map_cell_style_xf_index(): array
    {
        return $this->map_cell_style_xf_index;
    }
    /**
     * Parse conditional formatting blocks.
     *
     * @see https://www.openoffice.org/sc/excelfileformat.pdf Search for CFHEADER followed by CFRULE
     *
     * @return mixed[]
     */
    protected function read_cf_header(): array
    {
        return (new Xls\Conditional_Formatting())->read_cf_header2($this);
    }
    /** @param string[] $cellRangeAddresses */
    protected function read_cf_rule(array $cell_range_addresses): void
    {
        (new Xls\Conditional_Formatting())->read_cf_rule2($cell_range_addresses, $this);
    }
    public function get_version(): int
    {
        return $this->version;
    }
}