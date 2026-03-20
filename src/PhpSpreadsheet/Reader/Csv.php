<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Csv\Delimiter;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Throwable;
class Csv extends Base_Reader
{
    public const DEFAULT_FALLBACK_ENCODING = 'CP1252';
    public const GUESS_ENCODING = 'guess';
    public const UTF8_BOM = "﻿";
    public const UTF8_BOM_LEN = 3;
    public const UTF16BE_BOM = "\xfe\xff";
    public const UTF16BE_BOM_LEN = 2;
    public const UTF16BE_LF = "\x00\n";
    public const UTF16LE_BOM = "\xff\xfe";
    public const UTF16LE_BOM_LEN = 2;
    public const UTF16LE_LF = "\n\x00";
    public const UTF32BE_BOM = "\x00\x00\xfe\xff";
    public const UTF32BE_BOM_LEN = 4;
    public const UTF32BE_LF = "\x00\x00\x00\n";
    public const UTF32LE_BOM = "\xff\xfe\x00\x00";
    public const UTF32LE_BOM_LEN = 4;
    public const UTF32LE_LF = "\n\x00\x00\x00";
    /**
     * Input encoding.
     */
    private string $input_encoding = 'UTF-8';
    /**
     * Fallback encoding if guess strikes out.
     */
    private string $fallback_encoding = self::DEFAULT_FALLBACK_ENCODING;
    /**
     * Delimiter.
     */
    private ?string $delimiter = null;
    /**
     * Enclosure.
     */
    private string $enclosure = '"';
    /**
     * Sheet index to read.
     */
    private int $sheet_index = 0;
    /**
     * Load rows contiguously.
     */
    private bool $contiguous = false;
    /**
     * The character that can escape the enclosure.
     * This will probably become unsupported in Php 9.
     * Not yet ready to mark deprecated in order to give users
     * a migration path.
     */
    private ?string $escape_character = null;
    /**
     * Callback for setting defaults in construction.
     *
     * @var ?callable
     */
    private static $constructor_callback;
    /** Changed from true to false in release 4.0.0 */
    public const DEFAULT_TEST_AUTODETECT = false;
    /**
     * Attempt autodetect line endings (deprecated after PHP8.1)?
     */
    private bool $test_autodetect = self::DEFAULT_TEST_AUTODETECT;
    protected bool $cast_formatted_number_to_numeric = false;
    protected bool $preserve_numeric_formatting = false;
    private bool $preserve_null_string = false;
    private bool $sheet_name_is_file_name = false;
    private string $get_true = 'true';
    private string $get_false = 'false';
    private string $thousands_separator = ',';
    private string $decimal_separator = '.';
    /**
     * Create a new CSV Reader instance.
     */
    public function __construct()
    {
        parent::__construct();
        $callback = self::$constructor_callback;
        if ($callback !== null) {
            $callback($this);
        }
    }
    /**
     * Set a callback to change the defaults.
     *
     * The callback must accept the Csv Reader object as the first parameter,
     * and it should return void.
     */
    public static function set_constructor_callback(?callable $callback): void
    {
        self::$constructor_callback = $callback;
    }
    public static function get_constructor_callback(): ?callable
    {
        return self::$constructor_callback;
    }
    public function set_input_encoding(string $encoding): self
    {
        $this->input_encoding = $encoding;
        return $this;
    }
    public function get_input_encoding(): string
    {
        return $this->input_encoding;
    }
    public function set_fallback_encoding(string $fallback_encoding): self
    {
        $this->fallback_encoding = $fallback_encoding;
        return $this;
    }
    public function get_fallback_encoding(): string
    {
        return $this->fallback_encoding;
    }
    /**
     * Move filepointer past any BOM marker.
     */
    protected function skip_bom(): void
    {
        rewind($this->file_handle);
        if (fgets($this->file_handle, self::UTF8_BOM_LEN + 1) !== self::UTF8_BOM) {
            rewind($this->file_handle);
        }
    }
    /**
     * Identify any separator that is explicitly set in the file.
     */
    protected function check_separator(): void
    {
        $line = fgets($this->file_handle);
        if ($line === false) {
            return;
        }
        if (strlen(trim($line, "\r\n")) == 5 && stripos($line, 'sep=') === 0) {
            $this->delimiter = substr($line, 4, 1);
            return;
        }
        $this->skip_bom();
    }
    /**
     * Infer the separator if it isn't explicitly set in the file or specified by the user.
     */
    protected function infer_separator(): void
    {
        $temp = $this->delimiter;
        if ($temp !== null) {
            return;
        }
        $inference_engine = new Delimiter($this->file_handle, $this->get_escape_character(), $this->enclosure);
        // If number of lines is 0, nothing to infer : fall back to the default
        if ($inference_engine->lines_counted() === 0) {
            $this->delimiter = $inference_engine->get_default_delimiter();
            $this->skip_bom();
            return;
        }
        $this->delimiter = $inference_engine->infer();
        // If no delimiter could be detected, fall back to the default
        if ($this->delimiter === null) {
            $this->delimiter = $inference_engine->get_default_delimiter();
        }
        $this->skip_bom();
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        // Open file
        $this->open_file_or_memory($filename);
        $file_handle = $this->file_handle;
        // Skip BOM, if any
        $this->skip_bom();
        $this->check_separator();
        $this->infer_separator();
        $worksheet_info = [['worksheetName' => 'Worksheet', 'lastColumnLetter' => 'A', 'lastColumnIndex' => 0, 'totalRows' => 0, 'totalColumns' => 0]];
        $delimiter = $this->delimiter ?? '';
        // Loop through each line of the file in turn
        $row_data = self::get_csv($file_handle, 0, $delimiter, $this->enclosure, $this->escape_character);
        while (is_array($row_data)) {
            ++$worksheet_info[0]['totalRows'];
            $worksheet_info[0]['lastColumnIndex'] = max($worksheet_info[0]['lastColumnIndex'], count($row_data) - 1);
            $row_data = self::get_csv($file_handle, 0, $delimiter, $this->enclosure, $this->escape_character);
        }
        $worksheet_info[0]['lastColumnLetter'] = Coordinate::string_from_column_index($worksheet_info[0]['lastColumnIndex'] + 1, true);
        $worksheet_info[0]['totalColumns'] = $worksheet_info[0]['lastColumnIndex'] + 1;
        $worksheet_info[0]['sheetState'] = Worksheet::SHEETSTATE_VISIBLE;
        // Close file
        fclose($file_handle);
        return $worksheet_info;
    }
    /**
     * Loads Spreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        // Load into this instance
        return $this->load_into_existing($filename, $spreadsheet);
    }
    /**
     * Loads Spreadsheet from string.
     */
    public function load_spreadsheet_from_string(string $contents): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        // Load into this instance
        return $this->load_string_or_file('data://text/plain,' . urlencode($contents), $spreadsheet, true);
    }
    private function open_file_or_memory(string $filename): void
    {
        // Open file
        $fhandle = $this->can_read($filename);
        if (!$fhandle) {
            throw new Reader_Exception($filename . ' is an Invalid Spreadsheet file.');
        }
        if ($this->input_encoding === 'UTF-8') {
            $encoding = self::guess_encoding_bom($filename);
            if ($encoding !== '') {
                $this->input_encoding = $encoding;
            }
        }
        if ($this->input_encoding === self::GUESS_ENCODING) {
            $this->input_encoding = self::guess_encoding($filename, $this->fallback_encoding);
        }
        $this->open_file($filename);
        if ($this->input_encoding !== 'UTF-8') {
            fclose($this->file_handle);
            $entire_file = file_get_contents($filename);
            $file_handle = fopen('php://memory', 'r+b');
            if ($file_handle !== false && $entire_file !== false) {
                $this->file_handle = $file_handle;
                $data = String_Helper::convert_encoding($entire_file, 'UTF-8', $this->input_encoding);
                fwrite($this->file_handle, $data);
                $this->skip_bom();
            }
        }
    }
    public function set_test_auto_detect(bool $value): self
    {
        $this->test_autodetect = $value;
        return $this;
    }
    private function set_auto_detect(?string $value, int $version = PHP_VERSION_ID): ?string
    {
        $ret_val = null;
        if ($value !== null && $this->test_autodetect && $version < 90000) {
            $ret_val2 = @ini_set('auto_detect_line_endings', $value);
            if (is_string($ret_val2)) {
                $ret_val = $ret_val2;
            }
        }
        return $ret_val;
    }
    public function cast_formatted_number_to_numeric(bool $cast_formatted_number_to_numeric, bool $preserve_numeric_formatting = false): void
    {
        $this->cast_formatted_number_to_numeric = $cast_formatted_number_to_numeric;
        $this->preserve_numeric_formatting = $preserve_numeric_formatting;
    }
    /**
     * Open data uri for reading.
     */
    private function open_data_uri(string $filename): void
    {
        $file_handle = fopen($filename, 'rb');
        if ($file_handle === false) {
            // @codeCoverageIgnoreStart
            throw new Reader_Exception('Could not open file ' . $filename . ' for reading.');
            // @codeCoverageIgnoreEnd
        }
        $this->file_handle = $file_handle;
    }
    /**
     * Loads PhpSpreadsheet from file into PhpSpreadsheet instance.
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet): Spreadsheet
    {
        return $this->load_string_or_file($filename, $spreadsheet, false);
    }
    /**
     * Loads PhpSpreadsheet from file into PhpSpreadsheet instance.
     */
    private function load_string_or_file(string $filename, Spreadsheet $spreadsheet, bool $data_uri): Spreadsheet
    {
        // Deprecated in Php8.1
        $iniset = $this->set_auto_detect('1');
        try {
            $this->load_string_or_file2($filename, $spreadsheet, $data_uri);
            $this->set_auto_detect($iniset);
        } catch (Throwable $e) {
            $this->set_auto_detect($iniset);
            throw $e;
        }
        return $spreadsheet;
    }
    private function load_string_or_file2(string $filename, Spreadsheet $spreadsheet, bool $data_uri): void
    {
        // Open file
        if ($data_uri) {
            $this->open_data_uri($filename);
        } else {
            $this->open_file_or_memory($filename);
        }
        $file_handle = $this->file_handle;
        // Skip BOM, if any
        $this->skip_bom();
        $this->check_separator();
        $this->infer_separator();
        // Create new PhpSpreadsheet object
        while ($spreadsheet->get_sheet_count() <= $this->sheet_index) {
            $spreadsheet->create_sheet();
        }
        $sheet = $spreadsheet->set_active_sheet_index($this->sheet_index);
        if ($this->sheet_name_is_file_name) {
            $sheet->set_title(substr(basename($filename, '.csv'), 0, Worksheet::SHEET_TITLE_MAXIMUM_LENGTH));
        }
        // Set our starting row based on whether we're in contiguous mode or not
        $current_row = 1;
        $out_row = 0;
        // Loop through each line of the file in turn
        $delimiter = $this->delimiter ?? '';
        $row_data = self::get_csv($file_handle, 0, $delimiter, $this->enclosure, $this->escape_character);
        $value_binder = $this->value_binder ?? Cell::get_value_binder();
        $preserve_boolean_string = method_exists($value_binder, 'getBooleanConversion') && $value_binder->get_boolean_conversion();
        $this->get_true = Calculation::get_true();
        $this->get_false = Calculation::get_false();
        $this->thousands_separator = String_Helper::get_thousands_separator();
        $this->decimal_separator = String_Helper::get_decimal_separator();
        while (is_array($row_data)) {
            $no_output_yet = true;
            $column_letter = 'A';
            foreach ($row_data as $row_datum) {
                if ($preserve_boolean_string) {
                    $row_datum ??= '';
                } else {
                    $this->convert_boolean($row_datum);
                }
                $number_format_mask = $this->cast_formatted_number_to_numeric ? $this->convert_formatted_number($row_datum) : '';
                if (($row_datum !== '' || $this->preserve_null_string) && $this->read_filter->read_cell($column_letter, $current_row)) {
                    if ($this->contiguous) {
                        if ($no_output_yet) {
                            $no_output_yet = false;
                            ++$out_row;
                        }
                    } else {
                        $out_row = $current_row;
                    }
                    // Set basic styling for the value (Note that this could be overloaded by styling in a value binder)
                    if ($number_format_mask !== '') {
                        $sheet->get_style($column_letter . $out_row)->get_number_format()->set_format_code($number_format_mask);
                    }
                    // Set cell value
                    $sheet->get_cell($column_letter . $out_row)->set_value($row_datum);
                }
                String_Helper::string_increment($column_letter);
            }
            $row_data = self::get_csv($file_handle, 0, $delimiter, $this->enclosure, $this->escape_character);
            ++$current_row;
        }
        // Close file
        fclose($file_handle);
    }
    /**
     * Convert string true/false to boolean, and null to null-string.
     */
    private function convert_boolean(mixed &$row_datum): void
    {
        if (is_string($row_datum)) {
            if (strcasecmp($this->get_true, $row_datum) === 0 || strcasecmp('true', $row_datum) === 0) {
                $row_datum = true;
            } elseif (strcasecmp($this->get_false, $row_datum) === 0 || strcasecmp('false', $row_datum) === 0) {
                $row_datum = false;
            }
        } else {
            $row_datum ??= '';
        }
    }
    /**
     * Convert numeric strings to int or float values.
     */
    private function convert_formatted_number(mixed &$row_datum): string
    {
        $number_format_mask = '';
        if ($this->cast_formatted_number_to_numeric === true && is_string($row_datum)) {
            $numeric = str_replace([$this->thousands_separator, $this->decimal_separator], ['', '.'], $row_datum);
            if (is_numeric($numeric)) {
                $decimal_pos = strpos($row_datum, $this->decimal_separator);
                if ($this->preserve_numeric_formatting === true) {
                    $number_format_mask = str_contains($row_datum, $this->thousands_separator) ? '#,##0' : '0';
                    if ($decimal_pos !== false) {
                        $decimals = strlen($row_datum) - $decimal_pos - 1;
                        $number_format_mask .= '.' . str_repeat('0', min($decimals, 6));
                    }
                }
                $row_datum = $decimal_pos !== false ? (float) $numeric : (int) $numeric;
            }
        }
        return $number_format_mask;
    }
    public function get_delimiter(): ?string
    {
        return $this->delimiter;
    }
    public function set_delimiter(?string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }
    public function get_enclosure(): string
    {
        return $this->enclosure;
    }
    public function set_enclosure(string $enclosure): self
    {
        if ($enclosure == '') {
            $enclosure = '"';
        }
        $this->enclosure = $enclosure;
        return $this;
    }
    public function get_sheet_index(): int
    {
        return $this->sheet_index;
    }
    public function set_sheet_index(int $index_value): self
    {
        $this->sheet_index = $index_value;
        return $this;
    }
    public function set_contiguous(bool $contiguous): self
    {
        $this->contiguous = $contiguous;
        return $this;
    }
    public function get_contiguous(): bool
    {
        return $this->contiguous;
    }
    /**
     * Php9 intends to drop support for this parameter in fgetcsv.
     * Not yet ready to mark deprecated in order to give users
     * a migration path.
     */
    public function set_escape_character(string $escape_character, int $version = PHP_VERSION_ID): self
    {
        if ($version >= 90000 && $escape_character !== '') {
            throw new Reader_Exception('Escape character must be null string for Php9+');
        }
        $this->escape_character = $escape_character;
        return $this;
    }
    public function get_escape_character(int $version = PHP_VERSION_ID): string
    {
        return $this->escape_character ?? self::get_default_escape_character($version);
    }
    /**
     * Can the current IReader read the file?
     */
    public function can_read(string $filename): bool
    {
        // Check if file exists
        try {
            $this->open_file($filename);
        } catch (Reader_Exception) {
            return false;
        }
        fclose($this->file_handle);
        // Trust file extension if any
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'tsv'])) {
            return true;
        }
        // Attempt to guess mimetype
        $type = mime_content_type($filename);
        $supported_types = [
            'application/csv',
            'text/csv',
            'text/plain',
            'inode/x-empty',
            'application/x-empty',
            // has now replaced previous
            'text/html',
        ];
        return in_array($type, $supported_types, true);
    }
    private static function guess_encoding_test_no_bom(string &$encoding, string &$contents, string $compare, string $set_encoding): void
    {
        if ($encoding === '') {
            $pos = strpos($contents, $compare);
            if ($pos !== false && $pos % strlen($compare) === 0) {
                $encoding = $set_encoding;
            }
        }
    }
    private static function guess_encoding_no_bom(string $filename): string
    {
        $encoding = '';
        $contents = (string) file_get_contents($filename);
        self::guess_encoding_test_no_bom($encoding, $contents, self::UTF32BE_LF, 'UTF-32BE');
        self::guess_encoding_test_no_bom($encoding, $contents, self::UTF32LE_LF, 'UTF-32LE');
        self::guess_encoding_test_no_bom($encoding, $contents, self::UTF16BE_LF, 'UTF-16BE');
        self::guess_encoding_test_no_bom($encoding, $contents, self::UTF16LE_LF, 'UTF-16LE');
        if ($encoding === '' && preg_match('//u', $contents) === 1) {
            return 'UTF-8';
        }
        return $encoding;
    }
    private static function guess_encoding_test_bom(string &$encoding, string $first4, string $compare, string $set_encoding): void
    {
        if ($encoding === '') {
            if (str_starts_with($first4, $compare)) {
                $encoding = $set_encoding;
            }
        }
    }
    public static function guess_encoding_bom(string $filename, ?string $convert_string = null): string
    {
        $encoding = '';
        $first4 = $convert_string ?? (string) file_get_contents($filename, false, null, 0, 4);
        self::guess_encoding_test_bom($encoding, $first4, self::UTF8_BOM, 'UTF-8');
        self::guess_encoding_test_bom($encoding, $first4, self::UTF16BE_BOM, 'UTF-16BE');
        self::guess_encoding_test_bom($encoding, $first4, self::UTF32BE_BOM, 'UTF-32BE');
        self::guess_encoding_test_bom($encoding, $first4, self::UTF32LE_BOM, 'UTF-32LE');
        self::guess_encoding_test_bom($encoding, $first4, self::UTF16LE_BOM, 'UTF-16LE');
        return $encoding;
    }
    public static function guess_encoding(string $filename, string $dflt = self::DEFAULT_FALLBACK_ENCODING): string
    {
        $encoding = self::guess_encoding_bom($filename);
        if ($encoding === '') {
            $encoding = self::guess_encoding_no_bom($filename);
        }
        return $encoding === '' ? $dflt : $encoding;
    }
    public function set_preserve_null_string(bool $value): self
    {
        $this->preserve_null_string = $value;
        return $this;
    }
    public function get_preserve_null_string(): bool
    {
        return $this->preserve_null_string;
    }
    public function set_sheet_name_is_file_name(bool $sheet_name_is_file_name): self
    {
        $this->sheet_name_is_file_name = $sheet_name_is_file_name;
        return $this;
    }
    /**
     * Php8.4 deprecates use of anything other than null string
     * as escape Character.
     *
     * @param resource $stream
     * @param null|int<0, max> $length
     *
     * @return array<int,?string>|false
     */
    private static function get_csv($stream, ?int $length = null, string $separator = ',', string $enclosure = '"', ?string $escape = null, int $version = PHP_VERSION_ID): array|false
    {
        $escape ??= self::get_default_escape_character();
        if ($version >= 80400 && $escape !== '') {
            return @fgetcsv($stream, $length, $separator, $enclosure, $escape);
        }
        return fgetcsv($stream, $length, $separator, $enclosure, $escape);
    }
    public static function affected_by_php9(string $filename, string $input_encoding = 'UTF-8', ?string $delimiter = null, string $enclosure = '"', string $escape_character = '\\', int $version = PHP_VERSION_ID): bool
    {
        if ($version < 70400 || $version >= 90000) {
            throw new Reader_Exception('Function valid only for Php7.4 or Php8');
        }
        $reader1 = new self();
        $reader1->set_input_encoding($input_encoding)->set_test_auto_detect(true)->set_escape_character($escape_character)->set_delimiter($delimiter)->set_enclosure($enclosure);
        $spreadsheet1 = $reader1->load($filename);
        $sheet1 = $spreadsheet1->get_active_sheet();
        $array1 = $sheet1->to_array(null, false, false);
        $spreadsheet1->disconnect_worksheets();
        $reader2 = new self();
        $reader2->set_input_encoding($input_encoding)->set_test_auto_detect(false)->set_escape_character('')->set_delimiter($delimiter)->set_enclosure($enclosure);
        $spreadsheet2 = $reader2->load($filename);
        $sheet2 = $spreadsheet2->get_active_sheet();
        $array2 = $sheet2->to_array(null, false, false);
        $spreadsheet2->disconnect_worksheets();
        return $array1 !== $array2;
    }
    /**
     * The character that will be supplied to fgetcsv
     * when escapeCharacter is null.
     * It is anticipated that it will conditionally be set
     * to null-string for Php9 and above.
     */
    private static function get_default_escape_character(int $version = PHP_VERSION_ID): string
    {
        return $version < 90000 ? '\\' : '';
    }
}