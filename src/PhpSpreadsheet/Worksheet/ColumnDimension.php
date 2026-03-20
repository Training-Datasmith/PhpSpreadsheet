<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Helper\Dimension as CssDimension;
class Column_Dimension extends Dimension
{
    public const EXCEL_MAX_WIDTH = 255.0;
    /**
     * Column width.
     *
     * When this is set to a negative value, the column width should be ignored by IWriter
     */
    private float $width = -1;
    /**
     * Auto size?
     */
    private bool $auto_size = false;
    /**
     * Create a new ColumnDimension.
     *
     * @param ?string $columnIndex Character column index
     */
    public function __construct(private ?string $column_index = 'A')
    {
        // set dimension as unformatted by default
        parent::__construct(0);
    }
    /**
     * Get column index as string eg: 'A'.
     */
    public function get_column_index(): ?string
    {
        return $this->column_index;
    }
    /**
     * Set column index as string eg: 'A'.
     */
    public function set_column_index(string $index): self
    {
        $this->column_index = $index;
        return $this;
    }
    /**
     * Get column index as numeric.
     */
    public function get_column_numeric(): int
    {
        return Coordinate::column_index_from_string($this->column_index ?? '');
    }
    /**
     * Set column index as numeric.
     */
    public function set_column_numeric(int $index): self
    {
        $this->column_index = Coordinate::string_from_column_index($index);
        return $this;
    }
    /**
     * Get Width.
     *
     * Each unit of column width is equal to the width of one character in the default font size. A value of -1
     *      tells Excel to display this column in its default width.
     * By default, this will be the return value; but this method also accepts an optional unit of measure argument
     *    and will convert the returned value to the specified UoM..
     */
    public function get_width(?string $unit_of_measure = null): float
    {
        return $unit_of_measure === null || $this->width < 0 ? $this->width : (new Css_Dimension((string) $this->width))->to_unit($unit_of_measure);
    }
    public function get_width_for_output(bool $restrict_max): float
    {
        return $restrict_max && $this->width > self::EXCEL_MAX_WIDTH ? self::EXCEL_MAX_WIDTH : $this->width;
    }
    /**
     * Set Width.
     *
     * Each unit of column width is equal to the width of one character in the default font size. A value of -1
     *      tells Excel to display this column in its default width.
     * By default, this will be the unit of measure for the passed value; but this method also accepts an
     *    optional unit of measure argument, and will convert the value from the specified UoM using an
     *    approximation method.
     *
     * @return $this
     */
    public function set_width(float $width, ?string $unit_of_measure = null): static
    {
        $this->width = $unit_of_measure === null || $width < 0 ? $width : (new Css_Dimension("{$width}{$unit_of_measure}"))->width();
        return $this;
    }
    /**
     * Get Auto Size.
     */
    public function get_auto_size(): bool
    {
        return $this->auto_size;
    }
    /**
     * Set Auto Size.
     *
     * @return $this
     */
    public function set_auto_size(bool $autosize_enabled): static
    {
        $this->auto_size = $autosize_enabled;
        return $this;
    }
}