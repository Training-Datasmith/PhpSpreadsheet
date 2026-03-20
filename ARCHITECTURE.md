# Architecture: PhpSpreadsheet

## Purpose

Pure PHP library for reading and writing spreadsheet files in multiple formats (XLSX, XLS, ODS, CSV, HTML, PDF, etc.). Fork of PHPExcel with improved architecture, type safety, and PHP 8 support.

## Directory Structure

```
src/PhpOffice/PhpSpreadsheet/
  Spreadsheet.php           Root object: collection of worksheets + document properties
  Worksheet/
    Worksheet.php           Sheet object: cells, rows, columns, styles, merges
    Drawing/                Embedded images and charts
  Cell/
    Cell.php                Individual cell: value, data type, formula, style reference
    DataType.php            Cell data type constants
    Coordinate.php          A1-notation ↔ row/column conversions
  Style/                    Cell styles: Font, Fill, Border, Alignment, NumberFormat
  Reader/
    IReader.php             Reader interface
    Xlsx/                   XLSX (Office Open XML) reader
    Xls/                    BIFF (binary XLS) reader
    Csv/, Ods/, Html/       Format-specific readers
  Writer/
    IWriter.php             Writer interface
    Xlsx/, Xls/, Csv/, Ods/, Html/, Pdf/  Format-specific writers
  Calculation/
    Calculation.php         Formula engine: evaluates Excel functions
    Functions/              PHP implementations of Excel worksheet functions
  Chart/                    Chart model and renderer
  IOFactory.php             Convenience factory: autodetect reader/writer by extension
  Settings.php              Global library settings (cache, locale, etc.)
```

## Key Design Decisions

- **Object graph model**: A `Spreadsheet` owns `Worksheet` objects which own `Cell` objects. Styles are stored as shared style objects referenced by hash to reduce memory.
- **Pluggable readers/writers**: Each format is an independent reader or writer class. `IOFactory` autodetects the format from file extension or MIME type.
- **Formula engine**: The calculation engine evaluates Excel formula strings using a recursive descent parser and a library of ~400 PHP function implementations.
- **Shared strings**: XLSX shared strings and styles are lazy-loaded and cached to handle large files efficiently.

## Extension Points

- Implement `IReader` or `IWriter` and register via `IOFactory::registerReader()` / `registerWriter()`.
- Implement `ICalculationEngine` to replace the built-in formula engine.
- Use `$spreadsheet->getProperties()` to set document metadata before writing.

## Dependency Flow

```
IOFactory::load('file.xlsx')
  -> autodetect format -> XlsxReader
  -> XlsxReader::load() -> Spreadsheet object
     (Worksheets -> Cells -> Styles)

$spreadsheet->getActiveSheet()->setCellValue('A1', 42)
IOFactory::createWriter($spreadsheet, 'Xlsx')->save('out.xlsx')
```
