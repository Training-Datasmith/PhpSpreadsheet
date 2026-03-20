<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
/**
 * <code>
 * Paper size taken from Office Open XML Part 4 - Markup Language Reference, page 1988:.
 *
 * 1 = Letter paper (8.5 in. by 11 in.)
 * 2 = Letter small paper (8.5 in. by 11 in.)
 * 3 = Tabloid paper (11 in. by 17 in.)
 * 4 = Ledger paper (17 in. by 11 in.)
 * 5 = Legal paper (8.5 in. by 14 in.)
 * 6 = Statement paper (5.5 in. by 8.5 in.)
 * 7 = Executive paper (7.25 in. by 10.5 in.)
 * 8 = A3 paper (297 mm by 420 mm)
 * 9 = A4 paper (210 mm by 297 mm)
 * 10 = A4 small paper (210 mm by 297 mm)
 * 11 = A5 paper (148 mm by 210 mm)
 * 12 = B4 paper (250 mm by 353 mm)
 * 13 = B5 paper (176 mm by 250 mm)
 * 14 = Folio paper (8.5 in. by 13 in.)
 * 15 = Quarto paper (215 mm by 275 mm)
 * 16 = Standard paper (10 in. by 14 in.)
 * 17 = Standard paper (11 in. by 17 in.)
 * 18 = Note paper (8.5 in. by 11 in.)
 * 19 = #9 envelope (3.875 in. by 8.875 in.)
 * 20 = #10 envelope (4.125 in. by 9.5 in.)
 * 21 = #11 envelope (4.5 in. by 10.375 in.)
 * 22 = #12 envelope (4.75 in. by 11 in.)
 * 23 = #14 envelope (5 in. by 11.5 in.)
 * 24 = C paper (17 in. by 22 in.)
 * 25 = D paper (22 in. by 34 in.)
 * 26 = E paper (34 in. by 44 in.)
 * 27 = DL envelope (110 mm by 220 mm)
 * 28 = C5 envelope (162 mm by 229 mm)
 * 29 = C3 envelope (324 mm by 458 mm)
 * 30 = C4 envelope (229 mm by 324 mm)
 * 31 = C6 envelope (114 mm by 162 mm)
 * 32 = C65 envelope (114 mm by 229 mm)
 * 33 = B4 envelope (250 mm by 353 mm)
 * 34 = B5 envelope (176 mm by 250 mm)
 * 35 = B6 envelope (176 mm by 125 mm)
 * 36 = Italy envelope (110 mm by 230 mm)
 * 37 = Monarch envelope (3.875 in. by 7.5 in.).
 * 38 = 6 3/4 envelope (3.625 in. by 6.5 in.)
 * 39 = US standard fanfold (14.875 in. by 11 in.)
 * 40 = German standard fanfold (8.5 in. by 12 in.)
 * 41 = German legal fanfold (8.5 in. by 13 in.)
 * 42 = ISO B4 (250 mm by 353 mm)
 * 43 = Japanese double postcard (200 mm by 148 mm)
 * 44 = Standard paper (9 in. by 11 in.)
 * 45 = Standard paper (10 in. by 11 in.)
 * 46 = Standard paper (15 in. by 11 in.)
 * 47 = Invite envelope (220 mm by 220 mm)
 * 50 = Letter extra paper (9.275 in. by 12 in.)
 * 51 = Legal extra paper (9.275 in. by 15 in.)
 * 52 = Tabloid extra paper (11.69 in. by 18 in.)
 * 53 = A4 extra paper (236 mm by 322 mm)
 * 54 = Letter transverse paper (8.275 in. by 11 in.)
 * 55 = A4 transverse paper (210 mm by 297 mm)
 * 56 = Letter extra transverse paper (9.275 in. by 12 in.)
 * 57 = SuperA/SuperA/A4 paper (227 mm by 356 mm)
 * 58 = SuperB/SuperB/A3 paper (305 mm by 487 mm)
 * 59 = Letter plus paper (8.5 in. by 12.69 in.)
 * 60 = A4 plus paper (210 mm by 330 mm)
 * 61 = A5 transverse paper (148 mm by 210 mm)
 * 62 = JIS B5 transverse paper (182 mm by 257 mm)
 * 63 = A3 extra paper (322 mm by 445 mm)
 * 64 = A5 extra paper (174 mm by 235 mm)
 * 65 = ISO B5 extra paper (201 mm by 276 mm)
 * 66 = A2 paper (420 mm by 594 mm)
 * 67 = A3 transverse paper (297 mm by 420 mm)
 * 68 = A3 extra transverse paper (322 mm by 445 mm)
 * </code>
 */
