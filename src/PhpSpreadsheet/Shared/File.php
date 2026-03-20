<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Zip_Archive;
class File
{
    /**
     * Use Temp or File Upload Temp for temporary files.
     */
    protected static bool $use_upload_temp_directory = false;
    /**
     * Set the flag indicating whether the File Upload Temp directory should be used for temporary files.
     */
    public static function set_use_upload_temp_directory(bool $use_upload_temp_dir): void
    {
        self::$use_upload_temp_directory = $use_upload_temp_dir;
    }
    /**
     * Get the flag indicating whether the File Upload Temp directory should be used for temporary files.
     */
    public static function get_use_upload_temp_directory(): bool
    {
        return self::$use_upload_temp_directory;
    }
    // https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT
    // Section 4.3.7
    // Looks like there might be endian-ness considerations
    private const ZIP_FIRST_4 = [
        "PK\x03\x04",
        // what it looks like on my system
        "\x04\x03KP",
    ];
    private static function validate_zip_first4(string $zip_file): bool
    {
        $contents = @file_get_contents($zip_file, false, null, 0, 4);
        return in_array($contents, self::ZIP_FIRST_4, true);
    }
    /**
     * Verify if a file exists.
     */
    public static function file_exists(string $filename): bool
    {
        // Sick construction, but it seems that
        // file_exists returns strange values when
        // doing the original file_exists on ZIP archives...
        if (strtolower(substr($filename, 0, 6)) == 'zip://') {
            // Open ZIP file and verify if the file exists
            $zip_file = substr($filename, 6, strrpos($filename, '#') - 6);
            $archive_file = substr($filename, strrpos($filename, '#') + 1);
            if (self::validate_zip_first4($zip_file)) {
                $zip = new Zip_Archive();
                $res = $zip->open($zip_file);
                if ($res === true) {
                    $return_value = $zip->get_from_name($archive_file) !== false;
                    $zip->close();
                    return $return_value;
                }
            }
            return false;
        }
        return file_exists($filename);
    }
    /**
     * Returns canonicalized absolute pathname, also for ZIP archives.
     */
    public static function realpath(string $filename): string
    {
        // Returnvalue
        $return_value = '';
        // Try using realpath()
        if (file_exists($filename)) {
            $return_value = realpath($filename) ?: '';
        }
        // Found something?
        if ($return_value === '') {
            $path_array = explode('/', $filename);
            while (in_array('..', $path_array) && $path_array[0] != '..') {
                $i_max = count($path_array);
                for ($i = 1; $i < $i_max; ++$i) {
                    if ($path_array[$i] == '..') {
                        array_splice($path_array, $i - 1, 2);
                        break;
                    }
                }
            }
            $return_value = implode('/', $path_array);
        }
        // Return
        return $return_value;
    }
    /**
     * Get the systems temporary directory.
     */
    public static function sys_get_temp_dir(): string
    {
        $path = sys_get_temp_dir();
        if (self::$use_upload_temp_directory) {
            //  use upload-directory when defined to allow running on environments having very restricted
            //      open_basedir configs
            if (ini_get('upload_tmp_dir') !== false) {
                if ($temp = ini_get('upload_tmp_dir')) {
                    if (file_exists($temp)) {
                        $path = $temp;
                    }
                }
            }
        }
        return realpath($path) ?: '';
    }
    public static function temporary_filename(): string
    {
        return tempnam(self::sys_get_temp_dir(), 'phpspreadsheet') ?: throw new Exception('Could not create temporary file');
    }
    /**
     * Assert that given path is an existing file and is readable, otherwise throw exception.
     */
    public static function assert_file(string $filename, string $zip_member = ''): void
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new Reader_Exception('File "' . $filename . '" does not exist or is not readable.');
        }
        if ($zip_member !== '') {
            $zipfile = "zip://{$filename}#{$zip_member}";
            if (!self::file_exists($zipfile)) {
                // Has the file been saved with Windoze directory separators rather than unix?
                $zipfile = "zip://{$filename}#" . str_replace('/', '\\', $zip_member);
                if (!self::file_exists($zipfile)) {
                    throw new Reader_Exception("Could not find zip member {$zipfile}");
                }
            }
        }
    }
    /**
     * Same as assertFile, except return true/false and don't throw Exception.
     */
    public static function test_file_no_throw(string $filename, ?string $zip_member = null): bool
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return false;
        }
        if ($zip_member === null) {
            return true;
        }
        // validate zip, but don't check specific member
        if ($zip_member === '') {
            return self::validate_zip_first4($filename);
        }
        $zipfile = "zip://{$filename}#{$zip_member}";
        if (self::file_exists($zipfile)) {
            return true;
        }
        // Has the file been saved with Windoze directory separators rather than unix?
        $zipfile = "zip://{$filename}#" . str_replace('/', '\\', $zip_member);
        return self::file_exists($zipfile);
    }
}