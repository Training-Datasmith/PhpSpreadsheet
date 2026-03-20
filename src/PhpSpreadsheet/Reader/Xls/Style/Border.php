<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Border as StyleBorder;
class Border
{
    /**
     * @var array<int, string>
     */
    protected static array $border_style_map = [0x0 => Style_Border::BORDER_NONE, 0x1 => Style_Border::BORDER_THIN, 0x2 => Style_Border::BORDER_MEDIUM, 0x3 => Style_Border::BORDER_DASHED, 0x4 => Style_Border::BORDER_DOTTED, 0x5 => Style_Border::BORDER_THICK, 0x6 => Style_Border::BORDER_DOUBLE, 0x7 => Style_Border::BORDER_HAIR, 0x8 => Style_Border::BORDER_MEDIUMDASHED, 0x9 => Style_Border::BORDER_DASHDOT, 0xa => Style_Border::BORDER_MEDIUMDASHDOT, 0xb => Style_Border::BORDER_DASHDOTDOT, 0xc => Style_Border::BORDER_MEDIUMDASHDOTDOT, 0xd => Style_Border::BORDER_SLANTDASHDOT];
    public static function lookup(int $index): string
    {
        return self::$border_style_map[$index] ?? Style_Border::BORDER_NONE;
    }
}