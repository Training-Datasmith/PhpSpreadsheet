<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods\Cell;

use Php_Office\Php_Spreadsheet\Helper\Dimension;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Style\Style as CellStyle;
use Php_Office\Php_Spreadsheet\Worksheet\Column_Dimension;
use Php_Office\Php_Spreadsheet\Worksheet\Row_Dimension;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Style
{
    public const CELL_STYLE_PREFIX = 'ce';
    public const COLUMN_STYLE_PREFIX = 'co';
    public const ROW_STYLE_PREFIX = 'ro';
    public const TABLE_STYLE_PREFIX = 'ta';
    public const INDENT_TO_INCHES = 0.1043;
    /** @param array<string, callable> $additionalNumberFormats */
    public function __construct(private readonly Xml_Writer $writer, private array $additional_number_formats = [])
    {
    }
    public function get_writer(): Xml_Writer
    {
        return $this->writer;
    }
    private function map_horizontal_alignment(?string $horizontal_alignment): string
    {
        return match ($horizontal_alignment) {
            Alignment::HORIZONTAL_CENTER, Alignment::HORIZONTAL_CENTER_CONTINUOUS, Alignment::HORIZONTAL_DISTRIBUTED => 'center',
            Alignment::HORIZONTAL_RIGHT => 'end',
            Alignment::HORIZONTAL_FILL, Alignment::HORIZONTAL_JUSTIFY => 'justify',
            Alignment::HORIZONTAL_GENERAL, '', null => '',
            default => 'start',
        };
    }
    private function map_vertical_alignment(string $vertical_alignment): string
    {
        return match ($vertical_alignment) {
            Alignment::VERTICAL_TOP => 'top',
            Alignment::VERTICAL_CENTER => 'middle',
            Alignment::VERTICAL_DISTRIBUTED, Alignment::VERTICAL_JUSTIFY => 'automatic',
            default => 'bottom',
        };
    }
    private function write_fill_style(Fill $fill): void
    {
        switch ($fill->get_fill_type()) {
            case Fill::FILL_SOLID:
                $this->writer->write_attribute('fo:background-color', sprintf(
                    '#%s',
                    // no idea why strtolower, but it doesn't hurt
                    strtolower($fill->get_start_color()->get_rgb())
                ));
                break;
            case Fill::FILL_NONE:
                $this->writer->write_attribute('fo:background-color', 'transparent');
                break;
        }
    }
    private function write_borders_style(Borders $borders): void
    {
        $this->write_border_style('bottom', $borders->get_bottom());
        $this->write_border_style('left', $borders->get_left());
        $this->write_border_style('right', $borders->get_right());
        $this->write_border_style('top', $borders->get_top());
        $diagonal = $borders->get_diagonal_direction();
        if ($diagonal === Borders::DIAGONAL_DOWN || $diagonal === Borders::DIAGONAL_BOTH) {
            $this->write_border_style('style:diagonal-tl-br', $borders->get_diagonal());
        }
        if ($diagonal === Borders::DIAGONAL_UP || $diagonal === Borders::DIAGONAL_BOTH) {
            $this->write_border_style('style:diagonal-bl-tr', $borders->get_diagonal());
        }
    }
    private function write_border_style(string $direction, Border $border): void
    {
        if ($border->get_border_style() === Border::BORDER_NONE) {
            return;
        }
        $attr_name = str_starts_with($direction, 'style:') ? $direction : 'fo:border-' . $direction;
        $this->writer->write_attribute($attr_name, sprintf('%s %s #%s', $this->map_border_width($border), $this->map_border_style($border), $border->get_color()->get_rgb()));
    }
    private const MAP_BORDER_WIDTH = [
        Border::BORDER_THIN => '0.75pt',
        Border::BORDER_DASHED => '0.75pt',
        Border::BORDER_DASHDOT => '0.75pt',
        Border::BORDER_DASHDOTDOT => '0.75pt',
        Border::BORDER_DOTTED => '0.75pt',
        Border::BORDER_HAIR => '0.75pt',
        // end of thin styles
        Border::BORDER_MEDIUM => '1.75pt',
        Border::BORDER_MEDIUMDASHED => '1.75pt',
        Border::BORDER_MEDIUMDASHDOT => '1.75pt',
        Border::BORDER_MEDIUMDASHDOTDOT => '1.75pt',
        Border::BORDER_SLANTDASHDOT => '1.75pt',
        // end of medium styles
        Border::BORDER_DOUBLE => '2.5pt',
        Border::BORDER_THICK => '2.5pt',
    ];
    private function map_border_width(Border $border): string
    {
        return self::MAP_BORDER_WIDTH[$border->get_border_style()] ?? '1pt';
    }
    private const MAP_BORDER_STYLE = [Border::BORDER_DOTTED => 'dotted', Border::BORDER_DASHED => 'dashed', Border::BORDER_MEDIUMDASHED => 'dashed', Border::BORDER_DASHDOT => 'dash-dot', Border::BORDER_MEDIUMDASHDOT => 'dash-dot', Border::BORDER_DASHDOTDOT => 'dash-dot-dot', Border::BORDER_MEDIUMDASHDOTDOT => 'dash-dot-dot', Border::BORDER_SLANTDASHDOT => 'dashed', Border::BORDER_DOUBLE => 'double', Border::BORDER_NONE => 'none'];
    private function map_border_style(Border $border): string
    {
        return self::MAP_BORDER_STYLE[$border->get_border_style()] ?? 'solid';
    }
    // 2d array, 1st index is locked, 2nd is hidden
    private const PROTECTION_MAP = [Protection::PROTECTION_PROTECTED => [Protection::PROTECTION_PROTECTED => 'protected formula-hidden', Protection::PROTECTION_UNPROTECTED => 'protected'], Protection::PROTECTION_UNPROTECTED => [Protection::PROTECTION_PROTECTED => 'formula-hidden', Protection::PROTECTION_UNPROTECTED => 'none']];
    /** @internal */
    public function write_cell_properties(Cell_Style $style): void
    {
        // Align
        $h_align = $style->get_alignment()->get_horizontal();
        $h_align = $this->map_horizontal_alignment($h_align);
        $v_align = $style->get_alignment()->get_vertical();
        $wrap = $style->get_alignment()->get_wrap_text();
        $indent = $style->get_alignment()->get_indent();
        $read_order = $style->get_alignment()->get_read_order();
        $shrink_to_fit = $style->get_alignment()->get_shrink_to_fit();
        $text_rotation = $style->get_alignment()->get_text_rotation();
        $this->writer->start_element('style:table-cell-properties');
        if (!empty($v_align) || $wrap) {
            if (!empty($v_align)) {
                $v_align = $this->map_vertical_alignment($v_align);
                $this->writer->write_attribute('style:vertical-align', $v_align);
            }
            if ($wrap) {
                $this->writer->write_attribute('fo:wrap-option', 'wrap');
            }
        }
        if ($text_rotation !== null) {
            if ($text_rotation < 0) {
                $text_rotation += 360;
            }
            $this->writer->write_attribute('style:rotation-angle', (string) $text_rotation);
        }
        $this->writer->write_attribute('style:rotation-align', 'none');
        if ($shrink_to_fit) {
            $this->writer->write_attribute('style:shrink-to-fit', 'true');
        }
        // Fill
        $this->write_fill_style($style->get_fill());
        // Border
        $this->write_borders_style($style->get_borders());
        // protection
        $protection = self::PROTECTION_MAP[$style->get_protection()->get_locked()][$style->get_protection()->get_hidden()] ?? '';
        if ($protection !== '') {
            $this->writer->write_attribute('style:cell-protect', $protection);
        }
        $this->writer->end_element();
        if ($h_align !== '' || !empty($indent) || $read_order === Alignment::READORDER_RTL || $read_order === Alignment::READORDER_LTR) {
            $this->writer->start_element('style:paragraph-properties');
            if ($h_align !== '') {
                $this->writer->write_attribute('fo:text-align', $h_align);
            }
            if (!empty($indent)) {
                $indent_string = sprintf('%.4f', $indent * self::INDENT_TO_INCHES) . 'in';
                $this->writer->write_attribute('fo:margin-left', $indent_string);
            }
            if ($read_order === Alignment::READORDER_RTL) {
                $this->writer->write_attribute('style:writing-mode', 'rl-tb');
            } elseif ($read_order === Alignment::READORDER_LTR) {
                $this->writer->write_attribute('style:writing-mode', 'lr-tb');
            }
            $this->writer->end_element();
        }
    }
    protected function map_underline_style(Font $font): string
    {
        return match ($font->get_underline()) {
            Font::UNDERLINE_DOUBLE, Font::UNDERLINE_DOUBLEACCOUNTING => 'double',
            Font::UNDERLINE_SINGLE, Font::UNDERLINE_SINGLEACCOUNTING => 'single',
            default => 'none',
        };
    }
    /** @internal */
    public function write_text_properties(Cell_Style $style): void
    {
        // Font
        $this->writer->start_element('style:text-properties');
        $font = $style->get_font();
        if ($font->get_bold()) {
            $this->writer->write_attribute('fo:font-weight', 'bold');
            $this->writer->write_attribute('style:font-weight-complex', 'bold');
            $this->writer->write_attribute('style:font-weight-asian', 'bold');
        }
        if ($font->get_italic()) {
            $this->writer->write_attribute('fo:font-style', 'italic');
        }
        if ($font->get_auto_color()) {
            $this->writer->write_attribute('style:use-window-font-color', 'true');
        } else {
            $this->writer->write_attribute('fo:color', sprintf('#%s', $font->get_color()->get_rgb()));
        }
        if ($family = $font->get_name()) {
            $this->writer->write_attribute('fo:font-family', $family);
        }
        if ($size = $font->get_size()) {
            $this->writer->write_attribute('fo:font-size', $size == (int) $size ? sprintf('%dpt', $size) : sprintf('%.1Fpt', $size));
        }
        if ($font->get_underline() && $font->get_underline() !== Font::UNDERLINE_NONE) {
            $this->writer->write_attribute('style:text-underline-style', 'solid');
            $this->writer->write_attribute('style:text-underline-width', 'auto');
            $this->writer->write_attribute('style:text-underline-color', 'font-color');
            $underline = $this->map_underline_style($font);
            $this->writer->write_attribute('style:text-underline-type', $underline);
        }
        if ($font->get_strikethrough()) {
            $this->writer->write_attribute('style:text-line-through-style', 'solid');
            $this->writer->write_attribute('style:text-line-through-type', 'single');
        }
        $this->writer->end_element();
        // Close style:text-properties
    }
    protected function write_column_properties(Column_Dimension $column_dimension): void
    {
        $this->writer->start_element('style:table-column-properties');
        $this->writer->write_attribute('style:column-width', round($column_dimension->get_width(Dimension::UOM_CENTIMETERS), 3) . 'cm');
        $this->writer->write_attribute('fo:break-before', 'auto');
        // End
        $this->writer->end_element();
        // Close style:table-column-properties
    }
    public function write_column_styles(Column_Dimension $column_dimension, int $sheet_id): void
    {
        $this->writer->start_element('style:style');
        $this->writer->write_attribute('style:family', 'table-column');
        $this->writer->write_attribute('style:name', sprintf('%s_%d_%d', self::COLUMN_STYLE_PREFIX, $sheet_id, $column_dimension->get_column_numeric()));
        $this->write_column_properties($column_dimension);
        // End
        $this->writer->end_element();
        // Close style:style
    }
    protected function write_row_properties(Row_Dimension $row_dimension): void
    {
        $this->writer->start_element('style:table-row-properties');
        $this->writer->write_attribute('style:row-height', round($row_dimension->get_row_height(Dimension::UOM_CENTIMETERS), 3) . 'cm');
        $this->writer->write_attribute('style:use-optimal-row-height', 'false');
        $this->writer->write_attribute('fo:break-before', 'auto');
        // End
        $this->writer->end_element();
        // Close style:table-row-properties
    }
    public function write_row_styles(Row_Dimension $row_dimension, int $sheet_id): void
    {
        $this->writer->start_element('style:style');
        $this->writer->write_attribute('style:family', 'table-row');
        $this->writer->write_attribute('style:name', sprintf('%s_%d_%d', self::ROW_STYLE_PREFIX, $sheet_id, $row_dimension->get_row_index()));
        $this->write_row_properties($row_dimension);
        // End
        $this->writer->end_element();
        // Close style:style
    }
    public function write_default_row_style(Row_Dimension $row_dimension, int $sheet_id): void
    {
        $this->writer->start_element('style:style');
        $this->writer->write_attribute('style:family', 'table-row');
        $this->writer->write_attribute('style:name', sprintf('%s%d', self::ROW_STYLE_PREFIX, $sheet_id));
        $this->write_row_properties($row_dimension);
        // End
        $this->writer->end_element();
        // Close style:style
    }
    public function write_table_style(Worksheet $worksheet, int $sheet_id): void
    {
        $this->writer->start_element('style:style');
        $this->writer->write_attribute('style:family', 'table');
        $this->writer->write_attribute('style:name', sprintf('%s%d', self::TABLE_STYLE_PREFIX, $sheet_id));
        $this->writer->write_attribute('style:master-page-name', 'Default');
        $this->writer->start_element('style:table-properties');
        $this->writer->write_attribute('table:display', $worksheet->get_sheet_state() === Worksheet::SHEETSTATE_VISIBLE ? 'true' : 'false');
        $this->writer->end_element();
        // Close style:table-properties
        $this->writer->end_element();
        // Close style:style
    }
    private int $num_fmt_index = 199;
    /** @var array<string, string> */
    private array $num_fmt_indexes = [];
    private function write_num_fmt(string $num_fmt): void
    {
        if (array_key_exists($num_fmt, $this->num_fmt_indexes)) {
            return;
        }
        $method = $this->additional_number_formats[$num_fmt] ?? self::NUMBER_FORMAT_METHODS[$num_fmt] ?? null;
        if ($method === null) {
            return;
        }
        ++$this->num_fmt_index;
        $name = 'N' . $this->num_fmt_index;
        $this->num_fmt_indexes[$num_fmt] = $name;
        $method($this, $name);
    }
    public function write(Cell_Style $style): void
    {
        $num_fmt = (string) $style->get_number_format()->get_format_code();
        $this->write_num_fmt($num_fmt);
        $this->writer->start_element('style:style');
        $this->writer->write_attribute('style:name', self::CELL_STYLE_PREFIX . $style->get_index());
        $this->writer->write_attribute('style:family', 'table-cell');
        $this->writer->write_attribute('style:parent-style-name', 'Default');
        if (array_key_exists($num_fmt, $this->num_fmt_indexes)) {
            $this->writer->write_attribute('style:data-style-name', $this->num_fmt_indexes[$num_fmt]);
        }
        // Alignment, fill colour, etc
        $this->write_cell_properties($style);
        // style:text-properties
        $this->write_text_properties($style);
        // End
        $this->writer->end_element();
        // Close style:style
    }
    private const NUMBER_FORMAT_METHODS = [
        Number_Format::FORMAT_NUMBER => [self::class, 'formatNumber'],
        Number_Format::FORMAT_NUMBER_0 => [self::class, 'formatNumber0'],
        Number_Format::FORMAT_NUMBER_00 => [self::class, 'formatNumber00'],
        Number_Format::FORMAT_NUMBER_COMMA_SEPARATED1 => [self::class, 'formatNumberCommaSeparated1'],
        Number_Format::FORMAT_NUMBER_COMMA_SEPARATED2 => [self::class, 'formatNumberCommaSeparated2'],
        Number_Format::FORMAT_PERCENTAGE => [self::class, 'formatPercentage'],
        Number_Format::FORMAT_PERCENTAGE_0 => [self::class, 'formatPercentage0'],
        Number_Format::FORMAT_PERCENTAGE_00 => [self::class, 'formatPercentage00'],
        Number_Format::FORMAT_DATE_YYYYMMDD => [self::class, 'formatDateYyyymmdd'],
        Number_Format::FORMAT_DATE_DDMMYYYY => [self::class, 'formatDateDdmmyyyy'],
        Number_Format::FORMAT_DATE_DMYSLASH => [self::class, 'formatDateDmyslash'],
        Number_Format::FORMAT_DATE_DMYMINUS => [self::class, 'formatDateDmyminus'],
        Number_Format::FORMAT_DATE_DMMINUS => [self::class, 'formatDateDmminus'],
        Number_Format::FORMAT_DATE_MYMINUS => [self::class, 'formatDateMyminus'],
        Number_Format::FORMAT_DATE_XLSX14 => [self::class, 'formatDateXlsx14'],
        Number_Format::FORMAT_DATE_XLSX14_ACTUAL => [self::class, 'formatDateXlsx14Actual'],
        Number_Format::FORMAT_DATE_XLSX15 => [self::class, 'formatDateXlsx15'],
        Number_Format::FORMAT_DATE_XLSX15_YYYY => [self::class, 'formatDateXlsx15Yyyy'],
        Number_Format::FORMAT_DATE_XLSX16 => [self::class, 'formatDateXlsx16'],
        Number_Format::FORMAT_DATE_XLSX17 => [self::class, 'formatDateXlsx17'],
        Number_Format::FORMAT_DATE_XLSX22 => [self::class, 'formatDateXlsx22'],
        Number_Format::FORMAT_DATE_XLSX22_ACTUAL => [self::class, 'formatDateXlsx22Actual'],
        Number_Format::FORMAT_DATE_DATETIME => [self::class, 'formatDateDatetime'],
        Number_Format::FORMAT_DATE_DATETIME_BETTER => [self::class, 'formatDateDatetimeBetter'],
        Number_Format::FORMAT_DATE_TIME1 => [self::class, 'formatDateTime1'],
        Number_Format::FORMAT_DATE_TIME2 => [self::class, 'formatDateTime2'],
        Number_Format::FORMAT_DATE_TIME3 => [self::class, 'formatDateTime3'],
        Number_Format::FORMAT_DATE_TIME4 => [self::class, 'formatDateTime4'],
        Number_Format::FORMAT_DATE_TIME5 => [self::class, 'formatDateTime5'],
        //NumberFormat::FORMAT_DATE_TIME6 => [self::class, 'formatDateTime6'], // FORMAT_DATE_TIME6 is identical to TIME4
        Number_Format::FORMAT_DATE_TIME7 => [self::class, 'formatDateTime7'],
        // constant is probably mis-coded
        Number_Format::FORMAT_DATE_TIME8 => [self::class, 'formatDateTime8'],
        Number_Format::FORMAT_DATE_TIME_INTERVAL_HMS => [self::class, 'formatDateTimeIntervalHms'],
        Number_Format::FORMAT_DATE_YYYYMMDDSLASH => [self::class, 'formatDateYyyymmddslash'],
        Number_Format::FORMAT_DATE_LONG_DATE => [self::class, 'formatDateLongDate'],
        Number_Format::FORMAT_CURRENCY_USD_INTEGER => [self::class, 'formatCurrencyUsdInteger'],
        Number_Format::FORMAT_CURRENCY_USD => [self::class, 'formatCurrencyUsd'],
        Number_Format::FORMAT_ACCOUNTING_USD => [self::class, 'formatCurrencyUsd'],
        // ACCOUNTING and CURRENCY are same in Ods
        Number_Format::FORMAT_CURRENCY_EUR_INTEGER => [self::class, 'formatCurrencyEurInteger'],
        Number_Format::FORMAT_CURRENCY_EUR => [self::class, 'formatCurrencyEur'],
        Number_Format::FORMAT_ACCOUNTING_EUR => [self::class, 'formatCurrencyEur'],
        // ACCOUNTING and CURRENCY are same in Ods
        Number_Format::FORMAT_CURRENCY_GBP_INTEGER => [self::class, 'formatCurrencyGbpInteger'],
        Number_Format::FORMAT_CURRENCY_GBP => [self::class, 'formatCurrencyGbp'],
        Number_Format::FORMAT_CURRENCY_YEN_YUAN_INTEGER => [self::class, 'formatCurrencyYenYuanInteger'],
        Number_Format::FORMAT_CURRENCY_YEN_YUAN => [self::class, 'formatCurrencyYenYuan'],
    ];
    protected static function format_number(self $obj, string $name): void
    {
        $obj->writer->start_element('number:number-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_number0(self $obj, string $name): void
    {
        $obj->writer->start_element('number:number-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '1');
        $obj->writer->write_attribute('number:min-decimal-places', '1');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_number00(self $obj, string $name): void
    {
        $obj->writer->start_element('number:number-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '2');
        $obj->writer->write_attribute('number:min-decimal-places', '2');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_number_comma_separated1(self $obj, string $name): void
    {
        $obj->writer->start_element('number:number-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '2');
        $obj->writer->write_attribute('number:min-decimal-places', '2');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->end_element();
        // number:number
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_number_comma_separated2(self $obj, string $name): void
    {
        $obj->writer->start_element('number:number-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '2');
        $obj->writer->write_attribute('number:min-decimal-places', '2');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->end_element();
        // number:number
        $obj->writer->start_element('number:text');
        //$obj->writer->writeAttribute('loext:blank-width-char', '-');
        $obj->writer->text(' ');
        $obj->writer->end_element();
        // number:text
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_percentage(self $obj, string $name): void
    {
        $obj->writer->start_element('number:percentage-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '0');
        $obj->writer->write_attribute('number:min-decimal-places', '0');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->write_element('number:text', '%');
        $obj->writer->end_element();
        // number:percentage-style
    }
    protected static function format_percentage0(self $obj, string $name): void
    {
        $obj->writer->start_element('number:percentage-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '1');
        $obj->writer->write_attribute('number:min-decimal-places', '1');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->write_element('number:text', '%');
        $obj->writer->end_element();
        // number:percentage-style
    }
    protected static function format_percentage00(self $obj, string $name): void
    {
        $obj->writer->start_element('number:percentage-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $obj->writer->write_attribute('number:decimal-places', '2');
        $obj->writer->write_attribute('number:min-decimal-places', '2');
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->end_element();
        // number:number
        $obj->writer->write_element('number:text', '%');
        $obj->writer->end_element();
        // number:percentage-style
    }
    protected static function format_date_yyyymmdd(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_ddmmyyyy(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_dmyslash(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_dmyminus(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_dmminus(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:month');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_myminus(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx14(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx14actual(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx15(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:textual', 'true');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx15yyyy(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:textual', 'true');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx16(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:textual', 'true');
        $obj->writer->end_element();
        // number:month
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx17(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:textual', 'true');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->write_element('number:year');
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx22(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:year');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_xlsx22actual(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_datetime(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->write_element('number:month');
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:year');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_datetime_better(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '-');
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->start_element('number:hours');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:hours
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_date_time1(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:am-pm');
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time2(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:am-pm');
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time3(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time4(self $obj, string $name): void
    {
        // TIME4 and TIME6 are identical
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time5(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time7(self $obj, string $name): void
    {
        // constant is probably mis-coded
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_time8(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name . 'P0');
        $obj->writer->write_element('number:hours');
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->end_element();
        // number:time-style
        $obj->writer->start_element('number:text-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:text-content');
        $obj->writer->start_element('style:map');
        $obj->writer->write_attribute('style:condition', 'value()>=0');
        $obj->writer->write_attribute('style:apply-style-name', $name . 'P0');
        $obj->writer->end_element();
        // number:style-map
        $obj->writer->end_element();
        // number:text-style
    }
    protected static function format_date_time_interval_hms(self $obj, string $name): void
    {
        $obj->writer->start_element('number:time-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_attribute('number:truncate-on-overflow', 'false');
        $obj->writer->start_element('number:hours');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:hours
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:minutes');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:minutes
        $obj->writer->write_element('number:text', ':');
        $obj->writer->start_element('number:seconds');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:seconds
        $obj->writer->end_element();
        // number:time-style
    }
    protected static function format_date_yyyymmddslash(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name . 'P0');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', '/');
        $obj->writer->start_element('number:day');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day
        $obj->writer->end_element();
        // number:date-style
        $obj->writer->start_element('number:text-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:text-content');
        $obj->writer->start_element('style:map');
        $obj->writer->write_attribute('style:condition', 'value()>=0');
        $obj->writer->write_attribute('style:apply-style-name', $name . 'P0');
        $obj->writer->end_element();
        // number:style-map
        $obj->writer->end_element();
        // number:text-style
    }
    protected static function format_date_long_date(self $obj, string $name): void
    {
        $obj->writer->start_element('number:date-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:day-of-week');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:day-of-week
        $obj->writer->write_element('number:text', ', ');
        $obj->writer->start_element('number:month');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->write_attribute('number:textual', 'true');
        $obj->writer->end_element();
        // number:month
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->write_element('number:day');
        $obj->writer->write_element('number:text', ', ');
        $obj->writer->start_element('number:year');
        $obj->writer->write_attribute('number:style', 'long');
        $obj->writer->end_element();
        // number:year
        $obj->writer->end_element();
        // number:date-style
    }
    protected static function format_currency_usd_integer(self $obj, string $name, string $symbol = '$'): void
    {
        $obj->writer->start_element('number:number-style');
        // not currency-style
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:text', $symbol);
        $obj->writer->start_element('number:number');
        $decimals = '0';
        $obj->writer->write_attribute('number:decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->start_element('number:embedded-text');
        $obj->writer->write_attribute('number-position', '0');
        $obj->writer->text(' ');
        $obj->writer->end_element();
        // number:embedded-text
        $obj->writer->end_element();
        // number:number
        $obj->writer->end_element();
        // number:number-style
    }
    protected static function format_currency_gbp_integer(self $obj, string $name): void
    {
        self::format_currency_usd_integer($obj, $name, '£');
    }
    protected static function format_currency_yen_yuan_integer(self $obj, string $name): void
    {
        self::format_currency_usd_integer($obj, $name, '￥');
    }
    protected static function format_currency_usd(self $obj, string $name, string $symbol = '$'): void
    {
        // Ods uses same format for Currency and Accounting
        $obj->writer->start_element('number:number-style');
        // NOT currency-style
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->write_element('number:text', $symbol);
        $obj->writer->start_element('number:number');
        $decimals = '2';
        $obj->writer->write_attribute('number:decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->end_element();
        // number:number
        $obj->writer->write_element('number:text', ' ');
        $obj->writer->end_element();
        // number:currency-style
    }
    protected static function format_currency_gbp(self $obj, string $name): void
    {
        self::format_currency_usd($obj, $name, '£');
    }
    protected static function format_currency_yen_yuan(self $obj, string $name): void
    {
        self::format_currency_usd($obj, $name, '￥');
    }
    protected static function format_currency_eur_integer(self $obj, string $name): void
    {
        $obj->writer->start_element('number:currency-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $decimals = '0';
        $obj->writer->write_attribute('number:decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->start_element('number:embedded-text');
        $obj->writer->write_attribute('number:position', '0');
        $obj->writer->end_element();
        // number:embedded-text
        $obj->writer->end_element();
        // number:number
        $obj->writer->start_element('number:text');
        // $obj->writer->writeAttribute('loext:blank-width-char', '-');
        $obj->writer->text(' ');
        $obj->writer->end_element();
        // number:text
        $obj->writer->start_element('number:currency-symbol');
        $obj->writer->write_attribute('number:language', 'en');
        $obj->writer->write_attribute('number:country', 'us');
        $obj->writer->text('€');
        $obj->writer->end_element();
        // number:currency-symbol
        $obj->writer->end_element();
        // number:currency-style
    }
    protected static function format_currency_eur(self $obj, string $name): void
    {
        // Ods uses same format for Currency and Accounting
        $obj->writer->start_element('number:currency-style');
        $obj->writer->write_attribute('style:name', $name);
        $obj->writer->start_element('number:number');
        $decimals = '2';
        $obj->writer->write_attribute('number:decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-decimal-places', $decimals);
        $obj->writer->write_attribute('number:min-integer-digits', '1');
        $obj->writer->write_attribute('number:grouping', 'true');
        $obj->writer->end_element();
        // number:number
        $obj->writer->start_element('number:text');
        // $obj->writer->writeAttribute('loext:blank-width-char', '-');
        $obj->writer->text(' ');
        $obj->writer->end_element();
        // number:text
        $obj->writer->start_element('number:currency-symbol');
        $obj->writer->write_attribute('number:language', 'en');
        $obj->writer->write_attribute('number:country', 'us');
        $obj->writer->text('€');
        $obj->writer->end_element();
        // number:currency-symbol
        $obj->writer->end_element();
        // number:currency-style
    }
}