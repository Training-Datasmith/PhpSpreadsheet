<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
use Php_Office\Php_Spreadsheet\Writer\Ods\Content;
use Php_Office\Php_Spreadsheet\Writer\Ods\Meta;
use Php_Office\Php_Spreadsheet\Writer\Ods\Meta_Inf;
use Php_Office\Php_Spreadsheet\Writer\Ods\Mimetype;
use Php_Office\Php_Spreadsheet\Writer\Ods\Settings;
use Php_Office\Php_Spreadsheet\Writer\Ods\Styles;
use Php_Office\Php_Spreadsheet\Writer\Ods\Thumbnails;
use Zip_Stream\Exception\OverflowException;
use Zip_Stream\Zip_Stream;
class Ods extends Base_Writer
{
    /**
     * Private PhpSpreadsheet.
     */
    private Spreadsheet $spread_sheet;
    private readonly Content $writer_part_content;
    private readonly Meta $writer_part_meta;
    private readonly Meta_Inf $writer_part_meta_inf;
    private readonly Mimetype $writer_part_mimetype;
    private readonly Settings $writer_part_settings;
    private readonly Styles $writer_part_styles;
    private readonly Thumbnails $writer_part_thumbnails;
    /**
     * Create a new Ods.
     */
    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->set_spreadsheet($spreadsheet);
        $this->writer_part_content = new Content($this);
        $this->writer_part_meta = new Meta($this);
        $this->writer_part_meta_inf = new Meta_Inf($this);
        $this->writer_part_mimetype = new Mimetype($this);
        $this->writer_part_settings = new Settings($this);
        $this->writer_part_styles = new Styles($this);
        $this->writer_part_thumbnails = new Thumbnails($this);
    }
    public function get_writer_part_content(): Content
    {
        return $this->writer_part_content;
    }
    public function get_writer_part_meta(): Meta
    {
        return $this->writer_part_meta;
    }
    public function get_writer_part_meta_inf(): Meta_Inf
    {
        return $this->writer_part_meta_inf;
    }
    public function get_writer_part_mimetype(): Mimetype
    {
        return $this->writer_part_mimetype;
    }
    public function get_writer_part_settings(): Settings
    {
        return $this->writer_part_settings;
    }
    public function get_writer_part_styles(): Styles
    {
        return $this->writer_part_styles;
    }
    public function get_writer_part_thumbnails(): Thumbnails
    {
        return $this->writer_part_thumbnails;
    }
    /** @param array<string, callable> $additionalNumberFormats */
    public function use_additional_number_formats(array $additional_number_formats): void
    {
        $this->writer_part_content->additional_number_formats = $additional_number_formats;
    }
    /**
     * Save PhpSpreadsheet to file.
     *
     * @param resource|string $filename
     */
    public function save($filename, int $flags = 0): void
    {
        $this->process_flags($flags);
        // garbage collect
        $this->spread_sheet->garbage_collect();
        $this->open_file_handle($filename);
        $zip = $this->create_zip();
        $zip->add_file('META-INF/manifest.xml', $this->get_writer_part_meta_inf()->write());
        $zip->add_file('Thumbnails/thumbnail.png', $this->get_writer_partthumbnails()->write());
        // Settings always need to be written before Content; Styles after Content
        $zip->add_file('settings.xml', $this->get_writer_partsettings()->write());
        $zip->add_file('content.xml', $this->get_writer_partcontent()->write());
        $zip->add_file('meta.xml', $this->get_writer_partmeta()->write());
        $zip->add_file('mimetype', $this->get_writer_partmimetype()->write());
        $zip->add_file('styles.xml', $this->get_writer_partstyles()->write());
        // Close file
        try {
            $zip->finish();
        } catch (OverflowException) {
            throw new Writer_Exception('Could not close resource.');
        }
        $this->maybe_close_file_handle();
    }
    /**
     * Create zip object.
     */
    private function create_zip(): Zip_Stream
    {
        // Try opening the ZIP file
        if (!is_resource($this->file_handle)) {
            throw new Writer_Exception('Could not open resource for writing.');
        }
        // Create new ZIP stream
        return Zip_Stream0::new_zip_stream($this->file_handle);
    }
    /**
     * Get Spreadsheet object.
     */
    public function get_spreadsheet(): Spreadsheet
    {
        return $this->spread_sheet;
    }
    /**
     * Set Spreadsheet object.
     *
     * @param Spreadsheet $spreadsheet PhpSpreadsheet object
     *
     * @return $this
     */
    public function set_spreadsheet(Spreadsheet $spreadsheet): static
    {
        $this->spread_sheet = $spreadsheet;
        return $this;
    }
}