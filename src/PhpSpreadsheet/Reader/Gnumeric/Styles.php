<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Gnumeric;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Simple_Xml_Element;
class Styles
{
    /** @var array<string, string[]> */
    public static array $mappings = ['borderStyle' => ['0' => Border::BORDER_NONE, '1' => Border::BORDER_THIN, '2' => Border::BORDER_MEDIUM, '3' => Border::BORDER_SLANTDASHDOT, '4' => Border::BORDER_DASHED, '5' => Border::BORDER_THICK, '6' => Border::BORDER_DOUBLE, '7' => Border::BORDER_DOTTED, '8' => Border::BORDER_MEDIUMDASHED, '9' => Border::BORDER_DASHDOT, '10' => Border::BORDER_MEDIUMDASHDOT, '11' => Border::BORDER_DASHDOTDOT, '12' => Border::BORDER_MEDIUMDASHDOTDOT, '13' => Border::BORDER_MEDIUMDASHDOTDOT], 'fillType' => [
        '1' => Fill::FILL_SOLID,
        '2' => Fill::FILL_PATTERN_DARKGRAY,
        '3' => Fill::FILL_PATTERN_MEDIUMGRAY,
        '4' => Fill::FILL_PATTERN_LIGHTGRAY,
        '5' => Fill::FILL_PATTERN_GRAY125,
        '6' => Fill::FILL_PATTERN_GRAY0625,
        '7' => Fill::FILL_PATTERN_DARKHORIZONTAL,
        // horizontal stripe
        '8' => Fill::FILL_PATTERN_DARKVERTICAL,
        // vertical stripe
        '9' => Fill::FILL_PATTERN_DARKDOWN,
        // diagonal stripe
        '10' => Fill::FILL_PATTERN_DARKUP,
        // reverse diagonal stripe
        '11' => Fill::FILL_PATTERN_DARKGRID,
        // diagonal crosshatch
        '12' => Fill::FILL_PATTERN_DARKTRELLIS,
        // thick diagonal crosshatch
        '13' => Fill::FILL_PATTERN_LIGHTHORIZONTAL,
        '14' => Fill::FILL_PATTERN_LIGHTVERTICAL,
        '15' => Fill::FILL_PATTERN_LIGHTUP,
        '16' => Fill::FILL_PATTERN_LIGHTDOWN,
        '17' => Fill::FILL_PATTERN_LIGHTGRID,
        // thin horizontal crosshatch
        '18' => Fill::FILL_PATTERN_LIGHTTRELLIS,
    ], 'horizontal' => ['1' => Alignment::HORIZONTAL_GENERAL, '2' => Alignment::HORIZONTAL_LEFT, '4' => Alignment::HORIZONTAL_RIGHT, '8' => Alignment::HORIZONTAL_CENTER, '16' => Alignment::HORIZONTAL_CENTER_CONTINUOUS, '32' => Alignment::HORIZONTAL_JUSTIFY, '64' => Alignment::HORIZONTAL_CENTER_CONTINUOUS], 'underline' => ['1' => Font::UNDERLINE_SINGLE, '2' => Font::UNDERLINE_DOUBLE, '3' => Font::UNDERLINE_SINGLEACCOUNTING, '4' => Font::UNDERLINE_DOUBLEACCOUNTING], 'vertical' => ['1' => Alignment::VERTICAL_TOP, '2' => Alignment::VERTICAL_BOTTOM, '4' => Alignment::VERTICAL_CENTER, '8' => Alignment::VERTICAL_JUSTIFY]];
    public function __construct(private readonly Spreadsheet $spreadsheet, protected bool $read_data_only)
    {
    }
    public function read(Simple_Xml_Element $sheet, int $max_row, int $max_col): void
    {
        if ($sheet->Styles->style_region !== null) {
            $this->read_styles($sheet->Styles->style_region, $max_row, $max_col);
        }
    }
    private function read_styles(Simple_Xml_Element $style_region, int $max_row, int $max_col): void
    {
        foreach ($style_region as $style) {
            $style_attributes = $style->attributes();
            if ($style_attributes !== null && $style_attributes['startRow'] <= $max_row && $style_attributes['startCol'] <= $max_col) {
                $cell_range = $this->read_style_range($style_attributes, $max_col, $max_row);
                $style_attributes = $style->Style->attributes();
                /** @var mixed[][] */
                $style_array = [];
                // We still set the number format mask for date/time values, even if readDataOnly is true
                //    so that we can identify whether a float is a float or a date value
                $format_code = $style_attributes ? (string) $style_attributes['Format'] : null;
                if ($format_code && Date::is_date_time_format_code($format_code)) {
                    $style_array['numberFormat']['formatCode'] = $format_code;
                }
                if ($this->read_data_only === false && $style_attributes !== null) {
                    //    If readDataOnly is false, we set all formatting information
                    $style_array['numberFormat']['formatCode'] = $format_code;
                    $style_array = $this->read_style($style_array, $style_attributes, $style);
                }
                /** @var mixed[][] $styleArray */
                $this->spreadsheet->get_active_sheet()->get_style($cell_range)->apply_from_array($style_array);
            }
        }
    }
    /** @param mixed[][] $styleArray */
    private function add_border_diagonal(Simple_Xml_Element $srssb, array &$style_array): void
    {
        if (isset($srssb->Diagonal, $srssb->{'Rev-Diagonal'})) {
            $style_array['borders']['diagonal'] = self::parse_border_attributes($srssb->Diagonal->attributes());
            $style_array['borders']['diagonalDirection'] = Borders::DIAGONAL_BOTH;
        } elseif (isset($srssb->Diagonal)) {
            $style_array['borders']['diagonal'] = self::parse_border_attributes($srssb->Diagonal->attributes());
            $style_array['borders']['diagonalDirection'] = Borders::DIAGONAL_UP;
        } elseif (isset($srssb->{'Rev-Diagonal'})) {
            $style_array['borders']['diagonal'] = self::parse_border_attributes($srssb->{'Rev-Diagonal'}->attributes());
            $style_array['borders']['diagonalDirection'] = Borders::DIAGONAL_DOWN;
        }
    }
    /** @param mixed[][] $styleArray */
    private function add_border_style(Simple_Xml_Element $srssb, array &$style_array, string $direction): void
    {
        $uc_direction = ucfirst($direction);
        if (isset($srssb->{$uc_direction})) {
            /** @var SimpleXMLElement */
            $temp = $srssb->{$uc_direction};
            $style_array['borders'][$direction] = self::parse_border_attributes($temp->attributes());
        }
    }
    private function calc_rotation(Simple_Xml_Element $style_attributes): int
    {
        $rotation = (int) $style_attributes->Rotation;
        if ($rotation >= 270 && $rotation <= 360) {
            $rotation -= 360;
        }
        return abs($rotation) > 90 ? 0 : $rotation;
    }
    /** @param mixed[][] $styleArray */
    private static function add_style(array &$style_array, string $key, string $value): void
    {
        if (array_key_exists($value, self::$mappings[$key])) {
            $style_array[$key] = self::$mappings[$key][$value];
            //* @phpstan-ignore-line
        }
    }
    /** @param mixed[][] $styleArray */
    private static function add_style2(array &$style_array, string $key1, string $key, string $value): void
    {
        if (array_key_exists($value, self::$mappings[$key])) {
            $style_array[$key1][$key] = self::$mappings[$key][$value];
        }
    }
    /** @return mixed[][] */
    private static function parse_border_attributes(?Simple_Xml_Element $border_attributes): array
    {
        /** @var mixed[][] */
        $style_array = [];
        if ($border_attributes !== null) {
            if (isset($border_attributes['Color'])) {
                $style_array['color']['rgb'] = self::parse_gnumeric_colour($border_attributes['Color']);
            }
            self::add_style($style_array, 'borderStyle', (string) $border_attributes['Style']);
        }
        /** @var mixed[][] $styleArray */
        return $style_array;
    }
    private static function parse_gnumeric_colour(string $gnm_colour): string
    {
        [$gnm_r, $gnm_g, $gnm_b] = explode(':', $gnm_colour);
        $gnm_r = substr(str_pad($gnm_r, 4, '0', STR_PAD_RIGHT), 0, 2);
        $gnm_g = substr(str_pad($gnm_g, 4, '0', STR_PAD_RIGHT), 0, 2);
        $gnm_b = substr(str_pad($gnm_b, 4, '0', STR_PAD_RIGHT), 0, 2);
        return $gnm_r . $gnm_g . $gnm_b;
    }
    /** @param mixed[][] $styleArray */
    private function add_colors(array &$style_array, Simple_Xml_Element $style_attributes): void
    {
        $RGB = self::parse_gnumeric_colour((string) $style_attributes['Fore']);
        /** @var mixed[][][] $styleArray */
        $style_array['font']['color']['rgb'] = $RGB;
        $RGB = self::parse_gnumeric_colour((string) $style_attributes['Back']);
        $shade = (string) $style_attributes['Shade'];
        if ($RGB !== '000000' || $shade !== '0') {
            $RGB2 = self::parse_gnumeric_colour((string) $style_attributes['PatternColor']);
            if ($shade === '1') {
                $style_array['fill']['startColor']['rgb'] = $RGB;
                $style_array['fill']['endColor']['rgb'] = $RGB2;
            } else {
                $style_array['fill']['endColor']['rgb'] = $RGB;
                $style_array['fill']['startColor']['rgb'] = $RGB2;
            }
            self::add_style2($style_array, 'fill', 'fillType', $shade);
        }
    }
    private function read_style_range(Simple_Xml_Element $style_attributes, int $max_col, int $max_row): string
    {
        $start_column = Coordinate::string_from_column_index((int) $style_attributes['startCol'] + 1);
        $start_row = $style_attributes['startRow'] + 1;
        $end_column = $style_attributes['endCol'] > $max_col ? $max_col : (int) $style_attributes['endCol'];
        $end_column = Coordinate::string_from_column_index($end_column + 1);
        $end_row = 1 + ($style_attributes['endRow'] > $max_row ? $max_row : (int) $style_attributes['endRow']);
        return $start_column . $start_row . ':' . $end_column . $end_row;
    }
    /**
     * @param mixed[][] $styleArray
     *
     * @return mixed[]
     */
    private function read_style(array $style_array, Simple_Xml_Element $style_attributes, Simple_Xml_Element $style): array
    {
        self::add_style2($style_array, 'alignment', 'horizontal', (string) $style_attributes['HAlign']);
        self::add_style2($style_array, 'alignment', 'vertical', (string) $style_attributes['VAlign']);
        $style_array['alignment']['wrapText'] = $style_attributes['WrapText'] == '1';
        $style_array['alignment']['textRotation'] = $this->calc_rotation($style_attributes);
        $style_array['alignment']['shrinkToFit'] = $style_attributes['ShrinkToFit'] == '1';
        $style_array['alignment']['indent'] = (int) $style_attributes['Indent'] > 0 ? $style_attributes['indent'] : 0;
        $this->add_colors($style_array, $style_attributes);
        $font_attributes = $style->Style->Font->attributes();
        if ($font_attributes !== null) {
            $style_array['font']['name'] = (string) $style->Style->Font;
            $style_array['font']['size'] = (int) $font_attributes['Unit'];
            $style_array['font']['bold'] = $font_attributes['Bold'] == '1';
            $style_array['font']['italic'] = $font_attributes['Italic'] == '1';
            $style_array['font']['strikethrough'] = $font_attributes['StrikeThrough'] == '1';
            self::add_style2($style_array, 'font', 'underline', (string) $font_attributes['Underline']);
            switch ($font_attributes['Script']) {
                case '1':
                    $style_array['font']['superscript'] = true;
                    break;
                case '-1':
                    $style_array['font']['subscript'] = true;
                    break;
            }
        }
        if (isset($style->Style->style_border)) {
            $srssb = $style->Style->style_border;
            $this->add_border_style($srssb, $style_array, 'top');
            $this->add_border_style($srssb, $style_array, 'bottom');
            $this->add_border_style($srssb, $style_array, 'left');
            $this->add_border_style($srssb, $style_array, 'right');
            $this->add_border_diagonal($srssb, $style_array);
        }
        //    TO DO
        /*
        if (isset($style->Style->HyperLink)) {
            $hyperlink = $style->Style->HyperLink->attributes();
        }
        */
        return $style_array;
    }
}