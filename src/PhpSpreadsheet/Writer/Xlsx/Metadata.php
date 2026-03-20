<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
class Metadata extends Writer_Part
{
    /**
     * Write content types to XML format.
     *
     * @return string XML Output
     */
    public function write_metadata(int $rich_data_count = 0): string
    {
        if (!$this->get_parent_writer()->use_dynamic_arrays() && $rich_data_count === 0) {
            return '';
        }
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Types
        $obj_writer->start_element('metadata');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        $obj_writer->write_attribute('xmlns:xlrd', Namespaces::DYNAMIC_ARRAY_RICHDATA);
        if (!$this->get_parent_writer()->use_dynamic_arrays()) {
            $obj_writer->start_element('metadataTypes');
            $obj_writer->write_attribute('count', '1');
            $this->write_metadata_type($obj_writer, 'XLRICHVALUE', false);
            $obj_writer->end_element();
            // metadataTypes
            $this->write_future_metadata_xlrichvalue($obj_writer, $rich_data_count);
            $this->write_value_metadata($obj_writer, $rich_data_count);
        } else {
            $obj_writer->write_attribute('xmlns:xda', Namespaces::DYNAMIC_ARRAY);
            $obj_writer->start_element('metadataTypes');
            $obj_writer->write_attribute('count', '2');
            $this->write_metadata_type($obj_writer, 'XLDAPR');
            $this->write_metadata_type($obj_writer, 'XLRICHVALUE', false);
            $obj_writer->end_element();
            // metadataTypes
            $this->write_future_metadata_xldapr($obj_writer, 1);
            $this->write_future_metadata_xlrichvalue($obj_writer, $rich_data_count ?: 1);
            $this->write_cell_metadata($obj_writer, 1);
            $this->write_value_metadata($obj_writer, $rich_data_count === 0 ? 1 : $rich_data_count, 2);
        }
        $obj_writer->end_element();
        // metadata
        // Return
        return $obj_writer->get_data();
    }
    private function write_metadata_type(Xml_Writer $obj_writer, string $name, bool $cell_meta = true): void
    {
        $obj_writer->start_element('metadataType');
        $obj_writer->write_attribute('name', $name);
        $obj_writer->write_attribute('minSupportedVersion', '120000');
        $obj_writer->write_attribute('copy', '1');
        $obj_writer->write_attribute('pasteAll', '1');
        $obj_writer->write_attribute('pasteValues', '1');
        $obj_writer->write_attribute('merge', '1');
        $obj_writer->write_attribute('splitFirst', '1');
        $obj_writer->write_attribute('rowColShift', '1');
        $obj_writer->write_attribute('clearFormats', '1');
        $obj_writer->write_attribute('clearComments', '1');
        $obj_writer->write_attribute('assign', '1');
        $obj_writer->write_attribute('coerce', '1');
        if ($cell_meta) {
            $obj_writer->write_attribute('cellMeta', '1');
        }
        $obj_writer->end_element();
    }
    private function write_future_metadata_xldapr(Xml_Writer $obj_writer, int $count = 1): void
    {
        $obj_writer->start_element('futureMetadata');
        $obj_writer->write_attribute('name', 'XLDAPR');
        $obj_writer->write_attribute('count', (string) $count);
        for ($index = 0; $index < $count; ++$index) {
            $obj_writer->start_element('bk');
            $obj_writer->start_element('extLst');
            $obj_writer->start_element('ext');
            $obj_writer->write_attribute('uri', '{bdbb8cdc-fa1e-496e-a857-3c3f30c029c3}');
            $obj_writer->start_element('xda:dynamicArrayProperties');
            $obj_writer->write_attribute('fDynamic', '1');
            $obj_writer->write_attribute('fCollapsed', '0');
            $obj_writer->end_element();
            // xda:dynamicArrayProperties
            $obj_writer->end_element();
            // ext
            $obj_writer->end_element();
            // extLst
            $obj_writer->end_element();
            // bk
        }
        $obj_writer->end_element();
        // futureMetadata XLDAPR
    }
    private function write_future_metadata_xlrichvalue(Xml_Writer $obj_writer, int $count): void
    {
        $obj_writer->start_element('futureMetadata');
        $obj_writer->write_attribute('name', 'XLRICHVALUE');
        $obj_writer->write_attribute('count', (string) $count);
        for ($index = 0; $index < $count; ++$index) {
            $obj_writer->start_element('bk');
            $obj_writer->start_element('extLst');
            $obj_writer->start_element('ext');
            $obj_writer->write_attribute('uri', '{3e2802c4-a4d2-4d8b-9148-e3be6c30e623}');
            $obj_writer->start_element('xlrd:rvb');
            $obj_writer->write_attribute('i', (string) $index);
            $obj_writer->end_element();
            // xlrd:rvb
            $obj_writer->end_element();
            // ext
            $obj_writer->end_element();
            // extLst
            $obj_writer->end_element();
            // bk
        }
        $obj_writer->end_element();
        // futureMetadata XLRICHVALUE
    }
    private function write_cell_metadata(Xml_Writer $obj_writer, int $count = 1, int $t = 1): void
    {
        $obj_writer->start_element('cellMetadata');
        $obj_writer->write_attribute('count', (string) $count);
        for ($index = 0; $index < $count; ++$index) {
            $obj_writer->start_element('bk');
            $obj_writer->start_element('rc');
            $obj_writer->write_attribute('t', (string) $t);
            $obj_writer->write_attribute('v', (string) $index);
            $obj_writer->end_element();
            // rc
            $obj_writer->end_element();
            // bk
        }
        $obj_writer->end_element();
        // cellMetadata
    }
    private function write_value_metadata(Xml_Writer $obj_writer, int $count = 1, int $t = 1): void
    {
        $obj_writer->start_element('valueMetadata');
        $obj_writer->write_attribute('count', (string) $count);
        for ($index = 0; $index < $count; ++$index) {
            $obj_writer->start_element('bk');
            $obj_writer->start_element('rc');
            $obj_writer->write_attribute('t', (string) $t);
            $obj_writer->write_attribute('v', (string) $index);
            $obj_writer->end_element();
            // rc
            $obj_writer->end_element();
            // bk
        }
        $obj_writer->end_element();
        // valueMetadata
    }
}