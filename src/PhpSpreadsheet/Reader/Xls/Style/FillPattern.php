<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Fill;
class Fill_Pattern
{
    /**
     * @var array<int, string>
     */
    protected static array $fill_pattern_map = [0x0 => Fill::FILL_NONE, 0x1 => Fill::FILL_SOLID, 0x2 => Fill::FILL_PATTERN_MEDIUMGRAY, 0x3 => Fill::FILL_PATTERN_DARKGRAY, 0x4 => Fill::FILL_PATTERN_LIGHTGRAY, 0x5 => Fill::FILL_PATTERN_DARKHORIZONTAL, 0x6 => Fill::FILL_PATTERN_DARKVERTICAL, 0x7 => Fill::FILL_PATTERN_DARKDOWN, 0x8 => Fill::FILL_PATTERN_DARKUP, 0x9 => Fill::FILL_PATTERN_DARKGRID, 0xa => Fill::FILL_PATTERN_DARKTRELLIS, 0xb => Fill::FILL_PATTERN_LIGHTHORIZONTAL, 0xc => Fill::FILL_PATTERN_LIGHTVERTICAL, 0xd => Fill::FILL_PATTERN_LIGHTDOWN, 0xe => Fill::FILL_PATTERN_LIGHTUP, 0xf => Fill::FILL_PATTERN_LIGHTGRID, 0x10 => Fill::FILL_PATTERN_LIGHTTRELLIS, 0x11 => Fill::FILL_PATTERN_GRAY125, 0x12 => Fill::FILL_PATTERN_GRAY0625];
    /**
     * Get fill pattern from index
     * OpenOffice documentation: 2.5.12.
     */
    public static function lookup(int $index): string
    {
        return self::$fill_pattern_map[$index] ?? Fill::FILL_NONE;
    }
}