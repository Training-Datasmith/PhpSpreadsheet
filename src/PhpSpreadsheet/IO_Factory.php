<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Reader\I_Reader;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Writer\I_Writer;
/**
 * Factory to create readers and writers easily.
 *
 * It is not required to use this class, but it should make it easier to read and write files.
 * Especially for reading files with an unknown format.
 */
abstract class Io_Factory
{
    public const READER_XLSX = 'Xlsx';
    public const READER_XLS = 'Xls';
    public const READER_XML = 'Xml';
    public const READER_ODS = 'Ods';
    public const READER_SYLK = 'Slk';
    public const READER_SLK = 'Slk';
    public const READER_GNUMERIC = 'Gnumeric';
    public const READER_HTML = 'Html';
    public const READER_CSV = 'Csv';
    public const WRITER_XLSX = 'Xlsx';
    public const WRITER_XLS = 'Xls';
    public const WRITER_ODS = 'Ods';
    public const WRITER_CSV = 'Csv';
    public const WRITER_HTML = 'Html';
    /** @var array<string, class-string<IReader>> */
    private static array $readers = [self::READER_XLSX => Reader\Xlsx::class, self::READER_XLS => Reader\Xls::class, self::READER_XML => Reader\Xml::class, self::READER_ODS => Reader\Ods::class, self::READER_SLK => Reader\Slk::class, self::READER_GNUMERIC => Reader\Gnumeric::class, self::READER_HTML => Reader\Html::class, self::READER_CSV => Reader\Csv::class];
    /** @var array<string, class-string<IWriter>> */
    private static array $writers = [self::WRITER_XLS => Writer\Xls::class, self::WRITER_XLSX => Writer\Xlsx::class, self::WRITER_ODS => Writer\Ods::class, self::WRITER_CSV => Writer\Csv::class, self::WRITER_HTML => Writer\Html::class, 'Tcpdf' => Writer\Pdf\Tcpdf::class, 'Dompdf' => Writer\Pdf\Dompdf::class, 'Mpdf' => Writer\Pdf\Mpdf::class];
    /** @internal */
    public static function restore_default_readers_and_writers(): void
    {
        self::$readers = [self::READER_XLSX => Reader\Xlsx::class, self::READER_XLS => Reader\Xls::class, self::READER_XML => Reader\Xml::class, self::READER_ODS => Reader\Ods::class, self::READER_SLK => Reader\Slk::class, self::READER_GNUMERIC => Reader\Gnumeric::class, self::READER_HTML => Reader\Html::class, self::READER_CSV => Reader\Csv::class];
        self::$writers = [self::WRITER_XLS => Writer\Xls::class, self::WRITER_XLSX => Writer\Xlsx::class, self::WRITER_ODS => Writer\Ods::class, self::WRITER_CSV => Writer\Csv::class, self::WRITER_HTML => Writer\Html::class, 'Tcpdf' => Writer\Pdf\Tcpdf::class, 'Dompdf' => Writer\Pdf\Dompdf::class, 'Mpdf' => Writer\Pdf\Mpdf::class];
    }
    /**
     * Create Writer\IWriter.
     */
    public static function create_writer(Spreadsheet $spreadsheet, string $writer_type): I_Writer
    {
        /** @var class-string<IWriter> */
        $class_name = $writer_type;
        if (!in_array($writer_type, self::$writers, true)) {
            if (!isset(self::$writers[$writer_type])) {
                throw new Writer\Exception("No writer found for type {$writer_type}");
            }
            // Instantiate writer
            $class_name = self::$writers[$writer_type];
        }
        return new $class_name($spreadsheet);
    }
    /**
     * Create IReader.
     */
    public static function create_reader(string $reader_type): I_Reader
    {
        /** @var class-string<IReader> */
        $class_name = $reader_type;
        if (!in_array($reader_type, self::$readers, true)) {
            if (!isset(self::$readers[$reader_type])) {
                throw new Reader\Exception("No reader found for type {$reader_type}");
            }
            // Instantiate reader
            $class_name = self::$readers[$reader_type];
        }
        return new $class_name();
    }
    /**
     * Loads Spreadsheet from file using automatic Reader\IReader resolution.
     *
     * @param string $filename The name of the spreadsheet file
     * @param int $flags the optional second parameter flags may be used to identify specific elements
     *                       that should be loaded, but which won't be loaded by default, using these values:
     *                            IReader::LOAD_WITH_CHARTS - Include any charts that are defined in the loaded file.
     *                            IReader::READ_DATA_ONLY - Read cell values only, not formatting or merge structure.
     *                            IReader::IGNORE_EMPTY_CELLS - Don't load empty cells into the model.
     * @param string[] $readers An array of Readers to use to identify the file type. By default, load() will try
     *                             all possible Readers until it finds a match; but this allows you to pass in a
     *                             list of Readers so it will only try the subset that you specify here.
     *                          Values in this list can be any of the constant values defined in the set
     *                                 IOFactory::READER_*.
     */
    public static function load(string $filename, int $flags = 0, ?array $readers = null): Spreadsheet
    {
        $reader = self::create_reader_for_file($filename, $readers);
        return $reader->load($filename, $flags);
    }
    /**
     * Identify file type using automatic IReader resolution.
     *
     * @param string[] $readers
     */
    public static function identify(string $filename, ?array $readers = null, bool $full_class_name = false): string
    {
        $reader = self::create_reader_for_file($filename, $readers);
        $class_name = $reader::class;
        if ($full_class_name) {
            return $class_name;
        }
        $class_type = explode('\\', $class_name);
        return array_pop($class_type);
    }
    /**
     * Create Reader\IReader for file using automatic IReader resolution.
     *
     * @param string[] $readers An array of Readers to use to identify the file type. By default, load() will try
     *                             all possible Readers until it finds a match; but this allows you to pass in a
     *                             list of Readers so it will only try the subset that you specify here.
     *                          Values in this list can be any of the constant values defined in the set
     *                                 IOFactory::READER_*.
     */
    public static function create_reader_for_file(string $filename, ?array $readers = null): I_Reader
    {
        File::assert_file($filename);
        $test_readers = self::$readers;
        if ($readers !== null) {
            $readers = array_map(strtoupper(...), $readers);
            $test_readers = array_filter(self::$readers, fn(string $reader_type): bool => in_array(strtoupper($reader_type), $readers, true), ARRAY_FILTER_USE_KEY);
        }
        // First, lucky guess by inspecting file extension
        $guessed_reader = self::get_reader_type_from_extension($filename);
        if ($guessed_reader !== null && array_key_exists($guessed_reader, $test_readers)) {
            $reader = self::create_reader($guessed_reader);
            // Let's see if we are lucky
            if ($reader->can_read($filename)) {
                return $reader;
            }
        }
        // If we reach here then "lucky guess" didn't give any result
        // Try walking through all the options in self::$readers (or the selected subset)
        foreach ($test_readers as $reader_type => $class) {
            //    Ignore our original guess, we know that won't work
            if ($reader_type !== $guessed_reader) {
                $reader = self::create_reader($reader_type);
                if ($reader->can_read($filename)) {
                    return $reader;
                }
            }
        }
        throw new Reader\Exception('Unable to identify a reader for this file');
    }
    /**
     * Guess a reader type from the file extension, if any.
     */
    private static function get_reader_type_from_extension(string $filename): ?string
    {
        $pathinfo = pathinfo($filename);
        if (!isset($pathinfo['extension'])) {
            return null;
        }
        return match (strtolower($pathinfo['extension'])) {
            // Excel (OfficeOpenXML) Spreadsheet
            'xlsx', 'xlsm', 'xltx', 'xltm' => 'Xlsx',
            // Excel (BIFF) Spreadsheet
            'xls', 'xlt' => 'Xls',
            // Open/Libre Offic Calc
            'ods', 'ots' => 'Ods',
            'slk' => 'Slk',
            // Excel 2003 SpreadSheetML
            'xml' => 'Xml',
            'gnumeric' => 'Gnumeric',
            'htm', 'html' => 'Html',
            // Do nothing
            // We must not try to use CSV reader since it loads
            // all files including Excel files etc.
            'csv' => null,
            default => null,
        };
    }
    /**
     * Register a writer with its type and class name.
     *
     * @param class-string<IWriter> $writerClass
     */
    public static function register_writer(string $writer_type, string $writer_class): void
    {
        // We want phpstan to validate caller, but still need this test
        if (!is_a($writer_class, I_Writer::class, true)) {
            //* @phpstan-ignore-line
            throw new Writer\Exception('Registered writers must implement ' . I_Writer::class);
        }
        self::$writers[$writer_type] = $writer_class;
    }
    /**
     * Register a reader with its type and class name.
     *
     * @param class-string<IReader> $readerClass
     */
    public static function register_reader(string $reader_type, string $reader_class): void
    {
        // We want phpstan to validate caller, but still need this test
        if (!is_a($reader_class, I_Reader::class, true)) {
            //* @phpstan-ignore-line
            throw new Reader\Exception('Registered readers must implement ' . I_Reader::class);
        }
        self::$readers[$reader_type] = $reader_class;
    }
}