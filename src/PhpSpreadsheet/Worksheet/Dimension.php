<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
abstract class Dimension
{
    /**
     * Visible?
     */
    private bool $visible = true;
    /**
     * Outline level.
     */
    private int $outline_level = 0;
    /**
     * Collapsed.
     */
    private bool $collapsed = false;
    /**
     * Create a new Dimension.
     *
     * @param ?int $xfIndex Numeric row index
     */
    public function __construct(private ?int $xf_index = null)
    {
    }
    /**
     * Get Visible.
     */
    public function get_visible(): bool
    {
        return $this->visible;
    }
    /**
     * Set Visible.
     *
     * @return $this
     */
    public function set_visible(bool $visible)
    {
        $this->visible = $visible;
        return $this;
    }
    /**
     * Get Outline Level.
     */
    public function get_outline_level(): int
    {
        return $this->outline_level;
    }
    /**
     * Set Outline Level.
     * Value must be between 0 and 7.
     *
     * @return $this
     */
    public function set_outline_level(int $level)
    {
        if ($level < 0 || $level > 7) {
            throw new Php_Spreadsheet_Exception('Outline level must range between 0 and 7.');
        }
        $this->outline_level = $level;
        return $this;
    }
    /**
     * Get Collapsed.
     */
    public function get_collapsed(): bool
    {
        return $this->collapsed;
    }
    /**
     * Set Collapsed.
     *
     * @return $this
     */
    public function set_collapsed(bool $collapsed)
    {
        $this->collapsed = $collapsed;
        return $this;
    }
    /**
     * Get index to cellXf.
     */
    public function get_xf_index(): ?int
    {
        return $this->xf_index;
    }
    /**
     * Set index to cellXf.
     *
     * @return $this
     */
    public function set_xf_index(int $xf_index)
    {
        $this->xf_index = $xf_index;
        return $this;
    }
}