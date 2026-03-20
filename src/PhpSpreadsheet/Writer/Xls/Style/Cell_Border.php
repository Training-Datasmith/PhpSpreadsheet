<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Border;
class Cell_Border
{
    /**
     * @var array<string, int>
     */
    protected static array $style_map = [Border::BORDER_NONE => 0x0, Border::BORDER_THIN => 0x1, Border::BORDER_MEDIUM => 0x2, Border::BORDER_DASHED => 0x3, Border::BORDER_DOTTED => 0x4, Border::BORDER_THICK => 0x5, Border::BORDER_DOUBLE => 0x6, Border::BORDER_HAIR => 0x7, Border::BORDER_MEDIUMDASHED => 0x8, Border::BORDER_DASHDOT => 0x9, Border::BORDER_MEDIUMDASHDOT => 0xa, Border::BORDER_DASHDOTDOT => 0xb, Border::BORDER_MEDIUMDASHDOTDOT => 0xc, Border::BORDER_SLANTDASHDOT => 0xd, Border::BORDER_OMIT => 0x0];
    public static function style(Border $border): int
    {
        $border_style = $border->get_border_style();
        if (array_key_exists($border_style, self::$style_map)) {
            return self::$style_map[$border_style];
        }
        return self::$style_map[Border::BORDER_NONE];
    }
}