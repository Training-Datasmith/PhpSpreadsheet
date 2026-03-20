<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Writer\Xls\Style\Cell_Alignment;
use Php_Office\Php_Spreadsheet\Writer\Xls\Style\Cell_Border;
use Php_Office\Php_Spreadsheet\Writer\Xls\Style\Cell_Fill;
// Original file header of PEAR::Spreadsheet_Excel_Writer_Format (used as the base for this class):
// -----------------------------------------------------------------------------------------
// /*
// *  Module written/ported by Xavier Noguer <xnoguer@rezebra.com>
// *
// *  The majority of this is _NOT_ my code.  I simply ported it from the
// *  PERL Spreadsheet::WriteExcel module.
// *
// *  The author of the Spreadsheet::WriteExcel module is John McNamara
// *  <jmcnamara@cpan.org>
// *
// *  I _DO_ maintain this code, and John McNamara has nothing to do with the
// *  porting of this code to PHP.  Any questions directly related to this
// *  class library should be directed to me.
// *
// *  License Information:
// *
// *    Spreadsheet_Excel_Writer:  A library for generating Excel Spreadsheets
// *    Copyright (c) 2002-2003 Xavier Noguer xnoguer@rezebra.com
// *
// *    This library is free software; you can redistribute it and/or
// *    modify it under the terms of the GNU Lesser General Public
// *    License as published by the Free Software Foundation; either
// *    version 2.1 of the License, or (at your option) any later version.
// *
// *    This library is distributed in the hope that it will be useful,
// *    but WITHOUT ANY WARRANTY; without even the implied warranty of
// *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// *    Lesser General Public License for more details.
// *
// *    You should have received a copy of the GNU Lesser General Public
// *    License along with this library; if not, write to the Free Software
// *    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
// */
class Xf
{
    /**
     * Style XF or a cell XF ?
     */
    private bool $is_style_xf;
    /**
     * Index to the FONT record. Index 4 does not exist.
     */
    private int $font_index;
    /**
     * An index (2 bytes) to a FORMAT record (number format).
     */
    private int $number_format_index;
    /**
     * 1 bit, apparently not used.
     */
    private readonly int $text_just_last;
    /**
     * The cell's foreground color.
     */
    private int $foreground_color;
    /**
     * The cell's background color.
     */
    private int $background_color;
    /**
     * Color of the bottom border of the cell.
     */
    private int $bottom_border_color;
    /**
     * Color of the top border of the cell.
     */
    private int $top_border_color;
    /**
     * Color of the left border of the cell.
     */
    private int $left_border_color;
    /**
     * Color of the right border of the cell.
     */
    private int $right_border_color;
    //private $diag; // theoretically int, not yet implemented
    private int $diag_color;
    /**
     * Constructor.
     *
     * @param Style $style The XF format
     */
    public function __construct(private readonly Style $style)
    {
        $this->is_style_xf = false;
        $this->font_index = 0;
        $this->number_format_index = 0;
        $this->text_just_last = 0;
        $this->foreground_color = 0x40;
        $this->background_color = 0x41;
        //$this->diag = 0;
        $this->bottom_border_color = 0x40;
        $this->top_border_color = 0x40;
        $this->left_border_color = 0x40;
        $this->right_border_color = 0x40;
        $this->diag_color = 0x40;
    }
    /**
     * Generate an Excel BIFF XF record (style or cell).
     *
     * @return string The XF record
     */
    public function write_xf(): string
    {
        // Set the type of the XF record and some of the attributes.
        if ($this->is_style_xf) {
            $style = 0xfff5;
        } else {
            $style = self::map_locked($this->style->get_protection()->get_locked());
            $style |= self::map_hidden($this->style->get_protection()->get_hidden()) << 1;
        }
        // Flags to indicate if attributes have been set.
        $atr_num = $this->number_format_index != 0 ? 1 : 0;
        $atr_fnt = $this->font_index != 0 ? 1 : 0;
        $atr_alc = (int) $this->style->get_alignment()->get_wrap_text() ? 1 : 0;
        $atr_bdr = Cell_Border::style($this->style->get_borders()->get_bottom()) || Cell_Border::style($this->style->get_borders()->get_top()) || Cell_Border::style($this->style->get_borders()->get_left()) || Cell_Border::style($this->style->get_borders()->get_right()) ? 1 : 0;
        $atr_pat = $this->foreground_color != 0x40 ? 1 : 0;
        $atr_pat = $this->background_color != 0x41 ? 1 : $atr_pat;
        $atr_pat = Cell_Fill::style($this->style->get_fill()) ? 1 : $atr_pat;
        $atr_prot = self::map_locked($this->style->get_protection()->get_locked()) | self::map_hidden($this->style->get_protection()->get_hidden());
        // Zero the default border colour if the border has not been set.
        if (Cell_Border::style($this->style->get_borders()->get_bottom()) == 0) {
            $this->bottom_border_color = 0;
        }
        if (Cell_Border::style($this->style->get_borders()->get_top()) == 0) {
            $this->top_border_color = 0;
        }
        if (Cell_Border::style($this->style->get_borders()->get_right()) == 0) {
            $this->right_border_color = 0;
        }
        if (Cell_Border::style($this->style->get_borders()->get_left()) == 0) {
            $this->left_border_color = 0;
        }
        if (Cell_Border::style($this->style->get_borders()->get_diagonal()) == 0) {
            $this->diag_color = 0;
        }
        $record = 0xe0;
        // Record identifier
        $length = 0x14;
        // Number of bytes to follow
        $ifnt = $this->font_index;
        // Index to FONT record
        $ifmt = $this->number_format_index;
        // Index to FORMAT record
        // Alignment
        $align = Cell_Alignment::horizontal($this->style->get_alignment());
        $align |= Cell_Alignment::wrap($this->style->get_alignment()) << 3;
        $align |= Cell_Alignment::vertical($this->style->get_alignment()) << 4;
        $align |= $this->text_just_last << 7;
        $used_attrib = $atr_num << 2;
        $used_attrib |= $atr_fnt << 3;
        $used_attrib |= $atr_alc << 4;
        $used_attrib |= $atr_bdr << 5;
        $used_attrib |= $atr_pat << 6;
        $used_attrib |= $atr_prot << 7;
        $icv = $this->foreground_color;
        // fg and bg pattern colors
        $icv |= $this->background_color << 7;
        $border1 = Cell_Border::style($this->style->get_borders()->get_left());
        // Border line style and color
        $border1 |= Cell_Border::style($this->style->get_borders()->get_right()) << 4;
        $border1 |= Cell_Border::style($this->style->get_borders()->get_top()) << 8;
        $border1 |= Cell_Border::style($this->style->get_borders()->get_bottom()) << 12;
        $border1 |= $this->left_border_color << 16;
        $border1 |= $this->right_border_color << 23;
        $diagonal_direction = $this->style->get_borders()->get_diagonal_direction();
        $diag_tl_to_rb = $diagonal_direction == Borders::DIAGONAL_BOTH || $diagonal_direction == Borders::DIAGONAL_DOWN;
        $diag_tr_to_lb = $diagonal_direction == Borders::DIAGONAL_BOTH || $diagonal_direction == Borders::DIAGONAL_UP;
        $border1 |= $diag_tl_to_rb << 30;
        $border1 |= $diag_tr_to_lb << 31;
        $border2 = $this->top_border_color;
        // Border color
        $border2 |= $this->bottom_border_color << 7;
        $border2 |= $this->diag_color << 14;
        $border2 |= Cell_Border::style($this->style->get_borders()->get_diagonal()) << 21;
        $border2 |= Cell_Fill::style($this->style->get_fill()) << 26;
        $header = pack('vv', $record, $length);
        //BIFF8 options: indentation, shrinkToFit and text direction
        $biff8_options = $this->style->get_alignment()->get_indent() & 15;
        $biff8_options |= (int) $this->style->get_alignment()->get_shrink_to_fit() << 4;
        $biff8_options |= $this->style->get_alignment()->get_read_order() << 6;
        $data = pack('vvvC', $ifnt, $ifmt, $style, $align);
        $data .= pack('CCC', self::map_text_rotation((int) $this->style->get_alignment()->get_text_rotation()), $biff8_options, $used_attrib);
        $data .= pack('VVv', $border1, $border2, $icv);
        return $header . $data;
    }
    /**
     * Is this a style XF ?
     */
    public function set_is_style_xf(bool $value): void
    {
        $this->is_style_xf = $value;
    }
    /**
     * Sets the cell's bottom border color.
     *
     * @param int $colorIndex Color index
     */
    public function set_bottom_color(int $color_index): void
    {
        $this->bottom_border_color = $color_index;
    }
    /**
     * Sets the cell's top border color.
     *
     * @param int $colorIndex Color index
     */
    public function set_top_color(int $color_index): void
    {
        $this->top_border_color = $color_index;
    }
    /**
     * Sets the cell's left border color.
     *
     * @param int $colorIndex Color index
     */
    public function set_left_color(int $color_index): void
    {
        $this->left_border_color = $color_index;
    }
    /**
     * Sets the cell's right border color.
     *
     * @param int $colorIndex Color index
     */
    public function set_right_color(int $color_index): void
    {
        $this->right_border_color = $color_index;
    }
    /**
     * Sets the cell's diagonal border color.
     *
     * @param int $colorIndex Color index
     */
    public function set_diag_color(int $color_index): void
    {
        $this->diag_color = $color_index;
    }
    /**
     * Sets the cell's foreground color.
     *
     * @param int $colorIndex Color index
     */
    public function set_fg_color(int $color_index): void
    {
        $this->foreground_color = $color_index;
    }
    /**
     * Sets the cell's background color.
     *
     * @param int $colorIndex Color index
     */
    public function set_bg_color(int $color_index): void
    {
        $this->background_color = $color_index;
    }
    /**
     * Sets the index to the number format record
     * It can be date, time, currency, etc...
     *
     * @param int $numberFormatIndex Index to format record
     */
    public function set_number_format_index(int $number_format_index): void
    {
        $this->number_format_index = $number_format_index;
    }
    /**
     * Set the font index.
     *
     * @param int $value Font index, note that value 4 does not exist
     */
    public function set_font_index(int $value): void
    {
        $this->font_index = $value;
    }
    /**
     * Map to BIFF8 codes for text rotation angle.
     */
    private static function map_text_rotation(int $text_rotation): int
    {
        if ($text_rotation >= 0) {
            return $text_rotation;
        }
        if ($text_rotation == Alignment::TEXTROTATION_STACK_PHPSPREADSHEET) {
            return Alignment::TEXTROTATION_STACK_EXCEL;
        }
        return 90 - $text_rotation;
    }
    private const LOCK_ARRAY = [Protection::PROTECTION_INHERIT => 1, Protection::PROTECTION_PROTECTED => 1, Protection::PROTECTION_UNPROTECTED => 0];
    /**
     * Map locked values.
     */
    private static function map_locked(?string $locked): int
    {
        return $locked !== null && array_key_exists($locked, self::LOCK_ARRAY) ? self::LOCK_ARRAY[$locked] : 1;
    }
    private const HIDDEN_ARRAY = [Protection::PROTECTION_INHERIT => 0, Protection::PROTECTION_PROTECTED => 1, Protection::PROTECTION_UNPROTECTED => 0];
    /**
     * Map hidden.
     */
    private static function map_hidden(?string $hidden): int
    {
        return $hidden !== null && array_key_exists($hidden, self::HIDDEN_ARRAY) ? self::HIDDEN_ARRAY[$hidden] : 0;
    }
}