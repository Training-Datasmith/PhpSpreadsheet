<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Rich_Text;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Style\Font;
class Run extends Text_Element implements I_Text_Element
{
    /**
     * Font.
     */
    private ?Font $font;
    /**
     * Create a new Run instance.
     *
     * @param string $text Text
     */
    public function __construct(string $text = '')
    {
        parent::__construct($text);
        // Initialise variables
        $this->font = new Font();
    }
    /**
     * Get font.
     */
    public function get_font(): ?Font
    {
        return $this->font;
    }
    public function get_font_or_throw(): Font
    {
        if ($this->font === null) {
            throw new Spreadsheet_Exception('unexpected null font');
        }
        return $this->font;
    }
    /**
     * Set font.
     *
     * @param ?Font $font Font
     *
     * @return $this
     */
    public function set_font(?Font $font = null): static
    {
        $this->font = $font;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->get_text() . ($this->font === null ? '' : $this->font->get_hash_code()) . self::class);
    }
}