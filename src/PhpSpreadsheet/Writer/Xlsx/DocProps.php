<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Document\Properties;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
class Doc_Props extends Writer_Part
{
    /**
     * Write docProps/app.xml to XML format.
     *
     * @return string XML Output
     */
    public function write_doc_props_app(Spreadsheet $spreadsheet): string
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
        // Properties
        $obj_writer->start_element('Properties');
        $obj_writer->write_attribute('xmlns', Namespaces::EXTENDED_PROPERTIES);
        $obj_writer->write_attribute('xmlns:vt', Namespaces::PROPERTIES_VTYPES);
        // Application
        $obj_writer->write_element('Application', 'Microsoft Excel');
        // DocSecurity
        $obj_writer->write_element('DocSecurity', '0');
        // ScaleCrop
        $obj_writer->write_element('ScaleCrop', 'false');
        // HeadingPairs
        $obj_writer->start_element('HeadingPairs');
        // Vector
        $obj_writer->start_element('vt:vector');
        $obj_writer->write_attribute('size', '2');
        $obj_writer->write_attribute('baseType', 'variant');
        // Variant
        $obj_writer->start_element('vt:variant');
        $obj_writer->write_element('vt:lpstr', 'Worksheets');
        $obj_writer->end_element();
        // Variant
        $obj_writer->start_element('vt:variant');
        $obj_writer->write_element('vt:i4', (string) $spreadsheet->get_sheet_count());
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // TitlesOfParts
        $obj_writer->start_element('TitlesOfParts');
        // Vector
        $obj_writer->start_element('vt:vector');
        $obj_writer->write_attribute('size', (string) $spreadsheet->get_sheet_count());
        $obj_writer->write_attribute('baseType', 'lpstr');
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            $obj_writer->write_element('vt:lpstr', $spreadsheet->get_sheet($i)->get_title());
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Company
        $obj_writer->write_element('Company', $spreadsheet->get_properties()->get_company());
        // Company
        $obj_writer->write_element('Manager', $spreadsheet->get_properties()->get_manager());
        // LinksUpToDate
        $obj_writer->write_element('LinksUpToDate', 'false');
        // SharedDoc
        $obj_writer->write_element('SharedDoc', 'false');
        // HyperlinkBase
        $obj_writer->write_element('HyperlinkBase', $spreadsheet->get_properties()->get_hyperlink_base());
        // HyperlinksChanged
        $obj_writer->write_element('HyperlinksChanged', 'false');
        // AppVersion
        $obj_writer->write_element('AppVersion', '12.0000');
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write docProps/core.xml to XML format.
     *
     * @return string XML Output
     */
    public function write_doc_props_core(Spreadsheet $spreadsheet): string
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
        // cp:coreProperties
        $obj_writer->start_element('cp:coreProperties');
        $obj_writer->write_attribute('xmlns:cp', Namespaces::CORE_PROPERTIES2);
        $obj_writer->write_attribute('xmlns:dc', Namespaces::DC_ELEMENTS);
        $obj_writer->write_attribute('xmlns:dcterms', Namespaces::DC_TERMS);
        $obj_writer->write_attribute('xmlns:dcmitype', Namespaces::DC_DCMITYPE);
        $obj_writer->write_attribute('xmlns:xsi', Namespaces::SCHEMA_INSTANCE);
        // dc:creator
        $obj_writer->write_element('dc:creator', $spreadsheet->get_properties()->get_creator());
        // cp:lastModifiedBy
        $obj_writer->write_element('cp:lastModifiedBy', $spreadsheet->get_properties()->get_last_modified_by());
        // dcterms:created
        $obj_writer->start_element('dcterms:created');
        $obj_writer->write_attribute('xsi:type', 'dcterms:W3CDTF');
        $created = $spreadsheet->get_properties()->get_created();
        $date = Date::date_time_from_timestamp("{$created}");
        $obj_writer->write_raw_data($date->format(DATE_W3C));
        $obj_writer->end_element();
        // dcterms:modified
        $obj_writer->start_element('dcterms:modified');
        $obj_writer->write_attribute('xsi:type', 'dcterms:W3CDTF');
        $created = $spreadsheet->get_properties()->get_modified();
        $date = Date::date_time_from_timestamp("{$created}");
        $obj_writer->write_raw_data($date->format(DATE_W3C));
        $obj_writer->end_element();
        // dc:title
        $obj_writer->write_element('dc:title', $spreadsheet->get_properties()->get_title());
        // dc:description
        $obj_writer->write_element('dc:description', $spreadsheet->get_properties()->get_description());
        // dc:subject
        $obj_writer->write_element('dc:subject', $spreadsheet->get_properties()->get_subject());
        // cp:keywords
        $obj_writer->write_element('cp:keywords', $spreadsheet->get_properties()->get_keywords());
        // cp:category
        $obj_writer->write_element('cp:category', $spreadsheet->get_properties()->get_category());
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write docProps/custom.xml to XML format.
     *
     * @return null|string XML Output
     */
    public function write_doc_props_custom(Spreadsheet $spreadsheet): ?string
    {
        $custom_property_list = $spreadsheet->get_properties()->get_custom_properties();
        if (empty($custom_property_list)) {
            return null;
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
        // cp:coreProperties
        $obj_writer->start_element('Properties');
        $obj_writer->write_attribute('xmlns', Namespaces::CUSTOM_PROPERTIES);
        $obj_writer->write_attribute('xmlns:vt', Namespaces::PROPERTIES_VTYPES);
        foreach ($custom_property_list as $key => $custom_property) {
            $property_value = $spreadsheet->get_properties()->get_custom_property_value($custom_property);
            $property_type = $spreadsheet->get_properties()->get_custom_property_type($custom_property);
            $obj_writer->start_element('property');
            $obj_writer->write_attribute('fmtid', '{D5CDD505-2E9C-101B-9397-08002B2CF9AE}');
            $obj_writer->write_attribute('pid', (string) ($key + 2));
            $obj_writer->write_attribute('name', $custom_property);
            switch ($property_type) {
                case Properties::PROPERTY_TYPE_INTEGER:
                    $obj_writer->write_element('vt:i4', (string) $property_value);
                    break;
                case Properties::PROPERTY_TYPE_FLOAT:
                    $obj_writer->write_element('vt:r8', sprintf('%F', $property_value));
                    break;
                case Properties::PROPERTY_TYPE_BOOLEAN:
                    $obj_writer->write_element('vt:bool', $property_value ? 'true' : 'false');
                    break;
                case Properties::PROPERTY_TYPE_DATE:
                    $obj_writer->start_element('vt:filetime');
                    $date = Date::date_time_from_timestamp("{$property_value}");
                    $obj_writer->write_raw_data($date->format(DATE_W3C));
                    $obj_writer->end_element();
                    break;
                default:
                    $obj_writer->write_element('vt:lpwstr', (string) $property_value);
                    break;
            }
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
}