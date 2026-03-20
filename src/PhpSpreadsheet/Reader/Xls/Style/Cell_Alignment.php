<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Alignment;
class Cell_Alignment
{
    /**
     * @var array<int, string>
     */
    protected static array $horizontal_alignment_map = [0 => Alignment::HORIZONTAL_GENERAL, 1 => Alignment::HORIZONTAL_LEFT, 2 => Alignment::HORIZONTAL_CENTER, 3 => Alignment::HORIZONTAL_RIGHT, 4 => Alignment::HORIZONTAL_FILL, 5 => Alignment::HORIZONTAL_JUSTIFY, 6 => Alignment::HORIZONTAL_CENTER_CONTINUOUS];
    /**
     * @var array<int, string>
     */
    protected static array $vertical_alignment_map = [0 => Alignment::VERTICAL_TOP, 1 => Alignment::VERTICAL_CENTER, 2 => Alignment::VERTICAL_BOTTOM, 3 => Alignment::VERTICAL_JUSTIFY];
    public static function horizontal(Alignment $alignment, int $horizontal): void
    {
        if (array_key_exists($horizontal, self::$horizontal_alignment_map)) {
            $alignment->set_horizontal(self::$horizontal_alignment_map[$horizontal]);
        }
    }
    public static function vertical(Alignment $alignment, int $vertical): void
    {
        if (array_key_exists($vertical, self::$vertical_alignment_map)) {
            $alignment->set_vertical(self::$vertical_alignment_map[$vertical]);
        }
    }
    public static function wrap(Alignment $alignment, int $wrap): void
    {
        $alignment->set_wrap_text((bool) $wrap);
    }
}