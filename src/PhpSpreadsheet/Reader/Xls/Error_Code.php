<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

class Error_Code
{
    private const ERROR_CODE_MAP = [0x0 => '#NULL!', 0x7 => '#DIV/0!', 0xf => '#VALUE!', 0x17 => '#REF!', 0x1d => '#NAME?', 0x24 => '#NUM!', 0x2a => '#N/A'];
    /**
     * Map error code, e.g. '#N/A'.
     */
    public static function lookup(int $code): string|bool
    {
        return self::ERROR_CODE_MAP[$code] ?? false;
    }
}