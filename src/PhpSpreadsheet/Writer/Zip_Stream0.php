<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Zip_Stream\Option\Archive;
use Zip_Stream\Zip_Stream;
class Zip_Stream0
{
    /**
     * @param resource $fileHandle
     */
    public static function new_zip_stream($file_handle): Zip_Stream
    {
        return class_exists(Archive::class) ? Zip_Stream2::new_zip_stream($file_handle) : Zip_Stream3::new_zip_stream($file_handle);
    }
}