<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls\Style;

use Php_Office\Php_Spreadsheet\Style\Alignment;
class Cell_Alignment
{
    /**
     * @var array<string, int>
     */
    private static array $horizontal_map = [Alignment::HORIZONTAL_GENERAL => 0, Alignment::HORIZONTAL_LEFT => 1, Alignment::HORIZONTAL_RIGHT => 3, Alignment::HORIZONTAL_CENTER => 2, Alignment::HORIZONTAL_CENTER_CONTINUOUS => 6, Alignment::HORIZONTAL_JUSTIFY => 5];
    /**
     * @var array<string, int>
     */
    private static array $vertical_map = [Alignment::VERTICAL_BOTTOM => 2, Alignment::VERTICAL_TOP => 0, Alignment::VERTICAL_CENTER => 1, Alignment::VERTICAL_JUSTIFY => 3];
    public static function horizontal(Alignment $alignment): int
    {
        $horizontal_alignment = $alignment->get_horizontal();
        if (is_string($horizontal_alignment) && array_key_exists($horizontal_alignment, self::$horizontal_map)) {
            return self::$horizontal_map[$horizontal_alignment];
        }
        return self::$horizontal_map[Alignment::HORIZONTAL_GENERAL];
    }
    public static function wrap(Alignment $alignment): int
    {
        $wrap = $alignment->get_wrap_text();
        return $wrap === true ? 1 : 0;
    }
    public static function vertical(Alignment $alignment): int
    {
        $vertical_alignment = $alignment->get_vertical();
        if (is_string($vertical_alignment) && array_key_exists($vertical_alignment, self::$vertical_map)) {
            return self::$vertical_map[$vertical_alignment];
        }
        return self::$vertical_map[Alignment::VERTICAL_BOTTOM];
    }
}