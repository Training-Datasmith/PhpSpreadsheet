<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

abstract class Base_Writer implements I_Writer
{
    /**
     * Write charts that are defined in the workbook?
     * Identifies whether the Writer should write definitions for any charts that exist in the PhpSpreadsheet object.
     */
    protected bool $include_charts = false;
    /**
     * Pre-calculate formulas
     * Forces PhpSpreadsheet to recalculate all formulae in a workbook when saving, so that the pre-calculated values are
     * immediately available to MS Excel or other office spreadsheet viewer when opening the file.
     */
    protected bool $pre_calculate_formulas = true;
    /**
     * Use disk caching where possible?
     */
    private bool $use_disk_caching = false;
    /**
     * Disk caching directory.
     */
    private string $disk_caching_directory = './';
    /**
     * @var resource
     */
    protected $file_handle;
    private bool $should_close_file;
    public function get_include_charts(): bool
    {
        return $this->include_charts;
    }
    public function set_include_charts(bool $include_charts): self
    {
        $this->include_charts = $include_charts;
        return $this;
    }
    public function get_pre_calculate_formulas(): bool
    {
        return $this->pre_calculate_formulas;
    }
    public function set_pre_calculate_formulas(bool $precalculate_formulas): self
    {
        $this->pre_calculate_formulas = $precalculate_formulas;
        return $this;
    }
    public function get_use_disk_caching(): bool
    {
        return $this->use_disk_caching;
    }
    public function set_use_disk_caching(bool $use_disk_cache, ?string $cache_directory = null): self
    {
        $this->use_disk_caching = $use_disk_cache;
        if ($cache_directory !== null) {
            if (is_dir($cache_directory)) {
                $this->disk_caching_directory = $cache_directory;
            } else {
                throw new Exception("Directory does not exist: {$cache_directory}");
            }
        }
        return $this;
    }
    public function get_disk_caching_directory(): string
    {
        return $this->disk_caching_directory;
    }
    protected function process_flags(int $flags): void
    {
        if ((bool) ($flags & self::SAVE_WITH_CHARTS) === true) {
            $this->set_include_charts(true);
        }
        if ((bool) ($flags & self::DISABLE_PRECALCULATE_FORMULAE) === true) {
            $this->set_pre_calculate_formulas(false);
        }
    }
    /**
     * Open file handle.
     *
     * @param resource|string $filename
     */
    public function open_file_handle($filename): void
    {
        if (!is_string($filename)) {
            $this->file_handle = $filename;
            $this->should_close_file = false;
            return;
        }
        $mode = 'wb';
        $scheme = parse_url($filename, PHP_URL_SCHEME);
        if ($scheme === 's3') {
            // @codeCoverageIgnoreStart
            $mode = 'w';
            // @codeCoverageIgnoreEnd
        }
        $file_handle = $filename ? fopen($filename, $mode) : false;
        if ($file_handle === false) {
            throw new Exception('Could not open file "' . $filename . '" for writing.');
        }
        $this->file_handle = $file_handle;
        $this->should_close_file = true;
    }
    protected function try_close(): bool
    {
        return fclose($this->file_handle);
    }
    /**
     * Close file handle only if we opened it ourselves.
     */
    protected function maybe_close_file_handle(): void
    {
        if ($this->should_close_file) {
            if (!$this->try_close()) {
                throw new Exception('Could not close file after writing.');
            }
        }
    }
}