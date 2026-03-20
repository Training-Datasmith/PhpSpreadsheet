<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

class Error_Code
{
    /**
     * @var array<string, int>
     */
    protected static array $error_code_map = ['#NULL!' => 0x0, '#DIV/0!' => 0x7, '#VALUE!' => 0xf, '#REF!' => 0x17, '#NAME?' => 0x1d, '#NUM!' => 0x24, '#N/A' => 0x2a];
    public static function error(string $error_code): int
    {
        return self::$error_code_map[$error_code] ?? 0;
    }
}