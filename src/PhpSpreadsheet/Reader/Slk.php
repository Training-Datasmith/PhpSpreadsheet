<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Reference_Helper;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Slk extends Base_Reader
{
    /**
     * Sheet index to read.
     */
    private int $sheet_index = 0;
    /**
     * Formats.
     *
     * @var mixed[]
     */
    private array $formats = [];
    /**
     * Format Count.
     */
    private int $format = 0;
    /**
     * Fonts.
     *
     * @var mixed[]
     */
    private array $fonts = [];
    /**
     * Font Count.
     */
    private int $fontcount = 0;
    /**
     * Validate that the current file is a SYLK file.
     */
    public function can_read(string $filename): bool
    {
        try {
            $this->open_file($filename);
        } catch (Reader_Exception) {
            return false;
        }
        // Read sample data (first 2 KB will do)
        $data = (string) fread($this->file_handle, 2048);
        // Count delimiters in file
        $delimiter_count = substr_count($data, ';');
        $has_delimiter = $delimiter_count > 0;
        // Analyze first line looking for ID; signature
        $lines = explode("\n", $data);
        $has_id = str_starts_with($lines[0], 'ID;P');
        fclose($this->file_handle);
        return $has_delimiter && $has_id;
    }
    private function can_read_or_bust(string $filename): void
    {
        if (!$this->can_read($filename)) {
            throw new Reader_Exception($filename . ' is an Invalid SYLK file.');
        }
        $this->open_file($filename);
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        // Open file
        $this->can_read_or_bust($filename);
        $file_handle = $this->file_handle;
        rewind($file_handle);
        $worksheet_info = [['worksheetName' => basename($filename, '.slk')]];
        // loop through one row (line) at a time in the file
        $row_index = 0;
        $column_index = 0;
        while (($row_data = fgets($file_handle)) !== false) {
            $column_index = 0;
            // convert SYLK encoded $rowData to UTF-8
            $row_data = String_Helper::syl_kto_utf8($row_data);
            // explode each row at semicolons while taking into account that literal semicolon (;)
            // is escaped like this (;;)
            $row_data = explode("\t", str_replace('¤', ';', str_replace(';', "\t", str_replace(';;', '¤', rtrim($row_data)))));
            $data_type = array_shift($row_data);
            if ($data_type == 'B') {
                foreach ($row_data as $row_datum) {
                    switch ($row_datum[0]) {
                        case 'X':
                            $column_index = (int) substr($row_datum, 1) - 1;
                            break;
                        case 'Y':
                            $row_index = (int) substr($row_datum, 1);
                            break;
                    }
                }
                break;
            }
        }
        $worksheet_info[0]['lastColumnIndex'] = $column_index;
        $worksheet_info[0]['totalRows'] = $row_index;
        $worksheet_info[0]['lastColumnLetter'] = Coordinate::string_from_column_index($worksheet_info[0]['lastColumnIndex'] + 1, true);
        $worksheet_info[0]['totalColumns'] = $worksheet_info[0]['lastColumnIndex'] + 1;
        $worksheet_info[0]['sheetState'] = Worksheet::SHEETSTATE_VISIBLE;
        // Close file
        fclose($file_handle);
        return $worksheet_info;
    }
    /**
     * Loads PhpSpreadsheet from file.
     */
    protected function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        // Load into this instance
        return $this->load_into_existing($filename, $spreadsheet);
    }
    private const COLOR_ARRAY = [
        'FF00FFFF',
        // 0 - cyan
        'FF000000',
        // 1 - black
        'FFFFFFFF',
        // 2 - white
        'FFFF0000',
        // 3 - red
        'FF00FF00',
        // 4 - green
        'FF0000FF',
        // 5 - blue
        'FFFFFF00',
        // 6 - yellow
        'FFFF00FF',
    ];
    private const FONT_STYLE_MAPPINGS = ['B' => 'bold', 'I' => 'italic', 'U' => 'underline'];
    /**
     * @param-out true $hasCalculatedValue
     */
    private function process_formula(string $row_datum, bool &$has_calculated_value, string &$cell_data_formula, string $row, string $column): void
    {
        $cell_data_formula = '=' . substr($row_datum, 1);
        //    Convert R1C1 style references to A1 style references (but only when not quoted)
        $temp = explode('"', $cell_data_formula);
        $key = false;
        foreach ($temp as &$value) {
            //    Only count/replace in alternate array entries
            $key = $key === false;
            if ($key) {
                preg_match_all('/(R(\[?-?\d*\]?))(C(\[?-?\d*\]?))/', $value, $cell_references, PREG_SET_ORDER + PREG_OFFSET_CAPTURE);
                //    Reverse the matches array, otherwise all our offsets will become incorrect if we modify our way
                //        through the formula from left to right. Reversing means that we work right to left.through
                //        the formula
                $cell_references = array_reverse($cell_references);
                //    Loop through each R1C1 style reference in turn, converting it to its A1 style equivalent,
                //        then modify the formula to use that new reference
                foreach ($cell_references as $cell_reference) {
                    $row_reference = $cell_reference[2][0];
                    //    Empty R reference is the current row
                    if ($row_reference == '') {
                        $row_reference = $row;
                    }
                    //    Bracketed R references are relative to the current row
                    if ($row_reference[0] == '[') {
                        $row_reference = (int) $row + (int) trim($row_reference, '[]');
                    }
                    $column_reference = $cell_reference[4][0];
                    //    Empty C reference is the current column
                    if ($column_reference == '') {
                        $column_reference = $column;
                    }
                    //    Bracketed C references are relative to the current column
                    if ($column_reference[0] == '[') {
                        $column_reference = (int) $column + (int) trim($column_reference, '[]');
                    }
                    $a1cell_reference = Coordinate::string_from_column_index((int) $column_reference) . $row_reference;
                    $value = substr_replace($value, $a1cell_reference, $cell_reference[0][1], strlen($cell_reference[0][0]));
                }
            }
        }
        unset($value);
        //    Then rebuild the formula string
        $cell_data_formula = implode('"', $temp);
        $has_calculated_value = true;
    }
    /** @param mixed[] $rowData */
    private function process_c_record(array $row_data, Spreadsheet &$spreadsheet, string &$row, string &$column): void
    {
        //    Read cell value data
        $has_calculated_value = false;
        $try_numeric = false;
        $cell_data_formula = $cell_data = '';
        $shared_column = $shared_row = -1;
        $shared_formula = false;
        foreach ($row_data as $row_datum) {
            /** @var string $rowDatum */
            switch ($row_datum[0]) {
                case 'X':
                    $column = substr($row_datum, 1);
                    break;
                case 'Y':
                    $row = substr($row_datum, 1);
                    break;
                case 'K':
                    $cell_data = substr($row_datum, 1);
                    $try_numeric = is_numeric($cell_data);
                    break;
                case 'E':
                    $this->process_formula($row_datum, $has_calculated_value, $cell_data_formula, $row, $column);
                    break;
                case 'A':
                    $comment = substr($row_datum, 1);
                    $column_letter = Coordinate::string_from_column_index((int) $column);
                    $spreadsheet->get_active_sheet()->get_comment("{$column_letter}{$row}")->get_text()->create_text($comment);
                    break;
                case 'C':
                    $shared_column = (int) substr($row_datum, 1);
                    break;
                case 'R':
                    $shared_row = (int) substr($row_datum, 1);
                    break;
                case 'S':
                    $shared_formula = true;
                    break;
            }
        }
        if ($shared_formula === true && $shared_row >= 0 && $shared_column >= 0) {
            $this_coordinate = Coordinate::string_from_column_index((int) $column) . $row;
            $shared_coordinate = Coordinate::string_from_column_index($shared_column) . $shared_row;
            /** @var string */
            $formula = $spreadsheet->get_active_sheet()->get_cell($shared_coordinate)->get_value();
            $spreadsheet->get_active_sheet()->get_cell($this_coordinate)->set_value($formula);
            $reference_helper = Reference_Helper::get_instance();
            $new_formula = $reference_helper->update_formula_references($formula, 'A1', (int) $column - $shared_column, (int) $row - $shared_row, '', true, false);
            $spreadsheet->get_active_sheet()->get_cell($this_coordinate)->set_value($new_formula);
            //$calc = $spreadsheet->getActiveSheet()->getCell($thisCoordinate)->getCalculatedValue();
            //$spreadsheet->getActiveSheet()->getCell($thisCoordinate)->setCalculatedValue($calc);
            $cell_data = Calculation::unwrap_result($cell_data);
            $spreadsheet->get_active_sheet()->get_cell($this_coordinate)->set_calculated_value($cell_data, $try_numeric);
            return;
        }
        $column_letter = Coordinate::string_from_column_index((int) $column);
        /** @var string */
        $cell_data = Calculation::unwrap_result($cell_data);
        // Set cell value
        $this->process_c_final($spreadsheet, $has_calculated_value, $cell_data_formula, $cell_data, "{$column_letter}{$row}", $try_numeric);
    }
    private function process_c_final(Spreadsheet &$spreadsheet, bool $has_calculated_value, string $cell_data_formula, string $cell_data, string $coordinate, bool $try_numeric): void
    {
        // Set cell value
        $spreadsheet->get_active_sheet()->get_cell($coordinate)->set_value($has_calculated_value ? $cell_data_formula : $cell_data);
        if ($has_calculated_value) {
            $cell_data = Calculation::unwrap_result($cell_data);
            $spreadsheet->get_active_sheet()->get_cell($coordinate)->set_calculated_value($cell_data, $try_numeric);
        }
    }
    /** @param mixed[] $rowData */
    private function process_f_record(array $row_data, Spreadsheet &$spreadsheet, string &$row, string &$column): void
    {
        //    Read cell formatting
        $format_style = $column_width = '';
        $start_col = $end_col = '';
        $font_style = '';
        $style_data = [];
        foreach ($row_data as $row_datum) {
            /** @var string $rowDatum */
            switch ($row_datum[0]) {
                case 'C':
                case 'X':
                    $column = substr($row_datum, 1);
                    break;
                case 'R':
                case 'Y':
                    $row = substr($row_datum, 1);
                    break;
                case 'P':
                    $format_style = $row_datum;
                    break;
                case 'W':
                    [$start_col, $end_col, $column_width] = explode(' ', substr($row_datum, 1));
                    break;
                case 'S':
                    $this->style_settings($row_datum, $style_data, $font_style);
                    break;
            }
        }
        /** @var string $formatStyle */
        $this->add_formats($spreadsheet, $format_style, $row, $column);
        $this->add_fonts($spreadsheet, $font_style, $row, $column);
        $this->add_style($spreadsheet, $style_data, $row, $column);
        $this->add_width($spreadsheet, $column_width, $start_col, $end_col);
    }
    private const STYLE_SETTINGS_FONT = ['D' => 'bold', 'I' => 'italic'];
    private const STYLE_SETTINGS_BORDER = ['B' => 'bottom', 'L' => 'left', 'R' => 'right', 'T' => 'top'];
    /** @param mixed[][] $styleData */
    private function style_settings(string $row_datum, array &$style_data, string &$font_style): void
    {
        $style_settings = substr($row_datum, 1);
        $i_max = strlen($style_settings);
        for ($i = 0; $i < $i_max; ++$i) {
            $char = $style_settings[$i];
            if (array_key_exists($char, self::STYLE_SETTINGS_FONT)) {
                $style_data['font'][self::STYLE_SETTINGS_FONT[$char]] = true;
            } elseif (array_key_exists($char, self::STYLE_SETTINGS_BORDER)) {
                $style_data['borders'][self::STYLE_SETTINGS_BORDER[$char]]['borderStyle'] = Border::BORDER_THIN;
                //* @phpstan-ignore-line
            } elseif ($char == 'S') {
                $style_data['fill']['fillType'] = Fill::FILL_PATTERN_GRAY125;
            } elseif ($char == 'M') {
                if (preg_match('/M([1-9]\d*)/', $style_settings, $matches)) {
                    $font_style = $matches[1];
                }
            }
        }
    }
    private function add_formats(Spreadsheet &$spreadsheet, string $format_style, string $row, string $column): void
    {
        if ($format_style && $column > '' && $row > '') {
            $column_letter = Coordinate::string_from_column_index((int) $column);
            if (isset($this->formats[$format_style]) && is_array($this->formats[$format_style])) {
                $spreadsheet->get_active_sheet()->get_style($column_letter . $row)->apply_from_array($this->formats[$format_style]);
            }
        }
    }
    private function add_fonts(Spreadsheet &$spreadsheet, string $font_style, string $row, string $column): void
    {
        if ($font_style && $column > '' && $row > '') {
            $column_letter = Coordinate::string_from_column_index((int) $column);
            if (isset($this->fonts[$font_style]) && is_array($this->fonts[$font_style])) {
                $spreadsheet->get_active_sheet()->get_style($column_letter . $row)->apply_from_array($this->fonts[$font_style]);
            }
        }
    }
    /** @param mixed[] $styleData */
    private function add_style(Spreadsheet &$spreadsheet, array $style_data, string $row, string $column): void
    {
        if (!empty($style_data) && $column > '' && $row > '') {
            $column_letter = Coordinate::string_from_column_index((int) $column);
            $spreadsheet->get_active_sheet()->get_style($column_letter . $row)->apply_from_array($style_data);
        }
    }
    private function add_width(Spreadsheet $spreadsheet, string $column_width, string $start_col, string $end_col): void
    {
        if ($column_width > '') {
            if ($start_col == $end_col) {
                $start_col = Coordinate::string_from_column_index((int) $start_col);
                $spreadsheet->get_active_sheet()->get_column_dimension($start_col)->set_width((float) $column_width);
            } else {
                $start_col = Coordinate::string_from_column_index((int) $start_col);
                $end_col = Coordinate::string_from_column_index((int) $end_col);
                $spreadsheet->get_active_sheet()->get_column_dimension($start_col)->set_width((float) $column_width);
                do {
                    /** @var string $startCol */
                    $spreadsheet->get_active_sheet()->get_column_dimension(String_Helper::string_increment($start_col))->set_width((float) $column_width);
                } while ($start_col !== $end_col);
            }
        }
    }
    /** @param string[] $rowData */
    private function process_p_record(array $row_data, Spreadsheet &$spreadsheet): void
    {
        //    Read shared styles
        $format_array = [];
        $from_formats = ['\-', '\ '];
        $to_formats = ['-', ' '];
        foreach ($row_data as $row_datum) {
            switch ($row_datum[0]) {
                case 'P':
                    $format_array['numberFormat']['formatCode'] = str_replace($from_formats, $to_formats, substr($row_datum, 1));
                    break;
                case 'E':
                case 'F':
                    $format_array['font']['name'] = substr($row_datum, 1);
                    break;
                case 'M':
                    $format_array['font']['size'] = (float) substr($row_datum, 1) / 20;
                    break;
                case 'L':
                    /** @var mixed[][][] $formatArray */
                    $this->process_p_colors($row_datum, $format_array);
                    break;
                case 'S':
                    $this->process_p_font_styles($row_datum, $format_array);
                    break;
            }
        }
        $this->process_p_final($spreadsheet, $format_array);
    }
    /** @param mixed[][][] $formatArray */
    private function process_p_colors(string $row_datum, array &$format_array): void
    {
        if (preg_match('/L([1-9]\d*)/', $row_datum, $matches)) {
            $font_color = (int) $matches[1] % 8;
            $format_array['font']['color']['argb'] = self::COLOR_ARRAY[$font_color];
        }
    }
    /** @param mixed[][] $formatArray */
    private function process_p_font_styles(string $row_datum, array &$format_array): void
    {
        $style_settings = substr($row_datum, 1);
        $i_max = strlen($style_settings);
        for ($i = 0; $i < $i_max; ++$i) {
            if (array_key_exists($style_settings[$i], self::FONT_STYLE_MAPPINGS)) {
                $format_array['font'][self::FONT_STYLE_MAPPINGS[$style_settings[$i]]] = true;
            }
        }
    }
    /** @param mixed[] $formatArray */
    private function process_p_final(Spreadsheet &$spreadsheet, array $format_array): void
    {
        if (array_key_exists('numberFormat', $format_array)) {
            $this->formats['P' . $this->format] = $format_array;
            ++$this->format;
        } elseif (array_key_exists('font', $format_array)) {
            ++$this->fontcount;
            $this->fonts[$this->fontcount] = $format_array;
            if ($this->fontcount === 1) {
                $spreadsheet->get_default_style()->apply_from_array($format_array);
            }
        }
    }
    /**
     * Loads PhpSpreadsheet from file into PhpSpreadsheet instance.
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet): Spreadsheet
    {
        // Open file
        $this->can_read_or_bust($filename);
        $file_handle = $this->file_handle;
        rewind($file_handle);
        // Create new Worksheets
        while ($spreadsheet->get_sheet_count() <= $this->sheet_index) {
            $spreadsheet->create_sheet();
        }
        $spreadsheet->set_active_sheet_index($this->sheet_index);
        $spreadsheet->get_active_sheet()->set_title(substr(basename($filename, '.slk'), 0, Worksheet::SHEET_TITLE_MAXIMUM_LENGTH));
        // Loop through file
        $column = $row = '';
        // loop through one row (line) at a time in the file
        while (($row_data_txt = fgets($file_handle)) !== false) {
            // convert SYLK encoded $rowData to UTF-8
            $row_data_txt = String_Helper::syl_kto_utf8($row_data_txt);
            // explode each row at semicolons while taking into account that literal semicolon (;)
            // is escaped like this (;;)
            $row_data = explode("\t", str_replace('¤', ';', str_replace(';', "\t", str_replace(';;', '¤', rtrim($row_data_txt)))));
            $data_type = array_shift($row_data);
            if ($data_type == 'P') {
                //    Read shared styles
                $this->process_p_record($row_data, $spreadsheet);
            } elseif ($data_type == 'C') {
                //    Read cell value data
                $this->process_c_record($row_data, $spreadsheet, $row, $column);
            } elseif ($data_type == 'F') {
                //    Read cell formatting
                $this->process_f_record($row_data, $spreadsheet, $row, $column);
            } else {
                $this->column_row_from_row_data($row_data, $column, $row);
            }
        }
        // Close file
        fclose($file_handle);
        // Return
        return $spreadsheet;
    }
    /** @param string[] $rowData */
    private function column_row_from_row_data(array $row_data, string &$column, string &$row): void
    {
        foreach ($row_data as $row_datum) {
            $char0 = $row_datum[0];
            if ($char0 === 'X' || $char0 == 'C') {
                $column = substr($row_datum, 1);
            } elseif ($char0 === 'Y' || $char0 == 'R') {
                $row = substr($row_datum, 1);
            }
        }
    }
    /**
     * Get sheet index.
     */
    public function get_sheet_index(): int
    {
        return $this->sheet_index;
    }
    /**
     * Set sheet index.
     *
     * @param int $sheetIndex Sheet index
     *
     * @return $this
     */
    public function set_sheet_index(int $sheet_index): static
    {
        $this->sheet_index = $sheet_index;
        return $this;
    }
}