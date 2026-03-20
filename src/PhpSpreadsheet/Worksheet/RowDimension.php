<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Helper\Dimension as CssDimension;
class Row_Dimension extends Dimension
{
    /**
     * Row height (in pt).
     *
     * When this is set to a negative value, the row height should be ignored by IWriter
     */
    private float $height = -1;
    /**
     * ZeroHeight for Row?
     */
    private bool $zero_height = false;
    private bool $custom_format = false;
    private bool $visible_after_filter = true;
    public function set_visible_after_filter(bool $visible_after_filter): self
    {
        $this->visible_after_filter = $visible_after_filter;
        return $this;
    }
    public function get_visible_after_filter(): bool
    {
        return $this->visible_after_filter;
    }
    /**
     * @param ?int $rowIndex Numeric row index
     */
    public function __construct(private ?int $row_index = 0)
    {
        // set dimension as unformatted by default
        parent::__construct();
    }
    public function get_row_index(): ?int
    {
        return $this->row_index;
    }
    public function set_row_index(int $index): static
    {
        $this->row_index = $index;
        return $this;
    }
    /**
     * Get Row Height.
     * By default, this will be in points; but this method also accepts an optional unit of measure
     *    argument, and will convert the value from points to the specified UoM.
     *    A value of -1 tells Excel to display this column in its default height.
     */
    public function get_row_height(?string $unit_of_measure = null): float
    {
        return $unit_of_measure === null || $this->height < 0 ? $this->height : (new Css_Dimension($this->height . Css_Dimension::UOM_POINTS))->to_unit($unit_of_measure);
    }
    /**
     * Set Row Height.
     *
     * @param float $height in points. A value of -1 tells Excel to display this column in its default height.
     * By default, this will be the passed argument value; but this method also accepts an optional unit of measure
     *    argument, and will convert the passed argument value to points from the specified UoM
     */
    public function set_row_height(float $height, ?string $unit_of_measure = null): static
    {
        $this->height = $unit_of_measure === null || $height < 0 ? $height : (new Css_Dimension("{$height}{$unit_of_measure}"))->height();
        $this->custom_format = false;
        return $this;
    }
    public function get_zero_height(): bool
    {
        return $this->zero_height;
    }
    public function set_zero_height(bool $zero_height): static
    {
        $this->zero_height = $zero_height;
        return $this;
    }
    public function get_custom_format(): bool
    {
        return $this->custom_format;
    }
    public function set_custom_format(bool $custom_format, ?float $height = -1): self
    {
        $this->custom_format = $custom_format;
        if ($height !== null) {
            $this->height = $height;
        }
        return $this;
    }
}