<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Sheet_View
{
    // Sheet View types
    public const SHEETVIEW_NORMAL = 'normal';
    public const SHEETVIEW_PAGE_LAYOUT = 'pageLayout';
    public const SHEETVIEW_PAGE_BREAK_PREVIEW = 'pageBreakPreview';
    private const SHEET_VIEW_TYPES = [self::SHEETVIEW_NORMAL, self::SHEETVIEW_PAGE_LAYOUT, self::SHEETVIEW_PAGE_BREAK_PREVIEW];
    /**
     * ZoomScale.
     *
     * Valid values range from 10 to 400.
     */
    private ?int $zoom_scale = 100;
    /**
     * ZoomScaleNormal.
     *
     * Valid values range from 10 to 400.
     */
    private ?int $zoom_scale_normal = 100;
    /**
     * ZoomScalePageLayoutView.
     *
     * Valid values range from 10 to 400.
     */
    private int $zoom_scale_page_layout_view = 100;
    /**
     * ZoomScaleSheetLayoutView.
     *
     * Valid values range from 10 to 400.
     */
    private int $zoom_scale_sheet_layout_view = 100;
    /**
     * ShowZeros.
     *
     * If true, "null" values from a calculation will be shown as "0". This is the default Excel behaviour and can be changed
     * with the advanced worksheet option "Show a zero in cells that have zero value"
     */
    private bool $show_zeros = true;
    /**
     * View.
     *
     * Valid values range from 10 to 400.
     */
    private string $sheetview_type = self::SHEETVIEW_NORMAL;
    /**
     * Get ZoomScale.
     */
    public function get_zoom_scale(): ?int
    {
        return $this->zoom_scale;
    }
    /**
     * Set ZoomScale.
     * Valid values range from 10 to 400.
     *
     * @return $this
     */
    public function set_zoom_scale(?int $zoom_scale): static
    {
        // Microsoft Office Excel 2007 only allows setting a scale between 10 and 400 via the user interface,
        // but it is apparently still able to handle any scale >= 1
        if ($zoom_scale === null || $zoom_scale >= 1) {
            $this->zoom_scale = $zoom_scale;
        } else {
            throw new Php_Spreadsheet_Exception('Scale must be greater than or equal to 1.');
        }
        return $this;
    }
    /**
     * Get ZoomScaleNormal.
     */
    public function get_zoom_scale_normal(): ?int
    {
        return $this->zoom_scale_normal;
    }
    /**
     * Set ZoomScale.
     * Valid values range from 10 to 400.
     *
     * @return $this
     */
    public function set_zoom_scale_normal(?int $zoom_scale_normal): static
    {
        if ($zoom_scale_normal === null || $zoom_scale_normal >= 1) {
            $this->zoom_scale_normal = $zoom_scale_normal;
        } else {
            throw new Php_Spreadsheet_Exception('Scale must be greater than or equal to 1.');
        }
        return $this;
    }
    public function get_zoom_scale_page_layout_view(): int
    {
        return $this->zoom_scale_page_layout_view;
    }
    public function set_zoom_scale_page_layout_view(int $zoom_scale_page_layout_view): static
    {
        if ($zoom_scale_page_layout_view >= 1) {
            $this->zoom_scale_page_layout_view = $zoom_scale_page_layout_view;
        } else {
            throw new Php_Spreadsheet_Exception('Scale must be greater than or equal to 1.');
        }
        return $this;
    }
    public function get_zoom_scale_sheet_layout_view(): int
    {
        return $this->zoom_scale_sheet_layout_view;
    }
    public function set_zoom_scale_sheet_layout_view(int $zoom_scale_sheet_layout_view): static
    {
        if ($zoom_scale_sheet_layout_view >= 1) {
            $this->zoom_scale_sheet_layout_view = $zoom_scale_sheet_layout_view;
        } else {
            throw new Php_Spreadsheet_Exception('Scale must be greater than or equal to 1.');
        }
        return $this;
    }
    /**
     * Set ShowZeroes setting.
     */
    public function set_show_zeros(bool $show_zeros): void
    {
        $this->show_zeros = $show_zeros;
    }
    public function get_show_zeros(): bool
    {
        return $this->show_zeros;
    }
    /**
     * Get View.
     */
    public function get_view(): string
    {
        return $this->sheetview_type;
    }
    /**
     * Set View.
     *
     * Valid values are
     *        'normal'            self::SHEETVIEW_NORMAL
     *        'pageLayout'        self::SHEETVIEW_PAGE_LAYOUT
     *        'pageBreakPreview'  self::SHEETVIEW_PAGE_BREAK_PREVIEW
     *
     * @return $this
     */
    public function set_view(?string $sheet_view_type): static
    {
        // MS Excel 2007 allows setting the view to 'normal', 'pageLayout' or 'pageBreakPreview' via the user interface
        if ($sheet_view_type === null) {
            $sheet_view_type = self::SHEETVIEW_NORMAL;
        }
        if (in_array($sheet_view_type, self::SHEET_VIEW_TYPES)) {
            $this->sheetview_type = $sheet_view_type;
        } else {
            throw new Php_Spreadsheet_Exception('Invalid sheetview layout type.');
        }
        return $this;
    }
}