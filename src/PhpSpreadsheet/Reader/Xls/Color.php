<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Reader\Xls;

use PhpOffice\PhpSpreadsheet\Reader\Xls;

class Color
{
    /**
     * Read color.
     *
     * @param int $color Indexed color
     * @param string[][] $palette Color palette
     *
     * @return string[] RGB color value, example: ['rgb' => 'FF0000']
     */
    public static function map(int $color, array $palette, int $version): array
    {
        if ($color <= 0x07 || $color >= 0x40) {
            // special built-in color
            return Color\BuiltIn::lookup($color);
        }

        return $palette[$color - 8] ?? (($version === Xls::XLS_BIFF8) ? Color\BIFF8::lookup($color) : Color\BIFF5::lookup($color));
    }
}
