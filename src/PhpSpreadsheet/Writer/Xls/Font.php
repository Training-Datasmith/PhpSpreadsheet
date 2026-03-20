<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Font
{
    /**
     * Color index.
     */
    private int $color_index;
    /**
     * Constructor.
     */
    public function __construct(
        /**
         * Font.
         */
        private readonly \Php_Office\Php_Spreadsheet\Style\Font $font
    )
    {
        $this->color_index = 0x7fff;
    }
    /**
     * Set the color index.
     */
    public function set_color_index(int $color_index): void
    {
        $this->color_index = $color_index;
    }
    private static int $not_implemented = 0;
    /**
     * Get font record data.
     */
    public function write_font(): string
    {
        $font_outline = self::$not_implemented;
        $font_shadow = self::$not_implemented;
        $icv = $this->color_index;
        // Index to color palette
        if ($this->font->get_superscript()) {
            $sss = 1;
        } elseif ($this->font->get_subscript()) {
            $sss = 2;
        } else {
            $sss = 0;
        }
        $b_family = 0;
        // Font family
        $b_char_set = \Php_Office\Php_Spreadsheet\Shared\Font::get_charset_from_font_name((string) $this->font->get_name());
        // Character set
        $record = 0x31;
        // Record identifier
        $reserved = 0x0;
        // Reserved
        $grbit = 0x0;
        // Font attributes
        if ($this->font->get_italic()) {
            $grbit |= 0x2;
        }
        if ($this->font->get_strikethrough()) {
            $grbit |= 0x8;
        }
        if ($font_outline) {
            $grbit |= 0x10;
        }
        if ($font_shadow) {
            $grbit |= 0x20;
        }
        $data = pack(
            'vvvvvCCCC',
            // Fontsize (in twips)
            $this->font->get_size() * 20,
            $grbit,
            // Colour
            $icv,
            // Font weight
            self::map_bold($this->font->get_bold()),
            // Superscript/Subscript
            $sss,
            self::map_underline((string) $this->font->get_underline()),
            $b_family,
            $b_char_set,
            $reserved
        );
        $data .= String_Helper::utf8to_biff8unicode_short((string) $this->font->get_name());
        $length = strlen($data);
        $header = pack('vv', $record, $length);
        return $header . $data;
    }
    /**
     * Map to BIFF5-BIFF8 codes for bold.
     */
    private static function map_bold(?bool $bold): int
    {
        if ($bold === true) {
            return 0x2bc;
            //  700 = Bold font weight
        }
        return 0x190;
        //  400 = Normal font weight
    }
    /**
     * Map of BIFF2-BIFF8 codes for underline styles.
     *
     * @var int[]
     */
    private static array $map_underline = [\Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_NONE => 0x0, \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_SINGLE => 0x1, \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_DOUBLE => 0x2, \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_SINGLEACCOUNTING => 0x21, \Php_Office\Php_Spreadsheet\Style\Font::UNDERLINE_DOUBLEACCOUNTING => 0x22];
    /**
     * Map underline.
     */
    private static function map_underline(string $underline): int
    {
        return self::$map_underline[$underline] ?? 0x0;
    }
}