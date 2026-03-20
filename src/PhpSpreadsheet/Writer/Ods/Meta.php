<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Php_Office\Php_Spreadsheet\Document\Properties;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
class Meta extends Writer_Part
{
    /**
     * Write meta.xml to XML format.
     *
     * @return string XML Output
     */
    public function write(): string
    {
        $spreadsheet = $this->get_parent_writer()->get_spreadsheet();
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8');
        // Meta
        $obj_writer->start_element('office:document-meta');
        $obj_writer->write_attribute('xmlns:office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $obj_writer->write_attribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $obj_writer->write_attribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $obj_writer->write_attribute('xmlns:meta', 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0');
        $obj_writer->write_attribute('xmlns:ooo', 'http://openoffice.org/2004/office');
        $obj_writer->write_attribute('xmlns:grddl', 'http://www.w3.org/2003/g/data-view#');
        $obj_writer->write_attribute('office:version', '1.2');
        $obj_writer->start_element('office:meta');
        $obj_writer->write_element('meta:initial-creator', $spreadsheet->get_properties()->get_creator());
        $obj_writer->write_element('dc:creator', $spreadsheet->get_properties()->get_creator());
        $created = $spreadsheet->get_properties()->get_created();
        $date = Date::date_time_from_timestamp("{$created}");
        $date->set_time_zone(Date::get_default_or_local_time_zone());
        $obj_writer->write_element('meta:creation-date', $date->format(DATE_W3C));
        $created = $spreadsheet->get_properties()->get_modified();
        $date = Date::date_time_from_timestamp("{$created}");
        $date->set_time_zone(Date::get_default_or_local_time_zone());
        $obj_writer->write_element('dc:date', $date->format(DATE_W3C));
        $obj_writer->write_element('dc:title', $spreadsheet->get_properties()->get_title());
        $obj_writer->write_element('dc:description', $spreadsheet->get_properties()->get_description());
        $obj_writer->write_element('dc:subject', $spreadsheet->get_properties()->get_subject());
        $obj_writer->write_element('meta:keyword', $spreadsheet->get_properties()->get_keywords());
        // Don't know if this changed over time, but the keywords are all
        //  in a single declaration now.
        //$keywords = explode(' ', $spreadsheet->getProperties()->getKeywords());
        //foreach ($keywords as $keyword) {
        //    $objWriter->writeElement('meta:keyword', $keyword);
        //}
        //<meta:document-statistic meta:table-count="XXX" meta:cell-count="XXX" meta:object-count="XXX"/>
        $obj_writer->start_element('meta:user-defined');
        $obj_writer->write_attribute('meta:name', 'Company');
        $obj_writer->write_raw_data($spreadsheet->get_properties()->get_company());
        $obj_writer->end_element();
        $obj_writer->start_element('meta:user-defined');
        $obj_writer->write_attribute('meta:name', 'category');
        $obj_writer->write_raw_data($spreadsheet->get_properties()->get_category());
        $obj_writer->end_element();
        self::write_doc_props_custom($obj_writer, $spreadsheet);
        $obj_writer->end_element();
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    private static function write_doc_props_custom(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        $custom_property_list = $spreadsheet->get_properties()->get_custom_properties();
        foreach ($custom_property_list as $custom_property) {
            $property_value = $spreadsheet->get_properties()->get_custom_property_value($custom_property);
            $property_type = $spreadsheet->get_properties()->get_custom_property_type($custom_property);
            $obj_writer->start_element('meta:user-defined');
            $obj_writer->write_attribute('meta:name', $custom_property);
            switch ($property_type) {
                case Properties::PROPERTY_TYPE_INTEGER:
                case Properties::PROPERTY_TYPE_FLOAT:
                    $obj_writer->write_attribute('meta:value-type', 'float');
                    $obj_writer->write_raw_data((string) $property_value);
                    break;
                case Properties::PROPERTY_TYPE_BOOLEAN:
                    $obj_writer->write_attribute('meta:value-type', 'boolean');
                    $obj_writer->write_raw_data($property_value ? 'true' : 'false');
                    break;
                case Properties::PROPERTY_TYPE_DATE:
                    $obj_writer->write_attribute('meta:value-type', 'date');
                    $dtobj = Date::date_time_from_timestamp((string) ($property_value ?? 0));
                    $obj_writer->write_raw_data($dtobj->format(DATE_W3C));
                    break;
                default:
                    $obj_writer->write_raw_data((string) $property_value);
                    break;
            }
            $obj_writer->end_element();
        }
    }
}