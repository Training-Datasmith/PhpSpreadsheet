<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

class Legend
{
    /** Legend positions */
    public const XL_LEGEND_POSITION_BOTTOM = -4107;
    //    Below the chart.
    public const XL_LEGEND_POSITION_CORNER = 2;
    //    In the upper right-hand corner of the chart border.
    public const XL_LEGEND_POSITION_CUSTOM = -4161;
    //    A custom position.
    public const XL_LEGEND_POSITION_LEFT = -4131;
    //    Left of the chart.
    public const XL_LEGEND_POSITION_RIGHT = -4152;
    //    Right of the chart.
    public const XL_LEGEND_POSITION_TOP = -4160;
    //    Above the chart.
    public const POSITION_RIGHT = 'r';
    public const POSITION_LEFT = 'l';
    public const POSITION_BOTTOM = 'b';
    public const POSITION_TOP = 't';
    public const POSITION_TOPRIGHT = 'tr';
    public const POSITION_XLREF = [self::XL_LEGEND_POSITION_BOTTOM => self::POSITION_BOTTOM, self::XL_LEGEND_POSITION_CORNER => self::POSITION_TOPRIGHT, self::XL_LEGEND_POSITION_CUSTOM => '??', self::XL_LEGEND_POSITION_LEFT => self::POSITION_LEFT, self::XL_LEGEND_POSITION_RIGHT => self::POSITION_RIGHT, self::XL_LEGEND_POSITION_TOP => self::POSITION_TOP];
    /**
     * Legend position.
     */
    private string $position = self::POSITION_RIGHT;
    /**
     * Allow overlay of other elements?
     */
    private bool $overlay = true;
    private Grid_Lines $border_lines;
    private Chart_Color $fill_color;
    private ?Axis_Text $legend_text = null;
    /**
     * Create a new Legend.
     */
    public function __construct(
        string $position = self::POSITION_RIGHT,
        /**
         * Legend Layout.
         */
        private ?Layout $layout = null,
        bool $overlay = false
    )
    {
        $this->set_position($position);
        $this->set_overlay($overlay);
        $this->border_lines = new Grid_Lines();
        $this->fill_color = new Chart_Color();
    }
    public function get_fill_color(): Chart_Color
    {
        return $this->fill_color;
    }
    /**
     * Get legend position as an Excel string value.
     */
    public function get_position(): string
    {
        return $this->position;
    }
    /**
     * Get legend position using an Excel string value.
     *
     * @param string $position see self::POSITION_*
     */
    public function set_position(string $position): bool
    {
        if (!in_array($position, self::POSITION_XLREF)) {
            return false;
        }
        $this->position = $position;
        return true;
    }
    /**
     * Get legend position as an Excel internal numeric value.
     */
    public function get_position_xl(): false|int
    {
        return array_search($this->position, self::POSITION_XLREF);
    }
    /**
     * Set legend position using an Excel internal numeric value.
     *
     * @param int $positionXL see self::XL_LEGEND_POSITION_*
     */
    public function set_position_xl(int $position_xl): bool
    {
        if (!isset(self::POSITION_XLREF[$position_xl])) {
            return false;
        }
        $this->position = self::POSITION_XLREF[$position_xl];
        return true;
    }
    /**
     * Get allow overlay of other elements?
     */
    public function get_overlay(): bool
    {
        return $this->overlay;
    }
    /**
     * Set allow overlay of other elements?
     */
    public function set_overlay(bool $overlay): void
    {
        $this->overlay = $overlay;
    }
    /**
     * Get Layout.
     */
    public function get_layout(): ?Layout
    {
        return $this->layout;
    }
    public function get_legend_text(): ?Axis_Text
    {
        return $this->legend_text;
    }
    public function set_legend_text(?Axis_Text $legend_text): self
    {
        $this->legend_text = $legend_text;
        return $this;
    }
    public function get_border_lines(): Grid_Lines
    {
        return $this->border_lines;
    }
    public function set_border_lines(Grid_Lines $border_lines): self
    {
        $this->border_lines = $border_lines;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->layout = $this->layout === null ? null : clone $this->layout;
        $this->legend_text = $this->legend_text === null ? null : clone $this->legend_text;
        $this->border_lines = clone $this->border_lines;
        $this->fill_color = clone $this->fill_color;
    }
}