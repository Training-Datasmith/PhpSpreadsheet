<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing;
class Rich_Data_Drawing
{
    /** @var Drawing[] */
    private array $drawings = [];
    /**
     * Generate all Rich Data XML files.
     *
     * @return array<string,string> [path => XML content]
     */
    public function generate_files(Spreadsheet $spreadsheet): array
    {
        $worksheet_count = $spreadsheet->get_sheet_count();
        $index = 0;
        for ($i = 0; $i < $worksheet_count; ++$i) {
            $worksheet = $spreadsheet->get_sheet($i);
            $iterator = $worksheet->get_in_cell_drawing_collection()->getIterator();
            while ($iterator->valid()) {
                /** @var Drawing $pDrawing */
                $p_drawing = $iterator->current();
                $indexed_filename = $p_drawing->get_indexed_filename();
                if (!isset($this->drawings[$indexed_filename])) {
                    $p_drawing->set_index(++$index);
                    $this->drawings[$indexed_filename] = $p_drawing;
                } else {
                    $p_drawing->set_index($this->drawings[$indexed_filename]->get_index());
                }
                $iterator->next();
            }
        }
        return count($this->drawings) === 0 ? [] : ['xl/richData/rdrichvalue.xml' => $this->write_rdrichvalue_xml(), 'xl/richData/rdrichvaluestructure.xml' => $this->write_rdrichvaluestructure_xml(), 'xl/richData/rdRichValueTypes.xml' => $this->write_rd_rich_value_types_xml(), 'xl/richData/richValueRel.xml' => $this->write_rich_value_rel_xml(), 'xl/richData/_rels/richValueRel.xml.rels' => $this->write_rich_value_rel_rels_xml()];
    }
    /**
     * @return Drawing[]
     */
    public function get_drawings(): array
    {
        return $this->drawings;
    }
    private function write_rdrichvalue_xml(): string
    {
        $xml = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        $xml->start_document('1.0', 'UTF-8', 'yes');
        $xml->start_element('rvData');
        $xml->write_attribute('xmlns', 'http://schemas.microsoft.com/office/spreadsheetml/2017/richdata');
        $xml->write_attribute('count', (string) count($this->drawings));
        $index = 0;
        foreach ($this->drawings as $drawing) {
            $xml->start_element('rv');
            $xml->write_attribute('s', '0');
            $xml->write_element('v', (string) $index++);
            $xml->write_element('v', '5');
            $xml->end_element();
            // rv
        }
        $xml->end_element();
        // rvData
        return $xml->get_data();
    }
    private function write_rdrichvaluestructure_xml(): string
    {
        $xml = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        $xml->start_document('1.0', 'UTF-8', 'yes');
        $xml->start_element('rvStructures');
        $xml->write_attribute('xmlns', 'http://schemas.microsoft.com/office/spreadsheetml/2017/richdata');
        $xml->write_attribute('count', '1');
        $xml->start_element('s');
        $xml->write_attribute('t', '_localImage');
        $xml->start_element('k');
        $xml->write_attribute('n', '_rvRel:LocalImageIdentifier');
        $xml->write_attribute('t', 'i');
        $xml->end_element();
        $xml->start_element('k');
        $xml->write_attribute('n', 'CalcOrigin');
        $xml->write_attribute('t', 'i');
        $xml->end_element();
        $xml->end_element();
        // s
        $xml->end_element();
        // rvStructures
        return $xml->get_data();
    }
    private function write_rd_rich_value_types_xml(): string
    {
        $xml = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        $xml->start_document('1.0', 'UTF-8', 'yes');
        $xml->start_element('rvTypesInfo');
        $xml->write_attribute('xmlns', 'http://schemas.microsoft.com/office/spreadsheetml/2017/richdata2');
        $xml->write_attribute('xmlns:mc', 'http://schemas.openxmlformats.org/markup-compatibility/2006');
        $xml->write_attribute('mc:Ignorable', 'x');
        $xml->write_attribute('xmlns:x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->start_element('global');
        $xml->start_element('keyFlags');
        $keys = ['_Self', '_DisplayString', '_Flags', '_Format', '_SubLabel', '_Attribution', '_Icon', '_Display', '_CanonicalPropertyNames', '_ClassificationId'];
        foreach ($keys as $key) {
            $xml->start_element('key');
            $xml->write_attribute('name', $key);
            $xml->start_element('flag');
            $xml->write_attribute('name', 'ExcludeFromCalcComparison');
            $xml->write_attribute('value', '1');
            if ($key === '_Self') {
                $xml->start_element('flag');
                $xml->write_attribute('name', 'ExcludeFromFile');
                $xml->write_attribute('value', '1');
                $xml->end_element();
            }
            $xml->end_element();
            // flag
            $xml->end_element();
            // key
        }
        $xml->end_element();
        // keyFlags
        $xml->end_element();
        // global
        $xml->end_element();
        // rvTypesInfo
        return $xml->get_data();
    }
    private function write_rich_value_rel_xml(): string
    {
        $xml = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        $xml->start_document('1.0', 'UTF-8', 'yes');
        $xml->start_element('richValueRels');
        $xml->write_attribute('xmlns', 'http://schemas.microsoft.com/office/spreadsheetml/2022/richvaluerel');
        $xml->write_attribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $index = 0;
        foreach ($this->drawings as $drawing) {
            $xml->start_element('rel');
            $xml->write_attribute('r:id', 'rId' . ++$index);
            $xml->end_element();
        }
        $xml->end_element();
        // richValueRels
        return $xml->get_data();
    }
    private function write_rich_value_rel_rels_xml(): string
    {
        $xml = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        $xml->start_document('1.0', 'UTF-8', 'yes');
        $xml->start_element('Relationships');
        $xml->write_attribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $index = 0;
        foreach ($this->drawings as $drawing) {
            $xml->start_element('Relationship');
            $xml->write_attribute('Id', 'rId' . ++$index);
            $xml->write_attribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
            $xml->write_attribute('Target', '../media/' . $drawing->get_indexed_filename());
            $xml->end_element();
        }
        $xml->end_element();
        // Relationships
        return $xml->get_data();
    }
}