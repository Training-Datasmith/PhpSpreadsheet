<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Font;
class Title
{
    public const TITLE_CELL_REFERENCE = '/^(.*)!' . '[$]([A-Z]{1,3})' . '[$](\d{1,7})$/i';
    /**
     * Allow overlay of other elements?
     */
    private bool $overlay = true;
    private string $cell_reference = '';
    private ?Font $font = null;
    /**
     * Create a new Title.
     *
     * @param array<RichText|string>|RichText|string $caption
     */
    public function __construct(
        /**
         * Title Caption.
         */
        private array|Rich_Text|string $caption = '',
        /**
         * Title Layout.
         */
        private ?Layout $layout = null,
        bool $overlay = false
    )
    {
        $this->set_overlay($overlay);
    }
    /**
     * Get caption.
     *
     * @return array<RichText|string>|RichText|string
     */
    public function get_caption(): array|Rich_Text|string
    {
        return $this->caption;
    }
    public function get_caption_text(?Spreadsheet $spreadsheet = null): string
    {
        if ($spreadsheet !== null) {
            $caption = $this->get_calculated_title($spreadsheet);
            if ($caption !== null) {
                return $caption;
            }
        }
        $caption = $this->caption;
        if (is_string($caption)) {
            return $caption;
        }
        if ($caption instanceof Rich_Text) {
            return $caption->get_plain_text();
        }
        $ret_val = '';
        foreach ($caption as $textx) {
            /** @var RichText|string $text */
            $text = $textx;
            if ($text instanceof Rich_Text) {
                $ret_val .= $text->get_plain_text();
            } else {
                $ret_val .= $text;
            }
        }
        return $ret_val;
    }
    /**
     * Set caption.
     *
     * @param array<RichText|string>|RichText|string $caption
     *
     * @return $this
     */
    public function set_caption(array|Rich_Text|string $caption): static
    {
        $this->caption = $caption;
        return $this;
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
    public function set_overlay(bool $overlay): self
    {
        $this->overlay = $overlay;
        return $this;
    }
    public function get_layout(): ?Layout
    {
        return $this->layout;
    }
    public function set_cell_reference(string $cell_reference): self
    {
        $this->cell_reference = $cell_reference;
        return $this;
    }
    public function get_cell_reference(): string
    {
        return $this->cell_reference;
    }
    public function get_calculated_title(?Spreadsheet $spreadsheet): ?string
    {
        preg_match(self::TITLE_CELL_REFERENCE, $this->cell_reference, $matches);
        if (count($matches) === 0 || $spreadsheet === null) {
            return null;
        }
        $sheet_name = preg_replace("/^'(.*)'\$/", '$1', $matches[1]) ?? '';
        return $spreadsheet->get_sheet_by_name($sheet_name)?->get_cell($matches[2] . $matches[3])?->get_formatted_value();
    }
    public function get_font(): ?Font
    {
        return $this->font;
    }
    public function set_font(?Font $font): self
    {
        $this->font = $font;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->layout = $this->layout === null ? null : clone $this->layout;
        $this->font = $this->font === null ? null : clone $this->font;
        if (is_array($this->caption)) {
            $captions = [];
            foreach ($this->caption as $caption) {
                $captions[] = is_object($caption) ? clone $caption : $caption;
            }
            $this->caption = $captions;
        } else {
            $this->caption = is_object($this->caption) ? clone $this->caption : $this->caption;
        }
    }
}