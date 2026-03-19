<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Cell;

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
    public function getUrl(): string
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
    public function setUrl(string $url): static
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['javascript', 'data'], true)) {
            throw new \InvalidArgumentException(
                "URL scheme '{$scheme}:' is not permitted in a hyperlink."
            );
        }

        $this->url = $url;

        return $this;
    }

    /**
     * Get tooltip.
     */
    public function getTooltip(): string
    {
        return $this->tooltip;
    }

    /**
     * Set tooltip.
     *
     * @return $this
     */
    public function setTooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    /**
     * Is this hyperlink internal? (to another worksheet or a cell in this worksheet).
     */
    public function isInternal(): bool
    {
        return str_starts_with($this->url, 'sheet://') || str_starts_with($this->url, '#');
    }

    public function getTypeHyperlink(): string
    {
        return $this->isInternal() ? '' : 'External';
    }

    public function getDisplay(): string
    {
        return $this->display;
    }

    /**
     * This can be displayed in cell rather than actual cell contents.
     * It seems to be ignored by Excel.
     * It may be used by Google Sheets.
     */
    public function setDisplay(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function getHashCode(): string
    {
        return md5(
            $this->url
            . ','
            . $this->tooltip
            . ','
            . $this->display
            . ','
            . self::class
        );
    }
}
