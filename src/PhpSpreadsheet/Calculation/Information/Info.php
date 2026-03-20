<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Information;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Cell;
class Info
{
    /**
     * @internal
     */
    public static bool $info_supported = true;
    /**
     * INFO.
     *
     * Excel Function:
     *        =INFO(type_text)
     *
     * @param mixed $typeText String specifying the type of information to be returned
     * @param ?Cell $cell Cell from which spreadsheet information is retrieved
     *
     * @return int|string The requested information about the current operating environment
     */
    public static function get_info(mixed $type_text = '', ?Cell $cell = null): int|string
    {
        if (!self::$info_supported) {
            return Functions::DUMMY();
        }
        return match (is_string($type_text) ? strtolower($type_text) : $type_text) {
            'directory' => '/',
            'numfile' => $cell?->get_worksheet_or_null()?->get_parent()?->get_sheet_count() ?? 1,
            'origin' => '$A:$A$1',
            'osversion' => 'PHP ' . PHP_VERSION,
            'recalc' => 'Automatic',
            'release' => PHP_VERSION,
            'system' => 'PHP',
            'memavail', 'memused', 'totmem' => Excel_Error::NA(),
            default => Excel_Error::VALUE(),
        };
    }
}