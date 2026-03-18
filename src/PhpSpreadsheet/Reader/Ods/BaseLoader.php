<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Reader\Ods;

use DOMElement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

abstract class BaseLoader
{
    public function __construct(protected Spreadsheet $spreadsheet, protected string $tableNs)
    {
    }

    abstract public function read(DOMElement $workbookData): void;
}
