<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Font as FontStyle;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
class Font
{
    // Methods for resolving autosize value
    public const AUTOSIZE_METHOD_APPROX = 'approx';
    public const AUTOSIZE_METHOD_EXACT = 'exact';
    private const AUTOSIZE_METHODS = [self::AUTOSIZE_METHOD_APPROX, self::AUTOSIZE_METHOD_EXACT];
    /** Character set codes used by BIFF5-8 in Font records */
    public const CHARSET_ANSI_LATIN = 0x0;
    public const CHARSET_SYSTEM_DEFAULT = 0x1;
    public const CHARSET_SYMBOL = 0x2;
    public const CHARSET_APPLE_ROMAN = 0x4d;
    public const CHARSET_ANSI_JAPANESE_SHIFTJIS = 0x80;
    public const CHARSET_ANSI_KOREAN_HANGUL = 0x81;
    public const CHARSET_ANSI_KOREAN_JOHAB = 0x82;
    public const CHARSET_ANSI_CHINESE_SIMIPLIFIED = 0x86;
    //    gb2312
    public const CHARSET_ANSI_CHINESE_TRADITIONAL = 0x88;
    //    big5
    public const CHARSET_ANSI_GREEK = 0xa1;
    public const CHARSET_ANSI_TURKISH = 0xa2;
    public const CHARSET_ANSI_VIETNAMESE = 0xa3;
    public const CHARSET_ANSI_HEBREW = 0xb1;
    public const CHARSET_ANSI_ARABIC = 0xb2;
    public const CHARSET_ANSI_BALTIC = 0xba;
    public const CHARSET_ANSI_CYRILLIC = 0xcc;
    public const CHARSET_ANSI_THAI = 0xdd;
    public const CHARSET_ANSI_LATIN_II = 0xee;
    public const CHARSET_OEM_LATIN_I = 0xff;
    //  XXX: Constants created!
    /** Font filenames */
    public const ARIAL = 'arial.ttf';
    public const ARIAL_BOLD = 'arialbd.ttf';
    public const ARIAL_ITALIC = 'ariali.ttf';
    public const ARIAL_BOLD_ITALIC = 'arialbi.ttf';
    public const CALIBRI = 'calibri.ttf';
    public const CALIBRI_BOLD = 'calibrib.ttf';
    public const CALIBRI_ITALIC = 'calibrii.ttf';
    public const CALIBRI_BOLD_ITALIC = 'calibriz.ttf';
    public const COMIC_SANS_MS = 'comic.ttf';
    public const COMIC_SANS_MS_BOLD = 'comicbd.ttf';
    public const COURIER_NEW = 'cour.ttf';
    public const COURIER_NEW_BOLD = 'courbd.ttf';
    public const COURIER_NEW_ITALIC = 'couri.ttf';
    public const COURIER_NEW_BOLD_ITALIC = 'courbi.ttf';
    public const GEORGIA = 'georgia.ttf';
    public const GEORGIA_BOLD = 'georgiab.ttf';
    public const GEORGIA_ITALIC = 'georgiai.ttf';
    public const GEORGIA_BOLD_ITALIC = 'georgiaz.ttf';
    public const IMPACT = 'impact.ttf';
    public const LIBERATION_SANS = 'LiberationSans-Regular.ttf';
    public const LIBERATION_SANS_BOLD = 'LiberationSans-Bold.ttf';
    public const LIBERATION_SANS_ITALIC = 'LiberationSans-Italic.ttf';
    public const LIBERATION_SANS_BOLD_ITALIC = 'LiberationSans-BoldItalic.ttf';
    public const LUCIDA_CONSOLE = 'lucon.ttf';
    public const LUCIDA_SANS_UNICODE = 'l_10646.ttf';
    public const MICROSOFT_SANS_SERIF = 'micross.ttf';
    public const PALATINO_LINOTYPE = 'pala.ttf';
    public const PALATINO_LINOTYPE_BOLD = 'palab.ttf';
    public const PALATINO_LINOTYPE_ITALIC = 'palai.ttf';
    public const PALATINO_LINOTYPE_BOLD_ITALIC = 'palabi.ttf';
    public const SYMBOL = 'symbol.ttf';
    public const TAHOMA = 'tahoma.ttf';
    public const TAHOMA_BOLD = 'tahomabd.ttf';
    public const TIMES_NEW_ROMAN = 'times.ttf';
    public const TIMES_NEW_ROMAN_BOLD = 'timesbd.ttf';
    public const TIMES_NEW_ROMAN_ITALIC = 'timesi.ttf';
    public const TIMES_NEW_ROMAN_BOLD_ITALIC = 'timesbi.ttf';
    public const TREBUCHET_MS = 'trebuc.ttf';
    public const TREBUCHET_MS_BOLD = 'trebucbd.ttf';
    public const TREBUCHET_MS_ITALIC = 'trebucit.ttf';
    public const TREBUCHET_MS_BOLD_ITALIC = 'trebucbi.ttf';
    public const VERDANA = 'verdana.ttf';
    public const VERDANA_BOLD = 'verdanab.ttf';
    public const VERDANA_ITALIC = 'verdanai.ttf';
    public const VERDANA_BOLD_ITALIC = 'verdanaz.ttf';
    public const FONT_FILE_NAMES = ['Arial' => ['x' => self::ARIAL, 'xb' => self::ARIAL_BOLD, 'xi' => self::ARIAL_ITALIC, 'xbi' => self::ARIAL_BOLD_ITALIC], 'Calibri' => ['x' => self::CALIBRI, 'xb' => self::CALIBRI_BOLD, 'xi' => self::CALIBRI_ITALIC, 'xbi' => self::CALIBRI_BOLD_ITALIC], 'Comic Sans MS' => ['x' => self::COMIC_SANS_MS, 'xb' => self::COMIC_SANS_MS_BOLD, 'xi' => self::COMIC_SANS_MS, 'xbi' => self::COMIC_SANS_MS_BOLD], 'Courier New' => ['x' => self::COURIER_NEW, 'xb' => self::COURIER_NEW_BOLD, 'xi' => self::COURIER_NEW_ITALIC, 'xbi' => self::COURIER_NEW_BOLD_ITALIC], 'Georgia' => ['x' => self::GEORGIA, 'xb' => self::GEORGIA_BOLD, 'xi' => self::GEORGIA_ITALIC, 'xbi' => self::GEORGIA_BOLD_ITALIC], 'Impact' => ['x' => self::IMPACT, 'xb' => self::IMPACT, 'xi' => self::IMPACT, 'xbi' => self::IMPACT], 'Liberation Sans' => ['x' => self::LIBERATION_SANS, 'xb' => self::LIBERATION_SANS_BOLD, 'xi' => self::LIBERATION_SANS_ITALIC, 'xbi' => self::LIBERATION_SANS_BOLD_ITALIC], 'Lucida Console' => ['x' => self::LUCIDA_CONSOLE, 'xb' => self::LUCIDA_CONSOLE, 'xi' => self::LUCIDA_CONSOLE, 'xbi' => self::LUCIDA_CONSOLE], 'Lucida Sans Unicode' => ['x' => self::LUCIDA_SANS_UNICODE, 'xb' => self::LUCIDA_SANS_UNICODE, 'xi' => self::LUCIDA_SANS_UNICODE, 'xbi' => self::LUCIDA_SANS_UNICODE], 'Microsoft Sans Serif' => ['x' => self::MICROSOFT_SANS_SERIF, 'xb' => self::MICROSOFT_SANS_SERIF, 'xi' => self::MICROSOFT_SANS_SERIF, 'xbi' => self::MICROSOFT_SANS_SERIF], 'Palatino Linotype' => ['x' => self::PALATINO_LINOTYPE, 'xb' => self::PALATINO_LINOTYPE_BOLD, 'xi' => self::PALATINO_LINOTYPE_ITALIC, 'xbi' => self::PALATINO_LINOTYPE_BOLD_ITALIC], 'Symbol' => ['x' => self::SYMBOL, 'xb' => self::SYMBOL, 'xi' => self::SYMBOL, 'xbi' => self::SYMBOL], 'Tahoma' => ['x' => self::TAHOMA, 'xb' => self::TAHOMA_BOLD, 'xi' => self::TAHOMA, 'xbi' => self::TAHOMA_BOLD], 'Times New Roman' => ['x' => self::TIMES_NEW_ROMAN, 'xb' => self::TIMES_NEW_ROMAN_BOLD, 'xi' => self::TIMES_NEW_ROMAN_ITALIC, 'xbi' => self::TIMES_NEW_ROMAN_BOLD_ITALIC], 'Trebuchet MS' => ['x' => self::TREBUCHET_MS, 'xb' => self::TREBUCHET_MS_BOLD, 'xi' => self::TREBUCHET_MS_ITALIC, 'xbi' => self::TREBUCHET_MS_BOLD_ITALIC], 'Verdana' => ['x' => self::VERDANA, 'xb' => self::VERDANA_BOLD, 'xi' => self::VERDANA_ITALIC, 'xbi' => self::VERDANA_BOLD_ITALIC]];
    /**
     * Array that can be used to supplement FONT_FILE_NAMES for calculating exact width.
     *
     * @var array<string, array<string, string>>
     */
    private static array $extra_font_array = [];
    /** @param array<string, array<string, string>> $extraFontArray */
    public static function set_extra_font_array(array $extra_font_array): void
    {
        self::$extra_font_array = $extra_font_array;
    }
    /** @return array<string, array<string, string>> */
    public static function get_extra_font_array(): array
    {
        return self::$extra_font_array;
    }
    /**
     * AutoSize method.
     */
    private static string $auto_size_method = self::AUTOSIZE_METHOD_APPROX;
    /**
     * Path to folder containing TrueType font .ttf files.
     */
    private static string $true_type_font_path = '';
    /**
     * How wide is a default column for a given default font and size?
     * Empirical data found by inspecting real Excel files and reading off the pixel width
     * in Microsoft Office Excel 2007.
     * Added height in points.
     */
    public const DEFAULT_COLUMN_WIDTHS = ['Arial' => [1 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 2 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 3 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.0], 4 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.75], 5 => ['px' => 40, 'width' => 10.0, 'height' => 8.25], 6 => ['px' => 48, 'width' => 9.59765625, 'height' => 8.25], 7 => ['px' => 48, 'width' => 9.59765625, 'height' => 9.0], 8 => ['px' => 56, 'width' => 9.33203125, 'height' => 11.25], 9 => ['px' => 64, 'width' => 9.140625, 'height' => 12.0], 10 => ['px' => 64, 'width' => 9.140625, 'height' => 12.75]], 'Calibri' => [1 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 2 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 3 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.0], 4 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.75], 5 => ['px' => 40, 'width' => 10.0, 'height' => 8.25], 6 => ['px' => 48, 'width' => 9.59765625, 'height' => 8.25], 7 => ['px' => 48, 'width' => 9.59765625, 'height' => 9.0], 8 => ['px' => 56, 'width' => 9.33203125, 'height' => 11.25], 9 => ['px' => 56, 'width' => 9.33203125, 'height' => 12.0], 10 => ['px' => 64, 'width' => 9.140625, 'height' => 12.75], 11 => ['px' => 64, 'width' => 9.140625, 'height' => 15.0]], 'Verdana' => [1 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 2 => ['px' => 24, 'width' => 12.0, 'height' => 5.25], 3 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.0], 4 => ['px' => 32, 'width' => 10.6640625, 'height' => 6.75], 5 => ['px' => 40, 'width' => 10.0, 'height' => 8.25], 6 => ['px' => 48, 'width' => 9.59765625, 'height' => 8.25], 7 => ['px' => 48, 'width' => 9.59765625, 'height' => 9.0], 8 => ['px' => 64, 'width' => 9.140625, 'height' => 10.5], 9 => ['px' => 72, 'width' => 9.0, 'height' => 11.25], 10 => ['px' => 72, 'width' => 9.0, 'height' => 12.75]]];
    /**
     * Set autoSize method.
     *
     * @param string $method see self::AUTOSIZE_METHOD_*
     *
     * @return bool Success or failure
     */
    public static function set_auto_size_method(string $method): bool
    {
        if (!in_array($method, self::AUTOSIZE_METHODS)) {
            return false;
        }
        self::$auto_size_method = $method;
        return true;
    }
    /**
     * Get autoSize method.
     */
    public static function get_auto_size_method(): string
    {
        return self::$auto_size_method;
    }
    /**
     * Set the path to the folder containing .ttf files. There should be a trailing slash.
     * Path will be recursively searched for font file.
     * Typical locations on various platforms:
     *    <ul>
     *        <li>C:/Windows/Fonts/</li>
     *        <li>/usr/share/fonts/truetype/</li>
     *        <li>~/.fonts/</li>
     * </ul>.
     */
    public static function set_true_type_font_path(string $folder_path): void
    {
        self::$true_type_font_path = $folder_path;
    }
    /**
     * Get the path to the folder containing .ttf files.
     */
    public static function get_true_type_font_path(): string
    {
        return self::$true_type_font_path;
    }
    /**
     * Pad amount for exact in pixels; use best guess if null.
     */
    private static null|float|int $padding_amount_exact = null;
    /**
     * Set pad amount for exact in pixels; use best guess if null.
     */
    public static function set_padding_amount_exact(null|float|int $padding_amount_exact): void
    {
        self::$padding_amount_exact = $padding_amount_exact;
    }
    /**
     * Get pad amount for exact in pixels; or null if using best guess.
     */
    public static function get_padding_amount_exact(): null|float|int
    {
        return self::$padding_amount_exact;
    }
    /**
     * Calculate an (approximate) OpenXML column width, based on font size and text contained.
     *
     * @param FontStyle $font Font object
     * @param null|RichText|string $cellText Text to calculate width
     * @param int $rotation Rotation angle
     * @param null|FontStyle $defaultFont Font object
     * @param bool $filterAdjustment Add space for Autofilter or Table dropdown
     */
    public static function calculate_column_width(Font_Style $font, $cell_text = '', int $rotation = 0, ?Font_Style $default_font = null, bool $filter_adjustment = false, int $indent_adjustment = 0): float
    {
        // If it is rich text, use plain text
        if ($cell_text instanceof Rich_Text) {
            $cell_text = $cell_text->get_plain_text();
        }
        // Special case if there are one or more newline characters ("\n")
        $cell_text = (string) $cell_text;
        if (str_contains($cell_text, "\n")) {
            $line_texts = explode("\n", $cell_text);
            $line_widths = [];
            foreach ($line_texts as $line_text) {
                $line_widths[] = self::calculate_column_width($font, $line_text, $rotation = 0, $default_font, $filter_adjustment);
            }
            return max($line_widths);
            // width of longest line in cell
        }
        // Try to get the exact text width in pixels
        $approximate = self::$auto_size_method === self::AUTOSIZE_METHOD_APPROX;
        $column_width = 0;
        if (!$approximate) {
            try {
                $column_width_adjust = ceil(self::get_text_width_pixels_exact(str_repeat('n', ($filter_adjustment ? 3 : 1) + $indent_adjustment * 2), $font, 0) * 1.07);
                // Width of text in pixels excl. padding
                // and addition because Excel adds some padding, just use approx width of 'n' glyph
                $column_width = self::get_text_width_pixels_exact($cell_text, $font, $rotation) + (self::$padding_amount_exact ?? $column_width_adjust);
            } catch (Php_Spreadsheet_Exception) {
                $approximate = true;
            }
        }
        if ($approximate) {
            $column_width_adjust = self::get_text_width_pixels_approx(str_repeat('n', ($filter_adjustment ? 3 : 1) + $indent_adjustment * 2), $font, 0);
            // Width of text in pixels excl. padding, approximation
            // and addition because Excel adds some padding, just use approx width of 'n' glyph
            $column_width = self::get_text_width_pixels_approx($cell_text, $font, $rotation) + $column_width_adjust;
        }
        // Convert from pixel width to column width
        $column_width = Drawing::pixels_to_cell_dimension((int) $column_width, $default_font ?? new Font_Style());
        // Return
        return round($column_width, 4);
    }
    /**
     * Get GD text width in pixels for a string of text in a certain font at a certain rotation angle.
     */
    public static function get_text_width_pixels_exact(string $text, Font_Style $font, int $rotation = 0): float
    {
        // font size should really be supplied in pixels in GD2,
        // but since GD2 seems to assume 72dpi, pixels and points are the same
        $font_file = self::get_true_type_font_file_from_font($font);
        $text_box = imagettfbbox($font->get_size() ?? 10.0, $rotation, $font_file, $text);
        if ($text_box === false) {
            // @codeCoverageIgnoreStart
            throw new Php_Spreadsheet_Exception('imagettfbbox failed');
            // @codeCoverageIgnoreEnd
        }
        // Get corners positions
        /** @var int[] $textBox */
        $lower_left_corner_x = $text_box[0];
        $lower_right_corner_x = $text_box[2];
        $upper_right_corner_x = $text_box[4];
        $upper_left_corner_x = $text_box[6];
        // Consider the rotation when calculating the width
        return round(max($lower_right_corner_x - $upper_left_corner_x, $upper_right_corner_x - $lower_left_corner_x), 4);
    }
    /**
     * Get approximate width in pixels for a string of text in a certain font at a certain rotation angle.
     *
     * @return int Text width in pixels (no padding added)
     */
    public static function get_text_width_pixels_approx(string $column_text, Font_Style $font, int $rotation = 0): int
    {
        $font_name = $font->get_name();
        $font_size = $font->get_size();
        // Calculate column width in pixels.
        // We assume fixed glyph width, but count double for "fullwidth" characters.
        // Result varies with font name and size.
        switch ($font_name) {
            case 'Arial':
            case 'Verdana':
                // value 8 was set because of experience in different exports at Arial 10 font.
                $column_width = 8 * String_Helper::count_characters_dbcs($column_text);
                $column_width = $column_width * $font_size / 10;
                // extrapolate from font size
                break;
            default:
                // just assume Calibri
                // value 8.26 was found via interpolation by inspecting real Excel files with Calibri 11 font.
                $column_width = (int) (8.26 * String_Helper::count_characters_dbcs($column_text));
                $column_width = $column_width * $font_size / 11;
                // extrapolate from font size
                break;
        }
        // Calculate approximate rotated column width
        if ($rotation !== 0) {
            if ($rotation == Alignment::TEXTROTATION_STACK_PHPSPREADSHEET) {
                // stacked text
                $column_width = 4;
                // approximation
            } else {
                // rotated text
                $column_width = $column_width * cos(deg2rad($rotation)) + $font_size * abs(sin(deg2rad($rotation))) / 5;
                // approximation
            }
        }
        // pixel width is an integer
        return (int) $column_width;
    }
    /**
     * Calculate an (approximate) pixel size, based on a font points size.
     *
     * @param float|int $fontSizeInPoints Font size (in points)
     *
     * @return int Font size (in pixels)
     */
    public static function font_size_to_pixels(float|int $font_size_in_points): int
    {
        return (int) (4 / 3 * $font_size_in_points);
    }
    /**
     * Calculate an (approximate) pixel size, based on inch size.
     *
     * @param float|int $sizeInInch Font size (in inch)
     *
     * @return float|int Size (in pixels)
     */
    public static function inch_size_to_pixels(int|float $size_in_inch): int|float
    {
        return $size_in_inch * 96;
    }
    /**
     * Calculate an (approximate) pixel size, based on centimeter size.
     *
     * @param float|int $sizeInCm Font size (in centimeters)
     *
     * @return float Size (in pixels)
     */
    public static function centimeter_size_to_pixels(int|float $size_in_cm): float
    {
        return $size_in_cm * 37.795275591;
    }
    /**
     * Returns the font path given the font.
     *
     * @return string Path to TrueType font file
     */
    public static function get_true_type_font_file_from_font(Font_Style $font, bool $check_path = true): string
    {
        if ($check_path && (!file_exists(self::$true_type_font_path) || !is_dir(self::$true_type_font_path))) {
            throw new Php_Spreadsheet_Exception('Valid directory to TrueType Font files not specified');
        }
        $name = $font->get_name();
        $font_array = array_merge(self::FONT_FILE_NAMES, self::$extra_font_array);
        if (!isset($font_array[$name])) {
            throw new Php_Spreadsheet_Exception('Unknown font name "' . $name . '". Cannot map to TrueType font file');
        }
        $bold = $font->get_bold();
        $italic = $font->get_italic();
        $index = 'x';
        if ($bold) {
            $index .= 'b';
        }
        if ($italic) {
            $index .= 'i';
        }
        $font_file = $font_array[$name][$index];
        $separator = '';
        if (mb_strlen(self::$true_type_font_path) > 1 && mb_substr(self::$true_type_font_path, -1) !== '/' && mb_substr(self::$true_type_font_path, -1) !== '\\') {
            $separator = DIRECTORY_SEPARATOR;
        }
        $font_file_absolute = preg_match('~^([A-Za-z]:)?[/\\\\]~', $font_file) === 1;
        if (!$font_file_absolute) {
            $font_file = self::find_font_file(self::$true_type_font_path, $font_file) ?? self::$true_type_font_path . $separator . $font_file;
        }
        // Check if file actually exists
        if ($check_path && !file_exists($font_file) && !$font_file_absolute) {
            $alternate_name = $name;
            if ($index !== 'x' && $font_array[$name][$index] !== $font_array[$name]['x']) {
                // Bold but no italic:
                //   Comic Sans
                //   Tahoma
                // Neither bold nor italic:
                //   Impact
                //   Lucida Console
                //   Lucida Sans Unicode
                //   Microsoft Sans Serif
                //   Symbol
                if ($index === 'xb') {
                    $alternate_name .= ' Bold';
                } elseif ($index === 'xi') {
                    $alternate_name .= ' Italic';
                } elseif ($font_array[$name]['xb'] === $font_array[$name]['xbi']) {
                    $alternate_name .= ' Bold';
                } else {
                    $alternate_name .= ' Bold Italic';
                }
            }
            $font_file = self::$true_type_font_path . $separator . $alternate_name . '.ttf';
            if (!file_exists($font_file)) {
                throw new Php_Spreadsheet_Exception('TrueType Font file not found');
            }
        }
        return $font_file;
    }
    public const CHARSET_FROM_FONT_NAME = ['EucrosiaUPC' => self::CHARSET_ANSI_THAI, 'Wingdings' => self::CHARSET_SYMBOL, 'Wingdings 2' => self::CHARSET_SYMBOL, 'Wingdings 3' => self::CHARSET_SYMBOL];
    /**
     * Returns the associated charset for the font name.
     *
     * @param string $fontName Font name
     *
     * @return int Character set code
     */
    public static function get_charset_from_font_name(string $font_name): int
    {
        return self::CHARSET_FROM_FONT_NAME[$font_name] ?? self::CHARSET_ANSI_LATIN;
    }
    /**
     * Get the effective column width for columns without a column dimension or column with width -1
     * For example, for Calibri 11 this is 9.140625 (64 px).
     *
     * @param FontStyle $font The workbooks default font
     * @param bool $returnAsPixels true = return column width in pixels, false = return in OOXML units
     *
     * @return ($returnAsPixels is true ? int : float) Column width
     */
    public static function get_default_column_width_by_font(Font_Style $font, bool $return_as_pixels = false): float|int
    {
        $size = $font->get_size();
        $sizex = $size !== null && $size == (int) $size ? (int) $size : "{$size}";
        if (isset(self::DEFAULT_COLUMN_WIDTHS[$font->get_name()][$sizex])) {
            // Exact width can be determined
            $column_width = $return_as_pixels ? self::DEFAULT_COLUMN_WIDTHS[$font->get_name()][$sizex]['px'] : self::DEFAULT_COLUMN_WIDTHS[$font->get_name()][$sizex]['width'];
        } else {
            // We don't have data for this particular font and size, use approximation by
            // extrapolating from Calibri 11
            $column_width = $return_as_pixels ? self::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['px'] : self::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['width'];
            $column_width = $column_width * $font->get_size() / 11;
            // Round pixels to closest integer
            if ($return_as_pixels) {
                $column_width = (int) round($column_width);
            }
        }
        return $column_width;
    }
    /**
     * Get the effective row height for rows without a row dimension or rows with height -1
     * For example, for Calibri 11 this is 15 points.
     *
     * @param FontStyle $font The workbooks default font
     *
     * @return float Row height in points
     */
    public static function get_default_row_height_by_font(Font_Style $font): float
    {
        $name = $font->get_name();
        $size = $font->get_size();
        $sizex = $size !== null && $size == (int) $size ? (int) $size : "{$size}";
        if (isset(self::DEFAULT_COLUMN_WIDTHS[$name][$sizex])) {
            $row_height = self::DEFAULT_COLUMN_WIDTHS[$name][$sizex]['height'];
        } elseif ($name === 'Arial' || $name === 'Verdana') {
            $row_height = self::DEFAULT_COLUMN_WIDTHS[$name][10]['height'] * $size / 10.0;
        } else {
            $row_height = self::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['height'] * $size / 11.0;
        }
        return $row_height;
    }
    private static function find_font_file(string $start_directory, string $desired_font): ?string
    {
        $font_path = null;
        if ($start_directory === '') {
            return null;
        }
        if (file_exists("{$start_directory}/{$desired_font}")) {
            $font_path = "{$start_directory}/{$desired_font}";
        } else {
            $iterations = 0;
            $it = new Recursive_Directory_Iterator($start_directory, Recursive_Directory_Iterator::SKIP_DOTS | Recursive_Directory_Iterator::FOLLOW_SYMLINKS);
            foreach (new Recursive_Iterator_Iterator($it, Recursive_Iterator_Iterator::LEAVES_ONLY, Recursive_Iterator_Iterator::CATCH_GET_CHILD) as $filex) {
                /** @var string */
                $file = $filex;
                if (basename($file) === $desired_font) {
                    $font_path = $file;
                    break;
                }
                ++$iterations;
                if ($iterations > 5000) {
                    // @codeCoverageIgnoreStart
                    break;
                    // @codeCoverageIgnoreEnd
                }
            }
        }
        return $font_path;
    }
}