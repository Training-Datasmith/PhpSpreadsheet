<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Zip_Stream\Zip_Stream;
class Zip_Stream3
{
    /**
     * @param resource $fileHandle
     */
    public static function new_zip_stream($file_handle): Zip_Stream
    {
        return new Zip_Stream(enableZip64: false, outputStream: $file_handle, sendHttpHeaders: false, defaultEnableZeroHeader: false);
    }
}