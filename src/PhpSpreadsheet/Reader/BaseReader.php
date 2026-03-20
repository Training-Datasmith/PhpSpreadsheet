<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Closure;
use Php_Office\Php_Spreadsheet\Cell\I_Value_Binder;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Spreadsheet;
abstract class Base_Reader implements I_Reader
{
    /**
     * Read data only?
     * Identifies whether the Reader should only read data values for cells, and ignore any formatting information;
     *        or whether it should read both data and formatting.
     */
    protected bool $read_data_only = false;
    /**
     * Read empty cells?
     * Identifies whether the Reader should read data values for all cells, or should ignore cells containing
     *         null value or empty string.
     */
    protected bool $read_empty_cells = true;
    /**
     * Read charts that are defined in the workbook?
     * Identifies whether the Reader should read the definitions for any charts that exist in the workbook;.
     */
    protected bool $include_charts = false;
    /**
     * Restrict which sheets should be loaded?
     * This property holds an array of worksheet names to be loaded. If null, then all worksheets will be loaded.
     * This property is ignored for Csv, Html, and Slk.
     *
     * @var null|string[]
     */
    protected ?array $load_sheets_only = null;
    /**
     * Ignore rows with no cells?
     * Identifies whether the Reader should ignore rows with no cells.
     *        Currently implemented only for Xlsx.
     */
    protected bool $ignore_rows_with_no_cells = false;
    /**
     * Allow external images. Use with caution.
     * Improper specification of these within a spreadsheet
     * can subject the caller to security exploits.
     */
    protected bool $allow_external_images = false;
    /**
     * Create a blank sheet if none are read,
     * possibly due to a typo when using LoadSheetsOnly.
     */
    protected bool $create_blank_sheet_if_none_read = false;
    /**
     * Enable drawing pass-through?
     * Identifies whether the Reader should preserve unsupported drawing elements (shapes, grouped images, etc.)
     * by storing the original XML for pass-through during write operations.
     * When enabled, drawings cannot be modified programmatically but are preserved exactly.
     */
    protected bool $enable_drawing_pass_through = false;
    /**
     * IReadFilter instance.
     */
    protected I_Read_Filter $read_filter;
    /** @var resource */
    protected $file_handle;
    protected ?Xml_Scanner $security_scanner = null;
    protected ?I_Value_Binder $value_binder = null;
    /** @var null|Closure(string):bool function to return whether image path is okay */
    protected ?Closure $is_whitelisted = null;
    public function __construct()
    {
        $this->read_filter = new Default_Read_Filter();
    }
    public function get_read_data_only(): bool
    {
        return $this->read_data_only;
    }
    public function set_read_data_only(bool $read_cell_values_only): self
    {
        $this->read_data_only = $read_cell_values_only;
        return $this;
    }
    public function get_read_empty_cells(): bool
    {
        return $this->read_empty_cells;
    }
    public function set_read_empty_cells(bool $read_empty_cells): self
    {
        $this->read_empty_cells = $read_empty_cells;
        return $this;
    }
    public function get_ignore_rows_with_no_cells(): bool
    {
        return $this->ignore_rows_with_no_cells;
    }
    public function set_ignore_rows_with_no_cells(bool $ignore_rows_with_no_cells): self
    {
        $this->ignore_rows_with_no_cells = $ignore_rows_with_no_cells;
        return $this;
    }
    public function get_include_charts(): bool
    {
        return $this->include_charts;
    }
    public function set_include_charts(bool $include_charts): self
    {
        $this->include_charts = $include_charts;
        return $this;
    }
    public function get_enable_drawing_pass_through(): bool
    {
        return $this->enable_drawing_pass_through;
    }
    public function set_enable_drawing_pass_through(bool $enable_drawing_pass_through): self
    {
        $this->enable_drawing_pass_through = $enable_drawing_pass_through;
        return $this;
    }
    /** @return null|string[] */
    public function get_load_sheets_only(): ?array
    {
        return $this->load_sheets_only;
    }
    /** @param null|string|string[] $sheetList */
    public function set_load_sheets_only(string|array|null $sheet_list): self
    {
        if ($sheet_list === null) {
            return $this->set_load_all_sheets();
        }
        $this->load_sheets_only = is_array($sheet_list) ? $sheet_list : [$sheet_list];
        return $this;
    }
    public function set_load_all_sheets(): self
    {
        $this->load_sheets_only = null;
        return $this;
    }
    public function get_read_filter(): I_Read_Filter
    {
        return $this->read_filter;
    }
    public function set_read_filter(I_Read_Filter $read_filter): self
    {
        $this->read_filter = $read_filter;
        return $this;
    }
    /**
     * USE WITH CAUTION (and in conjunction with setIsWhiteListed)!
     * Allow external images;
     * these can be specified within a spreadsheet
     * in a way that can subject the caller to security exploits.
     */
    public function set_allow_external_images(bool $allow_external_images): self
    {
        $this->allow_external_images = $allow_external_images;
        return $this;
    }
    public function get_allow_external_images(): bool
    {
        return $this->allow_external_images;
    }
    /**
     * USE WITH CAUTION!
     * Supply a callback to determine whether a path should be whitelisted,
     * used in conjunction with setAllowExternalImages;
     * supplying a method which might return true
     * can subject the caller to security exploits.
     *
     * @param Closure(string):bool $isWhitelisted
     */
    public function set_is_whitelisted(Closure $is_whitelisted): self
    {
        $this->is_whitelisted = $is_whitelisted;
        return $this;
    }
    /**
     * Create a blank sheet if none are read,
     * possibly due to a typo when using LoadSheetsOnly.
     */
    public function set_create_blank_sheet_if_none_read(bool $create_blank_sheet_if_none_read): self
    {
        $this->create_blank_sheet_if_none_read = $create_blank_sheet_if_none_read;
        return $this;
    }
    public function get_security_scanner(): ?Xml_Scanner
    {
        return $this->security_scanner;
    }
    public function get_security_scanner_or_throw(): Xml_Scanner
    {
        if ($this->security_scanner === null) {
            throw new Reader_Exception('Security scanner is unexpectedly null');
        }
        return $this->security_scanner;
    }
    protected function process_flags(int $flags): void
    {
        if ((bool) ($flags & self::LOAD_WITH_CHARTS) === true) {
            $this->set_include_charts(true);
        }
        if ((bool) ($flags & self::READ_DATA_ONLY) === true) {
            $this->set_read_data_only(true);
        }
        if ((bool) ($flags & self::IGNORE_EMPTY_CELLS) === true) {
            $this->set_read_empty_cells(false);
        }
        if ((bool) ($flags & self::IGNORE_ROWS_WITH_NO_CELLS) === true) {
            $this->set_ignore_rows_with_no_cells(true);
        }
        if ((bool) ($flags & self::ALLOW_EXTERNAL_IMAGES) === true) {
            $this->set_allow_external_images(true);
        }
        if ((bool) ($flags & self::DONT_ALLOW_EXTERNAL_IMAGES) === true) {
            $this->set_allow_external_images(false);
        }
        if ((bool) ($flags & self::CREATE_BLANK_SHEET_IF_NONE_READ) === true) {
            $this->set_create_blank_sheet_if_none_read(true);
        }
    }
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        throw new Php_Spreadsheet_Exception('Reader classes must implement their own loadSpreadsheetFromFile() method');
    }
    /**
     * Loads Spreadsheet from file.
     *
     * @param int $flags the optional second parameter flags may be used to identify specific elements
     *                       that should be loaded, but which won't be loaded by default, using these values:
     *                            IReader::LOAD_WITH_CHARTS - Include any charts that are defined in the loaded file
     */
    public function load(string $filename, int $flags = 0): Spreadsheet
    {
        $this->process_flags($flags);
        return $this->load_spreadsheet_from_file($filename);
    }
    /**
     * Open file for reading.
     */
    protected function open_file(string $filename): void
    {
        $file_handle = false;
        if ($filename) {
            File::assert_file($filename);
            // Open file
            $file_handle = fopen($filename, 'rb');
        }
        if ($file_handle === false) {
            throw new Reader_Exception('Could not open file ' . $filename . ' for reading.');
        }
        $this->file_handle = $file_handle;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        throw new Php_Spreadsheet_Exception('Reader classes must implement their own listWorksheetInfo() method');
    }
    /**
     * Returns names of the worksheets from a file,
     * possibly without parsing the whole file to a Spreadsheet object.
     * Readers will often have a more efficient method with which
     * they can override this method.
     *
     * @return string[]
     */
    public function list_worksheet_names(string $filename): array
    {
        $return_array = [];
        $info = $this->list_worksheet_info($filename);
        foreach ($info as $info_array) {
            $return_array[] = $info_array['worksheetName'];
        }
        return $return_array;
    }
    public function get_value_binder(): ?I_Value_Binder
    {
        return $this->value_binder;
    }
    public function set_value_binder(?I_Value_Binder $value_binder): self
    {
        $this->value_binder = $value_binder;
        return $this;
    }
    protected function new_spreadsheet(): Spreadsheet
    {
        return new Spreadsheet();
    }
}