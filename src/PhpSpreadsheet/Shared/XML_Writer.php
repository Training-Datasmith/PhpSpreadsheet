<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
class Xml_Writer extends \Xml_Writer
{
    public static bool $debug_enabled = false;
    /** Temporary storage method */
    public const STORAGE_MEMORY = 1;
    public const STORAGE_DISK = 2;
    /**
     * Temporary filename.
     */
    private string $temp_file_name = '';
    /**
     * Create a new XMLWriter instance.
     *
     * @param int $temporaryStorage Temporary storage location
     * @param ?string $temporaryStorageFolder Temporary storage folder
     */
    public function __construct(int $temporary_storage = self::STORAGE_MEMORY, ?string $temporary_storage_folder = null)
    {
        // Open temporary storage
        if ($temporary_storage == self::STORAGE_MEMORY) {
            $this->open_memory();
        } else {
            // Create temporary filename
            if ($temporary_storage_folder === null) {
                $temporary_storage_folder = File::sys_get_temp_dir();
            }
            $this->temp_file_name = (string) @tempnam($temporary_storage_folder, 'xml');
            // Open storage
            if (empty($this->temp_file_name) || $this->open_uri($this->temp_file_name) === false) {
                // Fallback to memory...
                $this->open_memory();
                if ($this->temp_file_name != '') {
                    @unlink($this->temp_file_name);
                }
                $this->temp_file_name = '';
            }
        }
        // Set default values
        if (self::$debug_enabled) {
            $this->set_indent(true);
        }
    }
    /**
     * Destructor.
     */
    public function __destruct()
    {
        // Unlink temporary files
        // There is nothing reasonable to do if unlink fails.
        if ($this->temp_file_name != '') {
            @unlink($this->temp_file_name);
        }
    }
    /** @param mixed[] $data */
    public function __unserialize(array $data): void
    {
        $this->temp_file_name = '';
        throw new Spreadsheet_Exception('Unserialize not permitted');
    }
    /**
     * Get written data.
     */
    public function get_data(): string
    {
        if ($this->temp_file_name == '') {
            return $this->output_memory(true);
        }
        $this->flush();
        return file_get_contents($this->temp_file_name) ?: '';
    }
    /**
     * Wrapper method for writeRaw.
     *
     * @param null|string|string[] $rawTextData
     */
    public function write_raw_data($raw_text_data): bool
    {
        if (is_array($raw_text_data)) {
            $raw_text_data = implode("\n", $raw_text_data);
        }
        return $this->text($raw_text_data ?? '');
    }
}