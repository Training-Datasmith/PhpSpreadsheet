<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Document\Properties as DocumentProperties;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Simple_Xml_Element;
class Properties
{
    public function __construct(private readonly Xml_Scanner $security_scanner, private readonly Document_Properties $doc_props)
    {
    }
    private function extract_property_data(string $property_data): ?Simple_Xml_Element
    {
        // okay to omit namespace because everything will be processed by xpath
        $obj = simplexml_load_string($this->security_scanner->scan($property_data));
        return $obj === false ? null : $obj;
    }
    public function read_core_properties(string $property_data): void
    {
        $xml_core = $this->extract_property_data($property_data);
        if (is_object($xml_core)) {
            $xml_core->register_x_path_namespace('dc', Namespaces::DC_ELEMENTS);
            $xml_core->register_x_path_namespace('dcterms', Namespaces::DC_TERMS);
            $xml_core->register_x_path_namespace('cp', Namespaces::CORE_PROPERTIES2);
            $this->doc_props->set_creator($this->get_array_item($xml_core->xpath('dc:creator')));
            $this->doc_props->set_last_modified_by($this->get_array_item($xml_core->xpath('cp:lastModifiedBy')));
            $this->doc_props->set_created($this->get_array_item($xml_core->xpath('dcterms:created')));
            //! respect xsi:type
            $this->doc_props->set_modified($this->get_array_item($xml_core->xpath('dcterms:modified')));
            //! respect xsi:type
            $this->doc_props->set_title($this->get_array_item($xml_core->xpath('dc:title')));
            $this->doc_props->set_description($this->get_array_item($xml_core->xpath('dc:description')));
            $this->doc_props->set_subject($this->get_array_item($xml_core->xpath('dc:subject')));
            $this->doc_props->set_keywords($this->get_array_item($xml_core->xpath('cp:keywords')));
            $this->doc_props->set_category($this->get_array_item($xml_core->xpath('cp:category')));
        }
    }
    public function read_extended_properties(string $property_data): void
    {
        $xml_core = $this->extract_property_data($property_data);
        if (is_object($xml_core)) {
            if (isset($xml_core->Company)) {
                $this->doc_props->set_company((string) $xml_core->Company);
            }
            if (isset($xml_core->Manager)) {
                $this->doc_props->set_manager((string) $xml_core->Manager);
            }
            if (isset($xml_core->hyperlink_base)) {
                $this->doc_props->set_hyperlink_base((string) $xml_core->hyperlink_base);
            }
        }
    }
    public function read_custom_properties(string $property_data): void
    {
        $xml_core = $this->extract_property_data($property_data);
        if (is_object($xml_core)) {
            foreach ($xml_core as $xml_property) {
                /** @var SimpleXMLElement $xmlProperty */
                $cell_data_office_attributes = $xml_property->attributes();
                if (isset($cell_data_office_attributes['name'])) {
                    $property_name = (string) $cell_data_office_attributes['name'];
                    $cell_data_office_children = $xml_property->children('http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes');
                    $attribute_type = $cell_data_office_children->get_name();
                    /** @var SimpleXMLElement */
                    $attribute_value = $cell_data_office_children->{$attribute_type};
                    $attribute_value = (string) $attribute_value;
                    $attribute_value = Document_Properties::convert_property($attribute_value, $attribute_type);
                    $attribute_type = Document_Properties::convert_property_type($attribute_type);
                    $this->doc_props->set_custom_property($property_name, $attribute_value, $attribute_type);
                }
            }
        }
    }
    /** @param null|false|scalar[] $array */
    private function get_array_item(null|array|false $array): string
    {
        return is_array($array) ? (string) ($array[0] ?? '') : '';
    }
}