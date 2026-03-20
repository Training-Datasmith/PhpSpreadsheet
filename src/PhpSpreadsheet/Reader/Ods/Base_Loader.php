<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Dom_Element;
use Php_Office\Php_Spreadsheet\Spreadsheet;
abstract class Base_Loader
{
    public function __construct(protected Spreadsheet $spreadsheet, protected string $table_ns)
    {
    }
    abstract public function read(Dom_Element $workbook_data): void;
}