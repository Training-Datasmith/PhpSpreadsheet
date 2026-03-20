<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

/**
 * @template T
 */
interface Address_Range
{
    public const MAX_ROW = 1048576;
    public const MAX_COLUMN = 'XFD';
    public const MAX_COLUMN_INT = 16384;
    public const MAX_ROW_XLS_OLD = 16384;
    public const MAX_ROW_XLS = 65536;
    public const MAX_COLUMN_XLS = 'IV';
    public const MAX_COLUMN_INT_XLS = 256;
    /**
     * @return T
     */
    public function from(): mixed;
    /**
     * @return T
     */
    public function to(): mixed;
    public function __toString(): string;
}