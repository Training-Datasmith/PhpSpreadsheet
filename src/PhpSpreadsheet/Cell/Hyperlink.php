<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

class Hyperlink
{
    private string $display = '';
    /**
     * Create a new Hyperlink.
     *
     * @param string $url Url to link the cell to
     * @param string $tooltip Tooltip to display on the hyperlink
     */
    public function __construct(private string $url = '', private string $tooltip = '')
    {
    }
    /**
     * Get URL.
     */
    public function get_url(): string
    {
        return $this->url;
    }
    /**
     * Set URL.
     *
     * Rejects URLs using dangerous schemes such as javascript: or data: to
     * prevent XSS/code-injection when the spreadsheet is rendered as HTML.
     *
     * @throws \InvalidArgumentException if the URL uses a forbidden scheme
     * @return $this
     */
    public function set_url(string $url): static
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['javascript', 'data'], true)) {
            throw new \InvalidArgumentException("URL scheme '{$scheme}:' is not permitted in a hyperlink.");
        }
        $this->url = $url;
        return $this;
    }
    /**
     * Get tooltip.
     */
    public function get_tooltip(): string
    {
        return $this->tooltip;
    }
    /**
     * Set tooltip.
     *
     * @return $this
     */
    public function set_tooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;
        return $this;
    }
    /**
     * Is this hyperlink internal? (to another worksheet or a cell in this worksheet).
     */
    public function is_internal(): bool
    {
        return str_starts_with($this->url, 'sheet://') || str_starts_with($this->url, '#');
    }
    public function get_type_hyperlink(): string
    {
        return $this->is_internal() ? '' : 'External';
    }
    public function get_display(): string
    {
        return $this->display;
    }
    /**
     * This can be displayed in cell rather than actual cell contents.
     * It seems to be ignored by Excel.
     * It may be used by Google Sheets.
     */
    public function set_display(string $display): self
    {
        $this->display = $display;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->url . ',' . $this->tooltip . ',' . $this->display . ',' . self::class);
    }
}