<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Rich_Text;

use Php_Office\Php_Spreadsheet\Style\Font;
interface I_Text_Element
{
    /**
     * Get text.
     */
    public function get_text(): string;
    /**
     * Set text.
     *
     * @param string $text Text
     *
     * @return $this
     */
    public function set_text(string $text): self;
    /**
     * Get font.
     */
    public function get_font(): ?Font;
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string;
}