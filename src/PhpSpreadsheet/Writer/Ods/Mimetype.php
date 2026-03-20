<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

class Mimetype extends Writer_Part
{
    /**
     * Write mimetype to plain text format.
     *
     * @return string XML Output
     */
    public function write(): string
    {
        return 'application/vnd.oasis.opendocument.spreadsheet';
    }
}