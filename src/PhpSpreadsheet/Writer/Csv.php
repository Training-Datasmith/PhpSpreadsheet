<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Spreadsheet;
class Csv extends Base_Writer
{
    /**
     * Delimiter.
     */
    private string $delimiter = ',';
    /**
     * Enclosure.
     */
    private string $enclosure = '"';
    /**
     * Line ending.
     */
    private string $line_ending = PHP_EOL;
    /**
     * Sheet index to write.
     */
    private int $sheet_index = 0;
    /**
     * Whether to write a UTF8 BOM.
     */
    private bool $use_bom = false;
    /**
     * Whether to write a Separator line as the first line of the file
     *     sep=x.
     */
    private bool $include_separator_line = false;
    /**
     * Whether to write a fully Excel compatible CSV file.
     */
    private bool $excel_compatibility = false;
    /**
     * Output encoding.
     */
    private string $output_encoding = '';
    /**
     * Whether number of columns should be allowed to vary
     * between rows, or use a fixed range based on the max
     * column overall.
     */
    private bool $variable_columns = false;
    private bool $prefer_hyperlink_to_label = false;
    /**
     * Create a new CSV.
     */
    public function __construct(
        /**
         * PhpSpreadsheet object.
         */
        private readonly Spreadsheet $spreadsheet
    )
    {
    }
    /**
     * Save PhpSpreadsheet to file.
     *
     * @param resource|string $filename
     */
    public function save($filename, int $flags = 0): void
    {
        $this->process_flags($flags);
        // Fetch sheet
        $sheet = $this->spreadsheet->get_sheet($this->sheet_index);
        $save_debug_log = Calculation::get_instance($this->spreadsheet)->get_debug_log()->get_write_debug_log();
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log(false);
        $sheet->calculate_arrays($this->pre_calculate_formulas);
        // Open file
        $this->open_file_handle($filename);
        if ($this->excel_compatibility) {
            $this->set_use_bom(true);
            //  Enforce UTF-8 BOM Header
            $this->set_include_separator_line(true);
            //  Set separator line
            $this->set_enclosure('"');
            //  Set enclosure to "
            $this->set_delimiter(';');
            //  Set delimiter to a semicolon
            $this->set_line_ending("\r\n");
        }
        if ($this->use_bom) {
            // Write the UTF-8 BOM code if required
            fwrite($this->file_handle, "﻿");
        }
        if ($this->include_separator_line) {
            // Write the separator line if required
            fwrite($this->file_handle, 'sep=' . $this->get_delimiter() . $this->line_ending);
        }
        //    Identify the range that we need to extract from the worksheet
        $max_col = $sheet->get_highest_data_column();
        $max_row = $sheet->get_highest_data_row();
        // Write rows to file
        $row = 0;
        foreach ($sheet->range_to_array_yield_rows("A1:{$max_col}{$max_row}", '', $this->pre_calculate_formulas) as $cells_array) {
            ++$row;
            if ($this->variable_columns) {
                $column = $sheet->get_highest_data_column($row);
                if ($column === 'A' && !$sheet->cell_exists("A{$row}")) {
                    $cells_array = [];
                } else {
                    array_splice($cells_array, Coordinate::column_index_from_string($column));
                }
            }
            if ($this->prefer_hyperlink_to_label) {
                foreach ($cells_array as $key => $value) {
                    $url = $sheet->get_cell([$key + 1, $row])->get_hyperlink()->get_url();
                    if ($url !== '') {
                        $cells_array[$key] = $url;
                    }
                }
            }
            /** @var string[] $cellsArray */
            $this->write_line($this->file_handle, $cells_array);
        }
        $this->maybe_close_file_handle();
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log($save_debug_log);
    }
    public function get_delimiter(): string
    {
        return $this->delimiter;
    }
    public function set_delimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }
    public function get_enclosure(): string
    {
        return $this->enclosure;
    }
    public function set_enclosure(string $enclosure = '"'): self
    {
        $this->enclosure = $enclosure;
        return $this;
    }
    public function get_line_ending(): string
    {
        return $this->line_ending;
    }
    public function set_line_ending(string $line_ending): self
    {
        $this->line_ending = $line_ending;
        return $this;
    }
    /**
     * Get whether BOM should be used.
     */
    public function get_use_bom(): bool
    {
        return $this->use_bom;
    }
    /**
     * Set whether BOM should be used, typically when non-ASCII characters are used.
     */
    public function set_use_bom(bool $use_bom): self
    {
        $this->use_bom = $use_bom;
        return $this;
    }
    /**
     * Get whether a separator line should be included.
     */
    public function get_include_separator_line(): bool
    {
        return $this->include_separator_line;
    }
    /**
     * Set whether a separator line should be included as the first line of the file.
     */
    public function set_include_separator_line(bool $include_separator_line): self
    {
        $this->include_separator_line = $include_separator_line;
        return $this;
    }
    /**
     * Get whether the file should be saved with full Excel Compatibility.
     */
    public function get_excel_compatibility(): bool
    {
        return $this->excel_compatibility;
    }
    /**
     * Set whether the file should be saved with full Excel Compatibility.
     *
     * @param bool $excelCompatibility Set the file to be written as a fully Excel compatible csv file
     *                                Note that this overrides other settings such as useBOM, enclosure and delimiter
     */
    public function set_excel_compatibility(bool $excel_compatibility): self
    {
        $this->excel_compatibility = $excel_compatibility;
        return $this;
    }
    public function get_sheet_index(): int
    {
        return $this->sheet_index;
    }
    public function set_sheet_index(int $sheet_index): self
    {
        $this->sheet_index = $sheet_index;
        return $this;
    }
    public function get_output_encoding(): string
    {
        return $this->output_encoding;
    }
    public function set_output_encoding(string $output_encoding): self
    {
        $this->output_encoding = $output_encoding;
        return $this;
    }
    private bool $enclosure_required = true;
    public function set_enclosure_required(bool $value): self
    {
        $this->enclosure_required = $value;
        return $this;
    }
    public function get_enclosure_required(): bool
    {
        return $this->enclosure_required;
    }
    /**
     * Write line to CSV file.
     *
     * @param resource $fileHandle PHP filehandle
     * @param string[] $values Array containing values in a row
     */
    private function write_line($file_handle, array $values): void
    {
        // No leading delimiter
        $delimiter = '';
        // Build the line
        $line = '';
        foreach ($values as $element) {
            if (Preg::is_match('/^([+-])?(\d+)[.](\d+)/', $element, $matches)) {
                // Excel will "convert" file with pop-up
                // if there are more than 15 digits precision.
                $whole = $matches[2];
                if ($whole !== '0') {
                    $whole_len = strlen((string) $whole);
                    $frac = $matches[3];
                    $max_frac_len = 15 - $whole_len;
                    if ($max_frac_len >= 0 && strlen((string) $frac) > $max_frac_len) {
                        $result = sprintf("%.{$max_frac_len}F", $element);
                        if (str_contains($result, '.')) {
                            $element = Preg::replace('/[.]?0+$/', '', $result);
                            // strip trailing zeros
                        }
                    }
                }
            }
            // Add delimiter
            $line .= $delimiter;
            $delimiter = $this->delimiter;
            // Escape enclosures
            $enclosure = $this->enclosure;
            if ($enclosure) {
                // If enclosure is not required, use enclosure only if
                // element contains newline, delimiter, or enclosure.
                if (!$this->enclosure_required && strpbrk($element, "{$delimiter}{$enclosure}\n") === false) {
                    $enclosure = '';
                } else {
                    $element = str_replace($enclosure, $enclosure . $enclosure, $element);
                }
            }
            // Add enclosed string
            $line .= $enclosure . $element . $enclosure;
        }
        // Add line ending
        $line .= $this->line_ending;
        // Write to file
        if ($this->output_encoding != '') {
            $line = mb_convert_encoding($line, $this->output_encoding);
        }
        fwrite($file_handle, $line);
    }
    /**
     * Get whether number of columns should be allowed to vary
     * between rows, or use a fixed range based on the max
     * column overall.
     */
    public function get_variable_columns(): bool
    {
        return $this->variable_columns;
    }
    /**
     * Set whether number of columns should be allowed to vary
     * between rows, or use a fixed range based on the max
     * column overall.
     */
    public function set_variable_columns(bool $p_value): self
    {
        $this->variable_columns = $p_value;
        return $this;
    }
    /**
     * Get whether hyperlink or label should be output.
     */
    public function get_prefer_hyperlink_to_label(): bool
    {
        return $this->prefer_hyperlink_to_label;
    }
    /**
     * Set whether hyperlink or label should be output.
     */
    public function set_prefer_hyperlink_to_label(bool $prefer_hyperlink_to_label): self
    {
        $this->prefer_hyperlink_to_label = $prefer_hyperlink_to_label;
        return $this;
    }
}