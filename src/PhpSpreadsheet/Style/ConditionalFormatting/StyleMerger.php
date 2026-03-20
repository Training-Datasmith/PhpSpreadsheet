<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Style;
class Style_Merger
{
    protected Style $base_style;
    public function __construct(Style $base_style)
    {
        // Setting to $baseStyle sometimes causes problems later on.
        $array = $base_style->export_array();
        $this->base_style = new Style();
        $this->base_style->apply_from_array($array);
    }
    public function get_style(): Style
    {
        return $this->base_style;
    }
    public function merge_style(Style $style): void
    {
        if ($style->get_number_format()->get_format_code() !== null) {
            $this->base_style->get_number_format()->set_format_code($style->get_number_format()->get_format_code());
        }
        $this->merge_font_style($this->base_style->get_font(), $style->get_font());
        $this->merge_fill_style($this->base_style->get_fill(), $style->get_fill());
        $this->merge_borders_style($this->base_style->get_borders(), $style->get_borders());
    }
    protected function merge_font_style(Font $base_font_style, Font $font_style): void
    {
        if ($font_style->get_bold() !== null) {
            $base_font_style->set_bold($font_style->get_bold());
        }
        if ($font_style->get_italic() !== null) {
            $base_font_style->set_italic($font_style->get_italic());
        }
        if ($font_style->get_strikethrough() !== null) {
            $base_font_style->set_strikethrough($font_style->get_strikethrough());
        }
        if ($font_style->get_underline() !== null) {
            $base_font_style->set_underline($font_style->get_underline());
        }
        if ($font_style->get_color()->get_argb() !== null) {
            $base_font_style->set_color($font_style->get_color());
        }
    }
    protected function merge_fill_style(Fill $base_fill_style, Fill $fill_style): void
    {
        if ($fill_style->get_fill_type() !== null) {
            $base_fill_style->set_fill_type($fill_style->get_fill_type());
        }
        $base_fill_style->set_rotation($fill_style->get_rotation());
        if ($fill_style->get_start_color()->get_argb() !== null) {
            $base_fill_style->set_start_color($fill_style->get_start_color());
        }
        if ($fill_style->get_end_color()->get_argb() !== null) {
            $base_fill_style->set_end_color($fill_style->get_end_color());
        }
    }
    protected function merge_borders_style(Borders $base_borders_style, Borders $borders_style): void
    {
        $this->merge_border_style($base_borders_style->get_top(), $borders_style->get_top());
        $this->merge_border_style($base_borders_style->get_bottom(), $borders_style->get_bottom());
        $this->merge_border_style($base_borders_style->get_left(), $borders_style->get_left());
        $this->merge_border_style($base_borders_style->get_right(), $borders_style->get_right());
    }
    protected function merge_border_style(Border $base_border_style, Border $border_style): void
    {
        if ($border_style->get_border_style() !== Border::BORDER_OMIT) {
            $base_border_style->set_border_style($border_style->get_border_style());
        }
        if ($border_style->get_color()->get_argb() !== null) {
            $base_border_style->set_color($border_style->get_color());
        }
    }
}