<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Font;
class Cell_Font
{
    public static function escapement(Font $font, int $escapement): void
    {
        switch ($escapement) {
            case 0x1:
                $font->set_superscript(true);
                break;
            case 0x2:
                $font->set_subscript(true);
                break;
        }
    }
    /**
     * @var array<int, string>
     */
    protected static array $underline_map = [0x1 => Font::UNDERLINE_SINGLE, 0x2 => Font::UNDERLINE_DOUBLE, 0x21 => Font::UNDERLINE_SINGLEACCOUNTING, 0x22 => Font::UNDERLINE_DOUBLEACCOUNTING];
    public static function underline(Font $font, int $underline): void
    {
        if (array_key_exists($underline, self::$underline_map)) {
            $font->set_underline(self::$underline_map[$underline]);
        }
    }
}