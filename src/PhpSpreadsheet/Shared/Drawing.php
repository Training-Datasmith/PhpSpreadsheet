<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Simple_Xml_Element;
class Drawing
{
    /**
     * Convert pixels to EMU.
     *
     * @param int $pixelValue Value in pixels
     *
     * @return float|int Value in EMU
     */
    public static function pixels_to_emu(int $pixel_value): int|float
    {
        return $pixel_value * 9525;
    }
    /**
     * Convert EMU to pixels.
     *
     * @param int|SimpleXMLElement $emuValue Value in EMU
     *
     * @return int Value in pixels
     */
    public static function emu_to_pixels($emu_value): int
    {
        $emu_value = (int) $emu_value;
        if ($emu_value != 0) {
            return (int) round($emu_value / 9525);
        }
        return 0;
    }
    /**
     * Convert pixels to column width. Exact algorithm not known.
     * By inspection of a real Excel file using Calibri 11, one finds 1000px ~ 142.85546875
     * This gives a conversion factor of 7. Also, we assume that pixels and font size are proportional.
     *
     * @param int $pixelValue Value in pixels
     *
     * @return float|int Value in cell dimension
     */
    public static function pixels_to_cell_dimension(int $pixel_value, \Php_Office\Php_Spreadsheet\Style\Font $default_font): int|float
    {
        // Font name and size
        $name = $default_font->get_name();
        $size = $default_font->get_size();
        $sizex = $size !== null && $size == (int) $size ? (int) $size : "{$size}";
        if (isset(Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex])) {
            // Exact width can be determined
            return $pixel_value * Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex]['width'] / Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex]['px'];
        }
        // We don't have data for this particular font and size, use approximation by
        // extrapolating from Calibri 11
        return $pixel_value * 11 * Font::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['width'] / Font::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['px'] / $size;
    }
    /**
     * Convert column width from (intrinsic) Excel units to pixels.
     *
     * @param float $cellWidth Value in cell dimension
     * @param \PhpOffice\PhpSpreadsheet\Style\Font $defaultFont Default font of the workbook
     *
     * @return int Value in pixels
     */
    public static function cell_dimension_to_pixels(float $cell_width, \Php_Office\Php_Spreadsheet\Style\Font $default_font): int
    {
        // Font name and size
        $name = $default_font->get_name();
        $size = $default_font->get_size();
        $sizex = $size !== null && $size == (int) $size ? (int) $size : "{$size}";
        if (isset(Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex])) {
            // Exact width can be determined
            $col_width = $cell_width * Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex]['px'] / Font::DEFAULT_COLUMN_WIDTHS[$name][$sizex]['width'];
        } else {
            // We don't have data for this particular font and size, use approximation by
            // extrapolating from Calibri 11
            $col_width = $cell_width * $size * Font::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['px'] / Font::DEFAULT_COLUMN_WIDTHS['Calibri'][11]['width'] / 11;
        }
        // Round pixels to closest integer
        $col_width = (int) round($col_width);
        return $col_width;
    }
    /**
     * Convert pixels to points.
     *
     * @param int $pixelValue Value in pixels
     *
     * @return float Value in points
     */
    public static function pixels_to_points(int $pixel_value): float
    {
        return $pixel_value * 0.75;
    }
    /**
     * Convert points to pixels.
     *
     * @param float|int $pointValue Value in points
     *
     * @return int Value in pixels
     */
    public static function points_to_pixels($point_value): int
    {
        return (int) ceil($point_value / 0.75);
    }
    /**
     * Convert degrees to angle.
     *
     * @param int $degrees Degrees
     *
     * @return int Angle
     */
    public static function degrees_to_angle(int $degrees): int
    {
        return (int) round($degrees * 60000);
    }
    /**
     * Convert angle to degrees.
     *
     * @param int|SimpleXMLElement $angle Angle
     *
     * @return int Degrees
     */
    public static function angle_to_degrees($angle): int
    {
        $angle = (int) $angle;
        if ($angle != 0) {
            return (int) round($angle / 60000);
        }
        return 0;
    }
}