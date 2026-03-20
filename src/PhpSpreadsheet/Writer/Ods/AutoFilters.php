<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Auto_Filters
{
    public function __construct(private readonly Xml_Writer $obj_writer, private readonly Spreadsheet $spreadsheet)
    {
    }
    public function write(): void
    {
        $wrapper_written = false;
        $sheet_count = $this->spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            $worksheet = $this->spreadsheet->get_sheet($i);
            $autofilter = $worksheet->get_auto_filter();
            if (!empty($autofilter->get_range())) {
                if ($wrapper_written === false) {
                    $this->obj_writer->start_element('table:database-ranges');
                    $wrapper_written = true;
                }
                $this->obj_writer->start_element('table:database-range');
                $this->obj_writer->write_attribute('table:orientation', 'column');
                $this->obj_writer->write_attribute('table:display-filter-buttons', 'true');
                $this->obj_writer->write_attribute('table:target-range-address', $this->format_range($worksheet, $autofilter));
                $this->obj_writer->end_element();
            }
        }
        if ($wrapper_written === true) {
            $this->obj_writer->end_element();
        }
    }
    protected function format_range(Worksheet $worksheet, Auto_Filter $autofilter): string
    {
        $title = $worksheet->get_title();
        $range = $autofilter->get_range();
        return "'{$title}'.{$range}";
    }
}