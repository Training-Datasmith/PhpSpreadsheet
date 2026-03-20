<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Merged_Cell_Style
{
    private bool $matched = false;
    /**
     * Indicate whether the last call to getMergedStyle found
     * any conditional or table styles affecting the cell in question.
     */
    public function get_matched(): bool
    {
        return $this->matched;
    }
    /**
     * Return a style that combines the base style for a cell
     * with any conditional or table styles applicable to the cell.
     *
     * @param bool $tableFormats True/false to indicate whether
     *        custom table styles should be considered.
     *        Note that builtin table styles are not supported.
     * @param bool $conditionals True/false to indicate whether
     *        conditional styles should be considered.
     */
    public function get_merged_style(Worksheet $worksheet, string $coordinate, bool $table_formats = true, bool $conditionals = true, ?bool $built_in_table_styles = null): Style
    {
        $built_in_table_styles ??= $table_formats;
        $this->matched = false;
        $style_merger = new Style_Merger($worksheet->get_style($coordinate));
        if ($table_formats) {
            $this->assess_tables($worksheet, $coordinate, $style_merger);
        }
        if ($built_in_table_styles) {
            $this->assess_builtin_tables($worksheet, $coordinate, $style_merger);
        }
        if ($conditionals) {
            $this->assess_conditionals($worksheet, $coordinate, $style_merger);
        }
        return $style_merger->get_style();
    }
    private function assess_tables(Worksheet $worksheet, string $coordinate, Style_Merger $style_merger): void
    {
        $tables = $worksheet->get_tables_with_styles_for_cell($worksheet->get_cell($coordinate));
        foreach ($tables as $ts) {
            $dxfs_table_style = $ts->get_style()->get_table_dxfs_style();
            if ($dxfs_table_style !== null) {
                $table_row = $ts->get_row_number($coordinate);
                if ($table_row === 0 && $dxfs_table_style->get_header_row_style() !== null) {
                    $style_merger->merge_style($dxfs_table_style->get_header_row_style());
                    $this->matched = true;
                } elseif ($table_row % 2 === 1 && $dxfs_table_style->get_first_row_stripe_style() !== null) {
                    $style_merger->merge_style($dxfs_table_style->get_first_row_stripe_style());
                    $this->matched = true;
                } elseif ($table_row % 2 === 0 && $dxfs_table_style->get_second_row_stripe_style() !== null) {
                    $style_merger->merge_style($dxfs_table_style->get_second_row_stripe_style());
                    $this->matched = true;
                }
            }
        }
    }
    private static ?Style $header_style = null;
    private static ?Style $first_row_style = null;
    private function assess_builtin_tables(Worksheet $worksheet, string $coordinate, Style_Merger $style_merger): void
    {
        if (self::$header_style === null) {
            self::$header_style = new Style();
            self::$header_style->get_fill()->set_fill_type(Fill::FILL_SOLID)->get_end_color()->set_argb('FF000000');
            self::$header_style->get_fill()->get_start_color()->set_argb('FF000000');
            self::$header_style->get_font()->get_color()->set_rgb('FFFFFF');
        }
        if (self::$first_row_style === null) {
            self::$first_row_style = new Style();
            self::$first_row_style->get_fill()->set_fill_type(Fill::FILL_SOLID)->get_end_color()->set_argb('FFD9D9D9');
            self::$first_row_style->get_fill()->get_start_color()->set_argb('FFD9D9D9');
        }
        $tables = $worksheet->get_tables_without_styles_for_cell($worksheet->get_cell($coordinate));
        foreach ($tables as $table) {
            $table_row = $table->get_row_number($coordinate);
            if ($table_row === 0 && $table->get_show_header_row()) {
                $style_merger->merge_style(self::$header_style);
                $this->matched = true;
            } elseif ($table_row % 2 === 1) {
                $style_merger->merge_style(self::$first_row_style);
                $this->matched = true;
            }
        }
    }
    private function assess_conditionals(Worksheet $worksheet, string $coordinate, Style_Merger $style_merger): void
    {
        if ($worksheet->get_conditional_range($coordinate) !== null) {
            $assessor = new Cell_Style_Assessor($worksheet->get_cell($coordinate), $worksheet->get_conditional_range($coordinate));
        } else {
            $assessor = new Cell_Style_Assessor($worksheet->get_cell($coordinate), $coordinate);
        }
        $matched_style = $assessor->match_conditions_return_null_if_none_matched($worksheet->get_conditional_styles($coordinate), $worksheet->get_cell($coordinate)->get_calculated_value_string(), true);
        if ($matched_style !== null) {
            $this->matched = true;
            $style_merger->merge_style($matched_style);
        }
    }
}