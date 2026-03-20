<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

class Page_Margins
{
    /**
     * Left.
     */
    private float $left = 0.7;
    /**
     * Right.
     */
    private float $right = 0.7;
    /**
     * Top.
     */
    private float $top = 0.75;
    /**
     * Bottom.
     */
    private float $bottom = 0.75;
    /**
     * Header.
     */
    private float $header = 0.3;
    /**
     * Footer.
     */
    private float $footer = 0.3;
    /**
     * Get Left.
     */
    public function get_left(): float
    {
        return $this->left;
    }
    /**
     * Set Left.
     *
     * @return $this
     */
    public function set_left(float $left): static
    {
        $this->left = $left;
        return $this;
    }
    /**
     * Get Right.
     */
    public function get_right(): float
    {
        return $this->right;
    }
    /**
     * Set Right.
     *
     * @return $this
     */
    public function set_right(float $right): static
    {
        $this->right = $right;
        return $this;
    }
    /**
     * Get Top.
     */
    public function get_top(): float
    {
        return $this->top;
    }
    /**
     * Set Top.
     *
     * @return $this
     */
    public function set_top(float $top): static
    {
        $this->top = $top;
        return $this;
    }
    /**
     * Get Bottom.
     */
    public function get_bottom(): float
    {
        return $this->bottom;
    }
    /**
     * Set Bottom.
     *
     * @return $this
     */
    public function set_bottom(float $bottom): static
    {
        $this->bottom = $bottom;
        return $this;
    }
    /**
     * Get Header.
     */
    public function get_header(): float
    {
        return $this->header;
    }
    /**
     * Set Header.
     *
     * @return $this
     */
    public function set_header(float $header): static
    {
        $this->header = $header;
        return $this;
    }
    /**
     * Get Footer.
     */
    public function get_footer(): float
    {
        return $this->footer;
    }
    /**
     * Set Footer.
     *
     * @return $this
     */
    public function set_footer(float $footer): static
    {
        $this->footer = $footer;
        return $this;
    }
    public static function from_centimeters(float $value): float
    {
        return $value / 2.54;
    }
    public static function to_centimeters(float $value): float
    {
        return $value * 2.54;
    }
    public static function from_millimeters(float $value): float
    {
        return $value / 25.4;
    }
    public static function to_millimeters(float $value): float
    {
        return $value * 25.4;
    }
    public static function from_points(float $value): float
    {
        return $value / 72;
    }
    public static function to_points(float $value): float
    {
        return $value * 72;
    }
}