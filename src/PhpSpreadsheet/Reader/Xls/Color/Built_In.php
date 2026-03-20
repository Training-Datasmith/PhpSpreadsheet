<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls\Color;

class Built_In
{
    private const BUILTIN_COLOR_MAP = [
        0x0 => '000000',
        0x1 => 'FFFFFF',
        0x2 => 'FF0000',
        0x3 => '00FF00',
        0x4 => '0000FF',
        0x5 => 'FFFF00',
        0x6 => 'FF00FF',
        0x7 => '00FFFF',
        0x40 => '000000',
        // system window text color
        0x41 => 'FFFFFF',
    ];
    /**
     * Map built-in color to RGB value.
     *
     * @param int $color Indexed color
     *
     * @return array{rgb: string}
     */
    public static function lookup(int $color): array
    {
        return ['rgb' => self::BUILTIN_COLOR_MAP[$color] ?? '000000'];
    }
}