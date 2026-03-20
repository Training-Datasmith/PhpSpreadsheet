<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
class Rels_Vba extends Writer_Part
{
    /**
     * Write relationships for a signed VBA Project.
     *
     * @return string XML Output
     */
    public function write_vba_relationships(): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        $obj_writer->start_element('Relationship');
        $obj_writer->write_attribute('Id', 'rId1');
        $obj_writer->write_attribute('Type', Namespaces::VBA_SIGNATURE);
        $obj_writer->write_attribute('Target', 'vbaProjectSignature.bin');
        $obj_writer->end_element();
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
}