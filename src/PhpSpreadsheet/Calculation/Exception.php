<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Exception extends Php_Spreadsheet_Exception
{
    public const CALCULATION_ENGINE_PUSH_TO_STACK = 1;
    /**
     * Error handler callback.
     */
    public static function error_handler_callback(int $code, string $string, string $file, int $line): void
    {
        $e = new self($string, $code);
        $e->line = $line;
        $e->file = $file;
        throw $e;
    }
}