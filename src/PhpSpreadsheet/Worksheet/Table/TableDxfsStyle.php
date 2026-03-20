<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Table;

use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
class Table_Dxfs_Style
{
    /**
     * Header row dxfs index.
     */
    private ?int $header_row = null;
    /**
     * First row stripe dxfs index.
     */
    private ?int $first_row_stripe = null;
    /**
     * second row stripe dxfs index.
     */
    private ?int $second_row_stripe = null;
    /**
     * Header row Style.
     */
    private ?Style $header_row_style = null;
    /**
     * First row stripe Style.
     */
    private ?Style $first_row_stripe_style = null;
    /**
     * Second row stripe Style.
     */
    private ?Style $second_row_stripe_style = null;
    /**
     * Create a new Table Style.
     *
     * @param string $name The name
     */
    public function __construct(private readonly string $name)
    {
    }
    /**
     * Get name.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Set header row dxfs index.
     */
    public function set_header_row(int $row): self
    {
        $this->header_row = $row;
        return $this;
    }
    /**
     * Get header row dxfs index.
     */
    public function get_header_row(): ?int
    {
        return $this->header_row;
    }
    /**
     * Set first row stripe dxfs index.
     */
    public function set_first_row_stripe(int $row): self
    {
        $this->first_row_stripe = $row;
        return $this;
    }
    /**
     * Get first row stripe dxfs index.
     */
    public function get_first_row_stripe(): ?int
    {
        return $this->first_row_stripe;
    }
    /**
     * Set second row stripe dxfs index.
     */
    public function set_second_row_stripe(int $row): self
    {
        $this->second_row_stripe = $row;
        return $this;
    }
    /**
     * Get second row stripe dxfs index.
     */
    public function get_second_row_stripe(): ?int
    {
        return $this->second_row_stripe;
    }
    /**
     * Set Header row Style.
     */
    public function set_header_row_style(Style $style): self
    {
        $this->header_row_style = $style;
        return $this;
    }
    /**
     * Get Header row Style.
     */
    public function get_header_row_style(): ?Style
    {
        return $this->header_row_style;
    }
    /**
     * Set first row stripe Style.
     */
    public function set_first_row_stripe_style(Style $style): self
    {
        $this->first_row_stripe_style = $style;
        return $this;
    }
    /**
     * Get first row stripe Style.
     */
    public function get_first_row_stripe_style(): ?Style
    {
        return $this->first_row_stripe_style;
    }
    /**
     * Set second row stripe Style.
     */
    public function set_second_row_stripe_style(Style $style): self
    {
        $this->second_row_stripe_style = $style;
        return $this;
    }
    /**
     * Get second row stripe Style.
     */
    public function get_second_row_stripe_style(): ?Style
    {
        return $this->second_row_stripe_style;
    }
}