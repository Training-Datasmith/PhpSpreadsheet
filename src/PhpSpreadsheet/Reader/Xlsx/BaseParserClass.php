<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Stringable;
class Base_Parser_Class
{
    protected static function boolean(mixed $value): bool
    {
        if (is_object($value)) {
            $value = $value instanceof Stringable ? (string) $value : 'true';
        }
        if (is_numeric($value)) {
            return (bool) $value;
        }
        return $value === 'true' || $value === 'TRUE';
    }
}