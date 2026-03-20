<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
class Style extends Writer_Part
{
    /**
     * Write styles to XML format.
     *
     * @return string XML Output
     */
    public function write_styles(Spreadsheet $spreadsheet): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // styleSheet
        $obj_writer->start_element('styleSheet');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        // numFmts
        $obj_writer->start_element('numFmts');
        $obj_writer->write_attribute('count', (string) $this->get_parent_writer()->get_num_fmt_hash_table()->count());
        // numFmt
        for ($i = 0; $i < $this->get_parent_writer()->get_num_fmt_hash_table()->count(); ++$i) {
            $this->write_num_fmt($obj_writer, $this->get_parent_writer()->get_num_fmt_hash_table()->get_by_index($i), $i);
        }
        $obj_writer->end_element();
        // fonts
        $obj_writer->start_element('fonts');
        $obj_writer->write_attribute('count', (string) $this->get_parent_writer()->get_font_hash_table()->count());
        // font
        for ($i = 0; $i < $this->get_parent_writer()->get_font_hash_table()->count(); ++$i) {
            $thisfont = $this->get_parent_writer()->get_font_hash_table()->get_by_index($i);
            if ($thisfont !== null) {
                $this->write_font($obj_writer, $thisfont, $spreadsheet);
            }
        }
        $obj_writer->end_element();
        // fills
        $obj_writer->start_element('fills');
        $obj_writer->write_attribute('count', (string) $this->get_parent_writer()->get_fill_hash_table()->count());
        // fill
        for ($i = 0; $i < $this->get_parent_writer()->get_fill_hash_table()->count(); ++$i) {
            $thisfill = $this->get_parent_writer()->get_fill_hash_table()->get_by_index($i);
            if ($thisfill !== null) {
                $this->write_fill($obj_writer, $thisfill);
            }
        }
        $obj_writer->end_element();
        // borders
        $obj_writer->start_element('borders');
        $obj_writer->write_attribute('count', (string) $this->get_parent_writer()->get_borders_hash_table()->count());
        // border
        for ($i = 0; $i < $this->get_parent_writer()->get_borders_hash_table()->count(); ++$i) {
            $thisborder = $this->get_parent_writer()->get_borders_hash_table()->get_by_index($i);
            if ($thisborder !== null) {
                $this->write_border($obj_writer, $thisborder);
            }
        }
        $obj_writer->end_element();
        // cellStyleXfs
        $obj_writer->start_element('cellStyleXfs');
        $obj_writer->write_attribute('count', '1');
        // xf
        $obj_writer->start_element('xf');
        $obj_writer->write_attribute('numFmtId', '0');
        $obj_writer->write_attribute('fontId', '0');
        $obj_writer->write_attribute('fillId', '0');
        $obj_writer->write_attribute('borderId', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // cellXfs
        $obj_writer->start_element('cellXfs');
        $obj_writer->write_attribute('count', (string) count($spreadsheet->get_cell_xf_collection()));
        // xf
        $alignment = new Alignment();
        $default_align_hash = $alignment->get_hash_code();
        if ($default_align_hash !== $spreadsheet->get_default_style()->get_alignment()->get_hash_code()) {
            $default_align_hash = '';
        }
        foreach ($spreadsheet->get_cell_xf_collection() as $cell_xf) {
            $this->write_cell_style_xf($obj_writer, $cell_xf, $spreadsheet, $default_align_hash);
        }
        $obj_writer->end_element();
        // cellStyles
        $obj_writer->start_element('cellStyles');
        $obj_writer->write_attribute('count', '1');
        // cellStyle
        $obj_writer->start_element('cellStyle');
        $obj_writer->write_attribute('name', 'Normal');
        $obj_writer->write_attribute('xfId', '0');
        $obj_writer->write_attribute('builtinId', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // dxfs
        $obj_writer->start_element('dxfs');
        $obj_writer->write_attribute('count', (string) $this->get_parent_writer()->get_styles_conditional_hash_table()->count());
        // dxf
        for ($i = 0; $i < $this->get_parent_writer()->get_styles_conditional_hash_table()->count(); ++$i) {
            /** @var ?Conditional */
            $thisstyle = $this->get_parent_writer()->get_styles_conditional_hash_table()->get_by_index($i);
            if ($thisstyle !== null) {
                $this->write_cell_style_dxf($obj_writer, $thisstyle->get_style(), $spreadsheet);
            }
        }
        $obj_writer->end_element();
        // tableStyles
        $obj_writer->start_element('tableStyles');
        $obj_writer->write_attribute('defaultTableStyle', 'TableStyleMedium9');
        $obj_writer->write_attribute('defaultPivotStyle', 'PivotTableStyle1');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write Fill.
     */
    private function write_fill(Xml_Writer $obj_writer, Fill $fill): void
    {
        // Check if this is a pattern type or gradient type
        if ($fill->get_fill_type() === Fill::FILL_GRADIENT_LINEAR || $fill->get_fill_type() === Fill::FILL_GRADIENT_PATH) {
            // Gradient fill
            $this->write_gradient_fill($obj_writer, $fill);
        } elseif ($fill->get_fill_type() !== null) {
            // Pattern fill
            $this->write_pattern_fill($obj_writer, $fill);
        }
    }
    /**
     * Write Gradient Fill.
     */
    private function write_gradient_fill(Xml_Writer $obj_writer, Fill $fill): void
    {
        // fill
        $obj_writer->start_element('fill');
        // gradientFill
        $obj_writer->start_element('gradientFill');
        $obj_writer->write_attribute('type', (string) $fill->get_fill_type());
        $obj_writer->write_attribute('degree', (string) $fill->get_rotation());
        // stop
        $obj_writer->start_element('stop');
        $obj_writer->write_attribute('position', '0');
        // color
        if (!empty($fill->get_start_color()->get_argb())) {
            $obj_writer->start_element('color');
            $obj_writer->write_attribute('rgb', $fill->get_start_color()->get_argb());
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        // stop
        $obj_writer->start_element('stop');
        $obj_writer->write_attribute('position', '1');
        // color
        if (!empty($fill->get_end_color()->get_argb())) {
            $obj_writer->start_element('color');
            $obj_writer->write_attribute('rgb', $fill->get_end_color()->get_argb());
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    private static function write_pattern_colors(Fill $fill): bool
    {
        if ($fill->get_fill_type() === Fill::FILL_NONE) {
            return false;
        }
        if ($fill->get_fill_type() === Fill::FILL_SOLID) {
            return true;
        }
        return $fill->get_colors_changed();
    }
    /**
     * Write Pattern Fill.
     */
    private function write_pattern_fill(Xml_Writer $obj_writer, Fill $fill): void
    {
        // fill
        $obj_writer->start_element('fill');
        // patternFill
        $obj_writer->start_element('patternFill');
        if ($fill->get_fill_type()) {
            $obj_writer->write_attribute('patternType', $fill->get_fill_type());
        }
        if (self::write_pattern_colors($fill)) {
            // fgColor
            if ($fill->get_start_color()->get_argb()) {
                if (!$fill->get_end_color()->get_argb() && $fill->get_fill_type() === Fill::FILL_SOLID) {
                    $obj_writer->start_element('bgColor');
                    $obj_writer->write_attribute('rgb', $fill->get_start_color()->get_argb());
                } else {
                    $obj_writer->start_element('fgColor');
                    $obj_writer->write_attribute('rgb', $fill->get_start_color()->get_argb());
                }
                $obj_writer->end_element();
            }
            // bgColor
            if ($fill->get_end_color()->get_argb()) {
                $obj_writer->start_element('bgColor');
                $obj_writer->write_attribute('rgb', $fill->get_end_color()->get_argb());
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * @param-out true $fontStarted
     */
    private function start_font(Xml_Writer $obj_writer, bool &$font_started): void
    {
        if (!$font_started) {
            $font_started = true;
            $obj_writer->start_element('font');
        }
    }
    /**
     * Write Font.
     */
    private function write_font(Xml_Writer $obj_writer, Font $font, Spreadsheet $spreadsheet): void
    {
        $font_started = false;
        // font
        //    Weird! The order of these elements actually makes a difference when opening Xlsx
        //        files in Excel2003 with the compatibility pack. It's not documented behaviour,
        //        and makes for a real WTF!
        // Bold. We explicitly write this element also when false (like MS Office Excel 2007 does
        // for conditional formatting). Otherwise it will apparently not be picked up in conditional
        // formatting style dialog
        if ($font->get_bold() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('b');
            $obj_writer->write_attribute('val', $font->get_bold() ? '1' : '0');
            $obj_writer->end_element();
        }
        // Italic
        if ($font->get_italic() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('i');
            $obj_writer->write_attribute('val', $font->get_italic() ? '1' : '0');
            $obj_writer->end_element();
        }
        // Strikethrough
        if ($font->get_strikethrough() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('strike');
            $obj_writer->write_attribute('val', $font->get_strikethrough() ? '1' : '0');
            $obj_writer->end_element();
        }
        // Underline
        if ($font->get_underline() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('u');
            $obj_writer->write_attribute('val', $font->get_underline());
            $obj_writer->end_element();
        }
        // Superscript / subscript
        if ($font->get_superscript() === true || $font->get_subscript() === true) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('vertAlign');
            if ($font->get_superscript() === true) {
                $obj_writer->write_attribute('val', 'superscript');
            } elseif ($font->get_subscript() === true) {
                $obj_writer->write_attribute('val', 'subscript');
            }
            $obj_writer->end_element();
        }
        // Size
        if ($font->get_size() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('sz');
            $obj_writer->write_attribute('val', String_Helper::format_number($font->get_size()));
            $obj_writer->end_element();
        }
        // Foreground color
        if ($font->get_auto_color()) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('auto');
            $obj_writer->write_attribute('val', '1');
            $obj_writer->end_element();
        } elseif ($font->get_color()->get_theme() >= 0) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('color');
            $obj_writer->write_attribute('theme', (string) $font->get_color()->get_theme());
            $obj_writer->end_element();
        } elseif ($font->get_color()->get_argb() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('color');
            $obj_writer->write_attribute('rgb', $font->get_color()->get_argb());
            $obj_writer->end_element();
        }
        // Name
        if ($font->get_name() !== null) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('name');
            $obj_writer->write_attribute('val', $font->get_name());
            $obj_writer->end_element();
            $charset = $spreadsheet->get_font_charset($font->get_name());
            if ($charset >= 0 && $charset <= 255) {
                $obj_writer->start_element('charset');
                $obj_writer->write_attribute('val', "{$charset}");
                $obj_writer->end_element();
            }
        }
        if (!empty($font->get_scheme())) {
            $this->start_font($obj_writer, $font_started);
            $obj_writer->start_element('scheme');
            $obj_writer->write_attribute('val', $font->get_scheme());
            $obj_writer->end_element();
        }
        if ($font_started) {
            $obj_writer->end_element();
        }
    }
    /**
     * Write Border.
     */
    private function write_border(Xml_Writer $obj_writer, Borders $borders): void
    {
        // Write border
        $obj_writer->start_element('border');
        // Diagonal?
        switch ($borders->get_diagonal_direction()) {
            case Borders::DIAGONAL_UP:
                $obj_writer->write_attribute('diagonalUp', 'true');
                $obj_writer->write_attribute('diagonalDown', 'false');
                break;
            case Borders::DIAGONAL_DOWN:
                $obj_writer->write_attribute('diagonalUp', 'false');
                $obj_writer->write_attribute('diagonalDown', 'true');
                break;
            case Borders::DIAGONAL_BOTH:
                $obj_writer->write_attribute('diagonalUp', 'true');
                $obj_writer->write_attribute('diagonalDown', 'true');
                break;
        }
        // BorderPr
        $this->write_border_pr($obj_writer, 'left', $borders->get_left());
        $this->write_border_pr($obj_writer, 'right', $borders->get_right());
        $this->write_border_pr($obj_writer, 'top', $borders->get_top());
        $this->write_border_pr($obj_writer, 'bottom', $borders->get_bottom());
        $this->write_border_pr($obj_writer, 'diagonal', $borders->get_diagonal());
        $obj_writer->end_element();
    }
    /**
     * Write Cell Style Xf.
     */
    private function write_cell_style_xf(Xml_Writer $obj_writer, \Php_Office\Php_Spreadsheet\Style\Style $style, Spreadsheet $spreadsheet, string $default_align_hash): void
    {
        // xf
        $obj_writer->start_element('xf');
        $obj_writer->write_attribute('xfId', '0');
        $obj_writer->write_attribute('fontId', (string) (int) $this->get_parent_writer()->get_font_hash_table()->get_index_for_hash_code($style->get_font()->get_hash_code()));
        if ($style->get_quote_prefix()) {
            $obj_writer->write_attribute('quotePrefix', '1');
        }
        if ($style->get_number_format()->get_built_in_format_code() === false) {
            $obj_writer->write_attribute('numFmtId', (string) $this->get_parent_writer()->get_num_fmt_hash_table()->get_index_for_hash_code($style->get_number_format()->get_hash_code()) + 164);
        } else {
            $obj_writer->write_attribute('numFmtId', (string) (int) $style->get_number_format()->get_built_in_format_code());
        }
        $obj_writer->write_attribute('fillId', (string) (int) $this->get_parent_writer()->get_fill_hash_table()->get_index_for_hash_code($style->get_fill()->get_hash_code()));
        $obj_writer->write_attribute('borderId', (string) (int) $this->get_parent_writer()->get_borders_hash_table()->get_index_for_hash_code($style->get_borders()->get_hash_code()));
        // Apply styles?
        $obj_writer->write_attribute('applyFont', $spreadsheet->get_default_style()->get_font()->get_hash_code() != $style->get_font()->get_hash_code() ? '1' : '0');
        $obj_writer->write_attribute('applyNumberFormat', $spreadsheet->get_default_style()->get_number_format()->get_hash_code() != $style->get_number_format()->get_hash_code() ? '1' : '0');
        $obj_writer->write_attribute('applyFill', $spreadsheet->get_default_style()->get_fill()->get_hash_code() != $style->get_fill()->get_hash_code() ? '1' : '0');
        $obj_writer->write_attribute('applyBorder', $spreadsheet->get_default_style()->get_borders()->get_hash_code() != $style->get_borders()->get_hash_code() ? '1' : '0');
        if ($default_align_hash !== '' && $default_align_hash === $style->get_alignment()->get_hash_code()) {
            $apply_alignment = '0';
        } else {
            $apply_alignment = '1';
        }
        $obj_writer->write_attribute('applyAlignment', $apply_alignment);
        if ($style->get_protection()->get_locked() != Protection::PROTECTION_INHERIT || $style->get_protection()->get_hidden() != Protection::PROTECTION_INHERIT) {
            $obj_writer->write_attribute('applyProtection', 'true');
        }
        // alignment
        if ($apply_alignment === '1') {
            $obj_writer->start_element('alignment');
            $vertical = Alignment::VERTICAL_ALIGNMENT_FOR_XLSX[$style->get_alignment()->get_vertical()] ?? '';
            $horizontal = Alignment::HORIZONTAL_ALIGNMENT_FOR_XLSX[$style->get_alignment()->get_horizontal()] ?? '';
            if ($horizontal !== '') {
                $obj_writer->write_attribute('horizontal', $horizontal);
            }
            if ($vertical !== '') {
                $obj_writer->write_attribute('vertical', $vertical);
            }
            $justify_last_line = $style->get_alignment()->get_justify_last_line();
            if (is_bool($justify_last_line)) {
                $obj_writer->write_attribute('justifyLastLine', (string) (int) $justify_last_line);
            }
            if ($style->get_alignment()->get_text_rotation() >= 0) {
                $text_rotation = $style->get_alignment()->get_text_rotation();
            } else {
                $text_rotation = 90 - $style->get_alignment()->get_text_rotation();
            }
            $obj_writer->write_attribute('textRotation', (string) $text_rotation);
            $obj_writer->write_attribute('wrapText', $style->get_alignment()->get_wrap_text() ? 'true' : 'false');
            $obj_writer->write_attribute('shrinkToFit', $style->get_alignment()->get_shrink_to_fit() ? 'true' : 'false');
            if ($style->get_alignment()->get_indent() > 0) {
                $obj_writer->write_attribute('indent', (string) $style->get_alignment()->get_indent());
            }
            if ($style->get_alignment()->get_read_order() > 0) {
                $obj_writer->write_attribute('readingOrder', (string) $style->get_alignment()->get_read_order());
            }
            $obj_writer->end_element();
        }
        // protection
        if ($style->get_protection()->get_locked() != Protection::PROTECTION_INHERIT || $style->get_protection()->get_hidden() != Protection::PROTECTION_INHERIT) {
            $obj_writer->start_element('protection');
            if ($style->get_protection()->get_locked() != Protection::PROTECTION_INHERIT) {
                $obj_writer->write_attribute('locked', $style->get_protection()->get_locked() == Protection::PROTECTION_PROTECTED ? 'true' : 'false');
            }
            if ($style->get_protection()->get_hidden() != Protection::PROTECTION_INHERIT) {
                $obj_writer->write_attribute('hidden', $style->get_protection()->get_hidden() == Protection::PROTECTION_PROTECTED ? 'true' : 'false');
            }
            $obj_writer->end_element();
        }
        if ($style->get_check_box()) {
            $obj_writer->start_element('extLst');
            $obj_writer->start_element('ext');
            $obj_writer->write_attribute('uri', Namespaces::STYLE_CHECKBOX_URI);
            $obj_writer->write_attribute('xmlns:xfpb', Namespaces::FEATURE_PROPERTY_BAG);
            $obj_writer->start_element('xfpb:xfComplement');
            $obj_writer->write_attribute('i', '0');
            $obj_writer->end_element();
            //xfpb:xfComplement
            $obj_writer->end_element();
            //ext
            $obj_writer->end_element();
            //extLst
        }
        $obj_writer->end_element();
    }
    /**
     * Write Cell Style Dxf.
     */
    private function write_cell_style_dxf(Xml_Writer $obj_writer, \Php_Office\Php_Spreadsheet\Style\Style $style, Spreadsheet $spreadsheet): void
    {
        // dxf
        $obj_writer->start_element('dxf');
        // font
        $this->write_font($obj_writer, $style->get_font(), $spreadsheet);
        // numFmt
        $this->write_num_fmt($obj_writer, $style->get_number_format());
        // fill
        $this->write_fill($obj_writer, $style->get_fill());
        // border
        $this->write_border($obj_writer, $style->get_borders());
        $obj_writer->end_element();
    }
    /**
     * Write BorderPr.
     *
     * @param string $name Element name
     */
    private function write_border_pr(Xml_Writer $obj_writer, string $name, Border $border): void
    {
        // Write BorderPr
        if ($border->get_border_style() === Border::BORDER_OMIT) {
            return;
        }
        $obj_writer->start_element($name);
        if ($border->get_border_style() !== Border::BORDER_NONE) {
            $obj_writer->write_attribute('style', $border->get_border_style());
            // color
            if ($border->get_color()->get_argb() !== null) {
                $obj_writer->start_element('color');
                $obj_writer->write_attribute('rgb', $border->get_color()->get_argb());
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
    }
    /**
     * Write NumberFormat.
     *
     * @param int $id Number Format identifier
     */
    private function write_num_fmt(Xml_Writer $obj_writer, ?Number_Format $number_format, int $id = 0): void
    {
        // Translate formatcode
        $format_code = $number_format === null ? null : $number_format->get_format_code();
        // numFmt
        if ($format_code !== null) {
            $obj_writer->start_element('numFmt');
            $obj_writer->write_attribute('numFmtId', (string) ($id + 164));
            $obj_writer->write_attribute('formatCode', $format_code);
            $obj_writer->end_element();
        }
    }
    /**
     * Get an array of all styles.
     *
     * @return \PhpOffice\PhpSpreadsheet\Style\Style[] All styles in PhpSpreadsheet
     */
    public function all_styles(Spreadsheet $spreadsheet): array
    {
        return $spreadsheet->get_cell_xf_collection();
    }
    /**
     * Get an array of all conditional styles.
     *
     * @return Conditional[] All conditional styles in PhpSpreadsheet
     */
    public function all_conditional_styles(Spreadsheet $spreadsheet): array
    {
        // Get an array of all styles
        $a_styles = [];
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            foreach ($spreadsheet->get_sheet($i)->get_conditional_styles_collection() as $conditional_styles) {
                foreach ($conditional_styles as $conditional_style) {
                    $a_styles[] = $conditional_style;
                }
            }
        }
        return $a_styles;
    }
    /**
     * Get an array of all fills.
     *
     * @return Fill[] All fills in PhpSpreadsheet
     */
    public function all_fills(Spreadsheet $spreadsheet): array
    {
        // Get an array of unique fills
        $a_fills = [];
        // Two first fills are predefined
        $fill0 = new Fill();
        $fill0->set_fill_type(Fill::FILL_NONE);
        $a_fills[] = $fill0;
        $fill1 = new Fill();
        $fill1->set_fill_type(Fill::FILL_PATTERN_GRAY125);
        $a_fills[] = $fill1;
        // The remaining fills
        $a_styles = $this->all_styles($spreadsheet);
        foreach ($a_styles as $style) {
            if (!isset($a_fills[$style->get_fill()->get_hash_code()])) {
                $a_fills[$style->get_fill()->get_hash_code()] = $style->get_fill();
            }
        }
        return $a_fills;
    }
    /**
     * Get an array of all fonts.
     *
     * @return Font[] All fonts in PhpSpreadsheet
     */
    public function all_fonts(Spreadsheet $spreadsheet): array
    {
        // Get an array of unique fonts
        $a_fonts = [];
        $a_styles = $this->all_styles($spreadsheet);
        foreach ($a_styles as $style) {
            if (!isset($a_fonts[$style->get_font()->get_hash_code()])) {
                $a_fonts[$style->get_font()->get_hash_code()] = $style->get_font();
            }
        }
        return $a_fonts;
    }
    /**
     * Get an array of all borders.
     *
     * @return Borders[] All borders in PhpSpreadsheet
     */
    public function all_borders(Spreadsheet $spreadsheet): array
    {
        // Get an array of unique borders
        $a_borders = [];
        $a_styles = $this->all_styles($spreadsheet);
        foreach ($a_styles as $style) {
            if (!isset($a_borders[$style->get_borders()->get_hash_code()])) {
                $a_borders[$style->get_borders()->get_hash_code()] = $style->get_borders();
            }
        }
        return $a_borders;
    }
    /**
     * Get an array of all number formats.
     *
     * @return NumberFormat[] All number formats in PhpSpreadsheet
     */
    public function all_number_formats(Spreadsheet $spreadsheet): array
    {
        // Get an array of unique number formats
        $a_num_fmts = [];
        $a_styles = $this->all_styles($spreadsheet);
        foreach ($a_styles as $style) {
            if ($style->get_number_format()->get_built_in_format_code() === false && !isset($a_num_fmts[$style->get_number_format()->get_hash_code()])) {
                $a_num_fmts[$style->get_number_format()->get_hash_code()] = $style->get_number_format();
            }
        }
        return $a_num_fmts;
    }
}