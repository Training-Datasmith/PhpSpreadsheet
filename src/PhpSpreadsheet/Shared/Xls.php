<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Helper\Dimension;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Xls
{
    /**
     * Get the width of a column in pixels. We use the relationship y = ceil(7x) where
     * x is the width in intrinsic Excel units (measuring width in number of normal characters)
     * This holds for Arial 10.
     *
     * @param Worksheet $worksheet The sheet
     * @param string $col The column
     *
     * @return int The width in pixels
     */
    public static function size_col(Worksheet $worksheet, string $col = 'A'): int
    {
        // default font of the workbook
        $font = $worksheet->get_parent_or_throw()->get_default_style()->get_font();
        $column_dimensions = $worksheet->get_column_dimensions();
        // first find the true column width in pixels (uncollapsed and unhidden)
        if (isset($column_dimensions[$col]) && $column_dimensions[$col]->get_width() != -1) {
            // then we have column dimension with explicit width
            $column_dimension = $column_dimensions[$col];
            $width = $column_dimension->get_width();
            $pixel_width = Drawing::cell_dimension_to_pixels($width, $font);
        } elseif ($worksheet->get_default_column_dimension()->get_width() != -1) {
            // then we have default column dimension with explicit width
            $default_column_dimension = $worksheet->get_default_column_dimension();
            $width = $default_column_dimension->get_width();
            $pixel_width = Drawing::cell_dimension_to_pixels($width, $font);
        } else {
            // we don't even have any default column dimension. Width depends on default font
            $pixel_width = Font::get_default_column_width_by_font($font, true);
        }
        // now find the effective column width in pixels
        if (isset($column_dimensions[$col]) && !$column_dimensions[$col]->get_visible()) {
            return 0;
        }
        return $pixel_width;
    }
    /**
     * Convert the height of a cell from user's units to pixels. By interpolation
     * the relationship is: y = 4/3x. If the height hasn't been set by the user we
     * use the default value. If the row is hidden we use a value of zero.
     *
     * @param Worksheet $worksheet The sheet
     * @param int $row The row index (1-based)
     *
     * @return int The width in pixels
     */
    public static function size_row(Worksheet $worksheet, int $row = 1): int
    {
        // default font of the workbook
        $font = $worksheet->get_parent_or_throw()->get_default_style()->get_font();
        $row_dimensions = $worksheet->get_row_dimensions();
        // first find the true row height in pixels (uncollapsed and unhidden)
        if (isset($row_dimensions[$row]) && $row_dimensions[$row]->get_row_height() != -1) {
            // then we have a row dimension
            $row_dimension = $row_dimensions[$row];
            $row_height = $row_dimension->get_row_height();
            $pixel_row_height = (int) ceil(4 * $row_height / 3);
            // here we assume Arial 10
        } elseif ($worksheet->get_default_row_dimension()->get_row_height() != -1) {
            // then we have a default row dimension with explicit height
            $default_row_dimension = $worksheet->get_default_row_dimension();
            $pixel_row_height = $default_row_dimension->get_row_height(Dimension::UOM_PIXELS);
        } else {
            // we don't even have any default row dimension. Height depends on default font
            $point_row_height = Font::get_default_row_height_by_font($font);
            $pixel_row_height = Font::font_size_to_pixels((int) $point_row_height);
        }
        // now find the effective row height in pixels
        if (isset($row_dimensions[$row]) && !$row_dimensions[$row]->get_visible()) {
            $effective_pixel_row_height = 0;
        } else {
            $effective_pixel_row_height = $pixel_row_height;
        }
        return (int) $effective_pixel_row_height;
    }
    /**
     * Get the horizontal distance in pixels between two anchors
     * The distanceX is found as sum of all the spanning columns widths minus correction for the two offsets.
     *
     * @param float|int $startOffsetX Offset within start cell measured in 1/1024 of the cell width
     * @param float|int $endOffsetX Offset within end cell measured in 1/1024 of the cell width
     *
     * @return int Horizontal measured in pixels
     */
    public static function get_distance_x(Worksheet $worksheet, string $start_column = 'A', float|int $start_offset_x = 0, string $end_column = 'A', float|int $end_offset_x = 0): int
    {
        $distance_x = 0;
        // add the widths of the spanning columns
        $start_column_index = Coordinate::column_index_from_string($start_column);
        $end_column_index = Coordinate::column_index_from_string($end_column);
        for ($i = $start_column_index; $i <= $end_column_index; ++$i) {
            $distance_x += self::size_col($worksheet, Coordinate::string_from_column_index($i));
        }
        // correct for offsetX in startcell
        $distance_x -= (int) floor(self::size_col($worksheet, $start_column) * $start_offset_x / 1024);
        // correct for offsetX in endcell
        $distance_x -= (int) floor(self::size_col($worksheet, $end_column) * (1 - $end_offset_x / 1024));
        return $distance_x;
    }
    /**
     * Get the vertical distance in pixels between two anchors
     * The distanceY is found as sum of all the spanning rows minus two offsets.
     *
     * @param int $startRow (1-based)
     * @param float|int $startOffsetY Offset within start cell measured in 1/256 of the cell height
     * @param int $endRow (1-based)
     * @param float|int $endOffsetY Offset within end cell measured in 1/256 of the cell height
     *
     * @return int Vertical distance measured in pixels
     */
    public static function get_distance_y(Worksheet $worksheet, int $start_row = 1, float|int $start_offset_y = 0, int $end_row = 1, float|int $end_offset_y = 0): int
    {
        $distance_y = 0;
        // add the widths of the spanning rows
        for ($row = $start_row; $row <= $end_row; ++$row) {
            $distance_y += self::size_row($worksheet, $row);
        }
        // correct for offsetX in startcell
        $distance_y -= (int) floor(self::size_row($worksheet, $start_row) * $start_offset_y / 256);
        // correct for offsetX in endcell
        $distance_y -= (int) floor(self::size_row($worksheet, $end_row) * (1 - $end_offset_y / 256));
        return $distance_y;
    }
    /**
     * Convert 1-cell anchor coordinates to 2-cell anchor coordinates
     * This function is ported from PEAR Spreadsheet_Writer_Excel with small modifications.
     *
     * Calculate the vertices that define the position of the image as required by
     * the OBJ record.
     *
     *         +------------+------------+
     *         |     A      |      B     |
     *   +-----+------------+------------+
     *   |     |(x1,y1)     |            |
     *   |  1  |(A1)._______|______      |
     *   |     |    |              |     |
     *   |     |    |              |     |
     *   +-----+----|    BITMAP    |-----+
     *   |     |    |              |     |
     *   |  2  |    |______________.     |
     *   |     |            |        (B2)|
     *   |     |            |     (x2,y2)|
     *   +---- +------------+------------+
     *
     * Example of a bitmap that covers some of the area from cell A1 to cell B2.
     *
     * Based on the width and height of the bitmap we need to calculate 8 vars:
     *     $col_start, $row_start, $col_end, $row_end, $x1, $y1, $x2, $y2.
     * The width and height of the cells are also variable and have to be taken into
     * account.
     * The values of $col_start and $row_start are passed in from the calling
     * function. The values of $col_end and $row_end are calculated by subtracting
     * the width and height of the bitmap from the width and height of the
     * underlying cells.
     * The vertices are expressed as a percentage of the underlying cell width as
     * follows (rhs values are in pixels):
     *
     *       x1 = X / W *1024
     *       y1 = Y / H *256
     *       x2 = (X-1) / W *1024
     *       y2 = (Y-1) / H *256
     *
     *       Where:  X is distance from the left side of the underlying cell
     *               Y is distance from the top of the underlying cell
     *               W is the width of the cell
     *               H is the height of the cell
     *
     * @param string $coordinates E.g. 'A1'
     * @param int $offsetX Horizontal offset in pixels
     * @param int $offsetY Vertical offset in pixels
     * @param int $width Width in pixels
     * @param int $height Height in pixels
     *
     * @return ?array{startCoordinates: string, startOffsetX: float|int, startOffsetY: float|int, endCoordinates: string, endOffsetX: float|int, endOffsetY: float|int}
     */
    public static function one_anchor2two_anchor(Worksheet $worksheet, string $coordinates, int $offset_x, int $offset_y, int $width, int $height): ?array
    {
        [$col_start, $row] = Coordinate::indexes_from_string($coordinates);
        $row_start = $row - 1;
        $x1 = $offset_x;
        $y1 = $offset_y;
        // Initialise end cell to the same as the start cell
        $col_end = $col_start;
        // Col containing lower right corner of object
        $row_end = $row_start;
        // Row containing bottom right corner of object
        // Zero the specified offset if greater than the cell dimensions
        if ($x1 >= self::size_col($worksheet, Coordinate::string_from_column_index($col_start))) {
            $x1 = 0;
        }
        if ($y1 >= self::size_row($worksheet, $row_start + 1)) {
            $y1 = 0;
        }
        $width = $width + $x1 - 1;
        $height = $height + $y1 - 1;
        // Subtract the underlying cell widths to find the end cell of the image
        while ($width >= self::size_col($worksheet, Coordinate::string_from_column_index($col_end))) {
            $width -= self::size_col($worksheet, Coordinate::string_from_column_index($col_end));
            ++$col_end;
        }
        // Subtract the underlying cell heights to find the end cell of the image
        while ($height >= self::size_row($worksheet, $row_end + 1)) {
            $height -= self::size_row($worksheet, $row_end + 1);
            ++$row_end;
        }
        // Bitmap isn't allowed to start or finish in a hidden cell, i.e. a cell
        // with zero height or width.
        if (self::size_col($worksheet, Coordinate::string_from_column_index($col_start)) == 0 || self::size_col($worksheet, Coordinate::string_from_column_index($col_end)) == 0 || self::size_row($worksheet, $row_start + 1) == 0 || self::size_row($worksheet, $row_end + 1) == 0) {
            return null;
        }
        // Convert the pixel values to the percentage value expected by Excel
        $x1 = $x1 / self::size_col($worksheet, Coordinate::string_from_column_index($col_start)) * 1024;
        $y1 = $y1 / self::size_row($worksheet, $row_start + 1) * 256;
        $x2 = ($width + 1) / self::size_col($worksheet, Coordinate::string_from_column_index($col_end)) * 1024;
        // Distance to right side of object
        $y2 = ($height + 1) / self::size_row($worksheet, $row_end + 1) * 256;
        // Distance to bottom of object
        $start_coordinates = Coordinate::string_from_column_index($col_start) . ($row_start + 1);
        $end_coordinates = Coordinate::string_from_column_index($col_end) . ($row_end + 1);
        return ['startCoordinates' => $start_coordinates, 'startOffsetX' => $x1, 'startOffsetY' => $y1, 'endCoordinates' => $end_coordinates, 'endOffsetX' => $x2, 'endOffsetY' => $y2];
    }
}