<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

class Int_Or_Float
{
    /**
     * Help some functions with large results operate correctly on 32-bit,
     * by returning result as int when possible, float otherwise.
     */
    public static function evaluate(float|int $value): float|int
    {
        $i_value = (int) $value;
        return $value == $i_value ? $i_value : $value;
    }
}