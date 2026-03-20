<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Ods;

use Php_Office\Php_Spreadsheet\Document\Properties as DocumentProperties;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Simple_Xml_Element;
class Properties
{
    public function __construct(private readonly Spreadsheet $spreadsheet)
    {
    }
    /** @param array{meta?: string, office?: string, dc?: string} $namespacesMeta */
    public function load(Simple_Xml_Element $xml, array $namespaces_meta): void
    {
        $doc_props = $this->spreadsheet->get_properties();
        $office_property = $xml->children($namespaces_meta['office'] ?? '');
        foreach ($office_property as $office_property_data) {
            if (isset($namespaces_meta['dc'])) {
                $office_properties_dc = $office_property_data->children($namespaces_meta['dc']);
                $this->set_core_properties($doc_props, $office_properties_dc);
            }
            $office_property_meta = null;
            if (isset($namespaces_meta['dc'])) {
                $office_property_meta = $office_property_data->children($namespaces_meta['meta'] ?? '');
            }
            $office_property_meta ??= [];
            foreach ($office_property_meta as $property_name => $property_value) {
                $this->set_meta_properties($namespaces_meta, $property_value, $property_name, $doc_props);
            }
        }
    }
    private function set_core_properties(Document_Properties $doc_props, Simple_Xml_Element $office_property_dc): void
    {
        foreach ($office_property_dc as $property_name => $property_value) {
            $property_value = (string) $property_value;
            switch ($property_name) {
                case 'title':
                    $doc_props->set_title($property_value);
                    break;
                case 'subject':
                    $doc_props->set_subject($property_value);
                    break;
                case 'creator':
                    $doc_props->set_creator($property_value);
                    $doc_props->set_last_modified_by($property_value);
                    break;
                case 'date':
                    $doc_props->set_modified($property_value);
                    break;
                case 'description':
                    $doc_props->set_description($property_value);
                    break;
            }
        }
    }
    /** @param array{meta?: string, office?: mixed, dc?: mixed} $namespacesMeta */
    private function set_meta_properties(array $namespaces_meta, Simple_Xml_Element $property_value, string $property_name, Document_Properties $doc_props): void
    {
        $property_value_attributes = $property_value->attributes($namespaces_meta['meta'] ?? '');
        $property_value = (string) $property_value;
        switch ($property_name) {
            case 'initial-creator':
                $doc_props->set_creator($property_value);
                break;
            case 'keyword':
                $doc_props->set_keywords($property_value);
                break;
            case 'creation-date':
                $doc_props->set_created($property_value);
                break;
            case 'user-defined':
                $name2 = (string) ($property_value_attributes['name'] ?? '');
                if ($name2 === 'Company') {
                    $doc_props->set_company($property_value);
                } elseif ($name2 === 'category') {
                    $doc_props->set_category($property_value);
                } else {
                    $this->set_user_defined_property($property_value_attributes, $property_value, $doc_props);
                }
                break;
        }
    }
    /** @param iterable<string> $propertyValueAttributes */
    private function set_user_defined_property(iterable $property_value_attributes, string $property_value, Document_Properties $doc_props): void
    {
        $property_value_name = '';
        $property_value_type = Document_Properties::PROPERTY_TYPE_STRING;
        foreach ($property_value_attributes as $key => $value) {
            if ($key == 'name') {
                /** @var scalar $value */
                $property_value_name = (string) $value;
            } elseif ($key == 'value-type') {
                /** @var string $value */
                switch ($value) {
                    case 'date':
                        $property_value = Document_Properties::convert_property($property_value, 'date');
                        $property_value_type = Document_Properties::PROPERTY_TYPE_DATE;
                        break;
                    case 'boolean':
                        $property_value = Document_Properties::convert_property($property_value, 'bool');
                        $property_value_type = Document_Properties::PROPERTY_TYPE_BOOLEAN;
                        break;
                    case 'float':
                        $property_value = Document_Properties::convert_property($property_value, 'r4');
                        $property_value_type = Document_Properties::PROPERTY_TYPE_FLOAT;
                        break;
                    default:
                        $property_value_type = Document_Properties::PROPERTY_TYPE_STRING;
                }
            }
        }
        $doc_props->set_custom_property($property_value_name, $property_value, $property_value_type);
    }
}