class Page_Setup
{
    // Paper size
    public const PAPERSIZE_LETTER = 1;
    public const PAPERSIZE_LETTER_SMALL = 2;
    public const PAPERSIZE_TABLOID = 3;
    public const PAPERSIZE_LEDGER = 4;
    public const PAPERSIZE_LEGAL = 5;
    public const PAPERSIZE_STATEMENT = 6;
    public const PAPERSIZE_EXECUTIVE = 7;
    public const PAPERSIZE_A3 = 8;
    public const PAPERSIZE_A4 = 9;
    public const PAPERSIZE_A4_SMALL = 10;
    public const PAPERSIZE_A5 = 11;
    public const PAPERSIZE_B4 = 12;
    public const PAPERSIZE_B5 = 13;
    public const PAPERSIZE_FOLIO = 14;
    public const PAPERSIZE_QUARTO = 15;
    public const PAPERSIZE_STANDARD_1 = 16;
    public const PAPERSIZE_STANDARD_2 = 17;
    public const PAPERSIZE_NOTE = 18;
    public const PAPERSIZE_NO9_ENVELOPE = 19;
    public const PAPERSIZE_NO10_ENVELOPE = 20;
    public const PAPERSIZE_NO11_ENVELOPE = 21;
    public const PAPERSIZE_NO12_ENVELOPE = 22;
    public const PAPERSIZE_NO14_ENVELOPE = 23;
    public const PAPERSIZE_C = 24;
    public const PAPERSIZE_D = 25;
    public const PAPERSIZE_E = 26;
    public const PAPERSIZE_DL_ENVELOPE = 27;
    public const PAPERSIZE_C5_ENVELOPE = 28;
    public const PAPERSIZE_C3_ENVELOPE = 29;
    public const PAPERSIZE_C4_ENVELOPE = 30;
    public const PAPERSIZE_C6_ENVELOPE = 31;
    public const PAPERSIZE_C65_ENVELOPE = 32;
    public const PAPERSIZE_B4_ENVELOPE = 33;
    public const PAPERSIZE_B5_ENVELOPE = 34;
    public const PAPERSIZE_B6_ENVELOPE = 35;
    public const PAPERSIZE_ITALY_ENVELOPE = 36;
    public const PAPERSIZE_MONARCH_ENVELOPE = 37;
    public const PAPERSIZE_6_3_4_ENVELOPE = 38;
    public const PAPERSIZE_US_STANDARD_FANFOLD = 39;
    public const PAPERSIZE_GERMAN_STANDARD_FANFOLD = 40;
    public const PAPERSIZE_GERMAN_LEGAL_FANFOLD = 41;
    public const PAPERSIZE_ISO_B4 = 42;
    public const PAPERSIZE_JAPANESE_DOUBLE_POSTCARD = 43;
    public const PAPERSIZE_STANDARD_PAPER_1 = 44;
    public const PAPERSIZE_STANDARD_PAPER_2 = 45;
    public const PAPERSIZE_STANDARD_PAPER_3 = 46;
    public const PAPERSIZE_INVITE_ENVELOPE = 47;
    public const PAPERSIZE_LETTER_EXTRA_PAPER = 48;
    public const PAPERSIZE_LEGAL_EXTRA_PAPER = 49;
    public const PAPERSIZE_TABLOID_EXTRA_PAPER = 50;
    public const PAPERSIZE_A4_EXTRA_PAPER = 51;
    public const PAPERSIZE_LETTER_TRANSVERSE_PAPER = 52;
    public const PAPERSIZE_A4_TRANSVERSE_PAPER = 53;
    public const PAPERSIZE_LETTER_EXTRA_TRANSVERSE_PAPER = 54;
    public const PAPERSIZE_SUPERA_SUPERA_A4_PAPER = 55;
    public const PAPERSIZE_SUPERB_SUPERB_A3_PAPER = 56;
    public const PAPERSIZE_LETTER_PLUS_PAPER = 57;
    public const PAPERSIZE_A4_PLUS_PAPER = 58;
    public const PAPERSIZE_A5_TRANSVERSE_PAPER = 59;
    public const PAPERSIZE_JIS_B5_TRANSVERSE_PAPER = 60;
    public const PAPERSIZE_A3_EXTRA_PAPER = 61;
    public const PAPERSIZE_A5_EXTRA_PAPER = 62;
    public const PAPERSIZE_ISO_B5_EXTRA_PAPER = 63;
    public const PAPERSIZE_A2_PAPER = 64;
    public const PAPERSIZE_A3_TRANSVERSE_PAPER = 65;
    public const PAPERSIZE_A3_EXTRA_TRANSVERSE_PAPER = 66;
    // Page orientation
    public const ORIENTATION_DEFAULT = 'default';
    public const ORIENTATION_LANDSCAPE = 'landscape';
    public const ORIENTATION_PORTRAIT = 'portrait';
    // Print Range Set Method
    public const SETPRINTRANGE_OVERWRITE = 'O';
    public const SETPRINTRANGE_INSERT = 'I';
    public const PAGEORDER_OVER_THEN_DOWN = 'overThenDown';
    public const PAGEORDER_DOWN_THEN_OVER = 'downThenOver';
    /**
     * Paper size default.
     */
    private static int $paper_size_default = self::PAPERSIZE_LETTER;
    /**
     * Paper size.
     */
    private ?int $paper_size = null;
    /**
     * Orientation default.
     */
    private static string $orientation_default = self::ORIENTATION_DEFAULT;
    /**
     * Orientation.
     */
    private string $orientation;
    /**
     * Scale (Print Scale).
     *
     * Print scaling. Valid values range from 10 to 400
     * This setting is overridden when fitToWidth and/or fitToHeight are in use
     */
    private ?int $scale = 100;
    /**
     * Fit To Page
     * Whether scale or fitToWith / fitToHeight applies.
     */
    private bool $fit_to_page = false;
    /**
     * Fit To Height
     * Number of vertical pages to fit on.
     */
    private ?int $fit_to_height = 1;
    /**
     * Fit To Width
     * Number of horizontal pages to fit on.
     */
    private ?int $fit_to_width = 1;
    /**
     * Columns to repeat at left.
     *
     * @var array{string, string} Containing start column and end column, empty array if option unset
     */
    private array $columns_to_repeat_at_left = ['', ''];
    /**
     * Rows to repeat at top.
     *
     * @var int[] Containing start row number and end row number, empty array if option unset
     */
    private array $rows_to_repeat_at_top = [0, 0];
    /**
     * Center page horizontally.
     */
    private bool $horizontal_centered = false;
    /**
     * Center page vertically.
     */
    private bool $vertical_centered = false;
    /**
     * Print area.
     */
    private ?string $print_area = null;
    /**
     * First page number.
     */
    private ?int $first_page_number = null;
    private string $page_order = self::PAGEORDER_DOWN_THEN_OVER;
    /**
     * Create a new PageSetup.
     */
    public function __construct()
    {
        $this->orientation = self::$orientation_default;
    }
    /**
     * Get Paper Size.
     */
    public function get_paper_size(): int
    {
        return $this->paper_size ?? self::$paper_size_default;
    }
    /**
     * Set Paper Size.
     *
     * @param int $paperSize see self::PAPERSIZE_*
     *
     * @return $this
     */
    public function set_paper_size(int $paper_size): static
    {
        $this->paper_size = $paper_size;
        return $this;
    }
    /**
     * Get Paper Size default.
     */
    public static function get_paper_size_default(): int
    {
        return self::$paper_size_default;
    }
    /**
     * Set Paper Size Default.
     */
    public static function set_paper_size_default(int $paper_size): void
    {
        self::$paper_size_default = $paper_size;
    }
    /**
     * Get Orientation.
     */
    public function get_orientation(): string
    {
        return $this->orientation;
    }
    /**
     * Set Orientation.
     *
     * @param string $orientation see self::ORIENTATION_*
     *
     * @return $this
     */
    public function set_orientation(string $orientation): static
    {
        if ($orientation === self::ORIENTATION_LANDSCAPE || $orientation === self::ORIENTATION_PORTRAIT || $orientation === self::ORIENTATION_DEFAULT) {
            $this->orientation = $orientation;
        }
        return $this;
    }
    public static function get_orientation_default(): string
    {
        return self::$orientation_default;
    }
    public static function set_orientation_default(string $orientation): void
    {
        if ($orientation === self::ORIENTATION_LANDSCAPE || $orientation === self::ORIENTATION_PORTRAIT || $orientation === self::ORIENTATION_DEFAULT) {
            self::$orientation_default = $orientation;
        }
    }
    /**
     * Get Scale.
     */
    public function get_scale(): ?int
    {
        return $this->scale;
    }
    /**
     * Set Scale.
     * Print scaling. Valid values range from 10 to 400
     * This setting is overridden when fitToWidth and/or fitToHeight are in use.
     *
     * @param bool $update Update fitToPage so scaling applies rather than fitToHeight / fitToWidth
     *
     * @return $this
     */
    public function set_scale(?int $scale, bool $update = true): static
    {
        // Microsoft Office Excel 2007 only allows setting a scale between 10 and 400 via the user interface,
        // but it is apparently still able to handle any scale >= 0, where 0 results in 100
        if ($scale === null || $scale >= 0) {
            $this->scale = $scale;
            if ($update) {
                $this->fit_to_page = false;
            }
        } else {
            throw new Php_Spreadsheet_Exception('Scale must not be negative');
        }
        return $this;
    }
    /**
     * Get Fit To Page.
     */
    public function get_fit_to_page(): bool
    {
        return $this->fit_to_page;
    }
    /**
     * Set Fit To Page.
     *
     * @return $this
     */
    public function set_fit_to_page(bool $fit_to_page): static
    {
        $this->fit_to_page = $fit_to_page;
        return $this;
    }
    /**
     * Get Fit To Height.
     */
    public function get_fit_to_height(): ?int
    {
        return $this->fit_to_height;
    }
    /**
     * Set Fit To Height.
     *
     * @param bool $update Update fitToPage so it applies rather than scaling
     *
     * @return $this
     */
    public function set_fit_to_height(?int $fit_to_height, bool $update = true): static
    {
        $this->fit_to_height = $fit_to_height;
        if ($update) {
            $this->fit_to_page = true;
        }
        return $this;
    }
    /**
     * Get Fit To Width.
     */
    public function get_fit_to_width(): ?int
    {
        return $this->fit_to_width;
    }
    /**
     * Set Fit To Width.
     *
     * @param bool $update Update fitToPage so it applies rather than scaling
     *
     * @return $this
     */
    public function set_fit_to_width(?int $value, bool $update = true): static
    {
        $this->fit_to_width = $value;
        if ($update) {
            $this->fit_to_page = true;
        }
        return $this;
    }
    /**
     * Is Columns to repeat at left set?
     */
    public function is_columns_to_repeat_at_left_set(): bool
    {
        if (empty($this->columns_to_repeat_at_left)) {
            return false;
        }
        if ($this->columns_to_repeat_at_left[0] != '' && $this->columns_to_repeat_at_left[1] != '') {
            return true;
        }
        return false;
    }
    /**
     * Get Columns to repeat at left.
     *
     * @return array{string, string} Containing start column and end column, empty array if option unset
     */
    public function get_columns_to_repeat_at_left(): array
    {
        return $this->columns_to_repeat_at_left;
    }
    /**
     * Set Columns to repeat at left.
     *
     * @param array{string, string} $columnsToRepeatAtLeft Containing start column and end column, empty array if option unset
     *
     * @return $this
     */
    public function set_columns_to_repeat_at_left(array $columns_to_repeat_at_left): static
    {
        $this->columns_to_repeat_at_left = $columns_to_repeat_at_left;
        return $this;
    }
    /**
     * Set Columns to repeat at left by start and end.
     *
     * @param string $start eg: 'A'
     * @param string $end eg: 'B'
     *
     * @return $this
     */
    public function set_columns_to_repeat_at_left_by_start_and_end(string $start, string $end): static
    {
        $this->columns_to_repeat_at_left = [$start, $end];
        return $this;
    }
    /**
     * Is Rows to repeat at top set?
     */
    public function is_rows_to_repeat_at_top_set(): bool
    {
        if (empty($this->rows_to_repeat_at_top)) {
            return false;
        }
        if ($this->rows_to_repeat_at_top[0] != 0 && $this->rows_to_repeat_at_top[1] != 0) {
            return true;
        }
        return false;
    }
    /**
     * Get Rows to repeat at top.
     *
     * @return int[] Containing start column and end column, empty array if option unset
     */
    public function get_rows_to_repeat_at_top(): array
    {
        return $this->rows_to_repeat_at_top;
    }
    /**
     * Set Rows to repeat at top.
     *
     * @param int[] $rowsToRepeatAtTop Containing start column and end column, empty array if option unset
     *
     * @return $this
     */
    public function set_rows_to_repeat_at_top(array $rows_to_repeat_at_top): static
    {
        $this->rows_to_repeat_at_top = $rows_to_repeat_at_top;
        return $this;
    }
    /**
     * Set Rows to repeat at top by start and end.
     *
     * @param int $start eg: 1
     * @param int $end eg: 1
     *
     * @return $this
     */
    public function set_rows_to_repeat_at_top_by_start_and_end(int $start, int $end): static
    {
        $this->rows_to_repeat_at_top = [$start, $end];
        return $this;
    }
    /**
     * Get center page horizontally.
     */
    public function get_horizontal_centered(): bool
    {
        return $this->horizontal_centered;
    }
    /**
     * Set center page horizontally.
     *
     * @return $this
     */
    public function set_horizontal_centered(bool $value): static
    {
        $this->horizontal_centered = $value;
        return $this;
    }
    /**
     * Get center page vertically.
     */
    public function get_vertical_centered(): bool
    {
        return $this->vertical_centered;
    }
    /**
     * Set center page vertically.
     *
     * @return $this
     */
    public function set_vertical_centered(bool $value): static
    {
        $this->vertical_centered = $value;
        return $this;
    }
    /**
     * Get print area.
     *
     * @param int $index Identifier for a specific print area range if several ranges have been set
     *                            Default behaviour, or an index value of 0, will return all ranges as a comma-separated string
     *                            Otherwise, the specific range identified by the value of $index will be returned
     *                            Print areas are numbered from 1
     */
    public function get_print_area(int $index = 0): string
    {
        if ($index == 0) {
            return (string) $this->print_area;
        }
        $print_areas = explode(',', (string) $this->print_area);
        if (isset($print_areas[$index - 1])) {
            return $print_areas[$index - 1];
        }
        throw new Php_Spreadsheet_Exception('Requested Print Area does not exist');
    }
    /**
     * Is print area set?
     *
     * @param int $index Identifier for a specific print area range if several ranges have been set
     *                            Default behaviour, or an index value of 0, will identify whether any print range is set
     *                            Otherwise, existence of the range identified by the value of $index will be returned
     *                            Print areas are numbered from 1
     */
    public function is_print_area_set(int $index = 0): bool
    {
        if ($index == 0) {
            return $this->print_area !== null;
        }
        $print_areas = explode(',', (string) $this->print_area);
        return isset($print_areas[$index - 1]);
    }
    /**
     * Clear a print area.
     *
     * @param int $index Identifier for a specific print area range if several ranges have been set
     *                            Default behaviour, or an index value of 0, will clear all print ranges that are set
     *                            Otherwise, the range identified by the value of $index will be removed from the series
     *                            Print areas are numbered from 1
     *
     * @return $this
     */
    public function clear_print_area(int $index = 0): static
    {
        if ($index == 0) {
            $this->print_area = null;
        } else {
            $print_areas = explode(',', (string) $this->print_area);
            if (isset($print_areas[$index - 1])) {
                unset($print_areas[$index - 1]);
                $this->print_area = implode(',', $print_areas);
            }
        }
        return $this;
    }
    /**
     * Set print area. e.g. 'A1:D10' or 'A1:D10,G5:M20'.
     *
     * @param int $index Identifier for a specific print area range allowing several ranges to be set
     *                            When the method is "O"verwrite, then a positive integer index will overwrite that indexed
     *                                entry in the print areas list; a negative index value will identify which entry to
     *                                overwrite working backward through the print area to the list, with the last entry as -1.
     *                                Specifying an index value of 0, will overwrite <b>all</b> existing print ranges.
     *                            When the method is "I"nsert, then a positive index will insert after that indexed entry in
     *                                the print areas list, while a negative index will insert before the indexed entry.
     *                                Specifying an index value of 0, will always append the new print range at the end of the
     *                                list.
     *                            Print areas are numbered from 1
     * @param string $method Determines the method used when setting multiple print areas
     *                            Default behaviour, or the "O" method, overwrites existing print area
     *                            The "I" method, inserts the new print area before any specified index, or at the end of the list
     *
     * @return $this
     */
    public function set_print_area(string $value, int $index = 0, string $method = self::SETPRINTRANGE_OVERWRITE): static
    {
        if (str_contains($value, '!')) {
            throw new Php_Spreadsheet_Exception('Cell coordinate must not specify a worksheet.');
        }
        if (!str_contains($value, ':')) {
            throw new Php_Spreadsheet_Exception('Cell coordinate must be a range of cells.');
        }
        if (str_contains($value, '$')) {
            throw new Php_Spreadsheet_Exception('Cell coordinate must not be absolute.');
        }
        $value = strtoupper($value);
        if (!$this->print_area) {
            $index = 0;
        }
        if ($method == self::SETPRINTRANGE_OVERWRITE) {
            if ($index == 0) {
                $this->print_area = $value;
            } else {
                $print_areas = explode(',', (string) $this->print_area);
                if ($index < 0) {
                    $index = count($print_areas) - abs($index) + 1;
                }
                if ($index <= 0 || $index > count($print_areas)) {
                    throw new Php_Spreadsheet_Exception('Invalid index for setting print range.');
                }
                $print_areas[$index - 1] = $value;
                $this->print_area = implode(',', $print_areas);
            }
        } elseif ($method == self::SETPRINTRANGE_INSERT) {
            if ($index == 0) {
                $this->print_area = $this->print_area ? $this->print_area . ',' . $value : $value;
            } else {
                $print_areas = explode(',', (string) $this->print_area);
                if ($index < 0) {
                    $index = abs($index) - 1;
                }
                if ($index > count($print_areas)) {
                    throw new Php_Spreadsheet_Exception('Invalid index for setting print range.');
                }
                $print_areas = array_merge(array_slice($print_areas, 0, $index), [$value], array_slice($print_areas, $index));
                $this->print_area = implode(',', $print_areas);
            }
        } else {
            throw new Php_Spreadsheet_Exception('Invalid method for setting print range.');
        }
        return $this;
    }
    /**
     * Add a new print area (e.g. 'A1:D10' or 'A1:D10,G5:M20') to the list of print areas.
     *
     * @param int $index Identifier for a specific print area range allowing several ranges to be set
     *                            A positive index will insert after that indexed entry in the print areas list, while a
     *                                negative index will insert before the indexed entry.
     *                                Specifying an index value of 0, will always append the new print range at the end of the
     *                                list.
     *                            Print areas are numbered from 1
     *
     * @return $this
     */
    public function add_print_area(string $value, int $index = -1): static
    {
        return $this->set_print_area($value, $index, self::SETPRINTRANGE_INSERT);
    }
    /**
     * Set print area.
     *
     * @param int $column1 Column 1
     * @param int $row1 Row 1
     * @param int $column2 Column 2
     * @param int $row2 Row 2
     * @param int $index Identifier for a specific print area range allowing several ranges to be set
     *                                When the method is "O"verwrite, then a positive integer index will overwrite that indexed
     *                                    entry in the print areas list; a negative index value will identify which entry to
     *                                    overwrite working backward through the print area to the list, with the last entry as -1.
     *                                    Specifying an index value of 0, will overwrite <b>all</b> existing print ranges.
     *                                When the method is "I"nsert, then a positive index will insert after that indexed entry in
     *                                    the print areas list, while a negative index will insert before the indexed entry.
     *                                    Specifying an index value of 0, will always append the new print range at the end of the
     *                                    list.
     *                                Print areas are numbered from 1
     * @param string $method Determines the method used when setting multiple print areas
     *                                Default behaviour, or the "O" method, overwrites existing print area
     *                                The "I" method, inserts the new print area before any specified index, or at the end of the list
     *
     * @return $this
     */
    public function set_print_area_by_column_and_row(int $column1, int $row1, int $column2, int $row2, int $index = 0, string $method = self::SETPRINTRANGE_OVERWRITE): static
    {
        return $this->set_print_area(Coordinate::string_from_column_index($column1) . $row1 . ':' . Coordinate::string_from_column_index($column2) . $row2, $index, $method);
    }
    /**
     * Add a new print area to the list of print areas.
     *
     * @param int $column1 Start Column for the print area
     * @param int $row1 Start Row for the print area
     * @param int $column2 End Column for the print area
     * @param int $row2 End Row for the print area
     * @param int $index Identifier for a specific print area range allowing several ranges to be set
     *                                A positive index will insert after that indexed entry in the print areas list, while a
     *                                    negative index will insert before the indexed entry.
     *                                    Specifying an index value of 0, will always append the new print range at the end of the
     *                                    list.
     *                                Print areas are numbered from 1
     *
     * @return $this
     */
    public function add_print_area_by_column_and_row(int $column1, int $row1, int $column2, int $row2, int $index = -1): static
    {
        return $this->set_print_area(Coordinate::string_from_column_index($column1) . $row1 . ':' . Coordinate::string_from_column_index($column2) . $row2, $index, self::SETPRINTRANGE_INSERT);
    }
    /**
     * Get first page number.
     */
    public function get_first_page_number(): ?int
    {
        return $this->first_page_number;
    }
    /**
     * Set first page number.
     *
     * @return $this
     */
    public function set_first_page_number(?int $value): static
    {
        $this->first_page_number = $value;
        return $this;
    }
    /**
     * Reset first page number.
     *
     * @return $this
     */
    public function reset_first_page_number(): static
    {
        return $this->set_first_page_number(null);
    }
    public function get_page_order(): string
    {
        return $this->page_order;
    }
    public function set_page_order(?string $page_order): self
    {
        if ($page_order === null || $page_order === self::PAGEORDER_DOWN_THEN_OVER || $page_order === self::PAGEORDER_OVER_THEN_DOWN) {
            $this->page_order = $page_order ?? self::PAGEORDER_DOWN_THEN_OVER;
        }
        return $this;
    }
}