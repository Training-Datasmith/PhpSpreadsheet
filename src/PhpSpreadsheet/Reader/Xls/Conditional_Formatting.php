<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Reader\Xls;
use Php_Office\Php_Spreadsheet\Reader\Xls\Style\Fill_Pattern;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Style;
class Conditional_Formatting extends Xls
{
    /**
     * @var array<int, string>
     */
    private static array $types = [0x1 => Conditional::CONDITION_CELLIS, 0x2 => Conditional::CONDITION_EXPRESSION];
    /**
     * @var array<int, string>
     */
    private static array $operators = [0x0 => Conditional::OPERATOR_NONE, 0x1 => Conditional::OPERATOR_BETWEEN, 0x2 => Conditional::OPERATOR_NOTBETWEEN, 0x3 => Conditional::OPERATOR_EQUAL, 0x4 => Conditional::OPERATOR_NOTEQUAL, 0x5 => Conditional::OPERATOR_GREATERTHAN, 0x6 => Conditional::OPERATOR_LESSTHAN, 0x7 => Conditional::OPERATOR_GREATERTHANOREQUAL, 0x8 => Conditional::OPERATOR_LESSTHANOREQUAL];
    public static function type(int $type): ?string
    {
        return self::$types[$type] ?? null;
    }
    public static function operator(int $operator): ?string
    {
        return self::$operators[$operator] ?? null;
    }
    /**
     * Parse conditional formatting blocks.
     *
     * @see https://www.openoffice.org/sc/excelfileformat.pdf Search for CFHEADER followed by CFRULE
     *
     * @return mixed[]
     */
    protected function read_cf_header2(Xls $xls): array
    {
        $length = self::get_u_int2d($xls->data, $xls->pos + 2);
        $record_data = $xls->read_record_data($xls->data, $xls->pos + 4, $length);
        // move stream pointer forward to next record
        $xls->pos += 4 + $length;
        if ($xls->read_data_only) {
            return [];
        }
        // offset: 0; size: 2; Rule Count
        //        $ruleCount = self::getUInt2d($recordData, 0);
        // offset: var; size: var; cell range address list with
        $cell_range_address_list = $xls->version == self::XLS_BIFF8 ? Biff8::read_biff8cell_range_address_list(substr($record_data, 12)) : Biff5::read_biff5cell_range_address_list(substr($record_data, 12));
        return $cell_range_address_list['cellRangeAddresses'];
    }
    /** @param string[] $cellRangeAddresses */
    protected function read_cf_rule2(array $cell_range_addresses, Xls $xls): void
    {
        $length = self::get_u_int2d($xls->data, $xls->pos + 2);
        $record_data = $xls->read_record_data($xls->data, $xls->pos + 4, $length);
        // move stream pointer forward to next record
        $xls->pos += 4 + $length;
        if ($xls->read_data_only) {
            return;
        }
        // offset: 0; size: 2; Options
        $cf_rule = self::get_u_int2d($record_data, 0);
        // bit: 8-15; mask: 0x00FF; type
        $type = (0xff & $cf_rule) >> 0;
        $type = self::type($type);
        // bit: 0-7; mask: 0xFF00; type
        $operator = (0xff00 & $cf_rule) >> 8;
        $operator = self::operator($operator);
        if ($type === null || $operator === null) {
            return;
        }
        // offset: 2; size: 2; Size1
        $size1 = self::get_u_int2d($record_data, 2);
        // offset: 4; size: 2; Size2
        $size2 = self::get_u_int2d($record_data, 4);
        // offset: 6; size: 4; Options
        $options = self::get_int4d($record_data, 6);
        $style = new Style(false, true);
        // non-supervisor, conditional
        $no_format_set = true;
        //$xls->getCFStyleOptions($options, $style);
        $has_font_record = (bool) ((0x4000000 & $options) >> 26);
        $has_alignment_record = (bool) ((0x8000000 & $options) >> 27);
        $has_border_record = (bool) ((0x10000000 & $options) >> 28);
        $has_fill_record = (bool) ((0x20000000 & $options) >> 29);
        $has_protection_record = (bool) ((0x40000000 & $options) >> 30);
        // note unexpected values for following 4
        $has_border_left = !(bool) (0x400 & $options);
        $has_border_right = !(bool) (0x800 & $options);
        $has_border_top = !(bool) (0x1000 & $options);
        $has_border_bottom = !(bool) (0x2000 & $options);
        $offset = 12;
        if ($has_font_record === true) {
            $font_style = substr($record_data, $offset, 118);
            $this->get_cf_font_style($font_style, $style, $xls);
            $offset += 118;
            $no_format_set = false;
        }
        if ($has_alignment_record === true) {
            //$alignmentStyle = substr($recordData, $offset, 8);
            //$this->getCFAlignmentStyle($alignmentStyle, $style, $xls);
            $offset += 8;
        }
        if ($has_border_record === true) {
            $border_style = substr($record_data, $offset, 8);
            $this->get_cf_border_style($border_style, $style, $has_border_left, $has_border_right, $has_border_top, $has_border_bottom, $xls);
            $offset += 8;
            $no_format_set = false;
        }
        if ($has_fill_record === true) {
            $fill_style = substr($record_data, $offset, 4);
            $this->get_cf_fill_style($fill_style, $style, $xls);
            $offset += 4;
            $no_format_set = false;
        }
        if ($has_protection_record === true) {
            //$protectionStyle = substr($recordData, $offset, 4);
            //$this->getCFProtectionStyle($protectionStyle, $style, $xls);
            $offset += 2;
        }
        $formula1 = $formula2 = null;
        if ($size1 > 0) {
            $formula1 = $this->read_cf_formula($record_data, $offset, $size1, $xls);
            if ($formula1 === null) {
                return;
            }
            $offset += $size1;
        }
        if ($size2 > 0) {
            $formula2 = $this->read_cf_formula($record_data, $offset, $size2, $xls);
            if ($formula2 === null) {
                return;
            }
            $offset += $size2;
        }
        $this->set_cf_rules($cell_range_addresses, $type, $operator, $formula1, $formula2, $style, $no_format_set, $xls);
    }
    /*private function getCFStyleOptions(int $options, Style $style, Xls $xls): void
      {
      }*/
    private function get_cf_font_style(string $options, Style $style, Xls $xls): void
    {
        $font_size = self::get_int4d($options, 64);
        if ($font_size !== -1) {
            $style->get_font()->set_size($font_size / 20);
            // Convert twips to points
        }
        $options68 = self::get_int4d($options, 68);
        $options88 = self::get_int4d($options, 88);
        if (($options88 & 2) === 0) {
            $bold = self::get_u_int2d($options, 72);
            // 400 = normal, 700 = bold
            if ($bold !== 0) {
                $style->get_font()->set_bold($bold >= 550);
            }
            if (($options68 & 2) !== 0) {
                $style->get_font()->set_italic(true);
            }
        }
        if (($options88 & 0x80) === 0) {
            if (($options68 & 0x80) !== 0) {
                $style->get_font()->set_strikethrough(true);
            }
        }
        $color = self::get_int4d($options, 80);
        if ($color !== -1) {
            $style->get_font()->get_color()->set_rgb(Color::map($color, $xls->palette, $xls->version)['rgb']);
        }
    }
    /*private function getCFAlignmentStyle(string $options, Style $style, Xls $xls): void
      {
      }*/
    private function get_cf_border_style(string $options, Style $style, bool $has_border_left, bool $has_border_right, bool $has_border_top, bool $has_border_bottom, Xls $xls): void
    {
        /** @var false|int[] */
        $value_array = unpack('V', $options);
        $value = is_array($value_array) ? $value_array[1] : 0;
        $left = $value & 15;
        $right = $value >> 4 & 15;
        $top = $value >> 8 & 15;
        $bottom = $value >> 12 & 15;
        $leftc = $value >> 16 & 0x7f;
        $rightc = $value >> 23 & 0x7f;
        /** @var false|int[] */
        $value_array = unpack('V', substr($options, 4));
        $value = is_array($value_array) ? $value_array[1] : 0;
        $topc = $value & 0x7f;
        $bottomc = ($value & 0x3f80) >> 7;
        if ($has_border_left) {
            $style->get_borders()->get_left()->set_border_style(self::BORDER_STYLE_MAP[$left]);
            $style->get_borders()->get_left()->get_color()->set_rgb(Color::map($leftc, $xls->palette, $xls->version)['rgb']);
        }
        if ($has_border_right) {
            $style->get_borders()->get_right()->set_border_style(self::BORDER_STYLE_MAP[$right]);
            $style->get_borders()->get_right()->get_color()->set_rgb(Color::map($rightc, $xls->palette, $xls->version)['rgb']);
        }
        if ($has_border_top) {
            $style->get_borders()->get_top()->set_border_style(self::BORDER_STYLE_MAP[$top]);
            $style->get_borders()->get_top()->get_color()->set_rgb(Color::map($topc, $xls->palette, $xls->version)['rgb']);
        }
        if ($has_border_bottom) {
            $style->get_borders()->get_bottom()->set_border_style(self::BORDER_STYLE_MAP[$bottom]);
            $style->get_borders()->get_bottom()->get_color()->set_rgb(Color::map($bottomc, $xls->palette, $xls->version)['rgb']);
        }
    }
    private function get_cf_fill_style(string $options, Style $style, Xls $xls): void
    {
        $fill_pattern = self::get_u_int2d($options, 0);
        // bit: 10-15; mask: 0xFC00; type
        $fill_pattern = (0xfc00 & $fill_pattern) >> 10;
        $fill_pattern = Fill_Pattern::lookup($fill_pattern);
        $fill_pattern = $fill_pattern === Fill::FILL_NONE ? Fill::FILL_SOLID : $fill_pattern;
        if ($fill_pattern !== Fill::FILL_NONE) {
            $style->get_fill()->set_fill_type($fill_pattern);
            $fill_colors = self::get_u_int2d($options, 2);
            // bit: 0-6; mask: 0x007F; type
            $color1 = (0x7f & $fill_colors) >> 0;
            // bit: 7-13; mask: 0x3F80; type
            $color2 = (0x3f80 & $fill_colors) >> 7;
            if ($fill_pattern === Fill::FILL_SOLID) {
                $style->get_fill()->get_start_color()->set_rgb(Color::map($color2, $xls->palette, $xls->version)['rgb']);
            } else {
                $style->get_fill()->get_start_color()->set_rgb(Color::map($color1, $xls->palette, $xls->version)['rgb']);
                $style->get_fill()->get_end_color()->set_rgb(Color::map($color2, $xls->palette, $xls->version)['rgb']);
            }
        }
    }
    /*private function getCFProtectionStyle(string $options, Style $style, Xls $xls): void
      {
      }*/
    private function read_cf_formula(string $record_data, int $offset, int $size, Xls $xls): float|int|string|null
    {
        try {
            $formula = substr($record_data, $offset, $size);
            $formula = pack('v', $size) . $formula;
            // prepend the length
            $formula = $xls->get_formula_from_structure($formula);
            if (is_numeric($formula)) {
                return str_contains($formula, '.') ? (float) $formula : (int) $formula;
            }
            return $formula;
        } catch (Php_Spreadsheet_Exception) {
            return null;
        }
    }
    /** @param string[] $cellRanges */
    private function set_cf_rules(array $cell_ranges, string $type, string $operator, null|float|int|string $formula1, null|float|int|string $formula2, Style $style, bool $no_format_set, Xls $xls): void
    {
        foreach ($cell_ranges as $cell_range) {
            $conditional = new Conditional();
            $conditional->set_no_format_set($no_format_set);
            $conditional->set_condition_type($type);
            $conditional->set_operator_type($operator);
            $conditional->set_stop_if_true(true);
            if ($formula1 !== null) {
                $conditional->add_condition($formula1);
            }
            if ($formula2 !== null) {
                $conditional->add_condition($formula2);
            }
            $conditional->set_style($style);
            $conditional_styles = $xls->php_sheet->get_style($cell_range)->get_conditional_styles();
            $conditional_styles[] = $conditional;
            $xls->php_sheet->get_style($cell_range)->set_conditional_styles($conditional_styles);
        }
    }
}