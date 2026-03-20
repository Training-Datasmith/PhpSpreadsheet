<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Helper;

use Php_Office\Php_Spreadsheet\Chart\Chart;
use Php_Office\Php_Spreadsheet\Chart\Renderer\Mt_Jp_Graph_Renderer;
use Php_Office\Php_Spreadsheet\Io_Factory;
use Php_Office\Php_Spreadsheet\Settings;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Php_Office\Php_Spreadsheet\Writer\I_Writer;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use Recursive_Regex_Iterator;
use ReflectionClass;
use Regex_Iterator;
use RuntimeException;
use Throwable;
/**
 * Helper class to be used in sample code.
 */
class Sample
{
    /**
     * Returns whether we run on CLI or browser.
     */
    public function is_cli(): bool
    {
        return PHP_SAPI === 'cli';
    }
    /**
     * Return the filename currently being executed.
     */
    public function get_script_filename(): string
    {
        return basename(String_Helper::convert_to_string($_SERVER['SCRIPT_FILENAME']), '.php');
    }
    /**
     * Whether we are executing the index page.
     */
    public function is_index(): bool
    {
        return $this->get_script_filename() === 'index';
    }
    /**
     * Return the page title.
     */
    public function get_page_title(): string
    {
        return $this->is_index() ? 'PHPSpreadsheet' : $this->get_script_filename();
    }
    /**
     * Return the page heading.
     */
    public function get_page_heading(): string
    {
        return $this->is_index() ? '' : '<h1>' . str_replace('_', ' ', $this->get_script_filename()) . '</h1>';
    }
    /**
     * Returns an array of all known samples.
     *
     * @return string[][] [$name => $path]
     */
    public function get_samples(): array
    {
        // Populate samples
        $base_dir = realpath(__DIR__ . '/../../../samples');
        if ($base_dir === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('realpath returned false');
            // @codeCoverageIgnoreEnd
        }
        $directory = new Recursive_Directory_Iterator($base_dir);
        $iterator = new Recursive_Iterator_Iterator($directory);
        $regex = new Regex_Iterator($iterator, '/^.+\.php$/', Recursive_Regex_Iterator::GET_MATCH);
        $files = [];
        /** @var string[] $file */
        foreach ($regex as $file) {
            $file = str_replace(str_replace('\\', '/', $base_dir) . '/', '', str_replace('\\', '/', $file[0]));
            $info = pathinfo($file);
            $category = str_replace('_', ' ', $info['dirname'] ?? '');
            $name = str_replace('_', ' ', (string) preg_replace('/(|\.php)/', '', $info['filename']));
            if (!in_array($category, ['.', 'bootstrap', 'templates']) && $name !== 'Header') {
                if (!isset($files[$category])) {
                    $files[$category] = [];
                }
                $files[$category][$name] = $file;
            }
        }
        // Sort everything
        ksort($files);
        foreach ($files as &$f) {
            asort($f);
        }
        return $files;
    }
    /**
     * Write documents.
     *
     * @param string[] $writers
     */
    public function write(Spreadsheet $spreadsheet, string $filename, array $writers = ['Xlsx', 'Xls'], bool $with_charts = false, ?callable $writer_callback = null, bool $reset_active_sheet = true): void
    {
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        if ($reset_active_sheet) {
            $spreadsheet->set_active_sheet_index(0);
        }
        // Write documents
        foreach ($writers as $writer_type) {
            $path = $this->get_filename($filename, mb_strtolower($writer_type));
            if (preg_match('/(mpdf|tcpdf)$/', $path)) {
                $path .= '.pdf';
            }
            $writer = Io_Factory::create_writer($spreadsheet, $writer_type);
            $writer->set_include_charts($with_charts);
            if ($writer_callback !== null) {
                $writer_callback($writer);
            }
            $call_start_time = microtime(true);
            $writer->save($path);
            $this->log_write($writer, $path, $call_start_time);
            $this->add_download_link($path);
        }
        $this->log_ending_notes();
    }
    public function add_download_link(string $path): void
    {
        if ($this->is_cli() === false) {
            // @codeCoverageIgnoreStart
            echo '<a href="/download.php?type=' . pathinfo($path, PATHINFO_EXTENSION) . '&name=' . basename($path) . '">Download ' . basename($path) . '</a><br />';
            // @codeCoverageIgnoreEnd
        }
    }
    protected function is_dir_or_mkdir(string $folder): bool
    {
        return \is_dir($folder) || \mkdir($folder);
    }
    /**
     * Returns the temporary directory and make sure it exists.
     */
    public function get_temporary_folder(): string
    {
        $temp_folder = sys_get_temp_dir() . '/phpspreadsheet';
        if (!$this->is_dir_or_mkdir($temp_folder)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $temp_folder));
        }
        return $temp_folder;
    }
    /**
     * Returns the filename that should be used for sample output.
     */
    public function get_filename(string $filename, string $extension = 'xlsx'): string
    {
        $original_extension = pathinfo($filename, PATHINFO_EXTENSION);
        return $this->get_temporary_folder() . '/' . str_replace('.' . $original_extension, '.' . $extension, basename($filename));
    }
    /**
     * Return a random temporary file name.
     */
    public function get_temporary_filename(string $extension = 'xlsx'): string
    {
        $temporary_filename = tempnam($this->get_temporary_folder(), 'phpspreadsheet-');
        if ($temporary_filename === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('tempnam returned false');
            // @codeCoverageIgnoreEnd
        }
        unlink($temporary_filename);
        return $temporary_filename . '.' . $extension;
    }
    public function log(mixed $message): void
    {
        $eol = $this->is_cli() ? PHP_EOL : '<br />';
        echo ($this->is_cli() ? date('H:i:s ') : '') . String_Helper::convert_to_string($message) . $eol;
    }
    /**
     * Render chart as part of running chart samples in browser.
     * Charts are not rendered in unit tests, which are command line.
     *
     * @codeCoverageIgnore
     */
    public function render_chart(Chart $chart, string $file_name, ?Spreadsheet $spreadsheet = null): void
    {
        if ($this->is_cli() === true) {
            return;
        }
        Settings::set_chart_renderer(Mt_Jp_Graph_Renderer::class);
        $file_name = $this->get_filename($file_name, 'png');
        $title = $chart->get_title();
        $caption = null;
        if ($title !== null) {
            $calculated_title = $title->get_calculated_title($spreadsheet);
            if ($calculated_title !== null) {
                $caption = $title->get_caption();
                $title->set_caption($calculated_title);
            }
        }
        try {
            $chart->render($file_name);
            $this->log('Rendered image: ' . $file_name);
            $image_data = @file_get_contents($file_name);
            if ($image_data !== false) {
                echo '<div><img src="data:image/gif;base64,' . base64_encode($image_data) . '" /></div>';
            } else {
                $this->log('Unable to open chart' . PHP_EOL);
            }
        } catch (Throwable $e) {
            $this->log('Error rendering chart: ' . $e->get_message() . PHP_EOL);
        }
        if (isset($title, $caption)) {
            $title->set_caption($caption);
        }
        Settings::unset_chart_renderer();
    }
    public function titles(string $category, string $function_name, ?string $description = null): void
    {
        $this->log(sprintf('%s Functions:', $category));
        $description === null ? $this->log(sprintf('Function: %s()', rtrim($function_name, '()'))) : $this->log(sprintf('Function: %s() - %s.', rtrim($function_name, '()'), rtrim($description, '.')));
    }
    /** @param mixed[][] $matrix */
    public function display_grid(array $matrix, null|bool|Text_Grid_Right_Align $numbers_right = null): void
    {
        $renderer = new Text_Grid($matrix, $this->is_cli());
        if (is_bool($numbers_right)) {
            $numbers_right = $numbers_right ? Text_Grid_Right_Align::numeric : Text_Grid_Right_Align::none;
        }
        if ($numbers_right !== null) {
            $renderer->set_numbers_right($numbers_right);
        }
        echo $renderer->render();
    }
    public function log_calculation_result(Worksheet $worksheet, string $function_name, string $formula_cell, ?string $description_cell = null): void
    {
        if ($description_cell !== null) {
            $this->log($worksheet->get_cell($description_cell)->get_value_string());
        }
        $this->log($worksheet->get_cell($formula_cell)->get_value_string());
        $this->log(sprintf('%s() Result is ', $function_name) . $worksheet->get_cell($formula_cell)->get_calculated_value_string());
    }
    /**
     * Log ending notes.
     */
    public function log_ending_notes(): void
    {
        // Do not show execution time for index
        $this->log('Peak memory usage: ' . memory_get_peak_usage(true) / 1024 / 1024 . 'MB');
    }
    /**
     * Log a line about the write operation.
     */
    public function log_write(I_Writer $writer, string $path, float $call_start_time): void
    {
        $call_end_time = microtime(true);
        $call_time = $call_end_time - $call_start_time;
        $reflection = new ReflectionClass($writer);
        $format = $reflection->get_short_name();
        $code_path = $this->is_cli() ? $path : "<code>{$path}</code>";
        $message = "Write {$format} format to {$code_path}  in " . sprintf('%.4f', $call_time) . ' seconds';
        $this->log($message);
    }
    /**
     * Log a line about the read operation.
     */
    public function log_read(string $format, string $path, float $call_start_time): void
    {
        $call_end_time = microtime(true);
        $call_time = $call_end_time - $call_start_time;
        $message = "Read {$format} format from <code>{$path}</code>  in " . sprintf('%.4f', $call_time) . ' seconds';
        $this->log($message);
    }
}