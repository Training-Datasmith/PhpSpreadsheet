<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

interface I_Value_Binder
{
    /**
     * Bind value to a cell.
     *
     * @param Cell $cell Cell to bind value to
     * @param mixed $value Value to bind in cell
     */
    public function bind_value(Cell $cell, mixed $value): bool;
}