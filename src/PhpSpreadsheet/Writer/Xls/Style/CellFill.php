<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Fill;
class Cell_Fill
{
    /**
     * @var array<string, int>
     */
    protected static array $fill_style_map = [
        Fill::FILL_NONE => 0x0,
        Fill::FILL_SOLID => 0x1,
        Fill::FILL_PATTERN_MEDIUMGRAY => 0x2,
        Fill::FILL_PATTERN_DARKGRAY => 0x3,
        Fill::FILL_PATTERN_LIGHTGRAY => 0x4,
        Fill::FILL_PATTERN_DARKHORIZONTAL => 0x5,
        Fill::FILL_PATTERN_DARKVERTICAL => 0x6,
        Fill::FILL_PATTERN_DARKDOWN => 0x7,
        Fill::FILL_PATTERN_DARKUP => 0x8,
        Fill::FILL_PATTERN_DARKGRID => 0x9,
        Fill::FILL_PATTERN_DARKTRELLIS => 0xa,
        Fill::FILL_PATTERN_LIGHTHORIZONTAL => 0xb,
        Fill::FILL_PATTERN_LIGHTVERTICAL => 0xc,
        Fill::FILL_PATTERN_LIGHTDOWN => 0xd,
        Fill::FILL_PATTERN_LIGHTUP => 0xe,
        Fill::FILL_PATTERN_LIGHTGRID => 0xf,
        Fill::FILL_PATTERN_LIGHTTRELLIS => 0x10,
        Fill::FILL_PATTERN_GRAY125 => 0x11,
        Fill::FILL_PATTERN_GRAY0625 => 0x12,
        Fill::FILL_GRADIENT_LINEAR => 0x0,
        // does not exist in BIFF8
        Fill::FILL_GRADIENT_PATH => 0x0,
    ];
    public static function style(Fill $fill): int
    {
        $fill_style = $fill->get_fill_type();
        if (is_string($fill_style) && array_key_exists($fill_style, self::$fill_style_map)) {
            return self::$fill_style_map[$fill_style];
        }
        return self::$fill_style_map[Fill::FILL_NONE];
    }
}