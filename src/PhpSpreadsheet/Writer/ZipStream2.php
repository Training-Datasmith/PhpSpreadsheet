<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Zip_Stream\Option\Archive;
use Zip_Stream\Zip_Stream;
/**
 * Either ZipStream2 or ZipStream3, but not both, may be used.
 * For code coverage testing, it will always be ZipStream3.
 *
 * @codeCoverageIgnore
 */
class Zip_Stream2
{
    /**
     * @param resource $fileHandle
     */
    public static function new_zip_stream($file_handle): Zip_Stream
    {
        $options = new Archive();
        $options->set_enable_zip64(false);
        $options->set_output_stream($file_handle);
        return new Zip_Stream(null, $options);
    }
}