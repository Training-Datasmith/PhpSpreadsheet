<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Rich_Text;

use Php_Office\Php_Spreadsheet\Style\Font;
class Text_Element implements I_Text_Element
{
    /**
     * Create a new TextElement instance.
     *
     * @param string $text Text
     */
    public function __construct(private string $text = '')
    {
    }
    /**
     * Get text.
     *
     * @return string Text
     */
    public function get_text(): string
    {
        return $this->text;
    }
    /**
     * Set text.
     *
     * @param string $text Text
     *
     * @return $this
     */
    public function set_text(string $text): self
    {
        $this->text = $text;
        return $this;
    }
    /**
     * Get font. For this class, the return value is always null.
     */
    public function get_font(): ?Font
    {
        return null;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->text . self::class);
    }
}