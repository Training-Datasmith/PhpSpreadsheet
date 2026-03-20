<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
class Feature_Property_Bag extends Writer_Part
{
    public function write_feature_property_bag(Spreadsheet $spreadsheet): string
    {
        if (!$spreadsheet->get_uses_check_box_style()) {
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
        $obj_writer->start_element('FeaturePropertyBags');
        $obj_writer->write_attribute('xmlns', Namespaces::FEATURE_PROPERTY_BAG);
        $obj_writer->start_element('bag');
        $obj_writer->write_attribute('type', 'Checkbox');
        $obj_writer->end_element();
        // bag type=Checkbox
        $obj_writer->start_element('bag');
        $obj_writer->write_attribute('type', 'XFControls');
        $obj_writer->start_element('bagId');
        $obj_writer->write_attribute('k', 'CellControl');
        $obj_writer->text('0');
        $obj_writer->end_element();
        // bagid
        $obj_writer->end_element();
        // bag type=XFControls
        $obj_writer->start_element('bag');
        $obj_writer->write_attribute('type', 'XFComplement');
        $obj_writer->start_element('bagId');
        $obj_writer->write_attribute('k', 'XFControls');
        $obj_writer->text('1');
        $obj_writer->end_element();
        // bagid
        $obj_writer->end_element();
        // bag type=XFComplement
        $obj_writer->start_element('bag');
        $obj_writer->write_attribute('type', 'XFComplements');
        $obj_writer->write_attribute('extRef', 'XFComplementsMapperExtRef');
        $obj_writer->start_element('a');
        $obj_writer->write_attribute('k', 'MappedFeaturePropertyBags');
        $obj_writer->start_element('bagId');
        $obj_writer->text('2');
        $obj_writer->end_element();
        // bagid
        $obj_writer->end_element();
        // a
        $obj_writer->end_element();
        // bag type=XFComplements
        $obj_writer->end_element();
        // FeaturePropertyBags
        return $obj_writer->get_data();
    }
}