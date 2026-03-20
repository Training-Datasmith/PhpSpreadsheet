<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
class Rels_Ribbon extends Writer_Part
{
    /**
     * Write relationships for additional objects of custom UI (ribbon).
     *
     * @return string XML Output
     */
    public function write_ribbon_relationships(Spreadsheet $spreadsheet): string
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
        $local_rels = $spreadsheet->get_ribbon_bin_objects('names');
        if (is_array($local_rels)) {
            foreach ($local_rels as $a_id => $a_target) {
                $obj_writer->start_element('Relationship');
                $obj_writer->write_attribute('Id', $a_id);
                $obj_writer->write_attribute('Type', Namespaces::IMAGE);
                /** @var string $aTarget */
                $obj_writer->write_attribute('Target', $a_target);
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
}